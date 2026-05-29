<?php

require('../util/Connection.php');
require('../structures/District.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');
require('../util/Security.php');
require('../util/Logger.php');
require ('../util/Encryption.php');
$nonceValue = 'nonce_value';

if(!SessionCheck()){
	return;
}

require('Header.php');


function formatName($name) {
    if(preg_match('/[^a-zA-Z\s]/', $name)){
        echo "Error : Name contains invalid characters. Only letters and spaces are allowed.";
		exit();
    }
    $name = ucwords(strtolower($name));
    return trim($name);
}

$person = new Login;
$person->setUsername($_POST["username"]);
// $person->setPassword($_POST["password"]);
$Encryption = new Encryption();
$person->setPassword($Encryption->decrypt($_POST["password"], $nonceValue));

if($_SESSION['user']!=$person->getUsername()){
	echo "User is logged in with different username and password";
	return;
}

$query = "SELECT * FROM login WHERE username='".$person->getUsername()."'";
$result = mysqli_query($con,$query);
$row = mysqli_fetch_assoc($result);

$dbHashedPassword = $row['password'];
if(password_verify($person->getPassword(), $dbHashedPassword)){
	$District = new District;
	$District->setId(uniqid());
	$District->setName(formatName($_POST['name']));

	$query = $District->check($District);
	$result = mysqli_query($con, $query);
	$numrows = mysqli_num_rows($result);
	if($numrows>0){
		echo "Error : District name already exist";
		exit();
	}
	$query = $District->insert($District);
	mysqli_query($con, $query);
	mysqli_close($con);

	$filteredPost = $_POST;
	unset($filteredPost['username'], $filteredPost['password']);
	writeLog("User ->" ." District added ->". $_SESSION['user'] . "| Requested JSON -> " . json_encode($filteredPost));

	echo "<script>window.location.href = '../District.php';</script>";
}else{
	echo "Password or Username is incorrect" ;
}
?>
<?php require('Fullui.php');  ?>