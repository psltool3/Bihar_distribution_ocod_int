<?php
require('fpdf/fpdf.php');

class PDF extends FPDF {
    function header(){
        $this->SetFont('Arial','B',12);
        $this->Cell(0,10,'Landscape Table Example',0,1,'C');
    }

    function footer(){
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Page '.$this->PageNo(),0,0,'C');
    }

    function chapterTitle($title){
        $this->SetFont('Arial','B',12);
        $this->Cell(0,10,$title,0,1,'L');
        $this->Ln(2);
    }

    function chapterBody($body){
        $this->SetFont('Arial','',12);
        $this->MultiCell(0,10,$body);
        $this->Ln();
    }

    function addLandscapeTable($header, $data){
        // Set column widths
        $colWidths = array(50, 50, 50, 50);

        // Set font for table
        $this->SetFont('Arial','',10);

        // Add a page in landscape mode
        $this->AddPage('L');

        // Column names
        foreach($header as $colName){
            $this->Cell($colWidths[array_search($colName, $header)],10,$colName,1);
        }
        $this->Ln();

        // Data rows
        foreach($data as $row){
            foreach($row as $col){
                $this->Cell($colWidths[array_search($col, $row)],10,$col,1);
            }
            $this->Ln();
        }
    }
}

// Create instance of PDF class
$pdf = new PDF();

// Add a page
$pdf->AddPage();

// Add title
$pdf->chapterTitle('Landscape Table Example');

// Add some body text
$pdf->chapterBody('This is an example of how to create a landscape table using FPDF in PHP.');

// Define table header and data
$header = array('Name', 'Age', 'Country', 'Occupation');
$data = array(
    array('John Doe', 30, 'USA', 'Engineer'),
    array('Jane Smith', 25, 'UK', 'Teacher'),
    array('Tom Brown', 35, 'Canada', 'Doctor'),
    array('Emily Davis', 28, 'Australia', 'Artist')
);

// Add landscape table
$pdf->addLandscapeTable($header, $data);

// Output PDF
$pdf->Output();
?>
