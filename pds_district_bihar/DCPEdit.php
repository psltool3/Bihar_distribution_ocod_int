<?php
require('util/Connection.php');
require('util/SessionCheck.php');
require('Header.php');

$district = "";
$name = "";
$id = "";
$type = "";
$latitude = "";
$longitude = "";
$demand = "";
$demand_rice = "";
$active = "";

if(isset($_POST["uid"])){
	$uniqueid = $_POST["uid"];
	$query = "SELECT * FROM dcp WHERE uniqueid='$uniqueid'";
	$result = mysqli_query($con,$query);
	$numrows = mysqli_num_rows($result);
	if($numrows!=0){
		$row = mysqli_fetch_assoc($result);
		$district = $row['district'];
		$name = $row['name'];
		$id = $row['id'];
		$type = $row['type'];
		$latitude = $row['latitude'];
		$longitude = $row['longitude'];
		$demand = $row['demand'];
		$demand_rice = $row['demand_rice'];
		$active = $row['active'];
	}
	else{
		header("Location:DCP.php");
	}
}
else{
	header("Location:DCP.php");
}

?>
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
<script src="crypto-js/crypto-js.js"></script>
<script src="js/Encryption.js"></script>

<script>
	function verifyCaptcha() {
		var readableString = document.getElementById("password").value;
		var nonceValue = "nonce_value";
		let encryption = new Encryption();
		var encrypted = encryption.encrypt(readableString, nonceValue);
		document.getElementById("password").value = encrypted;
	}
</script>
                <!-- START BREADCRUMB -->
                <ul class="breadcrumb">
                    <li><a href="DCP.php">Home</a></li>
                    <li class="active">DCP Edit</li>
                </ul>
                <!-- END BREADCRUMB -->


				<!-- PAGE CONTENT WRAPPER -->
                 <div class="page-content-wrap">

                    <div class="row">
                        <div class="col-md-12">
                            <form action="api/DCPEdit.php" method="POST" class="form-horizontal" enctype = "multipart/form-data">
                            <div class="panel panel-default">
                               <div class="panel-body">
                                    <p>Fill this form to add new DCP.</p>
                                </div>
                             <div class="panel-body">
                                    <div class="row">
                                        <div class="col-md-6">
											<div class="form-group">
                                                <label class="col-md-3 control-label">Name of DCP*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo $name ?>" required />
                                                    </div>
                                                    <span class="help-block">DCP Name</span>
                                                </div>
                                            </div>
											
											<input type="hidden" id="uniqueid" name="uniqueid" value="<?php  echo $_POST["uid"] ?>" />
											<input type="hidden" id="active" name="active" value="<?php  echo $active ?>" />
											
											<div class="form-group">
                                                <label class="col-md-3 control-label">Type*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="type" name="type" value="<?php echo $type ?>" 
														required />
                                                    </div>
                                                    <span class="help-block">Type of DCP</span>
                                                </div>
                                            </div>
											
											<div class="form-group">
                                                <label class="col-md-3 control-label">Latitude of DCP*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="latitude" name="latitude" value="<?php echo $latitude ?>" required />
                                                    </div>
                                                    <span class="help-block">Latitude of DCP</span>
                                                </div>
                                            </div>
											
											<div class="form-group">
                                                <label class="col-md-3 control-label">Longitude of DCP*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="longitude" name="longitude" value="<?php echo $longitude ?>"required />
                                                    </div>
                                                    <span class="help-block">Longitude of DCP</span>
                                                </div>
                                            </div>
											
                                        </div>
                                        <div class="col-md-6">
										
											<div class="form-group">
                                                <label class="col-md-3 control-label">District*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
												   <span class="input-group-addon"><span class="fa fa-arrow-down"></span></span>
                                                    <select class="form-control" id="district" name="district">
                                                    </select>
													</div>
                                                    <span class="help-block">District</span>
                                                </div>
                                            </div>
										
											<div class="form-group">
                                                <label class="col-md-3 control-label">DCP Id*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="id" name="id" value="<?php echo $id ?>" required />
                                                    </div>
                                                    <span class="help-block">DCP ID</span>
                                                </div>
                                            </div>
											
											<div class="form-group">
                                                <label class="col-md-3 control-label">FRice Quintals*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="demand" name="demand" value="<?php echo $demand ?>" required />
                                                    </div>
                                                    <span class="help-block">Procurement in Quintals</span>
                                                </div>
                                            </div>
											<div class="form-group">
                                                <label class="col-md-3 control-label">Wheat Quintals*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="demand_rice" name="demand_rice" value="<?php echo $demand_rice ?>" required />
                                                    </div>
                                                    <span class="help-block">Procurement in Quintals</span>
                                                </div>
                                            </div>											
                                        </div>
                                    </div>
                                </div>
                               <div class="panel-footer">
                                    <button class="btn btn-primary pull-right" onclick="showPopup()" type="button">Submit</button>
                                </div>
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
										<center><button class="btn btn-primary" onclick="verifyCaptcha()">Verify</button></center>
								</div>
                            </div>
                            </form>
                        </div>
                    </div>
					</br></br></br></br></br></br></br></br></br></br></br></br></br></br></br></br></br></br></br>
                </div>
                            </div>
                            <!-- END SIMPLE DATATABLE --
                        </div>
                    </div>
                </div>
                <!-- PAGE CONTENT WRAPPER -->
            </div>
            <!-- END PAGE CONTENT -->
        </div>
        <!-- END PAGE CONTAINER -->



		<?php
			echo "<script>setSelectedValue('type','$type'); </script>";
			
		?>
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
		
		<?php
			require('DistrictAutocomplete.php');
			
			echo "<script>setSelectedValue('district','$district'); </script>";
		?>
		<script>
		function showPopup() {
            
			var name = document.getElementById('name').value;
            var type = document.getElementById('type').value;
			var latitude = document.getElementById('latitude').value;
            var longitude = document.getElementById('longitude').value;
			var id = document.getElementById('id').value;
            var demand = document.getElementById('demand').value;
			var demand_rice = document.getElementById('demand_rice').value;
            var district = document.getElementById('district').value;

            if (name === '' || type === '' || latitude === '' || longitude === '' || id === '' || demand === '' ||demand_rice === '' || district === '') {
                alert('Please enter all fields');
                return false;
            }
			
            document.getElementById('popup').style.display = 'block';
        }
		
		function hidePopup() {
            document.getElementById('popup').style.display = 'none';
        }
		
		</script>

    </body>
</html>
