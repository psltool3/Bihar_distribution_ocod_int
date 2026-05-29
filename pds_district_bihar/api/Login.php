<?php
require('../util/Connection.php');
require('../structures/Login.php');
require('../util/Encryption.php');
require('../util/Security.php');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if(empty($_POST) || empty($_SESSION)){
    die("Something went wrong");
}


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
$nonceValue = 'nonce_value';

$Encryption = new Encryption();
$person->setPassword($Encryption->decrypt($_POST["password"], $nonceValue));

$query = "SELECT * FROM login WHERE username='".$person->getUsername()."'";
$result = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($result);

if (empty($row)) {
	die("Password or Username is incorrect");
}

if ($row['role'] == 'admin') {
		echo "Error: Admins are not allowed to log in here.";
		exit;
}

if ($row["verified"] == 0) {
		echo "Error: Your account needs to be verified. Please contact admin.";
		exit;
}

$dbHashedPassword = $row['password'];
if(password_verify($person->getPassword(), $dbHashedPassword)){
    session_regenerate_id(true);
	$count = 1 + $row['count'];
	$uniqueId = uniqid();
	$authToken = md5($uniqueId);
	$currentLoginTime = date("Y-m-d H:i:s");
	
	$queryUpdate = "UPDATE login SET token='$authToken', lastlogin='$currentLoginTime', count='$count' WHERE username='".$person->getUsername()."'";
	mysqli_query($con, $queryUpdate);

	$_SESSION['district_user'] = $person->getUsername();
	$_SESSION['district_password'] = $person->getPassword();
	$_SESSION['district_district'] = $row["role"];
	$_SESSION['district_token'] = $authToken;

	// Close the database connection
	mysqli_close($con);
	echo "<script>window.location.href = '../Home.php';</script>";
} 
else{
    echo "Error : Password or Username is incorrect";
}

?>
<?php require('Fullui.php');  ?>