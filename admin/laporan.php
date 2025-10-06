<?php
// File: admin/laporan.php

// Atur zona waktu
date_default_timezone_set('Asia/Jakarta');

// Hubungkan ke database terlebih dahulu untuk semua aksi
require_once '../app/config/db_connect.php';
// Hubungkan ke konfigurasi aplikasi untuk mengambil nama kafe
require_once '../app/config/config.php';


// --- BAGIAN AKSI (ACTIONS) ---

// AKSI 1: MENGAMBIL DETAIL PESANAN (JSON untuk Modal)
if (isset($_GET['action']) && $_GET['action'] == 'get_order_details' && isset($_GET['id'])) {
    $order_id = (int)$_GET['id'];
    $response = ['success' => false, 'message' => 'Pesanan tidak ditemukan.'];

    $order_details = null;
    $order_items = [];

    $sql_order = "SELECT o.*, 
                         COALESCE(NULLIF(TRIM(mem.name), ''), NULLIF(TRIM(o.customer_name), ''), 'Guest') as customer_name_final, 
                         (o.member_id IS NOT NULL) as is_member,
                         u_cashier.name as cashier_name
                  FROM orders o 
                  LEFT JOIN members mem ON o.member_id = mem.id
                  LEFT JOIN users u_cashier ON o.cashier_id = u_cashier.id
                  WHERE o.id = ?";

    if ($stmt_order = $conn->prepare($sql_order)) {
        $stmt_order->bind_param("i", $order_id);
        $stmt_order->execute();
        $result_order = $stmt_order->get_result();

        if ($result_order->num_rows > 0) {
            $order_details = $result_order->fetch_assoc();

            $sql_items = "SELECT oi.quantity, m.name, oi.price_per_item as price, oi.total_price as subtotal, oi.selected_addons
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

    header('Content-Type: application/json');
    echo json_encode($response);
    $conn->close();
    exit();
}

// Atur tanggal default dan view
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$view = $_GET['view'] ?? 'ringkasan';

// AKSI 2: EXPORT EXCEL (CSV) - Format Lebih Rapi dan Profesional
if (isset($_GET['action']) && $_GET['action'] == 'cetak_excel') {
    $filename = "Laporan_" . ucfirst($view) . "_" . date('Y-m-d', strtotime($start_date)) . "_sampai_" . date('Y-m-d', strtotime($end_date)) . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');

    // BOM untuk encoding UTF-8 agar Excel membaca dengan benar
    fwrite($output, "\xEF\xBB\xBF");

    // HEADER LAPORAN - Format Profesional
    $cafe_name = $APP_CONFIG['cafe_name'] ?? 'CAFE ANDA';
    $report_title = strtoupper("LAPORAN " . str_replace('_', ' ', $view));
    $report_period = date('d F Y', strtotime($start_date)) . " s/d " . date('d F Y', strtotime($end_date));

    // Hitung ringkasan global untuk header
    $global_summary = ['total_transactions' => 0, 'total_revenue' => 0, 'total_items' => 0];
    $sql_global = "SELECT COUNT(DISTINCT o.id) as total_transactions, SUM(o.total_amount) as total_revenue, SUM(oi.quantity) as total_items 
                   FROM orders o 
                   LEFT JOIN order_items oi ON o.id = oi.order_id 
                   WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status IN ('completed', 'paid')";
    if ($stmt_global = $conn->prepare($sql_global)) {
        $stmt_global->bind_param("ss", $start_date, $end_date);
        $stmt_global->execute();
        $result_global = $stmt_global->get_result();
        if ($row = $result_global->fetch_assoc()) {
            $global_summary = $row;
        }
        $stmt_global->close();
    }

    fputcsv($output, ['=================================================================================']);
    fputcsv($output, [$cafe_name]);
    fputcsv($output, [$report_title]);
    fputcsv($output, ['=================================================================================']);
    fputcsv($output, ['PERIODE LAPORAN', $report_period]);
    fputcsv($output, ['TOTAL TRANSAKSI', number_format($global_summary['total_transactions'] ?? 0) . ' transaksi']);
    fputcsv($output, ['TOTAL PENDAPATAN', 'Rp ' . number_format($global_summary['total_revenue'] ?? 0, 0, ',', '.')]);
    fputcsv($output, ['TOTAL ITEM TERJUAL', number_format($global_summary['total_items'] ?? 0) . ' item']);
    fputcsv($output, ['DICETAK PADA', date('d F Y, H:i:s') . ' WIB']);
    fputcsv($output, ['=================================================================================']);
    fputcsv($output, []); // Baris kosong

    // KONTEN LAPORAN BERDASARKAN VIEW
    switch ($view) {
        case 'keuangan':
            // Header Laporan Keuangan
            fputcsv($output, ['LAPORAN KEUANGAN TRANSAKSI']);
            fputcsv($output, ['=================================================================================']);
            // Ringkasan
            $sql_summary = "SELECT SUM(total_amount) as total_rev, SUM(discount_amount) as total_disc, SUM(tax) as total_tax, COUNT(id) as total_tx FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status IN ('completed', 'paid')";
            $summary = ['total_rev' => 0, 'total_disc' => 0, 'total_tax' => 0, 'total_tx' => 0];
            if ($stmt_sum = $conn->prepare($sql_summary)) {
                $stmt_sum->bind_param("ss", $start_date, $end_date);
                $stmt_sum->execute();
                $res_sum = $stmt_sum->get_result();
                if ($row = $res_sum->fetch_assoc()) {
                    $summary = $row;
                }
                $stmt_sum->close();
            }
            $net_revenue = ($summary['total_rev'] ?? 0) - ($summary['total_disc'] ?? 0);
            fputcsv($output, ['Ringkasan Keuangan']);
            fputcsv($output, ['Pendapatan Kotor', 'Rp ' . number_format($summary['total_rev'] ?? 0, 0, ',', '.')]);
            fputcsv($output, ['Total Diskon', 'Rp ' . number_format($summary['total_disc'] ?? 0, 0, ',', '.')]);
            fputcsv($output, ['Pendapatan Bersih', 'Rp ' . number_format($net_revenue, 0, ',', '.')]);
            fputcsv($output, ['Total Pajak', 'Rp ' . number_format($summary['total_tax'] ?? 0, 0, ',', '.')]);
            fputcsv($output, ['Total Transaksi', number_format($summary['total_tx'] ?? 0)]);
            fputcsv($output, ['=================================================================================']);
            fputcsv($output, []);
            // Detail Transaksi
            fputcsv($output, ['DETAIL TRANSAKSI KEUANGAN']);
            fputcsv($output, ['Tanggal', 'ID Order', 'Pelanggan', 'Total', 'Diskon', 'Pajak', 'Metode Bayar', 'Kasir']);
            $sql_detail = "SELECT o.id, o.created_at, o.total_amount, o.discount_amount, o.tax, o.payment_method, COALESCE(mem.name, o.customer_name, 'Guest') as customer_name, COALESCE(u.name, 'N/A') as cashier_name FROM orders o LEFT JOIN members mem ON o.member_id = mem.id LEFT JOIN users u ON o.cashier_id = u.id WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status IN ('completed', 'paid') ORDER BY o.created_at ASC";
            $grand_total = 0;
            $grand_disc = 0;
            $grand_tax = 0;
            if ($stmt_detail = $conn->prepare($sql_detail)) {
                $stmt_detail->bind_param("ss", $start_date, $end_date);
                $stmt_detail->execute();
                $result_detail = $stmt_detail->get_result();
                while ($row = $result_detail->fetch_assoc()) {
                    fputcsv($output, [
                        date('d-m-Y H:i', strtotime($row['created_at'])),
                        '#' . $row['id'],
                        $row['customer_name'],
                        'Rp ' . number_format($row['total_amount'], 0, ',', '.'),
                        'Rp ' . number_format($row['discount_amount'], 0, ',', '.'),
                        'Rp ' . number_format($row['tax'], 0, ',', '.'),
                        ucfirst(str_replace('_', ' ', $row['payment_method'])),
                        $row['cashier_name']
                    ]);
                    $grand_total += $row['total_amount'];
                    $grand_disc += $row['discount_amount'];
                    $grand_tax += $row['tax'];
                }
                $stmt_detail->close();
            }
            // Footer
            fputcsv($output, ['=================================================================================']);
            fputcsv($output, ['TOTAL KESELURUHAN', '', '', 'Rp ' . number_format($grand_total, 0, ',', '.'), 'Rp ' . number_format($grand_disc, 0, ',', '.'), 'Rp ' . number_format($grand_tax, 0, ',', '.'), '', '']);
            fputcsv($output, ['=================================================================================']);
            break;
        case 'menu':
            // Header Tabel
            fputcsv($output, ['LAPORAN PERFORMA MENU']);
            fputcsv($output, ['=================================================================================']);
            fputcsv($output, ['NO', 'NAMA MENU', 'KATEGORI', 'QTY TERJUAL', 'HARGA RATA-RATA', 'TOTAL PENDAPATAN', 'KONTRIBUSI (%)', 'RANK PENJUALAN']);
            fputcsv($output, ['=================================================================================']);

            $sql_export = "SELECT m.name as menu_name, m.category, m.price as menu_price, 
                          SUM(oi.quantity) as total_sold, 
                          SUM(oi.total_price) as total_revenue,
                          AVG(oi.price_per_item) as avg_price,
                          COUNT(DISTINCT o.id) as total_transactions
                          FROM order_items oi 
                          JOIN menu m ON oi.menu_id = m.id 
                          JOIN orders o ON oi.order_id = o.id 
                          WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status IN ('completed', 'paid') 
                          GROUP BY oi.menu_id 
                          ORDER BY total_revenue DESC";

            if ($stmt = $conn->prepare($sql_export)) {
                $stmt->bind_param("ss", $start_date, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();
                $data_rows = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $grand_total_revenue = array_sum(array_column($data_rows, 'total_revenue'));
                $grand_total_sold = array_sum(array_column($data_rows, 'total_sold'));
                $grand_total_transactions = array_sum(array_column($data_rows, 'total_transactions'));
                $no = 1;

                foreach ($data_rows as $row) {
                    $contribution = ($grand_total_revenue > 0) ? ($row['total_revenue'] / $grand_total_revenue) * 100 : 0;
                    $rank = '';
                    if ($contribution >= 20) $rank = 'TOP SELLER';
                    elseif ($contribution >= 10) $rank = 'POPULER';
                    elseif ($contribution >= 5) $rank = 'SEDANG';
                    else $rank = 'RENDAH';

                    fputcsv($output, [
                        $no++,
                        $row['menu_name'],
                        $row['category'],
                        number_format($row['total_sold']) . ' pcs',
                        'Rp ' . number_format($row['avg_price'], 0, ',', '.'),
                        'Rp ' . number_format($row['total_revenue'], 0, ',', '.'),
                        number_format($contribution, 2) . '%',
                        $rank
                    ]);
                }

                // Footer Summary
                fputcsv($output, ['=================================================================================']);
                fputcsv($output, ['', 'TOTAL KESELURUHAN', count($data_rows) . ' menu', number_format($grand_total_sold) . ' pcs', '', 'Rp ' . number_format($grand_total_revenue, 0, ',', '.'), '100.00%', '']);
                fputcsv($output, ['', 'RATA-RATA PER MENU', '', number_format($grand_total_sold / max(count($data_rows), 1), 2) . ' pcs', '', 'Rp ' . number_format($grand_total_revenue / max(count($data_rows), 1), 0, ',', '.'), '', '']);
                fputcsv($output, ['=================================================================================']);
            }
            break;

        case 'kategori':
            // Header Tabel
            fputcsv($output, ['LAPORAN PENJUALAN PER KATEGORI']);
            fputcsv($output, ['=================================================================================']);
            fputcsv($output, ['NO', 'KATEGORI MENU', 'JUMLAH MENU', 'QTY TERJUAL', 'RATA-RATA HARGA', 'TOTAL PENDAPATAN', 'KONTRIBUSI (%)', 'STATUS']);
            fputcsv($output, ['=================================================================================']);

            $sql_export = "SELECT m.category, 
                          COUNT(DISTINCT m.id) as menu_count,
                          SUM(oi.quantity) as total_sold, 
                          SUM(oi.total_price) as total_revenue,
                          AVG(oi.price_per_item) as avg_price
                          FROM order_items oi 
                          JOIN menu m ON oi.menu_id = m.id 
                          JOIN orders o ON oi.order_id = o.id 
                          WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status IN ('completed', 'paid') 
                          GROUP BY m.category 
                          ORDER BY total_revenue DESC";

            if ($stmt = $conn->prepare($sql_export)) {
                $stmt->bind_param("ss", $start_date, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();
                $data_rows = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $grand_total_revenue = array_sum(array_column($data_rows, 'total_revenue'));
                $grand_total_sold = array_sum(array_column($data_rows, 'total_sold'));
                $grand_total_menu = array_sum(array_column($data_rows, 'menu_count'));
                $no = 1;

                foreach ($data_rows as $row) {
                    $contribution = ($grand_total_revenue > 0) ? ($row['total_revenue'] / $grand_total_revenue) * 100 : 0;
                    $status = '';
                    if ($contribution >= 30) $status = 'KATEGORI UNGGULAN';
                    elseif ($contribution >= 15) $status = 'KATEGORI POPULER';
                    elseif ($contribution >= 5) $status = 'KATEGORI STANDAR';
                    else $status = 'KATEGORI RENDAH';

                    fputcsv($output, [
                        $no++,
                        $row['category'],
                        number_format($row['menu_count']) . ' menu',
                        number_format($row['total_sold']) . ' pcs',
                        'Rp ' . number_format($row['avg_price'], 0, ',', '.'),
                        'Rp ' . number_format($row['total_revenue'], 0, ',', '.'),
                        number_format($contribution, 2) . '%',
                        $status
                    ]);
                }

                // Footer Summary
                fputcsv($output, ['=================================================================================']);
                fputcsv($output, ['', 'TOTAL KESELURUHAN', number_format($grand_total_menu) . ' menu', number_format($grand_total_sold) . ' pcs', '', 'Rp ' . number_format($grand_total_revenue, 0, ',', '.'), '100.00%', '']);
                fputcsv($output, ['', 'RATA-RATA PER KATEGORI', number_format($grand_total_menu / max(count($data_rows), 1), 2) . ' menu', number_format($grand_total_sold / max(count($data_rows), 1), 2) . ' pcs', '', 'Rp ' . number_format($grand_total_revenue / max(count($data_rows), 1), 0, ',', '.'), '', '']);
                fputcsv($output, ['=================================================================================']);
            }
            break;

        case 'member':
            // Header Tabel
            fputcsv($output, ['LAPORAN AKTIVITAS MEMBER']);
            fputcsv($output, ['=================================================================================']);
            fputcsv($output, ['NO', 'NAMA MEMBER', 'TOTAL TRANSAKSI', 'TOTAL ITEM', 'TOTAL BELANJA', 'RATA-RATA/TRX', 'FREKUENSI', 'STATUS MEMBER']);
            fputcsv($output, ['=================================================================================']);

            $sql_export = "SELECT m.name as member_name, m.email, 
                          COUNT(o.id) as total_transactions, 
                          SUM(o.total_amount) as total_spent,
                          SUM(oi.quantity) as total_items,
                          MIN(o.created_at) as first_order,
                          MAX(o.created_at) as last_order
                          FROM orders o 
                          JOIN members m ON o.member_id = m.id 
                          LEFT JOIN order_items oi ON o.id = oi.order_id
                          WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status IN ('completed', 'paid') 
                          GROUP BY o.member_id 
                          ORDER BY total_spent DESC";

            $grand_total_transactions = 0;
            $grand_total_spent = 0;
            $grand_total_items = 0;
            $active_members = 0;
            $no = 1;

            if ($stmt = $conn->prepare($sql_export)) {
                $stmt->bind_param("ss", $start_date, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $avg_spent = ($row['total_transactions'] > 0) ? $row['total_spent'] / $row['total_transactions'] : 0;

                    // Hitung frekuensi berdasarkan periode
                    $days_diff = max(1, (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24));
                    $frequency = round($row['total_transactions'] / $days_diff * 30, 1); // per bulan

                    // Status member
                    $status = '';
                    if ($row['total_spent'] >= 1000000) $status = 'VIP MEMBER';
                    elseif ($row['total_spent'] >= 500000) $status = 'GOLD MEMBER';
                    elseif ($row['total_spent'] >= 200000) $status = 'SILVER MEMBER';
                    elseif ($row['total_transactions'] >= 5) $status = 'ACTIVE MEMBER';
                    else $status = 'NEW MEMBER';

                    fputcsv($output, [
                        $no++,
                        $row['member_name'],
                        number_format($row['total_transactions']) . ' kali',
                        number_format($row['total_items']) . ' item',
                        'Rp ' . number_format($row['total_spent'], 0, ',', '.'),
                        'Rp ' . number_format($avg_spent, 0, ',', '.'),
                        $frequency . ' per bulan',
                        $status
                    ]);

                    $grand_total_transactions += $row['total_transactions'];
                    $grand_total_spent += $row['total_spent'];
                    $grand_total_items += $row['total_items'];
                    $active_members++;
                }
                $stmt->close();
            }

            // Footer Summary
            $grand_avg = ($grand_total_transactions > 0) ? $grand_total_spent / $grand_total_transactions : 0;
            $avg_items_per_member = ($active_members > 0) ? $grand_total_items / $active_members : 0;

            fputcsv($output, ['=================================================================================']);
            fputcsv($output, ['', 'TOTAL KESELURUHAN', number_format($grand_total_transactions) . ' kali', number_format($grand_total_items) . ' item', 'Rp ' . number_format($grand_total_spent, 0, ',', '.'), 'Rp ' . number_format($grand_avg, 0, ',', '.'), '', '']);
            fputcsv($output, ['', 'JUMLAH MEMBER AKTIF', number_format($active_members) . ' member', '', '', '', '', '']);
            fputcsv($output, ['', 'RATA-RATA PER MEMBER', number_format($grand_total_transactions / max($active_members, 1), 2) . ' trx', number_format($avg_items_per_member, 2) . ' item', 'Rp ' . number_format($grand_total_spent / max($active_members, 1), 0, ',', '.'), '', '', '']);
            fputcsv($output, ['=================================================================================']);
            break;

        case 'kasir':
            // Header Tabel
            fputcsv($output, ['LAPORAN PERFORMA KASIR']);
            fputcsv($output, ['=================================================================================']);
            fputcsv($output, ['NO', 'NAMA KASIR', 'SHIFT KERJA', 'TOTAL TRX', 'TOTAL ITEM', 'TOTAL PENDAPATAN', 'RATA-RATA/TRX', 'PERFORMA', 'TARGET HARIAN']);
            fputcsv($output, ['=================================================================================']);

            $sql_export = "SELECT u.name as cashier_name, 
                          COUNT(o.id) as total_transactions, 
                          SUM(o.total_amount) as total_revenue,
                          SUM(oi.quantity) as total_items,
                          COUNT(DISTINCT DATE(o.created_at)) as working_days,
                          MIN(TIME(o.created_at)) as earliest_time,
                          MAX(TIME(o.created_at)) as latest_time
                          FROM orders o 
                          JOIN users u ON o.cashier_id = u.id 
                          LEFT JOIN order_items oi ON o.id = oi.order_id
                          WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status IN ('completed', 'paid') 
                          GROUP BY o.cashier_id 
                          ORDER BY total_revenue DESC";

            $grand_total_transactions = 0;
            $grand_total_revenue = 0;
            $grand_total_items = 0;
            $active_cashiers = 0;
            $no = 1;

            if ($stmt = $conn->prepare($sql_export)) {
                $stmt->bind_param("ss", $start_date, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $avg_revenue = ($row['total_transactions'] > 0) ? $row['total_revenue'] / $row['total_transactions'] : 0;
                    $daily_avg = ($row['working_days'] > 0) ? $row['total_revenue'] / $row['working_days'] : 0;

                    // Shift kerja
                    $shift = '';
                    $earliest = strtotime($row['earliest_time']);
                    $latest = strtotime($row['latest_time']);
                    if (date('H', $earliest) < 12 && date('H', $latest) < 17) $shift = 'PAGI (07-15)';
                    elseif (date('H', $earliest) >= 12 && date('H', $latest) >= 17) $shift = 'SORE (15-23)';
                    else $shift = 'FULL (07-23)';

                    // Performa kasir
                    $performance = '';
                    if ($daily_avg >= 2000000) $performance = 'EXCELLENT';
                    elseif ($daily_avg >= 1500000) $performance = 'VERY GOOD';
                    elseif ($daily_avg >= 1000000) $performance = 'GOOD';
                    elseif ($daily_avg >= 500000) $performance = 'AVERAGE';
                    else $performance = 'NEEDS IMPROVEMENT';

                    // Target harian (asumsi target 1.5 juta per hari)
                    $target_status = ($daily_avg >= 1500000) ? 'TERCAPAI ✓' : 'BELUM TERCAPAI';

                    fputcsv($output, [
                        $no++,
                        $row['cashier_name'],
                        $shift,
                        number_format($row['total_transactions']) . ' trx',
                        number_format($row['total_items']) . ' item',
                        'Rp ' . number_format($row['total_revenue'], 0, ',', '.'),
                        'Rp ' . number_format($avg_revenue, 0, ',', '.'),
                        $performance,
                        $target_status
                    ]);

                    $grand_total_transactions += $row['total_transactions'];
                    $grand_total_revenue += $row['total_revenue'];
                    $grand_total_items += $row['total_items'];
                    $active_cashiers++;
                }
                $stmt->close();
            }

            // Footer Summary
            $grand_avg = ($grand_total_transactions > 0) ? $grand_total_revenue / $grand_total_transactions : 0;
            $avg_per_cashier = ($active_cashiers > 0) ? $grand_total_revenue / $active_cashiers : 0;

            fputcsv($output, ['=================================================================================']);
            fputcsv($output, ['', 'TOTAL KESELURUHAN', '', number_format($grand_total_transactions) . ' trx', number_format($grand_total_items) . ' item', 'Rp ' . number_format($grand_total_revenue, 0, ',', '.'), 'Rp ' . number_format($grand_avg, 0, ',', '.'), '', '']);
            fputcsv($output, ['', 'JUMLAH KASIR AKTIF', number_format($active_cashiers) . ' kasir', '', '', '', '', '', '']);
            fputcsv($output, ['', 'RATA-RATA PER KASIR', '', number_format($grand_total_transactions / max($active_cashiers, 1), 2) . ' trx', number_format($grand_total_items / max($active_cashiers, 1), 2) . ' item', 'Rp ' . number_format($avg_per_cashier, 0, ',', '.'), '', '', '']);
            fputcsv($output, ['=================================================================================']);
            break;

        default: // 'ringkasan'
            // BAGIAN 1: RINGKASAN UTAMA DENGAN PERHITUNGAN LENGKAP
            $summary_data = ['total_rev' => 0, 'total_tx' => 0, 'total_disc' => 0, 'total_tax' => 0, 'total_items' => 0];
            $sql_summary = "SELECT SUM(o.total_amount) as total_rev, 
                           COUNT(o.id) as total_tx, 
                           SUM(o.discount_amount) as total_disc, 
                           SUM(o.tax) as total_tax,
                           SUM(oi.quantity) as total_items,
                           COUNT(DISTINCT o.member_id) as unique_members,
                           COUNT(DISTINCT o.cashier_id) as active_cashiers
                           FROM orders o 
                           LEFT JOIN order_items oi ON o.id = oi.order_id
                           WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status IN ('completed', 'paid')";
            if ($stmt_sum = $conn->prepare($sql_summary)) {
                $stmt_sum->bind_param("ss", $start_date, $end_date);
                $stmt_sum->execute();
                $res_sum = $stmt_sum->get_result();
                if ($row = $res_sum->fetch_assoc()) {
                    $summary_data = $row;
                }
                $stmt_sum->close();
            }

            // Hitung pendapatan bersih
            $net_revenue = ($summary_data['total_rev'] ?? 0) - ($summary_data['total_disc'] ?? 0);
            $avg_trx = (($summary_data['total_tx'] ?? 0) > 0) ? (($summary_data['total_rev'] ?? 0) / $summary_data['total_tx']) : 0;
            $avg_items_per_trx = (($summary_data['total_tx'] ?? 0) > 0) ? (($summary_data['total_items'] ?? 0) / $summary_data['total_tx']) : 0;

            // Hitung periode dalam hari
            $start_timestamp = strtotime($start_date);
            $end_timestamp = strtotime($end_date);
            $days_period = max(1, ($end_timestamp - $start_timestamp) / (60 * 60 * 24) + 1);
            $daily_avg_revenue = ($summary_data['total_rev'] ?? 0) / $days_period;
            $daily_avg_transactions = ($summary_data['total_tx'] ?? 0) / $days_period;

            fputcsv($output, ['RINGKASAN PENJUALAN KESELURUHAN']);
            fputcsv($output, ['=================================================================================']);
            fputcsv($output, ['METRIK PENJUALAN', 'NILAI', 'KETERANGAN']);
            fputcsv($output, ['=================================================================================']);
            fputcsv($output, ['Total Pendapatan Kotor', 'Rp ' . number_format($summary_data['total_rev'] ?? 0, 0, ',', '.'), 'Sebelum diskon']);
            fputcsv($output, ['Total Diskon Diberikan', 'Rp ' . number_format($summary_data['total_disc'] ?? 0, 0, ',', '.'), 'Potongan harga']);
            fputcsv($output, ['Pendapatan Bersih', 'Rp ' . number_format($net_revenue, 0, ',', '.'), 'Setelah diskon']);
            fputcsv($output, ['Total Pajak (PPN 11%)', 'Rp ' . number_format($summary_data['total_tax'] ?? 0, 0, ',', '.'), 'Pajak terkumpul']);
            fputcsv($output, ['=================================================================================']);
            fputcsv($output, ['STATISTIK TRANSAKSI', '', '']);
            fputcsv($output, ['Total Transaksi', number_format($summary_data['total_tx'] ?? 0) . ' transaksi', 'Periode ' . $days_period . ' hari']);
            fputcsv($output, ['Total Item Terjual', number_format($summary_data['total_items'] ?? 0) . ' item', 'Semua produk']);
            fputcsv($output, ['Rata-rata per Transaksi', 'Rp ' . number_format($avg_trx, 0, ',', '.'), 'Per transaksi']);
            fputcsv($output, ['Rata-rata Item per Transaksi', number_format($avg_items_per_trx, 2) . ' item', 'Per transaksi']);
            fputcsv($output, ['=================================================================================']);
            fputcsv($output, ['ANALISIS HARIAN', '', '']);
            fputcsv($output, ['Rata-rata Pendapatan Harian', 'Rp ' . number_format($daily_avg_revenue, 0, ',', '.'), 'Per hari']);
            fputcsv($output, ['Rata-rata Transaksi Harian', number_format($daily_avg_transactions, 2) . ' transaksi', 'Per hari']);
            fputcsv($output, ['Member Aktif', number_format($summary_data['unique_members'] ?? 0) . ' member', 'Yang bertransaksi']);
            fputcsv($output, ['Kasir Aktif', number_format($summary_data['active_cashiers'] ?? 0) . ' kasir', 'Yang bertugas']);
            fputcsv($output, ['=================================================================================']);
            fputcsv($output, []);

            // BAGIAN 2: REKAP ADDON
            $total_pendapatan_addon = 0;
            $addon_summary = [];

            $sql_orders = "SELECT o.id as order_id FROM orders o WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status IN ('completed', 'paid')";
            if ($stmt_orders = $conn->prepare($sql_orders)) {
                $stmt_orders->bind_param("ss", $start_date, $end_date);
                $stmt_orders->execute();
                $result_orders = $stmt_orders->get_result();
                while ($order = $result_orders->fetch_assoc()) {
                    $order_id = $order['order_id'];
                    $sql_items = "SELECT oi.quantity, oi.selected_addons FROM order_items oi WHERE oi.order_id = ?";
                    if ($stmt_items = $conn->prepare($sql_items)) {
                        $stmt_items->bind_param("i", $order_id);
                        $stmt_items->execute();
                        $result_items = $stmt_items->get_result();
                        while ($item = $result_items->fetch_assoc()) {
                            if (!empty($item['selected_addons'])) {
                                $addons = json_decode($item['selected_addons'], true);
                                if (is_array($addons)) {
                                    foreach ($addons as $addon) {
                                        $addon_qty = $item['quantity'];
                                        $addon_price = isset($addon['price']) ? $addon['price'] : 0;
                                        $addon_subtotal = $addon_qty * $addon_price;
                                        $total_pendapatan_addon += $addon_subtotal;
                                        $addon_name = $addon['option_name'] ?? 'Addon Tidak Dikenal';
                                        if (!isset($addon_summary[$addon_name])) {
                                            $addon_summary[$addon_name] = ['qty' => 0, 'pendapatan' => 0];
                                        }
                                        $addon_summary[$addon_name]['qty'] += $addon_qty;
                                        $addon_summary[$addon_name]['pendapatan'] += $addon_subtotal;
                                    }
                                }
                            }
                        }
                        $stmt_items->close();
                    }
                }
                $stmt_orders->close();
            }

            if (!empty($addon_summary)) {
                fputcsv($output, ['DETAIL PENDAPATAN ADDON']);
                fputcsv($output, ['-----------------------------------------------------------------']);
                fputcsv($output, ['NO', 'NAMA ADDON', 'QTY TERJUAL', 'TOTAL PENDAPATAN']);
                fputcsv($output, ['-----------------------------------------------------------------']);
                $no = 1;
                foreach ($addon_summary as $addon_name => $row) {
                    fputcsv($output, [
                        $no++,
                        $addon_name,
                        number_format($row['qty']),
                        'Rp ' . number_format($row['pendapatan'], 0, ',', '.')
                    ]);
                }
                fputcsv($output, ['-----------------------------------------------------------------']);
                fputcsv($output, ['', 'TOTAL ADDON', number_format(array_sum(array_column($addon_summary, 'qty'))), 'Rp ' . number_format($total_pendapatan_addon, 0, ',', '.')]);
                fputcsv($output, ['=================================================================']);
                fputcsv($output, []);
            }

            // BAGIAN 3: DETAIL TRANSAKSI LENGKAP
            fputcsv($output, ['DETAIL TRANSAKSI LENGKAP']);
            fputcsv($output, ['=======================================================================================================================================================']);
            fputcsv($output, ['TANGGAL', 'ID ORDER', 'NAMA MENU', 'KATEGORI', 'QTY', 'HARGA SATUAN', 'SUBTOTAL', 'ADDON', 'HARGA ADDON', 'QTY ADDON', 'SUBTOTAL ADDON', 'PELANGGAN', 'KASIR', 'METODE BAYAR']);
            fputcsv($output, ['-------------------------------------------------------------------------------------------------------------------------------------------------------']);

            $sql_orders = "SELECT o.id as order_id, DATE_FORMAT(o.created_at, '%d-%m-%Y %H:%i') as formatted_date, COALESCE(mem.name, o.customer_name, 'Guest') as customer_name, COALESCE(uc.name, 'N/A') as cashier_name, o.payment_method FROM orders o LEFT JOIN members mem ON o.member_id = mem.id LEFT JOIN users uc ON o.cashier_id = uc.id WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status IN ('completed', 'paid') ORDER BY o.created_at ASC";

            if ($stmt_orders = $conn->prepare($sql_orders)) {
                $stmt_orders->bind_param("ss", $start_date, $end_date);
                $stmt_orders->execute();
                $result_orders = $stmt_orders->get_result();

                while ($order = $result_orders->fetch_assoc()) {
                    $order_id = $order['order_id'];
                    $sql_items = "SELECT oi.*, m.name as menu_name, m.category FROM order_items oi JOIN menu m ON oi.menu_id = m.id WHERE oi.order_id = ?";

                    if ($stmt_items = $conn->prepare($sql_items)) {
                        $stmt_items->bind_param("i", $order_id);
                        $stmt_items->execute();
                        $result_items = $stmt_items->get_result();

                        while ($item = $result_items->fetch_assoc()) {
                            $subtotal = isset($item['subtotal']) ? $item['subtotal'] : (isset($item['total_price']) ? $item['total_price'] : 0);

                            // Baris utama menu
                            fputcsv($output, [
                                $order['formatted_date'],
                                '#' . $order_id,
                                $item['menu_name'],
                                $item['category'],
                                number_format($item['quantity']),
                                'Rp ' . number_format($item['price_per_item'], 0, ',', '.'),
                                'Rp ' . number_format($subtotal, 0, ',', '.'),
                                '',
                                '',
                                '',
                                '',
                                $order['customer_name'],
                                $order['cashier_name'],
                                ucfirst(str_replace('_', ' ', $order['payment_method']))
                            ]);

                            // Baris addon jika ada
                            if (!empty($item['selected_addons'])) {
                                $addons = json_decode($item['selected_addons'], true);
                                if (is_array($addons)) {
                                    foreach ($addons as $addon) {
                                        $addon_qty = $item['quantity'];
                                        $addon_price = isset($addon['price']) ? $addon['price'] : 0;
                                        $addon_subtotal = $addon_qty * $addon_price;

                                        fputcsv($output, [
                                            '',
                                            '',
                                            '└─ ' . $item['menu_name'],
                                            '',
                                            '',
                                            '',
                                            '',
                                            '+ ' . ($addon['option_name'] ?? 'Addon'),
                                            'Rp ' . number_format($addon_price, 0, ',', '.'),
                                            number_format($addon_qty),
                                            'Rp ' . number_format($addon_subtotal, 0, ',', '.'),
                                            '',
                                            '',
                                            ''
                                        ]);
                                    }
                                }
                            }
                        }
                        $stmt_items->close();
                    }
                }
                $stmt_orders->close();
            }

            // Footer untuk laporan ringkasan
            fputcsv($output, ['=======================================================================================================================================================']);
            fputcsv($output, []);
            fputcsv($output, ['--- AKHIR LAPORAN ---']);
            fputcsv($output, ['Dicetak pada: ' . date('d F Y, H:i:s') . ' WIB']);
            fputcsv($output, ['Sistem Laporan ' . ($cafe_name)]);
            break;
    }

    // FOOTER UNIVERSAL UNTUK SEMUA LAPORAN DENGAN RINGKASAN GLOBAL
    fputcsv($output, []);
    fputcsv($output, ['=================================================================================']);
    fputcsv($output, ['RINGKASAN GLOBAL PERIODE LAPORAN']);
    fputcsv($output, ['=================================================================================']);
    fputcsv($output, ['Total Transaksi Keseluruhan', number_format($global_summary['total_transactions'] ?? 0) . ' transaksi']);
    fputcsv($output, ['Total Pendapatan Keseluruhan', 'Rp ' . number_format($global_summary['total_revenue'] ?? 0, 0, ',', '.')]);
    fputcsv($output, ['Total Item Terjual Keseluruhan', number_format($global_summary['total_items'] ?? 0) . ' item']);

    // Hitung persentase periode
    $period_days = max(1, (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1);
    $monthly_projection = ($global_summary['total_revenue'] ?? 0) * (30 / $period_days);
    $yearly_projection = ($global_summary['total_revenue'] ?? 0) * (365 / $period_days);

    fputcsv($output, ['Proyeksi Pendapatan Bulanan', 'Rp ' . number_format($monthly_projection, 0, ',', '.')]);
    fputcsv($output, ['Proyeksi Pendapatan Tahunan', 'Rp ' . number_format($yearly_projection, 0, ',', '.')]);
    fputcsv($output, ['=================================================================================']);
    fputcsv($output, []);
    fputcsv($output, ['INFORMASI LAPORAN']);
    fputcsv($output, ['=================================================================================']);
    fputcsv($output, ['Nama Bisnis', $cafe_name]);
    fputcsv($output, ['Jenis Laporan', strtoupper(str_replace('_', ' ', $view))]);
    fputcsv($output, ['Periode Laporan', date('d F Y', strtotime($start_date)) . ' sampai ' . date('d F Y', strtotime($end_date))]);
    fputcsv($output, ['Durasi Periode', number_format($period_days) . ' hari']);
    fputcsv($output, ['Tanggal Export', date('d F Y, H:i:s') . ' WIB']);
    fputcsv($output, ['Format File', 'CSV (Comma Separated Values)']);
    fputcsv($output, ['Sistem', 'Sistem Laporan Penjualan ' . $cafe_name]);
    fputcsv($output, ['=================================================================================']);
    fputcsv($output, []);
    fputcsv($output, ['📊 TERIMA KASIH TELAH MENGGUNAKAN SISTEM LAPORAN ' . strtoupper($cafe_name)]);
    fputcsv($output, ['📋 File ini dibuat secara otomatis dengan data terkini dan akurat.']);
    fputcsv($output, ['💼 Untuk informasi lebih lanjut, hubungi tim manajemen.']);
    fputcsv($output, []);
    fputcsv($output, ['=== AKHIR LAPORAN ===']);

    fclose($output);
    $conn->close();
    exit();
}


// --- BAGIAN TAMPILAN HALAMAN (VIEW) ---
require_once 'includes/header.php';

// --- PENGAMBILAN DATA UNTUK TAMPILAN ---
$report_data = [];
$page_title = "Laporan Penjualan";
$total_revenue = 0; // Untuk kartu statistik dan grafik
// --- QUERY MENU TERFAVORIT & MENU TERLARIS PER KATEGORI ---
$favorite_menu = null;
$top_menu_per_category = [];

// Query menu terfavorit (paling banyak dibeli, total penjualan tertinggi)
$sql_fav = "SELECT m.id, m.name, m.category, SUM(oi.quantity) as total_sold, SUM(oi.total_price) as total_revenue FROM order_items oi JOIN menu m ON oi.menu_id = m.id JOIN orders o ON oi.order_id = o.id WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status IN ('completed', 'paid') GROUP BY oi.menu_id ORDER BY total_sold DESC, total_revenue DESC LIMIT 1";
if ($stmt_fav = $conn->prepare($sql_fav)) {
    $stmt_fav->bind_param("ss", $start_date, $end_date);
    $stmt_fav->execute();
    $result_fav = $stmt_fav->get_result();
    $favorite_menu = $result_fav->fetch_assoc();
    $stmt_fav->close();
}

// Query menu terlaris per kategori
$sql_topcat = "SELECT m.category, m.name, SUM(oi.quantity) as total_sold FROM order_items oi JOIN menu m ON oi.menu_id = m.id JOIN orders o ON oi.order_id = o.id WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status IN ('completed', 'paid') GROUP BY m.category, oi.menu_id ORDER BY m.category ASC, total_sold DESC";
if ($stmt_topcat = $conn->prepare($sql_topcat)) {
    $stmt_topcat->bind_param("ss", $start_date, $end_date);
    $stmt_topcat->execute();
    $result_topcat = $stmt_topcat->get_result();
    $cat_seen = [];
    while ($row = $result_topcat->fetch_assoc()) {
        $cat = $row['category'];
        if (!isset($cat_seen[$cat])) {
            $top_menu_per_category[$cat] = $row;
            $cat_seen[$cat] = true;
        }
    }
    $stmt_topcat->close();
}

// Query disesuaikan berdasarkan view yang dipilih
switch ($view) {
    case 'menu':
        $page_title = "Laporan Performa Menu";
        $sql_report = "SELECT m.name as menu_name, m.category, SUM(oi.quantity) as total_sold, SUM(oi.total_price) as total_revenue FROM order_items oi JOIN menu m ON oi.menu_id = m.id JOIN orders o ON oi.order_id = o.id WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status IN ('completed', 'paid') GROUP BY oi.menu_id ORDER BY total_revenue DESC";
        break;
    case 'kategori':
        $page_title = "Laporan Penjualan per Kategori";
        $sql_report = "SELECT m.category, SUM(oi.quantity) as total_sold, SUM(oi.total_price) as total_revenue FROM order_items oi JOIN menu m ON oi.menu_id = m.id JOIN orders o ON oi.order_id = o.id WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status IN ('completed', 'paid') GROUP BY m.category ORDER BY total_revenue DESC";
        break;
    case 'member':
        $page_title = "Laporan Aktivitas Member";
        $sql_report = "SELECT m.name as member_name, COUNT(o.id) as total_transactions, SUM(o.total_amount) as total_spent FROM orders o JOIN members m ON o.member_id = m.id WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status IN ('completed', 'paid') GROUP BY o.member_id ORDER BY total_spent DESC";
        break;
    case 'kasir':
        $page_title = "Laporan Performa Kasir";
        $sql_report = "SELECT u.name as cashier_name, COUNT(o.id) as total_transactions, SUM(o.total_amount) as total_revenue FROM orders o JOIN users u ON o.cashier_id = u.id WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status IN ('completed', 'paid') GROUP BY o.cashier_id ORDER BY total_revenue DESC";
        break;
    default: // 'ringkasan'
        $page_title = "Laporan Ringkasan Penjualan";
        // Query untuk detail pesanan di tabel ringkasan
        $sql_report = "SELECT o.id, o.created_at, o.total_amount, COALESCE(mem.name, o.customer_name, 'Guest') as customer_name FROM orders o LEFT JOIN members mem ON o.member_id = mem.id WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status IN ('completed', 'paid') ORDER BY o.created_at DESC";

        // Data untuk Grafik Tren Penjualan
        $chart_labels = [];
        $chart_data = [];
        $sql_chart = "SELECT DATE(created_at) as sales_date, SUM(total_amount) as daily_total FROM orders WHERE created_at BETWEEN ? AND ? AND status IN ('completed', 'paid') GROUP BY DATE(created_at) ORDER BY sales_date ASC";
        if ($stmt_chart = $conn->prepare($sql_chart)) {
            $stmt_chart->bind_param("ss", $start_date, $end_date);
            $stmt_chart->execute();
            $result_chart = $stmt_chart->get_result();
            while ($row = $result_chart->fetch_assoc()) {
                $chart_labels[] = date('d M', strtotime($row['sales_date']));
                $chart_data[] = $row['daily_total'];
            }
            $stmt_chart->close();
        }
        break;
}

if ($stmt = $conn->prepare($sql_report)) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $report_data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Data untuk kartu statistik (hanya dihitung jika view 'ringkasan')
if ($view === 'ringkasan') {
    foreach ($report_data as $order) {
        $total_revenue += $order['total_amount'];
    }
    $total_transactions = count($report_data);
}

?>

<div class="container mx-auto p-4 md:p-6">
    <!-- Header dan Filter -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <h1 class="text-3xl font-bold text-gray-800"><?= $page_title ?></h1>
        <div class="flex items-center gap-4">
            <form id="filterForm" method="GET" class="flex items-center gap-2 bg-white p-2 rounded-lg shadow">
                <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="border p-2 rounded-lg text-sm">
                <span class="text-gray-500">hingga</span>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="border p-2 rounded-lg text-sm">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Filter</button>
            </form>
            <a href="laporan.php?action=cetak_excel&view=<?= htmlspecialchars($view) ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2 shadow">
                <i class="fas fa-file-excel"></i> Export
            </a>
        </div>
    </div>

    <!-- Navigasi Tab -->
    <div class="mb-6 border-b border-gray-200">
        <nav class="flex -mb-px space-x-6 overflow-x-auto">
            <a href="?view=ringkasan&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="py-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap <?= $view == 'ringkasan' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">Ringkasan</a>
            <a href="?view=menu&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="py-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap <?= $view == 'menu' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">Per Menu</a>
            <a href="?view=kategori&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="py-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap <?= $view == 'kategori' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">Per Kategori</a>
            <a href="?view=member&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="py-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap <?= $view == 'member' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">Per Member</a>
            <a href="?view=kasir&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="py-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap <?= $view == 'kasir' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">Per Kasir</a>
        </nav>
    </div>

    <!-- Konten Dinamis Berdasarkan Tab -->
    <?php if ($view === 'ringkasan'): ?>
        <!-- Tampilan untuk Ringkasan -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
            <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">Grafik Tren Pendapatan</h3>
                <div class="relative h-80">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>
            <div class="space-y-4">
                <div class="bg-white p-6 rounded-xl shadow flex items-center gap-4">
                    <i class="fas fa-wallet text-3xl text-green-500"></i>
                    <div>
                        <p class="text-sm text-gray-500">Total Pendapatan</p>
                        <p class="text-2xl font-bold text-gray-800">Rp <?= number_format($total_revenue, 0, ',', '.') ?></p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow flex items-center gap-4">
                    <i class="fas fa-receipt text-3xl text-blue-500"></i>
                    <div>
                        <p class="text-sm text-gray-500">Total Pesanan</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $total_transactions ?></p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow flex items-center gap-4">
                    <i class="fas fa-star text-3xl text-yellow-500"></i>
                    <div>
                        <p class="text-sm text-gray-500">Menu Terfavorit</p>
                        <?php if ($favorite_menu): ?>
                            <p class="text-lg font-bold text-gray-800"><?= htmlspecialchars($favorite_menu['name']) ?></p>
                            <p class="text-xs text-gray-500">Kategori: <?= htmlspecialchars($favorite_menu['category']) ?></p>
                            <p class="text-xs text-gray-500">Terjual: <?= number_format($favorite_menu['total_sold']) ?>x</p>
                        <?php else: ?>
                            <p class="text-gray-500">Belum ada data penjualan.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow">
                    <p class="text-sm text-gray-500 font-semibold mb-2"><i class="fas fa-list text-blue-500 mr-2"></i>Menu Paling Sering Dibeli per Kategori</p>
                    <?php if (!empty($top_menu_per_category)): ?>
                        <ul class="space-y-1">
                            <?php foreach ($top_menu_per_category as $cat => $row): ?>
                                <li class="flex justify-between items-center text-sm">
                                    <span class="font-semibold text-gray-700">Kategori: <?= htmlspecialchars($cat) ?></span>
                                    <span><?= htmlspecialchars($row['name']) ?> <span class="text-xs text-gray-500">(<?= number_format($row['total_sold']) ?>x)</span></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-gray-500">Belum ada data penjualan.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Detail Transaksi</h3>
        <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
            <table id="report-table" class="min-w-full leading-normal">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase">ID</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase">Tanggal</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase">Pelanggan</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-right text-xs font-semibold text-gray-600 uppercase">Total (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($report_data)): ?>
                        <?php foreach ($report_data as $order): ?>
                            <tr class="hover:bg-gray-100 cursor-pointer" data-order-id="<?= $order['id'] ?>">
                                <td class="px-5 py-4 border-b border-gray-200 text-sm">#<?= $order['id'] ?></td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm font-medium"><?= htmlspecialchars($order['customer_name'] ?? 'Guest') ?></td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm text-right font-semibold"><?= number_format($order['total_amount'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-10 text-gray-500">Tidak ada transaksi pada rentang tanggal ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <!-- Tampilan untuk Tabel Laporan Lainnya -->
        <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead class="bg-gray-50">
                    <tr>
                        <?php if (!empty($report_data)): ?>
                            <?php foreach (array_keys($report_data[0]) as $header): ?>
                                <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $header))) ?></th>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($report_data)): ?>
                        <?php foreach ($report_data as $row): ?>
                            <tr class="hover:bg-gray-100">
                                <?php foreach ($row as $key => $cell): ?>
                                    <td class="px-5 py-4 border-b border-gray-200 text-sm">
                                        <?php
                                        // Format angka menjadi rupiah jika kolom berisi 'revenue', 'spent', atau 'total'
                                        if (strpos($key, 'revenue') !== false || strpos($key, 'spent') !== false || strpos($key, 'total_amount') !== false) {
                                            echo 'Rp ' . number_format($cell, 0, ',', '.');
                                        } else {
                                            echo htmlspecialchars($cell);
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-10 text-gray-500">Tidak ada data untuk laporan ini pada rentang tanggal yang dipilih.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<!-- Modal untuk Detail Pesanan (Tetap Sama, hanya aktif di tab Ringkasan) -->
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Logika Modal (hanya aktif jika ada tabel #report-table di tab ringkasan)
        const reportTable = document.querySelector('#report-table');
        if (reportTable) {
            const modal = document.getElementById('detailModal');
            const closeModalBtn = document.getElementById('closeModal');
            const modalContent = document.getElementById('modalContent');
            const modalOrderId = document.getElementById('modalOrderId');

            const openModal = () => modal.classList.remove('hidden');
            const closeModal = () => modal.classList.add('hidden');

            reportTable.addEventListener('click', function(e) {
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
                let itemsHtml = items.map(item => {
                    let baseItemTotal = item.quantity * item.price;
                    let addonsHtml = '';
                    try {
                        const addons = JSON.parse(item.selected_addons);
                        if (Array.isArray(addons) && addons.length > 0) {
                            addonsHtml = addons.map(addon => `<div class="flex justify-between pl-4 text-gray-600 text-xs"><span>+ ${addon.option_name}</span><span>${formatRupiah(item.quantity * parseFloat(addon.price || 0))}</span></div>`).join('');
                        }
                    } catch (e) {}
                    return `<div class="py-2 border-b last:border-b-0"><div class="flex justify-between"><span class="font-semibold">${item.quantity}x ${item.name}</span><span>${formatRupiah(baseItemTotal)}</span></div>${addonsHtml}<div class="flex justify-between items-center font-bold text-gray-800 border-t border-dashed mt-2 pt-1"><span>Total Item</span><span>${formatRupiah(item.subtotal)}</span></div></div>`;
                }).join('');
                const customerLabel = details.is_member ? 'Member' : 'Pelanggan';
                modalContent.innerHTML = `<div class="grid grid-cols-2 gap-x-4 gap-y-2 mb-4"><div class="text-gray-600">Tanggal:</div><div class="font-semibold text-right">${orderDate}</div><div class="text-gray-600">${customerLabel}:</div><div class="font-semibold text-right">${details.customer_name_final}</div><div class="text-gray-600">Kasir:</div><div class="font-semibold text-right">${details.cashier_name || 'N/A'}</div></div><h3 class="font-bold mt-4 mb-2 text-gray-700">Rincian Item</h3><div class="space-y-1 mb-4">${itemsHtml}</div><hr class="my-3 border-gray-200"><div class="space-y-1 text-right text-gray-800"><div class="flex justify-between"><span>Subtotal</span><span>${formatRupiah(details.subtotal)}</span></div><div class="flex justify-between"><span>Diskon</span><span>- ${formatRupiah(details.discount_amount)}</span></div><div class="flex justify-between"><span>PPN (11%)</span><span>${formatRupiah(details.tax)}</span></div><div class="flex justify-between font-bold text-lg border-t pt-2 mt-2"><span>TOTAL</span><span>${formatRupiah(details.total_amount)}</span></div><div class="flex justify-between text-sm text-gray-600"><span>Metode Bayar</span><span class="capitalize">${(details.payment_method || '').replace('_', ' ')}</span></div></div>`;
            }

            closeModalBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal();
            });
        }

        // Logika Grafik (hanya aktif jika ada canvas #salesTrendChart di tab ringkasan)
        const salesChartCanvas = document.getElementById('salesTrendChart');
        if (salesChartCanvas) {
            const salesCtx = salesChartCanvas.getContext('2d');
            new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_labels ?? []) ?>,
                    datasets: [{
                        label: 'Pendapatan (Rp)',
                        data: <?= json_encode($chart_data ?? []) ?>,
                        backgroundColor: 'rgba(59, 130, 246, 0.2)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 2,
                        tension: 0.3,
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
                                callback: (value) => formatRupiah(value)
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => `Pendapatan: ${formatRupiah(context.parsed.y)}`
                            }
                        }
                    }
                }
            });
        }

        function formatRupiah(angka) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(angka);
        }
    });
</script>

<?php
require_once 'includes/footer.php';
$conn->close();
?>