<?php
// File: superadmin.php
// Halaman untuk super admin/owner

require_once 'db_connect.php';

// Cek apakah user sudah login sebagai superadmin
// TODO: implementasi sistem login yang benar
$is_authenticated_admin = true;
if (!$is_authenticated_admin) {
    header("Location: login.php");
    exit();
}

// Mengambil data dashboard
$today_revenue = 0;
$total_transactions = 0;
$avg_purchase = 0;

$sql = "SELECT SUM(total_paid) AS total_revenue FROM transactions WHERE DATE(transaction_date) = CURDATE()";
$result = $conn->query($sql);
if ($row = $result->fetch_assoc()) {
    $today_revenue = $row['total_revenue'] ?? 0;
}

$sql = "SELECT COUNT(*) AS total_tx FROM transactions WHERE DATE(transaction_date) = CURDATE()";
$result = $conn->query($sql);
if ($row = $result->fetch_assoc()) {
    $total_transactions = $row['total_tx'] ?? 0;
}

if ($total_transactions > 0) {
    $avg_purchase = $today_revenue / $total_transactions;
}

// Data menu terlaris
$best_sellers = [];
$sql = "SELECT m.name, SUM(oi.quantity) AS total_quantity 
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN menu m ON oi.menu_id = m.id
        WHERE o.status = 'paid' OR o.status = 'completed'
        GROUP BY m.name
        ORDER BY total_quantity DESC
        LIMIT 5";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $best_sellers[] = $row;
}

// Data stok habis
$low_stock_menu = [];
$sql = "SELECT * FROM menu WHERE stock <= 5 ORDER BY stock ASC";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $low_stock_menu[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halaman Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex">
    <!-- Sidebar -->
    <aside class="bg-gray-800 text-white w-64 p-4 space-y-4">
        <h2 class="text-2xl font-bold mb-6">Admin</h2>
        <nav>
            <a href="#dashboard" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">Dashboard</a>
            <a href="#kelola-menu" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">Kelola Menu</a>
            <a href="#laporan" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">Laporan</a>
            <a href="#ulasan" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">Ulasan</a>
            <a href="#pengaturan" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">Pengaturan</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-grow p-8">
        <header class="mb-8">
            <h1 class="text-4xl font-bold text-gray-800">Dashboard Super Admin</h1>
        </header>

        <!-- Dashboard Section -->
        <section id="dashboard" class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-700 mb-4">Ringkasan Hari Ini</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                <div class="bg-blue-100 p-4 rounded-lg text-blue-800">
                    <p class="text-sm font-semibold">Pendapatan Hari Ini</p>
                    <p class="text-2xl font-bold">Rp <?= number_format($today_revenue, 0, ',', '.') ?></p>
                </div>
                <div class="bg-green-100 p-4 rounded-lg text-green-800">
                    <p class="text-sm font-semibold">Jumlah Transaksi</p>
                    <p class="text-2xl font-bold"><?= $total_transactions ?></p>
                </div>
                <div class="bg-purple-100 p-4 rounded-lg text-purple-800">
                    <p class="text-sm font-semibold">Rata-rata Pembelian</p>
                    <p class="text-2xl font-bold">Rp <?= number_format($avg_purchase, 0, ',', '.') ?></p>
                </div>
                <div class="bg-red-100 p-4 rounded-lg text-red-800">
                    <p class="text-sm font-semibold">Menu Stok Habis/Sedikit</p>
                    <p class="text-2xl font-bold"><?= count($low_stock_menu) ?></p>
                </div>
            </div>

            <div class="mt-8">
                <h3 class="text-xl font-semibold mb-4">Menu Terlaris</h3>
                <ul class="space-y-2">
                    <?php foreach ($best_sellers as $item): ?>
                        <li class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span><?= htmlspecialchars($item['name']) ?></span>
                            <span class="font-semibold text-gray-800"><?= $item['total_quantity'] ?> Terjual</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="mt-8">
                <h3 class="text-xl font-semibold mb-4">Menu dengan Stok Rendah</h3>
                <ul class="space-y-2">
                    <?php foreach ($low_stock_menu as $item): ?>
                        <li class="flex justify-between items-center p-3 bg-red-50 rounded-lg">
                            <span class="text-red-800 font-medium"><?= htmlspecialchars($item['name']) ?></span>
                            <span class="text-red-800 font-semibold">Stok: <?= $item['stock'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <!-- TODO: Kelola Menu, Laporan, Ulasan, Pengaturan -->
        <section id="kelola-menu" class="hidden">
            <h2 class="text-2xl font-bold text-gray-700 mb-4">Kelola Menu</h2>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <!-- CRUD menu items here -->
                <p>Fitur CRUD menu akan diimplementasikan di sini. Anda dapat menambah, mengedit, dan menghapus menu.</p>
            </div>
        </section>
        <section id="laporan" class="hidden">
            <h2 class="text-2xl font-bold text-gray-700 mb-4">Laporan</h2>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <!-- Report generation here -->
                <p>Di sini Anda bisa memfilter dan mencetak laporan transaksi, pendapatan, dll.</p>
            </div>
        </section>
        <section id="ulasan" class="hidden">
            <h2 class="text-2xl font-bold text-gray-700 mb-4">Ulasan</h2>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <!-- Reviews will be displayed here -->
                <p>Ulasan dari pelanggan akan ditampilkan di sini.</p>
            </div>
        </section>
        <section id="pengaturan" class="hidden">
            <h2 class="text-2xl font-bold text-gray-700 mb-4">Pengaturan</h2>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <!-- Settings for customization -->
                <p>Hanya super admin yang dapat mengakses halaman ini untuk mengubah tampilan web.</p>
            </div>
        </section>
    </main>
</body>

</html>