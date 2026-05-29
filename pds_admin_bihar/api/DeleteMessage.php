<?php
require('../util/Connection.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');
require('../util/Logger.php');

if(!SessionCheck()){
	return;
}

require('Header.php');

$uid = $_POST["uid"];

$log_query = "select user_id from user_message WHERE id='$uid'";
$log_result = mysqli_query($con,$log_query);
if ($log_result && $row = $log_result->fetch_assoc()) {
	$user_id =  $row['user_id'];
}

$log_query = "select username  from login WHERE uid='$user_id'";
$log_result = mysqli_query($con,$log_query);
if ($log_result && $row = $log_result->fetch_assoc()) {
	$log_name =  $row['username'];
}

$query = "DELETE FROM user_message WHERE id='$uid'";
mysqli_query($con,$query);
mysqli_close($con);

$filteredPost = $_POST;
unset($filteredPost['username'], $filteredPost['password']);
writeLog("User ->" ." Delete Message ->". $_SESSION['user'] . "| Requested JSON -> " . json_encode($filteredPost). " | " . $log_name);


echo "<script>window.location.href = '../SendMessage.php';</script>";


?>
<?php require('Fullui.php');  ?>