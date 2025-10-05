<?php
header('Content-Type: application/json');

// Pastikan path koneksi database benar
require_once '../../app/config/db_connect.php';

// Membaca data JSON yang dikirim dari client
$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['order']) && is_array($input['order'])) {
    $bannerOrder = $input['order'];

    // Mulai transaksi untuk memastikan integritas data
    $conn->begin_transaction();

    try {
        // Siapkan statement SQL untuk update
        // Menggunakan prepared statement untuk keamanan
        $stmt = $conn->prepare("UPDATE banners SET order_number = ? WHERE id = ?");

        if (!$stmt) {
            throw new Exception("Gagal mempersiapkan statement: " . $conn->error);
        }

        // Loop melalui setiap banner dan update urutannya
        foreach ($bannerOrder as $index => $bannerId) {
            $newOrder = $index + 1; // Urutan dimulai dari 1
            $bannerId = (int)$bannerId; // Pastikan ID adalah integer

            $stmt->bind_param("ii", $newOrder, $bannerId);
            if (!$stmt->execute()) {
                throw new Exception("Gagal mengeksekusi statement untuk ID " . $bannerId . ": " . $stmt->error);
            }
        }

        $stmt->close();

        // Jika semua berhasil, commit transaksi
        $conn->commit();

        echo json_encode(['status' => 'success', 'message' => 'Urutan banner berhasil diperbarui.']);
    } catch (Exception $e) {
        // Jika terjadi error, rollback transaksi
        $conn->rollback();

        http_response_code(500); // Server Error
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Data urutan tidak valid.']);
}

$conn->close();
