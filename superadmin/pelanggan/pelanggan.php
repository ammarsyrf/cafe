<?php
// File: superadmin/pelanggan/pelanggan.php

// Panggil header dan koneksi database dengan path yang benar
require_once '../includes/header.php';
require_once '../../db_connect.php';

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
?>

<div class="container mx-auto">
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
</script>

<?php
require_once '../includes/footer.php';
$conn->close();
?>