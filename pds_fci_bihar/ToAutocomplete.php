<?php

require('util/Connection.php');
require('../structures/District.php');
require('../util/SessionFunction.php');

if(!SessionCheck()){
	return;
}

$month = $_POST['month'];
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

 ?>

<script>

var x = document.getElementById("to");

<?php
$query = "SELECT to FROM districts";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);

while($row = mysqli_fetch_array($result)){
	echo 'var option = document.createElement("option");';
	echo 'option.text = "'.$row['name'].'";';
	echo 'option.value = "'.$row['name'].'";';
	echo 'x.add(option);';
}

?>
</script>