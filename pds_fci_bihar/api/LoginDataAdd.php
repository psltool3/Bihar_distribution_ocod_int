<?php
require('../util/Connection.php');
require('../structures/Login.php');
require('../util/SessionFunction.php');
require ('../util/Encryption.php');
require('../util/Logger.php');

if(!SessionCheck()){
    return;
}

require('Header.php');
$nonceValue = 'nonce_value';

// Get the username and password from the POST data
$person = new Login;
$person->setUsername($_POST["username"]);
$person->setPassword($_POST["password"]);

// Check if the session user matches the submitted username
if($_SESSION['user']!=$person->getUsername()){
    echo "User is logged in with a different username and password";
    return;
}

// Validate password length
if (strlen($_POST["newpassword"]) < '5' || strlen($_POST["newusername"]) < '5') {
    echo "Username & Password must be at least 5 characters long";
    return;
}

$Encryption = new Encryption();
$person->setPassword($Encryption->decrypt($_POST["password"], $nonceValue));


$newusername = htmlspecialchars($_POST["newusername"], ENT_QUOTES, 'UTF-8');

// Ensure the new username doesn't contain special characters (optional)
if (!preg_match('/^[a-zA-Z0-9_@]+$/', $newusername)) {
    echo "Username can only contain letters, numbers, underscores and @.";
    return;
}

// Query the database to get the stored hash for the username
$query = "SELECT * FROM login WHERE username='".$person->getUsername()."'";
$result = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($result);

// Check if the username exists and verify the password using password_verify
if ($row) {
    if (password_verify($person->getPassword(), $row['password'])) {
        // Password is correct
        // Now proceed with other logic
        $person = new Login;
        $person->setUsername($_POST["newusername"]);
        $person->setPassword($_POST["newpassword"]);
        $person->setRole($_POST["district"]);
        $uid = uniqid();

        // Hash the new password before inserting it into the database
        $hashedPassword = password_hash($person->getPassword(), PASSWORD_DEFAULT);

        // Check if the new username already exists
        $query = "SELECT * FROM login WHERE username='".$person->getUsername()."'";
        $result = mysqli_query($con, $query);
        $numrows = mysqli_num_rows($result);

        if($numrows == 1){
            echo "Error : Username already exists";
        } else {
            // Insert the new user with the hashed password
            $query1 = "INSERT INTO login (username, password, uid, role, verified) 
                       VALUES ('".$person->getUsername()."', '".$hashedPassword."', '$uid', '".strtolower($person->getRole())."', '1')";
            mysqli_query($con, $query1);
            mysqli_close($con);
			$filteredPost = $_POST;
			unset($filteredPost['username'], $filteredPost['password']);
			writeLog("User ->" ." User Add ->". $_SESSION['user'] . "| Requested JSON -> " . json_encode($filteredPost). " | " . $person->getUsername());
            echo "<script>window.location.href = '../Userdata.php';</script>";
        }

    } else {
        // Password is incorrect
        echo "Error : Password is incorrect";
        return;
    }
} else {
    // Username doesn't exist
    echo "Error : Username does not exist";
    return;
}
?>
<?php require('Fullui.php'); ?>
