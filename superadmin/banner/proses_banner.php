<?php
// File: proses_banner.php
// Deskripsi: File terpusat untuk menangani C-R-U-D banner.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../db_connect.php';

// --- FUNGSI UNTUK MENGUNGGAH GAMBAR ---
function upload_image($file)
{
    // Cek jika tidak ada file atau ada error
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Tidak ada gambar yang diunggah atau terjadi kesalahan.'];
    }

    $target_dir = "../uploads/banners/";
    // Buat direktori jika belum ada
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // Buat nama file unik untuk menghindari penimpaan
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $unique_name = uniqid('banner_', true) . '.' . $file_extension;
    $target_file = $target_dir . $unique_name;

    // Validasi tipe file
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'Hanya file JPG, JPEG, PNG, GIF, & WEBP yang diizinkan.'];
    }

    // Pindahkan file ke direktori tujuan
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        // Hapus '../' dari path untuk disimpan di DB
        $db_path = str_replace('../', '', $target_file);
        return ['success' => true, 'path' => $db_path];
    } else {
        return ['success' => false, 'message' => 'Gagal mengunggah gambar.'];
    }
}

// --- FUNGSI UNTUK MENGHAPUS GAMBAR ---
function delete_image($filepath)
{
    // Tambahkan kembali '../' untuk path file fisik
    $full_path = '../' . $filepath;
    if (file_exists($full_path)) {
        unlink($full_path);
    }
}

// Menentukan aksi berdasarkan parameter GET atau POST
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    // --- AKSI MEMBUAT BANNER BARU ---
    case 'create':
        $upload_result = upload_image($_FILES['image']);
        if (!$upload_result['success']) {
            $_SESSION['error_message'] = $upload_result['message'];
            header("Location: tambah_banner.php");
            exit();
        }

        $title = $_POST['title'];
        $subtitle = $_POST['subtitle'];
        $link_url = $_POST['link_url'];
        $order_number = (int)$_POST['order_number'];
        $is_active = (int)$_POST['is_active'];
        $image_path = $upload_result['path'];

        $sql = "INSERT INTO banners (title, subtitle, image_url, link_url, is_active, order_number) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssii", $title, $subtitle, $image_path, $link_url, $is_active, $order_number);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Banner baru berhasil ditambahkan.";
        } else {
            $_SESSION['error_message'] = "Gagal menambahkan banner: " . $stmt->error;
            delete_image($image_path); // Hapus gambar jika query DB gagal
        }
        header("Location: kelola_banner.php");
        break;

    // --- AKSI MEMPERBARUI BANNER ---
    case 'update':
        $id = (int)$_POST['id'];
        $old_image_path = $_POST['old_image_path'];
        $image_path = $old_image_path;

        // Cek jika ada gambar baru yang diunggah
        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
            $upload_result = upload_image($_FILES['image']);
            if ($upload_result['success']) {
                delete_image($old_image_path); // Hapus gambar lama
                $image_path = $upload_result['path']; // Gunakan path gambar baru
            } else {
                $_SESSION['error_message'] = $upload_result['message'];
                header("Location: edit_banner.php?id=$id");
                exit();
            }
        }

        $title = $_POST['title'];
        $subtitle = $_POST['subtitle'];
        $link_url = $_POST['link_url'];
        $order_number = (int)$_POST['order_number'];
        $is_active = (int)$_POST['is_active'];

        $sql = "UPDATE banners SET title = ?, subtitle = ?, image_url = ?, link_url = ?, is_active = ?, order_number = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssiii", $title, $subtitle, $image_path, $link_url, $is_active, $order_number, $id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Banner berhasil diperbarui.";
        } else {
            $_SESSION['error_message'] = "Gagal memperbarui banner: " . $stmt->error;
        }
        header("Location: kelola_banner.php");
        break;

    // --- AKSI MENGHAPUS BANNER ---
    case 'delete':
        $id = (int)$_GET['id'];

        // Ambil path gambar untuk dihapus dari server
        $sql_select = "SELECT image_url FROM banners WHERE id = ?";
        $stmt_select = $conn->prepare($sql_select);
        $stmt_select->bind_param("i", $id);
        $stmt_select->execute();
        $result = $stmt_select->get_result();
        if ($row = $result->fetch_assoc()) {
            delete_image($row['image_url']);
        }

        // Hapus record dari database
        $sql_delete = "DELETE FROM banners WHERE id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $id);

        if ($stmt_delete->execute()) {
            $_SESSION['success_message'] = "Banner berhasil dihapus.";
        } else {
            $_SESSION['error_message'] = "Gagal menghapus banner: " . $stmt_delete->error;
        }
        header("Location: kelola_banner.php");
        break;

    default:
        $_SESSION['error_message'] = "Aksi tidak valid.";
        header("Location: kelola_banner.php");
        break;
}

$conn->close();
exit();
