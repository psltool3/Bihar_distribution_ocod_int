<?php

require('../util/Connection.php');
require('../util/SessionFunction.php');

if(!SessionCheck()){
	return;
}

require('Header.php');

$date = $_POST['date'];
$time = $_POST['time'];


if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
    echo "Error: Invalid date format. Please use the format YYYY-MM-DD.<br>";
    return;  
}

// add time logic here 


$query = "UPDATE timer SET deadline_date='$date', deadline_time='$time' WHERE 1";
mysqli_query($con,$query);
mysqli_close($con);

echo "<script>window.location.href = '../Timer.php';</script>";

?>
<?php require('Fullui.php');  ?>