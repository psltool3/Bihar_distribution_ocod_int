<?php
// Start session
session_start();

// Database connection
$servername = "localhost";
$username = "username";
$password = "password";
$dbname = "your_database";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Login function
function login($username, $password, $conn) {
    // Sanitize input
    $username = mysqli_real_escape_string($conn, $username);
    $password = mysqli_real_escape_string($conn, $password);

    // Query to fetch user from database
    $sql = "SELECT * FROM users WHERE username='$username'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        // Verify password
        if (password_verify($password, $row['password'])) {
            // Check if user has an active session
            $user_id = $row['id'];
            $device_info = $_SERVER['HTTP_USER_AGENT']; // Get device info
            $sql_session = "SELECT * FROM sessions WHERE user_id='$user_id' AND is_active=1";
            $result_session = $conn->query($sql_session);
            if ($result_session->num_rows > 0) {
                // User already has an active session, deny login
                return "User already logged in from another device.";
            } else {
                // Create new session
                $session_id = session_id();
                $current_time = time();
                $expiry_time = $current_time + 3600; // Session expiry time (1 hour)
                $sql_insert_session = "INSERT INTO sessions (user_id, session_id, device_info, expiry_time, is_active) VALUES ('$user_id', '$session_id', '$device_info', '$expiry_time', 1)";
                if ($conn->query($sql_insert_session) === TRUE) {
                    return "Login successful!";
                } else {
                    return "Error creating session: " . $conn->error;
                }
            }
        } else {
            return "Invalid password.";
        }
    } else {
        return "User not found.";
    }
}

// Logout function
function logout($conn) {
    // Delete session from database
    $session_id = session_id();
    $sql_delete_session = "DELETE FROM sessions WHERE session_id='$session_id'";
    if ($conn->query($sql_delete_session) === TRUE) {
        // Destroy session
        session_unset();
        session_destroy();
        return "Logout successful!";
    } else {
        return "Error deleting session: " . $conn->error;
    }
}

// Expire sessions function
function expireSessions($conn) {
    // Current time
    $current_time = time();
    // Query to delete expired sessions
    $sql_delete_expired_sessions = "DELETE FROM sessions WHERE expiry_time <= '$current_time'";
    if ($conn->query($sql_delete_expired_sessions) === TRUE) {
        return "Expired sessions deleted!";
    } else {
        return "Error deleting expired sessions: " . $conn->error;
    }
}

// Example usage
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    echo login($username, $password, $conn);
} elseif (isset($_POST['logout'])) {
    echo logout($conn);
}

// Check and expire sessions every hour (you can adjust the interval as needed)
if (time() % 3600 == 0) {
    echo expireSessions($conn);
}
?>
