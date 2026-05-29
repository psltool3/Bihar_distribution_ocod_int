<?php
require('../util/Connection.php');

set_time_limit(600);
ini_set('memory_limit', '4G');

$district = $_POST['district'];
$tablename = $_POST['tablename'];
$tablename1 = $_POST['tablename1'];
$data = null;
$data1 = null;


$query = "SELECT * FROM ".$tablename." WHERE to_district='".$district."'";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);
while($row = mysqli_fetch_array($result))
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

if($tablename!=$tablename1){
	$query = "SELECT * FROM ".$tablename1." WHERE to_district='".$district."'";
	$result = mysqli_query($con,$query);
	$numrows = mysqli_num_rows($result);
	while($row = mysqli_fetch_array($result))
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
		$data1[] = $row;

	}
}
$resultarray = [];
if($data==null){
	$data = array();
}
if($data1==null){
	$data1 = array();
}
$resultarray["data"] = $data;
$resultarray["data1"] = $data1;
echo json_encode($resultarray);
?>
