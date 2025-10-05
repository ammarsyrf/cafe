<?php
// File: admin/pelanggan/pelanggan.php

// Panggil header dan koneksi database dengan path yang benar
require_once '../includes/header.php';
require_once '../../app/config/db_connect.php';

// PERUBAHAN: Mengambil data dari tabel 'members' dengan kolom yang sesuai
$members = [];
// Mengambil kolom id, name, email, dan phone_number dari tabel members
$sql = "SELECT id, name, email, phone_number FROM members ORDER BY created_at DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
}

// Statistik member
$total_members = count($members);
// Top 5 member by transaksi (frekuensi datang)
$top_members = [];
$top_sql = "SELECT m.id, m.name, m.email, COUNT(o.id) as transaksi FROM members m LEFT JOIN orders o ON m.id = o.member_id GROUP BY m.id ORDER BY transaksi DESC, m.name ASC LIMIT 5";
$top_result = $conn->query($top_sql);
if ($top_result && $top_result->num_rows > 0) {
    while ($row = $top_result->fetch_assoc()) {
        $top_members[] = $row;
    }
}
// Member baru dalam 7 hari terakhir
$new_members = 0;
$new_sql = "SELECT COUNT(*) as baru FROM members WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$new_result = $conn->query($new_sql);
if ($new_result) {
    $new_members = $new_result->fetch_assoc()['baru'] ?? 0;
}

// Menampilkan notifikasi sukses jika ada
if (isset($_GET['success'])) {
    echo '<script>window.addEventListener("DOMContentLoaded",function(){showToast("' . htmlspecialchars($_GET['success']) . '",true);});</script>';
}
?>

<div class="container mx-auto">
    <!-- Dashboard Card -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-md p-6 flex flex-col items-center">
            <div class="text-2xl font-bold text-blue-600 mb-2">Total Member</div>
            <div class="text-4xl font-extrabold"><?= $total_members ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-md p-6 flex flex-col items-center">
            <div class="text-2xl font-bold text-green-600 mb-2">Top 5 Member Teraktif</div>
            <ul class="text-gray-700 text-sm w-full">
                <?php foreach ($top_members as $tm): ?>
                    <li class="flex justify-between items-center py-1 border-b last:border-b-0">
                        <span><?= htmlspecialchars($tm['name']) ?></span>
                        <span class="text-xs text-gray-500">Transaksi: <?= $tm['transaksi'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="bg-white rounded-xl shadow-md p-6 flex flex-col items-center">
            <div class="text-2xl font-bold text-indigo-600 mb-2">Member Baru (7 Hari)</div>
            <div class="text-4xl font-extrabold"><?= $new_members ?></div>
        </div>
    </div>

    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
        <div class="w-full sm:w-2/3">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                    <i class="fas fa-search text-gray-400"></i>
                </span>
                <!-- PERUBAHAN: Mengubah placeholder untuk pencarian sesuai data baru -->
                <input type="text" id="searchInput" class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Cari nama member atau email...">
            </div>
        </div>
        <a href="tambah_pelanggan.php" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg flex items-center justify-center">
            <i class="fas fa-plus mr-2"></i> Tambah Member
        </a>
    </div>

    <!-- Tabel Pelanggan -->
    <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
        <table class="min-w-full leading-normal">
            <thead>
                <tr class="bg-gray-100">
                    <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Nama Lengkap
                    </th>
                    <!-- PERUBAHAN: Menambahkan kembali kolom Email -->
                    <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Email
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        No. Telepon
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Aksi
                    </th>
                </tr>
            </thead>
            <tbody id="customer-table-body">
                <?php if (!empty($members)): ?>
                    <?php foreach ($members as $member): ?>
                        <!-- PERUBAHAN: Menambahkan data-email dan menggunakan kolom 'name' -->
                        <tr class="customer-row" data-name="<?= strtolower(htmlspecialchars($member['name'])) ?>" data-email="<?= strtolower(htmlspecialchars($member['email'] ?? '')) ?>">
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <p class="text-gray-900 whitespace-no-wrap font-medium"><?= htmlspecialchars($member['name']) ?></p>
                            </td>
                            <!-- PERUBAHAN: Menampilkan data email -->
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <p class="text-gray-900 whitespace-no-wrap"><?= htmlspecialchars($member['email'] ?? 'Tidak ada email') ?></p>
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <p class="text-gray-900 whitespace-no-wrap"><?= htmlspecialchars($member['phone_number'] ?? 'Tidak ada no. telp') ?></p>
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <a href="detail_pelanggan.php?id=<?= $member['id'] ?>" class="text-blue-600 hover:text-blue-800 font-semibold mr-4">Detail</a>
                                <a href="edit_pelanggan.php?id=<?= $member['id'] ?>" class="text-green-600 hover:text-green-800 font-semibold">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <!-- PERUBAHAN: Mengubah colspan menjadi 4 -->
                        <td colspan="4" class="text-center py-10 text-gray-500">
                            Tidak ada data member yang ditemukan.
                        </td>
                    </tr>
                <?php endif; ?>
                <tr id="no-results" class="hidden">
                    <!-- PERUBAHAN: Mengubah colspan menjadi 4 -->
                    <td colspan="4" class="text-center py-10 text-gray-500">
                        Pencarian tidak menemukan hasil.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Skrip untuk fungsionalitas pencarian real-time
    document.getElementById('searchInput').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let customerRows = document.querySelectorAll('tbody .customer-row');
        let noResults = document.getElementById('no-results');
        let visibleCount = 0;

        customerRows.forEach(row => {
            let name = row.getAttribute('data-name');
            // PERUBAHAN: Mengembalikan logika pencarian berdasarkan email
            let email = row.getAttribute('data-email');
            if (name.includes(filter) || email.includes(filter)) {
                row.style.display = ''; // Tampilkan baris
                visibleCount++;
            } else {
                row.style.display = 'none'; // Sembunyikan baris
            }
        });

        if (visibleCount === 0 && customerRows.length > 0) {
            noResults.style.display = 'table-row';
        } else {
            noResults.style.display = 'none';
        }
    });

    function showToast(message, isSuccess) {
        const existingToast = document.querySelector('.toast-notif');
        if (existingToast) existingToast.remove();
        const toast = document.createElement('div');
        toast.className = `toast-notif fixed top-6 right-6 z-50 p-4 rounded-lg shadow-lg text-white font-semibold ${isSuccess ? 'bg-green-500' : 'bg-red-500'}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => document.body.removeChild(toast), 300);
        }, 2500);
    }
</script>

<?php
require_once '../includes/footer.php';
$conn->close();
?>