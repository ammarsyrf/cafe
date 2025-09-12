<?php
// File: db_connect.php
// Konfigurasi koneksi ke database MySQL

$servername = "localhost";
$username = "root"; // Ganti dengan username database Anda
$password = ""; // Ganti dengan password database Anda
$dbname = "db_cafe"; // Ganti dengan nama database Anda

// Buat koneksi
$conn = new mysqli($servername, $username, $password, $dbname);

// Periksa koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Cek jika session belum aktif, baru jalankan session_start()
// Ini akan memperbaiki notifikasi pemanggilan ganda.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
