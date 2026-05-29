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

    </style>
<script>
	function setSelectedValue(obj_value,valueToSet) {
		var obj = document.getElementById(obj_value);
		for (var i = 0; i < obj.options.length; i++) {
			if (obj.options[i].value== valueToSet) {
				obj.options[i].selected = true;
				return;
			}
		}
	}

	function setSelectedValue1(obj_value,valueToSet) {
		valueToSet = valueToSet.substring(0, valueToSet.length - 1);
		var obj = document.getElementById(obj_value);
		for (var i = 0; i < obj.options.length; i++) {
			if (obj.options[i].text== valueToSet) {
				obj.options[i].selected = true;
				return;
			}
		}
	}
</script>
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
                                    <h3 class="panel-title"><b id="mainheading_big"></b></h3>
                                </div>
                            </div>
							<div class="row">
								<div class="col-md-6 mb-4">
									<div class="card h-100"
										style="background-color:#56A5FF; color:white; padding:20px; font-weight: bold;">
										<div style="font-size:25px" id="totalids"></div>
										<div style="font-size:15px">TOTAL TAGS</div>
									</div>
								</div>
								<div class="col-md-6 mb-4">
									<div class="card h-100"
										style="background-color:#FFC167; color:white; padding:20px; font-weight: bold;">
										<div style="font-size:25px" id="totalidsrequested"></div>
										<div style="font-size:15px">TOTAL TAGS CHANGE REQUESTED</div>
									</div>
								</div>
							</div>
							</br></br></br>
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
							</br></br></br>
                            <!-- END SIMPLE DATATABLE -->
							<button id="downloadCSV" class="btn btn-warning pull-right" style="margin-bottom: 10px;" type="button">Download CSV</button>
							<button id="downloadXLSX" class="btn btn-success pull-right" style="margin-bottom: 10px;" type="button">Download XLSX</button>
							<button class='btn btn-primary pull-right' onClick='sendData()' type='button'>Save</button>
							
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
										<th style="font-size:16px">commodity</th>
										<th style="font-size:16px">quantity</th>
										<th style="font-size:16px">Distance</th>
										<th style="font-size:16px">Approve/Not Approve</th>
										<th style="font-size:16px">Reason for not Approve</th>
										<th style="font-size:16px">Suggest Warehouse</th>
										<th style="font-size:16px">Suggested Warehouse Distance</th>
									</tr>
                                 </thead>
								<tbody id="table_body">
								
								</tbody>
								
                               </table>
								</div>	
									
                                    <div id="popup" class="popup" style="display:none">
										<a class="close" onclick="hidePopup()" style="font-size:25px">×</a>
										</br></br>
										<div class="col-md-6">
											<div class="form-group">
                                                <label class="col-md-3 control-label">Username*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="username" name="username" required />
                                                    </div>
                                                    <span class="help-block">Username</span>
                                                </div>
                                            </div>
											 <input type="hidden" class="form-control" id="deleteid" name="deleteid"  />
                                        </div>
                                        <div class="col-md-6">
											<div class="form-group">
                                                <label class="col-md-3 control-label">Password*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="password" class="form-control" id="password" name="password" required />
                                                    </div>
                                                    <span class="help-block">Password</span>
                                                </div>
                                            </div>
                                        </div>
										<center><button class="btn btn-primary" type="button" onClick="VerifyAndDelete()">Verify</button></center>
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
		
        <script type="text/javascript" src="js/plugins.js"></script>
        <script type="text/javascript" src="js/actions.js"></script>
        <!-- END PAGE PLUGINS -->

        <!-- START TEMPLATE -->
        
        <!-- END TEMPLATE -->
		<?php  require('DistrictAutocomplete.php'); ?>

		<script>
		
		var modifiedData = {};
		var modifiedIdData = {};
		var modifiedReasonData = {};
		var modifiedDistanceData = {};
		
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
			var district = document.getElementById("district").value;
			const csvResponse = await fetch('api/DownloadOptimalData.php?format=csv&month=' + month + "&district=" + district);
			const csvBlob = await csvResponse.blob();
			downloadFile(csvBlob, 'Pb_Warehouse_' + getDateString() + '.csv');
		} catch (error) {
			console.error('Error downloading CSV file:', error);
		}
	});
	
	function getDateString(){
			var currentDate = new Date();
			var year = currentDate.getFullYear();
			var month = currentDate.getMonth() + 1; // Month is zero-based, so we add 1
			var day = currentDate.getDate();
			var str = year + "-" + month + "-" + day;
			return str;
		}

	// Event listener for downloading XLSX
	document.getElementById('downloadXLSX').addEventListener('click', async function() {
		try {
			var month = document.getElementById("month").value;
			var district = document.getElementById("district").value;
			const excelResponse = await fetch('api/DownloadOptimalData.php?format=xlsx&month=' + month + "&district=" + district);
			const excelBlob = await excelResponse.blob();
			downloadFile(excelBlob, 'Pb_Warehouse_' + getDateString() + '.xlsx');
		} catch (error) {
			console.error('Error downloading XLSX file:', error);
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


		function edit_entry(temp_id){
			post({uid: temp_id} ,"FPSEdit.php");
		}
		
		function approvalFunction(selectedId){
			newvalue = document.getElementById(selectedId).value;
			if(newvalue=="yes"){
				modifiedData[selectedId] = "yes";
			}
			else if(newvalue=="no"){
				modifiedData[selectedId] = "no";
			}
			else{
				if(modifiedData.hasOwnProperty(selectedId)){
					delete modifiedData[selectedId];
				}
			}
		}
		
		function sendData(){
			for (var key in modifiedData) {
				if (modifiedData.hasOwnProperty(key)) {
					var value = modifiedData[key];
					if(value!="yes" && value!=""){
						if(!modifiedReasonData.hasOwnProperty(key + "_idreason")){
							alert("New Id " + String(value) + " Reason needs to be selected");
							return;
						}
						if(!modifiedDistanceData.hasOwnProperty(key + "_iddistance")){
							alert("New Id " + String(value) + " distance needs to be filled");
							return;
						}
					}
				}
			}
			const mergedDict = Object.assign({}, modifiedData, modifiedIdData, modifiedDistanceData, modifiedReasonData);
			post(mergedDict ,"api/SaveData.php");
			
		}
		
		function rolloutPlan(){
			post({} ,"api/RollOutPlan.php");
			
		}
		
		function enableDisable(selectedId){
			newvalue = document.getElementById(selectedId + "_bool").value;
			if(newvalue=="yes"){
				modifiedData[selectedId] = "yes";
				document.getElementById(selectedId).disabled = true;
				document.getElementById(selectedId + "_idreason").disabled = true;
				document.getElementById(selectedId + "_iddistance").value = '';
				document.getElementById(selectedId + "_iddistance").disabled = true;
			}
			else if(newvalue=="no"){
				modifiedData[selectedId] = "no";
				document.getElementById(selectedId).value = '';
				document.getElementById(selectedId).disabled = false;
				document.getElementById(selectedId + "_idreason").value = '';
				document.getElementById(selectedId + "_idreason").disabled = false;
				document.getElementById(selectedId + "_iddistance").value = '';
				document.getElementById(selectedId + "_iddistance").disabled = false;
			}
			else{
				modifiedData[selectedId] = "";
				document.getElementById(selectedId).value = '';
				document.getElementById(selectedId).disabled = false;
				document.getElementById(selectedId + "_idreason").value = '';
				document.getElementById(selectedId + "_idreason").disabled = false;
				document.getElementById(selectedId + "_iddistance").value = '';
				document.getElementById(selectedId + "_iddistance").disabled = false;
			}
		}
		
		function handleNewIdChange(selectedId){
			newvalue = document.getElementById(selectedId).value;
			modifiedIdData[selectedId] = newvalue;
			if(newvalue==''){
				delete modifiedIdData[selectedId];
			}
		}
		
		function handleReasonChange(selectedId){
			newvalue = document.getElementById(selectedId).value;
			modifiedReasonData[selectedId] = newvalue;
			if(newvalue==''){
				delete modifiedReasonData[selectedId];
			}
		}
		
		function handleDistanceChange(selectedId){
			newvalue = document.getElementById(selectedId).value;
			modifiedDistanceData[selectedId] = newvalue;
			if(newvalue==''){
				delete modifiedDistanceData[selectedId];
			}
		}
		
		var uniqueid_array = [];
		
		function acceptAll(){
			for (let i = 0; i < uniqueid_array.length; i++) {
				setSelectedValue(uniqueid_array[i],'yes');
				approvalFunction(uniqueid_array[i]);
			}
		}
		
		function fetchDataFromServerDistrict(){
			document.getElementById("approved").selectedIndex = 0;
			fetchDataFromServer();
		}
		
		function fetchDataFromServerApprove(){
			document.getElementById("reviewed").selectedIndex = 0;
			fetchDataFromServer();
		}
		
		function fetchDataFromServer(){
			//var approved = document.getElementById("approved").value;
			var district = document.getElementById("district").value;
			var month = document.getElementById("month").value;
			
			if(district=="" || district=="all"){
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
					url: "api/FetchDbData.php",
					data: dataString,
					cache: false,
					error: function(){
						alert("timeout");
						$("#filter_button").attr("disabled",false);
					},
					timeout: 59000,
					success: function(result){
						
						$('#table_body').empty();
						try{
							var resultarray = JSON.parse(result);
							var warehousearray = resultarray["warehouse"];
							
							var warehousepart = "";
							for(var ids in warehousearray){
								var warehouse_id = warehousearray[ids]["id"];
								warehousepart = warehousepart + "<option value=" + warehouse_id + ">" + warehouse_id + "</option>";
							}
							
							var obj = resultarray["data"];
							for (var datafield in obj) 
							{
								var uniqueid = obj[datafield]["from_id"] + "_" + obj[datafield]["to_id"];
								var uniqueid_idchange = uniqueid + "_idchange";
								var uniqueid_idreason = uniqueid + "_idreason";
								var uniqueid_iddistance = uniqueid + "_iddistance";
								var uniqueid_bool = uniqueid + "_bool";
								//uniqueid_bool_array.push(uniqueid_bool);
								var approved = obj[datafield]["approve"];
								var newidadmin = obj[datafield]["new_id_admin"];
								var newname = obj[datafield]["new_name"]
								var reason = obj[datafield]["reason"];
								var new_distance = obj[datafield]["new_distance"];
								if(reason==null || reason=="null"){
									reason = "";
								}
								var subpart1 = "<tr><td>" +  obj[datafield]["scenario"] +  "</td><td>"  + obj[datafield]["from"] +  "</td><td>"  + obj[datafield]["from_state"] +  "</td><td>"  + obj[datafield]["from_id"] +  "</td><td>"  + obj[datafield]["from_name"] +  "</td><td>"  + obj[datafield]["from_district"] +  "</td><td>"  + obj[datafield]["from_lat"] +  "</td><td>"  + obj[datafield]["from_long"] +  "</td><td>"  + obj[datafield]["to"] +  "</td><td>"  + obj[datafield]["to_state"] +  "</td><td>"  + obj[datafield]["to_id"] +  "</td><td>"  + obj[datafield]["to_name"] +  "</td><td>"  + obj[datafield]["to_district"] +  "</td><td>"  + obj[datafield]["to_lat"] +  "</td><td>"  + obj[datafield]["to_long"] +  "</td><td>"  + obj[datafield]["commodity"] +  "</td><td>"  + obj[datafield]["quantity"] +  "</td><td>"  + obj[datafield]["distance"] + "</td>";
								
								if(approved=="yes"){
									var reviewpart = "<td><button class='btn btn-info'>Already Reviewed</button></td><td>" + String(reason) + "</td>";
								}
								else if(approved=="no"){
									var reviewpart = "<td><button class='btn btn-danger'>Not Approved</button></td><td>" + String(reason) + "</td>";
								}
								else{
									var reviewpart = "<td><select class='form-control' onchange='enableDisable(\"" + uniqueid + "\")' id='" + uniqueid_bool + "' name='" + uniqueid_bool + "' required><option value=''>Select</option><option value='yes'>Approve</option><option value='no'>Change ID</option></select></td><td><select class='form-control' onchange='handleReasonChange(\"" + uniqueid_idreason + "\")' id='" + uniqueid_idreason + "' name='" + uniqueid_idreason + "' disabled><option value=''>Select</option><option value='Road not accessible'>Road not accessible</option><option value='Road repair going on'>Road repair going on</option><option value='Pertaining to Distance'>Pertaining to Distance</option></select></td>";
								}
								
								if(new_distance==null || new_distance==""){
									var newdistance = "<td><input type='text' onchange='handleDistanceChange(\"" + uniqueid_iddistance + "\")' id='" + uniqueid_iddistance + "' name='" + uniqueid_iddistance + "' disabled required /></td>";
								}
								else{
									var newdistance = "<td>" + new_distance + "</td>"
								}
								
								if(newidadmin==null || newidadmin==""){
									subpart1 = subpart1 + reviewpart + "<td><select class='form-control' onchange='handleNewIdChange(\"" + uniqueid + "\")' id='" + uniqueid + "' name='" + uniqueid + "' disabled required><option value=''>Select Id</option>" + warehousepart + "</select></td>" + newdistance + "</tr>";
								}
								else{
									subpart1 = subpart1 + reviewpart + "<td>" + newidadmin + "</td>" + newdistance + "</tr>";
								}
								
								$('#table_body').append(subpart1);
							}
							fetchCardDataFromServer();
						}
						catch (error) {
							console.log(error);
						}
					}
				});
			}
		}
		
		function fetchCardDataFromServer(){
			var month = document.getElementById("month").value;
			
			var dataString = 'month='+ month;
			if(dataString=='')
			{
				alert("Please Fill All Fields");
			}
			else
			{
				$("#filter_button").attr("disabled",true);
				$.ajax({
					type: "POST",
					url: "api/getCardData.php",
					data: dataString,
					cache: false,
					error: function(){
						alert("timeout");
						$("#filter_button").attr("disabled",false);
					},
					timeout: 59000,
					success: function(result){
						try{
							var resultarray = JSON.parse(result);
							var totalids = resultarray['totalids'];
							var totalidsrequested = resultarray['totalidsrequested'];
							document.getElementById('totalids').innerHTML  = totalids;
							document.getElementById('totalidsrequested').innerHTML  = totalidsrequested;
						}
						catch (error) {
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
    </body>
</html>
