<?php
// File: superadmin/penjualan/penjualan.php
require_once '../includes/header.php';
require_once '../../db_connect.php';

// Ambil daftar semua kasir dari tabel users
$cashiers = [];
// Asumsi: pengguna dengan role 'kasir' dianggap sebagai kasir.
$cashierSql = "SELECT name FROM users WHERE role = 'cashier' ORDER BY name ASC";
if ($cashierResult = $conn->query($cashierSql)) {
    while ($row = $cashierResult->fetch_assoc()) {
        $cashiers[] = $row['name'];
    }
    $cashierResult->free();
}

// Atur tanggal default: 1 bulan terakhir
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Ambil data transaksi berdasarkan rentang tanggal
$transactions = [];

/*
 * ==================================================================
 * PEMBARUAN QUERY SQL UNTUK DETAIL PESANAN
 * ==================================================================
 * - Menghilangkan GROUP_CONCAT dan GROUP BY untuk mengambil setiap item pesanan secara individual.
 * - Data ini akan dikelompokkan menggunakan PHP untuk membuat struktur dropdown.
 * - Menambahkan detail item: kuantitas, nama menu, dan harga item.
 */
$sql = "SELECT 
            o.id as transaction_id, 
            o.created_at as transaction_date, 
            o.payment_method,
            o.discount_amount as member_discount,
            o.customer_name as customer_name,
            cashier.name as cashier_name,
            o.total_amount as total_paid,
            o.status as order_status,
            oi.quantity,
            m.name as menu_name,
            oi.price as item_price
        FROM orders o
        LEFT JOIN users cashier ON o.cashier_id = cashier.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN menu m ON oi.menu_id = m.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        ORDER BY o.created_at DESC, o.id ASC";

$grouped_transactions = [];
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $transaction_id = $row['transaction_id'];
            if (!isset($grouped_transactions[$transaction_id])) {
                $grouped_transactions[$transaction_id] = [
                    'transaction_id' => $row['transaction_id'],
                    'transaction_date' => $row['transaction_date'],
                    'payment_method' => $row['payment_method'],
                    'member_discount' => $row['member_discount'],
                    'customer_name' => $row['customer_name'],
                    'cashier_name' => $row['cashier_name'],
                    'total_paid' => $row['total_paid'],
                    'order_status' => $row['order_status'],
                    'items' => []
                ];
            }
            if ($row['menu_name']) {
                $grouped_transactions[$transaction_id]['items'][] = [
                    'quantity' => $row['quantity'],
                    'menu_name' => $row['menu_name'],
                    'item_price' => $row['item_price']
                ];
            }
        }
    } else {
        echo "Error executing query: " . $conn->error;
    }
    $stmt->close();
} else {
    echo "Error preparing statement: " . $conn->error;
}


