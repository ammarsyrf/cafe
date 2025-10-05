<?php
// File: admin/pelanggan/edit_pelanggan.php

// Mulai session di baris paling atas
session_start();

// Panggil koneksi database terlebih dahulu
require_once '../../app/config/db_connect.php';

$error_message = '';
$user_id = $_GET['id'] ?? null;
$user = null;

// Alihkan jika tidak ada ID di URL
if (!$user_id) {
    header("Location: pelanggan.php");
    exit();
}

// Proses form jika metode POST (untuk update data)
// Blok ini harus dieksekusi sebelum output HTML apapun
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']);
    $password = $_POST['password'];
    $current_id = $_POST['user_id'];

    // Validasi dasar
    if (empty($username) || empty($email)) {
        $error_message = "Nama dan email tidak boleh kosong.";
    } else {
        // Cek duplikasi email/username, kecuali untuk pengguna ini sendiri
        $sql_check = "SELECT id FROM members WHERE (name = ? OR email = ?) AND id != ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ssi", $username, $email, $current_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $error_message = "Username atau email sudah digunakan oleh member lain.";
        } else {
            // Bangun query untuk update
            if (!empty($password)) {
                // Jika password baru diisi, hash dan update password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql_update = "UPDATE members SET name = ?, email = ?, phone_number = ?, password = ? WHERE id = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("ssssi", $username, $email, $phone_number, $hashed_password, $current_id);
            } else {
                // Jika password kosong, update data tanpa mengubah password
                $sql_update = "UPDATE members SET name = ?, email = ?, phone_number = ? WHERE id = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("sssi", $username, $email, $phone_number, $current_id);
            }

            // Eksekusi update
            if ($stmt_update->execute()) {
                // Redirect jika berhasil
                header("Location: pelanggan.php?status=updated");
                exit();
            } else {
                $error_message = "Gagal memperbarui data member: " . $stmt_update->error;
            }
            $stmt_update->close();
        }
        $stmt_check->close();
    }
}

// Ambil data member saat ini untuk ditampilkan di form (setelah pemrosesan POST)
$sql_get = "SELECT id, name, email, phone_number FROM members WHERE id = ?";
if ($stmt_get = $conn->prepare($sql_get)) {
    $stmt_get->bind_param("i", $user_id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
    } else {
        // Set error message jika user tidak ditemukan
        $error_message = "Member tidak ditemukan.";
    }
    $stmt_get->close();
}

// PERBAIKAN: Panggil header.php SETELAH semua logika PHP selesai
require_once '../includes/header.php';
?>

<div class="container mx-auto">
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center mb-6">
            <a href="pelanggan.php" title="Kembali" class="text-gray-500 hover:text-blue-600 transition duration-300 text-2xl p-2 rounded-full hover:bg-gray-100 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Edit Member</h1>
        </div>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
                <p class="font-bold">Terjadi Kesalahan</p>
                <p><?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($user): ?>
            <div class="bg-white rounded-xl shadow-lg p-8">
                <form action="edit_pelanggan.php?id=<?= $user['id'] ?>" method="POST" class="space-y-6">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">

                    <!-- Nama Member -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Nama Member</label>
                        <div class="relative rounded-md shadow-sm">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input type="text" name="username" id="username" class="block w-full rounded-md border-gray-300 pl-10 p-3" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <div class="relative rounded-md shadow-sm">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <i class="fas fa-envelope text-gray-400"></i>
                            </div>
                            <input type="email" name="email" id="email" class="block w-full rounded-md border-gray-300 pl-10 p-3" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                    </div>

                    <!-- Nomor Telepon -->
                    <div>
                        <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1">Nomor Telepon</label>
                        <div class="relative rounded-md shadow-sm">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <i class="fas fa-phone text-gray-400"></i>
                            </div>
                            <input type="text" name="phone_number" id="phone_number" class="block w-full rounded-md border-gray-300 pl-10 p-3" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Password Baru -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password Baru (Opsional)</label>
                        <div class="relative rounded-md shadow-sm">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" name="password" id="password" class="block w-full rounded-md border-gray-300 pl-10 p-3" placeholder="Kosongkan jika tidak ingin mengubah">
                        </div>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="pt-4 flex justify-between items-center">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
                            <i class="fas fa-save mr-2"></i> Simpan Perubahan
                        </button>
                        <a href="hapus_pelanggan.php?id=<?= $user['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus member ini? Tindakan ini tidak dapat dibatalkan.')" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
                            <i class="fas fa-trash mr-2"></i> Hapus
                        </a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-6 rounded-md text-center">
                <p class="font-bold">Data Member Tidak Ditemukan</p>
                <p class="mt-2">Member yang Anda cari mungkin telah dihapus atau ID tidak valid.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once '../includes/footer.php';
$conn->close();
?>