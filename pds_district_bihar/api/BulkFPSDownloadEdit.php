<?php
require('../util/Connection.php');
session_start();
$mapData = [
    "District" => "district",
    "Name of FPS" => "name",
    "FPS ID" => "id",
    "Model FPS/Normal FPS" => "type",
    "Latitude" => "latitude",
    "Longitude" => "longitude",
    "Demand of FRice" => "demand",
	"Demand of Rice" => "demand_rice",
	"Active/Not-Active" => "active"
];

// Reverse mapping
$reverseMapData = array_flip($mapData);
$district = $_SESSION['district_district'];
// Filter the excel data 
function filterData(&$str){ 
    $str = preg_replace("/\t/", "\\t", $str); 
    $str = preg_replace("/\r?\n/", "\\n", $str); 
    if(strstr($str, '"')) $str = '"' . str_replace('"', '""', $str) . '"'; 
} 
 
// Excel file name for download 
$fileName = "FPSData_" . date('d-m-Y') . ".csv"; 

$columns = array();

$query = "SHOW COLUMNS FROM fps";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);
if($numrows>0){
	while($row = mysqli_fetch_array($result)){
		if($row['Field']!="uniqueid"){
			array_push($columns,$reverseMapData[$row['Field']]);
		}
	}
}

// Display column names as first row 
$excelDataColumns = implode(",", array_values($columns)) . "\n";

 
// Headers for download 
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

 
// Render excel data 
echo $excelDataColumns;

$query = "SELECT * FROM fps WHERE district='$district'";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);
if($numrows>0){
	while($row = mysqli_fetch_array($result)){
		for($i=0;$i<count($columns);$i++){
            if ($columns[$i] !== "uniqueid") {
				filterData($row[$mapData[$columns[$i]]]);
				echo '"' . $row[$mapData[$columns[$i]]] . '",';
            }
        }
		echo "\n";
	}
}
 
exit();

?>