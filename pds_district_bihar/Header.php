<?php	

require("util/Connection.php");

$currentFile = basename($_SERVER["PHP_SELF"]);
$newMessage = 0;

$username = $_SESSION['district_user'];
$query = "SELECT uid FROM login WHERE username='$username'";
$result = mysqli_query($con,$query);
$row = mysqli_fetch_assoc($result);
$userid = $row['uid'];

$query = "SELECT * FROM user_message WHERE user_id='$userid' AND acknowledged='no'";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);
if($numrows>0){
	$newMessage = 1;
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
		<title>District</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<meta name="theme-color" content="#ffffff">
        <link rel="stylesheet" type="text/css" id="theme" href="css/theme-black.css"/>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    </head>
    <body>
        <!-- START PAGE CONTAINER -->
        <div class="page-container">

            <!-- START PAGE SIDEBAR -->
            <div class="page-sidebar scroll">
                <!-- START X-NAVIGATION -->
                <ul class="x-navigation">
                    <li class="xn-logo">
                        <a href="index.php">District Panel</a>
                        <a href="#" class="x-navigation-control"></a>
                    </li>
                    <li class="xn-profile">
                        <div class="profile">
                            <div class="profile-data">
                                
								
                                <div class="profile-data-name">
								<b>
									<img src="img/PngItem_1109026.png" alt="Logo" style="vertical-align: middle; height: 60px; width: 60px;" /> Namaste
								</b>
								</div>
                            </div>
                        </div>
                    </li>
					<li <?php if ($currentFile == 'Home.php') echo 'class="active"'; ?>>
						<a href="Home.php"> <span class="xn-text">Optimized Planning</span></a>
					</li>
					<li <?php if ($currentFile == 'RolloutPlan.php') echo 'class="active"'; ?>>
						<a href="RolloutPlan.php"> <span class="xn-text">Rollout Plan</span></a>
					</li>
					<li <?php if ($currentFile == 'DCP.php') echo 'class="active"'; ?>>
						<a href="DCP.php"> <span class="xn-text">DCP</span></a>
					</li>
					<li <?php if ($currentFile == 'Warehouse.php') echo 'class="active"'; ?>>
						<a href="Warehouse.php"> <span class="xn-text">Warehouse</span></a>
					</li>
					<li <?php if ($currentFile == 'FPS.php') echo 'class="active"'; ?>>
						<a href="FPS.php"> <span class="xn-text">FPS</span></a>
					</li>
					<li <?php if ($currentFile == 'Message.php') echo 'class="active"'; ?>>
						<?php if ($newMessage==0){ ?>
						<a href="Message.php"> <span class="xn-text">Message</span></a>
						<?php }else{ ?>
						<a href="Message.php"> <span class="xn-text">Message </span><img src="assets/images/new.gif" /></a>
						<?php } ?>
					</li>
					<li <?php if ($currentFile == 'api/Logout.php') echo 'class="active"'; ?>>
						<a href="api/Logout.php"> <span class="xn-text">Logout</span></a>
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
					<a href="#" class="x-navigation-minimize"><span class="fa fa-dedent"></span></a>
				</li>
				<!-- END TOGGLE NAVIGATION -->
			</ul>
			<!-- END X-NAVIGATION VERTICAL -->
			
			 <style>
				/* Styles for the popup */
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

        .page-sidebar.scroll * {
            font-family: sans-serif;
            font-weight: italic;
            font-size: 18px;
        }

        .x-navigation li a:hover,
        .page-sidebar.scroll a:hover {
            background-color: #FF5733;
            color: #fff;
            /* Define other hover properties as needed */
        }
		.x-navigation .xn-openable > a {
            background-color: #FF5733;
            color: #fff;
        }

        .x-navigation .xn-openable ul li a:hover {
            background-color: #9240FF; 
            color: #fff;
            padding-left: 20px; /* Modify padding on hover */
        }

        /* Gap between menu items */
        .x-navigation .xn-openable ul li {
            padding-bottom: 5px; /* Add some bottom padding to create a gap */
        }
		.red-bg-gap {
            background-color: red;
            padding: 10px; /* Adjust the padding as needed */
            margin-bottom: 10px; /* Create a gap below the list item */
        }
			</style>
