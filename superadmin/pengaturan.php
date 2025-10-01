<?php
// File: superadmin/pengaturan.php

// Panggil koneksi database dan muat pengaturan terlebih dahulu
require_once '../db_connect.php';

// --- PROSES SIMPAN PENGATURAN UMUM ---
// Blok ini harus dieksekusi SEBELUM ada output HTML (yang ada di header.php)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_general_settings'])) {
    // Simpan hari buka sebagai JSON array
    $days_open = isset($_POST['days_open']) ? json_encode($_POST['days_open']) : '[]';
    $_POST['settings']['days_open'] = $days_open;

    $sql_upsert = "INSERT INTO settings (setting_name, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
    $stmt = $conn->prepare($sql_upsert);

    foreach ($_POST['settings'] as $key => $value) {
        $stmt->bind_param("ss", $key, $value);
        $stmt->execute();
    }
    $stmt->close();

    // Redirect untuk refresh halaman dengan data baru dan notifikasi sukses
    // Ini sekarang bisa berjalan tanpa error
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
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md" role="alert">
            <p>Pengaturan berhasil disimpan!</p>
        </div>
    <?php elseif (isset($_GET['status']) && $_GET['status'] == 'crew_added'): ?>
        <div id="crewAddedNotification" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md transition-opacity duration-300" role="alert">
            <p>Crew berhasil ditambahkan!</p>
        </div>
    <?php endif; ?>

    <!-- Notifikasi Error -->
    <?php if (!empty($crew_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
            <p><?= htmlspecialchars($crew_error) ?></p>
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
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                    <div>
                        <label class="block text-sm font-medium">Jam Buka</label>
                        <input type="time" name="settings[hour_open]" value="<?= htmlspecialchars($APP_CONFIG['hour_open'] ?? '') ?>" class="mt-1 w-full border rounded-lg p-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Jam Tutup</label>
                        <input type="time" name="settings[hour_close]" value="<?= htmlspecialchars($APP_CONFIG['hour_close'] ?? '') ?>" class="mt-1 w-full border rounded-lg p-2">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">Hari Buka</label>
                        <div class="flex flex-wrap gap-4">
                            <?php $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']; ?>
                            <?php foreach ($days as $day): ?>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" name="days_open[]" value="<?= $day ?>" <?= in_array($day, $days_open) ? 'checked' : '' ?> class="rounded">
                                    <span><?= $day ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Media Sosial -->
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
                                <td class="p-3 border-b"><a href="#" class="text-blue-600 hover:underline">Edit</a></td>
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
        }
    });

    // Auto-hide crew added notification after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const crewNotification = document.getElementById('crewAddedNotification');
        if (crewNotification) {
            setTimeout(function() {
                crewNotification.style.opacity = '0';
                setTimeout(function() {
                    crewNotification.style.display = 'none';
                }, 500); // Wait for fade out animation to complete
            }, 5000); // Hide after 5 seconds
        }
    });
</script>

<?php
require_once 'includes/footer.php';
$conn->close();
?>