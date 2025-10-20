<?php
// =========================================================================
//  BAGIAN KONFIGURASI DAN INISIALISASI
// =========================================================================

require_once '../app/config/db_connect.php';
require_once '../app/helpers/middleware.php';

// Apply security middleware
Middleware::applySecurityMiddleware();

// Require kasir authentication
Middleware::requireKasirAuth();

$cashier_name = $_SESSION['kasir']['name']; // Already sanitized during login
$cashier_id = (int)$_SESSION['kasir']['id'];

// Mengambil informasi toko dari database untuk struk
$settings = [];
$settings_sql = "SELECT setting_name, setting_value FROM settings WHERE setting_name IN ('cafe_name', 'cafe_address', 'cafe_phone')";
if ($settings_result = $conn->query($settings_sql)) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
}
$cafe_settings = [
    'name'    => $settings['cafe_name'] ?? 'Nama Cafe Anda',
    'address' => $settings['cafe_address'] ?? 'Alamat Cafe Anda',
    'phone'   => $settings['cafe_phone'] ?? 'Telepon Cafe Anda'
];

// =========================================================================
//  LOGIC BAGIAN A: Menangani Permintaan AJAX
// =========================================================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'data' => null, 'message' => 'Invalid action'];

    // --- Endpoint untuk mengambil detail item pesanan ---
    if ($_GET['action'] === 'get_order_details' && isset($_GET['order_id'])) {
        $order_id = (int)$_GET['order_id'];

        $sql_order = "SELECT o.subtotal, o.discount_amount, o.tax, o.total_amount, o.order_type, t.table_number 
                      FROM orders o 
                      LEFT JOIN tables t ON o.table_id = t.id 
                      WHERE o.id = ?";
        $stmt_order = $conn->prepare($sql_order);
        $stmt_order->bind_param("i", $order_id);
        $stmt_order->execute();
        $order_data = $stmt_order->get_result()->fetch_assoc();

        $sql_items = "SELECT m.name, oi.quantity, oi.price_per_item AS price, oi.total_price, oi.selected_addons 
                      FROM order_items oi 
                      JOIN menu m ON oi.menu_id = m.id 
                      WHERE oi.order_id = ?";
        $stmt_items = $conn->prepare($sql_items);
        $stmt_items->bind_param("i", $order_id);
        $stmt_items->execute();
        $result_items = $stmt_items->get_result();
        $items = [];
        while ($row = $result_items->fetch_assoc()) {
            $row['price'] = (float)$row['price'];
            $row['total_price'] = (float)$row['total_price'];
            $row['quantity'] = (int)$row['quantity'];
            $items[] = $row;
        }

        $response_data = [
            'items' => $items,
            'subtotal' => (float)($order_data['subtotal'] ?? 0),
            'discount' => (float)($order_data['discount_amount'] ?? 0),
            'tax' => (float)($order_data['tax'] ?? 0),
            'total_amount' => (float)($order_data['total_amount'] ?? 0),
            'table_number' => $order_data['table_number'] ?? null,
            'order_type' => $order_data['order_type'] ?? 'dine-in'
        ];
        $response = ['success' => true, 'data' => $response_data];
    }

    // Endpoint untuk REAL-TIME UPDATE
    if ($_GET['action'] === 'get_latest_orders') {
        // [PERBAIKAN] Query untuk mengambil nama final dan status member.
        // - Mengganti JOIN ke tabel members dari `o.user_id` menjadi `o.member_id`.
        // - Mengganti alias tabel members dari `u` menjadi `m` agar lebih jelas.
        // - Kondisi `is_member` sekarang didasarkan pada `o.member_id IS NOT NULL`, yang merupakan cara yang benar.
        $base_select = "SELECT o.*, t.table_number, 
                               COALESCE(m.name, o.customer_name, 'Guest') AS final_customer_name,
                               (o.member_id IS NOT NULL) AS is_member,
                               c.name AS cashier_processor_name 
                        FROM orders o 
                        LEFT JOIN tables t ON o.table_id = t.id 
                        LEFT JOIN members m ON o.member_id = m.id 
                        LEFT JOIN users c ON o.cashier_id = c.id";

        // 1. Pending Orders (Tunai)
        $pending_html = '';
        $sql_pending = "$base_select WHERE o.status = 'pending_payment' AND (o.payment_method = 'cash' OR o.payment_method = 'manual_cash') ORDER BY o.created_at DESC";
        $result_pending = $conn->query($sql_pending);
        if ($result_pending && $result_pending->num_rows > 0) {
            while ($order = $result_pending->fetch_assoc()) {
                ob_start();
?>
                <div class="bg-yellow-50 p-4 rounded-lg shadow-sm border border-yellow-200">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-baseline gap-2 mb-3">
                        <h3 class="font-bold text-lg text-gray-800">Order #<?= $order['id'] ?></h3>
                        <span class="text-sm text-gray-500"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></span>
                    </div>

                    <p class="text-sm text-gray-600 mb-2 flex items-center">
                        Pemesan:
                        <span class="font-semibold ml-1 <?= $order['is_member'] ? 'text-green-700' : '' ?>"><?= htmlspecialchars($order['final_customer_name']) ?></span>
                        <?php if ($order['is_member']) : ?>
                            <span class="ml-2 text-xs font-semibold bg-green-100 text-green-800 px-2 py-0.5 rounded-full">MEMBER</span>
                        <?php endif; ?>
                    </p>

                    <p class="text-sm text-gray-600 mb-2">Tipe:
                        <span class="font-semibold">
                            <?= ($order['order_type'] === 'take-away') ? 'Take Away' : 'Dine-In (Meja ' . htmlspecialchars($order['table_number']) . ')' ?>
                        </span>
                    </p>

                    <p class="text-sm text-gray-600 mb-4">Total: <span class="font-bold text-lg text-gray-800">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span></p>
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="process_cash_payment">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <input type="hidden" name="total_amount" value="<?= $order['total_amount'] ?>">
                        <div class="flex flex-col md:flex-row gap-4 items-start md:items-end">
                            <div class="w-full md:w-1/2"><label for="amount-given-<?= $order['id'] ?>" class="block text-sm font-medium text-gray-700 mb-1">Uang Diberikan (Rp)</label><input type="text" id="amount-given-<?= $order['id'] ?>" name="amount_given" class="w-full p-2 border border-gray-300 rounded-md shadow-sm" required placeholder="Contoh: 50000"></div>
                            <div class="w-full md:w-1/2">
                                <p class="text-sm font-medium text-gray-700">Kembalian: <span id="change-amount-<?= $order['id'] ?>" class="font-bold text-green-600">Rp 0</span></p>
                            </div>
                            <div class="w-full md:w-auto"><button type="submit" class="w-full bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 font-semibold"><i class="fas fa-check-circle mr-2"></i>Proses</button></div>
                        </div>
                    </form>
                </div>
            <?php
                $pending_html .= ob_get_clean();
            }
        } else {
            $pending_html = '<p class="text-gray-500">Tidak ada pesanan yang menunggu pembayaran tunai.</p>';
        }

        // 2. Non-Cash Orders
        $non_cash_html = '';
        $sql_non_cash = "$base_select WHERE o.status = 'pending_payment' AND o.payment_method IN ('QRIS', 'transfer', 'virtual_account') ORDER BY o.created_at DESC";
        $result_non_cash = $conn->query($sql_non_cash);
        if ($result_non_cash && $result_non_cash->num_rows > 0) {
            while ($order = $result_non_cash->fetch_assoc()) {
                ob_start();
            ?>
                <div class="bg-purple-50 p-4 rounded-lg shadow-sm border border-purple-200">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-baseline gap-2 mb-2">
                        <h3 class="font-bold text-lg text-gray-800">Order #<?= $order['id'] ?></h3>
                        <span class="text-sm text-gray-500"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></span>
                    </div>
                    <p class="text-sm text-gray-600 mb-2 flex items-center">
                        Pemesan:
                        <span class="font-semibold ml-1 <?= $order['is_member'] ? 'text-green-700' : '' ?>"><?= htmlspecialchars($order['final_customer_name']) ?></span>
                        <?php if ($order['is_member']) : ?>
                            <span class="ml-2 text-xs font-semibold bg-green-100 text-green-800 px-2 py-0.5 rounded-full">MEMBER</span>
                        <?php endif; ?>
                    </p>

                    <p class="text-sm text-gray-600 mb-2">Tipe:
                        <span class="font-semibold">
                            <?= ($order['order_type'] === 'take-away') ? 'Take Away' : 'Dine-In (Meja ' . htmlspecialchars($order['table_number']) . ')' ?>
                        </span>
                    </p>

                    <p class="text-sm text-gray-500 mb-2">Total: <span class="font-bold text-gray-700">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span></p>
                    <p class="text-sm text-gray-500 mb-4">Pembayaran: <span class="font-bold text-gray-700 capitalize"><?= str_replace('_', ' ', $order['payment_method']) ?></span></p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button data-orderid="<?= $order['id'] ?>" class="view-details-btn bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors text-sm"><i class="fas fa-eye mr-2"></i>Lihat Detail</button>
                        <form method="POST" action="index.php" class="inline-block">
                            <input type="hidden" name="action" value="confirm_non_cash_payment">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <button type="submit" class="bg-purple-500 text-white px-4 py-2 rounded-lg hover:bg-purple-600 transition-colors text-sm font-semibold"><i class="fas fa-check mr-2"></i>Konfirmasi Pembayaran</button>
                        </form>
                    </div>
                    <div id="details-for-<?= $order['id'] ?>" class="details-container mt-4 pt-4 border-t border-purple-200"></div>
                </div>
<?php
                $non_cash_html .= ob_get_clean();
            }
        } else {
            $non_cash_html = '<p class="text-gray-500">Tidak ada pesanan non-tunai yang menunggu konfirmasi.</p>';
        }

        // 3. Paid Orders (Siap Cetak)
        $paid_html = '';
        $sql_paid = "$base_select WHERE o.status = 'completed' AND o.receipt_printed_at IS NULL ORDER BY o.updated_at DESC";
        $result_paid = $conn->query($sql_paid);
        if ($result_paid && $result_paid->num_rows > 0) {
            while ($order = $result_paid->fetch_assoc()) {
                $paid_html .= generate_order_card($order, $cashier_name, $conn, false, $cafe_settings);
            }
        } else {
            $paid_html = '<p class="text-gray-500 no-paid-placeholder">Tidak ada pesanan yang perlu dicetak struknya.</p>';
        }

        // 4. Archived Orders
        $archived_html = '';
        $sql_archived = "$base_select WHERE o.status = 'completed' AND o.receipt_printed_at IS NOT NULL ORDER BY o.receipt_printed_at DESC LIMIT 20";
        $result_archived = $conn->query($sql_archived);
        if ($result_archived && $result_archived->num_rows > 0) {
            while ($order = $result_archived->fetch_assoc()) {
                $archived_html .= generate_order_card($order, $cashier_name, $conn, true, $cafe_settings);
            }
        } else {
            $archived_html = '<p class="text-gray-500 no-archive-placeholder">Belum ada struk yang diarsipkan.</p>';
        }

        $response = [
            'success' => true,
            'data' => [
                'pending_html' => $pending_html,
                'non_cash_html' => $non_cash_html,
                'paid_html' => $paid_html,
                'archived_html' => $archived_html
            ]
        ];
    }

    echo json_encode($response);
    exit();
}
// =========================================================================
//  LOGIC BAGIAN B: Menangani Aksi POST
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Endpoint untuk memindahkan pesanan ke arsip setelah cetak struk ---
    if (isset($_POST['action']) && $_POST['action'] === 'archive_receipt') {
        header('Content-Type: application/json');
        $order_id = (int)$_POST['order_id'];
        $sql = "UPDATE orders SET receipt_printed_at = NOW() WHERE id = ? AND receipt_printed_at IS NULL";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $order_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Order archived successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to archive order.']);
        }
        exit();
    }

    // --- Endpoint untuk konfirmasi pembayaran non-tunai ---
    if (isset($_POST['action']) && $_POST['action'] === 'confirm_non_cash_payment') {
        $order_id = (int)$_POST['order_id'];
        $sql = "UPDATE orders SET status = 'completed', cashier_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $cashier_id, $order_id);
        if ($stmt->execute()) {
            header("Location: index.php?message=Pembayaran+non-tunai+berhasil+dikonfirmasi.");
        } else {
            header("Location: index.php?error=Gagal+mengonfirmasi+pembayaran.");
        }
        exit();
    }

    // --- Proses Pembayaran Tunai ---
    if (isset($_POST['action']) && $_POST['action'] === 'process_cash_payment') {
        $order_id = (int)$_POST['order_id'];
        $amount_given = (float)str_replace(['.', ','], '', $_POST['amount_given']);
        $total_amount = (float)$_POST['total_amount'];
        $change_amount = $amount_given - $total_amount;

        if ($amount_given < $total_amount) {
            header("Location: index.php?error=Jumlah+uang+yang+diberikan+kurang.");
            exit();
        }

        $conn->begin_transaction();
        try {
            $sql_update_order = "UPDATE orders SET status = 'completed', cashier_id = ? WHERE id = ?";
            $stmt_update_order = $conn->prepare($sql_update_order);
            $stmt_update_order->bind_param("ii", $cashier_id, $order_id);
            $stmt_update_order->execute();

            $sql_insert_trans = "INSERT INTO transactions (order_id, total_paid, amount_given, change_amount, payment_method) VALUES (?, ?, ?, ?, ?)";
            $stmt_insert_trans = $conn->prepare($sql_insert_trans);
            $payment_method = 'manual_cash';
            $stmt_insert_trans->bind_param("iddds", $order_id, $total_amount, $amount_given, $change_amount, $payment_method);
            $stmt_insert_trans->execute();

            $conn->commit();
            header("Location: index.php?message=Pembayaran+tunai+berhasil+diproses.");
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            header("Location: index.php?error=Gagal+memproses+pembayaran.");
        }
        exit();
    }
}


