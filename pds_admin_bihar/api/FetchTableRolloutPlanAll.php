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

	$query = "SELECT * FROM ".$tablename." WHERE 1";
	$result = mysqli_query($con,$query);
	$numrows = mysqli_num_rows($result);
	while($row = mysqli_fetch_assoc($result))
	{		
		addUnique($row["from_id"],$warehouse_optimised);
		$qkm_optimised = $qkm_optimised + (float)$row["quantity"] * (float)$row["distance"];
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
		$qkm = $qkm + (float)$row["quantity"] * (float)$row["distance"];
		$distance = $distance + (float)$row["distance"];
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
	$tableData["Distance"] = $distance;
	
	$tableData["WH_Used_Optimised"] = count($warehouse_optimised);
	$tableData["Total_QKM_Optimised"] = $qkm_optimised;
	$tableData["Average_Distance_Optimised"] = $averagedistanceoptimised;
	$tableData["Scenario_optimised"] = "Optimised";
	
	$tableData["WH_Used_Baseline"] = '78';
	$tableData["FPS_Used_Baseline"] = '90';
	$tableData["Demand_Baseline"] = '87,13,290';
	$tableData["Total_QKM_Baseline"] = '11,58,22,464';
	$tableData["Average_Distance_Baseline"] = '13.29';
	$tableData["Scenario_Baseline"] = "Baseline";
	
	foreach ($data_leg1 as $value) {
		$data[] = $value;
	}

	$resultarray["data"] = $data;
	$resultarray["table"] = $tableData;
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
		$distance = $distance + (float)$row["distance"];
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
	$tableData["Distance"] = $distance;
	
	$tableData["WH_Used_Optimised"] = count($warehouse_optimised);
	$tableData["Total_QKM_Optimised"] = $qkm_optimised;
	$tableData["Average_Distance_Optimised"] = $averagedistanceoptimised;
	$tableData["Scenario_optimised"] = "Optimised";
	
	$tableData["WH_Used_Baseline"] = '255';
	$tableData["FPS_Used_Baseline"] = '17,829';
	$tableData["Demand_Baseline"] = '87,13,290';
	$tableData["Total_QKM_Baseline"] = '11,58,22,464';
	$tableData["Average_Distance_Baseline"] = '13.29';
	$tableData["Scenario_Baseline"] = "Baseline";
	
	$resultarray["dataleg1"] = $data;
	$resultarray["tableleg1"] = $tableData;
	$resultarray["type"] = $type;
} else {
	$resultarray = [];
	$resultarray["dataleg1"] = array();
	$resultarray["tableleg1"] = array();
	$resultarray["type"] = "";
}


$resultarray["DemandTotal"] = $resultarray["tableleg1"]["Demand"] + $resultarray["table"]["Demand"];
$resultarray["Total_QKMTotal"] = $resultarray["tableleg1"]["Total_QKM"] + $resultarray["table"]["Total_QKM"];
$resultarray["Average_Distance_OptimisedTotal"] = $resultarray["Total_QKMTotal"]/ $resultarray["DemandTotal"];
$resultarray["Reduction_OptimisedTotal"] = ((21.83-$resultarray["Average_Distance_OptimisedTotal"])/21.83)*100;
$resultarray["Baseline_OptimisedTotal"] = 1245637;
$resultarray["DistanceTotal"] =  $resultarray["tableleg1"]["Distance"] + $resultarray["table"]["Distance"];


echo json_encode($resultarray);
?>