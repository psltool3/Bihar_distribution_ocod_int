<?php		
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
		<title>PDS Admin</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<meta name="theme-color" content="#ffffff">
        <link rel="stylesheet" type="text/css" id="theme" href="css/theme-black.css"/>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
		<script> var pythonUrl = "https://bsfcsmartroutes.in/api/"; var directory = "" </script>
						
		<style>
		.popup {
			display: none;
			position: fixed;
			top: 50%;
			left: 50%;
			transform: translate(-50%, -50%);
			padding: 20px;
			background-color: #fff;
			box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
			z-index: 1000;
			font-family: sans-serif;
		}
		.x-navigation li a:hover,
		.page-sidebar.scroll a:hover {
			background-color: #EA743E;
			color: #fff;
		}
		.x-navigation .xn-openable > a {
			background-color: #FF5733;
			color: #fff;
		}
		.x-navigation .xn-openable ul li a:hover {
			background-color: #9240FF; 
			color: #fff;
			padding-left: 20px;
		}
		.x-navigation .xn-openable ul li {
			padding-bottom: 5px;
		}
		.red-bg-gap {
			background-color: red;
			padding: 10px;
			margin-bottom: 10px;
		}
		</style>

    </head>
    <body>
        <!-- START PAGE CONTAINER -->
		<div class="page-container">
        <!-- START PAGE SIDEBAR -->
        <div class="page-sidebar">
            <!-- START X-NAVIGATION -->
			<ul class="x-navigation">
				<li class="xn-logo">
					<span style="font-size: 19px; text-align: center; margin: 10px;color:#fff">Bihar State Level</span>
					<a href="#" class="x-navigation-control"></a>
				</li>
				<li class="xn-profile">
					<div class="profile">
						<div class="profile-data">
							<div class="profile-data-name">
								<!-- <b>Namaste</b> -->
								<b>
									<img src="img/PngItem_1109026.png" alt="Logo" style="vertical-align: middle; height: 60px; width: 60px;" /> Namaste
								</b>
							</div>
						</div>
					</div>
				</li>
				
				
				
				<li>
					<a style="background-color:#FFC167"><center><span class="xn-text" style="font-family:sans-serif;font-weight:italic;font-size:16px;">Edit PDS Details</span></center></a>
				</li>
				
				<li>
					<a href="DCP.php" style="padding-left:0px"><span class="xn-text" style="font-family:sans-serif;font-weight:italic;font-size:16px;">Edit FCI</span></a>
				</li>

				<li <?php echo ($current_page === 'api/Logout.php') ? 'class="active"' : ''; ?> style="margin-bottom: 30px;">
					<a href="api/Logout.php" style="padding-left:0px"><span class="xn-text" style="font-family:sans-serif;font-weight:italic;font-size:16px;">Logout</span></a>
				</li>
				
			</ul>
				<!-- END X-NAVIGATION -->
        </div>
        <!-- END PAGE SIDEBAR -->

        <!-- PAGE CONTENT -->
        <div class="page-content">
            <!-- START X-NAVIGATION VERTICAL -->
            <ul class="x-navigation x-navigation-horizontal x-navigation-panel">
                <!-- TOGGLE NAVIGATION -->
                <li class="xn-icon-button">
                    <a href="#" class="x-navigation-minimize"><i class="fas fa-bars"></i></a>
                </li>
				<!-- END TOGGLE NAVIGATION -->
			</ul>
			<!-- END X-NAVIGATION VERTICAL -->
			
