<?php
require('util/Connection.php');
require('util/SessionCheck.php');
require('Header.php');
$district = $_SESSION['district_district'];

$query = "SELECT * FROM optimised_table ORDER BY last_updated DESC LIMIT 1";
$result = mysqli_query($con,$query);
$response = array();
$id = "";
while($row = mysqli_fetch_array($result))
{
	$id= $row["id"];
}


$tablename = "optimiseddata_".$id;

$query = "SELECT from_district FROM " . $tablename . " WHERE 1";
$result = mysqli_query($con,$query);
$totalids = mysqli_num_rows($result);

$query = "SELECT approve_district FROM " . $tablename . " WHERE approve_district='yes'";
$result = mysqli_query($con,$query);
$totalidsreviewed = mysqli_num_rows($result);

$query = "SELECT new_id_district FROM " . $tablename . " WHERE new_id_district<>''";
$result = mysqli_query($con,$query);
$totalidsrequested = mysqli_num_rows($result);

$query = "SELECT approve_admin FROM " . $tablename . " WHERE approve_admin='yes'";
$result = mysqli_query($con,$query);
$totalidsapproved = mysqli_num_rows($result);
							
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
                    <li class="active">Optimised Data</li>
                </ul>
                <!-- END BREADCRUMB -->


				<!-- PAGE CONTENT WRAPPER -->
                <div class="page-content-wrap" style="background-color:#fff">

                    <div class="row">
                        <div class="col-md-12">

                            <!-- START SIMPLE DATATABLE -->
                            <div class="panel panel-default">
								<div class="panel-heading">
                                    <h3 class="panel-title" id="mainheading_big"></h3>
                                </div>
                            </div>
							<div class="row">
								<div class="col-md-3 mb-4">
									<div class="card h-100"
										style="background-color:#56A5FF; color:white; padding:20px; font-weight: bold;">
										<div style="font-size:25px" id="countimplemented"></div>
										<div style="font-size:15px">Implemented</div>
									</div>
								</div>
								<div class="col-md-3 mb-4">
									<div class="card h-100"
										style="background-color:#3FDBBC; color:white; padding:20px; font-weight: bold;">
										<div style="font-size:25px" id="countnotimplemented"></div>
										<div style="font-size:15px">Not Implemented</div>
									</div>
								</div>
								<div class="col-md-3 mb-4">
									</br></br>
									<div class="form-group">
										<label class="col-md-3 control-label">Status</label>
										<div class="col-md-9">  
											<div class="input-group">
											<span class="input-group-addon"><span class="fa fa-certificate"></span></span>						
											<select class="form-control" id="status" name="status" onchange="fetchDataFromServer()">
												<option value=''>Select</option>
												<option value='implemented'>Already Implemented</option>
												<option value='not implemented'>Not Implemented</option>
											</select>
											</div>
										</div>
									</div>
								</div>
								<div class="col-md-3 mb-4">
									</br></br>
									<div class="form-group">
										<label class="col-md-3 control-label">Month</label>
										<div class="col-md-9">  
											<div class="input-group">
											<span class="input-group-addon"><span class="fa fa-certificate"></span></span>						
											<select class="form-control" id="month" name="month" onchange="fetchDataFromServer()">
												<option value=''>Select</option>
											</select>
											</div>
										</div>
									</div>
								</div>
							</div>
							</br></br>
								<div style="float:right">
									<button id="downloadCSV" class="btn btn-warning" style="margin-bottom: 10px;" type="button">Download CSV</button>
									<button id="downloadXLSX" class="btn btn-success" style="margin-bottom: 10px;" type="button">Download XLSX</button>
									<button id="downloadPDF" class="btn btn-danger" style="margin-bottom: 10px;" type="button">Download PDF</button>
								</div>
								</br></br>
								<button class='btn btn-info pull-right' onClick='acceptAll()' type='button' style='margin-left:10px;'>Accept All</button><button class='btn btn-primary pull-right' onClick='sendData()' type='button'>Save</button>
								</br></br>
								<div class="table-container">
                                    <table id="export_table" class="table" >
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
												<th style="font-size:16px">Quantity(Qtl)</th>
												<th style="font-size:16px">Distance(Km)</th>
												<th style="font-size:16px">Status</th>
                                            </tr>
                                        </thead>
										 <tbody id="table_body">
											
                                        </tbody>
                                    </table>
								</div>

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
        <!-- END PAGE PLUGINS -->

        <!-- START TEMPLATE -->
        <script type="text/javascript" src="js/plugins.js"></script>
        <script type="text/javascript" src="js/actions.js"></script>
        <!-- END TEMPLATE -->
