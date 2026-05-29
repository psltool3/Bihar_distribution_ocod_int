<?php
require('../util/Connection.php');
require('../structures/District.php');
require('../util/SessionFunction.php');

if(!SessionCheck()){
	return;
}

$month = $_POST['month'];
$parts = explode('_', $month);

$month = $parts[0];
$year = $parts[1]; 
$query = "SELECT * FROM optimised_table WHERE month='$month' AND year='$year'";
$result = mysqli_query($con,$query);
$numrow = mysqli_num_rows($result);
$id = "";
if($numrow>0){
	$row = mysqli_fetch_assoc($result);
	$id = $row['id'];
}

$tablename = "optimiseddata_".$id;

$result = $con->query("SHOW TABLES LIKE '$tablename'");

if ($result->num_rows == 0) {
	$resultarray = [];
	$resultarray["totalids"] = 0;
	$resultarray["totalidsreviewed"] = 0;
	$resultarray["totalidsrequested"] = 0;
	$resultarray["totalidsapproved"] = 0;
	echo json_encode($resultarray);
    exit();
} 

$query = "SELECT from_district FROM " . $tablename . " WHERE 1";
$result = mysqli_query($con,$query);
$totalids = mysqli_num_rows($result);

$query = "SELECT approve_district FROM " . $tablename . " WHERE approve_district='yes'";
$result = mysqli_query($con,$query);
$totalidsreviewed = mysqli_num_rows($result);

$query = "SELECT new_id_district FROM " . $tablename . " WHERE new_id_district<>''";
$result = mysqli_query($con,$query);
$totalidsrequested = mysqli_num_rows($result);

$query = "SELECT approve_admin FROM " . $tablename . " WHERE approve_admin='yes'";
$result = mysqli_query($con,$query);
$totalidsapproved = mysqli_num_rows($result);
							
$resultarray = [];
$resultarray["totalids"] = $totalids;
$resultarray["totalidsreviewed"] = $totalidsreviewed;
$resultarray["totalidsrequested"] = $totalidsrequested;
$resultarray["totalidsapproved"] = $totalidsapproved;
echo json_encode($resultarray);

?>