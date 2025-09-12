<?php
// File: superadmin/laporan.php

// Hubungkan ke database terlebih dahulu untuk semua aksi
require_once '../db_connect.php';

// Cek apakah aksi adalah 'cetak_struk'
if (isset($_GET['action']) && $_GET['action'] == 'cetak_struk' && isset($_GET['id'])) {
    $transaction_id = $_GET['id'];
    
    // --- LOGIKA UNTUK CETAK STRUK ---
    $transaction = null;
    $order_items = [];

    // Ambil data transaksi utama
    $sql_tx = "SELECT t.*, u.username, o.id as order_id 
               FROM transactions t 
               LEFT JOIN users u ON t.user_id = u.id
               LEFT JOIN orders o ON t.order_id = o.id
               WHERE t.id = ?";

    if ($stmt_tx = $conn->prepare($sql_tx)) {
        $stmt_tx->bind_param("i", $transaction_id);
        $stmt_tx->execute();
        $result_tx = $stmt_tx->get_result();
        if ($result_tx->num_rows > 0) {
            $transaction = $result_tx->fetch_assoc();

            // Ambil item pesanan yang terkait
            $order_id = $transaction['order_id'];
            $sql_items = "SELECT oi.quantity, m.name, m.price, (oi.quantity * m.price) as subtotal
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
        $stmt_tx->close();
    }

    $conn->close();

    if (!$transaction) {
        die("Transaksi tidak ditemukan.");
    }
    
    // Tampilkan HTML untuk struk dan hentikan eksekusi script
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Struk Transaksi #<?= $transaction['id'] ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inconsolata:wght@400;700&display=swap');
            body { font-family: 'Inconsolata', monospace; width: 300px; margin: 0 auto; }
            @media print { body { -webkit-print-color-adjust: exact; } .no-print { display: none; } }
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
                <div class="flex justify-between"><span>No. Transaksi:</span><span>#<?= $transaction['id'] ?></span></div>
                <div class="flex justify-between"><span>Tanggal:</span><span><?= date('d/m/y H:i', strtotime($transaction['transaction_date'])) ?></span></div>
                <div class="flex justify-between"><span>Pelanggan:</span><span><?= htmlspecialchars($transaction['username'] ?? 'Guest') ?></span></div>
            </div>
            <div class="border-t border-b border-dashed border-black my-2 py-2">
                <?php foreach($order_items as $item): ?>
                <div class="text-xs mb-1">
                    <p><?= htmlspecialchars($item['name']) ?></p>
                    <div class="flex justify-between">
                        <span><?= $item['quantity'] ?> x <?= number_format($item['price'], 0, ',', '.') ?></span>
                        <span><?= number_format($item['subtotal'], 0, ',', '.') ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-xs">
                <div class="flex justify-between font-bold"><span>TOTAL</span><span>Rp <?= number_format($transaction['total_paid'], 0, ',', '.') ?></span></div>
                <div class="flex justify-between"><span>Metode Bayar</span><span class="capitalize"><?= htmlspecialchars($transaction['payment_method']) ?></span></div>
            </div>
            <div class="text-center text-xs mt-6"><p>Terima kasih atas kunjungan Anda!</p></div>
        </div>
        <div class="text-center mt-4 no-print">
            <button onclick="window.print()" class="bg-blue-500 text-white px-4 py-2 rounded">Cetak Struk</button>
            <button onclick="window.close()" class="bg-gray-500 text-white px-4 py-2 rounded">Tutup</button>
        </div>
    </body>
    </html>
    <?php
    exit(); // Hentikan script agar tidak menampilkan halaman laporan
}

// --- JIKA BUKAN CETAK STRUK, TAMPILKAN HALAMAN LAPORAN ---
require_once 'includes/header.php';

// Atur tanggal default: 1 bulan terakhir
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Inisialisasi variabel statistik
$total_revenue = 0;
$total_transactions = 0;
$best_seller = ['name' => 'N/A', 'quantity' => 0];
$transactions = [];

// Query untuk mengambil data transaksi
$sql_transactions = "SELECT t.id, t.transaction_date, u.username, t.total_paid 
                     FROM transactions t
                     LEFT JOIN users u ON t.user_id = u.id
                     WHERE DATE(t.transaction_date) BETWEEN ? AND ?
                     ORDER BY t.transaction_date DESC";

if ($stmt = $conn->prepare($sql_transactions)) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $transactions = $result->fetch_all(MYSQLI_ASSOC);
        $total_transactions = $result->num_rows;
        foreach ($transactions as $tx) {
            $total_revenue += $tx['total_paid'];
        }
    }
    $stmt->close();
}

// Query untuk mencari produk terlaris
$sql_bestseller = "SELECT m.name, SUM(oi.quantity) as total_quantity 
                   FROM order_items oi
                   JOIN orders o ON oi.order_id = o.id
                   JOIN transactions t ON o.id = t.order_id
                   JOIN menu m ON oi.menu_id = m.id
                   WHERE DATE(t.transaction_date) BETWEEN ? AND ?
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

<!-- Pustaka JavaScript untuk Cetak PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>

