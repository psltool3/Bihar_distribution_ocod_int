<?php
require('util/Connection.php');
require('util/SessionCheck.php');
require('Header.php');
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
                    <li class="active">FPS Bulk Add</li>
                </ul>
                <!-- END BREADCRUMB -->


				<!-- PAGE CONTENT WRAPPER -->
                 <div class="page-content-wrap">

                    <div class="row">
                        <div class="col-md-12">
							<a href="api/BulkFPSDownload.php" style="float:right;margin-top:10px;margin-right:13px"><button type="button" class="btn btn-info">Download Template</button></a>
                            <form action="api/BulkFPSData.php" method="POST" class="form-horizontal" enctype = "multipart/form-data">
                            <div class="panel panel-default">
                               <div class="panel-body">
                                    <p>Download Template excel and upload the data.</p>
                                </div>

                             <div class="panel-body">

                                    <div class="row">

                                        <div class="col-md-6">
											<div class="form-group">
                                                <label class="col-md-3 control-label">Upload Template*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="file" class="form-control" name="file" id="file" accept=".csv">
                                                    </div>
                                                    <span class="help-block">Template File in CSV format</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
										
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
        <!-- END PAGE PLUGINS -->
		
		<script>
		document.getElementById('popup').style.display = 'none';
		function showPopup() {
            document.getElementById('popup').style.display = 'block';
        }
		function hidePopup() {
            document.getElementById('popup').style.display = 'none';
        }
		</script>

    </body>
</html>
