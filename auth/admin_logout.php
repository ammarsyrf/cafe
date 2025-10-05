<?php
// File: admin_logout.php
// Logout untuk Admin dan Superadmin

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hapus semua session admin
if (isset($_SESSION['admin_logged_in'])) {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_name']);
    unset($_SESSION['admin_email']);
    unset($_SESSION['admin_role']);
}

// Redirect ke halaman login dengan pesan sukses
header('Location: ../admin/index.php');
exit();
