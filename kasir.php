<?php
// File: kasir.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// --- PERUBAHAN 1: Autentikasi ---
// Cek apakah user sudah login dan memiliki peran 'cashier'
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'cashier') {
    // Jika tidak, redirect ke halaman login
    header("Location: login_kasir.php");
    exit();
}

// Ambil nama kasir dari session
$cashier_name = $_SESSION['username'] ?? 'Kasir';
$cashier_id = $_SESSION['id'];

// =========================================================================
//  LOGIC BAGIAN A: Mengambil data dari database
// =========================================================================
$pending_orders = [];
$sql_pending = "SELECT o.*, t.table_number
                  FROM orders o
                  LEFT JOIN tables t ON o.table_id = t.id
                  WHERE o.status = 'pending_payment' AND o.payment_method = 'cash'
                  ORDER BY o.created_at DESC";
$result_pending = $conn->query($sql_pending);
if ($result_pending) {
    while ($row_pending = $result_pending->fetch_assoc()) {
        $pending_orders[] = $row_pending;
    }
}

$orders = [];
$sql = "SELECT o.*, t.table_number, u.name AS user_name
        FROM orders o
        LEFT JOIN tables t ON o.table_id = t.id
        LEFT JOIN users u ON o.user_id = u.id
        WHERE (o.status = 'paid' OR o.status = 'completed')
           OR (o.status = 'pending_payment' AND o.payment_method IN ('qris', 'transfer', 'virtual_account'))
        ORDER BY o.created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

