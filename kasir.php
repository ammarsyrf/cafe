<?php
// File: kasir.php
// Halaman untuk kasir

// Catatan: Pastikan file db_connect.php sudah ada dan berisi koneksi database.
require_once 'db_connect.php';

// =========================================================================
//  LOGIC BAGIAN A: Mengambil data dari database
// =========================================================================

// Mengambil pesanan yang menunggu pembayaran tunai (status 'pending_payment')
$pending_orders = [];
$sql_pending = "SELECT o.*, t.table_number, u.name AS user_name
                FROM orders o
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.status = 'pending_payment'
                ORDER BY o.created_at DESC";
$result_pending = $conn->query($sql_pending);
if ($result_pending) {
    while ($row_pending = $result_pending->fetch_assoc()) {
        $pending_orders[] = $row_pending;
    }
}


// Mengambil pesanan yang sudah dibayar (status 'paid' atau 'completed')
$orders = [];
$sql = "SELECT o.*, t.table_number, u.name AS user_name
        FROM orders o
        LEFT JOIN tables t ON o.table_id = t.id
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.status = 'paid' OR o.status = 'completed'
        ORDER BY o.created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

// Mengambil pesanan yang sudah diarsipkan (status 'completed_printed')
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
//  LOGIC BAGIAN B: Menangani Aksi POST
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
<!DOCTYPE: HTML>
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

    <body class="bg-gray-100 min-h-screen flex">
        <!-- Sidebar -->
        <aside class="bg-gray-800 text-white w-64 p-4 space-y-4">
            <h2 class="text-2xl font-bold mb-6">Kasir</h2>
            <nav>
                <a href="kasir.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">Daftar Pesanan</a>
                <a href="manual_order.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">Manual Order</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-grow p-8">
            <header class="mb-8">
                <h1 class="text-4xl font-bold text-gray-800">Dashboard Kasir</h1>
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
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-lg font-semibold text-gray-800">
                                        Pesanan #<?= $order['id'] ?>
                                        <?php if ($order['table_number']): ?>
                                            <span class="ml-2 bg-blue-100 text-blue-800 text-sm px-2 py-1 rounded-full">Meja <?= $order['table_number'] ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="text-gray-600"><?= $order['created_at'] ?></span>
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
                                    <button type="submit" class="w-full bg-green-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-green-600 transition-colors">Proses Pembayaran & Cetak Struk</button>
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
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-lg font-semibold text-gray-800">
                                        Pesanan #<?= $order['id'] ?>
                                        <?php if ($order['table_number']): ?>
                                            <span class="ml-2 bg-blue-100 text-blue-800 text-sm px-2 py-1 rounded-full">Meja <?= $order['table_number'] ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="text-gray-600"><?= $order['created_at'] ?></span>
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
                                        $items_sql = "SELECT oi.*, m.name FROM order_items oi JOIN menu m ON oi.menu_id = m.id WHERE oi.order_id = ?";
                                        $items_stmt = $conn->prepare($items_sql);
                                        $items_stmt->bind_param("i", $order['id']);
                                        $items_stmt->execute();
                                        $items_result = $items_stmt->get_result();
                                        while ($item = $items_result->fetch_assoc()): ?>
                                            <div class="flex justify-between text-sm">
                                                <span><?= $item['name'] ?> (<?= $item['quantity'] ?>x)</span>
                                                <span>Rp <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?></span>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                    <div class="text-sm space-y-1">
                                        <div class="flex justify-between"><span>Subtotal</span><span>Rp <?= number_format($order['subtotal'], 0, ',', '.') ?></span></div>
                                        <div class="flex justify-between"><span>PPN (10%)</span><span>Rp <?= number_format($order['tax'], 0, ',', '.') ?></span></div>
                                        <div class="flex justify-between font-bold"><span>TOTAL</span><span>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span></div>
                                        <div class="flex justify-between"><span>Bayar</span><span>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span></div>
                                        <div class="flex justify-between"><span>Kembalian</span><span>Rp 0</span></div>
                                    </div>
                                    <div class="text-center mt-4 text-xs">
                                        <p>Terima kasih sudah berkunjung!</p>
                                    </div>
                                </div>
                                <!-- Akhir Konten Struk -->

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

                                <button class="toggle-details bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors">Lihat Detail</button>
                                <!-- Mengubah tombol cetak struk menjadi form untuk pembaruan status -->
                                <form method="POST" class="inline-block" onsubmit="printReceipt(<?= $order['id'] ?>); return true;">
                                    <input type="hidden" name="action" value="print_and_archive">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors ml-2">Cetak & Arsipkan Struk</button>
                                </form>
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
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-lg font-semibold text-gray-800">
                                        Pesanan #<?= $order['id'] ?>
                                        <?php if ($order['table_number']): ?>
                                            <span class="ml-2 bg-blue-100 text-blue-800 text-sm px-2 py-1 rounded-full">Meja <?= $order['table_number'] ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="text-gray-600"><?= $order['created_at'] ?></span>
                                </div>
                                <p class="text-sm text-gray-500 mb-2">Total: <span class="font-bold text-gray-700">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span></p>
                                <p class="text-sm text-gray-500 mb-2">Status: <span class="font-bold text-green-500 capitalize"><?= str_replace('_', ' ', $order['status']) ?></span></p>

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
                                <!-- Tombol Tampilkan Detail dan Cetak Struk -->
                                <button class="toggle-details bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors">Lihat Detail</button>
                                <button onclick="printReceipt(<?= $order['id'] ?>)" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors ml-2">Cetak Ulang Struk</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

        </main>

        <script>
            // Tampilkan/Sembunyikan detail pesanan yang sudah dibayar
            document.querySelectorAll('.toggle-details').forEach(button => {
                button.addEventListener('click', (e) => {
                    const detailsContainer = e.target.closest('div').querySelector('.order-details-container');
                    detailsContainer.classList.toggle('hidden');
                    if (!detailsContainer.classList.contains('hidden')) {
                        e.target.textContent = 'Sembunyikan Detail';
                    } else {
                        e.target.textContent = 'Lihat Detail';
                    }
                });
            });

            // Fungsi untuk mencetak struk
            function printReceipt(orderId) {
                const printContent = document.getElementById('receipt-' + orderId).innerHTML;
                const originalBody = document.body.innerHTML;

                // Menggunakan onafterprint untuk memastikan halaman dimuat ulang setelah dialog cetak ditutup
                window.onafterprint = function() {
                    // Hapus event listener untuk menghindari pemanggilan ganda
                    window.onafterprint = null;
                    // Muat ulang halaman setelah dialog ditutup
                    window.location.reload();
                };

                // Ganti konten body dengan konten struk
                document.body.innerHTML = printContent;

                // Panggil dialog cetak
                window.print();

                // Kembalikan konten body asli untuk berjaga-jaga (walaupun onafterprint akan memuat ulang halaman)
                document.body.innerHTML = originalBody;
            }

            // =========================================================================
            //  LOGIC JAVASCRIPT UNTUK PEMBAYARAN PENDING
            // =========================================================================
            document.querySelectorAll('input[id^="amount-given-"]').forEach(input => {
                input.addEventListener('input', (e) => {
                    const orderId = e.target.id.replace('amount-given-', '');
                    const form = e.target.closest('form');
                    const totalAmount = parseFloat(form.querySelector('input[name="total_amount"]').value);
                    const changeSpan = document.getElementById(`change-amount-${orderId}`);
                    updateChange(e.target, changeSpan, totalAmount);
                });
            });

            // Fungsi untuk mengupdate kembalian
            function updateChange(amountInput, changeSpan, total) {
                const amountGiven = parseFloat(amountInput.value) || 0;
                const change = amountGiven - total;
                changeSpan.textContent = `Rp ${change.toLocaleString('id-ID')}`;
            }

            // Sembunyikan pesan sukses setelah 2 detik
            document.addEventListener('DOMContentLoaded', () => {
                const successMessage = document.getElementById('success-message');
                if (successMessage) {
                    setTimeout(() => {
                        successMessage.classList.add('opacity-0');
                        setTimeout(() => {
                            successMessage.remove();
                        }, 500);
                    }, 2000);
                }
            });
        </script>
    </body>

    </html>