// =========================================================================
//  LOGIC BAGIAN C: Mengambil data untuk Tampilan Halaman
// =========================================================================
// [PERBAIKAN] Query untuk mengambil nama final dan status member.
// - Mengganti JOIN ke tabel members dari `o.user_id` menjadi `o.member_id`.
// - Mengganti alias tabel members dari `u` menjadi `m` agar lebih jelas.
// - Kondisi `is_member` sekarang didasarkan pada `o.member_id IS NOT NULL`, yang merupakan cara yang benar.
$base_select = "SELECT o.*, t.table_number, 
                       COALESCE(m.name, o.customer_name, 'Guest') AS final_customer_name,
                       (o.member_id IS NOT NULL) AS is_member,
                       c.name AS cashier_processor_name
                FROM orders o 
                LEFT JOIN tables t ON o.table_id = t.id 
                LEFT JOIN members m ON o.member_id = m.id
                LEFT JOIN users c ON o.cashier_id = c.id";

// 1. Pesanan menunggu pembayaran tunai
$pending_orders = [];
$sql_pending = "$base_select WHERE o.status = 'pending_payment' AND (o.payment_method = 'cash' OR o.payment_method = 'manual_cash') ORDER BY o.created_at DESC";
$result_pending = $conn->query($sql_pending);
if ($result_pending) while ($row = $result_pending->fetch_assoc()) $pending_orders[] = $row;

