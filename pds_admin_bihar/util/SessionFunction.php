<?php

function SessionCheck(){
	require('Connection.php');
	session_start();

	if(isset($_SESSION['user'])){
		$user = $_SESSION['user'];
		$token = $_SESSION['token'];
		$query = "SELECT * FROM login WHERE username='$user' AND token='$token'";
		$result = mysqli_query($con,$query);
		$numrows = mysqli_num_rows($result);
		if($numrows==0){
			return false;
		}
		else{
			$currentLoginTime = date("Y-m-d H:i:s");
			$queryUpdate = "UPDATE login SET lastlogin='$currentLoginTime' WHERE username='$user'";
			mysqli_query($con,$queryUpdate);
			
			return true;
		}
	}
	else{
		return false;
	}
}

?>
