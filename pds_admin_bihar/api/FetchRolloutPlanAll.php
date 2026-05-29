<?php
require('../util/Connection.php');
require('../structures/District.php');

set_time_limit(9000); 

$warehouse = array();
$fps = array();
$warehouse_optimised = array();
$resultarray = array();

$allocation = 0;
$qkm = 0;
$distance = 0;
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
$data_leg1 = array();
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


$query_leg1 = "SELECT * FROM optimised_table_leg1 WHERE month='$month' AND year='$year'";
$result_leg1 = mysqli_query($con,$query_leg1);
$numrow_leg1 = mysqli_num_rows($result_leg1);
$id_leg1 = "";
if($numrow_leg1>0){
	$row_leg1 = mysqli_fetch_assoc($result_leg1);
	$id_leg1 = $row_leg1['id'];
}
$tablename_leg1 = "optimiseddata_leg1_".$id_leg1;
$query_leg1 = "SHOW TABLES LIKE '$tablename_leg1'";
$result_leg1 = $con->query($query_leg1);


if ($result && $result->num_rows > 0 && $result_leg1 && $result_leg1->num_rows > 0) {
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
	if($numrows==0){
		$data = "";
	}
	
	$query = "SELECT * FROM ".$tablename_leg1." WHERE to_district='$district'";
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
		$data_leg1[] = $row;			
	}
	if($numrows==0){
		$data_leg1 = "";
	}

	foreach ($data_leg1 as $value) {
		$data[] = $value;
	}

	$resultarray["data"] = $data;
} else {
	$resultarray = [];
	$resultarray["data"] = array();
	$resultarray["table"] = array();
}

$allocation = 0;
$qkm = 0;
$distance = 0;
$qkm_optimised = 0;
$averagedistance = 0;


$query = "SELECT * FROM optimised_table_leg1 WHERE month='$month' AND year='$year'";
$result = mysqli_query($con,$query);
$numrow = mysqli_num_rows($result);
$id = "";
if($numrow>0){
	$row = mysqli_fetch_assoc($result);
	$id = $row['id'];
}

$tablename = "optimiseddata_leg1_".$id;

$query = "SHOW TABLES LIKE '$tablename'";
$result = $con->query($query);

if ($result && $result->num_rows > 0) {
	$query = "SELECT * FROM ".$tablename." WHERE to_district='$district'";
	$result = mysqli_query($con,$query);
	$numrows = mysqli_num_rows($result);
	while($row = mysqli_fetch_assoc($result))
	{
		if($row['new_id_admin']!=null or $row['new_id_admin']!=""){
			$new_id = $row['new_id_admin'];
			$query_warehouse = "SELECT latitude,longitude,district FROM warehouse_leg1_".$id." WHERE id='$new_id'";
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
			$new_id = $row['new_id_district'];
			$query_warehouse = "SELECT latitude,longitude,district FROM warehouse_leg1_".$id." WHERE id='$new_id'";
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
	if($numrows==0){
		$data = "";
	}
	
	$resultarray["dataleg1"] = $data;
} else {
	$resultarray = [];
	$resultarray["dataleg1"] = array();
}


echo json_encode($resultarray);
?>