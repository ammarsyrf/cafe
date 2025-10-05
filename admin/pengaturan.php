<?php
// File: admin/pengaturan.php

// Panggil koneksi database dan muat pengaturan terlebih dahulu
require_once '../app/config/db_connect.php';

// --- PROSES SIMPAN PENGATURAN UMUM ---
// Blok ini harus dieksekusi SEBELUM ada output HTML (yang ada di header.php)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_general_settings'])) {
    $sql_upsert = "INSERT INTO settings (setting_name, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
    $stmt = $conn->prepare($sql_upsert);

    foreach ($_POST['settings'] as $key => $value) {
        $stmt->bind_param("ss", $key, $value);
        $stmt->execute();
    }
    $stmt->close();

    // Redirect untuk refresh halaman dengan data baru dan notifikasi sukses
    header("Location: pengaturan.php?status=success");
    exit();
}

// --- PROSES TAMBAH CREW ---
$crew_message = '';
$crew_error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_crew'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    // Validasi input
    if (empty($username) || empty($name) || empty($password) || empty($role)) {
        $crew_error = 'Username, nama, password, dan role harus diisi.';
    } elseif (strlen($username) < 3) {
        $crew_error = 'Username minimal 3 karakter.';
    } elseif (strlen($password) < 6) {
        $crew_error = 'Password minimal 6 karakter.';
    } elseif (!in_array($role, ['admin', 'cashier'])) {
        $crew_error = 'Role tidak valid.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $crew_error = 'Format email tidak valid.';
    } else {
        // Cek apakah username sudah ada
        $sql_check = "SELECT id FROM users WHERE username = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $crew_error = 'Username sudah digunakan. Silakan pilih username lain.';
        } else {
            // Cek email jika diisi
            if (!empty($email)) {
                $sql_check_email = "SELECT id FROM users WHERE email = ?";
                $stmt_check_email = $conn->prepare($sql_check_email);
                $stmt_check_email->bind_param("s", $email);
                $stmt_check_email->execute();
                $stmt_check_email->store_result();

                if ($stmt_check_email->num_rows > 0) {
                    $crew_error = 'Email sudah digunakan. Silakan pilih email lain.';
                }
                $stmt_check_email->close();
            }
            if (empty($crew_error)) {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert crew baru
                $sql_insert = "INSERT INTO users (username, email, name, password, role) VALUES (?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("sssss", $username, $email, $name, $hashed_password, $role);

                if ($stmt_insert->execute()) {
                    $crew_message = 'Crew berhasil ditambahkan!';
                    // Redirect untuk refresh data
                    header("Location: pengaturan.php?status=crew_added");
                    exit();
                } else {
                    $crew_error = 'Gagal menambahkan crew. Silakan coba lagi.';
                }
                $stmt_insert->close();
            }
        }
        $stmt_check->close();
    }
}

