<?php
// File: superadmin/laporan.php

// Hubungkan ke database terlebih dahulu untuk semua aksi
require_once '../db_connect.php';

// Cek apakah aksi adalah 'cetak_struk'
if (isset($_GET['action']) && $_GET['action'] == 'cetak_struk' && isset($_GET['id'])) {
    $order_id = (int)$_GET['id'];

    // --- LOGIKA UNTUK CETAK STRUK (Tidak diubah) ---
    $order_details = null;
    $order_items = [];

    // Ambil data pesanan utama dari tabel 'orders'
    $sql_order = "SELECT o.*, u_customer.username as customer_username, u_cashier.name as cashier_name
                  FROM orders o 
                  LEFT JOIN users u_customer ON o.user_id = u_customer.id
                  LEFT JOIN users u_cashier ON o.cashier_id = u_cashier.id
                  WHERE o.id = ?";

    if ($stmt_order = $conn->prepare($sql_order)) {
        $stmt_order->bind_param("i", $order_id);
        $stmt_order->execute();
        $result_order = $stmt_order->get_result();

        if ($result_order->num_rows > 0) {
            $order_details = $result_order->fetch_assoc();

            // Ambil item pesanan yang terkait
            $sql_items = "SELECT oi.quantity, m.name, m.price as price, (oi.quantity * m.price) as subtotal
                          FROM order_items oi
                          JOIN menu m ON oi.menu_id = m.id
                          WHERE oi.order_id = ?";

            if ($stmt_items = $conn->prepare($sql_items)) {
                $stmt_items->bind_param("i", $order_id);
                $stmt_items->execute();
                $result_items = $stmt_items->get_result();
                $order_items = $result_items->fetch_all(MYSQLI_ASSOC);
                $stmt_items->close();
            }
        }
        $stmt_order->close();
    }

    if (!$order_details) {
        die("Pesanan tidak ditemukan.");
    }

    $customer_name = $order_details['customer_username'] ?? $order_details['customer_name'] ?? 'Guest';
    $cashier_name = $order_details['cashier_name'] ?? 'N/A';
?>
    <!DOCTYPE html>
    <html lang="id">

    <head>
        <meta charset="UTF-8">
        <title>Struk Pesanan #<?= $order_details['id'] ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inconsolata:wght@400;700&display=swap');

            body {
                font-family: 'Inconsolata', monospace;
                width: 300px;
                margin: 0 auto;
            }

            @media print {
                body {
                    -webkit-print-color-adjust: exact;
                }

                .no-print {
                    display: none;
                }
            }
        </style>
    </head>

    <body class="bg-gray-100 p-4">
        <div class="bg-white p-4">
            <div class="text-center mb-4">
                <h1 class="text-xl font-bold">NAMA KAFE ANDA</h1>
                <p class="text-xs">Jl. Alamat Kafe No. 123</p>
                <p class="text-xs">Telp: 081234567890</p>
            </div>
            <div class="text-xs border-t border-dashed border-black pt-2">
                <div class="flex justify-between"><span>No. Pesanan:</span><span>#<?= $order_details['id'] ?></span></div>
                <div class="flex justify-between"><span>Tanggal:</span><span><?= date('d/m/y H:i', strtotime($order_details['created_at'])) ?></span></div>
                <div class="flex justify-between"><span>Pelanggan:</span><span><?= htmlspecialchars($customer_name) ?></span></div>
                <div class="flex justify-between"><span>Kasir:</span><span><?= htmlspecialchars($cashier_name) ?></span></div>
            </div>
            <div class="border-t border-b border-dashed border-black my-2 py-2">
                <?php foreach ($order_items as $item): ?>
                    <div class="text-xs mb-1">
                        <p><?= htmlspecialchars($item['name']) ?></p>
                        <div class="flex justify-between">
                            <span><?= $item['quantity'] ?> x <?= number_format($item['price'], 0, ',', '.') ?></span>
                            <span><?= number_format($item['subtotal'], 0, ',', '.') ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-xs space-y-1">
                <div class="flex justify-between"><span>Subtotal</span><span>Rp <?= number_format($order_details['subtotal'], 0, ',', '.') ?></span></div>
                <div class="flex justify-between"><span>Diskon</span><span>Rp <?= number_format($order_details['discount_amount'], 0, ',', '.') ?></span></div>
                <div class="flex justify-between"><span>PPN (11%)</span><span>Rp <?= number_format($order_details['tax'], 0, ',', '.') ?></span></div>
                <div class="flex justify-between font-bold text-sm mt-1 border-t border-dashed pt-1"><span>TOTAL</span><span>Rp <?= number_format($order_details['total_amount'], 0, ',', '.') ?></span></div>
                <div class="flex justify-between"><span>Metode Bayar</span><span class="capitalize"><?= htmlspecialchars($order_details['payment_method']) ?></span></div>
            </div>
            <div class="text-center text-xs mt-6">
                <p>Terima kasih atas kunjungan Anda!</p>
            </div>
        </div>
        <div class="text-center mt-4 no-print">
            <button onclick="window.print()" class="bg-blue-500 text-white px-4 py-2 rounded">Cetak Struk</button>
            <a href="laporan.php" class="bg-gray-500 text-white px-4 py-2 rounded">Kembali</a>
        </div>
    </body>

    </html>
<?php
    $conn->close();
    exit(); // Hentikan script agar tidak menampilkan halaman laporan utama

    // --- [BARU] LOGIKA UNTUK CETAK EXCEL (FORMAT CSV) ---
} elseif (isset($_GET['action']) && $_GET['action'] == 'cetak_excel') {

    // Ambil rentang tanggal dari URL
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');

    // Set header untuk memberitahu browser agar men-download file
    $filename = "Laporan_Penjualan_" . $start_date . "_sampai_" . $end_date . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Buka output stream PHP untuk menulis file CSV
    $output = fopen('php://output', 'w');

    // Tulis baris header untuk CSV
    fputcsv($output, ['ID Pesanan', 'Tanggal', 'Nama Pelanggan', 'Total Bayar']);

    // Query data pesanan sesuai rentang tanggal (sama seperti di halaman laporan)
    $sql_export = "SELECT o.id, o.created_at, o.total_amount, 
                          COALESCE(u.username, o.customer_name) as customer_name
                   FROM orders o
                   LEFT JOIN users u ON o.user_id = u.id
                   WHERE DATE(o.created_at) BETWEEN ? AND ?
                   ORDER BY o.created_at ASC"; // Urutkan dari yang terlama untuk laporan

    if ($stmt_export = $conn->prepare($sql_export)) {
        $stmt_export->bind_param("ss", $start_date, $end_date);
        $stmt_export->execute();
        $result_export = $stmt_export->get_result();

        // Loop melalui setiap baris hasil query dan tulis ke file CSV
        while ($row = $result_export->fetch_assoc()) {
            fputcsv($output, [
                '#' . $row['id'],
                date('d-m-Y H:i:s', strtotime($row['created_at'])),
                $row['customer_name'] ?? 'Guest',
                $row['total_amount'] // Simpan sebagai angka murni agar mudah dihitung di Excel
            ]);
        }
        $stmt_export->close();
    }

    fclose($output);
    $conn->close();
    exit(); // Hentikan script setelah file CSV dibuat
}

