<?php
// File: db_connect.php

// --- PENGATURAN KONEKSI DATABASE ---
// Sesuaikan dengan konfigurasi database Anda
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'db_cafe'; // <-- Ganti dengan nama database Anda

// --- BUAT KONEKSI ---
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi Gagal: " . $conn->connect_error);
}

// Set character set
$conn->set_charset("utf8mb4");


// --- MEMUAT PENGATURAN APLIKASI DARI DATABASE ---
$APP_CONFIG = [];
$sql_settings = "SELECT setting_name, setting_value FROM settings";
$result_settings = $conn->query($sql_settings);

if ($result_settings) {
    while ($row = $result_settings->fetch_assoc()) {
        $APP_CONFIG[$row['setting_name']] = $row['setting_value'];
    }
} else {
    // Handle jika query gagal (opsional, bisa di-log)
    error_log("Gagal memuat pengaturan dari database.");
}

// --- FUNGSI GLOBAL (jika ada) ---
// ... Anda bisa menambahkan fungsi-fungsi yang sering digunakan di sini ...


// --- MEMUAT PENGATURAN APLIKASI DARI DATABASE ---
$APP_CONFIG = [];
$sql_settings = "SELECT setting_name, setting_value FROM settings";
$result_settings = $conn->query($sql_settings);

if ($result_settings) {
    while ($row = $result_settings->fetch_assoc()) {
        $APP_CONFIG[$row['setting_name']] = $row['setting_value'];
    }
} else {
    // Handle jika query gagal (opsional, bisa di-log)
    error_log("Gagal memuat pengaturan dari database.");
}

// --- FUNGSI GLOBAL (jika ada) ---
// ... Anda bisa menambahkan fungsi-fungsi yang sering digunakan di sini ...
