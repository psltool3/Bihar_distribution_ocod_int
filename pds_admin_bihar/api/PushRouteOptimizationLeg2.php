<?php
require('../util/Connection.php');
require('../util/SessionCheck.php');
require('../util/Logger.php');

// Increase script execution time and memory limits for large datasets
ini_set('memory_limit', '1G');
set_time_limit(600); // 10 minutes

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
    exit;
}

if (!isset($_POST['month']) || !isset($_POST['year'])) {
    echo json_encode(["status" => "error", "message" => "Month and year are required parameters."]);
    exit;
}

$month = mysqli_real_escape_string($con, $_POST['month']);
$year  = mysqli_real_escape_string($con, $_POST['year']);

// 1. Find the latest run ID from optimised_table (Leg 2) for the given month and year
$query  = "SELECT id, applicable FROM optimised_table WHERE month='$month' AND year='$year' ORDER BY last_updated DESC LIMIT 1";
$result = mysqli_query($con, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    echo json_encode(["status" => "error", "message" => "No Leg-2 optimization data found for month: $month, year: $year."]);
    exit;
}

$row             = mysqli_fetch_assoc($result);
$id              = $row['id'];
$applicableMonth = !empty($row['applicable']) ? $row['applicable'] : $month;
$tablename       = "optimiseddata_" . $id;

// 2. Check if the detail table exists
$checkTableQuery  = "SHOW TABLES LIKE '$tablename'";
$checkTableResult = mysqli_query($con, $checkTableQuery);

if (!$checkTableResult || mysqli_num_rows($checkTableResult) === 0) {
    echo json_encode(["status" => "error", "message" => "Optimized route details table ($tablename) does not exist."]);
    exit;
}

// 2.5 Check if all tags have been approved by the State Admin
$unapprovedQuery = "SELECT COUNT(*) as cnt FROM `$tablename` WHERE approve_admin IS NULL OR approve_admin = ''";
$unapprovedResult = mysqli_query($con, $unapprovedQuery);
if ($unapprovedResult) {
    $unapprovedRow = mysqli_fetch_assoc($unapprovedResult);
    if ($unapprovedRow['cnt'] > 0) {
        echo json_encode(["status" => "error", "message" => "All tags must be approved by the State Admin before pushing to SCM. (" . $unapprovedRow['cnt'] . " rows pending)"]);
        exit;
    }
}

// Helper: resolve month string to numeric month
function getMonthNumber($monthStr) {
    if (is_numeric($monthStr)) {
        return (int)$monthStr;
    }
    $monthStr = strtolower(trim($monthStr));
    $months = [
        'jan' => 1, 'january'   => 1,
        'feb' => 2, 'february'  => 2,
        'mar' => 3, 'march'     => 3,
        'apr' => 4, 'april'     => 4,
        'may' => 5,
        'jun' => 6, 'june'      => 6,
        'jul' => 7, 'july'      => 7,
        'aug' => 8, 'august'    => 8,
        'sep' => 9, 'sept'      => 9, 'september' => 9,
        'oct' => 10, 'october'  => 10,
        'nov' => 11, 'november' => 11,
        'dec' => 12, 'december' => 12,
    ];
    return isset($months[$monthStr]) ? $months[$monthStr] : 0;
}

// Helper: get commodity code (Bihar uses rice/wheat; default 98 for rice)
function getCommodityCode($commodityName) {
    $name = strtolower(trim($commodityName));
    if (strpos($name, 'wheat') !== false) {
        return 101;
    }
    // default: fortified rice = 98
    return 98;
}

// Fetch district code mapping
$districtMap = [];
$distRes = mysqli_query($con, "SELECT id, name FROM districts");
if ($distRes) {
    while ($distRow = mysqli_fetch_assoc($distRes)) {
        $districtMap[strtolower(trim($distRow['name']))] = $distRow['id'];
    }
}

function getDistrictCode($districtName, $districtMap) {
    $cleanName = strtolower(trim($districtName));
    if (isset($districtMap[$cleanName])) {
        return (string)$districtMap[$cleanName];
    }
    foreach ($districtMap as $name => $id) {
        if (strpos($cleanName, $name) !== false || strpos($name, $cleanName) !== false) {
            return (string)$id;
        }
    }
    return "0";
}

function getParentDistrictCode($id) {
    $id = trim((string)$id);
    if (strlen($id) === 7) {
        // Warehouse ID: first 3 digits represent district code
        return substr($id, 0, 3);
    } elseif (strlen($id) === 12) {
        // Shop ID: starts with '1' followed by 3-digit district code
        return substr($id, 1, 3);
    }
    return null;
}

// 3. Query all Leg-2 route rows from the detail table
$dataQuery  = "SELECT * FROM `$tablename`";
$dataResult = mysqli_query($con, $dataQuery);

