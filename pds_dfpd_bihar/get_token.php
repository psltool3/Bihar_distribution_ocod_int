<?php
// ini_set('session.cookie_lifetime', 86400); // 1 day
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Generate CSRF token
}
echo $_SESSION['csrf_token']; // Output the token
 ?>