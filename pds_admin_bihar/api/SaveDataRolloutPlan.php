<?php
require('../util/Connection.php');
require('../util/SessionCheck.php');
require('../util/Logger.php');

require('Header.php');
set_time_limit(600); // Set to 300 seconds (5 minutes), or 0 for no limit

$month = $_POST["month"];
$parts = explode('_', $month);

$month = $parts[0];
$year = $parts[1]; 
$query = "SELECT * FROM optimised_table_leg1 WHERE month='$month' AND year='$year'";

$result = mysqli_query($con,$query);
$response = array();
$id = "";
while($row = mysqli_fetch_array($result))
{
	$id= $row["id"];
}


$tablename = "optimiseddata_leg1_".$id;

foreach ($_POST as $key => $value) {
	if($key=="month"){
		continue;
	}
	$parts = explode("_", $key,3);
	$fromid = $parts[0];
	$toid = $parts[1];
	$commodity = $parts[2];
	$toid = str_replace('_', '.', $toid);
	if($value=="yes"){
		$query = "SELECT * FROM " . $tablename . " WHERE from_id='$fromid' AND to_id='$toid' AND commodity='$commodity'";
		$result = mysqli_query($con, $query);
		$numrows = mysqli_num_rows($result);
		if($numrows==0){
			$query = "SELECT * FROM " . $tablename . " WHERE new_id_admin='$fromid' AND to_id='$toid'AND commodity='$commodity'";
			$result = mysqli_query($con, $query);
			$numrows = mysqli_num_rows($result);
			if($numrows==0){
				$query = "SELECT * FROM " . $tablename . " WHERE new_id_district='$fromid' AND to_id='$toid'AND commodity='$commodity'";
				$result = mysqli_query($con, $query);
				$numrows = mysqli_num_rows($result);
				if($numrows!=0){
					$query = "UPDATE " . $tablename . " SET status='implemented' WHERE new_id_district='$fromid' AND to_id='$toid'AND commodity='$commodity'";
					mysqli_query($con,$query);
					writeLog("Admin User ->" ." Save Data | implemeneted ->". $_SESSION['user'] . "| " . $fromid . " - " . $toid);
				}
			}
			else{
				$query = "UPDATE " . $tablename . " SET status='implemented' WHERE new_id_admin='$fromid' AND to_id='$toid'AND commodity='$commodity'";
				mysqli_query($con,$query);
				writeLog("Admin User ->" ." Save Data | implemeneted ->". $_SESSION['user'] . "| " . $fromid . " - " . $toid);
			}
		}
		else{
			$query = "UPDATE " . $tablename . " SET status='implemented' WHERE from_id='$fromid' AND to_id='$toid'AND commodity='$commodity'";
			mysqli_query($con,$query);
			writeLog("Admin User ->" ." Save Data | implemeneted ->". $_SESSION['user'] . "| " . $fromid . " - " . $toid);
		}
	}
}
mysqli_close($con);
echo "<script>window.location.href = '../RolloutPlanLeg1.php';</script>";
?>
<?php require('Fullui.php');  ?>