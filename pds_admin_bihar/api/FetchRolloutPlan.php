<?php
require('../util/Connection.php');
require('../structures/District.php');
//require('../util/SessionFunction.php');
//require('../structures/Login.php');

//if(!SessionCheck()){
//	return;
//}


set_time_limit(9000); // Set to 300 seconds (5 minutes), or 0 for no limit

$warehouse = array();
$fps = array();
$warehouse_optimised = array();

$allocation = 0;
$qkm = 0;
$qkm_optimised = 0;
$averagedistance = 0;

function addUnique($value, &$array) {
    if (!in_array($value, $array)) {
        $array[] = $value;
    }
	return;
}

$month = $_POST['month'];
$district = $_POST['district'];

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

$query = "SHOW TABLES LIKE '$tablename'";
$result = $con->query($query);

$query = "SELECT * FROM ".$tablename." WHERE to_district='$district'";

if ($result && $result->num_rows > 0) {
	$query = "SELECT * FROM ".$tablename." WHERE to_district='$district'";
	$result = mysqli_query($con,$query);
	$numrows = mysqli_num_rows($result);
	while($row = mysqli_fetch_assoc($result))
	{
		if($row['new_id_admin']!=null or $row['new_id_admin']!=""){
			$id = $row['new_id_admin'];
			$query_warehouse = "SELECT latitude,longitude,district FROM warehouse WHERE id='$id'";
			$result_warehouse = mysqli_query($con,$query_warehouse);
			$numrows_warehouse = mysqli_num_rows($result_warehouse);
			if($numrows_warehouse!=0){
				$row_warehouse = mysqli_fetch_assoc($result_warehouse);
				$row["from_lat"] = $row_warehouse['latitude'];
				$row["from_long"] = $row_warehouse['longitude'];
				$row["from_district"] = $row_warehouse['district'];
			}
			$row["from_id"] = $row['new_id_admin'];
			$row["from_name"] = $row['new_name_admin'];
			$row["distance"] = $row['new_distance_admin'];
		}
		else if(($row['new_id_district']!=null or $row['new_id_district']!="") and $row['approve_admin']=="yes"){
			$id = $row['new_id_district'];
			$query_warehouse = "SELECT latitude,longitude,district FROM warehouse WHERE id='$id'";
			$result_warehouse = mysqli_query($con,$query_warehouse);
			$numrows_warehouse = mysqli_num_rows($result_warehouse);
			if($numrows_warehouse!=0){
				$row_warehouse = mysqli_fetch_assoc($result_warehouse);
				$row["from_lat"] = $row_warehouse['latitude'];
				$row["from_long"] = $row_warehouse['longitude'];
				$row["from_district"] = $row_warehouse['district'];
			}
			$row["from_id"] = $row['new_id_district'];
			$row["from_name"] = $row['new_name_district'];
			$row["distance"] = $row['new_distance_district'];
		}
		$data[] = $row;			
	}
	$resultarray["data"] = $data;
	echo json_encode($resultarray);
} else {
	$resultarray = [];
	$resultarray["data"] = array();
	echo json_encode($resultarray);
}
?>