<?php
require('../util/Connection.php');
require('../structures/Login.php');
require('../util/Security.php');
require ('../util/Encryption.php');
$nonceValue = 'nonce_value';
session_start();

if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    $_POST['csrf_token'] !== $_SESSION['csrf_token']
) {
    die("Something went wrong. Request denied.");
}

if (
    !isset($_POST['captchainput'], $_SESSION['captcha']) ||
    $_SESSION['captcha'] !== $_POST['captchainput']
) {
    die("Please check captcha.");
}


$person = new Login;
$person->setUsername($_POST["username"]);
$Encryption = new Encryption();
$person->setPassword($Encryption->decrypt($_POST["password"], $nonceValue));

$query = "SELECT * FROM login WHERE username='".$person->getUsername()."'";
$result = mysqli_query($con,$query);
$row = mysqli_fetch_assoc($result);

if(empty($row)){
    die("Password or Username is incorrect");
}

$dbHashedPassword = $row['password'];
if(password_verify($person->getPassword(), $dbHashedPassword)){
 if($row['role']=="fci"){
		$count = 1 + $row['count'];
		$uniqueId = uniqid();
		$authToken = md5($uniqueId);
		$currentLoginTime = date("Y-m-d H:i:s");
		$queryUpdate = "UPDATE login SET token='$authToken',lastlogin='$currentLoginTime',count='$count' WHERE username='".$person->getUsername()."'";
		mysqli_query($con,$queryUpdate);
		
		$_SESSION['user'] = $person->getUsername();
		$_SESSION['token'] = $authToken;
		
		mysqli_close($con);
		 echo "<script>window.location.href = '../DCP.php';</script>";
    }else {
            // ❌ NOT FCI → ERROR MESSAGE
            echo "Only FCI users are allowed to login.";
        }
} 
else{
    echo "Error : Password or Username is incorrect";
}

?>
<?php require('Fullui.php');  ?>