// --- PROSES EDIT USER ---
$edit_message = '';
$edit_error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    $user_id = intval($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $role = $_POST['role'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    // Validasi input
    if (empty($username) || empty($name) || empty($role) || $user_id <= 0) {
        $edit_error = 'Username, nama, dan role harus diisi.';
    } elseif (strlen($username) < 3) {
        $edit_error = 'Username minimal 3 karakter.';
    } elseif (!in_array($role, ['admin', 'cashier', 'superadmin'])) {
        $edit_error = 'Role tidak valid.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $edit_error = 'Format email tidak valid.';
    } elseif (!empty($new_password) && strlen($new_password) < 6) {
        $edit_error = 'Password baru minimal 6 karakter.';
    } else {
        // Cek apakah username sudah ada (kecuali untuk user yang sedang diedit)
        $sql_check = "SELECT id FROM users WHERE username = ? AND id != ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("si", $username, $user_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $edit_error = 'Username sudah digunakan. Silakan pilih username lain.';
        } else {
            // Cek email jika diisi (kecuali untuk user yang sedang diedit)
            if (!empty($email)) {
                $sql_check_email = "SELECT id FROM users WHERE email = ? AND id != ?";
                $stmt_check_email = $conn->prepare($sql_check_email);
                $stmt_check_email->bind_param("si", $email, $user_id);
                $stmt_check_email->execute();
                $stmt_check_email->store_result();

                if ($stmt_check_email->num_rows > 0) {
                    $edit_error = 'Email sudah digunakan. Silakan pilih email lain.';
                }
                $stmt_check_email->close();
            }

            if (empty($edit_error)) {
                // Update user
                if (!empty($new_password)) {
                    // Update dengan password baru
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql_update = "UPDATE users SET username = ?, email = ?, name = ?, role = ?, password = ? WHERE id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("sssssi", $username, $email, $name, $role, $hashed_password, $user_id);
                } else {
                    // Update tanpa mengubah password
                    $sql_update = "UPDATE users SET username = ?, email = ?, name = ?, role = ? WHERE id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("ssssi", $username, $email, $name, $role, $user_id);
                }

                if ($stmt_update->execute()) {
                    $edit_message = 'User berhasil diupdate!';
                    // Redirect untuk refresh data
                    header("Location: pengaturan.php?status=user_updated");
                    exit();
                } else {
                    $edit_error = 'Gagal mengupdate user. Silakan coba lagi.';
                }
                $stmt_update->close();
            }
        }
        $stmt_check->close();
    }
}

// --- PROSES DELETE USER ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        $edit_error = 'ID user tidak valid.';
    } else {
        // Cek apakah user yang akan dihapus adalah superadmin atau user yang sedang login
        $sql_check_user = "SELECT role FROM users WHERE id = ?";
        $stmt_check_user = $conn->prepare($sql_check_user);
        $stmt_check_user->bind_param("i", $user_id);
        $stmt_check_user->execute();
        $result_check = $stmt_check_user->get_result();

        if ($result_check && $result_check->num_rows > 0) {
            $user_data = $result_check->fetch_assoc();

            // Tidak bisa menghapus superadmin
            if ($user_data['role'] == 'superadmin') {
                $edit_error = 'Tidak dapat menghapus user dengan role superadmin.';
            } else {
                // Hapus user
                $sql_delete = "DELETE FROM users WHERE id = ?";
                $stmt_delete = $conn->prepare($sql_delete);
                $stmt_delete->bind_param("i", $user_id);

                if ($stmt_delete->execute()) {
                    header("Location: pengaturan.php?status=user_deleted");
                    exit();
                } else {
                    $edit_error = 'Gagal menghapus user. Silakan coba lagi.';
                }
                $stmt_delete->close();
            }
        } else {
            $edit_error = 'User tidak ditemukan.';
        }
        $stmt_check_user->close();
    }
}

// Setelah logika redirect selesai, baru kita panggil file yang menghasilkan HTML
require_once 'includes/header.php';


// --- AMBIL DATA DARI DATABASE (untuk ditampilkan di form) ---
// Data sekarang diambil dari variabel global $APP_CONFIG yang sudah dimuat di db_connect.php
$days_open = isset($APP_CONFIG['days_open']) ? json_decode($APP_CONFIG['days_open'], true) : [];

