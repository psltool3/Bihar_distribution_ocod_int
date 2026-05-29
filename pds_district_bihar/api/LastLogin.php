<?php
require('../util/Connection.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');

// Check if the user is logged in
if (!SessionCheck()) {
    echo json_encode(["error" => "User not logged in"]);
    exit;
}

// Get the current user (assuming username is stored in the session)
$current_user = $_SESSION['user']; // Replace 'username' with the correct session variable


// Query to fetch lastlogin for the logged-in user
$query = "SELECT lastlogin FROM login WHERE username = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("s", $current_user); // 's' for string
$stmt->execute();
$result = $stmt->get_result();

$response = array();

// Fetch the result
if ($row = $result->fetch_assoc()) {
    $response["lastlogin"] = $row["lastlogin"];
} else {
    $response["lastlogin"] = null; // User not found or no lastlogin recorded
}

// Return the data as JSON
echo json_encode($response);

// Close connection
$stmt->close();
$con->close();
?>
