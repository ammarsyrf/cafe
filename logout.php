<?php
// File: logout.php

// Selalu mulai sesi untuk mengakses dan menghapusnya
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hapus semua variabel sesi
$_SESSION = array();

// Hancurkan sesi
session_destroy();

// Redirect ke halaman utama (index.php)
header("Location: index.php?message=Anda+telah+logout");
exit();
