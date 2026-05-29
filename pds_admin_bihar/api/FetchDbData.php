<?php
require('../util/Connection.php');
require('../structures/District.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');

if(!SessionCheck()){
	return;
}

$reviewed = "";
$approved = "";
$from_id = "";
$to_id = "";

if(isset($_POST['fromid'])){
	$from_id = $_POST['fromid'];
}

if(isset($_POST['toid'])){
	$to_id = $_POST['toid'];
}

if(isset($_POST['approved'])){
	$approved = $_POST['approved'];
}

if(isset($_POST['reviewed'])){
	$reviewed = $_POST['reviewed'];
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
$data = null;

if ($result && $result->num_rows > 0) {
	$query = "SELECT * FROM ".$tablename." WHERE to_district='$district'";
	if($reviewed=="reviewed"){
		$query = "SELECT * FROM ".$tablename." WHERE approve_district='yes' AND to_district='$district'";
	}
	else if($reviewed=="notreviewed"){
		$query = "SELECT * FROM ".$tablename." WHERE (approve_district = '' OR approve_district IS NULL) AND to_district='$district'";
	}

	if($approved=="approved"){
		$query = "SELECT * FROM ".$tablename." WHERE approve_admin='yes' AND to_district='$district'";
	}
	else if($approved=="notapproved"){
		$query = "SELECT * FROM ".$tablename." WHERE (approve_admin='no' or approve_admin IS NULL) AND to_district='$district'";
	}
	if($from_id!=""){
		$query = "SELECT * FROM ".$tablename." WHERE to_district='$district' AND from_id='$from_id'";
		if($reviewed=="reviewed"){
			$query = "SELECT * FROM ".$tablename." WHERE approve_district='yes' AND to_district='$district' AND from_id='$from_id'";
		}
		else if($reviewed=="notreviewed"){
			$query = "SELECT * FROM ".$tablename." WHERE (approve_district = '' OR approve_district IS NULL) AND to_district='$district' AND from_id='$from_id'";
		}

		if($approved=="approved"){
			$query = "SELECT * FROM ".$tablename." WHERE approve_admin='yes' AND to_district='$district' AND from_id='$from_id'";
		}
		else if($approved=="notapproved"){
			$query = "SELECT * FROM ".$tablename." WHERE (approve_admin='no' or approve_admin IS NULL) AND to_district='$district' AND from_id='$from_id'";
		}
	}
	if($to_id!=""){
		$query = "SELECT * FROM ".$tablename." WHERE to_district='$district' AND `to`='$to_id'";
		if($reviewed=="reviewed"){
			$query = "SELECT * FROM ".$tablename." WHERE approve_district='yes' AND to_district='$district' AND `to`='$to_id'";
		}
		else if($reviewed=="notreviewed"){
			$query = "SELECT * FROM ".$tablename." WHERE (approve_district = '' OR approve_district IS NULL) AND to_district='$district' AND `to`='$to_id'";
		}

		if($approved=="approved"){
			$query = "SELECT * FROM ".$tablename." WHERE approve_admin='yes' AND to_district='$district' AND `to`='$to_id'";
		}
		else if($approved=="notapproved"){
			$query = "SELECT * FROM ".$tablename." WHERE (approve_admin='no' or approve_admin IS NULL) AND to_district='$district' AND `to`='$to_id'";
		}
	}
	if($to_id!="" and $from_id!=""){
		$query = "SELECT * FROM ".$tablename." WHERE to_district='$district' AND `to`='$to_id' AND from_id='$from_id'";
		if($reviewed=="reviewed"){
			$query = "SELECT * FROM ".$tablename." WHERE approve_district='yes' AND to_district='$district' AND `to`='$to_id' AND from_id='$from_id'";
		}
		else if($reviewed=="notreviewed"){
			$query = "SELECT * FROM ".$tablename." WHERE (approve_district = '' OR approve_district IS NULL) AND to_district='$district' AND `to`='$to_id' AND from_id='$from_id'";
		}

		if($approved=="approved"){
			$query = "SELECT * FROM ".$tablename." WHERE approve_admin='yes' AND to_district='$district' AND `to`='$to_id' AND from_id='$from_id'";
		}
		else if($approved=="notapproved"){
			$query = "SELECT * FROM ".$tablename." WHERE (approve_admin='no' or approve_admin IS NULL) AND to_district='$district' AND `to`='$to_id' AND from_id='$from_id' ";
		}
	}
	$result = mysqli_query($con,$query);
	while($row = mysqli_fetch_assoc($result))
	{
		$data[] = $row;
	}

	$query_warehouse = "SELECT id from warehouse WHERE active='1'";
	$result_warehouse = mysqli_query($con,$query_warehouse);
	while($row_warehouse = mysqli_fetch_assoc($result_warehouse)){
		$warehouse[] = $row_warehouse;
	}
	$resultarray = [];
	if($data==null){
		$data = array();
	}
	$resultarray["data"] = $data;
	$resultarray["warehouse"] = $warehouse;
	
	echo json_encode($resultarray);
} else {
	$resultarray = [];
	$resultarray["data"] = array();
	$resultarray["warehouse"] = array();
	echo json_encode($resultarray);
}
?>