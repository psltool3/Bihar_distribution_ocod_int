<?php
require('util/Connection.php');
require('util/SessionCheck.php');
require('Header.php');

$id = $_POST['id'];
$tablename = "optimiseddata_".$id;
$tablename1 = "optimiseddata_".$id;
$leg = 0;
if(isset($_POST['step'])){
	if($_POST['step']=="leg1"){
		$leg = 1;
		$tablename = "optimiseddata_leg1_".$id;
		$tablename1 = "optimiseddata_leg1_".$id;
	}
	if($_POST['step']=="all"){
		$leg = 2;
		$leg_id = $_POST['legid'];
		$tablename = "optimiseddata_".$id;
		$tablename1 = "optimiseddata_leg1_".$leg_id;
	}
}

?>
<style>

		body {
            font-size: 15px; /* Set the base font size for the entire page */
        }

        /* Apply increased font size to specific elements */
        h3 {
            font-size: 24px; /* Increase font size for heading elements */
        }

        /* You can add similar styles for other elements as needed */
        /* For example: */
        th
        td {
            font-size: 18px; /* Increase font size for table headers and data cells */
        }

        .btn {
            font-size: 12px; /* Increase font size for buttons */
        }
		
		.table-container {
			width: 100%;
			overflow-x: auto;
		}
		
		table {
			width: 100%;
			border-collapse: collapse;
			background-color: #95b75d !important;
			color: black;
		}
		
        .thead tr th {
			background-color: #95b75d !important;
			color: black;
		}

		th,	td {
			border: 2px solid black;
			padding: 25px;
			text-align: center;
			color: black;
			border-color: black !important;
			
		}

		tr {
			border: 2px solid black; /* Set border for table rows */
		}
		.table > tfoot > tr > td {
			border-color: black !important;
			border-width: 2px !important;
		}


		/* Apply background color to even rows */
		#export_table tbody tr:nth-child(even) {
			background-color: #FFCF8B;
		}
    </style>

                <!-- START BREADCRUMB -->
                <ul class="breadcrumb">
                    <li><a href="#">Home</a></li>
                    <li class="active">Optimised Data View</li>
                </ul>
                <!-- END BREADCRUMB -->


				<!-- PAGE CONTENT WRAPPER -->
                <div class="page-content-wrap">

                    <div class="row">
                        <div class="col-md-12">

                            <!-- START SIMPLE DATATABLE -->
                            <div class="panel panel-default">
							<div class="panel-heading">
                                    <h3 class="panel-title">Optimised Data View</h3>
                                </div>
								<div style="float:right" style="margin:10px">
									<button id="downloadCSV" class="btn btn-warning" style="margin-bottom: 10px;" type="button">Download CSV</button>
									<button id="downloadXLSX" class="btn btn-success" style="margin-bottom: 10px;" type="button">Download XLSX</button>
								</div>
								<div class="row" style="margin-top:30px;float:left">
									<div class="col-md-8">
									</div>
									<div class="col-md-4">
										<div class="form-group">
											<label class="col-md-3 control-label">Districts</label>
											<div class="col-md-9">  
												<div class="input-group">
												<span class="input-group-addon"><span class="fa fa-certificate"></span></span>						
												<select class="form-control" id="district" name="district" onchange="fetchDataFromServer()">
													<option value=''>Select</option>
													<option value='all'>All</option>
												</select>
												</div>
												<span class="help-block">All option will work only for download</span>
											</div>
										</div>
									</div>
								</div>
                                <div class="panel-body">
                                 <div class="table-responsive">
								 <div class="table-container">
								<table id="export_table" class="table">
                                        <thead>
                                            <tr>												
												<th style="font-size:16px">Scenario</th>
												<th style="font-size:16px">From</th>
												<th style="font-size:16px">From_State</th>
												<th style="font-size:16px">From_ID</th>
												<th style="font-size:16px">From_Name</th>
												<th style="font-size:16px">From_District</th>
												<th style="font-size:16px">From_Lat</th>
												<th style="font-size:16px">From_Long</th>
												<th style="font-size:16px">To</th>
												<th style="font-size:16px">To_State</th>
												<th style="font-size:16px">To_ID</th>
												<th style="font-size:16px">To_Name</th>
												<th style="font-size:16px">To_District</th>
												<th style="font-size:16px">To_Lat</th>
												<th style="font-size:16px">To_Long</th>
												<th style="font-size:16px">Commodity</th>
												<th style="font-size:16px">Quantity</th>
												<th style="font-size:16px">Distance</th>
                                            </tr>
                                        </thead>
										 <tbody id="optimised_table">
										
										</tbody>
										
                                    </table>
									</div>
                                  </div>
                                </div>
                            </div>
                            <!-- END SIMPLE DATATABLE -->

                        </div>
                    </div>

                </div>
                <!-- PAGE CONTENT WRAPPER -->
            </div>
            <!-- END PAGE CONTENT -->
        </div>
        <!-- END PAGE CONTAINER -->

		<?php  require('DistrictAutocomplete.php'); ?>

    <!-- START SCRIPTS -->
        <!-- START PLUGINS -->
        <script type="text/javascript" src="js/plugins/jquery/jquery.min.js"></script>
        <script type="text/javascript" src="js/plugins/jquery/jquery-ui.min.js"></script>
        <script type="text/javascript" src="js/plugins/bootstrap/bootstrap.min.js"></script>
        <!-- END PLUGINS -->

        <!-- THIS PAGE PLUGINS -->
        <script type='text/javascript' src='js/plugins/icheck/icheck.min.js'></script>
        <script type="text/javascript" src="js/plugins/mcustomscrollbar/jquery.mCustomScrollbar.min.js"></script>
        <script type="text/javascript" src="js/plugins/datatables/jquery.dataTables.min.js"></script>
		<script type="text/javascript" src="js/plugins/tableexport/tableExport.js"></script>
		<script type="text/javascript" src="js/plugins/tableexport/jquery.base64.js"></script>
		<script type="text/javascript" src="js/plugins/tableexport/html2canvas.js"></script>
		<script type="text/javascript" src="js/plugins/tableexport/jspdf/libs/sprintf.js"></script>
		<script type="text/javascript" src="js/plugins/tableexport/jspdf/jspdf.js"></script>
		<script type="text/javascript" src="js/plugins/tableexport/jspdf/libs/base64.js"></script>
        <script type="text/javascript" src="js/plugins.js"></script>
        <script type="text/javascript" src="js/actions.js"></script>
        <!-- END PAGE PLUGINS -->

        <script>
		function getDateString(){
			var currentDate = new Date();
			var year = currentDate.getFullYear();
			var month = currentDate.getMonth() + 1; // Month is zero-based, so we add 1
			var day = currentDate.getDate();
			var str = year + "-" + month + "-" + day;
			return str;
		}
		
		document.getElementById('downloadCSV').addEventListener('click', async function() {
			try {
				var tableName = '<?php echo $tablename ?>';
				var tableName1 = '<?php echo $tablename1 ?>';
				var district = document.getElementById('district').value;
				const csvResponse = await fetch('api/DownloadOptimalDataOptimised.php?format=csv&tableName=' + tableName + '&tableName1=' + tableName1 + '&district=' + district);
				const csvBlob = await csvResponse.blob();
				downloadFile(csvBlob, 'Optimised_Data_' + getDateString() + '.csv');
			} catch (error) {
				console.error('Error downloading CSV file:', error);
			}
		});

		// Event listener for downloading XLSX
		document.getElementById('downloadXLSX').addEventListener('click', async function() {
			try {
				var tableName = '<?php echo $tablename ?>';
				var tableName1 = '<?php echo $tablename1 ?>';
				var district = document.getElementById('district').value;
				const excelResponse = await fetch('api/DownloadOptimalDataOptimised.php?format=xlsx&tableName=' + tableName + '&tableName1=' + tableName1 + '&district=' + district);
				const excelBlob = await excelResponse.blob();
				downloadFile(excelBlob, 'Optimised_Data_' + getDateString() + '.xlsx');
			} catch (error) {
				console.error('Error downloading XLSX file:', error);
			}
		});

		// Event listener for downloading PDF
		/*document.getElementById('downloadPDF').addEventListener('click', async function() {
			try {
				var tableName = '<?php echo $tablename ?>';	
				const pdfResponse = await fetch('api/DownloadOptimalDataOptimised.php?format=pdf&tableName='+tableName);
				const pdfBlob = await pdfResponse.blob();

				const url = window.URL.createObjectURL(pdfBlob);
				const link = document.createElement('a');
				link.href = url;
				link.download = 'Optimised_Data_' + getDateString() + '.pdf';
				link.click();
				window.URL.revokeObjectURL(url);
			} catch (error) {
				console.error('Error downloading PDF file:', error);
			}
		});*/
		
		// Functions for file download and PDF generation (similar to previous code)
		function downloadFile(blob, fileName) {
			const url = window.URL.createObjectURL(blob);
			const link = document.createElement('a');
			link.href = url;
			link.download = fileName;
			link.click();
			window.URL.revokeObjectURL(url);
		}
		
		function fetchDataFromServer(){
			var districtElement = document.getElementById('district');
			var district = districtElement.value;
			
			if(district==""){
				var options = districtElement.options;
				for (var i = 0; i < options.length; i++) {
					if (options[i].value != "all" && options[i].value != "") {
						districtElement.selectedIndex = i;
						district = options[i].value ;
						break;
					}
				}
			}
			
			var dataString = "district=" + district + "&tablename=" + "<?php echo $tablename ?>" + "&tablename1=" + "<?php echo $tablename1 ?>";
			
			$.ajax({
				type: "POST",
				url: "api/fetchOptimisedDataView.php",
				data: dataString,
				cache: false,
				error: function(){
					alert("timeout");
					$("#filter_button").attr("disabled",false);
				},
				timeout: 216000,
				success: function(result){
					//console.log(result);
					try{
						$('#optimised_table').empty();
						var resultarray = JSON.parse(result);
						var obj = resultarray["data"];
						for (var datafield in obj){
							var subpart = "<tr><td>" +  obj[datafield]["scenario"] +  "</td><td>"  + obj[datafield]["from"] +  "</td><td>"  + obj[datafield]["from_state"] +  "</td><td>"  + obj[datafield]["from_id"] +  "</td><td>"  + obj[datafield]["from_name"] +  "</td><td>"  + obj[datafield]["from_district"] +  "</td><td>"  + obj[datafield]["from_lat"] + "</td><td>" + obj[datafield]["From_long"] + "</td><td>" + obj[datafield]["to"] + "</td><td>" + obj[datafield]["to_state"] + "</td><td>" + obj[datafield]["to_id"] + "</td><td>" + obj[datafield]["to_name"] + "</td><td>" + obj[datafield]["to_district"] + "</td><td>" + obj[datafield]["to_lat"] + "</td><td>" + obj[datafield]["to_long"] + "</td><td>" + obj[datafield]["commodity"] + "</td><td>" + obj[datafield]["quantity"] + "</td><td>" + obj[datafield]["distance"] + "</td></tr>";
							
							$('#optimised_table').append(subpart);
						}
						var obj = resultarray["data1"];
						for (var datafield in obj){
							var subpart1 = "<tr><td>" +  obj[datafield]["scenario"] +  "</td><td>"  + obj[datafield]["from"] +  "</td><td>"  + obj[datafield]["from_state"] +  "</td><td>"  + obj[datafield]["from_id"] +  "</td><td>"  + obj[datafield]["from_name"] +  "</td><td>"  + obj[datafield]["from_district"] +  "</td><td>"  + obj[datafield]["from_lat"] + "</td><td>" + obj[datafield]["From_long"] + "</td><td>" + obj[datafield]["to"] + "</td><td>" + obj[datafield]["to_state"] + "</td><td>" + obj[datafield]["to_id"] + "</td><td>" + obj[datafield]["to_name"] + "</td><td>" + obj[datafield]["to_district"] + "</td><td>" + obj[datafield]["to_lat"] + "</td><td>" + obj[datafield]["to_long"] + "</td><td>" + obj[datafield]["commodity"] + "</td><td>" + obj[datafield]["quantity"] + "</td><td>" + obj[datafield]["distance"] + "</td></tr>";
							
							$('#optimised_table').append(subpart1);
						}
					}
					catch (error) {
					}
				}
			});
		}
		fetchDataFromServer();



		</script>

    </body>
</html>
