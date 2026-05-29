<?php
require('util/Connection.php');
require('util/SessionCheck.php');
require('Header.php');

$uid = $_POST['uid'];
$query = "SELECT * FROM Districts WHERE id='$uid'";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);
 
if($numrows!=0)
{
	while($row = mysqli_fetch_array($result))   
    {
	 $name = $row['name'];
    }
}


?>
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
                    <li><a href="#">Home</a></li>                    
                    <li class="active">District Edit</li>
                </ul>
                <!-- END BREADCRUMB -->                       
                
				
				<!-- PAGE CONTENT WRAPPER -->
                 <div class="page-content-wrap">
                
                    <div class="row">
                        <div class="col-md-12">
                            
                            <form action="api/DistrictEdit.php" method="POST" class="form-horizontal">
                            <div class="panel panel-default">
                               <div class="panel-body">
                                    <p>Fill this form to Edit District Name.</p>
                                </div>
               			      
                             <div class="panel-body">                                                                        
                                    
                                    <div class="row">
                                        
                                        <div class="col-md-6">
                                            
                                            <div class="form-group">
                                                <label class="col-md-3 control-label">Name*</label>
                                                <div class="col-md-9">                                            
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo $name; ?>"  required />
                                                    </div>                                            
                                                    <span class="help-block">District Name</span>
                                                </div>
                                            </div>
																			
                                            
                                        </div>
                                        <div class="col-md-6">
                                            
											 
											<input type="hidden" value="'<?php echo $uid ?>'"  name="uid" id="uid">
                                            
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
		
		
        <script type="text/javascript" src="js/plugins/bootstrap/bootstrap-datepicker.js"></script>                
        <script type="text/javascript" src="js/plugins/bootstrap/bootstrap-file-input.js"></script>
        <script type="text/javascript" src="js/plugins/bootstrap/bootstrap-select.js"></script>
        <script type="text/javascript" src="js/plugins/tagsinput/jquery.tagsinput.min.js"></script>
        <script type="text/javascript" src="js/plugins.js"></script>
        <script type="text/javascript" src="js/actions.js"></script>
        <!-- END PAGE PLUGINS -->

        
        <!-- START TEMPLATE -->      
       

		<script type="text/javascript" src="js/jquery.autocomplete.min.js"></script>
		<!-- END TEMPLATE -->
		
		<script>
		function showPopup() {
            
			var name = document.getElementById('name').value;

            if (name === '') {
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