// 2. Pesanan Non-Tunai Menunggu Konfirmasi
$non_cash_orders = [];
$sql_non_cash = "$base_select WHERE o.status = 'pending_payment' AND o.payment_method IN ('QRIS', 'transfer', 'virtual_account') ORDER BY o.created_at DESC";
$result_non_cash = $conn->query($sql_non_cash);
if ($result_non_cash) while ($row = $result_non_cash->fetch_assoc()) $non_cash_orders[] = $row;

// 3. Pesanan sudah dibayar, TAPI struk belum dicetak
$paid_orders = [];
$sql_paid = "$base_select WHERE o.status = 'completed' AND o.receipt_printed_at IS NULL ORDER BY o.created_at DESC";
$result_paid = $conn->query($sql_paid);
if ($result_paid) while ($row = $result_paid->fetch_assoc()) $paid_orders[] = $row;

// 4. Arsip Struk (dibatasi 20 terbaru untuk performa)
$archived_orders = [];
$sql_archived = "$base_select WHERE o.status = 'completed' AND o.receipt_printed_at IS NOT NULL ORDER BY o.receipt_printed_at DESC LIMIT 20";
$result_archived = $conn->query($sql_archived);
if ($result_archived) while ($row = $result_archived->fetch_assoc()) $archived_orders[] = $row;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halaman Kasir</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }

        .details-container {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease-out;
        }

        .details-container.open {
            max-height: 1000px;
            transition: max-height 0.5s ease-in;
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .print-area,
            .print-area * {
                visibility: visible;
            }

            .print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body class="bg-gray-100">
    <!-- Sidebar -->
    <aside id="sidebar" class="bg-gray-800 text-white w-64 p-4 space-y-4 fixed top-0 left-0 h-screen z-40 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col no-print">
        <div>
            <h2 class="text-2xl font-bold mb-2">Kasir</h2>
            <div class="p-2.5 bg-gray-700 rounded-lg mb-6">
                <p class="text-sm text-gray-300">Selamat Datang,</p>
                <p class="font-semibold text-lg"><?= htmlspecialchars($cashier_name) ?></p>
            </div>
            <nav>
                <a href="index.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700 bg-gray-700">Daftar Pesanan</a>
                <a href="manual_order.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">Manual Order</a>
            </nav>
        </div>
        <div class="mt-auto">
            <a href="logout.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-red-500 bg-red-600 text-center font-semibold"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
        </div>
    </aside>

    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden no-print"></div>

    <main class="md:ml-64 p-4 sm:p-6 md:p-8 no-print">
        <header class="mb-8 flex justify-between items-center">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-800">Dashboard Kasir</h1>
            <button id="menu-button" class="md:hidden p-2 rounded-md text-gray-700 hover:bg-gray-200"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                </svg></button>
        </header>

        <!-- Notifikasi -->
        <?php if (isset($_GET['message'])) : ?><div id="success-message" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
                <p><?= htmlspecialchars($_GET['message']) ?></p>
            </div><?php endif; ?>
        <?php if (isset($_GET['error'])) : ?><div id="error-message" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                <p><?= htmlspecialchars($_GET['error']) ?></p>
            </div><?php endif; ?>

        <!-- 1. Menunggu Pembayaran Tunai -->
        <section class="bg-white rounded-xl shadow-lg p-4 sm:p-6 mb-8">
            <h2 class="text-2xl font-bold text-red-600 mb-4">Menunggu Pembayaran Tunai</h2>
            <div id="pending-payment-list" class="space-y-4">
                <?php if (empty($pending_orders)) : ?>
                    <p class="text-gray-500">Tidak ada pesanan yang menunggu pembayaran tunai.</p>
                    <?php else : foreach ($pending_orders as $order) : ?>
                        <div class="bg-yellow-50 p-4 rounded-lg shadow-sm border border-yellow-200">
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-baseline gap-2 mb-3">
                                <h3 class="font-bold text-lg text-gray-800">Order #<?= $order['id'] ?></h3>
                                <span class="text-sm text-gray-500"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></span>
                            </div>
                            <p class="text-sm text-gray-600 mb-2 flex items-center">
                                Pemesan:
                                <span class="font-semibold ml-1 <?= $order['is_member'] ? 'text-green-700' : '' ?>"><?= htmlspecialchars($order['final_customer_name']) ?></span>
                                <?php if ($order['is_member']) : ?>
                                    <span class="ml-2 text-xs font-semibold bg-green-100 text-green-800 px-2 py-0.5 rounded-full">MEMBER</span>
                                <?php endif; ?>
                            </p>
                            <p class="text-sm text-gray-600 mb-2">Tipe: <span class="font-semibold"><?= ($order['order_type'] === 'take-away') ? 'Take Away' : 'Dine-In (Meja ' . htmlspecialchars($order['table_number']) . ')' ?></span></p>
                            <p class="text-sm text-gray-600 mb-4">Total: <span class="font-bold text-lg text-gray-800">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span></p>
                            <form method="POST" action="index.php">
                                <input type="hidden" name="action" value="process_cash_payment">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <input type="hidden" name="total_amount" value="<?= $order['total_amount'] ?>">
                                <div class="flex flex-col md:flex-row gap-4 items-start md:items-end">
                                    <div class="w-full md:w-1/2"><label for="amount-given-<?= $order['id'] ?>" class="block text-sm font-medium text-gray-700 mb-1">Uang Diberikan (Rp)</label><input type="text" id="amount-given-<?= $order['id'] ?>" name="amount_given" class="w-full p-2 border border-gray-300 rounded-md shadow-sm" required placeholder="Contoh: 50000"></div>
                                    <div class="w-full md:w-1/2">
                                        <p class="text-sm font-medium text-gray-700">Kembalian: <span id="change-amount-<?= $order['id'] ?>" class="font-bold text-green-600">Rp 0</span></p>
                                    </div>
                                    <div class="w-full md:w-auto"><button type="submit" class="w-full bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 font-semibold"><i class="fas fa-check-circle mr-2"></i>Proses</button></div>
                                </div>
                            </form>
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
        </section>

        <!-- 2. Pesanan Non-Tunai (Menunggu Konfirmasi) -->
        <section class="bg-white rounded-xl shadow-lg p-4 sm:p-6 mb-8">
            <h2 class="text-2xl font-bold text-purple-600 mb-4">Pesanan Non-Tunai (Menunggu Konfirmasi)</h2>
            <div id="non-cash-list" class="space-y-4">
                <?php if (empty($non_cash_orders)) : ?>
                    <p class="text-gray-500">Tidak ada pesanan non-tunai yang menunggu konfirmasi.</p>
                    <?php else : foreach ($non_cash_orders as $order) : ?>
                        <div class="bg-purple-50 p-4 rounded-lg shadow-sm border border-purple-200">
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-baseline gap-2 mb-2">
                                <h3 class="font-bold text-lg text-gray-800">Order #<?= $order['id'] ?></h3>
                                <span class="text-sm text-gray-500"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></span>
                            </div>
                            <p class="text-sm text-gray-600 mb-2 flex items-center">
                                Pemesan:
                                <span class="font-semibold ml-1 <?= $order['is_member'] ? 'text-green-700' : '' ?>"><?= htmlspecialchars($order['final_customer_name']) ?></span>
                                <?php if ($order['is_member']) : ?>
                                    <span class="ml-2 text-xs font-semibold bg-green-100 text-green-800 px-2 py-0.5 rounded-full">MEMBER</span>
                                <?php endif; ?>
                            </p>
                            <p class="text-sm text-gray-600 mb-2">Tipe: <span class="font-semibold"><?= ($order['order_type'] === 'take-away') ? 'Take Away' : 'Dine-In (Meja ' . htmlspecialchars($order['table_number']) . ')' ?></span></p>
                            <p class="text-sm text-gray-500 mb-2">Total: <span class="font-bold text-gray-700">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span></p>
                            <p class="text-sm text-gray-500 mb-4">Pembayaran: <span class="font-bold text-gray-700 capitalize"><?= str_replace('_', ' ', $order['payment_method']) ?></span></p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button data-orderid="<?= $order['id'] ?>" class="view-details-btn bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors text-sm"><i class="fas fa-eye mr-2"></i>Lihat Detail</button>
                                <form method="POST" action="index.php" class="inline-block">
                                    <input type="hidden" name="action" value="confirm_non_cash_payment">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <button type="submit" class="bg-purple-500 text-white px-4 py-2 rounded-lg hover:bg-purple-600 transition-colors text-sm font-semibold"><i class="fas fa-check mr-2"></i>Konfirmasi Pembayaran</button>
                                </form>
                            </div>
                            <div id="details-for-<?= $order['id'] ?>" class="details-container mt-4 pt-4 border-t border-purple-200"></div>
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
        </section>

        <!-- 3. Pesanan Sudah Dibayar -->
        <section class="bg-white rounded-xl shadow-lg p-4 sm:p-6 mb-8">
            <h2 class="text-2xl font-bold text-blue-600 mb-4">Pesanan Siap Cetak Struk</h2>
            <div id="paid-orders-list" class="space-y-4">
                <?php if (empty($paid_orders)) : ?>
                    <p class="text-gray-500 no-paid-placeholder">Tidak ada pesanan yang perlu dicetak struknya.</p>
                <?php else : foreach ($paid_orders as $order) : echo generate_order_card($order, $cashier_name, $conn, false, $cafe_settings);
                    endforeach;
                endif; ?>
            </div>
        </section>

        <!-- 4. Arsip Struk -->
        <section class="bg-white rounded-xl shadow-lg p-4 sm:p-6">
            <h2 class="text-2xl font-bold text-gray-700 mb-4">Arsip Struk</h2>
            <div id="archived-receipts-list" class="space-y-4">
                <?php if (empty($archived_orders)) : ?>
                    <p class="text-gray-500 no-archive-placeholder">Belum ada struk yang diarsipkan.</p>
                <?php else : foreach ($archived_orders as $order) : echo generate_order_card($order, $cashier_name, $conn, true, $cafe_settings);
                    endforeach;
                endif; ?>
            </div>
        </section>
    </main>
</body>

</html>
<?php
// Fungsi untuk generate kartu pesanan agar tidak duplikat kode
function generate_order_card($order, $cashier_name, $conn, $is_archived, $cafe_settings)
{
    $transaction_details = null;
    if ($order['payment_method'] === 'manual_cash' || $order['payment_method'] === 'cash') {
        $trans_sql = "SELECT amount_given, change_amount FROM transactions WHERE order_id = ? LIMIT 1";
        if ($trans_stmt = $conn->prepare($trans_sql)) {
            $trans_stmt->bind_param("i", $order['id']);
            $trans_stmt->execute();
            $trans_result = $trans_stmt->get_result();
            if ($trans_result->num_rows > 0) {
                $transaction_details = $trans_result->fetch_assoc();
            }
        }
    }
    $display_cashier_name = $order['cashier_processor_name'] ?? $cashier_name;
    ob_start();
?>
    <div id="order-card-<?= $order['id'] ?>" class="bg-gray-50 p-4 rounded-lg shadow-sm border border-gray-200">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-baseline gap-2 mb-2">
            <h3 class="font-bold text-lg text-gray-800">Order #<?= $order['id'] ?></h3>
            <span class="text-sm text-gray-500"><?= $is_archived ? 'Dicetak pada: ' . date('d M Y, H:i', strtotime($order['receipt_printed_at'])) : 'Dibayar pada: ' . date('d M Y, H:i', strtotime($order['updated_at'])) ?></span>
        </div>
        <p class="text-sm text-gray-600 mb-2 flex items-center">
            Pemesan:
            <span class="font-semibold ml-1 <?= $order['is_member'] ? 'text-green-700' : '' ?>"><?= htmlspecialchars($order['final_customer_name']) ?></span>
            <?php if ($order['is_member']) : ?>
                <span class="ml-2 text-xs font-semibold bg-green-100 text-green-800 px-2 py-0.5 rounded-full">MEMBER</span>
            <?php endif; ?>
        </p>
        <p class="text-sm text-gray-600 mb-2">Tipe: <span class="font-semibold"><?= ($order['order_type'] === 'take-away') ? 'Take Away' : 'Dine-In (Meja ' . htmlspecialchars($order['table_number']) . ')' ?></span></p>
        <p class="text-sm text-gray-500 mb-2">Total: <span class="font-bold text-gray-700">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span></p>
        <p class="text-sm text-gray-500 mb-4">Pembayaran: <span class="font-bold text-gray-700 capitalize"><?= str_replace('_', ' ', $order['payment_method']) ?></span></p>

        <div class="print-area hidden" id="receipt-<?= $order['id'] ?>">
            <div style="font-family: 'Courier New', Courier, monospace; width: 300px; font-size: 12px; color: black;">
                <div style="text-align: center;">
                    <h2 style="font-size: 16px; margin: 0; font-weight: bold;"><?= htmlspecialchars($cafe_settings['name']) ?></h2>
                    <p style="margin: 0; font-size: 10px;"><?= htmlspecialchars($cafe_settings['address']) ?></p>
                    <p style="margin: 0; font-size: 10px;">Telp: <?= htmlspecialchars($cafe_settings['phone']) ?></p>
                </div>
                <div style="margin: 8px 0; border-top: 1px solid black;"></div>
                <table style="width: 100%; font-size: 10px;">
                    <tr>
                        <td>No. Struk</td>
                        <td style="text-align: right;">#<?= $order['id'] ?></td>
                    </tr>
                    <tr>
                        <td>Kasir</td>
                        <td style="text-align: right;"><?= htmlspecialchars($display_cashier_name) ?></td>
                    </tr>
                    <tr>
                        <td>Tanggal</td>
                        <td style="text-align: right;"><?= date('d/m/y H:i', strtotime($order['updated_at'])) ?></td>
                    </tr>
                    <?php if ($order['is_member']) : ?>
                        <tr>
                            <td>Member</td>
                            <td style="text-align: right;"><?= htmlspecialchars($order['final_customer_name']) ?></td>
                        </tr>
                    <?php else : ?>
                        <tr>
                            <td>Pemesan</td>
                            <td style="text-align: right;"><?= htmlspecialchars($order['final_customer_name']) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td>Tipe</td>
                        <td style="text-align: right;"><?= ($order['order_type'] === 'take-away') ? 'Take Away' : 'Dine-In (Meja ' . htmlspecialchars($order['table_number']) . ')' ?></td>
                    </tr>
                </table>
                <div style="margin: 8px 0; border-top: 1px solid black;"></div>
                <table style="width: 100%; font-size: 11px;">
                    <?php
                    $items_sql_print = "SELECT oi.quantity, m.name, oi.price_per_item AS price, oi.total_price, oi.selected_addons FROM order_items oi JOIN menu m ON oi.menu_id = m.id WHERE oi.order_id = ?";
                    $items_stmt_print = $conn->prepare($items_sql_print);
                    $items_stmt_print->bind_param("i", $order['id']);
                    $items_stmt_print->execute();
                    $items_result_print = $items_stmt_print->get_result();
                    while ($item = $items_result_print->fetch_assoc()) :
                        $base_price = (float)$item['price'];
                        $qty = (int)$item['quantity'];
                        $base_item_total = $base_price * $qty;
                    ?>
                        <tr>
                            <td colspan="4" style="padding-top: 5px; font-weight: bold;"><?= htmlspecialchars($item['name']) ?></td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding-left: 10px;"><?= $qty ?> x <?= number_format($base_price, 0, ',', '.') ?></td>
                            <td colspan="2" style="text-align: right;"><?= number_format($base_item_total, 0, ',', '.') ?></td>
                        </tr>
                        <?php
                        $addons = json_decode($item['selected_addons'], true);
                        if (is_array($addons) && !empty($addons)) {
                            foreach ($addons as $addon) {
                                $addon_price = (float)($addon['price'] ?? 0);
                                $addon_total = $addon_price * $qty;
                        ?>
                                <tr>
                                    <td colspan="3" style="padding-left: 15px; font-size: 10px;">+ <?= htmlspecialchars($addon['option_name'] ?? 'Addon') ?></td>
                                    <td style="text-align: right; font-size: 10px;"><?= $addon_price > 0 ? number_format($addon_total, 0, ',', '.') : '' ?></td>
                                </tr>
                        <?php
                            }
                        }
                        ?>
                        <!-- Total Item -->
                        <tr>
                            <td colspan="4" style="padding: 2px 0;">
                                <div style="border-top: 1px dashed #000;"></div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding-left: 10px; font-size: 10px; font-weight: bold;">Total Item</td>
                            <td colspan="2" style="text-align: right; font-size: 10px; font-weight: bold;"><?= number_format($item['total_price'], 0, ',', '.') ?></td>
                        </tr>

                    <?php endwhile; ?>
                </table>
                <div style="margin: 8px 0; border-top: 1px solid black;"></div>
                <table style="width: 100%; font-size: 11px;">
                    <tr>
                        <td>Subtotal</td>
                        <td style="text-align: right;">Rp <?= number_format($order['subtotal'], 0, ',', '.') ?></td>
                    </tr>
                    <?php if ($order['discount_amount'] > 0): ?><tr>
                            <td>Diskon</td>
                            <td style="text-align: right;">- Rp <?= number_format($order['discount_amount'], 0, ',', '.') ?></td>
                        </tr><?php endif; ?>
                    <tr>
                        <td>PPN (11%)</td>
                        <td style="text-align: right;">Rp <?= number_format($order['tax'], 0, ',', '.') ?></td>
                    </tr>
                </table>
                <div style="margin: 8px 0; border-top: 1px solid black;"></div>
                <table style="width: 100%; font-size: 11px;">
                    <tr style="font-weight: bold; font-size: 12px;">
                        <td>Total Bayar</td>
                        <td style="text-align: right;">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></td>
                    </tr>
                    <?php if ($transaction_details) : ?>
                        <tr>
                            <td>TUNAI</td>
                            <td style="text-align: right;">Rp <?= number_format($transaction_details['amount_given'], 0, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td>KEMBALI</td>
                            <td style="text-align: right;">Rp <?= number_format($transaction_details['change_amount'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
                <div style="margin: 8px 0; border-top: 1px solid black;"></div>
                <p style="text-align: center; font-size: 10px; margin: 10px 0;">Terima kasih atas kunjungan Anda!</p>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <button data-orderid="<?= $order['id'] ?>" class="view-details-btn bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors text-sm"><i class="fas fa-eye mr-2"></i>Lihat Detail</button>
            <button onclick="printAndArchiveAction(<?= $order['id'] ?>)" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors text-sm"><i class="fas fa-print mr-2"></i><?= $is_archived ? 'Cetak Ulang Struk' : 'Cetak & Arsipkan Struk' ?></button>
        </div>
        <div id="details-for-<?= $order['id'] ?>" class="details-container mt-4 pt-4 border-t border-gray-200"></div>
    </div>
<?php
    return ob_get_clean();
}
?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- SCRIPT SIDEBAR ---
        const sidebar = document.getElementById('sidebar');
        const menuButton = document.getElementById('menu-button');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const openSidebar = () => {
            sidebar.classList.remove('-translate-x-full');
            sidebarOverlay.classList.remove('hidden');
        };
        const closeSidebar = () => {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        };
        menuButton.addEventListener('click', openSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);

        // --- SCRIPT HILANGKAN NOTIFIKASI ---
        document.querySelectorAll('#success-message, #error-message').forEach(msg => {
            setTimeout(() => {
                msg.style.transition = 'opacity 0.5s ease';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            }, 4000);
        });

        function initializeChangeCalculation() {
            document.querySelectorAll('input[id^="amount-given-"]').forEach(input => {
                if (input.hasAttribute('data-listener-attached')) return;
                const form = input.closest('form');
                if (!form) return;
                const totalAmount = parseFloat(form.querySelector('input[name="total_amount"]').value);
                const orderId = form.querySelector('input[name="order_id"]').value;
                const changeAmountEl = document.getElementById(`change-amount-${orderId}`);
                if (!changeAmountEl) return;
                const inputHandler = (e) => {
                    let value = e.target.value.replace(/\D/g, '');
                    const amountGiven = parseFloat(value) || 0;
                    const change = amountGiven - totalAmount;
                    changeAmountEl.textContent = `Rp ${change >= 0 ? formatRupiah(change, false) : '0'}`;
                    changeAmountEl.classList.toggle('text-red-600', change < 0);
                    changeAmountEl.classList.toggle('text-green-600', change >= 0);
                    e.target.value = value ? formatRupiah(value, false) : '';
                };
                input.addEventListener('input', inputHandler);
                input.setAttribute('data-listener-attached', 'true');
            });
        }

        initializeChangeCalculation();

        document.body.addEventListener('click', async (e) => {
            const detailsButton = e.target.closest('.view-details-btn');
            if (detailsButton) {
                const orderId = detailsButton.dataset.orderid;
                const detailsContainer = document.getElementById(`details-for-${orderId}`);
                if (!detailsContainer) return;
                const isLoaded = detailsContainer.dataset.loaded === 'true';
                const isOpen = detailsContainer.classList.contains('open');
                if (isLoaded && isOpen) {
                    detailsContainer.classList.remove('open');
                } else if (isLoaded && !isOpen) {
                    detailsContainer.classList.add('open');
                } else {
                    detailsContainer.innerHTML = '<p class="text-center text-gray-500 p-4">Memuat detail...</p>';
                    detailsContainer.classList.add('open');
                    try {
                        const response = await fetch(`index.php?ajax=1&action=get_order_details&order_id=${orderId}`);
                        const result = await response.json();
                        if (result.success) {
                            populateInlineDetails(detailsContainer, result.data);
                            detailsContainer.dataset.loaded = 'true';
                        } else {
                            detailsContainer.innerHTML = '<p class="text-red-500 text-center p-4">Gagal memuat detail.</p>';
                        }
                    } catch (error) {
                        console.error("Fetch error:", error);
                        detailsContainer.innerHTML = '<p class="text-red-500 text-center p-4">Terjadi kesalahan jaringan.</p>';
                    }
                }
            }
        });

        function populateInlineDetails(container, data) {
            const {
                items,
                subtotal,
                discount,
                tax,
                total_amount
            } = data;

            let itemsHtml = items.map(item => {
                let baseItemTotal = item.quantity * item.price;
                let addonsHtml = '';
                try {
                    const addons = JSON.parse(item.selected_addons);
                    if (Array.isArray(addons) && addons.length > 0) {
                        addonsHtml = addons.map(addon => {
                            const addonPrice = parseFloat(addon.price || 0);
                            const addonTotal = item.quantity * addonPrice;
                            return `
                            <div class="flex justify-between pl-4 text-gray-600 text-xs">
                                <span>+ ${addon.option_name}</span>
                                <span>${formatRupiah(addonTotal)}</span>
                            </div>`;
                        }).join('');
                    }
                } catch (e) {}

                return `
                <div class="py-2 border-b last:border-b-0">
                    <div class="space-y-1">
                        <div class="flex justify-between">
                            <span class="font-semibold">${item.quantity}x ${item.name}</span>
                            <span>${formatRupiah(baseItemTotal)}</span>
                        </div>
                        ${addonsHtml}
                    </div>
                    <div class="flex justify-between items-center font-bold text-gray-800 border-t border-dashed mt-2 pt-1">
                        <span>Total Item</span>
                        <span>${formatRupiah(item.total_price)}</span>
                    </div>
                </div>
                `;
            }).join('');

            container.innerHTML = `
                <h3 class="font-bold mb-2 text-gray-700 text-base">Rincian Item</h3>
                <div class="space-y-1 mb-4">${itemsHtml}</div>
                <hr class="my-3 border-gray-200">
                <div class="space-y-1 text-right text-gray-800 text-sm">
                    <div class="flex justify-between"><span>Subtotal</span><span>${formatRupiah(subtotal)}</span></div>
                    <div class="flex justify-between"><span>Diskon</span><span>- ${formatRupiah(discount)}</span></div>
                    <div class="flex justify-between"><span>PPN (11%)</span><span>${formatRupiah(tax)}</span></div>
                    <div class="flex justify-between font-bold text-base border-t pt-2 mt-2">
                        <span>Total Bayar</span>
                        <span>${formatRupiah(total_amount)}</span>
                    </div>
                </div>
            `;
        }


        // --- SCRIPT REAL-TIME UPDATE ---
        async function fetchLatestOrders() {
            try {
                const response = await fetch('index.php?ajax=1&action=get_latest_orders');
                const result = await response.json();
                if (result.success) {
                    document.getElementById('pending-payment-list').innerHTML = result.data.pending_html;
                    document.getElementById('non-cash-list').innerHTML = result.data.non_cash_html;
                    document.getElementById('paid-orders-list').innerHTML = result.data.paid_html;
                    document.getElementById('archived-receipts-list').innerHTML = result.data.archived_html;
                    initializeChangeCalculation();
                }
            } catch (error) {
                console.error("Gagal mengambil data terbaru:", error);
            }
        }
        setInterval(fetchLatestOrders, 7000); // Polling setiap 7 detik
    });

    // --- FUNGSI CETAK & ARSIP ---
    async function printAndArchiveAction(orderId) {
        printReceipt(orderId);
        const card = document.getElementById(`order-card-${orderId}`);
        // Hanya arsipkan jika kartu ada di daftar "Siap Cetak"
        if (document.getElementById('paid-orders-list').contains(card)) {
            try {
                const formData = new FormData();
                formData.append('action', 'archive_receipt');
                formData.append('order_id', orderId);
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    // Pindahkan kartu secara visual tanpa refresh halaman
                    const archiveList = document.getElementById('archived-receipts-list');
                    const paidList = document.getElementById('paid-orders-list');
                    const button = card.querySelector('button[onclick*="printAndArchiveAction"]');
                    button.innerHTML = '<i class="fas fa-print mr-2"></i>Cetak Ulang Struk';
                    // Hapus placeholder jika ada
                    archiveList.querySelector('.no-archive-placeholder')?.remove();
                    archiveList.prepend(card); // Pindahkan ke atas arsip
                    // Jika daftar siap cetak kosong, tampilkan placeholder
                    if (!paidList.querySelector('[id^="order-card-"]')) {
                        paidList.innerHTML = '<p class="text-gray-500 no-paid-placeholder">Tidak ada pesanan yang perlu dicetak struknya.</p>';
                    }
                } else {
                    alert('Gagal mengarsipkan struk: ' + (result.message || ''));
                }
            } catch (error) {
                console.error('Archive error:', error);
                alert('Gagal mengarsipkan struk karena kesalahan jaringan.');
            }
        }
    }

    // --- FUNGSI CETAK ---
    function printReceipt(orderId) {
        const receiptElement = document.getElementById(`receipt-${orderId}`);
        if (!receiptElement) return;
        const printContents = receiptElement.innerHTML;
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        document.body.appendChild(iframe);
        const iFrameDoc = iframe.contentWindow.document;
        iFrameDoc.open();
        iFrameDoc.write(`<html><head><title>Cetak Struk</title><style>body { font-family: 'Courier New', monospace; margin: 0; -webkit-print-color-adjust: exact !important; color-adjust: exact !important; }</style></head><body>${printContents}</body></html>`);
        iFrameDoc.close();
        iframe.onload = function() {
            setTimeout(() => {
                try {
                    iframe.contentWindow.focus();
                    iframe.contentWindow.print();
                } catch (e) {
                    console.error("Proses cetak gagal:", e);
                } finally {
                    document.body.removeChild(iframe);
                }
            }, 250);
        };
    }

    // FUNGSI FORMAT RUPIAH
    function formatRupiah(angka, useCurrency = true) {
        const options = {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        };
        if (useCurrency) {
            options.style = 'currency';
            options.currency = 'IDR';
        }
        return new Intl.NumberFormat('id-ID', options).format(Number(String(angka).replace(/\D/g, '')) || 0);
    }
</script>