// --- JIKA BUKAN AKSI CETAK, TAMPILKAN HALAMAN LAPORAN BIASA ---
require_once 'includes/header.php';

// Atur tanggal default: 1 bulan terakhir
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Inisialisasi variabel statistik
$total_revenue = 0;
$total_transactions = 0;
$best_seller = ['name' => 'N/A', 'quantity' => 0];
$orders_data = [];

// Query untuk mengambil data pesanan
$sql_orders = "SELECT o.id, o.created_at, o.total_amount, 
                      COALESCE(u.username, o.customer_name) as customer_name
               FROM orders o
               LEFT JOIN users u ON o.user_id = u.id
               WHERE DATE(o.created_at) BETWEEN ? AND ?
               ORDER BY o.created_at DESC";

if ($stmt = $conn->prepare($sql_orders)) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $orders_data = $result->fetch_all(MYSQLI_ASSOC);
        $total_transactions = $result->num_rows;
        foreach ($orders_data as $order) {
            $total_revenue += $order['total_amount'];
        }
    }
    $stmt->close();
}

// Query untuk mencari produk terlaris
$sql_bestseller = "SELECT m.name, SUM(oi.quantity) as total_quantity 
                   FROM order_items oi
                   JOIN orders o ON oi.order_id = o.id
                   JOIN menu m ON oi.menu_id = m.id
                   WHERE DATE(o.created_at) BETWEEN ? AND ?
                   GROUP BY m.name ORDER BY total_quantity DESC LIMIT 1";

