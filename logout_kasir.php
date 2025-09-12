<?php
// File: logout.php
session_start();

// Hapus semua variabel session
$_SESSION = array();

// Hancurkan session
session_destroy();

// Redirect ke halaman login
header("Location: login_kasir.php");
exit();
