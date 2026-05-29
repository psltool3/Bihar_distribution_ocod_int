<?php
require('util/Connection.php');
require('util/SessionCheck.php');
require('Header.php');
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
		
		.loader {
            border: 16px solid #3498db; /* Light grey */
            border-top: 16px solid #f3f3f3; /* Blue */
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 2s linear infinite;
            display: none; /* Changed to flex for centering text */
            justify-content: center;
            align-items: center;
            position: relative;
            background-color: #f9f9f9; /* Background color */
        }

        .loader::after {
            content: 'Loading';
            color: #000000; /* Text color */
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 14px;
        }


    </style>

                <!-- START BREADCRUMB -->
                <ul class="breadcrumb">
                    <li><a href="#">Home</a></li>
                    <li class="active">PDS Data</li>
                </ul>
                <!-- END BREADCRUMB -->

                <div class="page-content-wrap" style="background-color:#fff">

                    <div class="row">
                        <div class="col-md-12">

                            <!-- START SIMPLE DATATABLE -->
                            <div class="panel panel-default">
								<div class="panel-heading">
                                    <h3 class="panel-title" id="mainheading_big"></b></h3>
                                </div>
                            </div>
							<div class="row">
							<table class="table" id="optimisedtable" style="width: 100%; text-align: center;">
								<thead>
									<tr>
										<th>Scenario</th>
										<th>WH_Used</th>
										<th>FPS_Used</th>
										<th>Total_Allocation</th>
										<th>Total_QKM</th>
										<th>Average Distance</th>
									</tr>
								</thead>
								<tbody id="optimisedtable_body">
									<!-- Table body content -->
								</tbody>
							</table>
							<center><div class="loader" id="loader"></div></center>
							</div>
							<div class="row">
								<div class="col-md-4">
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
								<div class="col-md-4">
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
									<!--<a href="OptimisedDataLeg2.php"><button class="btn btn-info" style="margin-bottom: 10px;" type="button">Previous Data</button></a>-->
								</div>
                            <!-- END SIMPLE DATATABLE -->
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
		<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.4/xlsx.full.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.3/jspdf.umd.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.4/xlsx.full.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.3/jspdf.umd.min.js"></script>


		<script type="text/javascript" src="js/plugins.js"></script>
		<script type="text/javascript" src="js/actions.js"></script>
		
		
		<?php  require('DistrictAutocomplete.php'); ?>

		
        <!-- END PAGE PLUGINS -->

        <!-- START TEMPLATE -->
        
        <!-- END TEMPLATE -->

		<script>
		
		var modifiedData = {};
		
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
		
		function getDateString(){
			var currentDate = new Date();
			var year = currentDate.getFullYear();
			var month = currentDate.getMonth() + 1; // Month is zero-based, so we add 1
			var day = currentDate.getDate();
			var str = year + "-" + month + "-" + day;
			return str;
		}

		function edit_entry(temp_id){
			post({uid: temp_id} ,"FPSEdit.php");
		}
		
		function approvalFunction(selectedId){
			newvalue = document.getElementById(selectedId).value;
			if(newvalue=="yes"){
				modifiedData[selectedId] = "yes";
			}
			else{
				if(modifiedData.hasOwnProperty(selectedId)){
					delete modifiedData[selectedId];
				}
			}
		}
		
		
		function sendData(){
			post(modifiedData ,"api/SaveData.php");
		}
	
	
	document.getElementById('downloadCSV').addEventListener('click', async function() {
		try {
			var month = document.getElementById("month").value;
			var district = document.getElementById("district").value;
			const csvResponse = await fetch('api/DownloadOptimalData.php?format=csv&month=' + month + "&district=" + district);
			const csvBlob = await csvResponse.blob();
			downloadFile(csvBlob, 'Rollout_Plan_leg2_' + getDateString() + '.csv');
		} catch (error) {
			console.error('Error downloading CSV file:', error);
		}
	});
	
	document.getElementById('downloadPDF').addEventListener('click', async function() {
			try {
				var month = document.getElementById("month").value;
				var district = document.getElementById("district").value;
				const csvResponse = await fetch('api/DownloadOptimalData.php?format=pdf&month=' + month + "&district=" + district);
				const csvBlob = await csvResponse.blob();
				downloadFile(csvBlob, 'Rollout_Plan_leg2_' + getDateString() + '.pdf');
			} catch (error) {
				console.error('Error downloading PDF file:', error);
			}
		});

	// Event listener for downloading XLSX
	document.getElementById('downloadXLSX').addEventListener('click', async function() {
		try {
			var month = document.getElementById("month").value;
			var district = document.getElementById("district").value;
			const excelResponse = await fetch('api/DownloadOptimalData1.php?format=xlsx&month=' + month + "&district=" + district);
			const excelBlob = await excelResponse.blob();
			downloadFile(excelBlob, 'Rollout_Plan_leg2_' + getDateString() + '.xlsx');
		} catch (error) {
			console.error('Error downloading XLSX file:', error);
		}
	});

	// Event listener for downloading PDF
	/*document.getElementById('downloadPDF').addEventListener('click', async function() {
		try {
			var month = document.getElementById("month").value;			
			const pdfResponse = await fetch('api/DownloadOptimalData.php?format=pdf&month='+month);
			const pdfBlob = await pdfResponse.blob();

			const url = window.URL.createObjectURL(pdfBlob);
			const link = document.createElement('a');
			link.href = url;
			link.download = 'Bihar_Warehouse_' + getDateString() + '.pdf';
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

	var currentDate = new Date();
	var currentMonth = currentDate.getMonth();
	var monthNames = ['jan', 'feb', 'march', 'april', 'may', 'june', 'july', 'aug', 'sept', 'oct', 'nov', 'dec'];
	var currentMonthValue = monthNames[currentMonth];

	var dropdown = document.getElementById('month');

	for (var i = 0; i < dropdown.options.length; i++) {
		if (dropdown.options[i].value === currentMonthValue) {
			dropdown.options[i].selected = true;
			break;
		}
	}
	
	function formatNumberWithCommas(value) {
		const formattedNumber = Number(value).toFixed(2);

		// Separate the integer and decimal parts
		const parts = formattedNumber.split('.');
		let integerPart = parts[0];
		const decimalPart = parts[1] || '';

		// Add commas every two digits from the right in the integer part
		integerPart = integerPart.replace(/\B(?=(\d{2})+(?!\d))/g, ',');

		// Combine the integer and decimal parts and return the formatted number
		return integerPart + '.' + decimalPart;
	}
	
	function formatNumberWithCommasWithoutDecimal(value) {
		const roundedNumber = Math.round(value);

		// Separate the integer and decimal parts
		const parts = roundedNumber.toString().split('.');
		let integerPart = parts[0];
  
		// Add commas every three digits from the right in the integer part
		integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
	  
		// Return the formatted number
		return integerPart;
	}

	function fetchDataFromServer(){
		var district = document.getElementById("district").value;
		var month = document.getElementById("month").value;
		
		if(district==""){
			district = "";
			var dropdown = document.getElementById("district");
			for (var i = 0; i < dropdown.options.length; i++) {
				var option = dropdown.options[i];
				if (option.value.trim() !== "" && option.value.trim().toLowerCase() !== "all") {
					dropdown.selectedIndex = i;
					district = option.value;
					break;
				}
			}
		}
		
		if(district=="all"){
			district = "";
		}
		
		//var dataString = 'approved='+ approved + '&reviewed='+ reviewed + '&month='+ month;
		var dataString = 'month='+ month + '&district=' + district;
		
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
				error: function(jqXHR, textStatus, errorThrown) {
					// Construct a detailed error message
					var errorMessage = "Error occurred: ";
					errorMessage += "Status: " + textStatus + ", ";
					errorMessage += "Error: " + errorThrown + ", ";
					errorMessage += "Response: " + jqXHR.responseText;
					
					// Display the error message in an alert
					alert(errorMessage);
					$("#filter_button").attr("disabled",false);
				},
				timeout: 590000,
				success: function(result){
					$('#table_body').empty();
					try{
						var resultarray = JSON.parse(result);
						var obj = resultarray["data"];
						
						for (var dataField in obj) {
							var status = "Not Implemented";
							if(obj[dataField]["status"]=="implemented"){
								status = "Implemented"
							}
							
							var subpart1 = "<tr><td>" +  obj[dataField]["scenario"] +  "</td><td>"  + obj[dataField]["from"] +  "</td><td>"  + obj[dataField]["from_state"] +  "</td><td>"  + obj[dataField]["from_id"] +  "</td><td>"  + obj[dataField]["from_name"] +  "</td><td>"  + obj[dataField]["from_district"] +  "</td><td>"  + obj[dataField]["from_lat"] +  "</td><td>"  + obj[dataField]["from_long"] +  "</td><td>"  + obj[dataField]["to"] +  "</td><td>"  + obj[dataField]["to_state"] +  "</td><td>"  + obj[dataField]["to_id"] +  "</td><td>"  + obj[dataField]["to_name"] +  "</td><td>"  + obj[dataField]["to_district"] +  "</td><td>"  + obj[dataField]["to_lat"] +  "</td><td>"  + obj[dataField]["to_long"] +  "</td><td>"  + obj[dataField]["commodity"] +  "</td><td>"  + obj[dataField]["quantity"] +  "</td><td>"  + obj[dataField]["distance"] +  "</td><td>"  + status + "</td></tr>";
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
	
	
	function fetchTableDataFromServer(){
		var district = document.getElementById("district").value;
		var month = document.getElementById("month").value;
		
		if(district==""){
			district = "";
			var dropdown = document.getElementById("district");
			for (var i = 0; i < dropdown.options.length; i++) {
				var option = dropdown.options[i];
				if (option.value.trim() !== "" && option.value.trim().toLowerCase() !== "all") {
					dropdown.selectedIndex = i;
					district = option.value;
					break;
				}
			}
		}
		
		if(district=="all"){
			district = "";
		}
		
		//var dataString = 'approved='+ approved + '&reviewed='+ reviewed + '&month='+ month;
		var dataString = 'month='+ month + '&district=' + district;
		
		if(dataString=='')
		{
			alert("Please Fill All Fields");
		}
		else
		{
			loader.style.display = 'block';
			$("#filter_button").attr("disabled",true);
			$.ajax({
				type: "POST",
				url: "api/FetchRolloutPlanTable.php",
				data: dataString,
				cache: false,
				error: function(jqXHR, textStatus, errorThrown) {
					// Construct a detailed error message
					var errorMessage = "Error occurred: ";
					errorMessage += "Status: " + textStatus + ", ";
					errorMessage += "Error: " + errorThrown + ", ";
					errorMessage += "Response: " + jqXHR.responseText;
					
					// Display the error message in an alert
					alert(errorMessage);
					$("#filter_button").attr("disabled",false);
				},
				timeout: 590000,
				success: function(result){
					try{
						var resultarray = JSON.parse(result);
						var obj = resultarray["table"];
						var table = document.getElementById("optimisedtable");
						table.innerHTML = "";
						//table.appendChild(thead);
						
						var newRow = table.insertRow();
						var cell1 = newRow.insertCell(0);
						var cell2 = newRow.insertCell(1);
						var cell3 = newRow.insertCell(2);
						var cell4 = newRow.insertCell(3);
						var cell5 = newRow.insertCell(4);
						var cell6 = newRow.insertCell(5);
						
						cell1.innerHTML = obj["Scenario_optimised"];
						cell2.innerHTML = obj["WH_Used_Optimised"];
						cell3.innerHTML = obj["FPS_Used"];
						cell4.innerHTML = formatNumberWithCommas(obj["Demand"]);
						cell5.innerHTML = formatNumberWithCommas(obj["Total_QKM_Optimised"]);
						cell6.innerHTML = formatNumberWithCommas(obj["Average_Distance_Optimised"]);

						var newRow = table.insertRow();
						var cell1 = newRow.insertCell(0);
						var cell2 = newRow.insertCell(1);
						var cell3 = newRow.insertCell(2);
						var cell4 = newRow.insertCell(3);
						var cell5 = newRow.insertCell(4);
						var cell6 = newRow.insertCell(5);
						
						cell1.innerHTML = obj["Scenario"];
						cell2.innerHTML = obj["WH_Used"];
						cell3.innerHTML = obj["FPS_Used"];
						cell4.innerHTML = formatNumberWithCommas(obj["Demand"]);
						cell5.innerHTML = formatNumberWithCommas(obj["Total_QKM"]);
						cell6.innerHTML = formatNumberWithCommas(obj["Average_Distance"]);
						
						var newRow = table.insertRow();
						var cell1 = newRow.insertCell(0);
						var cell2 = newRow.insertCell(1);
						var cell3 = newRow.insertCell(2);
						var cell4 = newRow.insertCell(3);
						var cell5 = newRow.insertCell(4);
						var cell6 = newRow.insertCell(5);

						cell1.innerHTML = obj["Scenario_Baseline"];
						cell2.innerHTML = obj["WH_Used_Baseline"];
						cell3.innerHTML = obj["FPS_Used_Baseline"];
						cell4.innerHTML = obj["Demand_Baseline"];
						cell5.innerHTML = obj["Total_QKM_Baseline"];
						cell6.innerHTML = obj["Average_Distance_Baseline"];
						
						table.style.padding = "20px";
						table.style.marginBottom = "50px";
						table.style.fontSize = "20px"; 
						table.style.color = "black"; // Add margin-left
						table.style.textAlign = "center";

						var tableHeaders = table.getElementsByTagName('th');
						for (var i = 0; i < tableHeaders.length; i++) {
							tableHeaders[i].style.fontSize = "20px"; // Increase font size for headers
						}
						loader.style.display = 'none';
					}
					catch (error) {
						console.log(error);
						loader.style.display = 'none';
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
		timeout: 216000,
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
	
	
	setTimeout(fetchTableDataFromServer, 5000);

			
    </script>
    </body>
</html>
