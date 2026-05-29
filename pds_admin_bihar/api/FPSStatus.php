<?php

require('../util/Connection.php');
require('../util/SessionFunction.php');
require('../util/Logger.php');

if(!SessionCheck()){
	return;
}

require('Header.php');


$id = $_POST["uid"];

$query = "SELECT * FROM fps WHERE uniqueid='$id'";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);

if($numrows>0){
	$row = mysqli_fetch_assoc($result);
	$status = $row['active'];
	$fpsname = $row['name'];
	if($status==0){
		$query = "UPDATE fps SET active='1' WHERE uniqueid='$id'";
		writeLog("User ->" ." FPS Active -> ". $_SESSION['user'] . "| " . $fpsname);
		mysqli_query($con,$query);
	}
	else{
		$query = "UPDATE fps SET active='0' WHERE uniqueid='$id'";
		writeLog("User ->" ." FPS InActive -> ". $_SESSION['user'] . "| " . $fpsname);
		mysqli_query($con,$query);
	}
}


mysqli_close($con);

echo "<script>window.location.href = '../FPS.php';</script>";

?>
<?php require('Fullui.php');  ?>