<?php
// Memulai session untuk menampilkan notifikasi
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sisipkan header, asumsikan berada di dalam folder 'admin/includes'
require_once '../includes/header.php';
// Hubungkan ke database, asumsikan berada di root folder
require_once '../../db_connect.php';

// Ambil semua data banner dari database
$banners = [];
$sql = "SELECT id, title, subtitle, image_url, link_url, is_active, order_number FROM banners ORDER BY order_number ASC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $banners[] = $row;
    }
}

// Cek notifikasi dari session
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']); // Hapus setelah dibaca

$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']); // Hapus setelah dibaca
?>

<div class="container mx-auto p-4 md:p-6">
    <h1 class="text-4xl font-bold text-gray-800 mb-4">Kelola Banner</h1>
    <p class="text-gray-600 mb-8">Atur banner promosi yang tampil di halaman utama menu pelanggan.</p>

    <!-- Tampilkan Notifikasi -->
    <?php if ($success_message): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg shadow" role="alert">
            <p class="font-bold">Sukses!</p>
            <p><?= htmlspecialchars($success_message) ?></p>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg shadow" role="alert">
            <p class="font-bold">Error!</p>
            <p><?= htmlspecialchars($error_message) ?></p>
        </div>
    <?php endif; ?>

    <!-- Tombol Tambah Banner -->
    <div class="mb-6">
        <a href="tambah_banner.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-5 rounded-lg inline-flex items-center justify-center shadow-md transition-transform transform hover:scale-105">
            <i class="fas fa-plus mr-2"></i>
            <span>Tambah Banner Baru</span>
        </a>
    </div>

    <!-- Tabel Daftar Banner -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Gambar</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Judul & Subjudul</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Urutan</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody id="bannerTableBody">
                    <?php if (!empty($banners)): ?>
                        <?php foreach ($banners as $banner): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <img src="../<?= htmlspecialchars($banner['image_url']) ?>" alt="<?= htmlspecialchars($banner['title']) ?>" class="w-32 h-16 object-cover rounded-md">
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 font-semibold whitespace-no-wrap"><?= htmlspecialchars($banner['title']) ?></p>
                                    <p class="text-gray-600 whitespace-no-wrap"><?= htmlspecialchars($banner['subtitle']) ?></p>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <span class="relative inline-block px-3 py-1 font-semibold leading-tight rounded-full <?= $banner['is_active'] ? 'text-green-900 bg-green-200' : 'text-red-900 bg-red-200' ?>">
                                        <?= $banner['is_active'] ? 'Aktif' : 'Tidak Aktif' ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap"><?= htmlspecialchars($banner['order_number']) ?></p>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 bg-white text-sm text-right">
                                    <a href="edit_banner.php?id=<?= $banner['id'] ?>" class="text-yellow-600 hover:text-yellow-900 mr-3 font-semibold">Edit</a>
                                    <a href="proses_banner.php?action=delete&id=<?= $banner['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus banner ini?')" class="text-red-600 hover:text-red-900 font-semibold">Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-10 text-gray-500">
                                <p class="text-lg">Belum ada banner.</p>
                                <p>Silakan tambahkan banner baru untuk ditampilkan di halaman depan.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Sisipkan footer
require_once '../includes/footer.php';
$conn->close();
?>