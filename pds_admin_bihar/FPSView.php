<?php
require('util/Connection.php');
require('util/SessionCheck.php');
require('Header.php');

$id = $_POST['id'];
$tablename = "fps_".$id;

?>
<style>
    td {
            font-size: 15px; /* Increase font size for table headers and data cells */
        }
        .table thead tr th {
    background-color: #95b75d !important;
    /* border: 2px solid #777; */
    color: black;
    /* Optional: Font size for table header */
}
    </style>

                <!-- START BREADCRUMB -->
                <ul class="breadcrumb">
                    <li><a href="FPS.php">Home</a></li>
                    <li class="active">FPS View</li>
                </ul>
                <!-- END BREADCRUMB -->


				<!-- PAGE CONTENT WRAPPER -->
                <div class="page-content-wrap">

                    <div class="row">
                        <div class="col-md-12">

                            <!-- START SIMPLE DATATABLE -->
                            <div class="panel panel-default">
							<div class="panel-heading">
                                    <h3 class="panel-title">FPS</h3>
                                </div>
								<div style="float:right" style="margin:10px">
									<button id="downloadCSV" class="btn btn-warning" style="margin-bottom: 10px;" type="button">Download CSV</button>
									<button id="downloadXLSX" class="btn btn-success" style="margin-bottom: 10px;" type="button">Download XLSX</button>
								</div>
								<div class="row" style="float:right;margin-top:20px">
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
												</select>
												</div>
											</div>
										</div>
									</div>
								</div>
                                <div class="panel-body">
                                 <div class="table-responsive">
                                    <table id="export_table" class="table">
                                        <thead>
                                            <tr>
												<th style="font-size:16px">District</th>
												<th style="font-size:16px">Name of FPS</th>
												<th style="font-size:16px">FPS ID</th>
												<th style="font-size:16px">Model FPS/Normal FPS</th>
												<th style="font-size:16px">Latitude</th>
												<th style="font-size:16px">Longitude</th>
												<th style="font-size:16px">Demand of Wheat(Qtl)</th>
												<th style="font-size:16px">Demand of FRice(Qtl)</th>
                                            </tr>
                                        </thead>
										 <tbody id="fps_table">
										</tbody>
                                    </table>
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

        <!-- START TEMPLATE -->
        
		<?php  require('DistrictAutocomplete.php'); ?>
        <!-- END TEMPLATE -->
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
				const csvResponse = await fetch('api/DownloadOptimalDataFPS.php?format=csv&tableName='+tableName);
				const csvBlob = await csvResponse.blob();
				downloadFile(csvBlob, 'Bihar_FPS_' + getDateString() + '.csv');
			} catch (error) {
				console.error('Error downloading CSV file:', error);
			}
		});

		// Event listener for downloading XLSX
		document.getElementById('downloadXLSX').addEventListener('click', async function() {
			try {
				var tableName = '<?php echo $tablename ?>';
				const excelResponse = await fetch('api/DownloadOptimalDataFPS.php?format=xlsx&tableName='+tableName);
				const excelBlob = await excelResponse.blob();
				downloadFile(excelBlob, 'Bihar_FPS_' + getDateString() + '.xlsx');
			} catch (error) {
				console.error('Error downloading XLSX file:', error);
			}
		});

		// Event listener for downloading PDF
		/*document.getElementById('downloadPDF').addEventListener('click', async function() {
			try {
				var tableName = '<?php echo $tablename ?>';	
				const pdfResponse = await fetch('api/DownloadOptimalDataFPS.php?format=pdf&tableName='+tableName);
				const pdfBlob = await pdfResponse.blob();

				const url = window.URL.createObjectURL(pdfBlob);
				const link = document.createElement('a');
				link.href = url;
				link.download = 'Pb_Warehouse_' + getDateString() + '.pdf';
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
			
			var dataString = "district=" + district + "&tablename=" + '<?php echo $tablename ?>';
			
			
			$.ajax({
				type: "POST",
				url: "api/fetchFPSViewData.php",
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
						$('#fps_table').empty();
						var resultarray = JSON.parse(result);
						var obj = resultarray["data"];
						console.log(obj);
						for (var datafield in obj){
							var temp_id = obj[datafield]["uniqueid"];
							var status = obj[datafield]["active"];
							if(status==1){
								status = "<span style='padding:5px' class='btn-success btn-rounded'>Active</span>";
							}
							else{
								status = "<span style='padding:5px' class='btn-danger btn-rounded'>InActive</span>";
							}
							var subpart = "<tr><td>" +  obj[datafield]["district"] +  "</td><td>"  + obj[datafield]["name"] +  "</td><td>"  + obj[datafield]["id"] +  "</td><td>"  + obj[datafield]["type"] +  "</td><td>"  + obj[datafield]["latitude"] +  "</td><td>"  + obj[datafield]["longitude"] +  "</td><td>"  + obj[datafield]["demand"] +"</td><td>"  + obj[datafield]["demand_rice"]+  "</td></tr>";
							$('#fps_table').append(subpart);
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
