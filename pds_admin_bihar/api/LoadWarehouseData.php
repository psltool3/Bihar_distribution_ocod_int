<?php
// Disable timeouts (can run for several minutes)
@set_time_limit(0);
@ini_set('max_execution_time', '0');

require('../util/Connection.php');
require('../structures/Warehouse.php');
require('../util/SessionFunction.php');
require('../util/SessionCheck.php');
require('../util/Logger.php');
require('../util/Security.php');
require('Header.php');

function formatName($name)
{
    if (!$name) return '';
    $name = preg_replace('/[^a-zA-Z0-9_ ]/', '', $name);
    $name = ucwords(strtolower($name));
    return trim($name);
}

function isValidCoordinate($value, $type)
{
    if ($value === null || $value === '')
        return false;
    if (!is_numeric($value))
        return false;
    $v = (float) $value;
    return $type === 'latitude' ? ($v >= -90 && $v <= 90) : ($v >= -180 && $v <= 180);
}

// Current month & year (send as strings to match API requirement)
$currentMonth = (string) date('n');
$currentYear = (string) date('Y');

$apiData = [
    'month' => $currentMonth,
    'year' => $currentYear
];

// Bihar API endpoint
$apiUrl = 'https://scm.bihar.gov.in/Metadata/api/metadata/mlswidemetadatanew';

// Initialize cURL (no timeout)
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_CONNECTTIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($apiData),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    CURLOPT_PROXY => ''
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);
curl_close($curl);

if ($error) {
    echo "Error connecting to API: " . $error . "\n";
    exit();
}
if ($httpCode !== 200) {
    echo "API returned error code: " . $httpCode . "\n";
    exit();
}

$apiResponse = json_decode($response, true);
if (!$apiResponse || !is_array($apiResponse)) {
    echo "Invalid API response or API returned error.\n";
    exit();
}

// Check for old format error code if present
if (isset($apiResponse['rep_code']) && $apiResponse['rep_code'] !== '200') {
    echo "API returned error code: " . $apiResponse['rep_code'] . "\n";
    exit();
}

// Support details, data, and direct array response formats
if (isset($apiResponse['data']) && is_array($apiResponse['data'])) {
    $warehouseData = $apiResponse['data'];
} elseif (isset($apiResponse['details']) && is_array($apiResponse['details'])) {
    $warehouseData = $apiResponse['details'];
} else {
    $warehouseData = $apiResponse; // Direct array response
}

if (empty($warehouseData)) {
    echo "No warehouse data found for $currentMonth/$currentYear.\n";
    exit();
}

// Clear existing data before pushing fresh data to prevent duplicates
mysqli_query($con, "TRUNCATE TABLE warehouse");

$insertedCount = 0;
$errorCount = 0;

foreach ($warehouseData as $data) {
    try {
        if (empty($data['id']) || empty($data['name']) || empty($data['district'])) {
            $errorCount++;
            continue;
        }

        $warehouse = new Warehouse;
        $warehouse->setDistrict(formatName($data['district'] ?? ''));
        $warehouse->setName(formatName($data['name'] ?? ''));
        $warehouse->setId($data['id'] ?? '');
        $warehouse->setWarehousetype($data['type'] ?? 'MLSP');
        $blockType = $data['block'] ?? '';
        if (empty($blockType) || strtolower(trim($blockType)) === 'na' || strtolower(trim($blockType)) === 'n/a') {
            $blockType = 'Motorable';
        }
        $warehouse->setType($blockType);

        $lat = isset($data['latitude']) && is_numeric($data['latitude']) ? $data['latitude'] : 0;
        $lon = isset($data['longitude']) && is_numeric($data['longitude']) ? $data['longitude'] : 0;
        $warehouse->setLatitude($lat);
        $warehouse->setLongitude($lon);

        $warehouse->setStorage($data['storage'] ?? 0);
        $warehouse->setUniqueid(substr(uniqid("WH_"), 0, 15));
        $warehouse->setActive($data['active'] ?? '1');

        $insertQuery = $warehouse->insert($warehouse);
        if (mysqli_query($con, $insertQuery)) {
            $insertedCount++;
            writeLog("User -> " . ($_SESSION['user'] ?? 'SYSTEM') .
                " | Warehouse loaded from API -> " . ($data['name'] ?? '') .
                " | District -> " . ($data['district'] ?? ''));
        } else {
            $errorCount++;
        }

    } catch (Exception $e) {
        $errorCount++;
        continue;
    }
}

mysqli_close($con);

// Plain text summary (no scripts)
echo "Data Load Complete\n";
echo "-------------------------\n";
echo "New records inserted : $insertedCount\n";
echo "Records with errors  : $errorCount\n";
echo "-------------------------\n";
echo "Source: depotwisemetadata | Period: " . date('F Y') . "\n";

// Redirect to Warehouse page after completion
echo "<script type='text/javascript'>";
echo "setTimeout(function() {";
echo "window.location.href = '../Warehouse.php';";
echo "}, 3000);"; // Wait 3 seconds to show the summary
echo "</script>";

require('Fullui.php');
?>
