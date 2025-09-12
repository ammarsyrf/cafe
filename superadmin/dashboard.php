<?php
// Sisipkan header
require_once 'includes/header.php';

// Pastikan Anda memiliki file db_connect.php yang berisi koneksi ke database
// Contoh isi db_connect.php:
// <?php
// $servername = "localhost";
// $username = "root";
// $password = "";
// $dbname = "db_cafe";
// $conn = new mysqli($servername, $username, $password, $dbname);
// if ($conn->connect_error) {
//     die("Connection failed: " . $conn->connect_error);
// }
require_once '../db_connect.php'; // Aktifkan baris ini setelah db_connect.php siap

// 1. Mengambil Pendapatan & Jumlah Transaksi Hari Ini
$today_revenue = 0;
$total_transactions = 0;
$sql_today = "SELECT SUM(total_paid) AS total_revenue, COUNT(id) AS total_tx FROM transactions WHERE DATE(transaction_date) = CURDATE()";
$result_today = $conn->query($sql_today);
if ($row_today = $result_today->fetch_assoc()) {
    $today_revenue = $row_today['total_revenue'] ?? 0;
    $total_transactions = $row_today['total_tx'] ?? 0;
}

// 2. Menghitung Rata-Rata Pembelian
$avg_purchase = ($total_transactions > 0) ? $today_revenue / $total_transactions : 0;

// 3. Menghitung Pesanan yang Belum Diproses
$unprocessed_orders = 0;
$sql_unprocessed = "SELECT COUNT(id) AS unprocessed_count FROM orders WHERE status = 'pending' OR status = 'processing'";
$result_unprocessed = $conn->query($sql_unprocessed);
if ($row_unprocessed = $result_unprocessed->fetch_assoc()) {
    $unprocessed_orders = $row_unprocessed['unprocessed_count'] ?? 0;
}

// 4. Mengambil Menu Terlaris (Top 3)
$best_sellers = [];
$sql_bestsellers = "SELECT m.name, SUM(oi.quantity) AS total_quantity 
                    FROM order_items oi
                    JOIN menu m ON oi.menu_id = m.id
                    JOIN orders o ON oi.order_id = o.id
                    WHERE o.status IN ('completed', 'paid') 
                    GROUP BY m.name 
                    ORDER BY total_quantity DESC 
                    LIMIT 3";
$result_bestsellers = $conn->query($sql_bestsellers);
while ($row = $result_bestsellers->fetch_assoc()) {
    $best_sellers[] = $row;
}

// 5. Mengambil Menu dengan Stok Rendah (Kurang dari atau sama dengan 5)
$low_stock_menu = [];
$sql_low_stock = "SELECT name, stock FROM menu WHERE stock <= 5 ORDER BY stock ASC";
$result_low_stock = $conn->query($sql_low_stock);
while ($row = $result_low_stock->fetch_assoc()) {
    $low_stock_menu[] = $row;
}

// 6. Data untuk Grafik Penjualan 7 Hari Terakhir
$chart_labels = [];
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_name = date('D', strtotime($date)); // 'Mon', 'Tue', etc.
    $chart_labels[] = $day_name;
    $chart_data[$date] = 0; // Inisialisasi dengan 0
}

$sql_sales_chart = "SELECT DATE(transaction_date) as sales_date, SUM(total_paid) as daily_total 
                    FROM transactions 
                    WHERE transaction_date >= CURDATE() - INTERVAL 6 DAY 
                    GROUP BY DATE(transaction_date)";
$result_sales_chart = $conn->query($sql_sales_chart);
while ($row = $result_sales_chart->fetch_assoc()) {
    $chart_data[$row['sales_date']] = $row['daily_total'];
}

$chart_labels_json = json_encode($chart_labels);
$chart_data_json = json_encode(array_values($chart_data));

?>

<!-- Konten Dashboard -->
<div class="container mx-auto">
    <!-- Stat Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
        <!-- Card Pendapatan -->
        <div class="bg-white p-5 rounded-xl shadow flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4"><i class="fas fa-wallet fa-2x"></i></div>
            <div>
                <p class="text-sm text-gray-500">Pendapatan Hari Ini</p>
                <p class="text-xl font-bold text-gray-800">Rp <?= number_format($today_revenue, 0, ',', '.') ?></p>
            </div>
        </div>
        <!-- Card Transaksi -->
        <div class="bg-white p-5 rounded-xl shadow flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4"><i class="fas fa-receipt fa-2x"></i></div>
            <div>
                <p class="text-sm text-gray-500">Jumlah Transaksi</p>
                <p class="text-2xl font-bold text-gray-800"><?= $total_transactions ?></p>
            </div>
        </div>
        <!-- Card Rata-rata -->
        <div class="bg-white p-5 rounded-xl shadow flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4"><i class="fas fa-chart-line fa-2x"></i></div>
            <div>
                <p class="text-sm text-gray-500">Rata-Rata Pembelian</p>
                <p class="text-xl font-bold text-gray-800">Rp <?= number_format($avg_purchase, 0, ',', '.') ?></p>
            </div>
        </div>
        <!-- Card Pesanan Belum Proses -->
        <div class="bg-white p-5 rounded-xl shadow flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4"><i class="fas fa-box-open fa-2x"></i></div>
            <div>
                <p class="text-sm text-gray-500">Pesanan Belum Proses</p>
                <p class="text-2xl font-bold text-gray-800"><?= $unprocessed_orders ?></p>
            </div>
        </div>
        <!-- Card Stok Habis -->
        <div class="bg-white p-5 rounded-xl shadow flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600 mr-4"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
            <div>
                <p class="text-sm text-gray-500">Stok Segera Habis</p>
                <p class="text-2xl font-bold text-gray-800"><?= count($low_stock_menu) ?></p>
            </div>
        </div>
    </div>

    <!-- Grafik dan List -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Grafik Penjualan -->
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">Grafik Penjualan (7 Hari Terakhir)</h3>
            <canvas id="salesChart"></canvas>
        </div>

        <!-- Menu Terlaris & Stok Habis -->
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-xl shadow">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">Menu Terlaris</h3>
                <ul class="space-y-3">
                    <?php if (!empty($best_sellers)): ?>
                        <?php foreach ($best_sellers as $item): ?>
                            <li class="flex justify-between items-center text-sm">
                                <span><?= htmlspecialchars($item['name']) ?></span>
                                <span class="font-semibold text-gray-600"><?= $item['total_quantity'] ?> Terjual</span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="text-sm text-gray-500">Tidak ada data penjualan.</li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="bg-white p-6 rounded-xl shadow">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">Stok Segera Habis</h3>
                <ul class="space-y-3">
                    <?php if (!empty($low_stock_menu)): ?>
                        <?php foreach ($low_stock_menu as $item): ?>
                            <li class="flex justify-between items-center text-sm">
                                <span class="text-red-600"><?= htmlspecialchars($item['name']) ?></span>
                                <span class="font-semibold text-red-600">Sisa <?= $item['stock'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="text-sm text-gray-500">Semua stok aman.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    // Logika untuk Grafik Penjualan
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= $chart_labels_json ?>,
            datasets: [{
                label: 'Penjualan (Rp)',
                data: <?= $chart_data_json ?>,
                backgroundColor: 'rgba(89, 88, 161, 0.2)',
                borderColor: '#5958A1',
                borderWidth: 3,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
</script>

<?php
// Sisipkan footer
require_once 'includes/footer.php';
$conn->close(); // Tutup koneksi database
?>