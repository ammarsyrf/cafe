<?php
// Hubungkan ke database
require_once '../db_connect.php';

// Ambil ID dari URL dan pastikan itu adalah angka
$menu_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($menu_id > 0) {
    // Siapkan statement DELETE untuk mencegah SQL Injection
    $sql = "DELETE FROM menu WHERE id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        // Bind parameter
        $stmt->bind_param("i", $menu_id);
        
        // Eksekusi statementt
        if ($stmt->execute()) {
            // Jika berhasil, redirect kembali ke halaman kelola menu
            header("Location: kelolamenu.php");
            exit();
        } else {
            // Jika gagal, tampilkan error (sebaiknya dibuat halaman error khusus)
            echo "Error: Gagal menghapus menu. " . $stmt->error;
        }
        
        // Tutup statement
        $stmt->close();
    } else {
        echo "Error: Gagal menyiapkan query. " . $conn->error;
    }
} else {
    // Jika ID tidak valid, redirect kembali
    header("Location: kelolamenu.php");
    exit();
}

// Tutup koneksi
$conn->close();
?>
