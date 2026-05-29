<?php
require('../util/Connection.php');

$mapData = [
    "District" => "district",
    "Name of DCP" => "name",
    "DCP ID" => "id",
    "Type" => "type",
    "Latitude" => "latitude",
    "Longitude" => "longitude",
    "Procurement of FRice" => "demand",
	"Procurement of Wheat" => "demand_rice",
	"Active/Not-Active" => "active"
];

// Reverse mapping
$reverseMapData = array_flip($mapData);


// Filter the excel data 
function filterData(&$str){ 
    $str = preg_replace("/\t/", "\\t", $str); 
    $str = preg_replace("/\r?\n/", "\\n", $str); 
    if(strstr($str, '"')) $str = '"' . str_replace('"', '""', $str) . '"'; 
} 
 
// Excel file name for download 
$fileName = "DCPTemplate_" . date('d-m-Y') . ".csv"; 

$columns = array();

$query = "SHOW COLUMNS FROM dcp";
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
header("Content-Type: application/vnd.ms-excel"); 
header("Content-Disposition: attachment; filename=\"$fileName\""); 
 
// Render excel data 
echo $excelDataColumns; 
 
exit();

?>