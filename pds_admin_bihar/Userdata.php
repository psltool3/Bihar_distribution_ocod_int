<?php
require('util/Connection.php');
require('util/SessionCheck.php');
require('Header.php');

?>

<style>
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
                    <li class="active">Login Data</li>
                </ul>
                <!-- END BREADCRUMB -->                       
                
				
				<!-- PAGE CONTENT WRAPPER -->
                <div class="page-content-wrap">                
                
                    <div class="row">
                        <div class="col-md-12">


                            <!-- START SIMPLE DATATABLE -->
                            <div class="panel panel-default">
								<div class="panel-heading">                                
                                    <h3 class="panel-title">Login Data</h3>
                                </div>
								<a href="LoginDataAdd.php" style="float:right;margin-top:10px;margin-right:13px"><button type="button" class="btn btn-success">Add New</button></a>
                                <div class="panel-body">
                                 <div class="table-responsive">
                                    <table id="export_table" class="table">
                                        <thead>
                                            <tr>
												<th>Email Id</th>
												<th>Password</th>
                                                <th>Verified</th>
                                                <th>Role/District</th>
                                                <th>Login Count</th>
                                                <th>Last Login</th>
                                                <th>Verify</th>
                                                <th>Block</th>
                                                <th>Delete</th>
                                            </tr>
                                        </thead>
                                        <tbody id="table_body">
										<?php
										
										$query = "SELECT * FROM login";
										$result = mysqli_query($con,$query);
										$numrows = mysqli_num_rows($result);
										while($row = mysqli_fetch_array($result))
										{
											
											$temp_id = (string)$row['uid'];
											$verify = $row['verified'];
											$verified = "Not Verified";
											if($verify==1){
												$verified = "Verified";
											}
											
											echo "<tr><td>{$row['username']}</td>".
											 "<td>{$row['password']}</td>".
											 "<td>$verified</td>".
											 "<td>{$row['role']}</td>".
											 "<td>{$row['count']}</td>".
											 "<td>{$row['lastlogin']}</td>".
											 "<td> <button class='btn btn-success btn-rounded' onclick=\"verify_funtion('{$temp_id}')\">Verify</button></td>".
											 "<td> <button class='btn btn-warning btn-rounded' onclick=\"block_function('{$temp_id}')\">Block</button></td>".
											 "<td> <button class='btn btn-danger btn-rounded' onclick=\"delete_function('{$temp_id}')\">Delete</button></td></tr>";
             										
										}

										?>
                                        </tbody>
                                    </table>
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
										
										<center><button class="btn btn-primary" type="button" onClick="proceed()">Verify</button></center>
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
		var methodCalled = "";
		var uidCalled = "";
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

		function delete_function(temp_id){	
			document.getElementById('popup').style.display = 'block';
			methodCalled = "delete";
			uidCalled = temp_id;
		}
		
		function block_function(temp_id){
			document.getElementById('popup').style.display = 'block';
			methodCalled = "block"
			uidCalled = temp_id;
		}
		
		function verify_funtion(temp_id){
			document.getElementById('popup').style.display = 'block';
			methodCalled = "verify";
			uidCalled = temp_id;
		}
        
		function proceed(){
			var username = document.getElementById('username').value;
			var password = document.getElementById('password').value;
			var nonceValue = "nonce_value";
			let encryption = new Encryption();
			var encrypted = encryption.encrypt(password, nonceValue);
			if(methodCalled=="delete"){
				post({uid: uidCalled,username:username,password:encrypted} ,"api/DeleteUser.php");
			}
			else if(methodCalled=="verify"){
				post({uid: uidCalled,username:username,password:encrypted} ,"api/VerifyUser.php");
			}
			else if(methodCalled=="block"){
				post({uid: uidCalled,username:username,password:encrypted} ,"api/BlockUser.php");
			}
		}
		
		function showPopup() {
            
			var name = document.getElementById('name').value;
            var type = document.getElementById('type').value;
			var latitude = document.getElementById('latitude').value;
            var longitude = document.getElementById('longitude').value;
			var id = document.getElementById('id').value;
            var demand = document.getElementById('demand').value;
            var district = document.getElementById('district').value;

            if (name === '' || type === '' || latitude === '' || longitude === '' || id === '' || demand === '' || district === '') {
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
