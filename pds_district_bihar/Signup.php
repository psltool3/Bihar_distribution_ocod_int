<!DOCTYPE html>
<html lang="en" class="body-full-height">
    <head>        
        <!-- META SECTION -->
        <title>SDG Admin</title>            
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        
		<meta name="theme-color" content="#ffffff">
        <!-- END META SECTION -->
        
        <!-- CSS INCLUDE -->        
        <link rel="stylesheet" type="text/css" id="theme" href="css/theme-default.css"/>
        <!-- EOF CSS INCLUDE -->
		<script>
		var captcha;
		function generateCaptcha() {
			document.getElementById("captchainput").value = "";
			captcha = document.getElementById("image");
			var uniquechar = "";
			const randomchar = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
			for (let i = 1; i < 5; i++) {
				uniquechar += randomchar.charAt(Math.random() * randomchar.length)
			}
			captcha.innerHTML = uniquechar;
		}
		 
		function verifyCaptcha() {
			const usr_input = document.getElementById("captchainput").value;
			if (usr_input == captcha.innerHTML) {
				return true;
			}
			else {
				alert("Incorrect Captcha");
				generateCaptcha();
				return false;
			}
		}
		</script>
		<style>
		#image{
			box-shadow: 2px 2px 2px 2px gray;
			width: 80px;
			font-weight: 600;
			height: 60px;
			color:#FFF;
			user-select: none;
			text-decoration:line-through;
			font-style: italic;
			font-size: x-large;
			border: #2798D5 2px solid;
			padding: 10px;
		}
		</style>
    </head>
    <body>
        
        <div class="login-container">
        
            <div class="login-box animated fadeInDown">
			</br></br>
                <div class="login-body">
                    <div class="login-title"><strong>Welcome</strong>, Create Account</div>
                    <form action="api/Signup.php" class="form-horizontal" method="post">
					<div class="form-group">
                        <div class="col-md-12">
                            <input type="text" id="username" name="username" class="form-control" placeholder="Username"/>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-md-12">
                            <input type="password" id="password" name="password" class="form-control" placeholder="Password"/>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-md-12">
                            <input type="password" id="confirmpassword" name="confirmpassword" class="form-control" placeholder="Confirm Password"/>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-md-12">
							<select class="form-control select" style="color: #000; background-color: #fff; text-shadow: none;" id="district" name="district">
							</select>
							<span class="help-block">Select District</span>
						</div>
                    </div>
					<div class="form-group">
                        <div class="col-md-12">
                            <input type="text" id="captchainput" name="captchainput" class="form-control" placeholder="Captcha Code" required />
                        </div>
                    </div>
					
					<div class="form-group">
                        <div class="row">
							<div class="col-md-8">
								<center>
									<div id="image" selectable="False"></div>
								</center>
							</div>
							 <div class="col-md-4">
								<center>
									<span class="fa fa-refresh" style="font-size:30px;color:white;margin-top:20px" onclick="generateCaptcha()"></span>
								</center>
							</div>
						</div>
                    </div>
                    <div class="form-group">
					<center>
                            <button class="btn btn-info btn-block" style="width:50%">Create Account</button>
					</center>
                    </div>
                    </form>
                </div>
                <div class="login-footer">
                    <div class="pull-right">
                        <a href="Login.html">Login</a>
                    </div>
                </div>
            </div>
            
        </div>
        <script>
			generateCaptcha();
		</script>
		
		<?php require('DistrictAutocomplete.php');  ?>
    </body>
</html>






