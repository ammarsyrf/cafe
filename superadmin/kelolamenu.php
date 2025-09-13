<?php
// Memulai session untuk menampilkan notifikasi sukses/gagal
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sisipkan header
require_once 'includes/header.php';
// Hubungkan ke database
require_once '../db_connect.php';

// Ambil semua data menu dari database
$menu_items = [];
$sql = "SELECT id, name, description, price, category, stock, image_url, is_available FROM menu ORDER BY name ASC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $menu_items[] = $row;
    }
}

// Cek notifikasi dari session
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Hapus setelah ditampilkan
}
$error_message = '';
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Hapus setelah ditampilkan
}
?>

<div class="container mx-auto p-4 md:p-6">
    <h1 class="text-4xl font-bold text-gray-800 mb-4">Kelola Menu</h1>
    <p class="text-gray-600 mb-8">Atur daftar menu makanan, minuman, dan snack yang tersedia di restoran Anda.</p>

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

    <!-- Header Halaman: Judul, Tombol Tambah, dan Pencarian -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 bg-white p-4 rounded-lg shadow-md">
        <!-- Tombol Tambah Menu -->
        <a href="tambah_menu.php" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center justify-center mb-4 md:mb-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-lg mr-2" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2Z" />
            </svg>
            <span>Tambah Menu Baru</span>
        </a>
        <!-- Form Pencarian -->
        <div class="w-full md:w-1/3">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-search text-gray-400" viewBox="0 0 16 16">
                        <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z" />
                    </svg>
                </span>
                <input type="text" id="searchInput" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" placeholder="Cari menu...">
            </div>
        </div>
    </div>

    <!-- Grid untuk Daftar Menu -->
    <div id="menuGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php if (!empty($menu_items)): ?>
            <?php foreach ($menu_items as $item): ?>
                <div class="menu-card bg-white rounded-xl shadow-lg overflow-hidden flex flex-col transition-transform transform hover:-translate-y-2" data-name="<?= strtolower(htmlspecialchars($item['name'])) ?>" data-category="<?= strtolower(htmlspecialchars($item['category'])) ?>">
                    <!-- Gambar Menu -->
                    <div class="h-48 bg-gray-200 flex items-center justify-center relative">
                        <img src="../<?= !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : 'https://placehold.co/400x300/e2e8f0/64748b?text=Gambar' ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover">
                        <span class="absolute top-2 right-2 py-1 px-3 text-xs font-semibold rounded-full <?= $item['is_available'] ? 'bg-green-500 text-white' : 'bg-red-500 text-white' ?>">
                            <?= $item['is_available'] ? 'Tersedia' : 'Habis' ?>
                        </span>
                    </div>
                    <!-- Detail Menu -->
                    <div class="p-5 flex-grow">
                        <h3 class="text-xl font-bold text-gray-800 mb-2 truncate"><?= htmlspecialchars($item['name']) ?></h3>
                        <p class="text-sm text-gray-600 mb-3 capitalize">Kategori: <?= htmlspecialchars($item['category']) ?></p>
                        <p class="text-lg font-semibold text-blue-600 mb-3">Rp <?= number_format($item['price'], 0, ',', '.') ?></p>
                        <p class="text-sm font-medium <?= $item['stock'] <= 5 && $item['stock'] > 0 ? 'text-yellow-600' : ($item['stock'] == 0 ? 'text-red-600' : 'text-gray-700') ?>">
                            Stok: <?= $item['stock'] ?>
                        </p>
                    </div>
                    <!-- Tombol Aksi -->
                    <div class="p-4 bg-gray-50 border-t flex justify-end items-center gap-3">
                        <a href="edit_menu.php?id=<?= $item['id'] ?>" class="text-sm bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-4 rounded-lg transition-colors">
                            Edit
                        </a>
                        <a href="hapus_menu.php?id=<?= $item['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus menu \'<?= htmlspecialchars($item['name']) ?>\'? Tindakan ini tidak dapat dibatalkan.')" class="text-sm bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors">
                            Hapus
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full bg-white p-12 rounded-lg shadow-md text-center">
                <h3 class="text-xl font-semibold text-gray-700">Belum Ada Menu</h3>
                <p class="text-gray-500 mt-2">Silakan klik tombol "Tambah Menu Baru" untuk mulai menambahkan menu.</p>
            </div>
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