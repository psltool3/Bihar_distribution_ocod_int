<?php
require('../util/Connection.php');
require('../util/SessionCheck.php');

require('Header.php');

$query = "SELECT * FROM optimised_table ORDER BY last_updated DESC LIMIT 1";
$result = mysqli_query($con,$query);
$response = array();
$id = "";
while($row = mysqli_fetch_array($result))
{
	$id= $row["id"];
}

/*
$tablename = "optimiseddata_".$id;

$query = "SELECT * FROM $tablename WHERE 1";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);
if($numrows>0){
	while($row = mysqli_fetch_assoc($result)){
		$temp_admin = $row['new_id_admin'];
		$temp_district = $row['new_id_district'];
		$temp_approve = $row['approve_admin'];
		$fromid_temp = $row['from_id'];
		$to_id = $row['to_id'];
		if($temp_admin!=null and strlen($temp_admin)>0){
			$query = "UPDATE $tablename SET from_id='$temp_admin', old_id='$fromid_temp', new_id_admin='' WHERE from_id='$fromid_temp' AND to_id='$to_id'";
			mysqli_query($con,$query);
		}
		else if($temp_district!=null and strlen($temp_district)>0 and $temp_approve=="yes"){
			$query = "UPDATE $tablename SET from_id='$temp_district', old_id='$fromid_temp', new_id_admin='' WHERE from_id='$fromid_temp' AND to_id='$to_id'";
			mysqli_query($con,$query);
		}
	}
}*/

$query = "UPDATE optimised_table SET rolled_out='1' WHERE id='$id'";
mysqli_query($con,$query);
mysqli_close($con);
//echo "Sent to the district for verification";
echo "<script>window.location.href = '../OptimisedData.php';</script>";

?>
<?php require('Fullui.php');  ?>