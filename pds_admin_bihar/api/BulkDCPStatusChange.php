<?php

require('../util/Connection.php');
require('../structures/DCP.php');
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
	$district = $_POST["district"];
	$status = $_POST["status"];

	if($status=='active'){
		$query = "UPDATE dcp SET active='1' WHERE district='$district'";
		writeLog("User ->" ." DCP Active -> ". $_SESSION['user'] . "| " . $district);
	}
	else{
		$query = "UPDATE dcp SET active='0' WHERE district='$district'";
		writeLog("User ->" ." DCP InActive -> ". $_SESSION['user'] . "| " . $district);
	}
	mysqli_query($con, $query);
	echo "<script>window.location.href = '../DCP.php';</script>";
} 
else{
    echo "Error : Password or Username is incorrect";
}


?>
<?php require('Fullui.php');  ?>