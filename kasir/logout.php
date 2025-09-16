<?php
// File: logout_kasir.php
session_start();

// PERUBAHAN UTAMA: Hanya hapus data sesi 'kasir'
if (isset($_SESSION['cashier'])) {
    unset($_SESSION['cashier']);
}

// Redirect ke halaman login kasir
header("Location: login_kasir.php");
exit();
