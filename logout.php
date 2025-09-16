<?php
// File: logout_member.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// PERUBAHAN UTAMA: Hanya hapus data sesi 'member'
if (isset($_SESSION['member'])) {
    unset($_SESSION['member']);
}

// Redirect ke halaman utama (index.php)
header("Location: index.php?message=Anda+telah+logout");
exit();
