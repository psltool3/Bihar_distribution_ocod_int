<?php
require('util/Connection.php');
require('util/SessionCheck.php');
require('Header.php');
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
<script src="crypto-js/crypto-js.js"></script>
<script src="js/Encryption.js"></script>

                <!-- START BREADCRUMB -->
                <ul class="breadcrumb">
                    <li><a href="#">Home</a></li>
                    <li class="active">FPS</li>
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
								<a href="BulkFPSStatusChange.php" style="float:right;margin-top:10px;margin-right:13px"><button type="button" class="btn btn-info">District-Wise Status Change</button></a>
								<a href="BulkFPSDataEdit.php" style="float:right;margin-top:10px;margin-right:13px"><button type="button" class="btn btn-warning">Bulk Data Edit</button></a>
								<a href="BulkFPSData.php" style="float:right;margin-top:10px;margin-right:13px"><button type="button" class="btn btn-info">Bulk Data Add</button></a>
								<span style="float:right;margin-top:10px;margin-right:13px"><button type="button" onclick="delete_all()"  class="btn btn-danger">Delete All</button></span>
								<a href="FPSAdd.php" style="float:right;margin-top:10px;margin-right:13px"><button type="button" class="btn btn-success">Add New</button></a>
                                <a href="api/BulkFPSDownloadEdit.php" style="float:right;margin-top:10px;margin-right:13px"><button type="button" class="btn btn-info">Download Data</button></a>
                                <a href="api/LoadFPSData.php" style="float:right;margin-top:10px;margin-right:13px"><button type="button" class="btn btn-primary">Load from API</button></a>
                            
								</br></br>
								<div>
								</br></br></br>
                                <a href="api/SmartFPSStatus.php" style="float:right;margin-top:10px;margin-right:13px"><button type="button" class="btn btn-warning">Model FPS Status Change</button></a>
								<a href="api/NonSmartFPSStatus.php" style="float:right;margin-top:10px;margin-right:13px"><button type="button" class="btn btn-info">Non Smart FPS Status Change</button></a>
								</div>
								
								<div class="row" style="margin-top:60px">
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
                                    <table class="table">
                                        <thead>
                                            <tr>
												<th style="font-size:16px">District</th>
												<th style="font-size:16px">Name of FPS</th>
												<th style="font-size:16px">FPS ID</th>
												<th style="font-size:16px">Smart FPS/Non Smart FPS</th>
												<th style="font-size:16px">Latitude</th>
												<th style="font-size:16px">Longitude</th>
												<th style="font-size:16px">Demand of Wheat(Qtl)</th>
												<th style="font-size:16px">Demand of FRice(Qtl)</th>
												<th style="font-size:16px">Status</th>
												<th style="font-size:16px">Change Status</th>
                                                <th style="font-size:16px">Edit</th>
                                                <th style="font-size:16px">Delete</th>
                                            </tr>
                                        </thead>
                                        <tbody id="fps_table">
										</tbody>
										
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
		
		
		<?php  require('DistrictAutocomplete.php'); ?>
        <!-- END PAGE PLUGINS -->

        <!-- START TEMPLATE -->
        
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

		document.getElementById('popup').style.display = 'none';
		
		function delete_entry(temp_id){
			document.getElementById('popup').style.display = 'block';
			document.getElementById('deleteid').value = temp_id;
		}

		function edit_entry(temp_id){
			post({uid: temp_id} ,"FPSEdit.php");
		}
		
		function change_status(temp_id){
			post({uid: temp_id} ,"api/FPSStatus.php");
		}
		
		function delete_all(){
			document.getElementById('popup').style.display = 'block';
			document.getElementById('deleteid').value = "all";
		}
		
		function VerifyAndDelete(){
			var username = document.getElementById('username').value;
			var password = document.getElementById('password').value;
			var temp_id = document.getElementById('deleteid').value;
			var nonceValue = "nonce_value";
			let encryption = new Encryption();
			var encrypted = encryption.encrypt(password, nonceValue);
			post({uid: temp_id,username:username,password:encrypted} ,"api/FPSDelete.php");
		}
		
		function hidePopup() {
            document.getElementById('popup').style.display = 'none';
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
			
			var dataString = "district=" + district;
			
			$.ajax({
				type: "POST",
				url: "api/fetchFPSData.php",
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
							var subpart = "<tr><td>" +  obj[datafield]["district"] +  "</td><td>"  + obj[datafield]["name"] +  "</td><td>"  + obj[datafield]["id"] +  "</td><td>"  + obj[datafield]["type"] +  "</td><td>"  + obj[datafield]["latitude"] +  "</td><td>"  + obj[datafield]["longitude"] +  "</td><td>"  + obj[datafield]["demand"] +  "</td><td>"  + obj[datafield]["demand_rice"]  + "</td><td>" + status + "</td><td> <button class='btn btn-info btn-rounded' onclick=\"change_status('"+ temp_id + "')\">Change Status</button></td><td> <button class='btn btn-warning btn-rounded' onclick=\"edit_entry('" + temp_id +  "')\">Edit</button></td><td> <button class='btn btn-danger btn-rounded' onclick=\"delete_entry('" + temp_id +"')\">Delete</button></td></tr>";
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
    

		</script>
    </body>
</html>
