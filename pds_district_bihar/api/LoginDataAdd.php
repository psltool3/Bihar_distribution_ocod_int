<?php
require('../util/Connection.php');
require('../structures/Login.php');
require('../util/SessionFunction.php');

if(!SessionCheck()){
	return;
}

require('Header.php');

$person = new Login;
$person->setUsername($_POST["username"]);
$person->setPassword($_POST["password"]);

if($_SESSION['district_user']!=$person->getUsername()){
	echo "User is logged in with different username and password";
	return;
}

$query = "SELECT * FROM login WHERE username='".$person->getUsername()."' AND password='".$person->getPassword()."'";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);

if($numrows == 0){
	echo "Error : Password or Username is incorrect";
	return;
}


$person = new Login;
$person->setUsername($_POST["newusername"]);
$person->setPassword($_POST["newpassword"]);
$person->setRole($_POST["district"]);
$uid = uniqid();

$query = "SELECT * FROM login WHERE username='".$person->getUsername()."'";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);

if($numrows == 1){
	echo "Error : Username already exist";
}
else if($numrows == 0){
	$query1 = "INSERT INTO login (username,password,uid,role,verified) VALUES ('".$person->getUsername()."','".$person->getPassword()."','$uid','".strtolower($person->getRole())."','0')";
	mysqli_query($con,$query1);

	mysqli_close($con);
	echo "<script>window.location.href = '../Userdata.php';</script>";
}
?>
<?php require('Fullui.php');  ?>