<?php
// File: admin_logout.php
// Logout untuk Admin dan Superadmin

session_start();
session_unset();
session_destroy();
header("Location: admin_login.php");
exit;