// Hitung total untuk ringkasan statistik
$total_penjualan = array_sum(array_column($grouped_transactions, 'total_paid'));
$total_diskon = array_sum(array_column($grouped_transactions, 'member_discount'));
$jumlah_transaksi = count($grouped_transactions);
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

    <!-- Daftar Semua Kasir -->
    <div class="mb-8">
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Daftar Kasir Terdaftar</h2>
            <?php if (!empty($cashiers)): ?>
                <div class="flex flex-wrap gap-x-6 gap-y-3">
                    <?php foreach ($cashiers as $cashierName): ?>
                        <span class="bg-gray-200 text-gray-800 text-sm font-medium px-4 py-2 rounded-full flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-circle mr-2" viewBox="0 0 16 16">
                                <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                                <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z" />
                            </svg>
                            <?= htmlspecialchars($cashierName) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500">Tidak ada data kasir yang ditemukan.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabel Transaksi -->
    <div class="bg-white rounded-xl shadow-lg">
        <div class="p-4 border-b">
            <input type="text" id="searchInput" class="w-full md:w-1/3 pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" placeholder="Cari ID, nama pelanggan, atau kasir...">
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tanggal & Waktu</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama Pelanggan</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Kasir Transaksi</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Pesanan</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Metode Bayar</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Diskon (Rp)</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Total Bayar (Rp)</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody id="transaction-table-body">
                    <?php if (!empty($grouped_transactions)): ?>
                        <?php foreach ($grouped_transactions as $tx_id => $tx): ?>
                            <!-- Baris Utama (Bisa Diklik) -->
                            <tr class="hover:bg-gray-100 transaction-row cursor-pointer"
                                data-id="#<?= $tx['transaction_id'] ?>"
                                data-name="<?= strtolower(htmlspecialchars($tx['customer_name'] ?? 'guest')) ?>"
                                data-cashier="<?= strtolower(htmlspecialchars($tx['cashier_name'] ?? '')) ?>"
                                onclick="toggleDetails(this)">
                                <td class="px-5 py-4 border-b border-gray-200 text-sm">
                                    <span class="flex items-center">
                                        #<?= $tx['transaction_id'] ?>
                                        <svg class="dropdown-icon w-4 h-4 ml-2 text-gray-500 transition-transform transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </span>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm"><?= date('d M Y, H:i', strtotime($tx['transaction_date'])) ?></td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm font-medium text-gray-900"><?= htmlspecialchars($tx['customer_name'] ?? 'Guest') ?></td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm">
                                    <?php
                                    if (!empty($tx['cashier_name'])) {
                                        echo htmlspecialchars($tx['cashier_name']);
                                    } else {
                                        echo '<span class="font-semibold text-red-600">ID Kasir Hilang!</span>';
                                    }
                                    ?>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm text-gray-600">
                                    <?= count($tx['items']) ?> item
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
                            <!-- Baris Detail (Tersembunyi) -->
                            <tr class="transaction-details-row bg-gray-50" style="display: none;">
                                <td colspan="9" class="p-4 border-b border-gray-200">
                                    <div class="max-w-md">
                                        <h4 class="text-md font-bold text-gray-700 mb-2">Detail Pesanan:</h4>
                                        <?php if (!empty($tx['items'])): ?>
                                            <?php $subtotal = 0; ?>
                                            <ul class="space-y-1 text-sm text-gray-600">
                                                <?php foreach ($tx['items'] as $item): ?>
                                                    <?php $subtotal += $item['quantity'] * $item['item_price']; ?>
                                                    <li class="flex justify-between">
                                                        <span>
                                                            <?= htmlspecialchars($item['quantity']) ?>x <?= htmlspecialchars($item['menu_name']) ?>
                                                        </span>
                                                        <span class="font-mono">
                                                            @ Rp <?= number_format($item['item_price'], 0, ',', '.') ?>
                                                        </span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <hr class="my-2 border-gray-300">
                                            <div class="text-sm text-gray-800 space-y-1">
                                                <?php
                                                $ppn = $subtotal * 0.11; // PPN 11%
                                                $total_biaya_detail = $subtotal + $ppn;
                                                ?>
                                                <div class="flex justify-between">
                                                    <span class="font-semibold">Subtotal:</span>
                                                    <span class="font-mono">Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="font-semibold">PPN (11%):</span>
                                                    <span class="font-mono">Rp <?= number_format($ppn, 0, ',', '.') ?></span>
                                                </div>
                                                <div class="flex justify-between font-bold text-md mt-1">
                                                    <span>Total Biaya:</span>
                                                    <span class="font-mono">Rp <?= number_format($total_biaya_detail, 0, ',', '.') ?></span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-sm text-gray-500">Tidak ada item dalam pesanan ini.</p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-10 text-gray-500">Tidak ada transaksi pada rentang tanggal yang dipilih.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function toggleDetails(rowElement) {
        const detailsRow = rowElement.nextElementSibling;
        const icon = rowElement.querySelector('.dropdown-icon');

        if (detailsRow.style.display === 'none') {
            detailsRow.style.display = ''; // Menampilkan baris detail
            icon.classList.add('rotate-180');
        } else {
            detailsRow.style.display = 'none'; // Menyembunyikan baris detail
            icon.classList.remove('rotate-180');
        }
    }

    document.getElementById('searchInput').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('.transaction-row');

        rows.forEach(row => {
            let transactionId = row.getAttribute('data-id').toLowerCase();
            let customerName = row.getAttribute('data-name').toLowerCase();
            let cashierName = row.getAttribute('data-cashier').toLowerCase();
            let detailsRow = row.nextElementSibling;

            if (transactionId.includes(filter) || customerName.includes(filter) || cashierName.includes(filter)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
                // Pastikan baris detail juga tersembunyi saat filter
                if (detailsRow && detailsRow.classList.contains('transaction-details-row')) {
                    detailsRow.style.display = 'none';
                    const icon = row.querySelector('.dropdown-icon');
                    if (icon) {
                        icon.classList.remove('rotate-180');
                    }
                }
            }
        });
    });
</script>

<?php
require_once '../includes/footer.php';
$conn->close();
?>