if (!$dataResult) {
    echo json_encode(["status" => "error", "message" => "Failed to retrieve Leg-2 route optimization details from the database."]);
    exit;
}

$routeData = [];

while ($rowDetail = mysqli_fetch_assoc($dataResult)) {
    // Resolve warehouse overrides: admin override takes priority, then approved district override
    if (!empty($rowDetail['new_id_admin'])) {
        $wh_id            = $rowDetail['new_id_admin'];
        $query_warehouse  = "SELECT latitude, longitude, district, name FROM `warehouse` WHERE id='$wh_id'";
        $result_warehouse = mysqli_query($con, $query_warehouse);
        if ($result_warehouse && mysqli_num_rows($result_warehouse) > 0) {
            $row_warehouse              = mysqli_fetch_assoc($result_warehouse);
            $rowDetail['from_lat']      = $row_warehouse['latitude'];
            $rowDetail['from_long']     = $row_warehouse['longitude'];
            $rowDetail['from_district'] = $row_warehouse['district'];
            $rowDetail['from_name']     = $row_warehouse['name'];
        }
        $rowDetail['from_id'] = $rowDetail['new_id_admin'];
        $rowDetail['distance'] = $rowDetail['new_distance_admin'];
    } elseif (!empty($rowDetail['new_id_district']) && isset($rowDetail['district_change_approve']) && $rowDetail['district_change_approve'] === 'yes') {
        $wh_id            = $rowDetail['new_id_district'];
        $query_warehouse  = "SELECT latitude, longitude, district, name FROM `warehouse` WHERE id='$wh_id'";
        $result_warehouse = mysqli_query($con, $query_warehouse);
        if ($result_warehouse && mysqli_num_rows($result_warehouse) > 0) {
            $row_warehouse              = mysqli_fetch_assoc($result_warehouse);
            $rowDetail['from_lat']      = $row_warehouse['latitude'];
            $rowDetail['from_long']     = $row_warehouse['longitude'];
            $rowDetail['from_district'] = $row_warehouse['district'];
            $rowDetail['from_name']     = $row_warehouse['name'];
        }
        $rowDetail['from_id'] = $rowDetail['new_id_district'];
        $rowDetail['distance'] = $rowDetail['new_distance_district'];
    }

    $comm     = isset($rowDetail['commodity'])     ? trim((string)$rowDetail['commodity'])     : 'Rice';
    $fromDist = isset($rowDetail['from_district']) ? trim((string)$rowDetail['from_district']) : '';
    $toDist   = isset($rowDetail['to_district'])   ? trim((string)$rowDetail['to_district'])   : '';
    $from_id  = isset($rowDetail['from_id'])       ? trim((string)$rowDetail['from_id'])       : '';
    $to_id    = isset($rowDetail['to_id'])         ? trim((string)$rowDetail['to_id'])         : '';

    $from_district_code = getParentDistrictCode($from_id);
    if ($from_district_code === null) {
        $from_district_code = getDistrictCode($fromDist, $districtMap);
    }

    $to_district_code = getParentDistrictCode($to_id);
    if ($to_district_code === null) {
        $to_district_code = getDistrictCode($toDist, $districtMap);
    }

    $routeData[] = [
        "commodity"          => $comm,
        "commodity_code"     => getCommodityCode($comm),
        "distance"           => isset($rowDetail['distance'])   ? trim((string)$rowDetail['distance'])   : '0',
        "from"               => isset($rowDetail['from'])       ? trim((string)$rowDetail['from'])       : '',
        "from_district"      => $fromDist,
        "from_district_code" => $from_district_code,
        "from_id"            => $from_id,
        "from_lat"           => isset($rowDetail['from_lat'])   ? trim((string)$rowDetail['from_lat'])   : '0',
        "from_long"          => isset($rowDetail['from_long'])  ? trim((string)$rowDetail['from_long'])  : '0',
        "from_name"          => isset($rowDetail['from_name'])  ? trim((string)$rowDetail['from_name'])  : '',
        "from_state"         => isset($rowDetail['from_state']) ? str_replace(' ', '_', trim((string)$rowDetail['from_state'])) : 'Bihar',
        "quantity"           => isset($rowDetail['quantity'])   ? (double)$rowDetail['quantity']         : 0.0,
        "scenario"           => isset($rowDetail['scenario'])   ? trim((string)$rowDetail['scenario'])   : '',
        "status"             => !empty($rowDetail['status'])    ? trim((string)$rowDetail['status'])      : 'Implemented',
        "to"                 => isset($rowDetail['to'])         ? trim((string)$rowDetail['to'])         : '',
        "to_district"        => $toDist,
        "to_district_code"   => $to_district_code,
        "to_id"              => $to_id,
        "to_lat"             => isset($rowDetail['to_lat'])     ? trim((string)$rowDetail['to_lat'])     : '0',
        "to_long"            => isset($rowDetail['to_long'])    ? trim((string)$rowDetail['to_long'])    : '0',
        "to_name"            => isset($rowDetail['to_name'])    ? trim((string)$rowDetail['to_name'])    : '',
        "to_state"           => isset($rowDetail['to_state'])   ? str_replace(' ', '_', trim((string)$rowDetail['to_state'])) : 'Bihar',
    ];
}

