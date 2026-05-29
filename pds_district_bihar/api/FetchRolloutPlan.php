<?php
require('../util/Connection.php');
require('../structures/District.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');

if(!SessionCheck()){
	return;
}

$query = "SELECT * FROM optimised_table ORDER BY last_updated DESC LIMIT 1";
$result = mysqli_query($con,$query);
$response = array();
$id = "";
while($row = mysqli_fetch_array($result))
{
	$id= $row["id"];
}

$tablename = "optimiseddata_".$id;
$district = $_SESSION['district_district'];

$status = $_POST['status'];
$data = array();
$query = "SHOW TABLES LIKE '$tablename'";
$result = $con->query($query);


if ($result && $result->num_rows > 0) {
	$query_implemented = "SELECT * FROM ".$tablename." WHERE to_district='$district' AND status='implemented'";
	$result_implemented = mysqli_query($con,$query_implemented);
	$count_implemented = mysqli_num_rows($result_implemented);
	
	$query_notimplemented = "SELECT * FROM ".$tablename." WHERE to_district='$district' AND status IS NULL";
	$result_notimplemented = mysqli_query($con,$query_notimplemented);
	$count_notimplemented = mysqli_num_rows($result_notimplemented);
	
	$query = "SELECT * FROM ".$tablename." WHERE to_district='$district'";
	if($status=="implemented"){
		$query = "SELECT * FROM ".$tablename." WHERE to_district='$district' AND status='implemented'";
	}
	else if($status=="not implemented"){
		$query = "SELECT * FROM ".$tablename." WHERE to_district='$district' AND status IS NULL";
	}
	
	$result = mysqli_query($con,$query);
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
	$resultarray["implemented"] = $count_implemented;
	$resultarray["notimplemented"] = $count_notimplemented;
	echo json_encode($resultarray);
} else {
	$resultarray = [];
	$resultarray["data"] = array();
	echo json_encode($resultarray);
}
?>