<?php
require('util/Connection.php');
require('util/SessionCheck.php');
require('Header.php');
$district = $_SESSION['district_district'];


$query = "SELECT * FROM optimised_table ORDER BY last_updated DESC LIMIT 1";
$result = mysqli_query($con, $query);
$response = array();
$id = "";
while ($row = mysqli_fetch_array($result)) {
	$id = $row["id"];
}


$tablename = "optimiseddata_" . $id;


$query = "SELECT to_district FROM " . $tablename . " WHERE to_district='$district'";
$result = mysqli_query($con, $query);
$totalids = mysqli_num_rows($result);

$query = "SELECT approve_district FROM " . $tablename . " WHERE to_district='$district' AND approve_district='yes'";
$result = mysqli_query($con, $query);
$totalidsreviewed = mysqli_num_rows($result);

$query = "SELECT new_id_district FROM " . $tablename . " WHERE to_district='$district' AND new_id_district<>''";
$result = mysqli_query($con, $query);
$totalidsrequested = mysqli_num_rows($result);

$query = "SELECT approve_admin FROM " . $tablename . " WHERE to_district='$district' AND approve_admin='yes'";
$result = mysqli_query($con, $query);
$totalidsapproved = mysqli_num_rows($result);

//code to check the time expiry

$query = "SELECT * FROM timer WHERE 1";
$result = mysqli_query($con, $query);
while ($row = mysqli_fetch_array($result)) {
	$date = $row['deadline_date'];
	$time = $row['deadline_time'];
}
$targetDateTime = $date . " " . $time;
$targetTimestamp = strtotime($targetDateTime);
$currentTimestamp = time();
$expired = 0;

if ($currentTimestamp >= $targetTimestamp) {
	$expired = 1;
}


?>
<style>
	body {
		font-size: 15px;
		/* Set the base font size for the entire page */
	}

	/* Apply increased font size to specific elements */
	h3 {
		font-size: 24px;
		/* Increase font size for heading elements */
	}

	/* You can add similar styles for other elements as needed */
	/* For example: */
	th td {
		font-size: 18px;
		/* Increase font size for table headers and data cells */
	}

	.btn {
		font-size: 12px;
		/* Increase font size for buttons */
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

	th,
	td {
		border: 2px solid black;
		padding: 25px;
		text-align: center;
		color: black;
		border-color: black !important;

	}

	tr {
		border: 2px solid black;
		/* Set border for table rows */
	}

	.table>tfoot>tr>td {
		border-color: black !important;
		border-width: 2px !important;
	}


	/* Apply background color to even rows */
	#export_table tbody tr:nth-child(even) {
		background-color: #FFCF8B;
	}
</style>


<script>
	function setSelectedValue(obj_value, valueToSet) {
		var obj = document.getElementById(obj_value);
		for (var i = 0; i < obj.options.length; i++) {
			if (obj.options[i].value == valueToSet) {
				obj.options[i].selected = true;
				return;
			}
		}
	}

	function setSelectedValue1(obj_value, valueToSet) {
		valueToSet = valueToSet.substring(0, valueToSet.length - 1);
		var obj = document.getElementById(obj_value);
		for (var i = 0; i < obj.options.length; i++) {
			if (obj.options[i].text == valueToSet) {
				obj.options[i].selected = true;
				return;
			}
		}
	}
</script>
<!-- START BREADCRUMB -->
<ul class="breadcrumb">
	<li><a href="#">Home</a></li>
	<li class="active">Bihar Route Optimisation For PDS</li>
	<li id="lastLogin"></li>
</ul>
<!-- END BREADCRUMB -->


