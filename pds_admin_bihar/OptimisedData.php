<?php
require('util/Connection.php');
require('util/SessionCheck.php');
require('Header.php');

$query = "SELECT * FROM optimised_table ORDER BY last_updated DESC LIMIT 1";
$result = mysqli_query($con,$query);
$id = "";
while($row = mysqli_fetch_array($result))
{
	$id= $row["id"];
}

$query = "SELECT rolled_out FROM optimised_table WHERE id='$id'";
$result = mysqli_query($con,$query);
$rolled_out = "0";
while($row = mysqli_fetch_array($result))
{
	$rolled_out= $row["rolled_out"];
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
                    <li class="active">PDS Optimized Plan</li>
                </ul>
                <!-- END BREADCRUMB -->

                <div class="page-content-wrap" style="background-color:#fff">

                    <div class="row">
                        <div class="col-md-12">

                            <!-- START SIMPLE DATATABLE -->
                            <div class="panel panel-default">
								<div class="panel-heading">
                                    <h3 class="panel-title"><b id="mainheading_big"></b></h3>
									</br></br>
									<?php if($rolled_out=='1'){
											echo "<center><h3 style='color:green'>Plan already rolled out to districts</h3></center>";
										}
										else{
											echo "<center><h3 style='color:red'>Plan yet to be rolled out to districts</h3></center>";
										}
									?>
                                </div>
                            </div>
							<div class="row">
								<div class="col-md-3 mb-4">
									<div class="card h-100"
										style="background-color:#56A5FF; color:white; padding:20px; font-weight: bold;">
										<div style="font-size:25px" id="totalids"></div>
										<div style="font-size:15px">TOTAL TAGS</div>
									</div>
								</div>
								<div class="col-md-3 mb-4">
									<div class="card h-100"
										style="background-color:#3FDBBC; color:white; padding:20px; font-weight: bold;">
										<div style="font-size:25px" id="totalidsreviewed"></div>
										<div style="font-size:15px">TOTAL REVIEWED BY DISTRCTS</div>
									</div>
								</div>
								<div class="col-md-3 mb-4">
									<div class="card h-100"
										style="background-color:#FFC167; color:white; padding:20px; font-weight: bold;">
										<div style="font-size:25px" id="totalidsrequested"></div>
										<div style="font-size:15px">TOTAL TAGS CHANGE REQUESTED</div>
									</div>
								</div>
								<div class="col-md-3 mb-4">
									<div class="card h-100"
										style="background-color:#F96981; color:white; padding:20px; font-weight: bold;">
										<div style="font-size:25px" id="totalidsapproved"></div>
										<div style="font-size:15px">TOTAL TAGS APPROVED</div>
									</div>
								</div>
							</div>
							</br></br></br>
							<div class="row">
								<div class="col-md-3">
									<div class="form-group">
										<div class="col-md-12">  
											<div class="input-group">
											<span class="input-group-addon"><span class="fa fa-certificate"></span></span>						
											<select class="form-control" id="district" name="district" onchange="fetchDataFromServer()">
												<option value=''>Select</option>
												<option value='all'>All</option>
											</select>
											</div>
											<span class="help-block">Districts (All option will work only for download)</span>
										</div>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-group">
										<div class="col-md-12">  
											<div class="input-group">
											<span class="input-group-addon"><span class="fa fa-certificate"></span></span>						
											<select class="form-control" onchange="fetchDataFromServerDistrict()" id="reviewed" name="reviewed">
												<option value=''>Select</option>
												<option value='reviewed'>Reviewed</option>
												<option value='notreviewed'>Not Reviewed</option>
											</select>
											</div>
											<span class="help-block">Reviewed by District</span>
										</div>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-group">
										<div class="col-md-12">  
											<div class="input-group">
											<span class="input-group-addon"><span class="fa fa-certificate"></span></span>						
											<select class="form-control" onchange="fetchDataFromServerApprove()" id="approved" name="approved">
												<option value=''>Select</option>
												<option value='approved'>Admin Approved</option>
												<option value='notapproved'>Admin not Approved</option>
											</select>
											</div>
											<span class="help-block">Approved</span>
										</div>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-group">
										<div class="col-md-12">  
											<div class="input-group">
											<span class="input-group-addon"><span class="fa fa-certificate"></span></span>						
											<select class="form-control" id="month" name="month" onchange="fetchDataFromServer()">
												<option value=''>Select</option>
											</select>
											</div>
										<span class="help-block">Month</span>
										</div>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-md-3">
								</div>
								<div class="col-md-3">
									<div class="form-group">
										<div class="col-md-12">  
											<div class="input-group">
											<span class="input-group-addon"><span class="fa fa-certificate"></span></span>						
											<select class="form-control" id="from_id" name="from_id" onchange="fetchDataFromServerId()">
												<option value=''>Select</option>
											</select>
											</div>
											<span class="help-block">Select Warehouse</span>
										</div>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-group">
										<div class="col-md-12">  
											<div class="input-group">
											<span class="input-group-addon"><span class="fa fa-certificate"></span></span>						
											<select class="form-control" id="to_id" name="to_id" onchange="fetchDataFromServerId()">
												<option value=''>Select</option>
											</select>
											</div>
											<span class="help-block">Select To</span>
										</div>
									</div>
								</div>
								<div class="col-md-3">
								</div>
								
							</div>
							</br></br></br>
                            <!-- END SIMPLE DATATABLE -->
							
								<button class='btn btn-danger pull-right' onClick='rolloutPlan()' type='button' style='margin-left:10px;'>Send to District Verification</button>
								<button class='btn btn-info pull-right' onClick='acceptAll()' type='button' style='margin-left:10px;'>Accept All</button>
								<button class='btn btn-primary pull-right' onClick='sendData()' type='button'>Save</button>
								<button id="pushToSCMLeg2Btn" class='btn btn-success pull-right' onClick='pushToSCMLeg2()' type='button' style='margin-left:10px; background-color:#1a7a1a; border-color:#145c14;'>&#x2191; Push to SCM (Leg2)</button>
								</br></br>
								<button id="downloadCSV" class="btn btn-warning pull-right" style="margin-left: 10px;" type="button">Download CSV</button>
								<button id="downloadXLSX" class="btn btn-success pull-right" style="margin-left: 10px;" type="button">Download XLSX</button>
								<button id="downloadPDF" class="btn btn-danger pull-right" style="margin-left: 10px;" type="button">Download PDF</button>
								</br></br>
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
										<th style="font-size:16px">Quantity(Qtl)</th>
										<th style="font-size:16px">Distance(Km)</th>
										<th style="font-size:16px">District Suggested Warehouse</th>
										<th style="font-size:16px">District Reason for not Approve</th>
										<th style="font-size:16px">District Suggested Warehouse Distance</th>
										<th style="font-size:16px">District Reviewed</th>
										<th style="font-size:16px">Approve/Not Approve</th>
										<th style="font-size:16px">Reason for not Approve</th>
										<th style="font-size:16px">Suggest Warehouse</th>
										<th style="font-size:16px">Suggested Warehouse Distance</th>
										<th style="font-size:16px">Action</th>
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
		
		<?php  require('DistrictAutocomplete.php'); ?>
        
        <!-- END TEMPLATE -->

		<script>
		
		var modifiedData = {};
		var modifiedIdData = {};
		var modifiedReasonData = {};
		var modifiedDistanceData = {};
		var modifiedApproveData = {};
		
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
			else if(newvalue=="same"){
				modifiedData[selectedId] = "same";
				document.getElementById(selectedId).value = '';
				document.getElementById(selectedId).disabled = true;
				document.getElementById(selectedId + "_idreason").disabled = true;
				document.getElementById(selectedId + "_iddistance").value = '';
				document.getElementById(selectedId + "_iddistance").disabled = true;
			}
			else{
				modifiedData[selectedId] = "";
				document.getElementById(selectedId).value = '';
				document.getElementById(selectedId).disabled = true;
				document.getElementById(selectedId + "_idreason").disabled = true;
				document.getElementById(selectedId + "_iddistance").value = '';
				document.getElementById(selectedId + "_iddistance").disabled = true;
			}
		}
		
		function enableDisableApprove(selectedId){
			newvalue = document.getElementById(selectedId).value;
			
			if(newvalue=="yes"){
				modifiedApproveData[selectedId] = "yes";
			}
			else if(newvalue=="no"){
				modifiedApproveData[selectedId] = "no";
			}
			else{
				modifiedApproveData[selectedId] = "";
			}
		}

		function edit_entry(temp_id){
			post({uid: temp_id} ,"FPSEdit.php");
		}
		
		document.getElementById('downloadCSV').addEventListener('click', async function() {
			try {
				var month = document.getElementById("month").value;
				var district = document.getElementById("district").value;
				const csvResponse = await fetch('api/DownloadOptimalData.php?format=csv&month=' + month + "&district=" + district);
				const csvBlob = await csvResponse.blob();
				downloadFile(csvBlob, 'Optimise_Plan_Leg2_' + getDateString() + '.csv');
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
				downloadFile(csvBlob, 'Optimise_Plan_Leg2_' + getDateString() + '.pdf');
			} catch (error) {
				console.error('Error downloading PDF file:', error);
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
				downloadFile(excelBlob, 'Optimise_Plan_Leg2_' + getDateString() + '.xlsx');
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
					if(value!="yes" && value!=""  && value!="same"){
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
			const mergedDict = Object.assign({}, modifiedData, modifiedIdData, modifiedDistanceData, modifiedReasonData, modifiedApproveData);
			post(mergedDict ,"api/SaveData.php");
		}
		
		function rolloutPlan(){
			post({} ,"api/RollOutPlan.php");
		}
		
		function handleNewIdChange(selectedId){
			newvalue = document.getElementById(selectedId).value;
			modifiedIdData[selectedId] = newvalue;
			if(newvalue==''){
				delete modifiedIdData[selectedId];
			}
		}
		
		var uniqueid_array = [];
		
		function acceptAll(){
			for (let i = 0; i < uniqueid_array.length; i++) {
				setSelectedValue(uniqueid_array[i],'yes');
				approvalFunction(uniqueid_array[i]);
			}
			console.log("shallu");
		}
		
		function fetchDataFromServerDistrict(){
			document.getElementById("approved").selectedIndex = 0;
			fetchDataFromServer();
		}
		
		function fetchDataFromServerApprove(){
			document.getElementById("reviewed").selectedIndex = 0;
			fetchDataFromServer();
		}
		
		function fetchDataFromServerId(){
			var approved = document.getElementById("approved").value;
			var reviewed = document.getElementById("reviewed").value;
			var district = document.getElementById("district").value;
			var month = document.getElementById("month").value;
			var from_id = document.getElementById("from_id").value;
			var to_id = document.getElementById("to_id").value;
			
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
			var dataString = 'approved='+ approved + '&reviewed='+ reviewed + '&month='+ month + '&district=' + district + '&fromid=' + from_id + '&toid=' + to_id;
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
						uniqueid_array = [];
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
								var uniqueid = obj[datafield]["from_id"] + "_" + obj[datafield]["to_id"] + "_" + obj[datafield]["commodity"];
								var uniqueid_idchange = uniqueid + "_idchange";
								var uniqueid_idreason = uniqueid + "_idreason";
								var uniqueid_iddistance = uniqueid + "_iddistance";
								var uniqueid_bool = uniqueid + "_bool";
								var uniqueid_idapprove = uniqueid + "_approve";
								
								
								var approve_admin = obj[datafield]["approve_admin"] !== null ? obj[datafield]["approve_admin"] : "";
								var approve_district = obj[datafield]["approve_district"] !== null ? obj[datafield]["approve_district"] : "";
								var newid_admin = obj[datafield]["new_id_admin"] !== null ? obj[datafield]["new_id_admin"] : "";
								var newid_district = obj[datafield]["new_id_district"] !== null ? obj[datafield]["new_id_district"] : "";
								var newname_admin = obj[datafield]["new_name_admin"] !== null ? obj[datafield]["new_name_admin"] : "";
								var newname_district = obj[datafield]["new_name_district"] !== null ? obj[datafield]["new_name_district"] : "";
								var reason_admin = obj[datafield]["reason_admin"] !== null ? obj[datafield]["reason_admin"] : "";
								var reason_district = obj[datafield]["reason_district"] !== null ? obj[datafield]["reason_district"] : "";
								var distance_admin = obj[datafield]["new_distance_admin"] !== null ? obj[datafield]["new_distance_admin"] : "";
								var distance_district = obj[datafield]["new_distance_district"] !== null ? obj[datafield]["new_distance_district"] : "";
								var district_change_approve = obj[datafield]["district_change_approve"] !== null ? obj[datafield]["district_change_approve"] : "";
								
								var subpart1 = "<tr><td>" +  obj[datafield]["scenario"] +  "</td><td>"  + obj[datafield]["from"] +  "</td><td>"  + obj[datafield]["from_state"] +  "</td><td>"  + obj[datafield]["from_id"] +  "</td><td>"  + obj[datafield]["from_name"] +  "</td><td>"  + obj[datafield]["from_district"] +  "</td><td>"  + obj[datafield]["from_lat"] +  "</td><td>"  + obj[datafield]["from_long"] +  "</td><td>"  + obj[datafield]["to"] +  "</td><td>"  + obj[datafield]["to_state"] +  "</td><td>"  + obj[datafield]["to_id"] +  "</td><td>"  + obj[datafield]["to_name"] +  "</td><td>"  + obj[datafield]["to_district"] +  "</td><td>"  + obj[datafield]["to_lat"] +  "</td><td>"  + obj[datafield]["to_long"] +  "</td><td>"  + obj[datafield]["commodity"] +  "</td><td>"  + obj[datafield]["quantity"] +  "</td><td>"  + obj[datafield]["distance"] + "</td>";
								
								
								if(approve_district=="yes"){
									var approve_district_part = "<td><button class='btn btn-info'>Already Reviewed</button></td>";
								}
								else if(approve_district=="no"){
									var approve_district_part = "<td><button class='btn btn-danger'>Not Approved</button></td>";
								}
								else if(approve_district==""){
									var approve_district_part = "<td><button class='btn btn-warning'>Pending</button></td>";
								}
								
								if(reason_admin.length>0){
									var admin_reason = "<td>" + reason_admin + "</td>";
								}
								else{
									var admin_reason = "<td><select class='form-control' onchange='handleReasonChange(\"" + uniqueid_idreason + "\")' id='" + uniqueid_idreason + "' name='" + uniqueid_idreason + "' disabled><option value=''>Select</option><option value='Road not accessible'>Road not accessible</option><option value='Road repair going on'>Road repair going on</option><option value='Pertaining to Distance'>Pertaining to Distance</option></select></td>";
								}
								
								if(newid_district.length>0){
									if(district_change_approve=="yes"){
										var approve_district_change = "<td><button class='btn btn-info'>Already Approved</button></td>";
									}
									else if(district_change_approve=="no"){
										var approve_district_change = "<td><button class='btn btn-danger'>Not Approved</button></td>";
									}
									else{
										var approve_district_change = "<td><select class='form-control' onchange='enableDisableApprove(\"" + uniqueid_idapprove + "\")' id='" + uniqueid_idapprove + "' name='" + uniqueid_idapprove + "' required><option value=''>Select</option><option value='yes'>Approve</option><option value='no'>Not Approve</option></select></td>";
									}
								}
								else{
									var approve_district_change = "<td></td>";
								}
								
								if(newid_admin.length>0){
									if(district_change_approve=="yes"){
										var approve_district_change = "<td><button class='btn btn-info'>Already Approved</button></td>";
									}
									else if(district_change_approve=="no"){
										var approve_district_change = "<td><button class='btn btn-danger'>Not Approved</button></td>";
									}
									else{
										var approve_district_change = "<td><select class='form-control' onchange='enableDisableApprove(\"" + uniqueid_idapprove + "\")' id='" + uniqueid_idapprove + "' name='" + uniqueid_idapprove + "' required><option value=''>Select</option><option value='yes'>Approve</option><option value='no'>Not Approve</option></select></td>";
									}
								}
								else{
									var approve_district_change = "<td></td>";
								}
								
								if(distance_admin==null || distance_admin==""){
									var distance_admin_part = "<td><input type='text' onchange='handleDistanceChange(\"" + uniqueid_iddistance + "\")' id='" + uniqueid_iddistance + "' name='" + uniqueid_iddistance + "' disabled required /></td>";
								}
								else{
									var distance_admin_part = "<td>" + distance_admin + "</td>"
								}
								
								if(newid_admin.length>0){
									var newid_admin_part = "<td>" + newid_admin + "</td>";
								}
								else{
									var newid_admin_part = "<td><select class='form-control' onchange='handleNewIdChange(\"" + uniqueid + "\")' id='" + uniqueid + "' name='" + uniqueid + "' disabled required><option value=''>Select Id</option>" + warehousepart + "</select></td>";
								}
								var action_btn = "<td><button class='btn btn-warning' onclick='resetRow(\"" + uniqueid + "\")'>Reset</button></td>";
								if(approve_district==""){
									subpart1 = subpart1 + "<td>" + newid_district + "</td><td>" + reason_district + "</td><td>" + distance_district  + "</td>" + approve_district_part + "<td></td><td></td><td></td><td></td>" + action_btn + "</tr>";
								}
								else{
									if(approve_admin=="yes"){
										var approve_admin_part = "<td><button class='btn btn-info'>Already Reviewed</button></td>";
									}
									else if(approve_admin=="no"){
										var approve_admin_part = "<td><button class='btn btn-danger'>Not Approved</button></td>";
									}
									else{
										var approve_admin_part = "<td><select class='form-control' onchange='enableDisable(\"" + uniqueid + "\")' id='" + uniqueid_bool + "' name='" + uniqueid_bool + "' required><option value=''>Select</option><option value='yes'>Approve District</option><option value='same'>Keep System Generated</option><option value='no'>Change ID</option></select></td>";
										uniqueid_array.push(uniqueid_bool);
									}
									subpart1 = subpart1 + "<td>" + newid_district + "</td><td>" + reason_district + "</td><td>" + distance_district + "</td>" + approve_district_part + approve_admin_part + admin_reason + newid_admin_part + distance_admin_part + action_btn + "</tr>";
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
		
		function resetRow(uniqueid) {
			if(confirm("Are you sure you want to reset this row's tagging?")) {
				var month = document.getElementById("month").value;
				$.ajax({
					type: "POST",
					url: "api/ResetRowAdmin.php",
					data: { uniqueid: uniqueid, month: month },
					success: function(result) {
						alert("Row reset successfully");
						fetchDataFromServerId();
					}
				});
			}
		}

		
		function fetchDataFromServer(){
			var approved = document.getElementById("approved").value;
			var reviewed = document.getElementById("reviewed").value;
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
			
			var dataStringFromId = 'month='+ month + '&district=' + district;
			$.ajax({
				type: "POST",
				url: "api/FetchFromId.php",
				data: dataStringFromId,
				cache: false,
				error: function(){
					alert("timeout");
				},
				timeout: 59000,
				success: function(result){
					try{
						var selectInput = document.getElementById("from_id");
						while (selectInput.options.length > 0) {
							selectInput.remove(0);
						}
						var option = document.createElement("option");
						option.text = "Select";
						option.value = "";
						selectInput.appendChild(option);
						
						if(result!=""){
							var resultarray = JSON.parse(result);
							var fromidarray = resultarray.map(function(item) {
								return item.from_id;
							});
							var fromid_fromnamearray = resultarray.map(function(item) {
								return item.from_id.toString() + "_" + item.from_name.toString();
							});
							if (fromid_fromnamearray.length > 0) {
								fromid_fromnamearray.forEach(function(fromId_fromName) {
									var option = document.createElement("option");
									option.text = fromId_fromName;
									option.value = fromId_fromName.split('_')[0];
									selectInput.appendChild(option);
								});
							}
						}
					}
					catch (error) {
						console.log(error);
					}
				}
			});
			
			var dataStringToId = 'month='+ month + '&district=' + district;
			$.ajax({
				type: "POST",
				url: "api/FetchToId.php",
				data: dataStringToId,
				cache: false,
				error: function(){
					alert("timeout");
				},
				timeout: 59000,
				success: function(result){
					try{
						var selectInput = document.getElementById("to_id");
						while (selectInput.options.length > 0) {
							selectInput.remove(0);
						}
						var option = document.createElement("option");
						option.text = "Select";
						option.value = "";
						selectInput.appendChild(option);
						
						if(result!=""){
							var resultarray = JSON.parse(result);
							var toidarray = resultarray.map(function(item) {
								return item.to;
							});
							if (toidarray.length > 0) {
								toidarray.forEach(function(toId) {
									var option = document.createElement("option");
									option.text = toId;
									option.value = toId;
									selectInput.appendChild(option);
								});
							}
						}
					}
					catch (error) {
						console.log(error);
					}
				}
			});
			fetchDataFromServerId();
		}
		
		function fetchCardDataFromServer(){
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
							var totalidsreviewed = resultarray['totalidsreviewed'];
							var totalidsrequested = resultarray['totalidsrequested'];
							var totalidsapproved = resultarray['totalidsapproved'];
							document.getElementById('totalids').innerHTML  = totalids;
							document.getElementById('totalidsreviewed').innerHTML  = totalidsreviewed;
							document.getElementById('totalidsrequested').innerHTML  = totalidsrequested;
							document.getElementById('totalidsapproved').innerHTML  = totalidsapproved;
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
					console.log(error);
				}
			}
		});
		
		
		function pushToSCMLeg2() {
			var monthVal = document.getElementById("month").value;
			if (!monthVal || monthVal === '') {
				alert('Please select a Month first before pushing to SCM.');
				return;
			}
			var parts = monthVal.split('_');
			var mon  = parts[0];
			var yr   = parts.length > 1 ? parts[1] : '';
			if (!mon || !yr) {
				alert('Invalid month/year selection. Please re-select a month from the dropdown.');
				return;
			}
			if (!confirm('Are you sure you want to push Leg-2 optimised data for ' + monthVal + ' to the Bihar SCM portal?')) {
				return;
			}
			var btn = document.getElementById('pushToSCMLeg2Btn');
			btn.disabled = true;
			btn.innerHTML = '&#x23F3; Pushing...';
			$.ajax({
				type: 'POST',
				url: 'api/PushRouteOptimizationLeg2.php',
				data: { month: mon, year: yr },
				cache: false,
				timeout: 600000,
				error: function(xhr, status, error) {
					btn.disabled = false;
					btn.innerHTML = '&#x2191; Push to SCM (Leg2)';
					alert('Request failed: ' + status + ' - ' + error);
				},
				success: function(result) {
					btn.disabled = false;
					btn.innerHTML = '&#x2191; Push to SCM (Leg2)';
					try {
						var res = JSON.parse(result);
						if (res.status === 'success') {
							alert('SUCCESS: ' + res.message);
						} else {
							alert('ERROR: ' + res.message);
						}
					} catch(e) {
						alert('Unexpected response: ' + result);
					}
				}
			});
		}
		
    </script>
    </body>
</html>
