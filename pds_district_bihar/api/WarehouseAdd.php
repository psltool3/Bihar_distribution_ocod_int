<?php

require('../util/Connection.php');
require('../structures/Warehouse.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');
require('../util/Security.php');
require ('../util/Encryption.php');
require('../util/Logger.php');
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

if(!isStringNumber($_POST["actual_storage"]) or !isStringNumber($_POST["factorial"]) or floatval($_POST["actual_storage"]) < 0 or floatval($_POST["factorial"]) < 0){
	echo "Error : Check Actual Storage and Factorial Values (must be positive integer or float)";
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
$actual_storage = $_POST["actual_storage"];
$factorial = $_POST["factorial"];
$storage = floatval($actual_storage) * floatval($factorial);
$warehousetype = $_POST["warehousetype"];
$uniqueid = uniqid("WH_",);


$Warehouse = new Warehouse;
$Warehouse->setUniqueid(substr($uniqueid,0,15));
$Warehouse->setDistrict($district);
$Warehouse->setLatitude($latitude);
$Warehouse->setLongitude($longitude);
$Warehouse->setName($name);
$Warehouse->setId($id);
$Warehouse->setType($type);
$Warehouse->setStorage($storage);
$Warehouse->setActual_storage($actual_storage);
$Warehouse->setFactorial($factorial);
$Warehouse->setWarehousetype($warehousetype);
$Warehouse->setActive("1");

$query_insert_check = $Warehouse->checkInsert($Warehouse);
$query_insert_result = mysqli_query($con, $query_insert_check);
$numrows_insert = mysqli_num_rows($query_insert_result);
if($numrows_insert==0){
	$query = $Warehouse->insert($Warehouse);
	mysqli_query($con, $query);
	mysqli_close($con);
	$filteredPost = $_POST;
	unset($filteredPost['username'], $filteredPost['password']);
	writeLog("district_user ->" ." Warehouse added ->". $_SESSION['district_user'] . "| Requested JSON -> " . json_encode($filteredPost));
	echo "<script>window.location.href = '../Warehouse.php';</script>";
}
else{
	echo "Error : in Insertion as Warehouse id already exist";
}
} 
else{
    echo "Error : Password or Username is incorrect";
}

?>
<?php require('Fullui.php');  ?>