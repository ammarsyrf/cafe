<?php
// File: superadmin/pelanggan/tambah_pelanggan.php

// Panggil header dan koneksi database dengan path yang benar
require_once '../includes/header.php';
require_once '../../db_connect.php';

$error_message = '';
$success_message = '';

// Proses form jika metode adalah POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data dari form
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']);
    $password = $_POST['password'];

    // Validasi sederhana
    if (empty($username) || empty($email) || empty($password)) {
        $error_message = "Nama, email, dan password wajib diisi.";
    } else {
        // Cek apakah username atau email sudah ada
        $sql_check = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ss", $username, $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error_message = "Username atau email sudah terdaftar.";
        } else {
            // Hash password untuk keamanan
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // PERUBAHAN: Peran pengguna diatur secara otomatis menjadi 'member'
            $role = 'member'; 

            // Siapkan query untuk memasukkan data baru
            $sql_insert = "INSERT INTO users (username, email, phone_number, password, role) VALUES (?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("sssss", $username, $email, $phone_number, $hashed_password, $role);

            // Eksekusi query
            if ($stmt_insert->execute()) {
                // Jika berhasil, arahkan kembali ke halaman daftar pelanggan
                header("Location: pelanggan.php?status=added");
                exit();
            } else {
                // Tampilkan pesan error yang lebih detail dari database
                $error_message = "Gagal menambahkan pelanggan. Error: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}
?>

<div class="container mx-auto">
    <div class="max-w-2xl mx-auto bg-white rounded-xl shadow-lg p-8">
        <div class="flex items-center mb-6">
            <a href="pelanggan.php" title="Kembali" class="text-gray-500 hover:text-blue-600 transition duration-300 text-2xl p-2 rounded-full hover:bg-gray-100 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Tambah Member Baru</h1>
        </div>

        <!-- Tampilkan pesan error atau sukses -->
        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
                <p class="font-bold">Terjadi Kesalahan</p>
                <p><?= $error_message ?></p>
            </div>
        <?php endif; ?>

        <form action="tambah_pelanggan.php" method="POST" class="space-y-6">
            <!-- Nama Pelanggan -->
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Nama Member</label>
                <div class="relative rounded-md shadow-sm">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <i class="fas fa-user text-gray-400"></i>
                    </div>
                    <input type="text" name="username" id="username" class="block w-full rounded-md border-gray-300 pl-10 focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-3" placeholder="Contoh: Budi Santoso" required>
                </div>
            </div>

            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                 <div class="relative rounded-md shadow-sm">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <i class="fas fa-envelope text-gray-400"></i>
                    </div>
                    <input type="email" name="email" id="email" class="block w-full rounded-md border-gray-300 pl-10 focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-3" placeholder="contoh@email.com" required>
                </div>
            </div>

            <!-- Nomor Telepon -->
            <div>
                <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1">Nomor Telepon</label>
                 <div class="relative rounded-md shadow-sm">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <i class="fas fa-phone text-gray-400"></i>
                    </div>
                    <input type="text" name="phone_number" id="phone_number" class="block w-full rounded-md border-gray-300 pl-10 focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-3" placeholder="081234567890">
                </div>
            </div>
            
            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                 <div class="relative rounded-md shadow-sm">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <i class="fas fa-lock text-gray-400"></i>
                    </div>
                    <input type="password" name="password" id="password" class="block w-full rounded-md border-gray-300 pl-10 focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-3" placeholder="Minimal 8 karakter" required>
                </div>
            </div>

            <!-- Tombol Simpan -->
            <div class="pt-4">
                 <button type="submit" class="w-full flex justify-center items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg shadow-lg hover:shadow-xl transition duration-300">
                    <i class="fas fa-save"></i>
                    Simpan Member
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once '../includes/footer.php';
$conn->close();
?>

