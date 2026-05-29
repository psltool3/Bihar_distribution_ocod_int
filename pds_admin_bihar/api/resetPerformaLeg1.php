<?php
require('../util/Connection.php');
require('../util/SessionFunction.php');
require('../util/Logger.php');

if(!SessionCheck()){
	return;
}

$log_query = "select id from optimised_table_leg1 WHERE id='$uid'";
$log_result = mysqli_query($con,$log_query);
if ($log_result && $row = $log_result->fetch_assoc()) {
	$user_id =  $row['id'];
}

$log_query = "select username from login WHERE uid='$user_id'";
$log_result = mysqli_query($con,$log_query);
if ($log_result && $row = $log_result->fetch_assoc()) {
	$log_name =  $row['username'];
}

$id = $_POST["id"];

// Update the optimised table where id equals the extracted ID
$sql = "UPDATE optimised_table_leg1 SET cost = '' WHERE id = '$id'";
$filteredPost = $_POST;
unset($filteredPost['username'], $filteredPost['password']);
writeLog("User ->" ." Cost for leg1 Reset ->". $_SESSION['user'] . "| Requested JSON -> " . json_encode($filteredPost). " | " . $log_name);

if ($con->query($sql) === TRUE) {
	
} else {
	echo "Error : updating record: " . $con->error;
	return;
}	

?>
