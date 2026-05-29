<?php
require('Connection.php');
set_time_limit(600); // Set to 300 seconds

$ip_address = "";

if (!empty($_SERVER['HTTP_CLIENT_IP']))   
  {
	$ip_address = $_SERVER['HTTP_CLIENT_IP'];
  }
elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))  
  {
	$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
  }
else
  {
	$ip_address = $_SERVER['REMOTE_ADDR'];
  }

session_start();
if(isset($_SESSION['user'])){
	$user = $_SESSION['user'];
	$token = $_SESSION['token'];
	$query = "SELECT * FROM login WHERE username='$user' AND token='$token'";
	$result = mysqli_query($con,$query);
	$numrows = mysqli_num_rows($result);
	
	if($numrows==0){
		header("Location:AdminLogin.html");
		exit();
	}
	$currentLoginTime = date("Y-m-d H:i:s");
	$queryUpdate = "UPDATE login SET lastlogin='$currentLoginTime' WHERE username='$user'";
	mysqli_query($con,$queryUpdate);
	
}
else{
	header("Location:AdminLogin.html");
}

?>
