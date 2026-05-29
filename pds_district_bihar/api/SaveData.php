<?php
require('../util/Connection.php');
require('../util/SessionCheck.php');
require('../util/Logger.php');

require('Header.php');

set_time_limit(300); // Set to 300 seconds (5 minutes), or 0 for no limit


$query = "SELECT * FROM optimised_table ORDER BY last_updated DESC LIMIT 1";
$result = mysqli_query($con,$query);
$response = array();
$id = "";
while($row = mysqli_fetch_array($result))
{
	$id= $row["id"];
}


$tablename = "optimiseddata_".$id;

foreach ($_POST as $key => $value) {
	if (substr($key, -11) === '_iddistance' or substr($key, -9) === '_idreason' or $value===""){
		continue;
	}
	$parts = explode("_", $key,3);
	$fromid = $parts[0];
	$toid = $parts[1];
	$commodity = $parts[2];
	$toid = str_replace('_', '.', $toid);
	$commodity = str_replace('_', '.', $commodity);
	if($value=="yes"){
		$query = "UPDATE " . $tablename . " SET approve_district='yes' WHERE from_id='$fromid' AND to_id='$toid' AND commodity='$commodity'";
		writeLog("district User ->" ." Save Data | approve district change yes ->". $_SESSION['district_user'] . "| " . $fromid . " - " . $toid . " - " . $commodity);
	}
	else if($value=="no"){
		$query = "UPDATE " . $tablename . " SET approve_district='', new_id_district='' WHERE from_id='$fromid' AND to_id='$toid' AND commodity='$toid'";
		$filteredPost = $_POST;
		unset($filteredPost['username'], $filteredPost['password']);
		writeLog("district User ->" ." Save Data | approve district change no ->". $_SESSION['district_user'] . "| " . $fromid . " - " . $toid . " - " . $commodity);
	}
	else{
		$query_name = "SELECT name FROM warehouse WHERE id='$value'";
		$result_name = mysqli_query($con,$query_name);
		$row_name = mysqli_fetch_assoc($result_name);
		$name = $row_name['name'];
		$reason = $_POST[$key."_idreason"];
		$distance = $_POST[$key."_iddistance"];
		$query = "UPDATE " . $tablename . " SET new_id_district='$value', new_name_district='$name', approve_district='yes', new_distance_district='$distance', reason_district='$reason' WHERE from_id='$fromid' AND to_id='$toid'";
		
		writeLog("User ->" ." Save Data | district user change id ->". $_SESSION['district_user'] . "| " . $fromid . " - " . $toid .  " - " . $commodity . "| " . $value);
		
	}
	mysqli_query($con,$query);
}
mysqli_close($con);

echo "<script>window.location.href = '../Home.php';</script>";
?>
<?php require('Fullui.php');  ?>