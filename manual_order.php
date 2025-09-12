<?php
// File: manual_order.php
// Halaman untuk membuat pesanan manual dengan tampilan kartu

// Keamanan & Manajemen Sesi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// Cek apakah user sudah login dan memiliki peran sebagai kasir
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'cashier') {
    header("Location: login_kasir.php?error=Anda harus login sebagai kasir untuk mengakses halaman ini.");
    exit();
}

// Ambil informasi kasir dari sesi
$kasir_id = $_SESSION['id'];
$kasir_name = $_SESSION['username'];


// =========================================================================
//  LOGIC BAGIAN A: Mengambil data dari database
// =========================================================================

// Mengambil semua item menu yang tersedia
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
//  LOGIC BAGIAN B: Menangani Aksi POST
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create_manual_order') {
        $order_type = $_POST['order_type'];
        $table_number = $_POST['table_number'] ? (int)$_POST['table_number'] : NULL;
        $items = json_decode($_POST['items_json'], true);
        $total_amount = (float)$_POST['total_amount'];
        $payment_method = $_POST['payment_method'];

        $amount_given = 0;
        $change_amount = 0;

        if ($payment_method === 'manual_cash') {
            $amount_given = (float)str_replace('.', '', $_POST['amount_given']);
            $change_amount = $amount_given - $total_amount;
        } else {
            $amount_given = $total_amount;
            $change_amount = 0;
        }

        $subtotal = 0;
        foreach ($items as $item_data) {
            $subtotal += $item_data['price'] * $item_data['quantity'];
        }
        $tax = $subtotal * 0.11; // PPN 11%

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

        $status = ($payment_method === 'manual_cash') ? 'completed' : 'paid';
        $actual_payment_method = ($payment_method === 'manual_cash') ? 'cash' : $payment_method;

        $sql_insert_order = "INSERT INTO orders (user_id, table_id, order_type, subtotal, tax, total_amount, payment_method, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert_order = $conn->prepare($sql_insert_order);
        $stmt_insert_order->bind_param("iisdddss", $kasir_id, $table_id, $order_type, $subtotal, $tax, $total_amount, $actual_payment_method, $status);
        $stmt_insert_order->execute();
        $order_id = $conn->insert_id;

        $sql_insert_item = "INSERT INTO order_items (order_id, menu_id, quantity, price) VALUES (?, ?, ?, ?)";
        $stmt_insert_item = $conn->prepare($sql_insert_item);
        foreach ($items as $item_data) {
            $stmt_insert_item->bind_param("iiid", $order_id, $item_data['id'], $item_data['quantity'], $item_data['price']);
            $stmt_insert_item->execute();
        }

        $sql_insert_trans = "INSERT INTO transactions (order_id, total_paid, amount_given, change_amount, payment_method) VALUES (?, ?, ?, ?, ?)";
        $stmt_insert_trans = $conn->prepare($sql_insert_trans);
        $stmt_insert_trans->bind_param("iddds", $order_id, $total_amount, $amount_given, $change_amount, $actual_payment_method);
        $stmt_insert_trans->execute();

        header("Location: kasir.php?message=Pesanan+manual+berhasil+dibuat");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Order</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        .menu-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        .menu-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
        }

        .summary-item-enter {
            animation: slideIn 0.3s ease-out forwards;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Custom scrollbar */
        #order-summary-list::-webkit-scrollbar {
            width: 6px;
        }

        #order-summary-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        #order-summary-list::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 10px;
        }

        #order-summary-list::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen bg-gray-100">
        <!-- Sidebar -->
        <aside id="sidebar" class="bg-gray-800 text-white w-64 p-4 space-y-4 fixed top-0 left-0 h-screen z-40 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col">
            <div>
                <h2 class="text-2xl font-bold mb-2">Kasir</h2>
                <div class="p-2.5 bg-gray-700 rounded-lg mb-6">
                    <p class="text-sm text-gray-300">Selamat Datang,</p>
                    <p class="font-semibold text-lg"><?= htmlspecialchars($kasir_name) ?></p>
                </div>
                <nav>
                    <a href="kasir.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">Daftar Pesanan</a>
                    <a href="manual_order.php" class="block py-2.5 px-4 rounded transition duration-200 bg-gray-900 font-semibold">Manual Order</a>
                </nav>
            </div>
            <div class="mt-auto">
                <a href="logout.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-red-500 bg-red-600 text-center font-semibold">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
        </aside>

        <!-- Overlay -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden"></div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden md:ml-64 transition-all duration-300">
            <!-- Header -->
            <header class="sticky top-0 bg-white/80 backdrop-blur-sm z-20">
                <div class="flex justify-between items-center p-4 sm:p-6 border-b border-gray-200">
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Buat Pesanan Manual</h1>
                    <!-- Tombol Menu untuk Mobile -->
                    <button id="menu-button" class="md:hidden p-2 rounded-md text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                        </svg>
                    </button>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto">
                <div class="p-4 sm:p-6 lg:p-8 flex flex-col lg:flex-row gap-8 items-start">
                    <!-- Kolom Kiri: Menu -->
                    <section class="w-full lg:w-3/5">
                        <div id="menu-items-container" class="space-y-8">
                            <?php if (empty($categorized_menu_items)): ?>
                                <p class="text-gray-500 bg-white p-6 rounded-xl shadow-sm">Tidak ada menu yang tersedia.</p>
                            <?php else: ?>
                                <?php foreach ($categorized_menu_items as $category => $items): ?>
                                    <div>
                                        <h3 class="text-xl font-bold text-gray-800 capitalize mb-4 pb-2 border-b-2 border-gray-200"><?= htmlspecialchars($category) ?></h3>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                                            <?php foreach ($items as $item): ?>
                                                <div class="menu-card bg-white p-4 rounded-lg shadow-sm border border-gray-200 cursor-pointer group" data-id="<?= $item['id'] ?>" data-name="<?= htmlspecialchars($item['name']) ?>" data-price="<?= $item['price'] ?>">
                                                    <div class="flex justify-between items-start">
                                                        <div>
                                                            <h4 class="font-semibold text-gray-800 group-hover:text-blue-600"><?= htmlspecialchars($item['name']) ?></h4>
                                                            <p class="text-sm text-gray-600">Rp <?= number_format($item['price'], 0, ',', '.') ?></p>
                                                        </div>
                                                        <div class="bg-gray-100 group-hover:bg-blue-500 group-hover:text-white rounded-full w-8 h-8 flex items-center justify-center transition-colors">
                                                            <i class="fas fa-plus text-sm"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- Kolom Kanan: Ringkasan -->
                    <section class="w-full lg:w-2/5 lg:sticky lg:top-24">
                        <form id="manual-order-form" method="POST" class="bg-white rounded-xl shadow-lg flex flex-col">
                            <div class="p-6 border-b border-gray-200">
                                <h2 class="text-2xl font-bold text-gray-700">Ringkasan Pesanan</h2>
                            </div>
                            <div class="p-6 space-y-4 flex-1">
                                <input type="hidden" name="action" value="create_manual_order">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Pesanan</label>
                                    <select name="order_type" id="order-type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="dine-in">Dine In</option>
                                        <option value="take-away">Take Away</option>
                                    </select>
                                </div>
                                <div id="table-number-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Meja</label>
                                    <input type="number" name="table_number" id="table-number" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                </div>
                            </div>

                            <div id="order-summary-list" class="flex-grow space-y-2 p-4 border-t border-b bg-gray-50 min-h-[150px] max-h-64 overflow-y-auto">
                                <div id="empty-order-message" class="text-gray-400 text-center py-4 h-full flex flex-col justify-center items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <p>Belum ada item ditambahkan.</p>
                                </div>
                            </div>

                            <div class="p-6 space-y-2 border-b">
                                <div class="flex justify-between items-center text-gray-700"><span>Subtotal:</span><span id="manual-subtotal" class="font-semibold">Rp 0</span></div>
                                <div class="flex justify-between items-center text-gray-700"><span>PPN (11%):</span><span id="manual-tax" class="font-semibold">Rp 0</span></div>
                                <div class="flex justify-between items-center text-gray-900 font-bold text-xl mt-2"><span>TOTAL:</span><span id="manual-total">Rp 0</span><input type="hidden" name="total_amount" id="total-amount-hidden"></div>
                            </div>

                            <div class="p-6">
                                <h3 class="font-bold text-lg mb-2">Metode Pembayaran</h3>
                                <select name="payment_method" id="payment-method-select" class="block w-full rounded-md border-gray-300 shadow-sm mb-4 focus:border-blue-500 focus:ring-blue-500">
                                    <option value="manual_cash">Tunai</option>
                                    <option value="qris">QRIS</option>
                                </select>
                                <div id="cash-payment-fields" class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">Uang Diberikan (Rp)</label>
                                    <input type="text" name="amount_given" id="amount-given" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-lg" required placeholder="0">
                                    <div class="text-lg font-bold flex justify-between"><span>Kembalian:</span><span id="change-amount">Rp 0</span></div>
                                </div>
                                <div id="qris-payment-field" class="hidden text-center">
                                    <p class="text-sm text-gray-600 mb-2">Konfirmasi setelah pelanggan scan QRIS.</p>
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=Example" alt="QRIS Code" class="mx-auto rounded-lg">
                                </div>
                            </div>

                            <div class="p-6 bg-gray-50 rounded-b-xl">
                                <button type="submit" id="process-manual-payment" class="w-full bg-green-500 text-white px-4 py-3 rounded-lg font-semibold text-lg hover:bg-green-600 transition-colors shadow-md hover:shadow-lg disabled:bg-gray-400 disabled:cursor-not-allowed">Proses & Selesaikan</button>
                                <input type="hidden" name="items_json" id="items-json-input">
                            </div>
                        </form>
                    </section>
                </div>
            </main>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // --- Elemen DOM ---
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
            const processButton = document.getElementById('process-manual-payment');

            // --- Sidebar ---
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


            let orderItems = {}; // { id: {id, name, price, quantity} }
            const TAX_RATE = 0.11;

            // --- Fungsi Utama ---
            const formatRupiah = (angka) => new Intl.NumberFormat('id-ID').format(angka);

            const showMessage = (message, isError = false) => {
                const toast = document.createElement('div');
                toast.className = `fixed top-5 right-5 text-white px-6 py-3 rounded-lg shadow-xl z-50 transition-transform transform translate-x-full`;
                toast.textContent = message;
                toast.style.backgroundColor = isError ? 'rgb(239 68 68)' : 'rgb(34 197 94)';
                document.body.appendChild(toast);

                setTimeout(() => toast.classList.remove('translate-x-full'), 10);
                setTimeout(() => {
                    toast.classList.add('translate-x-full');
                    toast.addEventListener('transitionend', () => toast.remove());
                }, 3000);
            };

            const updateOrderSummary = () => {
                let subtotal = 0;
                const hasItems = Object.keys(orderItems).length > 0;

                emptyOrderMessage.classList.toggle('hidden', hasItems);
                processButton.disabled = !hasItems;

                // Hapus item lama sebelum render ulang
                orderSummaryList.querySelectorAll('.summary-item').forEach(el => el.remove());

                for (const id in orderItems) {
                    const item = orderItems[id];
                    const itemElement = document.createElement('div');
                    itemElement.className = 'summary-item flex justify-between items-center bg-white p-3 rounded-lg shadow-sm summary-item-enter';
                    itemElement.innerHTML = `
                    <div class="flex-grow pr-2">
                        <p class="font-medium text-gray-800 text-sm">${item.name}</p>
                        <p class="text-xs text-gray-500">Rp ${formatRupiah(item.price)}</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button type="button" class="quantity-button bg-red-100 text-red-600 w-6 h-6 rounded-full flex items-center justify-center hover:bg-red-500 hover:text-white transition-colors" data-id="${id}" data-action="subtract"><i class="fas fa-minus text-xs"></i></button>
                        <span class="font-bold w-6 text-center">${item.quantity}</span>
                        <button type="button" class="quantity-button bg-green-100 text-green-600 w-6 h-6 rounded-full flex items-center justify-center hover:bg-green-500 hover:text-white transition-colors" data-id="${id}" data-action="add"><i class="fas fa-plus text-xs"></i></button>
                    </div>`;
                    orderSummaryList.appendChild(itemElement);
                    subtotal += item.quantity * item.price;
                }

                const tax = subtotal * TAX_RATE;
                const total = subtotal + tax;

                manualSubtotalSpan.textContent = `Rp ${formatRupiah(subtotal)}`;
                manualTaxSpan.textContent = `Rp ${formatRupiah(tax)}`;
                manualTotalSpan.textContent = `Rp ${formatRupiah(total)}`;
                totalAmountHiddenInput.value = total;

                if (paymentMethodSelect.value === 'manual_cash') updateChange();
            };

            const updateChange = () => {
                const total = parseFloat(totalAmountHiddenInput.value) || 0;
                const amountGiven = parseFloat(amountGivenInput.value.replace(/\D/g, '')) || 0;
                const change = amountGiven - total;
                changeAmountSpan.textContent = `Rp ${change >= 0 ? formatRupiah(change) : '0'}`;
            };

            // --- Event Listeners ---
            menuItemsContainer.addEventListener('click', (e) => {
                const card = e.target.closest('.menu-card');
                if (!card) return;
                const {
                    id,
                    name,
                    price
                } = card.dataset;

                if (orderItems[id]) {
                    orderItems[id].quantity++;
                } else {
                    orderItems[id] = {
                        id: parseInt(id),
                        name,
                        price: parseFloat(price),
                        quantity: 1
                    };
                }
                updateOrderSummary();
            });

            orderSummaryList.addEventListener('click', (e) => {
                const button = e.target.closest('.quantity-button');
                if (!button) return;
                const {
                    id,
                    action
                } = button.dataset;

                if (action === 'add') {
                    orderItems[id].quantity++;
                } else if (action === 'subtract') {
                    orderItems[id].quantity--;
                    if (orderItems[id].quantity <= 0) delete orderItems[id];
                }
                updateOrderSummary();
            });

            amountGivenInput.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                e.target.value = formatRupiah(value);
                updateChange();
            });

            orderTypeSelect.addEventListener('change', (e) => {
                const isDineIn = e.target.value === 'dine-in';
                tableNumberGroup.classList.toggle('hidden', !isDineIn);
                tableNumberInput.required = isDineIn;
                if (!isDineIn) tableNumberInput.value = '';
            });

            paymentMethodSelect.addEventListener('change', (e) => {
                const isCash = e.target.value === 'manual_cash';
                cashPaymentFields.classList.toggle('hidden', !isCash);
                qrisPaymentField.classList.toggle('hidden', isCash);
                amountGivenInput.required = isCash;
            });

            manualOrderForm.addEventListener('submit', (e) => {
                if (Object.keys(orderItems).length === 0) {
                    e.preventDefault();
                    showMessage("Pilih setidaknya satu item menu.", true);
                    return;
                }
                if (orderTypeSelect.value === 'dine-in' && !tableNumberInput.value) {
                    e.preventDefault();
                    showMessage("Silakan pilih nomor meja untuk pesanan Dine In.", true);
                    return;
                }

                if (paymentMethodSelect.value === 'manual_cash') {
                    const total = parseFloat(totalAmountHiddenInput.value);
                    const amountGiven = parseFloat(amountGivenInput.value.replace(/\D/g, '')) || 0;
                    if (amountGiven < total) {
                        e.preventDefault();
                        showMessage("Uang yang diberikan kurang dari total pesanan.", true);
                        return;
                    }
                }

                itemsJsonInput.value = JSON.stringify(Object.values(orderItems));
            });

            // --- Inisialisasi ---
            updateOrderSummary();
            orderTypeSelect.dispatchEvent(new Event('change'));
            paymentMethodSelect.dispatchEvent(new Event('change'));
        });
    </script>
</body>

</html>