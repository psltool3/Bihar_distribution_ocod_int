<?php
// Disable timeouts (can run for several minutes)
@set_time_limit(0);
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '1024M');

require('../util/Connection.php');
require('../structures/FPS.php');
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
$apiUrl = 'https://scm.bihar.gov.in/Metadata/api/metadata/shopwidemetadatanew';

// Initialize cURL (higher timeout because response size is 12MB+)
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 240,
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
    $fpsData = $apiResponse['data'];
} elseif (isset($apiResponse['details']) && is_array($apiResponse['details'])) {
    $fpsData = $apiResponse['details'];
} else {
    $fpsData = $apiResponse; // Direct array response
}

if (empty($fpsData)) {
    echo "No FPS data found for $currentMonth/$currentYear.\n";
    exit();
}

// Clear existing data before pushing fresh data to prevent duplicates
mysqli_query($con, "TRUNCATE TABLE fps");

$insertedCount = 0;
$errorCount = 0;

foreach ($fpsData as $data) {
    try {
        if (empty($data['id']) || empty($data['name']) || empty($data['district'])) {
            $errorCount++;
            continue;
        }

        $fps = new FPS;
        $fps->setDistrict(formatName($data['district'] ?? ''));
        $fps->setName($data['name'] ?? '');
        $fps->setId($data['id'] ?? '');
        $fps->setType($data['type'] ?? 'Normal FPS');

        $lat = isset($data['latitude']) && is_numeric($data['latitude']) ? $data['latitude'] : 0;
        $lon = isset($data['longitude']) && is_numeric($data['longitude']) ? $data['longitude'] : 0;
        $fps->setLatitude($lat);
        $fps->setLongitude($lon);

        $wheatDemand = 0;
        $friceDemand = 0;

        if (isset($data['demands']) && is_array($data['demands'])) {
            foreach ($data['demands'] as $d) {
                $commodity = $d['commodity'] ?? '';
                if (stripos($commodity, 'Wheat') !== false) {
                    $wheatDemand = $d['demand'] ?? 0;
                } elseif (stripos($commodity, 'Rice') !== false || stripos($commodity, 'frice') !== false) {
                    $friceDemand = $d['demand'] ?? 0;
                }
            }
        } else {
            $wheatDemand = $data['demand'] ?? 0;
            $friceDemand = $data['demand_rice'] ?? 0;
        }

        $fps->setDemand($wheatDemand);
        $fps->setDemandrice($friceDemand);
        $fps->setUniqueid(substr(uniqid("FPS_"), 0, 15));
        $fps->setActive($data['active'] ?? '1');

        $insertQuery = $fps->insert($fps);
        if (mysqli_query($con, $insertQuery)) {
            $insertedCount++;
            writeLog("User -> " . ($_SESSION['user'] ?? 'SYSTEM') .
                " | FPS loaded from API -> " . ($data['name'] ?? '') .
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
echo "Source: shopwidemetadata | Period: " . date('F Y') . "\n";

// Redirect to FPS page after completion
echo "<script type='text/javascript'>";
echo "setTimeout(function() {";
echo "window.location.href = '../FPS.php';";
echo "}, 3000);"; // Wait 3 seconds to show the summary
echo "</script>";

require('Fullui.php');
?>