mysqli_free_result($dataResult);
mysqli_close($con);

if (empty($routeData)) {
    echo json_encode(["status" => "error", "message" => "No Leg-2 route data records found in table: $tablename."]);
    exit;
}

// 4. Group by destination district and send in chunks of 250
$monthNum          = getMonthNumber($month);
$applicableMonthNum = getMonthNumber($applicableMonth);

$chunks = [];
foreach ($routeData as $row) {
    $districtKey = !empty($row['to_district']) ? $row['to_district'] : 'Unknown';
    $chunks[$districtKey][] = $row;
}
$totalChunks = count($chunks);

$username = isset($_SESSION['user']) ? $_SESSION['user'] : 'unknown';
writeLog("User -> Push Route Optimization Leg2 API Started | Month: $month, Year: $year | Total Count: " . count($routeData) . " | Districts (Chunks): $totalChunks | User: $username");

$apiUrl      = "https://scm.bihar.gov.in/Metadata/api/metadata/OptimisedData";
$hasError    = false;
$errorMsg    = "";
$allResponses = [];

foreach ($chunks as $districtName => $districtData) {
    $subChunks     = array_chunk($districtData, 250);
    $totalSubChunks = count($subChunks);

    foreach ($subChunks as $subIndex => $chunk) {
        $payload = [
            "month"      => (string)$applicableMonthNum,
            "status"     => "success",
            "total_rows" => (int)count($chunk),
            "year"       => (string)$year,
            "data"       => $chunk,
        ];

        $jsonPayload = json_encode($payload);
        $gzPayload   = gzencode($jsonPayload, 9);

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $gzPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Encoding: gzip',
            'Accept: application/json',
            'Content-Length: ' . strlen($gzPayload),
        ]);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $apiResponse    = curl_exec($ch);
        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError      = curl_error($ch);
        curl_close($ch);

        $logPartLabel = $totalSubChunks > 1 ? " (Part " . ($subIndex + 1) . "/$totalSubChunks)" : "";

        if ($apiResponse === false) {
            $hasError  = true;
            $errorMsg .= "District $districtName$logPartLabel cURL Error: $curlError; ";
            writeLog("Error -> Push Route Optimization Leg2 API Failed | District $districtName$logPartLabel | Month: $month, Year: $year | cURL Error: $curlError");
        } else {
            writeLog("Response -> Push Route Optimization Leg2 API Response | District $districtName$logPartLabel | Month: $month, Year: $year | HTTP: $httpStatusCode | Response: $apiResponse");

            if ($httpStatusCode >= 200 && $httpStatusCode < 300) {
                $responseList = json_decode($apiResponse, true);
                if (is_array($responseList)) {
                    $subChunkHasError = false;
                    foreach ($responseList as $respItem) {
                        $code = isset($respItem['code'])        ? $respItem['code']        : (isset($respItem['respCode'])    ? $respItem['respCode']    : '');
                        $msg  = isset($respItem['message'])     ? $respItem['message']     : (isset($respItem['respMessage']) ? $respItem['respMessage'] : '');
                        // Treat 200, 101, 166 as success; anything else with error message as failure
                        if ($code !== '200' && $code !== 200 && $code !== '101' && $code !== 101 && $code !== '166' && $code !== 166 && !empty($code)) {
                            if (strpos(strtoupper($msg), 'ERROR-NOT-INSERTED') === false && strpos(strtoupper($msg), 'ALREADY EXIST') === false) {
                                $subChunkHasError = true;
                            }
                        }
                    }
                    if ($subChunkHasError) {
                        $hasError  = true;
                        $errorMsg .= "District $districtName$logPartLabel SCM Alert: " . trim($apiResponse) . "; ";
                    }
                }
                $allResponses[] = $apiResponse;
            } else {
                $hasError  = true;
                $errorMsg .= "District $districtName$logPartLabel failed with HTTP $httpStatusCode: $apiResponse; ";
            }
        }
    }
}

if ($hasError) {
    echo json_encode(["status" => "error", "message" => trim($errorMsg, "; ")]);
} else {
    echo json_encode([
        "status"  => "success",
        "message" => "All $totalChunks districts pushed successfully for Leg-2. Last response: " . end($allResponses),
    ]);
}
?>
