<?php
// File: manual_order.php
// Halaman untuk membuat pesanan manual dengan tampilan kartu

// Catatan: Pastikan file db_connect.php sudah ada dan berisi koneksi database.
require_once 'db_connect.php';

// =========================================================================
//  LOGIC BAGIAN A: Mengambil data dari database
// =========================================================================

// Mengambil semua item menu yang tersedia untuk manual order
$menu_items = [];
$sql_menu = "SELECT * FROM menu WHERE is_available = 1 ORDER BY category, name";
$result_menu = $conn->query($sql_menu);
if ($result_menu) {
    while ($row_menu = $result_menu->fetch_assoc()) {
        $menu_items[] = $row_menu;
    }
}

// Mengelompokkan item menu berdasarkan kategori
$categorized_menu_items = [];
foreach ($menu_items as $item) {
    if (!isset($categorized_menu_items[$item['category']])) {
        $categorized_menu_items[$item['category']] = [];
    }
    $categorized_menu_items[$item['category']][] = $item;
}

// =========================================================================
//  LOGIC BAGIAN B: Menangani Aksi POST
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Aksi untuk membuat pesanan manual baru
    if (isset($_POST['action']) && $_POST['action'] === 'create_manual_order') {
        $order_type = $_POST['order_type'];
        $table_number = $_POST['table_number'] ? (int)$_POST['table_number'] : NULL;
        $items = json_decode($_POST['items_json'], true); // items adalah array objek JSON
        $total_amount = (float)$_POST['total_amount'];
        $amount_given = (float)$_POST['amount_given'];
        $change_amount = $amount_given - $total_amount;
        $payment_method = $_POST['payment_method']; // Ambil metode pembayaran dari form

        $subtotal = 0;
        foreach ($items as $item_data) {
            $subtotal += $item_data['price'] * $item_data['quantity'];
        }
        $tax = $subtotal * 0.1;

        $table_id = NULL;
        if ($table_number) {
            $sql_get_table_id = "SELECT id FROM tables WHERE table_number = ?";
            $stmt_get_table_id = $conn->prepare($sql_get_table_id);
            $stmt_get_table_id->bind_param("i", $table_number);
            $stmt_get_table_id->execute();
            $result_get_table_id = $stmt_get_table_id->get_result();
            if ($result_get_table_id->num_rows > 0) {
                $table_id = $result_get_table_id->fetch_assoc()['id'];
            }
        }

        // Memasukkan pesanan baru ke tabel orders
        $sql_insert_order = "INSERT INTO orders (table_id, order_type, subtotal, tax, total_amount, payment_method, status) VALUES (?, ?, ?, ?, ?, ?, 'completed')";
        $stmt_insert_order = $conn->prepare($sql_insert_order);
        $stmt_insert_order->bind_param("isddds", $table_id, $order_type, $subtotal, $tax, $total_amount, $payment_method);
        $stmt_insert_order->execute();
        $order_id = $conn->insert_id;

        // Memasukkan item pesanan
        $sql_insert_item = "INSERT INTO order_items (order_id, menu_id, quantity, price) VALUES (?, ?, ?, ?)";
        $stmt_insert_item = $conn->prepare($sql_insert_item);
        foreach ($items as $item_data) {
            $stmt_insert_item->bind_param("iiid", $order_id, $item_data['id'], $item_data['quantity'], $item_data['price']);
            $stmt_insert_item->execute();
        }

        // Memasukkan data ke tabel transaksi
        $sql_insert_trans = "INSERT INTO transactions (order_id, total_paid, amount_given, change_amount, payment_method) VALUES (?, ?, ?, ?, ?)";
        $stmt_insert_trans = $conn->prepare($sql_insert_trans);
        $stmt_insert_trans->bind_param("iddds", $order_id, $total_amount, $amount_given, $change_amount, $payment_method);
        $stmt_insert_trans->execute();

        header("Location: kasir.php?message=Pesanan+manual+berhasil+dibuat+dan+dibayar");
        exit();
    }
}
?>
<!DOCTYPE: HTML>
    <html lang="id">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Manual Order</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

            body {
                font-family: 'Inter', sans-serif;
                background-color: #f3f4f6;
            }

            .menu-card {
                cursor: pointer;
                transition: transform 0.2s, box-shadow 0.2s;
            }

            .menu-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            }

            .selected-item-card {
                background-color: #e2e8f0;
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
                <a href="manual_order.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700 bg-gray-700">Manual Order</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-grow p-8 flex flex-col md:flex-row gap-8">
            <!-- Kolom Kiri: Menu Makanan & Minuman -->
            <section class="flex-1 bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-2xl font-bold text-gray-700 mb-4">Pilih Menu</h2>
                <div id="menu-items-container" class="space-y-6 max-h-[70vh] overflow-y-auto">
                    <?php if (empty($categorized_menu_items)): ?>
                        <p class="text-gray-500">Tidak ada menu yang tersedia.</p>
                    <?php else: ?>
                        <?php foreach ($categorized_menu_items as $category => $items): ?>
                            <div class="border-b-2 border-gray-200 pb-2">
                                <h3 class="text-xl font-bold text-gray-800 capitalize"><?= htmlspecialchars($category) ?></h3>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                <?php foreach ($items as $item): ?>
                                    <div class="menu-card bg-gray-50 p-4 rounded-lg shadow-sm border border-gray-200" data-id="<?= $item['id'] ?>" data-name="<?= htmlspecialchars($item['name']) ?>" data-price="<?= $item['price'] ?>">
                                        <h3 class="font-semibold text-lg text-gray-800"><?= htmlspecialchars($item['name']) ?></h3>
                                        <p class="text-sm text-gray-600">Rp <?= number_format($item['price'], 0, ',', '.') ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Kolom Kanan: Ringkasan Pesanan & Pembayaran -->
            <section class="flex-1 bg-white rounded-xl shadow-lg p-6 flex flex-col">
                <h2 class="text-2xl font-bold text-gray-700 mb-4">Ringkasan Pesanan</h2>
                <form id="manual-order-form" method="POST" class="space-y-4 flex flex-col h-full">
                    <input type="hidden" name="action" value="create_manual_order">

                    <!-- Informasi Pesanan -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Jenis Pesanan</label>
                            <select name="order_type" id="order-type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <option value="dine-in">Dine In</option>
                                <option value="take-away">Take Away</option>
                            </select>
                        </div>
                        <div id="table-number-group">
                            <label class="block text-sm font-medium text-gray-700">Nomor Meja</label>
                            <input type="number" name="table_number" id="table-number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" required>
                        </div>
                    </div>

                    <!-- Daftar Item Pesanan -->
                    <div id="order-summary-list" class="flex-grow space-y-2 p-2 border rounded-md bg-gray-50 max-h-64 overflow-y-auto">
                        <p id="empty-order-message" class="text-gray-500 text-center py-4">Belum ada item ditambahkan.</p>
                    </div>

                    <!-- Ringkasan Harga -->
                    <div class="mt-auto p-4 bg-gray-100 rounded-lg shadow">
                        <div class="flex justify-between items-center text-gray-700">
                            <span>Subtotal:</span>
                            <span id="manual-subtotal" class="font-semibold">Rp 0</span>
                        </div>
                        <div class="flex justify-between items-center text-gray-700">
                            <span>PPN (10%):</span>
                            <span id="manual-tax" class="font-semibold">Rp 0</span>
                        </div>
                        <div class="flex justify-between items-center text-gray-900 font-bold text-xl mt-2">
                            <span>TOTAL:</span>
                            <span id="manual-total">Rp 0</span>
                            <input type="hidden" name="total_amount" id="total-amount-hidden">
                        </div>
                    </div>

                    <!-- Formulir Pembayaran -->
                    <div class="p-4 bg-gray-100 rounded-lg shadow">
                        <h3 class="font-bold text-lg mb-2">Pilih Metode Pembayaran</h3>
                        <select name="payment_method" id="payment-method-select" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 mb-4">
                            <option value="manual_cash">Tunai</option>
                            <option value="qris">QRIS</option>
                        </select>

                        <!-- Bagian Tunai -->
                        <div id="cash-payment-fields" class="space-y-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Uang Diberikan (Rp)</label>
                                <input type="number" name="amount_given" id="amount-given" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" min="0" required>
                            </div>
                            <div class="mt-2 text-xl font-bold flex justify-between">
                                <span>Kembalian:</span>
                                <span id="change-amount">Rp 0</span>
                            </div>
                        </div>

                        <!-- Bagian QRIS -->
                        <div id="qris-payment-field" class="hidden text-center">
                            <p class="text-sm text-gray-600 mb-2">Silakan pindai QRIS di bawah ini untuk membayar.</p>
                            <div class="bg-white p-4 rounded-lg inline-block shadow-md">

                            </div>
                            <input type="hidden" name="amount_given" value="0">
                        </div>
                    </div>

                    <button type="submit" id="process-manual-payment" class="w-full bg-green-500 text-white px-4 py-3 rounded-lg font-semibold text-lg hover:bg-green-600 transition-colors">Proses Pembayaran & Cetak Struk</button>
                    <input type="hidden" name="items_json" id="items-json-input">
                </form>
            </section>
        </main>
        <script>
            // Fungsi pengganti alert() dengan toast notification
            function showMessage(message) {
                const toast = document.createElement('div');
                toast.className = 'fixed top-4 right-4 bg-gray-800 text-white px-6 py-3 rounded-lg shadow-xl z-50 transform translate-y-full transition-transform duration-300 ease-out';
                toast.textContent = message;

                document.body.appendChild(toast);

                // Animasikan toast masuk
                setTimeout(() => {
                    toast.classList.remove('translate-y-full');
                    toast.classList.add('translate-y-0');
                }, 100);

                // Hilangkan toast setelah 3 detik
                setTimeout(() => {
                    toast.classList.remove('translate-y-0');
                    toast.classList.add('translate-y-full');
                    // Hapus elemen setelah animasi selesai
                    setTimeout(() => {
                        toast.remove();
                    }, 300);
                }, 3000);
            }

            // =========================================================================
            //  LOGIC JAVASCRIPT UNTUK MANUAL ORDER
            // =========================================================================
            const menuItemsContainer = document.getElementById('menu-items-container');
            const orderSummaryList = document.getElementById('order-summary-list');
            const emptyOrderMessage = document.getElementById('empty-order-message');
            const manualSubtotalSpan = document.getElementById('manual-subtotal');
            const manualTaxSpan = document.getElementById('manual-tax');
            const manualTotalSpan = document.getElementById('manual-total');
            const totalAmountHiddenInput = document.getElementById('total-amount-hidden');
            const amountGivenInput = document.getElementById('amount-given');
            const changeAmountSpan = document.getElementById('change-amount');
            const orderTypeSelect = document.getElementById('order-type');
            const tableNumberGroup = document.getElementById('table-number-group');
            const tableNumberInput = document.getElementById('table-number');
            const manualOrderForm = document.getElementById('manual-order-form');
            const itemsJsonInput = document.getElementById('items-json-input');
            const paymentMethodSelect = document.getElementById('payment-method-select');
            const cashPaymentFields = document.getElementById('cash-payment-fields');
            const qrisPaymentField = document.getElementById('qris-payment-field');

            let orderItems = {};

            // Fungsi untuk mengupdate total dan ringkasan pesanan
            function updateOrderSummary() {
                let subtotal = 0;
                let itemsCount = 0;
                orderSummaryList.innerHTML = '';

                // Perbarui daftar item yang dipilih
                for (const id in orderItems) {
                    const item = orderItems[id];
                    const itemElement = document.createElement('div');
                    itemElement.className = 'flex justify-between items-center bg-white p-3 rounded-lg shadow-sm selected-item-card';
                    itemElement.innerHTML = `
                    <span class="font-medium text-gray-800">${item.name}</span>
                    <div class="flex items-center space-x-2">
                        <button type="button" class="quantity-button bg-red-500 text-white w-6 h-6 rounded-full flex items-center justify-center hover:bg-red-600 transition-colors" data-id="${id}" data-action="subtract">
                            <i class="fas fa-minus text-xs"></i>
                        </button>
                        <span class="text-lg font-bold w-6 text-center">${item.quantity}</span>
                        <button type="button" class="quantity-button bg-green-500 text-white w-6 h-6 rounded-full flex items-center justify-center hover:bg-green-600 transition-colors" data-id="${id}" data-action="add">
                            <i class="fas fa-plus text-xs"></i>
                        </button>
                    </div>
                `;
                    orderSummaryList.appendChild(itemElement);
                    subtotal += item.quantity * item.price;
                    itemsCount++;
                }

                if (itemsCount === 0) {
                    emptyOrderMessage.classList.remove('hidden');
                    orderSummaryList.appendChild(emptyOrderMessage);
                } else {
                    emptyOrderMessage.classList.add('hidden');
                }

                // Perbarui subtotal, pajak, dan total
                const tax = subtotal * 0.1;
                const total = subtotal + tax;

                manualSubtotalSpan.textContent = `Rp ${subtotal.toLocaleString('id-ID')}`;
                manualTaxSpan.textContent = `Rp ${tax.toLocaleString('id-ID')}`;
                manualTotalSpan.textContent = `Rp ${total.toLocaleString('id-ID')}`;
                totalAmountHiddenInput.value = total;

                // Perbarui kembalian hanya jika metode pembayaran tunai
                if (paymentMethodSelect.value === 'manual_cash') {
                    updateChange(amountGivenInput, changeAmountSpan, total);
                }
            }

            // Fungsi untuk mengupdate kembalian
            function updateChange(amountInput, changeSpan, total) {
                const amountGiven = parseFloat(amountInput.value) || 0;
                const change = amountGiven - total;
                changeSpan.textContent = `Rp ${change.toLocaleString('id-ID')}`;
            }

            // Event listener untuk klik pada kartu menu
            menuItemsContainer.addEventListener('click', (e) => {
                const card = e.target.closest('.menu-card');
                if (!card) return;

                const id = card.dataset.id;
                const name = card.dataset.name;
                const price = parseFloat(card.dataset.price);

                if (orderItems[id]) {
                    orderItems[id].quantity++;
                } else {
                    orderItems[id] = {
                        id: parseInt(id),
                        name: name,
                        price: price,
                        quantity: 1
                    };
                }
                updateOrderSummary();
            });

            // Event listener untuk tombol +/- di ringkasan pesanan
            orderSummaryList.addEventListener('click', (e) => {
                const button = e.target.closest('.quantity-button');
                if (!button) return;

                const id = button.dataset.id;

                if (button.dataset.action === 'add') {
                    orderItems[id].quantity++;
                } else if (button.dataset.action === 'subtract') {
                    orderItems[id].quantity--;
                    if (orderItems[id].quantity <= 0) {
                        delete orderItems[id];
                    }
                }
                updateOrderSummary();
            });

            // Event listener untuk uang yang diberikan
            amountGivenInput.addEventListener('input', () => updateChange(amountGivenInput, changeAmountSpan, parseFloat(totalAmountHiddenInput.value)));

            // Event listener untuk jenis pesanan
            orderTypeSelect.addEventListener('change', (e) => {
                if (e.target.value === 'dine-in') {
                    tableNumberGroup.classList.remove('hidden');
                    tableNumberInput.required = true;
                } else {
                    tableNumberGroup.classList.add('hidden');
                    tableNumberInput.required = false;
                    tableNumberInput.value = ''; // Reset nilai
                }
            });

            // Event listener untuk pilihan metode pembayaran
            paymentMethodSelect.addEventListener('change', (e) => {
                if (e.target.value === 'manual_cash') {
                    cashPaymentFields.classList.remove('hidden');
                    qrisPaymentField.classList.add('hidden');
                    amountGivenInput.required = true;
                } else {
                    cashPaymentFields.classList.add('hidden');
                    qrisPaymentField.classList.remove('hidden');
                    amountGivenInput.required = false;
                }
            });


            // Sembunyikan form nomor meja saat pertama kali dibuka jika bukan dine-in
            document.addEventListener('DOMContentLoaded', () => {
                if (orderTypeSelect.value !== 'dine-in') {
                    tableNumberGroup.classList.add('hidden');
                }
                updateOrderSummary(); // Inisialisasi tampilan
            });

            // Event listener untuk submit form
            manualOrderForm.addEventListener('submit', (e) => {
                const items = Object.values(orderItems);
                if (items.length === 0) {
                    e.preventDefault();
                    showMessage("Pilih setidaknya satu item menu.");
                    return;
                }

                const orderType = orderTypeSelect.value;
                if (orderType === 'dine-in' && !tableNumberInput.value) {
                    e.preventDefault();
                    showMessage("Silakan pilih nomor meja untuk pesanan Dine In.");
                    return;
                }

                // Tambahkan item yang dipilih ke form sebagai input hidden
                itemsJsonInput.value = JSON.stringify(items);
            });
        </script>
    </body>

    </html>