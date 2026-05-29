<?php
require('../util/Connection.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');

if(!SessionCheck()){
	return;
}

$query = "SELECT * FROM optimised_table_leg1 ORDER BY last_updated DESC LIMIT 1";
$result = mysqli_query($con,$query);
$response = array();
$id = "";
while($row = mysqli_fetch_array($result))
{
	$id = $row["id"];
}


$tablename = "optimiseddata_leg1_".$id;
$reviewed = "";
$approved = "";
$fromid = "";
$toid = "";
$data = array();
$warehouse = array();


$district = $_POST['district'];

if(isset($_POST['approved'])){
	$approved = $_POST['approved'];
}

if(isset($_POST['reviewed'])){
	$reviewed = $_POST['reviewed'];
}


$query = "SELECT * FROM " . $tablename;
$added = 0;

if (isset($_POST['fromid']) && !empty($_POST['fromid'])) {
    $fromid = $_POST['fromid'];
    $query .= " WHERE from_id = '$fromid'";
    $added = 1;
}

if (isset($_POST['toid']) && !empty($_POST['toid'])) {
    $toid = $_POST['toid'];
    if($added==1){
        $query .= " AND toid = '$toid'";
    }else{
        $query .= " WHERE toid = '$toid'";
    }
	$added = 1;
}

if ($reviewed == "reviewed") {
    if($added==1){
        $query .= " AND approve_district='yes'";
    }else{
        $query .= " WHERE approve_district='yes'";
    }
	$added = 1;
} else if ($reviewed == "notreviewed") {
    if($added==1){
        $query .= " AND approve_district IS NULL";
    }else{
        $query .= " WHERE approve_district IS NULL";
    }
	$added = 1;
}

if ($approved == "approved") {
    if($added==1){
        $query .= " AND approve_admin='yes'";
    }else{
        $query .= " WHERE approve_admin='yes'";
    }
	$added = 1;
} else if ($approved == "notapproved") {
    if($added==1){
        $query .= " AND approve_admin IS NULL";
    }else{
        $query .= " WHERE approve_admin IS NULL";
    }
	$added = 1;
}

if ($district != "") {
    if($added==1){
        $query .= " AND to_district='$district'";
    }else{
        $query .= " WHERE to_district='$district'";
    }
	$added = 1;
}

$result = mysqli_query($con,$query);
while($row = mysqli_fetch_assoc($result))
{
	$data[] = $row;
}

$query_warehouse = "SELECT * from warehouse_leg1_".$id." WHERE 1";
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

?>