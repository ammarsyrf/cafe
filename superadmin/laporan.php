<?php
// File: superadmin/laporan.php

// Hubungkan ke database terlebih dahulu untuk semua aksi
require_once '../db_connect.php';

// --- [BARU] AKSI UNTUK MENGAMBIL DETAIL PESANAN (JSON) ---
if (isset($_GET['action']) && $_GET['action'] == 'get_order_details' && isset($_GET['id'])) {
    $order_id = (int)$_GET['id'];
    $response = ['success' => false, 'message' => 'Pesanan tidak ditemukan.'];

    $order_details = null;
    $order_items = [];

    // [DIPERBAIKI] Ambil data pesanan utama, join ke tabel 'members' untuk nama pelanggan
    $sql_order = "SELECT o.*, 
                         COALESCE(NULLIF(TRIM(mem.name), ''), NULLIF(TRIM(o.customer_name), ''), 'Guest') as customer_name_final, 
                         u_cashier.name as cashier_name
                  FROM orders o 
                  LEFT JOIN members mem ON o.user_id = mem.id
                  LEFT JOIN users u_cashier ON o.cashier_id = u_cashier.id
                  WHERE o.id = ?";

    if ($stmt_order = $conn->prepare($sql_order)) {
        $stmt_order->bind_param("i", $order_id);
        $stmt_order->execute();
        $result_order = $stmt_order->get_result();

        if ($result_order->num_rows > 0) {
            $order_details = $result_order->fetch_assoc();

            // Ambil item pesanan yang terkait
            $sql_items = "SELECT oi.quantity, m.name, oi.price as price, (oi.quantity * oi.price) as subtotal
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

            $response = [
                'success' => true,
                'details' => $order_details,
                'items' => $order_items
            ];
        }
        $stmt_order->close();
    }

    // Kembalikan response sebagai JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    $conn->close();
    exit();
}

// --- LOGIKA UNTUK CETAK EXCEL (FORMAT CSV) ---
if (isset($_GET['action']) && $_GET['action'] == 'cetak_excel') {
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');

    $filename = "Laporan_Penjualan_" . $start_date . "_sampai_" . $end_date . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID Pesanan', 'Tanggal', 'Nama Pelanggan', 'Total Bayar', 'Metode Pembayaran', 'Kasir']);

    // [DIPERBAIKI] Query untuk ekspor, join ke tabel 'members' untuk nama pelanggan
    $sql_export = "SELECT o.id, o.created_at, o.total_amount, o.payment_method,
                          COALESCE(NULLIF(TRIM(mem.name), ''), NULLIF(TRIM(o.customer_name), ''), 'Guest') as customer_name,
                          COALESCE(uc.name, 'N/A') as cashier_name
                   FROM orders o
                   LEFT JOIN members mem ON o.user_id = mem.id
                   LEFT JOIN users uc ON o.cashier_id = uc.id
                   WHERE DATE(o.created_at) BETWEEN ? AND ?
                   ORDER BY o.created_at ASC";

    if ($stmt_export = $conn->prepare($sql_export)) {
        $stmt_export->bind_param("ss", $start_date, $end_date);
        $stmt_export->execute();
        $result_export = $stmt_export->get_result();

        while ($row = $result_export->fetch_assoc()) {
            fputcsv($output, [
                '#' . $row['id'],
                date('d-m-Y H:i:s', strtotime($row['created_at'])),
                $row['customer_name'],
                $row['total_amount'],
                ucfirst($row['payment_method']),
                $row['cashier_name']
            ]);
        }
        $stmt_export->close();
    }

    fclose($output);
    $conn->close();
    exit();
}

// --- JIKA BUKAN AKSI DI ATAS, TAMPILKAN HALAMAN LAPORAN BIASA ---
require_once 'includes/header.php';

// Atur tanggal default
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Inisialisasi variabel statistik
$total_revenue = 0;
$total_transactions = 0;
$best_seller = ['name' => 'N/A', 'quantity' => 0];
$orders_data = [];

