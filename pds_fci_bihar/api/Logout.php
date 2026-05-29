<?php

session_start();
$_SESSION['name'] = null;
$_SESSION['user'] = null;
header("Location:../Login.html");

?>