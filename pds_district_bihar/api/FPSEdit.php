<?php

require('../util/Connection.php');
require('../structures/FPS.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');
require('../util/Logger.php');
require('../util/Security.php');
require ('../util/Encryption.php');
$nonceValue = 'nonce_value';

if(!SessionCheck()){
	return;
}

require('Header.php');

function formatName($name) {
    $name = preg_replace('/[^a-zA-Z ]/', '', $name);
    $name = ucwords(strtolower($name));
    return trim($name);
}

function isValidCoordinate($value, $coordinateType) {
    // Check if the value is a number and not a string
    if (!is_numeric($value)) {
        return false;
    }
	
    // Convert the value to a float
    $coordinate = floatval($value);

    // Check if it's latitude or longitude and validate within the range
    switch ($coordinateType) {
        case 'latitude':
            return ($coordinate >= -90 && $coordinate <= 90);
        case 'longitude':
            return ($coordinate >= -180 && $coordinate <= 180);
        default:
            return false;
    }
}

function isStringNumber($stringValue) {
    return is_numeric($stringValue);
}

$person = new Login;
$person->setUsername($_POST["username"]);
$Encryption = new Encryption();
$person->setPassword($Encryption->decrypt($_POST["password"], $nonceValue));

if($_SESSION['district_user']!=$person->getUsername()){
	echo "User is logged in with different username and password";
	return;
}

$query = "SELECT * FROM login WHERE username='".$person->getUsername()."'";
$result = mysqli_query($con,$query);
$row = mysqli_fetch_assoc($result);

if(!isValidCoordinate($_POST["latitude"],'latitude') or !isValidCoordinate($_POST["longitude"],'longitude')){
	echo "Error : Check Latitude and Longitude Value";
	exit();
}

if(!isStringNumber($_POST["demand"])){
	echo "Error : Check DemandFRice Value";
	exit();
}

if(!isStringNumber($_POST["demand_rice"])){
	echo "Error : Check DemandRice Value";
	exit();
}

$dbHashedPassword = $row['password'];
if(password_verify($person->getPassword(), $dbHashedPassword)){
if(!preg_match('/^[a-zA-Z0-9 ]+$/', $_POST["id"])){
	echo "Error : ID must contain only numbers, alphabets, and spaces";
	exit();
}
$urlPattern = '/https?:\/\/|www\.|[a-zA-Z0-9.\-]+\.(com|org|net|in|co|gov|nic)\b/i';
if(preg_match($urlPattern, $_POST["name"])){
	echo "Error : Name cannot contain links or URLs";
	exit();
}
if(preg_match($urlPattern, $_POST["id"])){
	echo "Error : ID cannot contain links or URLs";
	exit();
}
if(!preg_match('/^[a-zA-Z0-9_ \-()\/.]*$/', $_POST["name"])){
	echo "Error : Name can only contain letters, numbers, spaces, and safe symbols (-, ., (, ), /, _)";
	exit();
}
if(!preg_match('/^[a-zA-Z0-9_ \-()\/.]*$/', $_POST["type"])){
	echo "Error : Type can only contain letters, numbers, spaces, and safe symbols (-, ., (, ), /, _)";
	exit();
}
if(isset($_POST["district"]) && !preg_match('/^[a-zA-Z0-9_ \-()\/.]*$/', $_POST["district"])){
	echo "Error : District can only contain letters, numbers, spaces, and safe symbols (-, ., (, ), /, _)";
	exit();
}
$district = formatName($_POST["district"]);
$latitude = $_POST["latitude"];
$longitude = $_POST["longitude"];
$name = formatName($_POST["name"]);
$id = $_POST["id"];
$type = formatName($_POST["type"]);
$demand = $_POST["demand"];
$demand_rice = $_POST["demand_rice"];
$uniqueid = $_POST["uniqueid"];
$active = $_POST["active"];

$FPS = new FPS;
$FPS->setUniqueid($uniqueid);
$FPS->setDistrict($district);
$FPS->setLatitude($latitude);
$FPS->setLongitude($longitude);
$FPS->setName($name);
$FPS->setId($id);
$FPS->setType($type);
$FPS->setDemand($demand);
$FPS->setDemandRice($demand_rice);
$FPS->setActive($active);

$query = $FPS->update($FPS);

mysqli_query($con, $query);

mysqli_close($con);

$filteredPost = $_POST;
unset($filteredPost['username'], $filteredPost['password']);
writeLog("district_user ->" ." FPS Edit ->". $_SESSION['district_user'] . "| Requested JSON -> " . json_encode($filteredPost));

echo "<script>window.location.href = '../FPS.php';</script>";
} 
else{
    echo "Error : Password or Username is incorrect";
}
?>
<?php require('Fullui.php');  ?>