<?php

require('../util/Connection.php');
require('../structures/Warehouse.php');
require('../util/SessionFunction.php');
require('../util/Security.php');
require ('../util/Encryption.php');
require('../util/Logger.php');
$nonceValue = 'nonce_value';

require('../structures/Login.php');

if(!SessionCheck()){
	return;
}

require('Header.php');

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
$numrows = mysqli_num_rows($result);

if($numrows == 0){
	echo "Error : Password or Username is incorrect";
	return;
}

$dbHashedPassword = $row['password'];
if(password_verify($person->getPassword(), $dbHashedPassword)){
	$Warehouse = new Warehouse;
	$log_name = 'all';
	$Warehouse->setUniqueid($_POST['uid']);

	$query = $Warehouse->delete($Warehouse);

	if($_POST['uid']=="all"){
		$query = $Warehouse->deleteall($Warehouse);
	}
	$log_query = $Warehouse->logname($Warehouse); 
	$log_name= "all"; 
	$log_result = mysqli_query($con,$log_query); 
	if ($log_result && $row = $log_result->fetch_assoc()) { 
		$log_name =  $row['name']; 
		}
		
	$filteredPost = $_POST;
	unset($filteredPost['username'], $filteredPost['password']);
	writeLog("district_user ->" ." Warehouse deleted -> ". $_SESSION['district_user'] . "| Requested JSON -> " . json_encode($filteredPost) . " | " . $log_name);

	mysqli_query($con,$query);
	mysqli_close($con);
	echo "<script>window.location.href = '../Warehouse.php';</script>";
} 
else{
    echo "Error : Password or Username is incorrect";
}



?>
<?php require('Fullui.php');  ?>