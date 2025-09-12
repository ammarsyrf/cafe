<?php
// File: superadmin/penjualan/penjualan.php
require_once '../includes/header.php';
require_once '../../db_connect.php';

// Atur tanggal default: 1 bulan terakhir
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Ambil data transaksi berdasarkan rentang tanggal
$transactions = [];
$sql = "SELECT 
            t.id as transaction_id, 
            t.transaction_date, 
            u.username as customer_name,
            GROUP_CONCAT(CONCAT(oi.quantity, 'x ', m.name) SEPARATOR ', ') as order_summary,
            t.total_paid, 
            o.status as order_status
        FROM transactions t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN orders o ON t.order_id = o.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN menu m ON oi.menu_id = m.id
        WHERE DATE(t.transaction_date) BETWEEN ? AND ?
        GROUP BY t.id
        ORDER BY t.transaction_date DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
    }
    $stmt->close();
}
?>

<div class="container mx-auto">
    <!-- Header Halaman dan Filter -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <h1 class="text-3xl font-bold text-gray-800">Riwayat Penjualan</h1>
        <form method="GET" class="flex items-center gap-2 bg-white p-2 rounded-lg shadow">
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="border p-2 rounded-lg text-sm">
            <span class="text-gray-500">hingga</span>
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="border p-2 rounded-lg text-sm">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2">
                <i class="fas fa-filter"></i> Filter
            </button>
        </form>
    </div>

    <!-- Tabel Transaksi -->
    <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
        <table class="min-w-full leading-normal">
            <thead>
                <tr class="bg-gray-100">
                    <th class="px-5 py-3 border-b-2 text-left text-xs font-semibold uppercase">ID Transaksi</th>
                    <th class="px-5 py-3 border-b-2 text-left text-xs font-semibold uppercase">Tanggal & Waktu</th>
                    <th class="px-5 py-3 border-b-2 text-left text-xs font-semibold uppercase">Nama Pelanggan</th>
                    <th class="px-5 py-3 border-b-2 text-left text-xs font-semibold uppercase">Pesanan</th>
                    <th class="px-5 py-3 border-b-2 text-right text-xs font-semibold uppercase">Total Bayar (Rp)</th>
                    <th class="px-5 py-3 border-b-2 text-center text-xs font-semibold uppercase">Status</th>
                </tr>
            </thead>
            <tbody id="transaction-table-body">
                <?php if (!empty($transactions)): ?>
                    <?php foreach ($transactions as $tx): ?>
                        <tr class="transaction-row">
                            <td class="px-5 py-5 border-b text-sm">#<?= $tx['transaction_id'] ?></td>
                            <td class="px-5 py-5 border-b text-sm"><?= date('d M Y, H:i', strtotime($tx['transaction_date'])) ?></td>
                            <td class="px-5 py-5 border-b text-sm font-medium"><?= htmlspecialchars($tx['customer_name'] ?? 'Guest') ?></td>
                            <td class="px-5 py-5 border-b text-sm text-gray-600">
                                <?= htmlspecialchars($tx['order_summary'] ?? 'Tidak ada item') ?>
                            </td>
                            <td class="px-5 py-5 border-b text-sm text-right font-semibold"><?= number_format($tx['total_paid'], 0, ',', '.') ?></td>
                            <td class="px-5 py-5 border-b text-sm text-center">
                                <span class="capitalize px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php 
                                        switch($tx['order_status']) {
                                            case 'completed': echo 'bg-green-200 text-green-800'; break;
                                            case 'pending': echo 'bg-yellow-200 text-yellow-800'; break;
                                            case 'cancelled': echo 'bg-red-200 text-red-800'; break;
                                            default: echo 'bg-gray-200 text-gray-800';
                                        }
                                    ?>">
                                    <?= htmlspecialchars($tx['order_status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-10 text-gray-500">Tidak ada transaksi pada rentang tanggal yang dipilih.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once '../includes/footer.php';
$conn->close();
?>