<div class="container mx-auto">
    <!-- Header dan Filter -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <h1 class="text-3xl font-bold text-gray-800">Laporan Penjualan</h1>
        <div class="flex items-center gap-4">
            <form method="GET" class="flex items-center gap-2 bg-white p-2 rounded-lg shadow">
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="border p-2 rounded-lg text-sm">
                <span class="text-gray-500">hingga</span>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="border p-2 rounded-lg text-sm">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </form>
            <button onclick="generatePDF()" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 flex items-center gap-2 shadow">
                <i class="fas fa-file-pdf"></i> Cetak PDF
            </button>
        </div>
    </div>

    <!-- Ringkasan Statistik -->
    <div id="summary-section">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white p-6 rounded-xl shadow-lg flex items-center gap-4">
                <div class="bg-green-100 p-4 rounded-full"><i class="fas fa-wallet text-2xl text-green-600"></i></div>
                <div><p class="text-sm text-gray-500">Total Pendapatan</p><p class="text-2xl font-bold text-gray-800">Rp <?= number_format($total_revenue, 0, ',', '.') ?></p></div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-lg flex items-center gap-4">
                <div class="bg-blue-100 p-4 rounded-full"><i class="fas fa-receipt text-2xl text-blue-600"></i></div>
                <div><p class="text-sm text-gray-500">Total Transaksi</p><p class="text-2xl font-bold text-gray-800"><?= $total_transactions ?></p></div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-lg flex items-center gap-4">
                <div class="bg-yellow-100 p-4 rounded-full"><i class="fas fa-trophy text-2xl text-yellow-600"></i></div>
                <div><p class="text-sm text-gray-500">Produk Terlaris</p><p class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($best_seller['name']) ?></p></div>
            </div>
        </div>
    </div>

    <!-- Tabel Laporan -->
    <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
        <table id="report-table" class="min-w-full leading-normal">
            <thead>
                <tr class="bg-gray-100">
                    <th class="px-5 py-3 border-b-2 text-left text-xs font-semibold uppercase">ID Transaksi</th>
                    <th class="px-5 py-3 border-b-2 text-left text-xs font-semibold uppercase">Tanggal</th>
                    <th class="px-5 py-3 border-b-2 text-left text-xs font-semibold uppercase">Nama Pelanggan</th>
                    <th class="px-5 py-3 border-b-2 text-right text-xs font-semibold uppercase">Total Bayar (Rp)</th>
                    <th class="px-5 py-3 border-b-2 text-center text-xs font-semibold uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($transactions)): ?>
                    <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td class="px-5 py-5 border-b text-sm">#<?= $tx['id'] ?></td>
                            <td class="px-5 py-5 border-b text-sm"><?= date('d M Y, H:i', strtotime($tx['transaction_date'])) ?></td>
                            <td class="px-5 py-5 border-b text-sm font-medium"><?= htmlspecialchars($tx['username'] ?? 'Guest') ?></td>
                            <td class="px-5 py-5 border-b text-sm text-right font-semibold"><?= number_format($tx['total_paid'], 0, ',', '.') ?></td>
                            <td class="px-5 py-5 border-b text-sm text-center">
                                <a href="laporan.php?action=cetak_struk&id=<?= $tx['id'] ?>" target="_blank" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-1 px-3 rounded text-xs">
                                    Cetak Struk
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center py-10 text-gray-500">Tidak ada transaksi pada rentang tanggal yang dipilih.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function generatePDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    // Judul PDF
    doc.setFontSize(18);
    doc.text("Laporan Penjualan Kafe", 14, 22);
    
    // Informasi Periode
    doc.setFontSize(11);
    doc.setTextColor(100);
    const startDate = "<?= date('d M Y', strtotime($start_date)) ?>";
    const endDate = "<?= date('d M Y', strtotime($end_date)) ?>";
    doc.text(`Periode: ${startDate} - ${endDate}`, 14, 30);

    // Ringkasan Statistik
    doc.autoTable({
        startY: 40,
        head: [['Deskripsi', 'Jumlah']],
        body: [
            ['Total Pendapatan', 'Rp <?= number_format($total_revenue, 0, ',', '.') ?>'],
            ['Total Transaksi', '<?= $total_transactions ?>'],
            ['Produk Terlaris', '<?= htmlspecialchars($best_seller['name']) ?>']
        ],
        theme: 'grid', styles: { fontSize: 10 }
    });

    // Tabel Transaksi
    doc.autoTable({
        startY: doc.autoTable.previous.finalY + 10,
        html: '#report-table',
        columns: [
            { header: 'ID Transaksi', dataKey: 0 },
            { header: 'Tanggal', dataKey: 1 },
            { header: 'Nama Pelanggan', dataKey: 2 },
            { header: 'Total Bayar (Rp)', dataKey: 3 },
        ],
        headStyles: { fillColor: [22, 160, 133] },
        styles: { fontSize: 9 }
    });

    // Simpan PDF
    doc.save(`Laporan_Penjualan_${startDate}_-_${endDate}.pdf`);
}
</script>

<?php
require_once 'includes/footer.php';
$conn->close();
?>

