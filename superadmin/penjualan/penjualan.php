<?php
// File: superadmin/penjualan/penjualan.php
require_once '../includes/header.php';
require_once '../../db_connect.php';

// Atur tanggal default: 1 bulan terakhir
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Ambil data transaksi berdasarkan rentang tanggal
$transactions = [];
/*
 * Query ini telah diperbaiki untuk menggunakan tabel 'orders' sebagai sumber utama.
 * - Mengambil 'discount_amount' dan 'total_amount' dari tabel 'orders'.
 * - Menghilangkan ketergantungan pada tabel 'transactions' yang mungkin strukturnya berbeda.
 * - Menggunakan 'created_at' dari tabel 'orders' untuk filter tanggal.
 */
$sql = "SELECT 
            o.id as transaction_id, 
            o.created_at as transaction_date, 
            o.payment_method,
            o.discount_amount as member_discount, -- Diperbaiki: Mengambil dari tabel orders
            -- Gunakan nama dari tabel user jika ada (member), jika tidak, gunakan nama customer dari tabel order
            COALESCE(u.username, o.customer_name) as customer_name,
            -- Gabungkan item pesanan menjadi satu ringkasan
            GROUP_CONCAT(CONCAT(oi.quantity, 'x ', m.name) SEPARATOR ', ') as order_summary,
            o.total_amount as total_paid, -- Diperbaiki: Mengambil dari tabel orders
            o.status as order_status
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN menu m ON oi.menu_id = m.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY o.id
        ORDER BY o.created_at DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
    } else {
        echo "Error executing query: " . $conn->error;
    }
    $stmt->close();
} else {
    echo "Error preparing statement: " . $conn->error;
}

// Hitung total untuk ringkasan statistik
$total_penjualan = array_sum(array_column($transactions, 'total_paid'));
$total_diskon = array_sum(array_column($transactions, 'member_discount'));
$jumlah_transaksi = count($transactions);
?>

<div class="container mx-auto p-4 md:p-6">
    <!-- Header Halaman -->
    <div class="flex flex-col md:flex-row justify-between items-start mb-6 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Riwayat Penjualan</h1>
            <p class="text-gray-600 mt-1">Menampilkan data dari <span class="font-semibold"><?= date('d M Y', strtotime($start_date)) ?></span> hingga <span class="font-semibold"><?= date('d M Y', strtotime($end_date)) ?></span>.</p>
        </div>
        <!-- Form Filter Tanggal -->
        <form method="GET" action="" class="flex items-center gap-2 bg-white p-3 rounded-lg shadow-md w-full md:w-auto">
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="border-gray-300 p-2 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
            <span class="text-gray-500">hingga</span>
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="border-gray-300 p-2 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-funnel-fill" viewBox="0 0 16 16">
                    <path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.74.439L7 11.583V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5v-2z" />
                </svg>
                <span>Filter</span>
            </button>
        </form>
    </div>

    <!-- Ringkasan Statistik -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl shadow-lg flex items-center gap-4">
            <div class="bg-blue-100 p-3 rounded-full"><svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v.01"></path>
                </svg></div>
            <div>
                <p class="text-sm text-gray-500">Total Penjualan</p>
                <p class="text-2xl font-bold text-gray-800">Rp <?= number_format($total_penjualan, 0, ',', '.') ?></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-lg flex items-center gap-4">
            <div class="bg-red-100 p-3 rounded-full"><svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                </svg></div>
            <div>
                <p class="text-sm text-gray-500">Total Diskon</p>
                <p class="text-2xl font-bold text-gray-800">Rp <?= number_format($total_diskon, 0, ',', '.') ?></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-lg flex items-center gap-4">
            <div class="bg-green-100 p-3 rounded-full"><svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M7 9l4-4 4 4M4 12h16M4 16h16"></path>
                </svg></div>
            <div>
                <p class="text-sm text-gray-500">Jumlah Transaksi</p>
                <p class="text-2xl font-bold text-gray-800"><?= $jumlah_transaksi ?></p>
            </div>
        </div>
    </div>

    <!-- Tabel Transaksi -->
    <div class="bg-white rounded-xl shadow-lg">
        <div class="p-4 border-b">
            <input type="text" id="searchInput" class="w-full md:w-1/3 pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" placeholder="Cari ID atau nama pelanggan...">
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tanggal & Waktu</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama Pelanggan</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Pesanan</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Metode Bayar</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Diskon (Rp)</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Total Bayar (Rp)</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody id="transaction-table-body">
                    <?php if (!empty($transactions)): ?>
                        <?php foreach ($transactions as $tx): ?>
                            <tr class="hover:bg-gray-50 transaction-row" data-id="#<?= $tx['transaction_id'] ?>" data-name="<?= strtolower(htmlspecialchars($tx['customer_name'] ?? 'guest')) ?>">
                                <td class="px-5 py-4 border-b border-gray-200 text-sm">#<?= $tx['transaction_id'] ?></td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm"><?= date('d M Y, H:i', strtotime($tx['transaction_date'])) ?></td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm font-medium text-gray-900"><?= htmlspecialchars($tx['customer_name'] ?? 'Guest') ?></td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm text-gray-600 max-w-xs truncate" title="<?= htmlspecialchars($tx['order_summary'] ?? 'Tidak ada item') ?>">
                                    <?= htmlspecialchars($tx['order_summary'] ?? 'Tidak ada item') ?>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm capitalize"><?= htmlspecialchars($tx['payment_method'] ?? '-') ?></td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm text-right text-red-600 font-semibold"><?= number_format($tx['member_discount'] ?? 0, 0, ',', '.') ?></td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm text-right font-bold text-gray-900"><?= number_format($tx['total_paid'], 0, ',', '.') ?></td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm text-center">
                                    <span class="capitalize px-3 py-1 text-xs font-semibold rounded-full 
                                        <?php
                                        switch ($tx['order_status']) {
                                            case 'completed':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'pending':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'cancelled':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?= htmlspecialchars($tx['order_status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-10 text-gray-500">Tidak ada transaksi pada rentang tanggal yang dipilih.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.getElementById('searchInput').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('.transaction-row');

        rows.forEach(row => {
            let transactionId = row.getAttribute('data-id').toLowerCase();
            let customerName = row.getAttribute('data-name').toLowerCase();

            if (transactionId.includes(filter) || customerName.includes(filter)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
</script>

<?php
require_once '../includes/footer.php';
$conn->close();
?>