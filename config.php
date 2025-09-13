<?php
// File: config.php
// File ini untuk menyimpan konfigurasi dasar website Anda.

// --- PENGATURAN URL DASAR ---
// PENTING: Sesuaikan URL ini dengan alamat folder proyek Anda di localhost.
// Pastikan ada tanda garis miring '/' di bagian akhir.
define('BASE_URL', 'http://localhost/cafe/');


// --- PENGATURAN KONEKSI DATABASE ---
$db_host = 'localhost'; // Biasanya 'localhost'
$db_name = 'db_cafe';   // Ganti dengan nama database Anda
$db_user = 'root';      // User database, default XAMPP adalah 'root'
$db_pass = '';          // Password database, default XAMPP kosong

// Opsi untuk koneksi PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Menampilkan error jika terjadi masalah SQL
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Mengembalikan hasil query sebagai array asosiatif
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Menggunakan native prepared statements dari database
];

try {
    // Membuat objek koneksi PDO baru dan menyimpannya di variabel $pdo
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Jika koneksi gagal, hentikan eksekusi script dan tampilkan pesan error yang jelas.
    die("Koneksi ke database gagal: " . $e->getMessage());
}
