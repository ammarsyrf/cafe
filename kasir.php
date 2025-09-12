<?php
// File: kasir.php
// Halaman untuk kasir

// Catatan: Pastikan file db_connect.php sudah ada dan berisi koneksi database.
require_once 'db_connect.php';

// =========================================================================
//  LOGIC BAGIAN A: Mengambil data dari database (Dengan Logika Baru)
// =========================================================================

// MODIFIKASI 1: Mengambil pesanan yang menunggu pembayaran tunai.
// Hanya tampilkan pesanan dengan status 'pending_payment' DAN metode pembayaran 'cash'.
$pending_orders = [];
$sql_pending = "SELECT o.*, t.table_number, u.name AS user_name
                  FROM orders o
                  LEFT JOIN tables t ON o.table_id = t.id
                  LEFT JOIN users u ON o.user_id = u.id
                  WHERE o.status = 'pending_payment' AND o.payment_method = 'cash'
                  ORDER BY o.created_at DESC";
$result_pending = $conn->query($sql_pending);
if ($result_pending) {
    while ($row_pending = $result_pending->fetch_assoc()) {
        $pending_orders[] = $row_pending;
    }
}


// MODIFIKASI 2: Mengambil pesanan yang sudah dibayar.
// Tampilkan pesanan yang statusnya 'paid' atau 'completed',
// ATAU pesanan yang statusnya 'pending_payment' tetapi metode bayarnya BUKAN 'cash' (qris, transfer, dll).
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

// Mengambil pesanan yang sudah diarsipkan (status 'completed_printed') - Tidak ada perubahan di sini
$archived_orders = [];
$sql_archived = "SELECT o.*, t.table_number, u.name AS user_name
                  FROM orders o
                  LEFT JOIN tables t ON o.table_id = t.id
                  LEFT JOIN users u ON o.user_id = u.id
                  WHERE o.status = 'completed_printed'
                  ORDER BY o.created_at DESC";
$result_archived = $conn->query($sql_archived);
if ($result_archived) {
    while ($row_archived = $result_archived->fetch_assoc()) {
        $archived_orders[] = $row_archived;
    }
}


// =========================================================================
//  LOGIC BAGIAN B: Menangani Aksi POST (Tidak ada perubahan signifikan)
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Aksi untuk memproses pembayaran tunai dari pesanan yang tertunda
    if (isset($_POST['action']) && $_POST['action'] === 'process_cash_payment') {
        $order_id = (int)$_POST['order_id'];
        $amount_given = (float)$_POST['amount_given'];
        $total_amount = (float)$_POST['total_amount'];
        $change_amount = $amount_given - $total_amount;

        if ($amount_given < $total_amount) {
            header("Location: kasir.php?error=Jumlah+uang+yang+diberikan+kurang.");
            exit();
        }

        // Memperbarui status pesanan menjadi 'completed' dan metode pembayaran
        $sql_update_order = "UPDATE orders SET status = 'completed', payment_method = 'cash' WHERE id = ?";
        $stmt_update_order = $conn->prepare($sql_update_order);
        $stmt_update_order->bind_param("i", $order_id);
        $stmt_update_order->execute();

        // Memasukkan data ke tabel transaksi
        $sql_insert_trans = "INSERT INTO transactions (order_id, total_paid, amount_given, change_amount, payment_method) VALUES (?, ?, ?, ?, 'cash')";
        $stmt_insert_trans = $conn->prepare($sql_insert_trans);
        $stmt_insert_trans->bind_param("iddd", $order_id, $total_amount, $amount_given, $change_amount);
        $stmt_insert_trans->execute();

        header("Location: kasir.php?message=Pembayaran+tunai+berhasil+diproses.");
        exit();
    }

    // Aksi baru untuk memindahkan pesanan ke arsip setelah dicetak
    if (isset($_POST['action']) && $_POST['action'] === 'print_and_archive') {
        $order_id = (int)$_POST['order_id'];

        // Memperbarui status pesanan menjadi 'completed_printed'
        $sql_update_order = "UPDATE orders SET status = 'completed_printed' WHERE id = ?";
        $stmt_update_order = $conn->prepare($sql_update_order);
        $stmt_update_order->bind_param("i", $order_id);
        $stmt_update_order->execute();

        header("Location: kasir.php?message=Struk+berhasil+dicetak+dan+pesanan+diarsipkan.");
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
            }

            .print-area h2,
            .print-area h3 {
                color: black !important;
            }
        }
    </style>
