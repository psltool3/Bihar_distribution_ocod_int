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

                <!-- START BREADCRUMB -->
                <ul class="breadcrumb">
                    <li><a href="#">Home</a></li>
                    <li class="active">Performa Leg1</li>
                </ul>
                <!-- END BREADCRUMB -->


				<!-- PAGE CONTENT WRAPPER -->
                <div class="page-content-wrap">

                    <div class="row">
                        <div class="col-md-12">

                            <!-- START SIMPLE DATATABLE -->
                            <div class="panel panel-default">
							<div class="panel-heading">
								<h3 class="panel-title">Performa Leg1</h3>
							</div>
								<div class="panel-body">
								
							</br></br>
							
                                 <div class="table-responsive">
									<form method="post" action="api/saveCostLeg1.php">
                                    <table id="export_table" class="table">
                                        <thead>
                                            <tr>
												<th style="font-size:16px">Year</th>
												<th style="font-size:16px">Month</th>
												<th style="font-size:16px">Applicable Month</th>
												<th style="font-size:16px">Allocation</th>
												<th style="font-size:16px">QKM</th>
												<th style="font-size:16px">Average Distance</th>
												<th style="font-size:16px">Cost</th>
												<th style="font-size:16px">Reset</th>
                                            </tr>
                                        </thead>
                                        <tbody>
										<?php
										$query_table = "SELECT * FROM optimised_table_leg1 WHERE 1";
										$result_table = mysqli_query($con, $query_table);
										while($row_table = mysqli_fetch_assoc($result_table)){
											$year = $row_table['year'];
											$month = $row_table['month'];
											$applicable = $row_table['applicable'];
											$id = $row_table['id'];
											$cost = $row_table['cost'];
											
											$allocation = 0;
											$qkm = 0;
											$qkm_optimised = 0;
											$averagedistance = 0;
											
											$query_fps = "SELECT SUM(storage) FROM fci_leg1_".$id;
											$result_fps = mysqli_query($con,$query_fps);
											$row_fps = mysqli_fetch_assoc($result_fps);
											
											$tablename = "optimiseddata_leg1_".$id;

											$query = "SELECT * FROM ".$tablename." WHERE 1";
											$result = mysqli_query($con,$query);
											$numrows = mysqli_num_rows($result);
											while($row = mysqli_fetch_assoc($result))
											{		
												$qkm_optimised = $qkm_optimised + (float)$row["quantity"] * (float)$row["distance"];
												if($row['new_id_admin']!=null or $row['new_id_admin']!=""){
													$row["distance"] = $row['new_distance_admin'];
												}
												else if(($row['new_id_district']!=null or $row['new_id_district']!="") and $row['approve_admin']=="yes"){
													$row["distance"] = $row['new_distance_district'];
												}		
												$allocation = $allocation + (float)$row["quantity"];
												$qkm = $qkm + (float)$row["quantity"] * (float)$row["distance"];
											}
											$averagedistanceoptimised = round($qkm_optimised/$allocation,2);
											$qkm = round($qkm,2);

											$reset = "<input class='btn btn-info btn-block' style='width:50%' onclick='resetFunction(\"".$id."\")' value='Reset'></input>";
											
											if($cost==null or $cost==""){
												$temp = "cost_".$id;
												$cost = "<input type='text' id='".$temp."' name='".$temp."' />";
												$reset = "";
											}											
											
											echo "<tr><td>".$year."</td><td>".$month."</td><td>".$applicable."</td><td>".$allocation."</td><td>".$qkm."</td><td>".$averagedistanceoptimised."</td><td>".$cost."</td><td>".$reset."</td></tr>";
									
											}
										?>
                                        </tbody>
                                    </table>
									<button class="btn btn-info btn-block pull-right" style="width:25%;margin-right:20px">Save</button>
									</form>
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
	
		function hidePopup() {
            document.getElementById('popup').style.display = 'none';
        }
		
		var dataString = "";
		$.ajax({
			type: "POST",
			url: "api/fetchTableData.php",
			data: dataString,
			cache: false,
			error: function(){
				alert("timeout");
			},
			timeout: 216000,
			success: function(result){
				try{
					var data = JSON.parse(result);
					var monthYearCombinations = data.map(item => `${item.month}_${item.year}`);
					var dropdown = document.getElementById("month");
					
					  // Clear existing options
					  dropdown.innerHTML = '';

					  // Add new options based on the array
					  monthYearCombinations.forEach(function(item) {
						var option = document.createElement("option");
						option.value = item;
						option.text = item;
						dropdown.add(option);
					  });
				}
				catch (error) {
				}
			}
		});

		function resetFunction($id){
			var dataString = { id: $id }; 
			$.ajax({
				type: "POST",
				url: "api/resetPerformaLeg1.php",
				data: dataString,
				cache: false,
				error: function(){
					alert("timeout");
				},
				success: function(result){
					location.reload();
				}
			});
			
		}
		

		</script>
    </body>
</html>
