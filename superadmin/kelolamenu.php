<?php
// Sisipkan header
require_once 'includes/header.php';
// Hubungkan ke database
require_once '../db_connect.php';

// Ambil semua data menu dari database
$menu_items = [];
$sql = "SELECT id, name, description, price, category, stock, image_url, is_available FROM menu ORDER BY name ASC";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $menu_items[] = $row;
    }
}
?>

<div class="container mx-auto">
    <!-- Header Halaman: Judul, Tombol Tambah, dan Pencarian -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <!-- Tombol Tambah Menu -->
            <a href="tambah_menu.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i>
                <span>Tambah Menu Baru</span>
            </a>
        </div>
        <!-- Form Pencarian -->
        <div class="w-1/3">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                    <i class="fas fa-search text-gray-400"></i>
                </span>
                <input type="text" id="searchInput" class="w-full pl-10 pr-4 py-2 border rounded-lg" placeholder="Cari menu...">
            </div>
        </div>
    </div>

    <!-- Grid untuk Daftar Menu -->
    <div id="menuGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (!empty($menu_items)): ?>
            <?php foreach ($menu_items as $item): ?>
                <div class="menu-card bg-white rounded-xl shadow-lg overflow-hidden flex flex-col" data-name="<?= strtolower(htmlspecialchars($item['name'])) ?>">
                    <!-- Gambar Menu -->
                    <div class="h-48 bg-gray-200 flex items-center justify-center">
                         <img src="<?= !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : 'https://placehold.co/400x300/e2e8f0/64748b?text=Gambar' ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover">
                    </div>
                    <!-- Detail Menu -->
                    <div class="p-5 flex-grow">
                        <h3 class="text-xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($item['name']) ?></h3>
                        <p class="text-sm text-gray-600 mb-3 capitalize">Kategori: <?= htmlspecialchars($item['category']) ?></p>
                        <p class="text-lg font-semibold text-green-600 mb-3">Rp <?= number_format($item['price'], 0, ',', '.') ?></p>
                        <p class="text-sm font-medium <?= $item['stock'] <= 5 ? 'text-red-500' : 'text-gray-700' ?>">
                            Stok: <?= $item['stock'] ?>
                        </p>
                    </div>
                     <!-- Tombol Aksi -->
                    <div class="p-5 bg-gray-50 flex justify-end items-center gap-3">
                        <a href="edit_menu.php?id=<?= $item['id'] ?>" class="text-sm bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-4 rounded-lg">
                            <i class="fas fa-pencil-alt mr-1"></i> Edit
                        </a>
                        <a href="hapus_menu.php?id=<?= $item['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus menu ini?')" class="text-sm bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg">
                           <i class="fas fa-trash-alt mr-1"></i> Hapus
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-gray-500 lg:col-span-3 text-center">Belum ada menu yang ditambahkan.</p>
        <?php endif; ?>
    </div>
</div>

<script>
// Script untuk fungsionalitas pencarian
document.getElementById('searchInput').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let menuCards = document.querySelectorAll('.menu-card');
    
    menuCards.forEach(card => {
        let menuName = card.getAttribute('data-name');
        if (menuName.includes(filter)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
});
</script>


<?php
// Sisipkan footer
require_once 'includes/footer.php';
$conn->close();
?>

