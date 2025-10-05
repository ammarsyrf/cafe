<?php
// File: logout_member.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// PERUBAHAN UTAMA: Hanya hapus data sesi 'member'
if (isset($_SESSION['member'])) {
    unset($_SESSION['member']);
}

// Redirect ke halaman utama dengan notifikasi sukses
header("Location: ../index.php?success=Berhasil+logout.+Selamat+datang+kembali!");
exit();