// =========================================================================
//  LOGIC BAGIAN B: Menangani Aksi POST
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'process_cash_payment') {
        $order_id = (int)$_POST['order_id'];
        $amount_given = (float)str_replace('.', '', $_POST['amount_given']); // Hapus pemisah ribuan
        $total_amount = (float)$_POST['total_amount'];
        $change_amount = $amount_given - $total_amount;

        if ($amount_given < $total_amount) {
            header("Location: kasir.php?error=Jumlah+uang+yang+diberikan+kurang.");
            exit();
        }

        // --- PERUBAHAN 2: Menyimpan ID Kasir saat transaksi ---
        // Update status, metode pembayaran, dan user_id (kasir yang memproses)
        $sql_update_order = "UPDATE orders SET status = 'completed', user_id = ? WHERE id = ?";
        $stmt_update_order = $conn->prepare($sql_update_order);
        $stmt_update_order->bind_param("ii", $cashier_id, $order_id);
        $stmt_update_order->execute();

        $sql_insert_trans = "INSERT INTO transactions (order_id, total_paid, amount_given, change_amount, payment_method) VALUES (?, ?, ?, ?, 'cash')";
        $stmt_insert_trans = $conn->prepare($sql_insert_trans);
        $stmt_insert_trans->bind_param("iddd", $order_id, $total_amount, $amount_given, $change_amount);
        $stmt_insert_trans->execute();

        header("Location: kasir.php?message=Pembayaran+tunai+berhasil+diproses.");
        exit();
    }
}
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

        /* Gaya untuk area cetak struk */
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
                padding: 1rem;
                font-size: 12px;
                /* Ukuran font lebih kecil untuk struk */
            }

            .print-area h2,
            .print-area h3 {
                color: black !important;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body class="bg-gray-100">
    <!-- Sidebar untuk Tampilan Mobile & Desktop -->
    <aside id="sidebar" class="bg-gray-800 text-white w-64 p-4 space-y-4 fixed top-0 left-0 h-screen z-40 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col">
        <div>
            <h2 class="text-2xl font-bold mb-2">Kasir</h2>
            <!-- --- PERUBAHAN 3: Tampilkan nama kasir --- -->
            <div class="p-2.5 bg-gray-700 rounded-lg mb-6">
                <p class="text-sm text-gray-300">Selamat Datang,</p>
                <p class="font-semibold text-lg"><?= htmlspecialchars($cashier_name) ?></p>
            </div>
            <nav>
                <a href="kasir.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700 bg-gray-700">Daftar Pesanan</a>
                <a href="manual_order.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">Manual Order</a>
            </nav>
        </div>
        <div class="mt-auto">
            <!-- --- PERUBAHAN 4: Tambah tombol logout --- -->
            <a href="logout.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-red-500 bg-red-600 text-center font-semibold">
                <i class="fas fa-sign-out-alt mr-2"></i>Logout
            </a>
        </div>
    </aside>

    <!-- Overlay untuk menutup sidebar di mobile -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden"></div>

    <main class="md:ml-64 p-4 sm:p-6 md:p-8">
        <header class="mb-8 flex justify-between items-center">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-800">Dashboard Kasir</h1>
            <!-- Tombol Menu untuk Mobile -->
            <button id="menu-button" class="md:hidden p-2 rounded-md text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                </svg>
            </button>
        </header>

        <!-- Pesan Sukses & Error -->
        <?php if (isset($_GET['message'])): ?>
            <div id="success-message" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
                <p><?= htmlspecialchars($_GET['message']) ?></p>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div id="error-message" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                <p><?= htmlspecialchars($_GET['error']) ?></p>
            </div>
        <?php endif; ?>

        <!-- Pesanan Menunggu Pembayaran Tunai -->
        <section class="bg-white rounded-xl shadow-lg p-4 sm:p-6 mb-8">
            <h2 class="text-2xl font-bold text-red-600 mb-4">Menunggu Pembayaran Tunai</h2>
            <div class="space-y-4">
                <?php if (empty($pending_orders)): ?>
                    <p class="text-gray-500">Tidak ada pesanan yang menunggu pembayaran tunai.</p>
                <?php else: ?>
                    <?php foreach ($pending_orders as $order): ?>
                        <div class="bg-yellow-50 p-4 rounded-lg shadow-sm border border-yellow-200">
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-baseline gap-2 mb-3">
                                <h3 class="font-bold text-lg text-gray-800">Order #<?= $order['id'] ?></h3>
                                <span class="text-sm text-gray-500"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></span>
                            </div>
                            <?php if ($order['table_number']): ?><p class="text-sm text-gray-600 mb-2">Meja: <span class="font-semibold"><?= $order['table_number'] ?></span></p><?php endif; ?>
                            <p class="text-sm text-gray-600 mb-4">Total: <span class="font-bold text-lg text-gray-800">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span></p>

                            <form method="POST" action="kasir.php">
                                <input type="hidden" name="action" value="process_cash_payment">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <input type="hidden" name="total_amount" value="<?= $order['total_amount'] ?>">
                                <div class="flex flex-col md:flex-row gap-4 items-start md:items-end">
                                    <div class="w-full md:w-1/2">
                                        <label for="amount-given-<?= $order['id'] ?>" class="block text-sm font-medium text-gray-700 mb-1">Uang Diberikan (Rp)</label>
                                        <input type="text" id="amount-given-<?= $order['id'] ?>" name="amount_given" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required placeholder="Contoh: 50000">
                                    </div>
                                    <div class="w-full md:w-1/2">
                                        <p class="text-sm font-medium text-gray-700">Kembalian: <span id="change-amount-<?= $order['id'] ?>" class="font-bold text-green-600">Rp 0</span></p>
                                    </div>
                                    <div class="w-full md:w-auto">
                                        <button type="submit" class="w-full bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors font-semibold">
                                            <i class="fas fa-check-circle mr-2"></i>Proses
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Riwayat Transaksi -->
        <section class="bg-white rounded-xl shadow-lg p-4 sm:p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-700 mb-4">Riwayat Transaksi</h2>
            <div class="space-y-4">
                <?php if (empty($orders)): ?>
                    <p class="text-gray-500">Belum ada riwayat transaksi.</p>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="bg-gray-50 p-4 rounded-lg shadow-sm border border-gray-200">
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-baseline gap-2 mb-2">
                                <h3 class="font-bold text-lg text-gray-800">Order #<?= $order['id'] ?></h3>
                                <span class="text-sm text-gray-500"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></span>
                            </div>
                            <?php if ($order['table_number']): ?><p class="text-sm text-gray-600 mb-2">Meja: <span class="font-semibold"><?= $order['table_number'] ?></span></p><?php endif; ?>
                            <p class="text-sm text-gray-500 mb-2">Total: <span class="font-bold text-gray-700">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span></p>
                            <p class="text-sm text-gray-500 mb-2">Pembayaran: <span class="font-bold text-gray-700 capitalize"><?= str_replace('_', ' ', $order['payment_method']) ?></span></p>
                            <p class="text-sm text-gray-500 mb-4">Status: <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $order['status'] == 'completed' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?>"><?= ucfirst($order['status']) ?></span></p>

                            <!-- Area Cetak Struk (disembunyikan secara default) -->
                            <div class="print-area hidden" id="receipt-<?= $order['id'] ?>">
                                <h2 class="text-center font-bold text-lg mb-2">Struk Pembayaran</h2>
                                <h3 class="text-center text-sm mb-4">Nama Resto Anda</h3>
                                <div class="text-xs mb-2">
                                    <p>Tanggal: <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
                                    <p>Nomor Pesanan: #<?= $order['id'] ?></p>
                                    <?php if ($order['table_number']): ?><p>Meja: <?= $order['table_number'] ?></p><?php endif; ?>
                                    <!-- --- PERUBAHAN 5: Menampilkan nama kasir di struk --- -->
                                    <p>Kasir: <?= htmlspecialchars($order['user_name'] ?? $cashier_name) ?></p>
                                </div>
                                <div class="border-t border-b border-dashed border-black py-2 my-2">
                                    <?php
                                    $items_sql_print = "SELECT oi.*, m.name FROM order_items oi JOIN menu m ON oi.menu_id = m.id WHERE oi.order_id = ?";
                                    $items_stmt_print = $conn->prepare($items_sql_print);
                                    $items_stmt_print->bind_param("i", $order['id']);
                                    $items_stmt_print->execute();
                                    $items_result_print = $items_stmt_print->get_result();
                                    while ($item = $items_result_print->fetch_assoc()): ?>
                                        <div class="flex justify-between text-xs">
                                            <div>
                                                <p><?= $item['name'] ?></p>
                                                <p class="pl-2"><?= $item['quantity'] ?> x Rp <?= number_format($item['price'], 0, ',', '.') ?></p>
                                            </div>
                                            <span>Rp <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?></span>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                                <div class="text-xs space-y-1">
                                    <div class="flex justify-between font-semibold">
                                        <span>TOTAL</span>
                                        <span>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span>
                                    </div>
                                </div>
                                <div class="text-center mt-4 text-xs">
                                    <p>Terima kasih sudah berkunjung!</p>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <button onclick="printReceipt(<?= $order['id'] ?>)" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors text-sm">
                                    <i class="fas fa-print mr-2"></i>Cetak Ulang Struk
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // --- SCRIPT UNTUK SIDEBAR RESPONSIVE ---
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

            // --- SCRIPT LAMA (dengan sedikit penyesuaian) ---

            // Sembunyikan notifikasi setelah beberapa detik
            const successMessage = document.getElementById('success-message');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.transition = 'opacity 0.5s ease';
                    successMessage.style.opacity = '0';
                    setTimeout(() => successMessage.remove(), 500);
                }, 3000);
            }
            const errorMessage = document.getElementById('error-message');
            if (errorMessage) {
                setTimeout(() => {
                    errorMessage.style.transition = 'opacity 0.5s ease';
                    errorMessage.style.opacity = '0';
                    setTimeout(() => errorMessage.remove(), 500);
                }, 5000);
            }

            // Kalkulasi kembalian dan format input uang (FIXED)
            document.querySelectorAll('input[id^="amount-given-"]').forEach(input => {
                const form = input.closest('form');
                if (!form) return; // Lewati jika input tidak ada di dalam form

                const totalAmountInput = form.querySelector('input[name="total_amount"]');
                const orderIdInput = form.querySelector('input[name="order_id"]');

                if (!totalAmountInput || !orderIdInput) return; // Lewati jika input tersembunyi tidak ditemukan

                const totalAmount = parseFloat(totalAmountInput.value);
                const orderId = orderIdInput.value;
                const changeAmountEl = document.getElementById(`change-amount-${orderId}`);

                if (!changeAmountEl) return; // Lewati jika elemen kembalian tidak ditemukan

                input.addEventListener('input', (e) => {
                    // Hapus karakter non-digit
                    let value = e.target.value.replace(/\D/g, '');

                    // Kalkulasi kembalian
                    const amountGiven = parseFloat(value) || 0;
                    const change = amountGiven - totalAmount;
                    changeAmountEl.textContent = `Rp ${change >= 0 ? formatRupiah(change) : '0'}`;
                    if (change < 0) {
                        changeAmountEl.classList.remove('text-green-600');
                        changeAmountEl.classList.add('text-red-600');
                    } else {
                        changeAmountEl.classList.add('text-green-600');
                        changeAmountEl.classList.remove('text-red-600');
                    }

                    // Format input dengan pemisah ribuan
                    e.target.value = formatRupiah(value);
                });
            });
        });

        // Fungsi format Rupiah
        function formatRupiah(angka) {
            if (!angka || isNaN(angka)) return '';
            var number_string = angka.toString().replace(/[^,\d]/g, ''),
                split = number_string.split(','),
                sisa = split[0].length % 3,
                rupiah = split[0].substr(0, sisa),
                ribuan = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }

            rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            return rupiah;
        }

        // Fungsi untuk mencetak struk (FIXED)
        function printReceipt(orderId) {
            const receiptElement = document.getElementById(`receipt-${orderId}`);
            if (!receiptElement) return;

            const printContents = receiptElement.innerHTML;

            // Buat iframe tersembunyi untuk proses cetak
            const iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            document.body.appendChild(iframe);

            const iFrameDoc = iframe.contentWindow.document;

            // Tulis konten HTML lengkap ke dalam iframe
            iFrameDoc.open();
            iFrameDoc.write(`
            <html>
            <head>
                <title>Cetak Struk</title>
                <script src="https://cdn.tailwindcss.com"><\/script>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                <style>
                    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
                    body { 
                        font-family: 'Inter', sans-serif; 
                        -webkit-print-color-adjust: exact !important;
                        color-adjust: exact !important;
                    }
                </style>
            </head>
            <body>
                ${printContents}
            </body>
            </html>
        `);
            iFrameDoc.close();

            // Tunggu iframe dan semua isinya (termasuk script Tailwind) selesai dimuat
            iframe.onload = function() {
                // Beri sedikit waktu agar Tailwind selesai menerapkan style
                setTimeout(() => {
                    try {
                        iframe.contentWindow.focus(); // Fokus diperlukan oleh beberapa browser
                        iframe.contentWindow.print();
                    } catch (e) {
                        console.error("Proses cetak gagal:", e);
                    } finally {
                        // Selalu hapus iframe setelah selesai, baik berhasil maupun gagal
                        document.body.removeChild(iframe);
                    }
                }, 500);
            };
        }
    </script>
</body>

</html>