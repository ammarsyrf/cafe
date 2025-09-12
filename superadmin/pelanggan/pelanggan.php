<?php
// Path diubah untuk menyesuaikan lokasi folder baru
require_once '../includes/header.php';
require_once '../../db_connect.php';

// Ambil semua data pengguna dari database
$users = [];
// Pastikan Anda sudah menjalankan ALTER TABLE untuk menambahkan kolom email dan phone_number
$sql = "SELECT id, username, email, phone_number, role FROM users ORDER BY username ASC";
$result = $conn->query($sql);
if ($result) {
    while($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<div class="container mx-auto">
    <!-- Header: Pencarian dan Tombol Tambah -->
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
        <div class="w-full sm:w-1/3">
             <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                    <i class="fas fa-search text-gray-400"></i>
                </span>
                <input type="text" id="searchInput" class="w-full pl-10 pr-4 py-2 border rounded-lg" placeholder="Cari nama atau email...">
            </div>
        </div>
        <div>
            <!-- Link ini tidak perlu diubah karena tujuannya ada di folder yang sama -->
            <a href="tambah_pelanggan.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-user-plus mr-2"></i>
                <span>Tambah Pelanggan Baru</span>
            </a>
        </div>
    </div>

    <!-- Grid Kartu Pelanggan -->
    <div id="customerGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (!empty($users)): ?>
            <?php foreach ($users as $user): ?>
                <div class="customer-card bg-white rounded-xl shadow-lg p-5" data-name="<?= strtolower(htmlspecialchars($user['username'])) ?>" data-email="<?= strtolower(htmlspecialchars($user['email'] ?? '')) ?>">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($user['username']) ?></h3>
                            <p class="text-sm font-semibold capitalize text-gray-500"><?= htmlspecialchars($user['role']) ?></p>
                        </div>
                        <div class="flex items-center gap-2">
                             <a href="edit_pelanggan.php?id=<?= $user['id'] ?>" class="text-gray-500 hover:text-blue-600" title="Edit">
                                <i class="fas fa-pencil-alt"></i>
                            </a>
                        </div>
                    </div>
                    <hr class="my-4">
                    <div class="space-y-2 text-sm text-gray-700">
                        <p><i class="fas fa-envelope mr-2 text-gray-400"></i> <?= htmlspecialchars($user['email'] ?? 'Tidak ada email') ?></p>
                        <p><i class="fas fa-phone mr-2 text-gray-400"></i> <?= htmlspecialchars($user['phone_number'] ?? 'Tidak ada no. telp') ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-gray-500 lg:col-span-3 text-center">Belum ada pelanggan yang ditambahkan.</p>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('searchInput').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let customerCards = document.querySelectorAll('.customer-card');
    
    customerCards.forEach(card => {
        let name = card.getAttribute('data-name');
        let email = card.getAttribute('data-email');
        if (name.includes(filter) || email.includes(filter)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
});
</script>

<?php
// Path diubah untuk menyesuaikan lokasi folder baru
require_once '../includes/footer.php';
$conn->close();
?>
