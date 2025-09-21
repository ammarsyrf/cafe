<?php
// Sisipkan header
require_once 'includes/header.php';

// Pastikan Anda memiliki file db_connect.php yang berisi koneksi ke database
require_once '../db_connect.php';

// =================================================================
// 1. DATA UNTUK STAT CARDS
// =================================================================

// Pendapatan & Transaksi Hari Ini
$today_revenue = 0;
$total_transactions = 0;
$sql_today = "SELECT SUM(total_amount) AS total_revenue, COUNT(id) AS total_tx FROM orders WHERE DATE(created_at) = CURDATE() AND status IN ('completed', 'paid')";
$result_today = $conn->query($sql_today);
if ($row_today = $result_today->fetch_assoc()) {
    $today_revenue = $row_today['total_revenue'] ?? 0;
    $total_transactions = $row_today['total_tx'] ?? 0;
}

// BARU: Perbandingan Performa dengan Kemarin
$yesterday_revenue = 0;
$sql_yesterday = "SELECT SUM(total_amount) AS total_revenue FROM orders WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY AND status IN ('completed', 'paid')";
$result_yesterday = $conn->query($sql_yesterday);
if ($row_yesterday = $result_yesterday->fetch_assoc()) {
    $yesterday_revenue = $row_yesterday['total_revenue'] ?? 0;
}
$revenue_percentage_change = 0;
if ($yesterday_revenue > 0) {
    $revenue_percentage_change = (($today_revenue - $yesterday_revenue) / $yesterday_revenue) * 100;
} elseif ($today_revenue > 0) {
    $revenue_percentage_change = 100; // Jika kemarin 0 tapi hari ini ada pendapatan
}

// Pesanan Belum Diproses
$unprocessed_orders = 0;
$sql_unprocessed = "SELECT COUNT(id) AS unprocessed_count FROM orders WHERE status = 'pending_payment' OR status = 'processing'";
$result_unprocessed = $conn->query($sql_unprocessed);
if ($row_unprocessed = $result_unprocessed->fetch_assoc()) {
    $unprocessed_orders = $row_unprocessed['unprocessed_count'] ?? 0;
}

// Member Baru Hari Ini
$new_members_today = 0;
$sql_new_members = "SELECT COUNT(id) AS new_members FROM members WHERE DATE(created_at) = CURDATE()";
$result_new_members = $conn->query($sql_new_members);
if ($row_new_members = $result_new_members->fetch_assoc()) {
    $new_members_today = $row_new_members['new_members'] ?? 0;
}

// Stok Segera Habis
$low_stock_count = 0;
$sql_low_stock = "SELECT COUNT(id) as low_stock_items FROM menu WHERE stock <= 10";
$result_low_stock = $conn->query($sql_low_stock);
if ($row_low_stock = $result_low_stock->fetch_assoc()) {
    $low_stock_count = $row_low_stock['low_stock_items'] ?? 0;
}

// =================================================================
// 2. DATA UNTUK GRAFIK
// =================================================================

// Grafik Penjualan (Harian, Mingguan, Bulanan)
$period = $_GET['period'] ?? 'daily';
$sales_chart_labels = [];
$sales_chart_data = [];
$sales_chart_title = '';
// Logika switch case untuk filter periode ada di file sebelumnya dan tetap sama...
switch ($period) {
    case 'weekly':
        $sales_chart_title = 'Penjualan per Minggu (12 Minggu Terakhir)';
        $sql_sales_chart = "SELECT YEARWEEK(created_at, 1) as sales_period, SUM(total_amount) as period_total FROM orders WHERE created_at >= CURDATE() - INTERVAL 12 WEEK AND status IN ('completed', 'paid') GROUP BY sales_period ORDER BY sales_period ASC";
        $result_sales_chart = $conn->query($sql_sales_chart);
        while ($row = $result_sales_chart->fetch_assoc()) {
            $sales_chart_labels[] = "Minggu " . substr($row['sales_period'], 4, 2);
            $sales_chart_data[] = $row['period_total'];
        }
        break;
    case 'monthly':
        $sales_chart_title = 'Penjualan per Bulan (12 Bulan Terakhir)';
        $sql_sales_chart = "SELECT DATE_FORMAT(created_at, '%Y-%m') as sales_period, SUM(total_amount) as period_total FROM orders WHERE created_at >= CURDATE() - INTERVAL 12 MONTH AND status IN ('completed', 'paid') GROUP BY sales_period ORDER BY sales_period ASC";
        $result_sales_chart = $conn->query($sql_sales_chart);
        while ($row = $result_sales_chart->fetch_assoc()) {
            $dateObj = DateTime::createFromFormat('!Y-m', $row['sales_period']);
            $sales_chart_labels[] = $dateObj->format('M Y');
            $sales_chart_data[] = $row['period_total'];
        }
        break;
    default: // daily
        $sales_chart_title = 'Penjualan Harian (30 Hari Terakhir)';
        $date_range = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $date_range[$date] = 0;
        }
        $sql_sales_chart = "SELECT DATE(created_at) as sales_date, SUM(total_amount) as daily_total FROM orders WHERE created_at >= CURDATE() - INTERVAL 29 DAY AND status IN ('completed', 'paid') GROUP BY DATE(created_at)";
        $result_sales_chart = $conn->query($sql_sales_chart);
        while ($row = $result_sales_chart->fetch_assoc()) {
            if (isset($date_range[$row['sales_date']])) {
                $date_range[$row['sales_date']] = $row['daily_total'];
            }
        }
        $sales_chart_labels = array_map(fn($date) => (new DateTime($date))->format('d M'), array_keys($date_range));
        $sales_chart_data = array_values($date_range);
        break;
}