<!-- PAGE CONTENT WRAPPER -->
<div class="page-content-wrap">

	<div class="row">
		<div class="col-md-12">

			<!-- START SIMPLE DATATABLE -->
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title">Bihar Route Optimisation For PDS District - <b><?php echo $district; ?></b>
						<div id="timer"> <b>Time Left &nbsp </b> <span id="countdown"></span>
					</h3>
				</div>
			</div>
			<div class="row">
				<div class="col-md-3 mb-4">
					<div class="card h-100"
						style="background-color:#56A5FF; color:white; padding:20px; font-weight: bold;">
						<div style="font-size:25px"><?php echo $totalids; ?></div>
						<div style="font-size:15px">TOTAL TAGS</div>
					</div>
				</div>
				<div class="col-md-3 mb-4">
					<div class="card h-100"
						style="background-color:#3FDBBC; color:white; padding:20px; font-weight: bold;">
						<div style="font-size:25px"><?php echo $totalidsreviewed; ?></div>
						<div style="font-size:15px">TOTAL REVIEWED</div>
					</div>
				</div>
				<div class="col-md-3 mb-4">
					<div class="card h-100"
						style="background-color:#FFC167; color:white; padding:20px; font-weight: bold;">
						<div style="font-size:25px"><?php echo $totalidsrequested; ?></div>
						<div style="font-size:15px">TOTAl TAGS CHANGED</div>
					</div>
				</div>
				<div class="col-md-3 mb-4">
					<div class="card h-100"
						style="background-color:#F96981; color:white; padding:20px; font-weight: bold;">
						<div style="font-size:25px"><?php echo $totalidsapproved; ?></div>
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
								<select class="form-control select" onClick="fetchDataFromServerId()" id="reviewed"
									name="reviewed" style="z-index:9999">
									<option value=''>Select</option>
									<option value='reviewed'>Reviewed</option>
									<option value='notreviewed'>Not Reviewed</option>
								</select>
							</div>
							<span class="help-block">Reviewed</span>
						</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="form-group">
						<div class="col-md-12">
							<div class="input-group">
								<span class="input-group-addon"><span class="fa fa-certificate"></span></span>
								<select class="form-control select" onClick="fetchDataFromServerId()" id="approved"
									name="approved" style="z-index:9999">
									<option value=''>Select</option>
									<option value='approved'>Admin Approved</option>
									<option value='notapproved'>Admin not Approved</option>
								</select>
							</div>
							<span class="help-block">Admin Approval</span>
						</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="form-group">
						<div class="col-md-12">
							<div class="input-group">
								<span class="input-group-addon"><span class="fa fa-certificate"></span></span>
								<select class="form-control" id="from_id" name="from_id"
									onchange="fetchDataFromServerId()">
									<option value=''>Select</option>
								</select>
							</div>
							<span class="help-block">Select from id</span>
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
							<span class="help-block">Select To Id</span>
						</div>
					</div>
				</div>
			</div>
			</br></br></br>
			<!-- END SIMPLE DATATABLE -->
			<button id="downloadCSV" class="btn btn-warning pull-right" style="margin-left: 10px;"
				type="button">Download CSV</button>
			<button id="downloadXLSX" class="btn btn-success pull-right" style="margin-left: 10px;"
				type="button">Download XLSX</button>
			<button id="downloadPDF" class="btn btn-danger pull-right" style="margin-bottom: 10px;"
				type="button">Download PDF</button>
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
							<th style="font-size:16px">District Reviewed</th>
							<th style="font-size:16px">District Suggested Warehouse</th>
							<th style="font-size:16px">District Reason for not Approve</th>
							<th style="font-size:16px">District Suggested Warehouse Distance</th>
							<th style="font-size:16px">Admin Approved</th>
							<th style="font-size:16px">Action</th>
						</tr>
					</thead>
					<tbody id="table_body">

					</tbody>

				</table>
			</div>
			<input type="hidden" id="district" name="district" value="<?php echo $district ?>" />
			<?php
			if ($expired == 0) {
				echo "<button class='btn btn-info pull-right' onClick='acceptAll()' type='button' style='margin-left:10px;'>Accept All</button><button class='btn btn-primary pull-right' onClick='sendData()' type='button'>Save</button>";
			} else {
				echo "<button class='btn btn-primary pull-right' type='button'>Time Expired</button>";
			}
			?>
			&nbsp </br>
			<div id="popup" class="popup">
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
					<input type="hidden" class="form-control" id="deleteid" name="deleteid" />

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

				<center><button class="btn btn-primary" type="button" onClick="VerifyAndDelete()">Verify</button>
				</center>
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

	var modifiedData = {};
	var modifiedIdData = {};
	var modifiedReasonData = {};
	var modifiedDistanceData = {};
	var modifiedApproveData = {};

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

	function enableDisable(selectedId) {
		newvalue = document.getElementById(selectedId + "_bool").value;
		if (newvalue == "yes") {
			modifiedData[selectedId] = "yes";
			document.getElementById(selectedId).disabled = true;
			document.getElementById(selectedId + "_idreason").disabled = true;
			document.getElementById(selectedId + "_iddistance").value = '';
			document.getElementById(selectedId + "_iddistance").disabled = true;
		}
		else if (newvalue == "no") {
			modifiedData[selectedId] = "no";
			document.getElementById(selectedId).value = '';
			document.getElementById(selectedId).disabled = false;
			document.getElementById(selectedId + "_idreason").value = '';
			document.getElementById(selectedId + "_idreason").disabled = false;
			document.getElementById(selectedId + "_iddistance").value = '';
			document.getElementById(selectedId + "_iddistance").disabled = false;
		}
		else {
			modifiedData[selectedId] = "same";
			document.getElementById(selectedId).value = '';
			document.getElementById(selectedId).disabled = true;
			document.getElementById(selectedId + "_idreason").disabled = true;
			document.getElementById(selectedId + "_iddistance").value = '';
			document.getElementById(selectedId + "_iddistance").disabled = true;
		}
	}

	function handleNewIdChange(selectedId) {
		newvalue = document.getElementById(selectedId).value;
		modifiedData[selectedId] = newvalue;
		if (newvalue == '') {
			delete modifiedData[selectedId];
		}
	}

	function handleDistanceChange(selectedId) {
		newvalue = document.getElementById(selectedId).value;
		modifiedDistanceData[selectedId] = newvalue;
		if (newvalue == '') {
			delete modifiedDistanceData[selectedId];
		}
	}

	function handleReasonChange(selectedId) {
		newvalue = document.getElementById(selectedId).value;
		modifiedReasonData[selectedId] = newvalue;
		if (newvalue == '') {
			delete modifiedReasonData[selectedId];
		}
	}

	function sendData() {
		for (var key in modifiedData) {
			if (modifiedData.hasOwnProperty(key)) {
				var value = modifiedData[key];
				if (value != "yes" && value != "" && value != "same") {
					if (!modifiedReasonData.hasOwnProperty(key + "_idreason")) {
						alert("New Id " + String(value) + " Reason needs to be selected");
						return;
					}
					if (!modifiedDistanceData.hasOwnProperty(key + "_iddistance")) {
						alert("New Id " + String(value) + " distance needs to be filled");
						return;
					}
					var distVal = modifiedDistanceData[key + "_iddistance"];
					if (isNaN(distVal) || distVal.toString().trim() === "" || parseFloat(distVal) < 0) {
						alert("New Id " + String(value) + " distance must be a valid non-negative number");
						return;
					}
				}
			}
		}
		const mergedDict = Object.assign({}, modifiedData, modifiedIdData, modifiedDistanceData, modifiedReasonData, modifiedApproveData);
		post(mergedDict, "api/SaveData.php");
	}

	var uniqueid_bool_array = [];

	function acceptAll() {
		for (let i = 0; i < uniqueid_bool_array.length; i++) {
			setSelectedValue(uniqueid_bool_array[i], 'yes');
			enableDisable(uniqueid_bool_array[i].substring(0, uniqueid_bool_array[i].indexOf('_bool')));
		}
	}
	document.getElementById('downloadCSV').addEventListener('click', async function () {
		try {

			const csvResponse = await fetch('api/DownloadOptimalData.php?format=csv');
			const csvBlob = await csvResponse.blob();
			downloadFile(csvBlob, 'Optimised_Planning_' + getDateString() + '.csv');
		} catch (error) {
			console.error('Error downloading CSV file:', error);
		}
	});

	function getDateString() {
		var currentDate = new Date();
		var year = currentDate.getFullYear();
		var month = currentDate.getMonth() + 1; // Month is zero-based, so we add 1
		var day = currentDate.getDate();
		var str = year + "-" + month + "-" + day;
		return str;
	}

	// Event listener for downloading XLSX
	document.getElementById('downloadXLSX').addEventListener('click', async function () {
		try {

			const excelResponse = await fetch('api/DownloadOptimalData.php?format=xlsx');
			const excelBlob = await excelResponse.blob();
			downloadFile(excelBlob, 'Optimised_Planning_' + getDateString() + '.xlsx');
		} catch (error) {
			console.error('Error downloading XLSX file:', error);
		}
	});

	document.getElementById('downloadPDF').addEventListener('click', async function () {
		try {

			const excelResponse = await fetch('api/DownloadOptimalData.php?format=pdf');
			const excelBlob = await excelResponse.blob();
			downloadFile(excelBlob, 'Optimised_Planning_' + getDateString() + '.pdf');
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



	function fetchDataFromServer() {
		var approved = document.getElementById("approved").value;
		var reviewed = document.getElementById("reviewed").value;
		var district = '<?php echo $district ?>';

		if (district == "" || district == "all") {
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

		var dataStringFromId = 'approved=' + approved + '&reviewed=' + reviewed + '&district=' + district;
		$.ajax({
			type: "POST",
			url: "api/FetchFromId.php",
			data: dataStringFromId,
			cache: false,
			error: function () {
				alert("timeout");
			},
			timeout: 59000,
			success: function (result) {
				try {
					var selectInput = document.getElementById("from_id");
					while (selectInput.options.length > 0) {
						selectInput.remove(0);
					}
					var option = document.createElement("option");
					option.text = "Select";
					option.value = "";
					selectInput.appendChild(option);

					if (result != "") {
						var resultarray = JSON.parse(result);
						var fromidarray = resultarray.map(function (item) {
							return item.from_id;
						});
						if (fromidarray.length > 0) {
							fromidarray.forEach(function (fromId) {
								var option = document.createElement("option");
								option.text = fromId;
								option.value = fromId;
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
		var dataStringToId = 'approved=' + approved + '&reviewed=' + reviewed + '&district=' + district;
		$.ajax({
			type: "POST",
			url: "api/FetchToId.php",
			data: dataStringToId,
			cache: false,
			error: function () {
				alert("timeout");
			},
			timeout: 59000,
			success: function (result) {
				try {
					var selectInput = document.getElementById("to_id");
					while (selectInput.options.length > 0) {
						selectInput.remove(0);
					}
					var option = document.createElement("option");
					option.text = "Select";
					option.value = "";
					selectInput.appendChild(option);

					if (result != "") {
						var resultarray = JSON.parse(result);
						var toidarray = resultarray.map(function (item) {
							return item.to;
						});
						if (toidarray.length > 0) {
							toidarray.forEach(function (toId) {
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

	function fetchDataFromServerId() {
		var approved = document.getElementById("approved").value;
		var reviewed = document.getElementById("reviewed").value;
		var district = '<?php echo $district ?>';
		var from_id = document.getElementById("from_id").value;
		var to_id = document.getElementById("to_id").value;

		var dataString = 'approved=' + approved + '&reviewed=' + reviewed + '&district=' + district + '&fromid=' + from_id + '&toid=' + to_id;
		if (dataString == '') {
			alert("Please Fill All Fields");
		}
		else {
			$("#filter_button").attr("disabled", true);
			$.ajax({
				type: "POST",
				url: "api/FetchDbData.php",
				data: dataString,
				cache: false,
				error: function () {
					alert("timeout");
					$("#filter_button").attr("disabled", false);
				},
				timeout: 59000,
				success: function (result) {
					console.log(result);
					$('#table_body').empty();
					try {
						var resultarray = JSON.parse(result);
						var warehousearray = resultarray["warehouse"];

						var warehousepart = "";
						for (var ids in warehousearray) {
							var warehouse_id = warehousearray[ids]["id"];
							warehousepart = warehousepart + "<option value=" + warehouse_id + ">" + warehouse_id + "</option>";
						}

						var obj = resultarray["data"];
						if (obj !== undefined && obj !== null) {
							for (var datafield in obj) {
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


								var subpart1 = "<tr><td>" + obj[datafield]["scenario"] + "</td><td>" + obj[datafield]["from"] + "</td><td>" + obj[datafield]["from_state"] + "</td><td>" + obj[datafield]["from_id"] + "</td><td>" + obj[datafield]["from_name"] + "</td><td>" + obj[datafield]["from_district"] + "</td><td>" + obj[datafield]["from_lat"] + "</td><td>" + obj[datafield]["from_long"] + "</td><td>" + obj[datafield]["to"] + "</td><td>" + obj[datafield]["to_state"] + "</td><td>" + obj[datafield]["to_id"] + "</td><td>" + obj[datafield]["to_name"] + "</td><td>" + obj[datafield]["to_district"] + "</td><td>" + obj[datafield]["to_lat"] + "</td><td>" + obj[datafield]["to_long"] + "</td><td>" + obj[datafield]["commodity"] + "</td><td>" + obj[datafield]["quantity"] + "</td><td>" + obj[datafield]["distance"] + "</td>";

								if (obj[datafield]["new_id"] == null) {
									obj[datafield]["new_id"] = "";
								}

								if (approve_admin == "yes" && newid_admin == "") {
									var admin_approve = "<td><button class='btn btn-info'>Approved</button></td>";
								}
								else if (approve_admin == "yes" && newid_district != "") {
									var admin_approve = "<td><button class='btn btn-danger'>Not Approved</button></td>";
								}
								else if (approve_admin == "no") {
									var admin_approve = "<td><button class='btn btn-danger'>Not Approved</button></td>";
								}
								else if (approve_admin == "") {
									var admin_approve = "<td><button class='btn btn-warning'>Pending</button></td>";
								}

								if (approve_district == "yes") {
									var warehouse_id_part = "<td><button class='btn btn-info'>Reviewed</button></td><td>" + newid_district + "</td>";
								}
								else if (approve_district == "no") {
									var warehouse_id_part = "<td><select class='form-control' onchange='enableDisable(\"" + uniqueid + "\")' id='" + uniqueid_bool + "' name='" + uniqueid_bool + "'><option value=''>Select</option><option value='yes'>Agree</option><option value='no'>Change ID</option></select></td><td><select class='form-control' onchange='handleNewIdChange(\"" + uniqueid + "\")' id='" + uniqueid + "' name='" + uniqueid + "' disabled><option value=''>Select Id</option>" + warehousepart + "</select></td>";
								}
								else {
									var warehouse_id_part = "<td><select class='form-control' onchange='enableDisable(\"" + uniqueid + "\")' id='" + uniqueid_bool + "' name='" + uniqueid_bool + "'><option value=''>Select</option><option value='yes'>Agree</option><option value='no'>Change ID</option></select></td><td><select class='form-control' onchange='handleNewIdChange(\"" + uniqueid + "\")' id='" + uniqueid + "' name='" + uniqueid + "' disabled><option value=''>Select Id</option>" + warehousepart + "</select></td>";
									uniqueid_bool_array.push(uniqueid_bool);
								}

								if (distance_district === null || distance_district === "") {
									var newdistance = "<td><input type='text' inputmode='decimal' oninput='this.value = this.value.replace(/[^0-9.]/g, \"\").replace(/(\\..*?)\\..*/g, \"$1\"); handleDistanceChange(\"" + uniqueid_iddistance + "\");' onchange='handleDistanceChange(\"" + uniqueid_iddistance + "\")' id='" + uniqueid_iddistance + "' name='" + uniqueid_iddistance + "' disabled required /></td>";
								}
								else {
									var newdistance = "<td>" + distance_district + "</td>"
								}

								if (reason_district.length > 0) {
									var district_reason = "<td>" + reason_district + "</td>";
								}
								else {
									var district_reason = "<td><select class='form-control' onchange='handleReasonChange(\"" + uniqueid_idreason + "\")' id='" + uniqueid_idreason + "' name='" + uniqueid_idreason + "' disabled><option value=''>Select</option><option value='Road not accessible'>Road not accessible</option><option value='Road repair going on'>Road repair going on</option><option value='Pertaining to Distance'>Pertaining to Distance</option></select></td>";
								}

								var action_btn = "";
								if (approve_admin == "yes") {
									action_btn = "<td><button class='btn btn-warning' disabled>Reset</button></td>";
								} else {
									action_btn = "<td><button class='btn btn-warning' onclick='resetRow(\"" + uniqueid + "\")'>Reset</button></td>";
								}
								$('#table_body').append(subpart1 + warehouse_id_part + district_reason + newdistance + admin_approve + action_btn + "</tr>");
							}
						}
						//fetchCardDataFromServer();							
					}
					catch (error) {
						console.log(error);
					}
				}
			});
		}
	}
	fetchDataFromServer();

	function resetRow(uniqueid) {
		if (confirm("Are you sure you want to reset this row's tagging?")) {
			$.ajax({
				type: "POST",
				url: "api/ResetRowDistrict.php",
				data: { uniqueid: uniqueid },
				success: function (result) {
					alert("Row reset successfully");
					fetchDataFromServerId();
				}
			});
		}
	}

	// Set the end time from PHP to JavaScript
	var endTime = "<?php echo strtotime($date . " " . $time); ?>";

	// Update the countdown timer every second
	function updateTimer() {
		var currentTime = Math.floor(Date.now() / 1000);
		var timeDifference = endTime - currentTime;

		// Ensure the timer doesn't go below zero
		var remainingTime = Math.max(0, timeDifference);

		var hours = Math.floor(remainingTime / 3600);
		var minutes = Math.floor((remainingTime % 3600) / 60);
		var seconds = remainingTime % 60;

		var days = Math.floor(hours / 24);
		var remainingHours = hours % 24;


		// Display the updated time
		document.getElementById('countdown').innerHTML = "<b>Days:</b>" + days.toString() + " <b>Hours:</b>" + remainingHours.toString() + " <b>Minutes:</b>" + minutes.toString() + " <b>Seconds:</b>" + seconds.toString();
	}

	// Update the timer initially
	updateTimer();

	// Update the timer every second
	setInterval(updateTimer, 1000);

	// Helper function to format time
	function sprintf(format) {
		var args = Array.prototype.slice.call(arguments, 1);
		return format.replace(/%(\d+)/g, function (match, index) {
			return typeof args[index] !== 'undefined' ? args[index] : match;
		});
	}

	function fetchLastLogin() {
		fetch('api/LastLogin.php')
			.then(response => response.json())
			.then(data => {
				if (data.error) {
					// Handle error response
					document.getElementById('lastLogin').innerText = data.error;
				} else {
					// Display the last login time
					document.getElementById('lastLogin').innerText =
						data.lastlogin ? `Last Login: ${data.lastlogin}` : 'No Last Login Data Found';
				}
			})
			.catch(error => {
				// Handle network or other errors
				console.error('Error:', error);
				document.getElementById('lastLogin').innerText = 'An error occurred while fetching data.';
			});
	}

	// Fetch the last login when the page loads
	window.onload = fetchLastLogin;


</script>
</body>

</html>