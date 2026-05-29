<?php
require('../util/Connection.php');
require('../structures/District.php');
require('../util/SessionFunction.php');

if(!SessionCheck()){
	return;
}

$query = "SELECT * FROM optimised_table_leg1 ORDER BY last_updated DESC LIMIT 1";

$result = mysqli_query($con,$query);
$numrow = mysqli_num_rows($result);
$id = "";
if($numrow>0){
	$row = mysqli_fetch_assoc($result);
	$id = $row['id'];
}

$tablename = "optimiseddata_leg1_".$id;
$result = $con->query("SELECT DISTINCT `to` from $tablename WHERE 1");

if ($result->num_rows > 0) {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    echo json_encode($rows);
}
?>