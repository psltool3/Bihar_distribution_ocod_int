<?php
require('util/Connection.php');
require('util/SessionCheck.php');
require('Header.php');
?>

<head>
	<link href='https://fonts.googleapis.com/css?family=Roboto+Condensed:400,300' rel='stylesheet' type='text/css'>

</head>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
	.multiselect {
	  width: 200px;
	  z-index: 2;
	}

	.selectBox {
	  position: relative;
	  z-index: 3;
	}

	.selectBox select {
	  width: 100%;
	  font-weight: bold;
	  z-index: 4;
	}

	.overSelect {
	  position: absolute;
	  left: 0;
	  right: 0;
	  top: 0;
	  bottom: 0;
	  z-index: 5;
	}

	#checkboxes {
	  display: none;
	  border: 1px #dadada solid;
	  color:#000;
	  z-index: 6;
	}

	#checkboxes label {
	  display: block;
	  color:#000;
	  z-index: 7;
	}

	#checkboxes label:hover {
	  background-color: #1e90ff;
	  z-index: 7;
	}

	#processingPopup {
		display: none;
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background-color: rgba(255, 255, 255, 0.8);
		align-items: center;
		justify-content: center;
		z-index: 9999;
	}

	#processingPopup .spinner {
		border: 6px solid #3498db;
		border-top: 6px solid #f39c12;
		border-radius: 50%;
		width: 40px;
		height: 40px;
		animation: spin 1s linear infinite;
	}

	#optimisedtable {
		border-collapse: collapse;
		width: 100%;
		margin-top: 0px;
	}

	#optimisedtable th,
	#optimisedtable td {
		border: 1px solid #ddd;
		padding: 8px;
		text-align: center;
	}

	#optimisedtable th {
		background-color: #5E35B1;
		color: white;
	}

	#optimisedtable tbody tr:nth-child(even) {
		background-color: #f2f2f2;
	}

	#optimisedtable tbody tr:hover {
		background-color: #ddd;
	}

	.help-block b {
		font-weight: bold;
	}

	*,
	*:before,
	*:after {
		box-sizing: border-box;
	}

	/* html {
	  font-family: 'Roboto Condensed', sans-serif;
	  display: flex;
	  justify-content: center;
	  align-items: center;
	  text-align: center;
	  height: 100%;
	  color: #ECEFF1;
	  background-image: radial-gradient(lighten(#263238, 20%), #263238);
	} */

	.toggle {
		position: relative;
		display: block;
		margin: 0;
		width: 140px;
		height: 50px;
		color: black;
		outline: 0;
		text-decoration: none;
		border-radius: 60px;
		border: 2px solid #546E7A;
		background-color: white;
		transition: all 500ms;
		cursor: pointer;
	}

	.toggle:active {
		background-color: darken(red, 5%);
	}

	.toggle:hover:not(.toggle--moving):after {
		background-color: green;
	}

	.toggle:after {
		content: attr(data-content);
		/* Use content attribute to display On/Off */
		display: block;
		position: absolute;
		top: 0px;
		bottom: 1px;
		left: 1px;
		width: calc(50% - 4px);
		line-height: 52px;
		/* Adjust line-height for vertical centering */
		text-align: center;
		text-transform: uppercase;
		font-size: 20px;
		color: white;
		background-color: red;
		border: 2px solid;
		transition: all 500ms;
		border-radius: 50px;
	}

	.toggle--on:after {
		transform: translate(100%, 0);
		color: whitesmoke;
		background-color: green;
	}

	.toggle--off:after {
		color: whitesmoke;
		background-color: red;
	}

	.toggle--moving {
		background-color: darken(#263238, 5%);
	}

	.toggle--moving:after {
		color: transparent;
		border-color: darken(#546E7A, 8%);
		background-color: darken(white, 10%);
		transition: color 0s, transform 500ms, border-radius 500ms, background-color 500ms;
	}

	/* h1 {
	  font-size: 34px;
	  margin-top: 0;
	  margin-bottom: -12px;
	} */

	@keyframes spin {
		0% {
			transform: rotate(0deg);
		}

		100% {
			transform: rotate(360deg);
		}
	}

	.btn {
		border-radius: 20px;
		/* Set border radius */
		/* Add other button styles as needed */
	}

	.upload_button_class {
		background-color: #F2F3F5;
		border-radius: 30px;
		box-shadow: -10px -10px 15px 0 #f6f6f6, 10px 10px 15px 0 #cecece;
		color: #676767;
		height: 50;
		margin: auto;
		padding: 0;
		/* Adjust padding as needed */
		text-align: center;
		transition: all .2s ease;
		width: auto;
		border: none;
		/* Remove border for this button */
		cursor: pointer;
		/* Add cursor pointer to indicate interactivity */
		display: flex;
		/* Allow flexible layout */
		justify-content: center;
		/* Center content horizontally */
		align-items: center;

	}

	.panel-footer {
		padding: 0;
		/* Remove padding for the entire panel-footer */
	}

	.panel-footer .btn {
		padding: 2px 10px;
		/* Apply padding to the button */
	}
	#optimisedtable th {
    text-align: center; /* Center-align the text within table headers */
}
*{
      box-sizing: border-box;
    }

    button {
      outline: none;
      cursor: pointer;
    }

    .icon {
      display: inline-block;
      width: 1em;
      height: 1em;
      fill: currentColor;
    }

    body {
      font-family: 'Open Sans', sans-serif;
      font-size: 16px;
      color: #fff;
      background: linear-gradient(to right, #566a39 0%, #75986f 100%);
    }

	.button-wrapper {
  position: relative;
  display: inline-block;
  padding: 2px 3px; /* Adjust padding */
  min-width: 10px; /* Adjust minimum width */
  min-height: 40px; /* Adjust minimum height */
  border-radius: 15px; /* Adjust border-radius */
  box-shadow: 0px -1px 1px rgba(255, 255, 255, 0.22), inset 0px -1px 3px rgba(0, 0, 0, 0.2);
}

.button {
  position: relative;
  height: 40px; /* Adjust height */
  min-width: 5px; /* Adjust minimum width */
  padding: 0 5px; /* Adjust padding */
  border-radius: 15px; /* Adjust border-radius */
  background: #ff005a;
  background: linear-gradient(#ff4184 0%, #ff005a 100%, #ff005a);
  border: none;
  font-size: 10px; /* Adjust font size */
  color: white;
  line-height: 40px; /* Adjust line height */
  font-weight: 700;
}


    .button__text {
      position: relative;
      display: block;
      height: 114px;
      white-space: nowrap;
      opacity: 1;
    }

    .button__text--download {
      width: 150px;
      transition: opacity 0.5s ease, width 0.5s ease;

      &.is_animated {
        overflow: hidden;
        width: 0px;
        opacity: 0;
      }
    }

    .button__text--progress {
      margin-right: -35px;
      margin-left: -35px;
      width: 114px;
      font-size: 40px;
      opacity: 0;
      transition: opacity 0.5s ease;

      sub {
        font-size: .5em;
        font-weight: normal;
      }

      &.is_animated {
        opacity: 1;
      }
    }

    .button__text--complete {
      position: absolute;
      top: 0;
      left: 0;
      z-index: 999;
      border-radius: 50%;
      height: 114px;
      width: 114px;
      box-shadow: inset 0px -1px 6px 0px rgba(255, 255, 255, 0.73);
      background: #3acaff;
      transform: scale(1.5);
      transition: transform 0.5s ease;

      &.is_animated {
        transform: scale(1);
      }
    }

    .button__icon--cloud-download,
    .button__icon--checkmark {
      position: relative;
      top: 7px;
    }

    .pie-loader {
      position: absolute;
      top: 0;
      left: 0;
      z-index: -1;
      width: 160px;
      height: 160px;
      opacity: 1;
      transition: opacity 0.1s ease;

      svg {
        width: 100%;
        height: 100%;
      }

      circle {
        fill: #3acaff;
        stroke: #3acaff;
        stroke-width: 80px;
        stroke-dasharray: 0 252;
        transition: all 0.1s linear;
      }

      &.is_hidden {
        opacity: 0;
      } 
    }
.btn btn-success pull-right{
	
}
</style>

<!-- START BREADCRUMB -->
<ul class="breadcrumb">
	<li><a href="#">Home</a></li>
	<li class="active">Punjab PDS Route Optimization</li>
</ul>
<!-- END BREADCRUMB -->
<div>
	
</div>

<!-- PAGE CONTENT WRAPPER -->
<div class="page-content-wrap"
	style="background-image: url('img/1 (2).png'); background-repeat: no-repeat; background-size: cover;">

	<div class="row">
		<div class="col-md-12">

			<!-- START SIMPLE DATATABLE -->
			<div class="panel panel-default">
				<div class="panel-heading" style="text-align: center;">
					<h1 style="font-weight: bold; color: #335566;">Telangana PDS Route Optimization</h1>
					<h1 style="font-weight: bold; color: #FF6666;">Kindly optimizised the Leg1-FCI to Warehouse</h1>

				</div>
			</div>

			<div class="row" style="margin-top:150px">
				<div class="col-md-3">
					<div class="form-group">
						<div class="col-md-2"></div>
						<div class="col-md-9">  
							<div class="input-group" style="width:100%;">					
							<select class="form-control" id="type" name="type" style="border-radius:5px;font-weight:bold">
								<option value='' style="font-weight:bold;color:#000;">Select</option>
								<option value='inter' style="font-weight:bold;color:#000;">Inter District</option>
								<option value='intra' style="font-weight:bold;color:#000;">Intra District</option>
							</select>
							</div>
							<span class="help-block">Select scenario for Optimisation</span>
						</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="form-group">
						<div class="col-md-2"></div>
						<div class="col-md-9">  
							<div class="input-group" style="width:100%;">					
							<select class="form-control" id="year" name="year" style="border-radius:5px;font-weight:bold">
								<option value='' style="font-weight:bold;color:#000;">Select</option>
								<option value='2024' style="font-weight:bold;color:#000;">2024</option>
							</select>
							</div>
							<span class="help-block">Selected Year</span>
						</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="form-group">
						<div class="col-md-2"></div>
						<div class="col-md-9">
							<div class="input-group" style="width:100%;">				
							<select class="form-control" id="month" name="month" style="border-radius:5px;font-weight:bold">
								<option value='' style="font-weight:bold;color:#000;">Select</option>
							</select>
							</div>
							<span class="help-block">Selected Month</span>
						</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="form-group">
						<div class="col-md-2"></div>
						<div class="col-md-9">
							 <div class="multiselect">
								<div class="selectBox" onclick="showCheckboxes()">
								  <select class="form-control" >
									<option>Select an option</option>
								  </select>
								  <div class="overSelect"></div>
								</div>
								<div id="checkboxes"  style="z-index:999;background-color:#fff;position: absolute;width:100%;" >
									<label for="jan">
									&nbsp <input type="checkbox" id="jan" value="jan" style /> January</label>
									<label for="feb">
									&nbsp <input type="checkbox" id="feb" value="feb" /> February</label>
									<label for="march">
									&nbsp <input type="checkbox" id="march" value="march" /> March</label>
									<label for="april">
									&nbsp <input type="checkbox" id="april" value="april" /> April</label>
									<label for="may">
									&nbsp <input type="checkbox" id="may" value="may" /> May</label>
									<label for="june">
									&nbsp <input type="checkbox" id="june" value="june" /> June</label>
									<label for="july">
									&nbsp <input type="checkbox" id="july" value="july" /> July</label>
									<label for="aug">
									&nbsp <input type="checkbox" id="aug" value="aug" /> August</label>
									<label for="sept">
									&nbsp <input type="checkbox" id="sept" value="sept" /> September</label>
									<label for="oct">
									&nbsp <input type="checkbox" id="oct" value="oct" /> October</label>
									<label for="nov">
									&nbsp <input type="checkbox" id="nov" value="nov" /> November</label>
									<label for="dec">
									&nbsp <input type="checkbox" id="dec" value="dec" /> December</label>
								</div>
							  </div>
							<span class="help-block">Applicable Month</span>
						</div>
					</div>
				</div>
			</div>
			</br>
			<div class="row">
				<div class="col-md-12">
					<div class="panel-body">

						<form action="" method="POST" class="form-horizontal" enctype="multipart/form-data" id="upload_button">
								
								<div class="row">
									<div class="col-md-8">
										<input style="font-size: 18px; padding: 10px 16px;" type="button" class="btn btn-success pull-right" onclick="fetchFromDb()" value="Fetch Data from Database" />
									</div>
								</div>
							<!-- </div> -->
						</form>
						<div id="processingPopup">
							<div class="spinner"></div>
							<button type="button" style="margin-top:100px;margin-left:-80px;display:none" id="cancel-request" class="btn btn-danger" onClick="cancelRequest()">Cancel Request</button>
						</div>
						&nbsp
						<div class="row">
							<div
								style="font-size: 20px; font-weight: 700; margin-top: 0px; padding: 5px; margin-bottom: 20px;">
								<i class="fa fa-info-circle" aria-hidden="true"></i> Pre-Analysis
							</div>
							<div class="row">
								<div class="col-md-2 mb-4">
									<div class="card h-100"
										style="background-color:#56A5FF; color:white; padding:12px; font-weight: bold;">
										<div style="font-size:20px" id="total_warehouse"></div>
										<div style="font-size:14px">Total Warehouse</div>
									</div>
								</div>
								<div class="col-md-3 mb-4">
									<div class="card h-100"
										style="background-color:#3FDBBC; color:white; padding:12px; font-weight: bold;">
										<div style="font-size:20px" id="total_supply"></div>
										<div style="font-size:14px">Total Capacity (Qtl)</div>
									</div>
								</div>
								<div class="col-md-2 mb-4">
									<div class="card h-100"
										style="background-color:#FFC167; color:white; padding:12px; font-weight: bold;">
										<div style="font-size:20px" id="total_fps"></div>
										<div style="font-size:14px">Total FPS Counts</div>
									</div>
								</div>
								<div class="col-md-3 mb-4">
									<div class="card h-100"
										style="background-color:#F96981; color:white; padding:12px; font-weight: bold;">
										<div style="font-size:20px" id="total_demand"></div>
										<div style="font-size:14px">Total FPS Demands (Qtl)</div>
									</div>
								</div>
								<div class="col-md-2 mb-4">
									<div class="card h-100"
										style="background-color:#9085AE; color:white; padding:12px; font-weight: bold;">
										<div style="font-size:20px" id="total_district"></div>
										<div style="font-size:14px">Total District</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					&nbsp

				</div>
				<div class="col-md-9">
				
				
					</br></br></br>
					<center>
						<div style="width:80%"><canvas id="myChart" width="400" height="300"></canvas></div>
					</center>
				</div>
				<div class="col-md-3" id="sidebar" style="display:none; border-radius: 20px;">
					<div
						style="border: 2px solid #DC8686; padding: 15px; background-color: #DC8686; color: white; border-radius: 20px; margin-top:100px; margin-bottom: 10px;">
						<div class="card">
							<div class="row">
								<center style="margin-top:20px">
									<h2><b><span style="color: white">Progress Bar</span></b></h2>
								</center>
								<center style="margin-top:20px">
									<h2><b><span style="color: white;">File Upload Successfully</span></b></h2>
								</center>
								<center><img src="img\Analysis-icon-1.png" style="width:45%" /></center>
								<center style="margin-top:20px">
									<h2><b><span style="color: white;">Pre-Analysis</span></b></h2>
								</center>
								<center style="margin-top:20px">
									<h4><b><span style="color: white;">State-Wise &nbsp <input type="checkbox" id="statewiseCheckbox" onchange="handleStateCheckboxChange()" checked  /></b></h4>
								</center>
								<center style="margin-top: 20px; font-weight: 500; color: white;">
									<h4><b id="totalFciSupply"></b></h4>
								</center>
								<center style="margin-top:20px">
									<h4><b id="totalFciDemand"></b></h4>
								</center>
								<center style="margin-top:20px">
									<h4><b id="selectedMonth"></b></h4>
								</center>
								<center style="margin-top:20px">
									<h4><b id="result"></b></h4>
								</center>
								<div id="districtcheckbox" style="display:none">
									<center style="margin-top:20px;">
										<h4><b><span style="color: white;">District-wise Supply and Demand &nbsp <input type="checkbox" id="districtwiseCheckbox" onchange="handleDistrictCheckboxChange()" /></b></h4>
									</center>
									<center style="margin-top:20px">
										<h4><b id="resultdistrict"></b></h4>
									</center>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			&nbsp
			</br>

			<div id="generateoptinizedplanbutton" style="display:none; overflow: hidden;">
				<center><div style="font-size: 20px; font-weight: 700; margin-top: 10px; margin-bottom: 20px;color:#000">
					<i class="fa fa-info-circle" aria-hidden="true"></i> Optimization
				</div></center>
			
					<button class="upload_button_class" id="upload_button" name="submit">
						<span style="text-align: center; font-weight: bold;">Generate Optimized Plan</span>
						<a href="#" class="toggle toggle--off"  data-content="Off" onclick="toggleState(this)"></a>
					</button>
					<!--<center><img id="level5Image" src="Backend/plantuml_file.png" alt="Plan Image" style="display:none;width:35%;margin-top:40px"></center>-->
                        <div style="margin-top: 13px;margin-left: 1300px;"> 
						<div class="pen-wrapper">
							<div class="button-wrapper">
							</div>
						</div>
					</div>
				<br><br>
					<table class="table" id="optimisedtable" style="display: none; width: 100%; text-align: center;">
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
						<tbody id="table_body">
							<!-- Table body content -->
						</tbody>
					</table>
					
				
				
			</div>
			&nbsp;<br><br><br>

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

<!-- END TEMPLATE -->

<script>

	function checkServerStatus() {
		// Make an AJAX request to your Python server
		var xhr = new XMLHttpRequest();
		xhr.open("GET", pythonUrl, true);
		xhr.onload = function () {
			if (xhr.status == 200) {
				document.getElementById('pythonStatus').innerHTML = "Server is Working";
				document.getElementById('statusBlock').style.backgroundColor = "green";
				console.log("Python server is working!");
			} else {
				document.getElementById('pythonStatus').innerHTML = "Server is not Working";
				document.getElementById('statusBlock').style.backgroundColor = "red";
				alert("Disconnected with Python Server");
				console.error("Python server is not working! Status code: " + xhr.status);
			}
		};
		xhr.onerror = function () {
			console.error("Error occurred while checking server status.");
			document.getElementById('pythonStatus').innerHTML = "Server is not Working";
			document.getElementById('statusBlock').style.backgroundColor = "red";
			console.error("server is not working! Status code: " + xhr.status);
		};
		xhr.send();
	}
	//checkServerStatus();
	//setInterval(checkServerStatus, 10000);
	
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
	

	function toggleState(element) {
		if (element.classList.contains('toggle--off')) {
			element.classList.remove('toggle--off');
			element.classList.add('toggle--on');
			element.setAttribute('data-content', 'On');
		} else {
			element.classList.remove('toggle--on');
			element.classList.add('toggle--off');
			element.setAttribute('data-content', 'Off');
		}
	}
	
	function post(params, file) {

		method = "post";
		path = file;

		var form = document.createElement("form");
		form.setAttribute("method", method);
		form.setAttribute("action", path);

		for (var key in params) {
			if (params.hasOwnProperty(key)) {
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

	function edit_entry(temp_id) {
		post({ uid: temp_id }, "FPSEdit.php");
	}


	// Initial data for the chart
	var initialData = {
		labels: ['Amritsar', 'Jalandhar', 'Bathinda', 'Ludhiana', 'Fazilka'],
		datasets: [{
			label: 'Supply',
			backgroundColor: '#1640D6',
			data: [0, 0, 0, 0, 0]
		}, {
			label: 'Demand',
			backgroundColor: '#25E6A5',
			data: [0, 0, 0, 0, 0]
		}]
	};

	// Get the canvas element
	var ctx = document.getElementById('myChart').getContext('2d');

	// Create a bar chart with initial data
	var myChart = new Chart(ctx, {
		type: 'bar',
		data: initialData,
		options: {
			scales: {
				y: {
					beginAtZero: true
				}
			}
		}
	});
	
	var district_names = [];
	
	function fetchFromDb(){
		var applicableLength = 1;
		$.ajax({
			type: "POST",
			url: "api/fetchTableDataAll.php",
			data: "",
			cache: false,
			error: function(){
				alert("timeout");
			},
			timeout: 120000,
			success: function(result){
				
				try{
					if(firstStart==0){
						var resultarray = JSON.parse(result);
						var monthsString = resultarray[0]["applicable"];
						var monthsArray = monthsString.split(',');
						applicableLength = monthsArray.length;
						var dropdownMultiple = document.querySelectorAll('#checkboxes input[type="checkbox"]');
						for (const fillMonth of monthsArray) {
							dropdownMultiple.forEach(function(checkbox) {
							  checkbox.checked = false;
							});
						}
						for (const fillMonth of monthsArray) {
							dropdownMultiple.forEach(function(checkbox) {
							  if (checkbox.value===fillMonth) {
								checkbox.checked = true;
							  }
							});
						}
					}
					else{
						applicableLength = 0;
						var dropdownMultiple = document.querySelectorAll('#checkboxes input[type="checkbox"]');
						dropdownMultiple.forEach(function(checkbox) {
						  if (checkbox.checked==true) {
							applicableLength = applicableLength + 1;
						  }
						});
					}
					applicableLength = Math.max(applicableLength,1);
					document.getElementById("districtcheckbox").style.display = "none";
					document.getElementById("result").innerHTML = "";
					document.getElementById("totalFciDemand").innerHTML = "";
					document.getElementById("totalFciSupply").innerHTML = "";
					document.getElementById("districtwiseCheckbox").checked = false;
					document.getElementById("statewiseCheckbox").checked = false;
					document.getElementById("generateoptinizedplanbutton").style.display = "none";

					document.getElementById("processingPopup").style.display = "flex";
					document.getElementById("sidebar").style.display = "block";

					const formData = new FormData();
					formData.append('applicable', applicableLength);
					fetch(pythonUrl + 'extract_db_leg1', {
						method: 'POST',
						body: formData,
						timeout: 14400000
					})
						.then(response => response.json())
						.then(data => {
							const formData = new FormData();

							fetch(pythonUrl + 'getfcidata', {
								method: 'POST',
								body: formData,
								timeout: 14400000
							})
								.then(response => response.json())
								.then(data => {
									document.getElementById("total_warehouse").innerHTML = formatNumberWithCommasWithoutDecimal(data["Warehouse_No"]);
									document.getElementById("total_demand").innerHTML = formatNumberWithCommasWithoutDecimal(data["Total_Demand"]);
									document.getElementById("total_fps").innerHTML = formatNumberWithCommasWithoutDecimal(data["FPS_No"]);
									document.getElementById("total_supply").innerHTML = formatNumberWithCommasWithoutDecimal(data["Total_Supply"]);
									document.getElementById("total_district").innerHTML = formatNumberWithCommasWithoutDecimal(data["District_Count"]);
									document.getElementById("processingPopup").style.display = "none";
									
									if(firstStart==0){
										document.getElementById("statewiseCheckbox").checked = true;
										handleStateCheckboxChange();
									}
								})
								.catch(error => {
									console.error('Error:', error);
									alert("Error in Fetching Data");
									document.getElementById("processingPopup").style.display = "none";
								});


						})
						.catch(error => {
							console.error('Error:', error);
							document.getElementById("processingPopup").style.display = "none";
						});
				}
				catch (error) {
						console.log(error);
						}
					}
				});			
	}
	
	function handleDistrictCheckboxChange() {
		var checkbox = document.getElementById("districtwiseCheckbox");
		if (checkbox.checked) {
			document.getElementById("generateoptinizedplanbutton").style.display = "";
			if (district_names["District_Name_All"].length > 0) {
				var concatenatedNames = district_names["District_Name_All"].join(', ');
				document.getElementById("resultdistrict").innerHTML = "Intra district movement is infeasble- " + concatenatedNames;
				document.getElementById("resultdistrict").style.color = "#ADFF2F"; 
			} else {
				document.getElementById("resultdistrict").innerHTML = "Intra scenario in every district is feasible";
				document.getElementById("resultdistrict").style.color = "#1111BB";
			}
			// Increase font size and make text bold
			document.getElementById("resultdistrict").style.fontSize = "18px";
			document.getElementById("resultdistrict").style.fontWeight = "bold";
		} else {
			document.getElementById("resultdistrict").innerHTML = "";
			document.getElementById("generateoptinizedplanbutton").style.display = "none";
		}
	}
	
	let controller;
	
	function generateoptimizedplan() {
		const formData = new FormData();
		formData.append('month', document.getElementById("month").value);
		formData.append('year', document.getElementById("year").value);
		formData.append('type', document.getElementById("type").value);
		
		var checkboxes = document.querySelectorAll('#checkboxes input[type="checkbox"]');
		var selectedValues = [];

		checkboxes.forEach(function(checkbox) {
		  if (checkbox.checked) {
			selectedValues.push(checkbox.value);
		  }
		});
		
		controller = new AbortController();
		const signal = controller.signal;

		formData.append('applicable', selectedValues);
		document.getElementById("processingPopup").style.display = "flex";
		document.getElementById("cancel-request").style.display = "flex";
		fetchPromise = fetch(pythonUrl + 'processFile', {
			method: 'POST',
			body: formData,
			signal: signal,
			timeout: 14400000
		})
			.then(response => response.json())
			.then(data => {
				document.getElementById("optimisedtable").innerHTML = "";
				document.getElementById("optimisedtable").style.display = "";
				document.getElementById("processingPopup").style.display = "none";
				document.getElementById("cancel-request").style.display = "none";
				
				
				var thead = document.createElement("thead");
				var headerRow = document.createElement("tr");
				var headers = ["Scenario", "WH_Used", "FPS_Used", "Total_Allocation", "Total_QKM", "Average Distance"];
				headers.forEach(function(headerText) {
					var th = document.createElement("th");
					th.textContent = headerText;
					headerRow.appendChild(th);
				});
				thead.appendChild(headerRow);
				var table = document.getElementById("optimisedtable");
				table.appendChild(thead);

				var newRow = table.insertRow();

				var cell1 = newRow.insertCell(0);
				var cell2 = newRow.insertCell(1);
				var cell3 = newRow.insertCell(2);
				var cell4 = newRow.insertCell(3);
				var cell5 = newRow.insertCell(4);
				var cell6 = newRow.insertCell(5);
				

				cell1.innerHTML = data["Scenario"];
				cell2.innerHTML = data["WH_Used"];
				cell3.innerHTML = data["FPS_Used"];
				cell4.innerHTML = data["Demand"];
				cell5.innerHTML = formatNumberWithCommas(data["Total_QKM"]);
				cell6.innerHTML = formatNumberWithCommas(data["Average_Distance"]);
				
				var newRow = table.insertRow();

				var cell1 = newRow.insertCell(0);
				var cell2 = newRow.insertCell(1);
				var cell3 = newRow.insertCell(2);
				var cell4 = newRow.insertCell(3);
				var cell5 = newRow.insertCell(4);
				var cell6 = newRow.insertCell(5);

				cell1.innerHTML = data["Scenario_Baseline"];
				cell2.innerHTML = data["WH_Used_Baseline"];
				cell3.innerHTML = data["FPS_Used_Baseline"];
				cell4.innerHTML = data["Demand_Baseline"];
				cell5.innerHTML = data["Total_QKM_Baseline"];
				cell6.innerHTML = data["Average_Distance_Baseline"];
				
			table.style.width = "100%";
			table.style.padding = "20px";
			table.style.marginBottom = "50px";
			table.style.fontSize = "20px"; 
			table.style.marginLeft = "20px"; // Add margin-left
			table.style.color = "black"; // Add margin-left
			table.style.textAlign = "center";

			var tableHeaders = table.getElementsByTagName('th');
			for (var i = 0; i < tableHeaders.length; i++) {
				tableHeaders[i].style.fontSize = "20px"; // Increase font size for headers
			}
			//toggleImage(); // Call the toggleImage function after displaying the table
			var toggleButton = document.querySelector('.toggle');
			toggleButton.classList.remove('toggle--on');
			toggleButton.classList.add('toggle--off');
			toggleButton.setAttribute('data-content', 'Off');

			//toggleTableAndDownloadButton(); // Call the function to show/hide download button
		})
		.catch(error => {
			alert("Error in Processing");
			var toggleButton = document.querySelector('.toggle');
			toggleButton.classList.remove('toggle--on');
			toggleButton.classList.add('toggle--off');
			toggleButton.setAttribute('data-content', 'Off');

			console.error('Error:', error);
		})
		.finally(() => {
			document.getElementById("processingPopup").style.display = "none";
			document.getElementById("cancel-request").style.display = "none";
		});
	}
	
	function cancelRequest() {
		if (controller) {
			controller.abort(); // Abort the fetch request using the AbortController
			console.log('Request cancelled.');
			const formData = new FormData();
			fetch(pythonUrl + 'processCancel', {
				method: 'POST',
				body: formData
			})
				.then(response => response.json())
				.then(data => {
				});
		} else {
			console.log('No request to cancel.');
		}
	}


/*function toggleImage() {
    var img = document.getElementById('level5Image');
    img.style.display = (img.style.display === 'none' || img.style.display === '') ? 'block' : 'none';

    // Log the current display values for image and download button
    var downloadButton = document.getElementById('downloadButtonText');
    if (downloadButton) {
        downloadButton.style.display = (img.style.display === 'block') ? 'inline-block' : 'none';
    }
}*/


function DownloadButton() {
    const downloadButton = document.getElementById('download_button');
    const table = document.getElementById('optimisedtable');

    if (table.innerHTML.trim() !== '') {
        downloadButton.style.display = '';
    } else {
        downloadButton.style.display = 'none';
    }
}

let workbook = null;

function readLocalExcelFile() {
    const filePath = 'Backend/Backend/SCO_Tagging_Sheet.xlsx';
    workbook = XLSX.readFile(filePath);
    DownloadButton(); // Call DownloadButton after reading the file
}

function downloadExcelFile() {
    if (!workbook) {
        console.error('Workbook not initialized.');
        return;
    }

    const wbout = XLSX.write(workbook, { bookType: 'xlsx', type: 'binary' });

    function s2ab(s) {
        const buf = new ArrayBuffer(s.length);
        const view = new Uint8Array(buf);
        for (let i = 0; i < s.length; i++) {
            view[i] = s.charCodeAt(i) & 0xFF;
        }
        return buf;
    }

    const blob = new Blob([s2ab(wbout)], { type: 'application/octet-stream' });
    const url = window.URL.createObjectURL(blob);

    const a = document.createElement('a');
    a.href = url;
    a.download = 'Template_SCO_IntraState_Punjab.xlsx';
    a.click();
    window.URL.revokeObjectURL(url);
}

function downloadFile(fileType) {
    let filePath = '';
    let fileName = '';

    if (fileType === 'xlsx') {
        filePath = 'template/Template_SCO_IntraState_Punjab.xlsx';
        fileName = 'Template_SCO_IntraState_Punjab.xlsx';
    } else if (fileType === 'pdf') {
        filePath = 'template/Template_SCO_IntraState_Punjab.pdf';
        fileName = 'Template_SCO_IntraState_Punjab.pdf';
    }

    fetch(filePath)
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = fileName;
            a.click();
            window.URL.revokeObjectURL(url);
        })
        .catch(error => {
            console.error('Error downloading file:', error);
        });
}

function toggleState(element) {
	if (element.classList.contains('toggle--off')) {
		element.classList.remove('toggle--off');
		element.classList.add('toggle--on');
		element.setAttribute('data-content', 'On');
		generateoptimizedplan();
	} else {
		element.classList.remove('toggle--on');
		element.classList.add('toggle--off');
		element.setAttribute('data-content', 'Off');
		var table = document.getElementById("optimisedtable");
		table.style.display = "none";
	}
}

function capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

function handleStateCheckboxChange() {
	var checkbox = document.getElementById("statewiseCheckbox");
	document.getElementById("districtwiseCheckbox").checked = false;
	
	if (checkbox.checked) {
		const formData = new FormData();
		document.getElementById("processingPopup").style.display = "flex";
		fetch(pythonUrl + 'getGraphData', {
			method: 'POST',
			body: formData
		})
			.then(response => response.json())
			.then(data => {
				district_names = data.District_Name;
				var totalCapacity = Object.values(data.District_Capacity).reduce((acc, capacity) => acc + capacity, 0);
				var totalDemand = Object.values(data.District_Demand).reduce((acc, demand) => acc + demand, 0);
				var month = document.getElementById("month").value;

				document.getElementById("totalFciDemand").innerHTML = "<span style='color: white; font-size: 14px;'>" + "Total Demand: " + totalDemand + " (Qtl)</span>";
				document.getElementById("totalFciSupply").innerHTML = "<span style='color: white; font-size: 14px;'>" + "Total Supply: " + totalCapacity + " (Qtl)</span>";
				document.getElementById("selectedMonth").innerHTML = "<span style='color: white; font-size: 14px;'>" + "Selected Month: " + capitalizeFirstLetter(month) + "</span>";


				districtdata = data.District_Name;

				if (totalCapacity > 0 && totalDemand > 0) {
					if (totalCapacity >= totalDemand) {
						// document.getElementById("result").innerHTML = "Optimization can be done.";
						document.getElementById("result").innerHTML = "<span style='font-weight: bold; font-size: 20px; color: green;'>Optimization can be done.</span>";

						document.getElementById("districtcheckbox").style.display = "block";
					}
					else {
						// document.getElementById("result").innerHTML = "Optimiazation cannot be done infeasible solution";
						document.getElementById("result").innerHTML = "<span style='font-weight: bold; font-size: 20px; color: red;'>Optimiazation cannot be done infeasible solution.</span>";

						document.getElementById("districtcheckbox").style.display = "none";
						document.getElementById("generateoptinizedplanbutton").style.display = "none";
					}

					// Get district names from the JSON data
					var districtNamesCapacity = Object.keys(data.District_Capacity);
					var districtNamesDemand = Object.keys(data.District_Demand);
					
					const unionSet = new Set([...districtNamesCapacity, ...districtNamesDemand]);
					const unionArray = Array.from(unionSet);

					// Get capacities and demands for each district
					var capacities = unionArray.map(district => data.District_Capacity[district]);
					var demands = unionArray.map(district => data.District_Demand[district]);

					// Generate newData object
					var newData = {
						labels: unionArray,
						datasets: [
							{
								label: 'FRice Demand',
								backgroundColor: '#5383FF',
								data: demands
							},
							{
								label: 'Wheat Supply',
								backgroundColor: '#9085AE',
								data: capacities
							}
						]
					};

					// Update the chart with new data
					myChart.data = newData;
					myChart.update();
					document.getElementById("processingPopup").style.display = "none";
					if(firstStart==0){
						document.getElementById("districtwiseCheckbox").checked = true;
						handleDistrictCheckboxChange();
						firstStart = 1;
					}
				}
				else {
					document.getElementById("result").innerHTML = "Optimization cannot be provided.";
					document.getElementById("result").style.color = "red";
					document.getElementById("districtcheckbox").style.display = "none";
					document.getElementById("processingPopup").style.display = "none";
					document.getElementById("generateoptinizedplanbutton").style.display = "none";
				}

			})
			.catch(error => {
				console.error('Error:', error);
				alert("Error in Fetching Data");
				document.getElementById("processingPopup").style.display = "none";
			});

	} else {
		document.getElementById("result").innerHTML = "";
		document.getElementById("totalFciDemand").innerHTML = "";
		document.getElementById("totalFciSupply").innerHTML = "";
		document.getElementById("districtwiseCheckbox").checked = false;
		document.getElementById("districtcheckbox").style.display = "none";
		document.getElementById("processingPopup").style.display = "none";
		document.getElementById("generateoptinizedplanbutton").style.display = "none";
	}
}

var currentDate = new Date();
var currentMonth = currentDate.getMonth();
var currentYear = currentDate.getFullYear();
var monthNames = ['jan', 'feb', 'march', 'april', 'may', 'june', 'july', 'aug', 'sept', 'oct', 'nov', 'dec'];
var currentMonthValue = monthNames[currentMonth];


document.getElementById("processingPopup").style.display = "block";
$.ajax({
	type: "POST",
	url: "api/fetchTableDataAll.php",
	data: "",
	cache: false,
	error: function(){
		alert("timeout");
	},
	timeout: 120000,
	success: function(result){		
	try{
		console.log(result);
		var dropdown = document.getElementById('month');
		var resultarray = JSON.parse(result);
		var firstRow = 0;
		resultarray.forEach(item => {
			var option = document.createElement('option');
			option.value = item.month;
			option.textContent = item.month;
			dropdown.appendChild(option);
			if(firstRow==0){
				dropdown.options[1].selected = true;
				 item.applicable.split(',').forEach(applicableMonth => {
					var checkboxId = applicableMonth.toLowerCase(); // Assuming checkbox IDs are in lowercase
					var checkbox = document.getElementById(checkboxId);
					if (checkbox) {
						checkbox.checked = true;
					}
				});
				firstRow = 1;
			}
		});
		
		document.getElementById("processingPopup").style.display = "none";
		
	}
	catch (error) {
			console.log(error);
			document.getElementById("processingPopup").style.display = "none";
		}
	}
});	

document.getElementById('month').addEventListener('change', function() {
    var selectedMonth = this.value; // Get the selected month value
	var datastring = "month=" + selectedMonth;
	$.ajax({
	type: "POST",
	url: "api/fetchTableDataAllMonth.php",
	data: datastring,
	cache: false,
	error: function(){
		alert("timeout");
	},
	timeout: 120000,
	success: function(result){		
	try{
		console.log(result);
		var resultarray = JSON.parse(result);
		resultarray.forEach(item => {
			var container = document.getElementById('checkboxes');
			var checkboxes = container.querySelectorAll('input[type="checkbox"]');
			checkboxes.forEach(function(checkbox) {
				checkbox.checked = false;
			});
			 item.applicable.split(',').forEach(applicableMonth => {
				var checkboxId = applicableMonth.toLowerCase(); // Assuming checkbox IDs are in lowercase
				var checkbox = document.getElementById(checkboxId);
				if (checkbox) {
					checkbox.checked = true;
				}
			});
		});
		document.getElementById("processingPopup").style.display = "none";
		
	}
	catch (error) {
			console.log(error);
			document.getElementById("processingPopup").style.display = "none";
		}
	}
});	

});

var dropdown = document.getElementById('year');
for (var i = 0; i < dropdown.options.length; i++) {
    if (dropdown.options[i].value == currentYear) {
        dropdown.options[i].selected = true;
        break;
    }
}

var dropdown = document.getElementById('type');
var currentType = "inter"
for (var i = 0; i < dropdown.options.length; i++) {
    if (dropdown.options[i].value === currentType) {
        dropdown.options[i].selected = true;
        break;
    }
}

var expanded = false;

function showCheckboxes() {
  var checkboxes = document.getElementById("checkboxes");
  if (!expanded) {
    checkboxes.style.display = "block";
    expanded = true;
  } else {
    checkboxes.style.display = "none";
    expanded = false;
  }
}
var firstStart = 0;
//fetchFromDb();

</script>
</body>

</html>