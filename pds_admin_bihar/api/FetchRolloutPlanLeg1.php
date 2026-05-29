<?php
require('../util/Connection.php');
require('../structures/District.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');

if(!SessionCheck()){
	return;
}

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
$query = "SELECT * FROM optimised_table_leg1 WHERE month='$month' AND year='$year'";
$result = mysqli_query($con,$query);
$numrow = mysqli_num_rows($result);
$id = "";
$type = "";
if($numrow>0){
	$row = mysqli_fetch_assoc($result);
	$id = $row['id'];
	$type = $row['type'];
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
	$query = "SELECT * FROM ".$tablename." WHERE 1";
	$result = mysqli_query($con,$query);
	$numrows = mysqli_num_rows($result);
	while($row = mysqli_fetch_assoc($result))
	{		
		addUnique($row["from_id"],$warehouse_optimised);
		$qkm_optimised = $qkm_optimised + (float)$row["quantity"] * (float)$row["distance"];
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
		addUnique($row["from_id"],$warehouse);
		addUnique($row["to_id"],$fps);
		$allocation = $allocation + (float)$row["quantity"];
		$qkm = $qkm + (float)$row["quantity"] * (float)$row["distance"];
	}
	$averagedistance = $qkm/$allocation;
	$averagedistanceoptimised = $qkm_optimised/$allocation;
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
	
	if($type=="rice_fci_wheat_fci"){
		$tableData["WH_Used_Baseline"] = '73';
		$tableData["FPS_Used_Baseline"] = '458';
		$tableData["Demand_Baseline"] = '46,05,917.05';
		$tableData["Total_QKM_Baseline"] = '15,85,93,066.7';
		$tableData["Average_Distance_Baseline"] = '34.33';
		$tableData["Scenario_Baseline"] = "Baseline";
	}
	else if($type=="rice_dcp_wheat_dcp"){
		$tableData["WH_Used_Baseline"] = '392';
		$tableData["FPS_Used_Baseline"] = '458';
		$tableData["Demand_Baseline"] = '46,05,917.05';
		$tableData["Total_QKM_Baseline"] = '15,51,98,041.05';
		$tableData["Average_Distance_Baseline"] = '33.69';
		$tableData["Scenario_Baseline"] = "Baseline";
	}
	else if($type=="rice_dcp_wheat_fci"){
		$tableData["WH_Used_Baseline"] = '465';
		$tableData["FPS_Used_Baseline"] = '459';
		$tableData["Demand_Baseline"] = '46,05,917.05';
		$tableData["Total_QKM_Baseline"] = '15,71,38,370.9';
		$tableData["Average_Distance_Baseline"] = '34.11';
		$tableData["Scenario_Baseline"] = "Baseline";
	}
	else if($type=="rice_dcp_wheat_fci_dcp"){
		$tableData["WH_Used_Baseline"] = '465';
		$tableData["FPS_Used_Baseline"] = '459';
		$tableData["Demand_Baseline"] = '46,05,917.05';
		$tableData["Total_QKM_Baseline"] = '15,71,38,370.9';
		$tableData["Average_Distance_Baseline"] = '34.11';
		$tableData["Scenario_Baseline"] = "Baseline";
	}
	
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