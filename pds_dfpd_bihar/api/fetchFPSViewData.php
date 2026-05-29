<?php
require('../util/Connection.php');


$district = $_POST['district'];
$tablename = $_POST['tablename'];

$query = "SELECT * FROM ".$tablename." WHERE district='$district'";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);
$data = null;

while($row = mysqli_fetch_assoc($result)){
	$data[] = $row;
}

$resultarray = [];
if($data==null){
	$data = array();
}
$resultarray["data"] = $data;
echo json_encode($resultarray);
?>
