<?php
// File: kelola_kategori.php

// Selalu aktifkan error reporting saat development untuk melihat masalah
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set header ke JSON di awal, sebelum output apapun
header('Content-Type: application/json');

// Mulai session jika belum ada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Gunakan try-catch-finally block untuk penanganan error yang terpusat
try {
    require_once '../db_connect.php';

    // Periksa koneksi database
    if (!$conn || $conn->connect_error) {
        throw new Exception('Koneksi database gagal: ' . ($conn->connect_error ?? 'Unknown error'));
    }

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // Logika untuk Mengambil Data Kategori
            $sql = "SELECT c.id, c.name, 
                           (SELECT COUNT(*) FROM menu m WHERE m.category = c.name) as menu_count 
                    FROM menu_categories c 
                    ORDER BY c.name ASC";

            $result = $conn->query($sql);

            if ($result === false) {
                throw new Exception('Query SQL gagal: ' . $conn->error);
            }

            $categories = [];
            while ($row = $result->fetch_assoc()) {
                $row['is_in_use'] = $row['menu_count'] > 0;
                unset($row['menu_count']); // Hapus kolom bantu
                $categories[] = $row;
            }

            echo json_encode($categories);
            break;

        case 'POST':
            // Logika untuk Menambah Kategori Baru
            $data = json_decode(file_get_contents('php://input'), true);
            $name = isset($data['name']) ? trim($data['name']) : '';

            if (empty($name)) {
                throw new Exception('Nama kategori tidak boleh kosong.');
            }

            // Cek duplikasi (case-insensitive)
            $stmt = $conn->prepare("SELECT id FROM menu_categories WHERE LOWER(name) = LOWER(?)");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->close();
                throw new Exception('Nama kategori sudah ada.');
            }
            $stmt->close();

            // Masukkan data baru
            $stmt = $conn->prepare("INSERT INTO menu_categories (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Kategori berhasil ditambahkan.']);
            } else {
                throw new Exception('Gagal menyimpan ke database.');
            }
            $stmt->close();
            break;

        case 'DELETE':
            // Logika untuk Menghapus Kategori
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

            if ($id <= 0) {
                throw new Exception('ID kategori tidak valid.');
            }

            // Ambil nama kategori untuk pengecekan
            $stmt = $conn->prepare("SELECT name FROM menu_categories WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $stmt->close();
                throw new Exception('Kategori tidak ditemukan.');
            }
            $category_name = $result->fetch_assoc()['name'];
            $stmt->close();

            // Cek apakah kategori sedang digunakan
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM menu WHERE category = ?");
            $stmt->bind_param("s", $category_name);
            $stmt->execute();
            $is_in_use = $stmt->get_result()->fetch_assoc()['count'] > 0;
            $stmt->close();

            if ($is_in_use) {
                throw new Exception('Kategori tidak dapat dihapus karena masih digunakan oleh menu.');
            }

            // Hapus kategori jika tidak digunakan
            $stmt = $conn->prepare("DELETE FROM menu_categories WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Kategori berhasil dihapus.']);
            } else {
                throw new Exception('Gagal menghapus kategori dari database.');
            }
            $stmt->close();
            break;

        default:
            // Jika metode request tidak didukung
            http_response_code(405); // Method Not Allowed
            throw new Exception('Metode request tidak didukung.');
    }
} catch (Exception $e) {
    // Blok ini akan menangkap semua error dan memastikan outputnya selalu JSON
    http_response_code(isset($e->getCode) && $e->getCode() >= 400 ? $e->getCode() : 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // Pastikan koneksi ditutup
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
