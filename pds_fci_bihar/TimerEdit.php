<?php
require('util/Connection.php');
require('util/SessionCheck.php');
require('Header.php');
?>
<style>
    /* Ensure that the calendar is displayed correctly */
.dropdown-menu {
    top: 190px !important;
}

</style>

                <!-- START BREADCRUMB -->
                <ul class="breadcrumb">
                    <li><a href="#">Home</a></li>
                    <li class="active">Timer</li>
                </ul>
                <!-- END BREADCRUMB -->


				<!-- PAGE CONTENT WRAPPER -->
                 <div class="page-content-wrap">

                    <div class="row">
                        <div class="col-md-12">

                            <form action="api/TimerEdit.php" method="POST" class="form-horizontal" enctype = "multipart/form-data">
                            <div class="panel panel-default">
                               <div class="panel-body">
                                    <p>Fill this form to edit date and time.</p>
                                </div>

                             <div class="panel-body">

                                    <div class="row">

                                        <div class="col-md-6">
											
											<div class="form-group">
                                                <label class="col-md-3 control-label">Date*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
                                                        <span class="input-group-addon"><span class="fa fa-info"></span></span>
                                                        <input type="text" class="form-control datepicker" id="date" name="date" required />
                                                    </div>
                                                    <span class="help-block">Deadline date</span>
                                                </div>
                                            </div>
											
											
                                        </div>
                                        <div class="col-md-6">
										
											<div class="form-group">
                                                <label class="col-md-3 control-label">Time*</label>
                                                <div class="col-md-9">
                                                    <div class="input-group">
												   <span class="input-group-addon"><span class="fa fa-arrow-down"></span></span>
                                                    <select class="form-control" name="time" id="time" required>
													<option style="color:#000" value="">Select Time</option>
													<?php 
													$hour_initialize_starting = 0;
													for($hours=$hour_initialize_starting; $hours<24; $hours++){
														for($mins=0; $mins<60; $mins+=30){
															$time_variable_starting = str_pad($hours,2,'0',STR_PAD_LEFT).':'.str_pad($mins,2,'0',STR_PAD_LEFT);
															echo "<option value='$time_variable_starting' style='color:#000'>$time_variable_starting</option>";
														}
													}
													?>
													</select>
													</div>
                                                    <span class="help-block">District</span>
                                                </div>
                                            </div>
											
                                        </div>

                                    </div>

                                </div>
                               <div class="panel-footer">
                                    <button class="btn btn-primary pull-right" type="submit">Submit</button>
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
		<script type="text/javascript" src="js/plugins/jquery/jquery.min.js"></script>
        <script type="text/javascript" src="js/plugins/jquery/jquery-ui.min.js"></script>
        <script type="text/javascript" src="js/plugins/bootstrap/bootstrap.min.js"></script>        
        <!-- END PLUGINS -->                

        <!-- THIS PAGE PLUGINS -->
        <script type='text/javascript' src='js/plugins/icheck/icheck.min.js'></script>
        <script type="text/javascript" src="js/plugins/mcustomscrollbar/jquery.mCustomScrollbar.min.js"></script>
        <script type="text/javascript" src="js/plugins/datatables/jquery.dataTables.min.js"></script>  
		<script type="text/javascript" src="js/plugins/bootstrap/bootstrap-datepicker.js"></script>                
        <script type="text/javascript" src="js/plugins/bootstrap/bootstrap-file-input.js"></script>
        <script type="text/javascript" src="js/plugins/bootstrap/bootstrap-select.js"></script>
        <script type="text/javascript" src="js/plugins/tagsinput/jquery.tagsinput.min.js"></script>
		
		
        <script type="text/javascript" src="js/plugins.js"></script>
        <script type="text/javascript" src="js/actions.js"></script>
	
            
        
		

    </body>
</html>
