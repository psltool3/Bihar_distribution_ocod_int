<?php
require('../util/Connection.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');
require('../util/Logger.php');

if(!SessionCheck()){
	return;
}

require('Header.php');

$district = $_SESSION['district_district'];
$query = "SELECT * FROM fps WHERE type='Model FPS' AND district='$district'";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);

if($numrows>0){
	$row = mysqli_fetch_assoc($result);
	$status = $row['active'];
	if($status==0){
		$query = "UPDATE fps SET active='1' WHERE type='Model FPS' AND district='$district'";
		writeLog("District User ->" ."All Model FPS Active -> ". $_SESSION['user']);
		mysqli_query($con,$query);
	}
	else{
		$query = "UPDATE fps SET active='0' WHERE type='Model FPS' AND district='$district'";
		writeLog("District User ->" ."All Model FPS InActive -> ". $_SESSION['user']);
		mysqli_query($con,$query);
	}
}


echo "<script>window.location.href = '../FPS.php';</script>";


?>
<?php require('Fullui.php');  ?>