<?php
require('../util/Connection.php');
require('../util/SessionCheck.php');
require('../util/Logger.php');

// Define API credentials as constants for easy configuration by the user
define('DCP_API_USERNAME', 'coop_admin'); 
define('DCP_API_PASSWORD', 'password123');

// Configure execution time and memory limits
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
$year = mysqli_real_escape_string($con, $_POST['year']);

// 1. Find the latest run ID from optimised_table_leg1 for the given month and year
$query = "SELECT id FROM optimised_table_leg1 WHERE month='$month' AND year='$year' ORDER BY last_updated DESC LIMIT 1";
$result = mysqli_query($con, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    echo json_encode(["status" => "error", "message" => "No optimization data found for month: $month, year: $year."]);
    exit;
}

$row = mysqli_fetch_assoc($result);
$run_id = $row['id'];
$tablename = "optimiseddata_leg1_" . $run_id;

// 2. Check if the detail table exists
$checkTableQuery = "SHOW TABLES LIKE '$tablename'";
$checkTableResult = mysqli_query($con, $checkTableQuery);

if (!$checkTableResult || mysqli_num_rows($checkTableResult) === 0) {
    echo json_encode(["status" => "error", "message" => "Optimized route details table ($tablename) does not exist."]);
    exit;
}

// 3. Query all route movement rows from the detail table where the source is 'DCP'
$dataQuery = "SELECT * FROM `$tablename` WHERE `from` = 'DCP'";
$dataResult = mysqli_query($con, $dataQuery);

if (!$dataResult) {
    echo json_encode(["status" => "error", "message" => "Failed to retrieve route optimization details from the database."]);
    exit;
}

$routeData = [];
while ($rowDetail = mysqli_fetch_assoc($dataResult)) {
    // If the admin or district has suggested a new warehouse ID, resolve its coordinates
    $from_id = $rowDetail['from_id'];
    $from_name = $rowDetail['from_name'];
    $from_lat = isset($rowDetail['from_lat']) ? (float)$rowDetail['from_lat'] : 0.0;
    $from_long = isset($rowDetail['from_long']) ? (float)$rowDetail['from_long'] : 0.0;
    $from_district = $rowDetail['from_district'];
    $distance = isset($rowDetail['distance']) ? (float)$rowDetail['distance'] : 0.0;

    if (!empty($rowDetail['new_id_admin'])) {
        $wh_id = $rowDetail['new_id_admin'];
        $whQuery = "SELECT latitude, longitude, district, name FROM `warehouse_leg1_{$run_id}` WHERE id='$wh_id'";
        $whResult = mysqli_query($con, $whQuery);
        if ($whResult && mysqli_num_rows($whResult) > 0) {
            $whRow = mysqli_fetch_assoc($whResult);
            $from_id = $wh_id;
            $from_name = $whRow['name'];
            $from_lat = (float)$whRow['latitude'];
            $from_long = (float)$whRow['longitude'];
            $from_district = $whRow['district'];
        }
        $distance = isset($rowDetail['new_distance_admin']) ? (float)$rowDetail['new_distance_admin'] : 0.0;
    } else if (!empty($rowDetail['new_id_district']) && isset($rowDetail['admin_approve']) && $rowDetail['admin_approve'] === 'yes') {
        $wh_id = $rowDetail['new_id_district'];
        $whQuery = "SELECT latitude, longitude, district, name FROM `warehouse_leg1_{$run_id}` WHERE id='$wh_id'";
        $whResult = mysqli_query($con, $whQuery);
        if ($whResult && mysqli_num_rows($whResult) > 0) {
            $whRow = mysqli_fetch_assoc($whResult);
            $from_id = $wh_id;
            $from_name = $whRow['name'];
            $from_lat = (float)$whRow['latitude'];
            $from_long = (float)$whRow['longitude'];
            $from_district = $whRow['district'];
        }
        $distance = isset($rowDetail['new_distance_district']) ? (float)$rowDetail['new_distance_district'] : 0.0;
    }

    $routeData[] = [
        "scenario" => isset($rowDetail['scenario']) ? (string)$rowDetail['scenario'] : "",
        "from" => isset($rowDetail['from']) ? (string)$rowDetail['from'] : "",
        "from_state" => isset($rowDetail['from_state']) ? (string)$rowDetail['from_state'] : "",
        "from_id" => (string)$from_id,
        "from_name" => (string)$from_name,
        "from_district" => (string)$from_district,
        "from_lat" => $from_lat,
        "from_long" => $from_long,
        "to" => isset($rowDetail['to']) ? (string)$rowDetail['to'] : "",
        "to_state" => isset($rowDetail['to_state']) ? (string)$rowDetail['to_state'] : "",
        "to_id" => isset($rowDetail['to_id']) ? (string)$rowDetail['to_id'] : "",
        "to_name" => isset($rowDetail['to_name']) ? (string)$rowDetail['to_name'] : "",
        "to_district" => isset($rowDetail['to_district']) ? (string)$rowDetail['to_district'] : "",
        "to_lat" => isset($rowDetail['to_lat']) ? (float)$rowDetail['to_lat'] : 0.0,
        "to_long" => isset($rowDetail['to_long']) ? (float)$rowDetail['to_long'] : 0.0,
        "commodity" => isset($rowDetail['commodity']) ? (string)$rowDetail['commodity'] : "",
        "quantity" => isset($rowDetail['quantity']) ? (float)$rowDetail['quantity'] : 0.0,
        "distance" => $distance,
        "status" => isset($rowDetail['status']) ? (string)$rowDetail['status'] : ""
    ];
}

