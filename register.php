<?php
// File: register.php
require_once 'db_connect.php';

// Set header untuk respons JSON
header('Content-Type: application/json');

// Inisialisasi respons
$response = ['success' => false, 'message' => 'Terjadi kesalahan.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metode permintaan tidak valid.';
    echo json_encode($response);
    exit();
}

// Ambil data dari form
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$phone_number = trim($_POST['phone_number'] ?? '');

// Validasi input
if (empty($name) || empty($email) || empty($password)) {
    $response['message'] = 'Nama, email, dan password harus diisi.';
    echo json_encode($response);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Format email tidak valid.';
    echo json_encode($response);
    exit();
}

if (strlen($password) < 6) {
    $response['message'] = 'Password minimal harus 6 karakter.';
    echo json_encode($response);
    exit();
}

// Validasi nomor telepon jika diisi
if (!empty($phone_number)) {
    // Hanya boleh berisi angka dan karakter + - ( ) dan spasi
    if (!preg_match('/^[0-9+\-\(\)\s]+$/', $phone_number)) {
        $response['message'] = 'Format nomor telepon tidak valid.';
        echo json_encode($response);
        exit();
    }

    // Minimal 10 karakter untuk nomor telepon Indonesia
    if (strlen(preg_replace('/[^0-9]/', '', $phone_number)) < 10) {
        $response['message'] = 'Nomor telepon minimal 10 digit.';
        echo json_encode($response);
        exit();
    }
}

// Cek apakah email sudah terdaftar di tabel members
$sql_check = "SELECT id FROM members WHERE email = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("s", $email);
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows > 0) {
    $response['message'] = 'Email ini sudah terdaftar. Silakan gunakan email lain.';
    $stmt_check->close();
    echo json_encode($response);
    exit();
}
$stmt_check->close();

// Hash password untuk keamanan
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Masukkan data member baru ke database
$sql_insert = "INSERT INTO members (name, email, phone_number, password) VALUES (?, ?, ?, ?)";
$stmt_insert = $conn->prepare($sql_insert);
$stmt_insert->bind_param("ssss", $name, $email, $phone_number, $hashed_password);

if ($stmt_insert->execute()) {
    $response['success'] = true;
    $response['message'] = 'Pendaftaran berhasil! Silakan login.';
} else {
    // Sebaiknya log error ini untuk debugging: error_log($stmt_insert->error);
    $response['message'] = 'Pendaftaran gagal. Silakan coba lagi.';
}

$stmt_insert->close();
$conn->close();

echo json_encode($response);
