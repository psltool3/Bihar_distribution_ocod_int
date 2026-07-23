<?php
require('../util/Connection.php');
require('../util/SessionCheck.php');
require('../util/Logger.php');

$uniqueid = $_POST['uniqueid'];

// uniqueid is from_id_to_id_commodity
$parts = explode("_", $uniqueid, 3);
$fromid = $parts[0];
$toid = $parts[1];
$commodity = $parts[2];
$toid = str_replace('_', '.', $toid);
$commodity = str_replace('_', '.', $commodity);
$commodity = str_replace('.bool', '', $commodity);

$query = "SELECT * FROM optimised_table_leg1 ORDER BY last_updated DESC LIMIT 1";
$result = mysqli_query($con,$query);
$id = "";
if($row = mysqli_fetch_array($result))
{
	$id= $row["id"];
}

if($id != "") {
    $tablename = "optimiseddata_leg1_".$id;
    $updateQuery = "UPDATE `$tablename` SET approve_admin=NULL, new_id_admin=NULL, new_name_admin=NULL, reason_admin=NULL, new_distance_admin=NULL, district_change_approve=NULL WHERE from_id='$fromid' AND to_id='$toid' AND commodity='$commodity'";
    mysqli_query($con, $updateQuery);
    writeLog("User -> Reset Admin Data Leg1 -> " . $_SESSION['user'] . " | " . $fromid . " - " . $toid . " - " . $commodity);
    echo json_encode(["status" => "success", "message" => "Row reset successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "No optimised data found"]);
}
?>
