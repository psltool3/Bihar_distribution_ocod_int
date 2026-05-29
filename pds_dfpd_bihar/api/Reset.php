<?php
require('../util/Connection.php');
require('../structures/Login.php');

require('Header.php');

$newpassword = $_POST['newpassword'];
$confirmpassword = $_POST['confirmpassword'];

if($newpassword=="" || $confirmpassword==""){
	echo "Error : Password is Empty";
	return;
}
if($newpassword!=$confirmpassword){
	echo "Error : Both Password doesn't match";
	return;
}

$person = new Login;
$person->setUsername($_POST["username"]);
$person->setPassword($_POST["oldpassword"]);

if($_SESSION['user']!=$person->getUsername()){
	echo "User is logged in with different username and password";
	return;
}

$query = "SELECT * FROM login WHERE username='".$person->getUsername()."' AND password='".$person->getPassword()."'";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);

if($numrows == 0){
	echo "Error : Old Password and username is incorrect";
}
else if($numrows > 0){
	$query1 = "UPDATE login SET password='$newpassword' WHERE 1";
	mysqli_query($con,$query1);

	mysqli_close($con);
	echo "<script>window.location.href = '../Login.php';</script>";

}
?>
<?php require('Fullui.php');  ?>