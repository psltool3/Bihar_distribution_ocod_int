<?php

require('../util/Connection.php');
require('../structures/District.php');
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


$person = new Login;
$person->setUsername($_POST["username"]);

$Encryption = new Encryption();
$person->setPassword($Encryption->decrypt($_POST["password"], $nonceValue));


if($_SESSION['user']!=$person->getUsername()){
	echo "User is logged in with different username and password";
	return;
}

$query = "SELECT * FROM login WHERE username='".$person->getUsername()."'";
$result = mysqli_query($con,$query);
$row = mysqli_fetch_assoc($result);

$dbHashedPassword = $row['password'];
if(password_verify($person->getPassword(), $dbHashedPassword)){
	$District = new District;
	$District->setId($_POST['uid']);

$log_query = $District->logname($District);
$log_result = mysqli_query($con,$log_query);
if ($log_result && $row = $log_result->fetch_assoc()) {
		$log_name =  $row['name'];
}


	$query = $District->delete($District);
	
	$log_query = $District->logname($District);
	$log_result = mysqli_query($con,$log_query);
	if ($log_result && $row = $log_result->fetch_assoc()) {
		$log_name =  $row['name'];
	}

	mysqli_query($con,$query);
	mysqli_close($con);
	
$filteredPost = $_POST;
unset($filteredPost['username'], $filteredPost['password']);
writeLog("User ->" ." District deleted -> ". $_SESSION['user'] . "| Requested JSON -> " . json_encode($filteredPost) . " | " . $log_name);


	echo "<script>window.location.href = '../District.php';</script>";
}else{
	echo "Password or Username is incorrect" ;
}

?>
<?php require('Fullui.php');  ?>