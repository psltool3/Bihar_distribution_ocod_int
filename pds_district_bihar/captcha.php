<?php
session_start();
if (isset($_POST['captcha'])) {
    $_SESSION['captcha'] = $_POST['captcha'];
    echo "Stored: " . $_SESSION['captcha'];
}
?>