// BARU: Grafik Jam Sibuk (Peak Hours) Hari Ini
$peak_hours_labels = [];
$peak_hours_data = [];
$hours_range = range(8, 22); // Asumsi jam operasional 08:00 - 22:00
foreach ($hours_range as $hour) {
    $peak_hours_labels[] = str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00';
    $peak_hours_data[str_pad($hour, 2, '0', STR_PAD_LEFT)] = 0;
}
$sql_peak_hours = "SELECT HOUR(created_at) as hour, COUNT(id) as total_tx 
                   FROM orders 
                   WHERE DATE(created_at) = CURDATE() AND status IN ('completed', 'paid')
                   GROUP BY hour ORDER BY hour ASC";
$result_peak_hours = $conn->query($sql_peak_hours);
while ($row = $result_peak_hours->fetch_assoc()) {
    $hour_key = str_pad($row['hour'], 2, '0', STR_PAD_LEFT);
    if (isset($peak_hours_data[$hour_key])) {
        $peak_hours_data[$hour_key] = $row['total_tx'];
    }
}
$peak_hours_data = array_values($peak_hours_data);


// BARU: Grafik Distribusi Metode Pembayaran
$payment_methods_labels = [];
$payment_methods_data = [];
$sql_payment_methods = "SELECT payment_method, COUNT(id) as total_usage 
                        FROM orders 
                        WHERE status IN ('completed', 'paid') AND payment_method IS NOT NULL AND payment_method != ''
                        GROUP BY payment_method";
$result_payment_methods = $conn->query($sql_payment_methods);
while ($row = $result_payment_methods->fetch_assoc()) {
    $payment_methods_labels[] = ucfirst(str_replace('_', ' ', $row['payment_method']));
    $payment_methods_data[] = $row['total_usage'];
}

// =================================================================
// 3. DATA UNTUK DAFTAR/LIST
// =================================================================

// Top 5 Member
$top_members = [];
$sql_top_members = "SELECT m.name, COUNT(o.id) as total_transactions, SUM(o.total_amount) as total_spent
                    FROM orders o
                    JOIN members m ON o.member_id = m.id
                    WHERE o.status IN ('completed', 'paid') AND o.member_id IS NOT NULL
                    GROUP BY o.member_id
                    ORDER BY total_spent DESC
                    LIMIT 5";
$result_top_members = $conn->query($sql_top_members);
while ($row = $result_top_members->fetch_assoc()) {
    $top_members[] = $row;
}

// Menu Terlaris
$best_sellers = [];
$sql_bestsellers = "SELECT m.name, SUM(oi.quantity) AS total_quantity FROM order_items oi JOIN menu m ON oi.menu_id = m.id JOIN orders o ON oi.order_id = o.id WHERE o.status IN ('completed', 'paid') GROUP BY m.name ORDER BY total_quantity DESC LIMIT 5";
$result_bestsellers = $conn->query($sql_bestsellers);
while ($row = $result_bestsellers->fetch_assoc()) {
    $best_sellers[] = $row;
}