mysqli_free_result($dataResult);
mysqli_close($con);

if (empty($routeData)) {
    echo json_encode(["status" => "error", "message" => "No DCP route data records found in table: $tablename."]);
    exit;
}

// 4. Authenticate to get OAuth2 Bearer token
$tokenUrl = "https://cooponline.bihar.gov.in/DCPApi/token";
$tokenCh = curl_init($tokenUrl);
curl_setopt($tokenCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($tokenCh, CURLOPT_POST, true);
curl_setopt($tokenCh, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'password',
    'username' => DCP_API_USERNAME,
    'password' => DCP_API_PASSWORD
]));
curl_setopt($tokenCh, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);
curl_setopt($tokenCh, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($tokenCh, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($tokenCh, CURLOPT_TIMEOUT, 30);

$tokenResponseStr = curl_exec($tokenCh);
$tokenHttpCode = curl_getinfo($tokenCh, CURLINFO_HTTP_CODE);
$tokenError = curl_error($tokenCh);
curl_close($tokenCh);

if ($tokenResponseStr === false) {
    writeLog("Error -> Push DCP Details Token Request Failed | cURL Error: $tokenError");
    echo json_encode(["status" => "error", "message" => "Authentication cURL Failed: " . $tokenError]);
    exit;
}

$tokenResponse = json_decode($tokenResponseStr, true);
if ($tokenHttpCode !== 200 || !isset($tokenResponse['access_token'])) {
    $errMessage = isset($tokenResponse['error_description']) ? $tokenResponse['error_description'] : (isset($tokenResponse['error']) ? $tokenResponse['error'] : "HTTP Code $tokenHttpCode");
    writeLog("Error -> Push DCP Details Token Auth Denied | Response: $tokenResponseStr");
    echo json_encode(["status" => "error", "message" => "Authentication Failed: " . $errMessage]);
    exit;
}

$accessToken = $tokenResponse['access_token'];

// 5. Send POST request to push endpoint with the retrieved Bearer token
$jsonPayload = json_encode($routeData);

$apiUrl = "http://cooponline.bihar.gov.in/DCPApi/Warehouse/PushDCPDetails";
$ch = curl_init($apiUrl);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Content-Length: ' . strlen($jsonPayload),
    "Authorization: Bearer $accessToken"
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);

$apiResponse = curl_exec($ch);
$httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$usernameLog = isset($_SESSION['user']) ? $_SESSION['user'] : 'unknown';

if ($apiResponse === false) {
    writeLog("Error -> Push DCP Details API Failed | Month: $month, Year: $year | cURL Error: $curlError | User: $usernameLog");
    echo json_encode(["status" => "error", "message" => "cURL Push Request Failed: " . $curlError]);
} else {
    writeLog("Response -> Push DCP Details API Response | Month: $month, Year: $year | HTTP: $httpStatusCode | Response: $apiResponse | User: $usernameLog");
    if ($httpStatusCode >= 200 && $httpStatusCode < 300) {
        echo json_encode(["status" => "success", "message" => "Successfully pushed " . count($routeData) . " records to Coop Online. Response: " . $apiResponse]);
    } else {
        echo json_encode(["status" => "error", "message" => "API returned HTTP status code $httpStatusCode. Response: $apiResponse"]);
    }
}
?>
