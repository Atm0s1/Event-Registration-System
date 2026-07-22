<?php
session_start();

// If admin is logged in, go to dashboard
if (isset($_SESSION['admin_logged_in'])) {
    header('Location: admin/dashboard.php');
    exit;
}

// Otherwise, force admin login
header('Location: admin/login.php');
exit;
?>