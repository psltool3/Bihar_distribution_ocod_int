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
$query = "UPDATE user_message SET acknowledged='yes' WHERE id='$uid'";

$log_query = "select user_id,message from user_message WHERE id='$uid'"; 
$log_result = mysqli_query($con,$log_query); 
if ($log_result && $row = $log_result->fetch_assoc()) { 
$user_id =  $row['user_id']; 
$user_message =  $row['message']; 
} 
$log_query = "select username  from login WHERE uid='$user_id'"; 
$log_result = mysqli_query($con,$log_query); 
if ($log_result && $row = $log_result->fetch_assoc()) { 
$log_name =  $row['username']; 
} 

mysqli_query($con,$query);
mysqli_close($con);

$filteredPost = $_POST;
unset($filteredPost['username'], $filteredPost['password']);
writeLog("User ->" ." Read  Message ->". $_SESSION['district_user'] . "| Requested JSON -> " . json_encode($filteredPost). " | " . $log_name);

echo "<script>window.location.href = '../Message.php';</script>";


?>
<?php require('Fullui.php');  ?>