<script>


	function post(params,file) {
		method = "post";
		path = file;

		var form = document.createElement("form");
		form.setAttribute("method", method);
		form.setAttribute("action", path);

		for(var key in params) {
			if(params.hasOwnProperty(key)) {
				var hiddenField = document.createElement("input");
				hiddenField.setAttribute("type", "hidden");
				hiddenField.setAttribute("name", key);
				hiddenField.setAttribute("value", params[key]);
				form.appendChild(hiddenField);
			 }
		}
		document.body.appendChild(form);
		form.submit();
	}

	document.getElementById('downloadCSV').addEventListener('click', async function() {
		try {
			var month = document.getElementById("month").value;
			const csvResponse = await fetch('api/DownloadOptimalData.php?format=csv&month='+month);
			const csvBlob = await csvResponse.blob();
			downloadFile(csvBlob, 'Rollout_Plan.csv');
		} catch (error) {
			console.error('Error downloading CSV file:', error);
		}
	});

	// Event listener for downloading XLSX
	document.getElementById('downloadXLSX').addEventListener('click', async function() {
		try {
			var month = document.getElementById("month").value;
			const excelResponse = await fetch('api/DownloadOptimalData.php?format=xlsx&month='+month);
			const excelBlob = await excelResponse.blob();
			downloadFile(excelBlob, 'Rollout_Plan.xlsx');
		} catch (error) {
			console.error('Error downloading XLSX file:', error);
		}
	});
	
	// Event listener for downloading XLSX
	document.getElementById('downloadPDF').addEventListener('click', async function() {
		try {
			var month = document.getElementById("month").value;
			const excelResponse = await fetch('api/DownloadOptimalData.php?format=pdf&month='+month);
			const excelBlob = await excelResponse.blob();
			downloadFile(excelBlob, 'Rollout_Plan.pdf');
		} catch (error) {
			console.error('Error downloading PDF file:', error);
		}
	});

	// Functions for file download and PDF generation (similar to previous code)
	function downloadFile(blob, fileName) {
		const url = window.URL.createObjectURL(blob);
		const link = document.createElement('a');
		link.href = url;
		link.download = fileName;
		link.click();
		window.URL.revokeObjectURL(url);
	}
	
	var modifiedIdData = {};
	function markReview(selectedId){
		if(modifiedIdData[selectedId] !== undefined) {
			delete modifiedIdData[selectedId];
			document.getElementById(selectedId).className  = "btn btn-info";
		}
		else{
			modifiedIdData[selectedId] = "yes";
			document.getElementById(selectedId).className  = "btn btn-danger";
		}
	}
	
	function sendData(){
		post(modifiedIdData ,"api/SaveDataRolloutPlan.php");
	}
	
	var uniqueid_bool_array = [];
	
	function acceptAll(){
		for (let i = 0; i < uniqueid_bool_array.length; i++) {
			markReview(uniqueid_bool_array[i]);
		}
	}

	function fetchDataFromServer(){
				var month = document.getElementById("month").value;
				var status = document.getElementById("status").value;
				
				var dataString = 'month='+ month + '&status=' + status;
				if(dataString=='')
				{
					alert("Please Fill All Fields");
				}
				else
				{
					$("#filter_button").attr("disabled",true);
					$.ajax({
						type: "POST",
						url: "api/FetchRolloutPlan.php",
						data: dataString,
						cache: false,
						error: function(){
							alert("timeout");
							$("#filter_button").attr("disabled",false);
						},
						timeout: 59000,
						success: function(result){
							$('#table_body').empty();
							console.log(result);
							try{
								var resultarray = JSON.parse(result);
								uniqueid_bool_array = [];
								var countimplemented = resultarray["implemented"];
								var countnotimplemented = resultarray["notimplemented"];
								document.getElementById("countimplemented").innerHTML = countimplemented;
								document.getElementById("countnotimplemented").innerHTML = countnotimplemented;
								
								var obj = resultarray["data"];
								for (var dataField in obj) {
									var uniqueid = obj[dataField]["from_id"] + "_" + obj[dataField]["to_id"] + "_" + obj[dataField]["commodity"];
									
									var status = obj[dataField]["status"];
									var status_part = "";
									if(status==null || status==""){
										status_part = "<button class='btn btn-info' id=\"" + uniqueid + "\" onClick='markReview(\"" + uniqueid + "\")'>Mark as Implemented</button>"; 
										uniqueid_bool_array.push(uniqueid);
									}
									else if(status=="implemented"){
										status_part = "Already Implemented";
									}
									
									var subpart1 = "<tr><td>" +  obj[dataField]["scenario"] +  "</td><td>"  + obj[dataField]["from"] +  "</td><td>"  + obj[dataField]["from_state"] +  "</td><td>"  + obj[dataField]["from_id"] +  "</td><td>"  + obj[dataField]["from_name"] +  "</td><td>"  + obj[dataField]["from_district"] +  "</td><td>"  + obj[dataField]["from_lat"] +  "</td><td>"  + obj[dataField]["from_long"] +  "</td><td>"  + obj[dataField]["to"] +  "</td><td>"  + obj[dataField]["to_state"] +  "</td><td>"  + obj[dataField]["to_id"] +  "</td><td>"  + obj[dataField]["to_name"] +  "</td><td>"  + obj[dataField]["to_district"] +  "</td><td>"  + obj[dataField]["to_lat"] +  "</td><td>"  + obj[dataField]["to_long"] +  "</td><td>"  + obj[dataField]["commodity"] +  "</td><td>"  + obj[dataField]["quantity"] +  "</td><td>"  + obj[dataField]["distance"] + "</td><td>"  + status_part + "</td></tr>";
									$('#table_body').append(subpart1);
								}
								
								
							}
							catch (error) {
								console.log(error);
							}
						}
					});
				}
			}
			
			var dataString = "";
			$.ajax({
				type: "POST",
				url: "api/fetchTableData.php",
				data: dataString,
				cache: false,
				error: function(){
					alert("timeout");
					$("#filter_button").attr("disabled",false);
				},
				timeout: 59000,
				success: function(result){
					try{
						var data = JSON.parse(result);
						var monthYearCombinations = data.map(item => `${item.month}_${item.year}`);
						var dropdown = document.getElementById("month");

						var year = data[0].year;
						var month = data[0].month;
						var applicable = data[0].applicable;
						var lastUpdated = data[0].last_updated;

						var resultString = "Optimised Data for Year <b>" + year + "</b> and Month <b>" + month + "</b>, Applicable for <b>" + applicable + "</b>. Last updated at <b>" + lastUpdated + "</b>";
						document.getElementById("mainheading_big").innerHTML = resultString;

						  // Clear existing options
						  dropdown.innerHTML = '';

						  // Add new options based on the array
						  monthYearCombinations.forEach(function(item) {
							var option = document.createElement("option");
							option.value = item;
							option.text = item;
							dropdown.add(option);
						  });
						  fetchDataFromServer();
					}
					catch (error) {
					}
				}
			});
			
    
</script>

    </script>
    </body>
</html>
