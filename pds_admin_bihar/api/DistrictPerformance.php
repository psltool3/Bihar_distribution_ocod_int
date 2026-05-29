<?php

require('../util/Connection.php');
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


// Check if format is specified in GET request
if (isset($_GET['format'])) {
    $format = $_GET['format'];
    
   $columns = ["to_district","total_tags","implemented_count","notimplemented_count","district_approved_count","notdistrict_approved_count","admin_approved_count","notadmin_approved_count"];

    $month = $_GET['month'];
	$parts = explode('_', $month);

	$month = $parts[0];
	$year = $parts[1];
	
	$tableData = array();
    array_push($tableData,$columns);
	
	
	$query = "SELECT id FROM optimised_table WHERE month='$month' AND  year='$year'";
	$result = mysqli_query($con, $query);
	if($result!=NULL or $result!=false){
		$row = mysqli_fetch_assoc($result);
		$query = "SELECT to_district, SUM(CASE WHEN status = 'implemented' THEN 1 ELSE 0 END) AS implemented_count, SUM(CASE WHEN status = 'implemented' THEN 0 ELSE 1 END) AS notimplemented_count, SUM(CASE WHEN approve_district = 'yes' THEN 1 ELSE 0 END) AS district_approved_count, SUM(CASE WHEN approve_district = 'yes' THEN 0 ELSE 1 END) AS notdistrict_approved_count, SUM(CASE WHEN approve_admin = 'yes' THEN 1 ELSE 0 END) AS admin_approved_count, SUM(CASE WHEN approve_district = 'yes' THEN 0 ELSE 1 END) AS notadmin_approved_count, COUNT(*) AS total_tags FROM optimiseddata_".$row['id']." GROUP BY to_district;";
		$result = mysqli_query($con,$query);
		if($result!=NULL or $result!=false){
			$numrows = mysqli_num_rows($result);
			if($numrows>0){
				while($row = mysqli_fetch_assoc($result))
				{
					$temp = array();
					for($i=0;$i<count($columns);$i++){
						array_push($temp,$row[$columns[$i]]);
					}
					array_push($tableData,$temp);
				}
			}
		}
	}
    
    // Filename for the downloaded file
    $filename = 'table_data';

    // Set headers for the chosen format
    switch ($format) {
        case 'csv':
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            outputCSV($tableData);
            break;

        case 'xlsx':
            // Create a new PhpSpreadsheet object
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set column names as the first row
            /*$columnIndex = 1;
            foreach ($columns as $columnName) {
                $sheet->setCellValueByColumnAndRow($columnIndex, 1, $columnName);
                $columnIndex++;
            }*/

            // Insert data tableData
            $rowIndex = 1;
            foreach ($tableData as $rowData) {
                $columnIndex = 1;
                foreach ($rowData as $value) {
                    $sheet->setCellValueByColumnAndRow($columnIndex, $rowIndex, $value);
                    $columnIndex++;
                }
                $rowIndex++;
            }


            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            break;

        case 'pdf':
            require('fpdf/fpdf.php');

            $pdf = new FPDF();
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 0);

            // Highlight the first row as header
            $pdf->SetFillColor(200, 220, 255); // Set background color
            $pdf->SetTextColor(0); // Reset text color
            $case = 0;
            foreach ($tableData as $row) {
                foreach ($row as $col) {
                    $pdf->Cell(30, 10, $col, 1, 0, 'C', true);
                }
                $pdf->Ln();
                $pdf->SetFillColor(255, 255, 255); 
            }

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
            echo $pdf->Output('S');
            break;

        default:
            echo 'Error : Invalid format specified.';
            break;
    }
} else {
    echo 'Error : Please specify a format in the GET request (e.g., ?format=pdf).';
}



// Function to output CSV data
function outputCSV($data) {
    $output = fopen('php://output', 'w');
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
}

exit();