// Ambil data pengguna (admin, kasir, dan superadmin)
$staff_users = [];
$sql_staff = "SELECT id, username, email, name, role FROM users WHERE role IN ('admin', 'cashier', 'superadmin') ORDER BY role, username";
$result_staff = $conn->query($sql_staff);
if ($result_staff) {
    $staff_users = $result_staff->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="container mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Pengaturan</h1>

    <!-- Navigasi Tab -->
    <div class="mb-6 border-b border-gray-200">
        <nav class="flex space-x-4" aria-label="Tabs">
            <button onclick="changeTab('general')" id="tab-general" class="tab-button text-blue-600 border-blue-600 px-3 py-2 font-medium text-sm rounded-t-lg border-b-2">
                General Settings
            </button>
            <button onclick="changeTab('users')" id="tab-users" class="tab-button text-gray-500 hover:text-gray-700 px-3 py-2 font-medium text-sm rounded-t-lg border-b-2 border-transparent">
                User Management
            </button>
        </nav>
    </div>

    <!-- Notifikasi Sukses -->
    <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
        <div id="successNotification" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md transition-opacity duration-500" role="alert">
            <p>Pengaturan berhasil disimpan!</p>
        </div>
    <?php elseif (isset($_GET['status']) && $_GET['status'] == 'crew_added'): ?>
        <div id="crewAddedNotification" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md transition-opacity duration-500" role="alert">
            <p>Crew berhasil ditambahkan!</p>
        </div>
    <?php elseif (isset($_GET['status']) && $_GET['status'] == 'user_updated'): ?>
        <div id="userUpdatedNotification" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md transition-opacity duration-500" role="alert">
            <p>User berhasil diupdate!</p>
        </div>
    <?php elseif (isset($_GET['status']) && $_GET['status'] == 'user_deleted'): ?>
        <div id="userDeletedNotification" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md transition-opacity duration-500" role="alert">
            <p>User berhasil dihapus!</p>
        </div>
    <?php endif; ?>

    <!-- Notifikasi Error -->
    <?php if (!empty($crew_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
            <p><?= htmlspecialchars($crew_error) ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($edit_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
            <p><?= htmlspecialchars($edit_error) ?></p>
        </div>
    <?php endif; ?>

    <!-- Konten Tab -->
    <div id="content-general" class="tab-content">
        <form method="POST" class="space-y-8 bg-white p-8 rounded-xl shadow-lg">
            <!-- Profil Toko -->
            <div>
                <h2 class="text-xl font-bold text-gray-800 border-b pb-2 mb-4">Profil Toko</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium">Nama Toko</label>
                        <input type="text" name="settings[cafe_name]" value="<?= htmlspecialchars($APP_CONFIG['cafe_name'] ?? '') ?>" class="mt-1 w-full border rounded-lg p-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">No. Telepon Toko</label>
                        <input type="text" name="settings[cafe_phone]" value="<?= htmlspecialchars($APP_CONFIG['cafe_phone'] ?? '') ?>" class="mt-1 w-full border rounded-lg p-2">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium">Alamat Toko</label>
                        <textarea name="settings[cafe_address]" rows="3" class="mt-1 w-full border rounded-lg p-2"><?= htmlspecialchars($APP_CONFIG['cafe_address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Jam Operasional -->
            <div>
                <h2 class="text-xl font-bold text-gray-800 border-b pb-2 mb-4">Jam Operasional</h2>
                <div class="space-y-4 max-w-2xl">
                    <div>
                        <label class="block text-sm font-medium mb-2">Jam Operasional</label>
                        <input type="text" name="settings[operating_hours]"
                            value="<?= htmlspecialchars($APP_CONFIG['operating_hours'] ?? '') ?>"
                            class="w-full border rounded-lg p-3 text-sm"
                            placeholder="Contoh: Senin - Jumat: 08:00 - 22:00, Sabtu - Minggu: 09:00 - 23:00">
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Tuliskan jam operasional cafe Anda dengan format bebas. Contoh: "Senin-Jumat 08:00-22:00, Weekend 09:00-23:00"
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Hari Buka</label>
                        <input type="text" name="settings[operating_days]"
                            value="<?= htmlspecialchars($APP_CONFIG['operating_days'] ?? '') ?>"
                            class="w-full border rounded-lg p-3 text-sm"
                            placeholder="Contoh: Setiap Hari, Senin - Sabtu, Kecuali Hari Minggu">
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Tuliskan hari operasional cafe Anda dengan format bebas. Contoh: "Setiap Hari", "Senin-Sabtu", "Tutup Hari Minggu"
                        </p>
                    </div>
                </div>
            </div> <!-- Media Sosial -->
            <div>
                <h2 class="text-xl font-bold text-gray-800 border-b pb-2 mb-4">Media Sosial</h2>
                <div class="space-y-4">
                    <div class="flex items-center gap-4"><i class="fab fa-instagram w-5 text-xl"></i><input type="url" name="settings[social_instagram]" placeholder="https://instagram.com/username" value="<?= htmlspecialchars($APP_CONFIG['social_instagram'] ?? '') ?>" class="w-full border rounded-lg p-2"></div>
                    <div class="flex items-center gap-4"><i class="fab fa-facebook w-5 text-xl"></i><input type="url" name="settings[social_facebook]" placeholder="https://facebook.com/username" value="<?= htmlspecialchars($APP_CONFIG['social_facebook'] ?? '') ?>" class="w-full border rounded-lg p-2"></div>
                    <div class="flex items-center gap-4"><i class="fab fa-twitter w-5 text-xl"></i><input type="url" name="settings[social_twitter]" placeholder="https://twitter.com/username" value="<?= htmlspecialchars($APP_CONFIG['social_twitter'] ?? '') ?>" class="w-full border rounded-lg p-2"></div>
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" name="save_general_settings" class="bg-blue-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-blue-700">Simpan Profil Toko</button>
            </div>
        </form>
    </div>

    <div id="content-users" class="tab-content hidden">
        <div class="bg-white p-8 rounded-xl shadow-lg">
            <!-- Manajemen Pengguna -->
            <div class="flex justify-between items-center border-b pb-2 mb-4">
                <h2 class="text-xl font-bold text-gray-800">Manajemen Pengguna (Admin, Kasir, & Superadmin)</h2>
                <button onclick="openAddCrewModal()" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 flex items-center">
                    <i class="fas fa-plus mr-2"></i>
                    Tambah Crew
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full leading-normal">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="p-3 text-left text-xs font-semibold uppercase">Nama Lengkap (Nama Kasir)</th>
                            <th class="p-3 text-left text-xs font-semibold uppercase">Username</th>
                            <th class="p-3 text-left text-xs font-semibold uppercase">Email</th>
                            <th class="p-3 text-left text-xs font-semibold uppercase">Role</th>
                            <th class="p-3 text-left text-xs font-semibold uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_users as $staff): ?>
                            <tr>
                                <td class="p-3 border-b"><?= htmlspecialchars($staff['name']) ?></td>
                                <td class="p-3 border-b"><?= htmlspecialchars($staff['username']) ?></td>
                                <td class="p-3 border-b"><?= htmlspecialchars($staff['email'] ?? '-') ?></td>
                                <td class="p-3 border-b capitalize"><?= htmlspecialchars($staff['role']) ?></td>
                                <td class="p-3 border-b">
                                    <button onclick="openEditUserModal(<?= $staff['id'] ?>, '<?= htmlspecialchars($staff['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($staff['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($staff['email'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($staff['role'], ENT_QUOTES) ?>')"
                                        class="text-blue-600 hover:text-blue-800 mr-3">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <?php if ($staff['role'] != 'superadmin'): ?>
                                        <button onclick="confirmDeleteUser(<?= $staff['id'] ?>, '<?= htmlspecialchars($staff['name'], ENT_QUOTES) ?>')"
                                            class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Metode Pembayaran -->
            <div class="mt-8">
                <h2 class="text-xl font-bold text-gray-800 border-b pb-2 mb-4">Metode Pembayaran</h2>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p class="text-gray-600">Integrasi dengan pihak ketiga untuk metode pembayaran akan ditampilkan di sini. Anda dapat memasukkan API Key dan mengelola opsi pembayaran yang tersedia untuk pelanggan.</p>
                    <div class="mt-4">
                        <label class="block text-sm font-medium">API Key (Contoh)</label>
                        <input type="text" disabled value="************************" class="mt-1 w-full md:w-1/2 border rounded-lg p-2 bg-gray-200 cursor-not-allowed">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Crew -->
    <div id="addCrewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 opacity-0 pointer-events-none transition-opacity duration-300">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md m-4 relative transform scale-95 transition-transform duration-300">
            <button onclick="closeAddCrewModal()" class="absolute top-3 right-3 text-gray-400 hover:text-gray-800">
                <i class="fas fa-times text-2xl"></i>
            </button>

            <div class="p-8">
                <h2 class="text-2xl font-bold mb-1 text-center text-gray-800">Tambah Crew Baru</h2>
                <p class="text-center text-gray-500 mb-6">Tambahkan admin atau kasir baru ke sistem</p>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="add_crew" value="1">

                    <div>
                        <label for="crew_username" class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                        <input type="text" id="crew_username" name="username" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Username untuk login">
                    </div>

                    <div>
                        <label for="crew_name" class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                        <input type="text" id="crew_name" name="name" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Nama lengkap crew">
                    </div>

                    <div>
                        <label for="crew_email" class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-gray-400 font-normal">(Opsional)</span></label>
                        <input type="email" id="crew_email" name="email"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="email@example.com">
                    </div>

                    <div>
                        <label for="crew_password" class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                        <input type="password" id="crew_password" name="password" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Minimal 6 karakter">
                    </div>

                    <div>
                        <label for="crew_role" class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                        <select id="crew_role" name="role" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Pilih Role</option>
                            <option value="admin">Admin</option>
                            <option value="cashier">Kasir</option>
                        </select>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2.5 rounded-md hover:bg-blue-700 transition-colors">
                            Tambah Crew
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit User -->
    <div id="editUserModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 opacity-0 pointer-events-none transition-opacity duration-300">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md m-4 relative transform scale-95 transition-transform duration-300">
            <button onclick="closeEditUserModal()" class="absolute top-3 right-3 text-gray-400 hover:text-gray-800">
                <i class="fas fa-times text-2xl"></i>
            </button>

            <div class="p-8">
                <h2 class="text-2xl font-bold mb-1 text-center text-gray-800">Edit User</h2>
                <p class="text-center text-gray-500 mb-6">Update informasi user</p>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="edit_user" value="1">
                    <input type="hidden" name="user_id" id="edit_user_id" value="">

                    <div>
                        <label for="edit_username" class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                        <input type="text" id="edit_username" name="username" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Username untuk login">
                    </div>

                    <div>
                        <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                        <input type="text" id="edit_name" name="name" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Nama lengkap user">
                    </div>

                    <div>
                        <label for="edit_email" class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-gray-400 font-normal">(Opsional)</span></label>
                        <input type="email" id="edit_email" name="email"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="email@example.com">
                    </div>

                    <div>
                        <label for="edit_role" class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                        <select id="edit_role" name="role" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Pilih Role</option>
                            <option value="admin">Admin</option>
                            <option value="cashier">Kasir</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                    </div>

                    <div>
                        <label for="edit_new_password" class="block text-sm font-medium text-gray-700 mb-1">Password Baru <span class="text-gray-400 font-normal">(Kosongkan jika tidak ingin mengubah)</span></label>
                        <input type="password" id="edit_new_password" name="new_password"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Minimal 6 karakter">
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2.5 rounded-md hover:bg-blue-700 transition-colors">
                            Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Delete -->
    <div id="deleteUserModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 opacity-0 pointer-events-none transition-opacity duration-300">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md m-4 relative transform scale-95 transition-transform duration-300">
            <div class="p-8 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h2 class="text-xl font-bold mb-2 text-gray-800">Konfirmasi Hapus User</h2>
                <p class="text-gray-500 mb-6">Apakah Anda yakin ingin menghapus user <strong id="deleteUserName"></strong>? Tindakan ini tidak dapat dibatalkan.</p>

                <form method="POST" class="flex gap-3">
                    <input type="hidden" name="delete_user" value="1">
                    <input type="hidden" name="user_id" id="delete_user_id" value="">

                    <button type="button" onclick="closeDeleteUserModal()"
                        class="flex-1 bg-gray-200 text-gray-800 font-bold py-2 rounded-md hover:bg-gray-300 transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                        class="flex-1 bg-red-600 text-white font-bold py-2 rounded-md hover:bg-red-700 transition-colors">
                        Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function changeTab(tabName) {
        // Sembunyikan semua konten tab
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        // Non-aktifkan semua tombol tab
        document.querySelectorAll('.tab-button').forEach(button => {
            button.classList.remove('text-blue-600', 'border-blue-600');
            button.classList.add('text-gray-500', 'border-transparent');
        });

        // Tampilkan konten tab yang dipilih
        document.getElementById('content-' + tabName).classList.remove('hidden');
        // Aktifkan tombol tab yang dipilih
        const activeButton = document.getElementById('tab-' + tabName);
        activeButton.classList.add('text-blue-600', 'border-blue-600');
        activeButton.classList.remove('text-gray-500', 'border-transparent');
    }

    function openAddCrewModal() {
        const modal = document.getElementById('addCrewModal');
        modal.classList.remove('opacity-0', 'pointer-events-none');
        modal.querySelector('div').classList.remove('scale-95');
    }

    function closeAddCrewModal() {
        const modal = document.getElementById('addCrewModal');
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.add('scale-95');
        setTimeout(() => modal.classList.add('pointer-events-none'), 300);

        // Reset form
        modal.querySelector('form').reset();
    }

    // Close modal when clicking outside
    document.getElementById('addCrewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAddCrewModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddCrewModal();
            closeEditUserModal();
            closeDeleteUserModal();
        }
    });

    // === EDIT USER MODAL FUNCTIONS ===
    function openEditUserModal(userId, name, username, email, role) {
        const modal = document.getElementById('editUserModal');

        // Populate form fields
        document.getElementById('edit_user_id').value = userId;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_role').value = role;
        document.getElementById('edit_new_password').value = ''; // Always clear password field

        // Show modal
        modal.classList.remove('opacity-0', 'pointer-events-none');
        modal.querySelector('div').classList.remove('scale-95');
    }

    function closeEditUserModal() {
        const modal = document.getElementById('editUserModal');
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.add('scale-95');
        setTimeout(() => modal.classList.add('pointer-events-none'), 300);

        // Reset form
        modal.querySelector('form').reset();
    }

    // Close edit modal when clicking outside
    document.getElementById('editUserModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditUserModal();
        }
    });

    // === DELETE USER MODAL FUNCTIONS ===
    function confirmDeleteUser(userId, userName) {
        const modal = document.getElementById('deleteUserModal');

        // Populate data
        document.getElementById('delete_user_id').value = userId;
        document.getElementById('deleteUserName').textContent = userName;

        // Show modal
        modal.classList.remove('opacity-0', 'pointer-events-none');
        modal.querySelector('div').classList.remove('scale-95');
    }

    function closeDeleteUserModal() {
        const modal = document.getElementById('deleteUserModal');
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.add('scale-95');
        setTimeout(() => modal.classList.add('pointer-events-none'), 300);
    }

    // Close delete modal when clicking outside
    document.getElementById('deleteUserModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteUserModal();
        }
    });

    // Auto-hide notifications
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-hide success notification after 4 seconds
        const successNotification = document.getElementById('successNotification');
        if (successNotification) {
            setTimeout(function() {
                successNotification.style.opacity = '0';
                setTimeout(function() {
                    successNotification.style.display = 'none';
                }, 500); // Wait for fade out animation to complete
            }, 4000); // Hide after 4 seconds
        }

        // Auto-hide crew added notification after 5 seconds
        const crewNotification = document.getElementById('crewAddedNotification');
        if (crewNotification) {
            setTimeout(function() {
                crewNotification.style.opacity = '0';
                setTimeout(function() {
                    crewNotification.style.display = 'none';
                }, 500); // Wait for fade out animation to complete
            }, 5000); // Hide after 5 seconds
        }

        // Auto-hide user updated notification after 4 seconds
        const userUpdatedNotification = document.getElementById('userUpdatedNotification');
        if (userUpdatedNotification) {
            setTimeout(function() {
                userUpdatedNotification.style.opacity = '0';
                setTimeout(function() {
                    userUpdatedNotification.style.display = 'none';
                }, 500);
            }, 4000);
        }

        // Auto-hide user deleted notification after 4 seconds
        const userDeletedNotification = document.getElementById('userDeletedNotification');
        if (userDeletedNotification) {
            setTimeout(function() {
                userDeletedNotification.style.opacity = '0';
                setTimeout(function() {
                    userDeletedNotification.style.display = 'none';
                }, 500);
            }, 4000);
        }
    });
</script>

<?php
require_once 'includes/footer.php';
$conn->close();
?>