// BARU: Ringkasan Ulasan Pelanggan
$avg_rating = 0;
$total_reviews = 0;
$latest_reviews = [];
$sql_avg_rating = "SELECT AVG(rating) as avg_rating, COUNT(id) as total_reviews FROM reviews";
$result_avg_rating = $conn->query($sql_avg_rating);
if ($row_avg = $result_avg_rating->fetch_assoc()) {
    $avg_rating = $row_avg['avg_rating'] ?? 0;
    $total_reviews = $row_avg['total_reviews'] ?? 0;
}
$sql_latest_reviews = "SELECT r.rating, r.comment, COALESCE(m.name, 'Anonymous') as member_name 
                       FROM reviews r 
                       LEFT JOIN members m ON r.member_id = m.id 
                       ORDER BY r.created_at DESC LIMIT 3";
$result_latest_reviews = $conn->query($sql_latest_reviews);
while ($row = $result_latest_reviews->fetch_assoc()) {
    $latest_reviews[] = $row;
}

?>

<!-- Konten Dashboard -->
<div class="container mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Dashboard <?= htmlspecialchars($APP_CONFIG['cafe_name'] ?? 'Toko Anda') ?></h1>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6 mb-6">
        <!-- Card Pendapatan -->
        <div class="bg-white p-5 rounded-xl shadow flex items-start">
            <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4"><i class="fas fa-wallet fa-lg"></i></div>
            <div class="flex-1">
                <p class="text-sm text-gray-500">Pendapatan Hari Ini</p>
                <p class="text-xl font-bold text-gray-800">Rp <?= number_format($today_revenue, 0, ',', '.') ?></p>
                <p class="text-xs mt-1 <?= $revenue_percentage_change >= 0 ? 'text-green-500' : 'text-red-500' ?>">
                    <i class="fas fa-arrow-<?= $revenue_percentage_change >= 0 ? 'up' : 'down' ?>"></i>
                    <?= number_format(abs($revenue_percentage_change), 1) ?>% dari kemarin
                </p>
            </div>
        </div>
        <!-- Card Transaksi -->
        <div class="bg-white p-5 rounded-xl shadow flex items-start">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4"><i class="fas fa-receipt fa-lg"></i></div>
            <div>
                <p class="text-sm text-gray-500">Total Transaksi</p>
                <p class="text-2xl font-bold text-gray-800"><?= $total_transactions ?></p>
            </div>
        </div>
        <!-- Card Pesanan Belum Proses -->
        <div class="bg-white p-5 rounded-xl shadow flex items-start">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4"><i class="fas fa-box-open fa-lg"></i></div>
            <div>
                <p class="text-sm text-gray-500">Pesanan Diproses</p>
                <p class="text-2xl font-bold text-gray-800"><?= $unprocessed_orders ?></p>
            </div>
        </div>
        <!-- Card Member Baru -->
        <div class="bg-white p-5 rounded-xl shadow flex items-start">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4"><i class="fas fa-user-plus fa-lg"></i></div>
            <div>
                <p class="text-sm text-gray-500">Member Baru Hari Ini</p>
                <p class="text-2xl font-bold text-gray-800"><?= $new_members_today ?></p>
            </div>
        </div>
        <!-- Card Stok Habis -->
        <div class="bg-white p-5 rounded-xl shadow flex items-start">
            <div class="p-3 rounded-full bg-red-100 text-red-600 mr-4"><i class="fas fa-exclamation-triangle fa-lg"></i></div>
            <div>
                <p class="text-sm text-gray-500">Stok Segera Habis</p>
                <p class="text-2xl font-bold text-gray-800"><?= $low_stock_count ?></p>
            </div>
        </div>
    </div>

    <!-- Grafik Penjualan (Satu Baris Penuh) -->
    <div class="bg-white p-6 rounded-xl shadow mb-6">
        <div class="flex flex-wrap justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-700"><?= $sales_chart_title ?></h3>
            <div class="flex items-center space-x-2">
                <a href="?period=daily" class="px-3 py-1 text-sm font-medium rounded-md <?= $period === 'daily' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">Harian</a>
                <a href="?period=weekly" class="px-3 py-1 text-sm font-medium rounded-md <?= $period === 'weekly' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">Mingguan</a>
                <a href="?period=monthly" class="px-3 py-1 text-sm font-medium rounded-md <?= $period === 'monthly' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">Bulanan</a>
            </div>
        </div>
        <div class="relative h-96">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!-- Grid untuk data lainnya -->
    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
        <!-- Grafik Jam Sibuk -->
        <div class="xl:col-span-2 bg-white p-6 rounded-xl shadow">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">Analisis Jam Sibuk (Hari Ini)</h3>
            <div class="relative h-80">
                <canvas id="peakHoursChart"></canvas>
            </div>
        </div>

        <!-- Metode Pembayaran -->
        <div class="bg-white p-6 rounded-xl shadow flex flex-col">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">Metode Pembayaran</h3>
            <div class="relative flex-grow flex items-center justify-center h-80">
                <canvas id="paymentMethodsChart"></canvas>
            </div>
        </div>

        <!-- Top Member -->
        <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">Top 5 Member Loyal</h3>
            <ul class="space-y-4">
                <?php if (!empty($top_members)): ?>
                    <?php foreach ($top_members as $index => $member): ?>
                        <li class="flex items-center">
                            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 text-gray-700 font-bold mr-3"><?= $index + 1 ?></span>
                            <div class="flex-1">
                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($member['name']) ?></p>
                                <p class="text-xs text-gray-500"><?= $member['total_transactions'] ?> Transaksi</p>
                            </div>
                            <span class="font-bold text-green-600">Rp <?= number_format($member['total_spent'], 0, ',', '.') ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="text-sm text-gray-500 text-center py-4">Belum ada transaksi dari member.</li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Menu Terlaris -->
        <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">5 Menu Terlaris</h3>
            <ul class="space-y-3">
                <?php if (!empty($best_sellers)): ?>
                    <?php foreach ($best_sellers as $item): ?>
                        <li class="flex justify-between items-center text-sm">
                            <span><?= htmlspecialchars($item['name']) ?></span>
                            <span class="font-semibold bg-gray-100 px-2 py-1 rounded"><?= $item['total_quantity'] ?> Terjual</span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="text-sm text-gray-500 text-center py-4">Tidak ada data penjualan.</li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Ulasan Terbaru -->
        <div class="bg-white p-6 rounded-xl shadow">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Ulasan Pelanggan</h3>
            <div class="flex items-baseline space-x-2 mb-4 border-b pb-3">
                <span class="text-2xl font-bold text-yellow-500"><?= number_format($avg_rating, 1) ?></span>
                <div class="text-yellow-400">
                    <?php for ($i = 1; $i <= 5; $i++): ?><i class="fa-<?= $i <= round($avg_rating) ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?>
                </div>
                <span class="text-sm text-gray-500">(<?= $total_reviews ?> ulasan)</span>
            </div>
            <ul class="space-y-4">
                <?php if (!empty($latest_reviews)): ?>
                    <?php foreach ($latest_reviews as $review): ?>
                        <li class="text-sm">
                            <div class="flex justify-between items-center mb-1">
                                <span class="font-semibold"><?= htmlspecialchars($review['member_name']) ?></span>
                                <span class="text-xs text-yellow-500"><?= str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']) ?></span>
                            </div>
                            <p class="text-gray-600 italic">"<?= htmlspecialchars($review['comment']) ?>"</p>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="text-sm text-gray-500 text-center py-4">Belum ada ulasan dari pelanggan.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const formatCurrency = (value) => 'Rp ' + new Intl.NumberFormat('id-ID').format(value);

        // 1. Grafik Penjualan (Line Chart)
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($sales_chart_labels) ?>,
                datasets: [{
                    label: 'Penjualan (Rp)',
                    data: <?= json_encode($sales_chart_data) ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: formatCurrency
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => `${context.dataset.label}: ${formatCurrency(context.parsed.y)}`
                        }
                    }
                }
            }
        });

        // 2. Grafik Jam Sibuk (Bar Chart)
        const peakHoursCtx = document.getElementById('peakHoursChart').getContext('2d');
        new Chart(peakHoursCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($peak_hours_labels) ?>,
                datasets: [{
                    label: 'Jumlah Transaksi',
                    data: <?= json_encode($peak_hours_data) ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.6)',
                    borderColor: 'rgba(239, 68, 68, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
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

        // 3. Grafik Metode Pembayaran (Doughnut Chart)
        const paymentMethodsCtx = document.getElementById('paymentMethodsChart').getContext('2d');
        new Chart(paymentMethodsCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($payment_methods_labels) ?>,
                datasets: [{
                    label: 'Penggunaan',
                    data: <?= json_encode($payment_methods_data) ?>,
                    backgroundColor: ['rgba(251, 191, 36, 0.8)', 'rgba(59, 130, 246, 0.8)', 'rgba(16, 185, 129, 0.8)', 'rgba(139, 92, 246, 0.8)'],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    });
</script>

<?php
// Sisipkan footer
require_once 'includes/footer.php';
$conn->close();
?>