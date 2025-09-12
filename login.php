<?php
// File: login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validasi input
if (empty($email) || empty($password)) {
    $response['message'] = 'Email dan password harus diisi.';
    echo json_encode($response);
    exit();
}

// Cari member berdasarkan email di tabel members
$sql = "SELECT id, name, password FROM members WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $member = $result->fetch_assoc();

    // Verifikasi password
    if (password_verify($password, $member['password'])) {
        // Jika berhasil, simpan data ke session
        // Nama session 'user_id' dan 'user_name' disesuaikan dengan yang sudah ada di index.php
        $_SESSION['user_id'] = $member['id'];
        $_SESSION['user_name'] = $member['name'];
        $_SESSION['role'] = 'member'; // Set role sebagai member

        $response['success'] = true;
        $response['message'] = 'Login berhasil!';
    } else {
        $response['message'] = 'Password salah.';
    }
} else {
    $response['message'] = 'Email tidak ditemukan.';
}

$stmt->close();
$conn->close();

echo json_encode($response);
