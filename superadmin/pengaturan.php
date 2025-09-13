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

// Setelah logika redirect selesai, baru kita panggil file yang menghasilkan HTML
require_once 'includes/header.php';


// --- AMBIL DATA DARI DATABASE (untuk ditampilkan di form) ---
// Data sekarang diambil dari variabel global $APP_CONFIG yang sudah dimuat di db_connect.php
$days_open = isset($APP_CONFIG['days_open']) ? json_decode($APP_CONFIG['days_open'], true) : [];

// Ambil data pengguna (hanya admin dan kasir)
$staff_users = [];
$sql_staff = "SELECT id, username, email, role FROM users WHERE role IN ('admin', 'kasir') ORDER BY role, username";
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
            <h2 class="text-xl font-bold text-gray-800 border-b pb-2 mb-4">Manajemen Pengguna (Admin & Kasir)</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full leading-normal">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="p-3 text-left text-xs font-semibold uppercase">Username</th>
                            <th class="p-3 text-left text-xs font-semibold uppercase">Email</th>
                            <th class="p-3 text-left text-xs font-semibold uppercase">Role</th>
                            <th class="p-3 text-left text-xs font-semibold uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_users as $staff): ?>
                            <tr>
                                <td class="p-3 border-b"><?= htmlspecialchars($staff['username']) ?></td>
                                <td class="p-3 border-b"><?= htmlspecialchars($staff['email']) ?></td>
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
</script>

<?php
require_once 'includes/footer.php';
$conn->close();
?>