</head>

<body class="bg-gray-100">
    <!-- Sidebar -->
    <aside id="sidebar" class="bg-gray-800 text-white w-64 p-4 space-y-4 fixed top-0 left-0 h-screen z-30 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
        <h2 class="text-2xl font-bold mb-6">Kasir</h2>
        <nav>
            <a href="kasir.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700 bg-gray-700">Daftar Pesanan</a>
            <a href="manual_order.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">Manual Order</a>
        </nav>
    </aside>

    <!-- Overlay for mobile menu -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 hidden md:hidden"></div>


    <!-- Main Content -->
    <main class="md:ml-64 p-4 md:p-8">
        <header class="mb-8 flex justify-between items-center">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-800">Dashboard Kasir</h1>
            <!-- Hamburger Menu Button -->
            <button id="menu-button" class="md:hidden p-2 rounded-md text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                </svg>
            </button>
        </header>

        <!-- Pesan Sukses -->
        <?php if (isset($_GET['message'])): ?>
            <div id="success-message" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded transition-opacity duration-500" role="alert">
                <p class="font-bold">Sukses!</p>
                <p><?= htmlspecialchars($_GET['message']) ?></p>
            </div>
        <?php endif; ?>
        <!-- Pesan Error -->
        <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded" role="alert">
                <p class="font-bold">Error!</p>
                <p><?= htmlspecialchars($_GET['error']) ?></p>
            </div>
        <?php endif; ?>

        <!-- Pesanan Menunggu Pembayaran Tunai -->
        <section class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-red-600 mb-4">Pesanan Menunggu Pembayaran Tunai</h2>
            <div class="space-y-4">
                <?php if (empty($pending_orders)): ?>
                    <p class="text-gray-500">Tidak ada pesanan yang menunggu pembayaran tunai.</p>
                <?php else: ?>
                    <?php foreach ($pending_orders as $order): ?>
                        <div class="bg-red-50 p-4 rounded-lg shadow">
                            <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-2">
                                <span class="text-lg font-semibold text-gray-800 mb-2 sm:mb-0">
                                    Pesanan #<?= $order['id'] ?>
                                    <?php if ($order['table_number']): ?>
                                        <span class="ml-2 bg-blue-100 text-blue-800 text-sm px-2 py-1 rounded-full">Meja <?= $order['table_number'] ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="text-gray-600 text-sm"><?= $order['created_at'] ?></span>
                            </div>
                            <p class="text-sm text-gray-500 mb-2">Total: <span class="font-bold text-gray-700">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span></p>
                            <p class="text-sm text-gray-500 mb-4">Status: <span class="font-bold text-red-500 capitalize"><?= str_replace('_', ' ', $order['status']) ?></span></p>

                            <!-- Detail Pesanan -->
                            <div class="order-details-container hidden space-y-2 mt-4">
                                <?php
                                $items_sql = "SELECT oi.*, m.name FROM order_items oi JOIN menu m ON oi.menu_id = m.id WHERE oi.order_id = ?";
                                $items_stmt = $conn->prepare($items_sql);
                                $items_stmt->bind_param("i", $order['id']);
                                $items_stmt->execute();
                                $items_result = $items_stmt->get_result();
                                while ($item = $items_result->fetch_assoc()): ?>
                                    <div class="flex justify-between">
                                        <span><?= $item['name'] ?> (<?= $item['quantity'] ?>x)</span>
                                        <span>Rp <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?></span>
                                    </div>
                                <?php endwhile; ?>
                                <div class="border-t pt-2 mt-2">
                                    <div class="flex justify-between font-bold"><span>Subtotal</span><span>Rp <?= number_format($order['subtotal'], 0, ',', '.') ?></span></div>
                                    <div class="flex justify-between font-bold"><span>PPN</span><span>Rp <?= number_format($order['tax'], 0, ',', '.') ?></span></div>
                                    <div class="flex justify-between font-bold"><span>TOTAL</span><span>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span></div>
                                </div>
                            </div>
                            <!-- Tombol Tampilkan Detail -->
                            <button class="toggle-details bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors">Lihat Detail</button>

                            <!-- Formulir Pembayaran Tunai -->
                            <form method="POST" class="space-y-2 mt-4">
                                <input type="hidden" name="action" value="process_cash_payment">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <input type="hidden" name="total_amount" value="<?= $order['total_amount'] ?>">

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Uang Diberikan (Rp)</label>
                                    <input type="number" name="amount_given" id="amount-given-<?= $order['id'] ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-300 focus:ring focus:ring-red-200 focus:ring-opacity-50" min="0" required>
                                </div>
                                <div class="mt-2 text-xl font-bold flex justify-between">
                                    <span>Kembalian:</span>
                                    <span id="change-amount-<?= $order['id'] ?>">Rp 0</span>
                                </div>
                                <button type="submit" class="w-full bg-green-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-green-600 transition-colors">Proses Pembayaran</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Pesanan yang Sudah Dibayar -->
        <section class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-700 mb-4">Pesanan yang Sudah Dibayar</h2>
            <div class="space-y-4">
                <?php if (empty($orders)): ?>
                    <p class="text-gray-500">Tidak ada pesanan yang perlu diproses.</p>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="bg-gray-50 p-4 rounded-lg shadow">
                            <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-2">
                                <span class="text-lg font-semibold text-gray-800 mb-2 sm:mb-0">
                                    Pesanan #<?= $order['id'] ?>
                                    <?php if ($order['table_number']): ?>
                                        <span class="ml-2 bg-blue-100 text-blue-800 text-sm px-2 py-1 rounded-full">Meja <?= $order['table_number'] ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="text-gray-600 text-sm"><?= $order['created_at'] ?></span>
                            </div>
                            <p class="text-sm text-gray-500 mb-2">Total: <span class="font-bold text-gray-700">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span></p>
                            <p class="text-sm text-gray-500 mb-2">Metode Pembayaran: <span class="font-bold text-gray-700 capitalize"><?= str_replace('_', ' ', $order['payment_method']) ?></span></p>

                            <!-- Konten Struk untuk dicetak -->
                            <div class="print-area hidden" id="receipt-<?= $order['id'] ?>">
                                <div class="text-center font-bold text-lg mb-2">Struk Pembayaran</div>
                                <div class="text-sm mb-2">
                                    <p>Tanggal: <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
                                    <p>Nomor Pesanan: <?= $order['id'] ?></p>
                                    <?php if ($order['table_number']): ?>
                                        <p>Meja: <?= $order['table_number'] ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="border-t border-b border-dashed py-2 mb-2">
                                    <?php
                                    $items_sql_print = "SELECT oi.*, m.name FROM order_items oi JOIN menu m ON oi.menu_id = m.id WHERE oi.order_id = ?";
                                    $items_stmt_print = $conn->prepare($items_sql_print);
                                    $items_stmt_print->bind_param("i", $order['id']);
                                    $items_stmt_print->execute();
                                    $items_result_print = $items_stmt_print->get_result();
                                    while ($item = $items_result_print->fetch_assoc()): ?>
                                        <div class="flex justify-between text-sm">
                                            <span><?= $item['name'] ?> (<?= $item['quantity'] ?>x)</span>
                                            <span>Rp <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?></span>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                                <div class="text-sm space-y-1">
                                    <div class="flex justify-between"><span>Subtotal</span><span>Rp <?= number_format($order['subtotal'], 0, ',', '.') ?></span></div>
                                    <div class="flex justify-between"><span>PPN (11%)</span><span>Rp <?= number_format($order['tax'], 0, ',', '.') ?></span></div>
                                    <div class="flex justify-between font-bold"><span>TOTAL</span><span>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span></div>
                                </div>
                                <div class="text-center mt-4 text-xs">
                                    <p>Terima kasih sudah berkunjung!</p>
                                </div>
                            </div>
                            <!-- Akhir Konten Struk -->

                            <div class="order-details-container hidden space-y-2 mt-4">
                                <?php
                                $items_sql_details = "SELECT oi.*, m.name FROM order_items oi JOIN menu m ON oi.menu_id = m.id WHERE oi.order_id = ?";
                                $items_stmt_details = $conn->prepare($items_sql_details);
                                $items_stmt_details->bind_param("i", $order['id']);
                                $items_stmt_details->execute();
                                $items_result_details = $items_stmt_details->get_result();
                                while ($item = $items_result_details->fetch_assoc()): ?>
                                    <div class="flex justify-between">
                                        <span><?= $item['name'] ?> (<?= $item['quantity'] ?>x)</span>
                                        <span>Rp <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?></span>
                                    </div>
                                <?php endwhile; ?>
                                <div class="border-t pt-2 mt-2">
                                    <div class="flex justify-between font-bold"><span>Subtotal</span><span>Rp <?= number_format($order['subtotal'], 0, ',', '.') ?></span></div>
                                    <div class="flex justify-between font-bold"><span>PPN</span><span>Rp <?= number_format($order['tax'], 0, ',', '.') ?></span></div>
                                    <div class="flex justify-between font-bold"><span>TOTAL</span><span>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span></div>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <button class="toggle-details bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors">Lihat Detail</button>
                                <!-- Mengubah tombol cetak struk menjadi form untuk pembaruan status -->
                                <form method="POST" class="inline-block" onsubmit="printReceipt(<?= $order['id'] ?>); return true;">
                                    <input type="hidden" name="action" value="print_and_archive">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">Cetak & Arsipkan Struk</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Arsip Pesanan -->
        <section class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-700 mb-4">Arsip Pesanan</h2>
            <div class="space-y-4">
                <?php if (empty($archived_orders)): ?>
                    <p class="text-gray-500">Tidak ada pesanan di arsip.</p>
                <?php else: ?>
                    <?php foreach ($archived_orders as $order): ?>
                        <div class="bg-gray-50 p-4 rounded-lg shadow">
                            <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-2">
                                <span class="text-lg font-semibold text-gray-800 mb-2 sm:mb-0">
                                    Pesanan #<?= $order['id'] ?>
                                    <?php if ($order['table_number']): ?>
                                        <span class="ml-2 bg-blue-100 text-blue-800 text-sm px-2 py-1 rounded-full">Meja <?= $order['table_number'] ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="text-gray-600 text-sm"><?= $order['created_at'] ?></span>
                            </div>
                            <p class="text-sm text-gray-500 mb-2">Total: <span class="font-bold text-gray-700">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span></p>
                            <p class="text-sm text-gray-500 mb-2">Status: <span class="font-bold text-green-500 capitalize"><?= str_replace('_', ' ', $order['status']) ?></span></p>

                            <!-- Detail Pesanan -->
                            <div class="order-details-container hidden space-y-2 mt-4">
                                <?php
                                $items_sql_archived = "SELECT oi.*, m.name FROM order_items oi JOIN menu m ON oi.menu_id = m.id WHERE oi.order_id = ?";
                                $items_stmt_archived = $conn->prepare($items_sql_archived);
                                $items_stmt_archived->bind_param("i", $order['id']);
                                $items_stmt_archived->execute();
                                $items_result_archived = $items_stmt_archived->get_result();
                                while ($item = $items_result_archived->fetch_assoc()): ?>
                                    <div class="flex justify-between">
                                        <span><?= $item['name'] ?> (<?= $item['quantity'] ?>x)</span>
                                        <span>Rp <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?></span>
                                    </div>
                                <?php endwhile; ?>
                                <div class="border-t pt-2 mt-2">
                                    <div class="flex justify-between font-bold"><span>Subtotal</span><span>Rp <?= number_format($order['subtotal'], 0, ',', '.') ?></span></div>
                                    <div class="flex justify-between font-bold"><span>PPN</span><span>Rp <?= number_format($order['tax'], 0, ',', '.') ?></span></div>
                                    <div class="flex justify-between font-bold"><span>TOTAL</span><span>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span></div>
                                </div>
                            </div>
                            <!-- Tombol Tampilkan Detail dan Cetak Struk -->
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button class="toggle-details bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors">Lihat Detail</button>
                                <button onclick="printReceipt(<?= $order['id'] ?>)" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">Cetak Ulang Struk</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

    </main>

    <script>
        // Tampilkan/Sembunyikan detail pesanan
        document.querySelectorAll('.toggle-details').forEach(button => {
            button.addEventListener('click', (e) => {
                const detailsContainer = e.target.closest('div').querySelector('.order-details-container');
                detailsContainer.classList.toggle('hidden');
                e.target.textContent = detailsContainer.classList.contains('hidden') ? 'Lihat Detail' : 'Sembunyikan Detail';
            });
        });

        /**
         * Fungsi dinamis untuk mencetak struk tanpa me-reload halaman.
         * Fungsi ini mengandalkan CSS @media print untuk menyembunyikan elemen yang tidak perlu.
         */
        function printReceipt(orderId) {
            const receiptToPrint = document.getElementById('receipt-' + orderId);
            if (!receiptToPrint) {
                console.error('Elemen struk untuk dicetak tidak ditemukan.');
                return;
            }

            // Hapus class 'hidden' agar elemen bisa terlihat oleh proses print
            receiptToPrint.classList.remove('hidden');

            // Atur event yang akan dijalankan setelah dialog print ditutup (baik print maupun cancel)
            window.onafterprint = function() {
                // Sembunyikan kembali elemen struk
                receiptToPrint.classList.add('hidden');
                // Hapus event listener agar tidak berjalan lagi di lain waktu
                window.onafterprint = null;
            };

            // Panggil dialog print browser
            window.print();

            // Fallback untuk memastikan struk disembunyikan lagi jika onafterprint tidak terpicu
            setTimeout(() => {
                receiptToPrint.classList.add('hidden');
            }, 500);
        }

        // Logic JavaScript untuk kalkulasi kembalian pada pembayaran pending
        document.querySelectorAll('input[id^="amount-given-"]').forEach(input => {
            input.addEventListener('input', (e) => {
                const orderId = e.target.id.replace('amount-given-', '');
                const form = e.target.closest('form');
                const totalAmount = parseFloat(form.querySelector('input[name="total_amount"]').value);
                const amountGiven = parseFloat(e.target.value) || 0;
                const change = amountGiven - totalAmount;
                const changeSpan = document.getElementById(`change-amount-${orderId}`);

                if (change >= 0) {
                    changeSpan.textContent = `Rp ${change.toLocaleString('id-ID')}`;
                } else {
                    changeSpan.textContent = 'Rp 0';
                }
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            // Sembunyikan pesan sukses setelah 3 detik
            const successMessage = document.getElementById('success-message');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.transition = 'opacity 0.5s';
                    successMessage.style.opacity = '0';
                    setTimeout(() => successMessage.remove(), 500);
                }, 3000);
            }

            // Mobile sidebar toggle
            const menuButton = document.getElementById('menu-button');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            const toggleSidebar = () => {
                sidebar.classList.toggle('-translate-x-full');
                sidebar.classList.toggle('translate-x-0');
                overlay.classList.toggle('hidden');
            };

            if (menuButton && sidebar && overlay) {
                menuButton.addEventListener('click', toggleSidebar);
                overlay.addEventListener('click', toggleSidebar);
            }
        });
    </script>
</body>

</html>