if ($stmt_bs = $conn->prepare($sql_bestseller)) {
    $stmt_bs->bind_param("ss", $start_date, $end_date);
    $stmt_bs->execute();
    $result_bs = $stmt_bs->get_result();
    if ($result_bs && $result_bs->num_rows > 0) {
        $best_seller_row = $result_bs->fetch_assoc();
        $best_seller['name'] = $best_seller_row['name'];
        $best_seller['quantity'] = $best_seller_row['total_quantity'];
    }
    $stmt_bs->close();
}
?>

<div class="container mx-auto p-4 md:p-6">
    <!-- Header dan Filter -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <h1 class="text-3xl font-bold text-gray-800">Laporan Penjualan</h1>
        <div class="flex items-center gap-4">
            <form method="GET" class="flex items-center gap-2 bg-white p-2 rounded-lg shadow">
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="border p-2 rounded-lg text-sm">
                <span class="text-gray-500">hingga</span>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="border p-2 rounded-lg text-sm">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L12 12.414l-8.707-8.707A1 1 0 013 6V4z"></path>
                    </svg> Filter
                </button>
            </form>
            <!-- [DIUBAH] Tombol Cetak PDF menjadi Cetak Excel -->
            <a href="laporan.php?action=cetak_excel&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2 shadow">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                Cetak Excel
            </a>
        </div>
    </div>

    <!-- Ringkasan Statistik -->
    <div id="summary-section">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white p-6 rounded-xl shadow-lg flex items-center gap-4">
                <div class="bg-green-100 p-4 rounded-full"><svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v.01"></path>
                    </svg></div>
                <div>
                    <p class="text-sm text-gray-500">Total Pendapatan</p>
                    <p class="text-2xl font-bold text-gray-800">Rp <?= number_format($total_revenue, 0, ',', '.') ?></p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-lg flex items-center gap-4">
                <div class="bg-blue-100 p-4 rounded-full"><svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg></div>
                <div>
                    <p class="text-sm text-gray-500">Total Pesanan</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $total_transactions ?></p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-lg flex items-center gap-4">
                <div class="bg-yellow-100 p-4 rounded-full"><svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-12v4m-2-2h4m5 6v4m-2-2h4M17 3l-4.5 5.5L17 14l-5.5 4.5"></path>
                    </svg></div>
                <div>
                    <p class="text-sm text-gray-500">Produk Terlaris</p>
                    <p class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($best_seller['name']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Laporan -->
    <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
        <table id="report-table" class="min-w-full leading-normal">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID Pesanan</th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tanggal</th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama Pelanggan</th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Total Bayar (Rp)</th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders_data)): ?>
                    <?php foreach ($orders_data as $order): ?>
                        <tr>
                            <td class="px-5 py-4 border-b border-gray-200 text-sm">#<?= $order['id'] ?></td>
                            <td class="px-5 py-4 border-b border-gray-200 text-sm"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></td>
                            <td class="px-5 py-4 border-b border-gray-200 text-sm font-medium"><?= htmlspecialchars($order['customer_name'] ?? 'Guest') ?></td>
                            <td class="px-5 py-4 border-b border-gray-200 text-sm text-right font-semibold"><?= number_format($order['total_amount'], 0, ',', '.') ?></td>
                            <td class="px-5 py-4 border-b border-gray-200 text-sm text-center">
                                <a href="laporan.php?action=cetak_struk&id=<?= $order['id'] ?>" target="_blank" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-1 px-3 rounded text-xs">
                                    Cetak Struk
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-10 text-gray-500">Tidak ada pesanan pada rentang tanggal yang dipilih.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- [DIHAPUS] Seluruh script JavaScript untuk membuat PDF dihapus karena tidak diperlukan lagi -->

<?php
require_once 'includes/footer.php';
$conn->close();
?>