// Query untuk mengambil data pesanan (Query ini sudah benar)
$sql_orders = "SELECT o.id, o.created_at, o.total_amount, 
                       COALESCE(u.name, o.customer_name, 'Guest') as customer_name
                FROM orders o
                LEFT JOIN members u ON o.user_id = u.id
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
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders_data)): ?>
                    <?php foreach ($orders_data as $order): ?>
                        <tr class="hover:bg-gray-100 cursor-pointer" data-order-id="<?= $order['id'] ?>">
                            <td class="px-5 py-4 border-b border-gray-200 text-sm">#<?= $order['id'] ?></td>
                            <td class="px-5 py-4 border-b border-gray-200 text-sm"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></td>
                            <td class="px-5 py-4 border-b border-gray-200 text-sm font-medium"><?= htmlspecialchars($order['customer_name'] ?? 'Guest') ?></td>
                            <td class="px-5 py-4 border-b border-gray-200 text-sm text-right font-semibold"><?= number_format($order['total_amount'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center py-10 text-gray-500">Tidak ada pesanan pada rentang tanggal yang dipilih.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal untuk Detail Pesanan -->
<div id="detailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 m-4 animate-fade-in-up">
        <div class="flex justify-between items-center border-b pb-3 mb-3">
            <h2 class="text-xl font-bold text-gray-800">Detail Pesanan <span id="modalOrderId" class="text-blue-600"></span></h2>
            <button id="closeModal" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
        </div>
        <div id="modalContent" class="text-sm">
            <div class="text-center py-8">
                <p class="text-gray-500">Memuat data...</p>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tableBody = document.querySelector('#report-table tbody');
        const modal = document.getElementById('detailModal');
        const closeModalBtn = document.getElementById('closeModal');
        const modalContent = document.getElementById('modalContent');
        const modalOrderId = document.getElementById('modalOrderId');

        if (!tableBody || !modal) return;

        const openModal = () => modal.classList.remove('hidden');
        const closeModal = () => modal.classList.add('hidden');

        tableBody.addEventListener('click', function(e) {
            const row = e.target.closest('tr');
            if (row && row.dataset.orderId) {
                const orderId = row.dataset.orderId;
                openModal();
                modalOrderId.textContent = `#${orderId}`;
                modalContent.innerHTML = '<p class="text-center py-8 text-gray-500">Memuat data...</p>';

                fetch(`laporan.php?action=get_order_details&id=${orderId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            renderModalContent(data.details, data.items);
                        } else {
                            modalContent.innerHTML = `<p class="text-center py-8 text-red-500">${data.message}</p>`;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching order details:', error);
                        modalContent.innerHTML = '<p class="text-center py-8 text-red-500">Gagal memuat data. Silakan coba lagi.</p>';
                    });
            }
        });

        function renderModalContent(details, items) {
            const orderDate = new Date(details.created_at).toLocaleString('id-ID', {
                day: '2-digit',
                month: 'long',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            let itemsHtml = items.map(item => `
            <div class="flex justify-between items-center py-2 border-b">
                <div>
                    <p class="font-semibold">${item.name}</p>
                    <p class="text-gray-500">${item.quantity} x ${formatRupiah(item.price)}</p>
                </div>
                <p class="font-semibold">${formatRupiah(item.subtotal)}</p>
            </div>
        `).join('');

            modalContent.innerHTML = `
            <div class="grid grid-cols-2 gap-x-4 gap-y-2 mb-4">
                <div class="text-gray-600">Tanggal:</div>
                <div class="font-semibold text-right">${orderDate}</div>
                <div class="text-gray-600">Pelanggan:</div>
                <div class="font-semibold text-right">${details.customer_name_final}</div>
                <div class="text-gray-600">Kasir:</div>
                <div class="font-semibold text-right">${details.cashier_name || 'N/A'}</div>
            </div>
            
            <h3 class="font-bold mt-4 mb-2 text-gray-700">Rincian Item</h3>
            <div class="space-y-1 mb-4">
                ${itemsHtml}
            </div>

            <div class="space-y-1 text-right text-gray-800">
                <div class="flex justify-between"><span>Subtotal</span><span>${formatRupiah(details.subtotal)}</span></div>
                <div class="flex justify-between"><span>Diskon</span><span>- ${formatRupiah(details.discount_amount)}</span></div>
                <div class="flex justify-between"><span>PPN (11%)</span><span>${formatRupiah(details.tax)}</span></div>
                <div class="flex justify-between font-bold text-lg border-t pt-2 mt-2">
                    <span>TOTAL</span>
                    <span>${formatRupiah(details.total_amount)}</span>
                </div>
                 <div class="flex justify-between text-sm text-gray-600">
                    <span>Metode Bayar</span>
                    <span class="capitalize">${details.payment_method}</span>
                </div>
            </div>
        `;
        }

        function formatRupiah(angka) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(angka);
        }

        closeModalBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    });
</script>

<?php
require_once 'includes/footer.php';
$conn->close();
?>