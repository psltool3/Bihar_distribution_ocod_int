<?php
require('../util/Connection.php');
require('../structures/District.php');


set_time_limit(900); // Set to 300 seconds (5 minutes), or 0 for no limit

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

$month = "aug_2024";//$_POST['month'];
$district = "Araria";//$_POST['district'];

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
	if($numrows==0){
		$data = "";
	}
	$query = "SELECT * FROM ".$tablename." WHERE 1";
	$result = mysqli_query($con,$query);
	$numrows = mysqli_num_rows($result);
	while($row = mysqli_fetch_assoc($result))
	{		
		addUnique($row["from_id"],$warehouse_optimised);
		$qkm_optimised = $qkm_optimised + ((float)$row["quantity"]) * (float)$row["distance"];
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
		addUnique($row["from_id"],$warehouse);
		addUnique($row["to_id"],$fps);
		$allocation = $allocation + (float)$row["quantity"];
		$qkm = $qkm + ((float)$row["quantity"]) * (float)$row["distance"];
	}
	$averagedistance = 1;//$qkm/$allocation;
	$averagedistanceoptimised = 1;//$qkm_optimised/$allocation;
	$tableData = array();
	$tableData["WH_Used"] = count($warehouse);
	$tableData["FPS_Used"] = count($fps);
	$tableData["Demand"] = $allocation;
	$tableData["Total_QKM"] = $qkm;
	$tableData["Average_Distance"] = $averagedistance;
	$tableData["Scenario"] = "State Suggested";
	
	$tableData["WH_Used_Optimised"] = count($warehouse_optimised);
	$tableData["Total_QKM_Optimised"] = $qkm_optimised;
	$tableData["Average_Distance_Optimised"] = $averagedistanceoptimised;
	$tableData["Scenario_optimised"] = "Optimised";
	
	$tableData["WH_Used_Baseline"] = '76';
	$tableData["FPS_Used_Baseline"] = '1,795';
	$tableData["Demand_Baseline"] = '69,247';
	$tableData["Total_QKM_Baseline"] = '18,40,201';
	$tableData["Average_Distance_Baseline"] = '26.58';
	$tableData["Scenario_Baseline"] = "Baseline";
	
	$resultarray["data"] = $data;
	$resultarray["table"] = $tableData;
	echo json_encode($resultarray);
} else {
	$resultarray = [];
	$resultarray["data"] = array();
	$resultarray["table"] = array();
	echo json_encode($resultarray);
}
?>