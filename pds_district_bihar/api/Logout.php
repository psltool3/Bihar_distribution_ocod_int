<?php

session_start();
$_SESSION['district_name'] = null;
$_SESSION['district_user'] = null;
header("Location:../Login.html");

?>