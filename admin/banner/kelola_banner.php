<?php
// Memulai session untuk menampilkan notifikasi
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Mengasumsikan file-file ini ada di path yang benar
require_once '../includes/header.php'; // Aktifkan jika Anda punya header
require_once '../../app/config/db_connect.php'; // Pastikan path ini benar

// Ambil semua data banner dari database, diurutkan berdasarkan order_number
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
unset($_SESSION['success_message']);

$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);

// Asumsikan path ke gambar adalah relatif dari folder admin
// Jika file ini ada di /admin/banner/kelola_banner.php, maka path ke root adalah '../../'
$base_path_img = '../../admin/';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Banner</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome untuk Ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SortableJS untuk Drag & Drop -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <style>
        /* Menambahkan gaya untuk handle drag */
        .drag-handle {
            cursor: move;
            /* atau cursor: grab; */
        }

        /* Styling untuk placeholder saat drag */
        .sortable-ghost {
            opacity: 0.4;
            background-color: #c0d9ff;
        }
    </style>
</head>

<body class="bg-gray-50 font-sans">

    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-8">
            <div>
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800">Kelola Banner</h1>
                <p class="text-gray-600 mt-1">Atur banner promosi yang tampil di halaman utama.</p>
            </div>
            <div class="mt-4 sm:mt-0">
                <a href="tambah_banner.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center shadow-md transition-transform transform hover:scale-105">
                    <i class="fas fa-plus mr-2"></i>
                    <span>Tambah Banner</span>
                </a>
            </div>
        </div>


        <!-- Notifikasi -->
        <div id="notification-container" class="space-y-4 mb-6">
            <?php if ($success_message): ?>
                <div class="notification-item bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-sm flex justify-between items-center" role="alert">
                    <div>
                        <p class="font-bold">Sukses!</p>
                        <p><?= htmlspecialchars($success_message) ?></p>
                    </div>
                    <button class="text-green-700 hover:text-green-900" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="notification-item bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-sm flex justify-between items-center" role="alert">
                    <div>
                        <p class="font-bold">Error!</p>
                        <p><?= htmlspecialchars($error_message) ?></p>
                    </div>
                    <button class="text-red-700 hover:text-red-900" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tampilan Tabel untuk Desktop -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden hidden md:block">
            <div class="overflow-x-auto">
                <table class="min-w-full leading-normal">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-3 w-12"></th> <!-- Kolom untuk drag handle -->
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
                                <tr data-id="<?= $banner['id'] ?>" class="hover:bg-gray-50">
                                    <td class="px-3 py-4 border-b border-gray-200 text-center text-gray-400 drag-handle">
                                        <i class="fas fa-grip-vertical"></i>
                                    </td>
                                    <td class="px-5 py-4 border-b border-gray-200">
                                        <img src="<?= $base_path_img . htmlspecialchars($banner['image_url']) ?>" alt="<?= htmlspecialchars($banner['title']) ?>" class="w-32 h-16 object-cover rounded-md shadow-sm">
                                    </td>
                                    <td class="px-5 py-4 border-b border-gray-200">
                                        <p class="text-gray-900 font-semibold"><?= htmlspecialchars($banner['title']) ?></p>
                                        <p class="text-gray-600 text-sm"><?= htmlspecialchars($banner['subtitle']) ?></p>
                                    </td>
                                    <td class="px-5 py-4 border-b border-gray-200">
                                        <span class="relative inline-block px-3 py-1 font-semibold leading-tight rounded-full <?= $banner['is_active'] ? 'text-green-900 bg-green-200' : 'text-red-900 bg-red-200' ?>">
                                            <?= $banner['is_active'] ? 'Aktif' : 'Tidak Aktif' ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 border-b border-gray-200">
                                        <p class="text-gray-900 order-number"><?= htmlspecialchars($banner['order_number']) ?></p>
                                    </td>
                                    <td class="px-5 py-4 border-b border-gray-200 text-right">
                                        <a href="edit_banner.php?id=<?= $banner['id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-4 font-semibold">Edit</a>
                                        <a href="proses_banner.php?action=delete&id=<?= $banner['id'] ?>" class="text-red-600 hover:text-red-900 font-semibold delete-btn">Hapus</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-10 text-gray-500">
                                    <p class="text-lg">Belum ada banner.</p>
                                    <p>Silakan tambahkan banner baru.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tampilan Kartu untuk Mobile -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 md:hidden">
            <?php if (!empty($banners)): ?>
                <?php foreach ($banners as $banner): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <img src="<?= $base_path_img . htmlspecialchars($banner['image_url']) ?>" alt="<?= htmlspecialchars($banner['title']) ?>" class="w-full h-40 object-cover">
                        <div class="p-4">
                            <h3 class="font-bold text-lg text-gray-800"><?= htmlspecialchars($banner['title']) ?></h3>
                            <p class="text-gray-600 text-sm mb-3"><?= htmlspecialchars($banner['subtitle']) ?></p>
                            <div class="flex justify-between items-center mb-4">
                                <span class="relative inline-block px-3 py-1 font-semibold leading-tight rounded-full text-xs <?= $banner['is_active'] ? 'text-green-900 bg-green-200' : 'text-red-900 bg-red-200' ?>">
                                    <?= $banner['is_active'] ? 'Aktif' : 'Tidak Aktif' ?>
                                </span>
                                <span class="text-gray-500 text-sm">Urutan: <?= htmlspecialchars($banner['order_number']) ?></span>
                            </div>
                            <div class="flex justify-end border-t pt-3">
                                <a href="edit_banner.php?id=<?= $banner['id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-4 font-semibold">Edit</a>
                                <a href="proses_banner.php?action=delete&id=<?= $banner['id'] ?>" class="text-red-600 hover:text-red-900 font-semibold delete-btn">Hapus</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow-md p-10 text-center col-span-1 sm:col-span-2 text-gray-500">
                    <p class="text-lg">Belum ada banner.</p>
                    <p>Silakan tambahkan banner baru.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 mt-2">Hapus Banner?</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500">
                        Apakah Anda yakin ingin menghapus banner ini? Tindakan ini tidak dapat dibatalkan.
                    </p>
                </div>
                <div class="items-center px-4 py-3">
                    <button id="modalConfirmDelete" class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md w-auto shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                        Ya, Hapus
                    </button>
                    <button id="modalCancel" class="px-4 py-2 bg-gray-200 text-gray-800 text-base font-medium rounded-md w-auto ml-2 shadow-sm hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Logika Modal Konfirmasi Hapus ---
            const deleteModal = document.getElementById('deleteModal');
            const modalConfirmDelete = document.getElementById('modalConfirmDelete');
            const modalCancel = document.getElementById('modalCancel');
            let deleteUrl = '';

            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    deleteUrl = this.href;
                    deleteModal.classList.remove('hidden');
                });
            });

            modalConfirmDelete.addEventListener('click', function() {
                window.location.href = deleteUrl;
            });

            modalCancel.addEventListener('click', function() {
                deleteModal.classList.add('hidden');
            });

            // --- Logika Drag & Drop untuk Tabel ---
            const tableBody = document.getElementById('bannerTableBody');
            if (tableBody) {
                new Sortable(tableBody, {
                    animation: 150,
                    handle: '.drag-handle', // Tentukan elemen mana yang bisa di-drag
                    ghostClass: 'sortable-ghost',
                    onEnd: function(evt) {
                        // Ambil semua baris setelah diurutkan
                        const rows = tableBody.querySelectorAll('tr');
                        const bannerOrder = [];

                        // Buat array berisi ID banner sesuai urutan baru
                        rows.forEach((row, index) => {
                            bannerOrder.push(row.dataset.id);
                            // Update nomor urutan di tampilan secara langsung
                            row.querySelector('.order-number').textContent = index + 1;
                        });

                        // Kirim data urutan baru ke server
                        fetch('proses_update_urutan.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    order: bannerOrder
                                }),
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    showToast('Urutan banner berhasil diperbarui!');
                                } else {
                                    showToast('Gagal memperbarui urutan.', 'error');
                                    // Optional: reload halaman jika terjadi error fatal
                                    // location.reload();
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                showToast('Terjadi kesalahan koneksi.', 'error');
                            });
                    }
                });
            }

            // --- Fungsi untuk menampilkan notifikasi toast ---
            function showToast(message, type = 'success') {
                const toast = document.createElement('div');
                toast.className = `fixed bottom-5 right-5 p-4 rounded-lg shadow-lg text-white ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}`;
                toast.textContent = message;

                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.remove();
                }, 3000);
            }
        });
    </script>

    <?php
    require_once '../includes/footer.php'; // Aktifkan jika Anda punya footer
    $conn->close();
    ?>
</body>

</html>