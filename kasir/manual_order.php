<?php
// File: manual_order.php
// Halaman untuk membuat pesanan manual dengan fitur add-on

// Keamanan & Manajemen Sesi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../db_connect.php';

// Cek apakah user sudah login dan memiliki peran sebagai kasir
if (!isset($_SESSION['kasir']) || $_SESSION['kasir']['role'] !== 'cashier') {
    header("Location: login_kasir.php?error=Anda harus login sebagai kasir untuk mengakses halaman ini.");
    exit();
}

$kasir_id = $_SESSION['kasir']['id'];
$kasir_name = htmlspecialchars($_SESSION['kasir']['name']);

// =========================================================================
//  LOGIC BAGIAN A: Mengambil data dari database
// =========================================================================

// 1. Mengambil semua item menu yang tersedia
$menu_items_raw = [];
$sql_menu = "SELECT id, name, price, category FROM menu WHERE is_available = 1 ORDER BY category, name";
$result_menu = $conn->query($sql_menu);
if ($result_menu) {
    while ($row_menu = $result_menu->fetch_assoc()) {
        $menu_items_raw[$row_menu['id']] = $row_menu;
    }
}

// 2. Mengambil semua data add-on dan mengelompokkannya
$addons_data = [];
$sql_addons = "
    SELECT 
        ma.menu_id, 
        ag.id as group_id, 
        ag.name as group_name, 
        ao.id as option_id, 
        ao.name as option_name, 
        ao.price as option_price
    FROM menu_addons ma
    JOIN addon_groups ag ON ma.addon_group_id = ag.id
    JOIN addon_options ao ON ag.id = ao.addon_group_id
    ORDER BY ma.menu_id, ag.id, ao.id
";
$result_addons = $conn->query($sql_addons);
if ($result_addons) {
    while ($row = $result_addons->fetch_assoc()) {
        // Inisialisasi jika belum ada
        if (!isset($addons_data[$row['menu_id']])) {
            $addons_data[$row['menu_id']] = [];
        }
        if (!isset($addons_data[$row['menu_id']][$row['group_id']])) {
            $addons_data[$row['menu_id']][$row['group_id']] = [
                'name' => $row['group_name'],
                'options' => []
            ];
        }
        // Tambahkan opsi ke grup
        $addons_data[$row['menu_id']][$row['group_id']]['options'][] = [
            'id' => $row['option_id'],
            'name' => $row['option_name'],
            'price' => $row['option_price']
        ];
    }
}

// 3. Menggabungkan data menu dengan data add-on
$menu_items_with_addons = [];
foreach ($menu_items_raw as $id => $item) {
    $item['addons'] = isset($addons_data[$id]) ? array_values($addons_data[$id]) : [];
    $menu_items_with_addons[$id] = $item;
}

// 4. Mengelompokkan item menu berdasarkan kategori untuk ditampilkan
$categorized_menu_items = [];
foreach ($menu_items_with_addons as $item) {
    $category = $item['category'];
    if (!isset($categorized_menu_items[$category])) {
        $categorized_menu_items[$category] = [];
    }
    $categorized_menu_items[$category][] = $item;
}


// =========================================================================
//  LOGIC BAGIAN B: Menangani Aksi POST
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create_manual_order') {
        $order_type = $_POST['order_type'];
        $customer_name = !empty($_POST['customer_name']) ? trim($_POST['customer_name']) : 'Guest';
        $table_number = !empty($_POST['table_number']) ? (int)$_POST['table_number'] : NULL;
        $items = json_decode($_POST['items_json'], true);
        $total_amount = (float)$_POST['total_amount'];
        $amount_given = (float)str_replace('.', '', $_POST['amount_given']);
        $change_amount = $amount_given - $total_amount;

        $subtotal = 0;
        foreach ($items as $item_data) {
            $item_total_price = $item_data['base_price'];
            if (isset($item_data['selected_addons']) && is_array($item_data['selected_addons'])) {
                foreach ($item_data['selected_addons'] as $addon) {
                    $item_total_price += $addon['price'];
                }
            }
            $subtotal += $item_total_price * $item_data['quantity'];
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
            } else {
                $sql_insert_table = "INSERT INTO tables (table_number) VALUES (?)";
                $stmt_insert_table = $conn->prepare($sql_insert_table);
                $stmt_insert_table->bind_param("i", $table_number);
                $stmt_insert_table->execute();
                $table_id = $conn->insert_id;
                $stmt_insert_table->close();
            }
            $stmt_get_table_id->close();
        }

        $status = 'completed';
        $payment_method = 'manual_cash';
        $user_id = NULL;

        $conn->begin_transaction();
        try {
            $sql_insert_order = "INSERT INTO orders (user_id, cashier_id, table_id, order_type, customer_name, subtotal, tax, total_amount, payment_method, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert_order = $conn->prepare($sql_insert_order);
            $stmt_insert_order->bind_param("iisssdddss", $user_id, $kasir_id, $table_id, $order_type, $customer_name, $subtotal, $tax, $total_amount, $payment_method, $status);
            $stmt_insert_order->execute();
            $order_id = $conn->insert_id;

            // Perubahan di sini untuk memasukkan order_items
            $sql_insert_item = "INSERT INTO order_items (order_id, menu_id, quantity, price_per_item, selected_addons, total_price) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_insert_item = $conn->prepare($sql_insert_item);

            foreach ($items as $item_data) {
                $menu_id = $item_data['id'];
                $quantity = $item_data['quantity'];
                $price_per_item = $item_data['base_price'];
                $selected_addons_json = isset($item_data['selected_addons']) ? json_encode($item_data['selected_addons']) : NULL;

                $total_addon_price = 0;
                if (isset($item_data['selected_addons']) && is_array($item_data['selected_addons'])) {
                    foreach ($item_data['selected_addons'] as $addon) {
                        $total_addon_price += $addon['price'];
                    }
                }
                $total_price_per_item_with_addons = $price_per_item + $total_addon_price;
                $total_price_for_quantity = $total_price_per_item_with_addons * $quantity;

                $stmt_insert_item->bind_param("iiidsd", $order_id, $menu_id, $quantity, $price_per_item, $selected_addons_json, $total_price_for_quantity);
                $stmt_insert_item->execute();
            }

            $sql_insert_trans = "INSERT INTO transactions (order_id, total_paid, amount_given, change_amount, payment_method) VALUES (?, ?, ?, ?, ?)";
            $stmt_insert_trans = $conn->prepare($sql_insert_trans);
            $stmt_insert_trans->bind_param("iddds", $order_id, $total_amount, $amount_given, $change_amount, $payment_method);
            $stmt_insert_trans->execute();

            $conn->commit();
            header("Location: index.php?message=Pesanan+manual+berhasil+dibuat");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            // Mungkin tambahkan logging error di sini
            header("Location: manual_order.php?error=Gagal+membuat+pesanan");
            exit();
        }
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
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        /* Addon Modal Styles */
        #addon-modal {
            transition: opacity 0.3s ease;
        }

        .toast-notification {
            transition: transform 0.3s ease-in-out;
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
                    <a href="index.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">Daftar Pesanan</a>
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
                                                <div class="menu-card bg-white p-4 rounded-lg shadow-sm border border-gray-200 cursor-pointer group" data-id="<?= $item['id'] ?>">
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
                                    <label for="customer-name" class="block text-sm font-medium text-gray-700 mb-1">Nama Pemesan</label>
                                    <input type="text" name="customer_name" id="customer-name" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Contoh: Budi" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Pesanan</label>
                                    <select name="order_type" id="order-type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="dine-in">Dine In</option>
                                        <option value="take-away">Take Away</option>
                                    </select>
                                </div>
                                <div id="table-number-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Meja</label>
                                    <input type="number" name="table_number" id="table-number" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Contoh: 12" required>
                                </div>
                            </div>
                            <div id="order-summary-list" class="flex-grow space-y-2 p-4 border-t border-b bg-gray-50 min-h-[150px] max-h-64 overflow-y-auto custom-scrollbar">
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
                                <h3 class="font-bold text-lg mb-2">Pembayaran Tunai</h3>
                                <div id="cash-payment-fields" class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">Uang Diberikan (Rp)</label>
                                    <input type="text" name="amount_given" id="amount-given" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-lg" required placeholder="0">
                                    <div class="text-lg font-bold flex justify-between"><span>Kembalian:</span><span id="change-amount">Rp 0</span></div>
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

    <!-- Addon Modal -->
    <div id="addon-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden pointer-events-none opacity-0">
        <div id="addon-modal-content" class="bg-white rounded-xl shadow-2xl w-full max-w-md m-4 transform scale-95 transition-transform duration-300">
            <div class="p-6 border-b">
                <h3 id="addon-modal-title" class="text-2xl font-bold text-gray-800">Pilih Add-on</h3>
            </div>
            <form id="addon-form">
                <div id="addon-modal-body" class="p-6 max-h-[60vh] overflow-y-auto space-y-6 custom-scrollbar">
                    <!-- Addon groups will be inserted here by JavaScript -->
                </div>
                <div class="p-6 bg-gray-50 rounded-b-xl flex justify-end items-center space-x-4">
                    <span id="addon-total-price" class="text-lg font-bold text-gray-800"></span>
                    <button type="button" id="cancel-addon" class="px-6 py-2 rounded-lg text-gray-700 bg-gray-200 hover:bg-gray-300 font-semibold">Batal</button>
                    <button type="submit" id="confirm-addon" class="px-6 py-2 rounded-lg text-white bg-blue-600 hover:bg-blue-700 font-semibold">Tambahkan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Data dari PHP
        const menuData = <?= json_encode($menu_items_with_addons); ?>;

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
            const processButton = document.getElementById('process-manual-payment');
            const customerNameInput = document.getElementById('customer-name');

            // --- Addon Modal Elements ---
            const addonModal = document.getElementById('addon-modal');
            const addonModalContent = document.getElementById('addon-modal-content');
            const addonModalTitle = document.getElementById('addon-modal-title');
            const addonModalBody = document.getElementById('addon-modal-body');
            const addonForm = document.getElementById('addon-form');
            const addonTotalPriceSpan = document.getElementById('addon-total-price');
            const cancelAddonBtn = document.getElementById('cancel-addon');

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

            // --- State ---
            let orderItems = {}; // { uniqueId: {id, name, base_price, quantity, selected_addons, display_name} }
            let currentMenuItemForAddon = null;
            const TAX_RATE = 0.11;

            // --- Fungsi Utility ---
            const formatRupiah = (angka) => new Intl.NumberFormat('id-ID').format(Math.round(angka));

            const showMessage = (message, isError = false) => {
                const toast = document.createElement('div');
                toast.className = `fixed top-5 right-5 text-white px-6 py-3 rounded-lg shadow-xl z-50 toast-notification transform translate-x-full ${isError ? 'bg-red-500' : 'bg-green-600'}`;
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => {
                    toast.style.transform = 'translateX(0)';
                }, 10);
                setTimeout(() => {
                    toast.style.transform = 'translateX(calc(100% + 1.25rem))';
                    toast.addEventListener('transitionend', () => toast.remove());
                }, 3000);
            };

            // --- Fungsi Addon Modal ---
            const showAddonModal = (menuItem) => {
                currentMenuItemForAddon = menuItem;
                addonModalTitle.textContent = `Pilih Add-on untuk ${menuItem.name}`;
                addonModalBody.innerHTML = '';

                menuItem.addons.forEach((group, groupIndex) => {
                    const groupContainer = document.createElement('div');
                    groupContainer.innerHTML = `<h4 class="font-bold text-gray-700 mb-2">${group.name}</h4>`;
                    const optionsGrid = document.createElement('div');
                    optionsGrid.className = 'grid grid-cols-2 gap-2';

                    group.options.forEach((option, optionIndex) => {
                        const optionId = `addon-${groupIndex}-${optionIndex}`;
                        const optionLabel = document.createElement('label');
                        optionLabel.htmlFor = optionId;
                        optionLabel.className = 'flex items-center p-3 border rounded-lg cursor-pointer transition-all duration-200 has-[:checked]:bg-blue-50 has-[:checked]:border-blue-500 has-[:checked]:ring-2 has-[:checked]:ring-blue-200';
                        optionLabel.innerHTML = `
                            <input type="radio" id="${optionId}" name="addon-group-${groupIndex}" value="${option.id}" class="hidden" data-name="${option.name}" data-price="${option.price}" required ${optionIndex === 0 ? 'checked' : ''}>
                            <div class="flex-1">
                                <span class="font-medium text-gray-800">${option.name}</span>
                                ${option.price > 0 ? `<p class="text-sm text-gray-500">+Rp ${formatRupiah(option.price)}</p>` : ''}
                            </div>
                        `;
                        optionsGrid.appendChild(optionLabel);
                    });
                    groupContainer.appendChild(optionsGrid);
                    addonModalBody.appendChild(groupContainer);
                });

                addonModal.classList.remove('hidden', 'pointer-events-none', 'opacity-0');
                addonModalContent.classList.remove('scale-95');
                updateAddonTotalPrice();
            };

            const closeAddonModal = () => {
                addonModal.classList.add('opacity-0');
                addonModalContent.classList.add('scale-95');
                setTimeout(() => {
                    addonModal.classList.add('hidden', 'pointer-events-none');
                }, 300);
            };

            const updateAddonTotalPrice = () => {
                let basePrice = parseFloat(currentMenuItemForAddon.price);
                let addonsPrice = 0;
                const selectedOptions = addonForm.querySelectorAll('input[type="radio"]:checked');
                selectedOptions.forEach(input => {
                    addonsPrice += parseFloat(input.dataset.price) || 0;
                });
                const total = basePrice + addonsPrice;
                addonTotalPriceSpan.textContent = `Total: Rp ${formatRupiah(total)}`;
            };

            addonModalBody.addEventListener('change', updateAddonTotalPrice);
            cancelAddonBtn.addEventListener('click', closeAddonModal);

            // --- Fungsi Manajemen Pesanan ---
            const updateOrderSummary = () => {
                let subtotal = 0;
                const hasItems = Object.keys(orderItems).length > 0;
                emptyOrderMessage.classList.toggle('hidden', hasItems);
                processButton.disabled = !hasItems;
                orderSummaryList.querySelectorAll('.summary-item').forEach(el => el.remove());

                for (const key in orderItems) {
                    const item = orderItems[key];
                    let itemPrice = parseFloat(item.base_price);

                    let addonHtml = '';
                    if (item.selected_addons && item.selected_addons.length > 0) {
                        const addonNames = item.selected_addons.map(a => a.option_name || 'Pilihan Tdk Valid').join(', ');
                        addonHtml = `<p class="text-xs text-blue-600 truncate">${addonNames}</p>`;
                        item.selected_addons.forEach(addon => {
                            itemPrice += parseFloat(addon.price) || 0;
                        });
                    }

                    const itemElement = document.createElement('div');
                    itemElement.className = 'summary-item flex justify-between items-center bg-white p-3 rounded-lg shadow-sm summary-item-enter';
                    itemElement.innerHTML = `
                        <div class="flex-grow pr-2 overflow-hidden">
                            <p class="font-medium text-gray-800 text-sm truncate">${item.name}</p>
                            ${addonHtml}
                            <p class="text-xs text-gray-500">Rp ${formatRupiah(itemPrice)} / item</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button type="button" class="quantity-button flex-shrink-0 bg-red-100 text-red-600 w-6 h-6 rounded-full flex items-center justify-center hover:bg-red-500 hover:text-white transition-colors" data-key="${key}" data-action="subtract"><i class="fas fa-minus text-xs"></i></button>
                            <span class="font-bold w-6 text-center">${item.quantity}</span>
                            <button type="button" class="quantity-button flex-shrink-0 bg-green-100 text-green-600 w-6 h-6 rounded-full flex items-center justify-center hover:bg-green-500 hover:text-white transition-colors" data-key="${key}" data-action="add"><i class="fas fa-plus text-xs"></i></button>
                        </div>`;
                    orderSummaryList.appendChild(itemElement);
                    subtotal += item.quantity * itemPrice;
                }

                const tax = subtotal * TAX_RATE;
                const total = subtotal + tax;
                manualSubtotalSpan.textContent = `Rp ${formatRupiah(subtotal)}`;
                manualTaxSpan.textContent = `Rp ${formatRupiah(tax)}`;
                manualTotalSpan.textContent = `Rp ${formatRupiah(total)}`;
                totalAmountHiddenInput.value = total;
                updateChange();
            };

            const addItemToOrder = (menuItem, selectedAddons = []) => {
                let uniqueId = `menu-${menuItem.id}`;
                if (selectedAddons.length > 0) {
                    uniqueId += '-' + selectedAddons.map(a => a.id).join('-');
                }

                if (orderItems[uniqueId]) {
                    orderItems[uniqueId].quantity++;
                } else {
                    orderItems[uniqueId] = {
                        id: menuItem.id,
                        name: menuItem.name,
                        base_price: parseFloat(menuItem.price),
                        quantity: 1,
                        selected_addons: selectedAddons,
                    };
                }
                updateOrderSummary();
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
                const menuId = card.dataset.id;
                const menuItem = menuData[menuId];

                if (menuItem.addons && menuItem.addons.length > 0) {
                    showAddonModal(menuItem);
                } else {
                    addItemToOrder(menuItem);
                }
            });

            addonForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const selectedOptions = [];
                const checkedInputs = addonForm.querySelectorAll('input[type="radio"]:checked');

                checkedInputs.forEach(input => {
                    const groupName = input.closest('.grid').previousElementSibling.textContent;
                    selectedOptions.push({
                        id: input.value,
                        group_name: groupName,
                        option_name: input.dataset.name,
                        price: parseFloat(input.dataset.price)
                    });
                });

                addItemToOrder(currentMenuItemForAddon, selectedOptions);
                closeAddonModal();
            });

            orderSummaryList.addEventListener('click', (e) => {
                const button = e.target.closest('.quantity-button');
                if (!button) return;
                const {
                    key,
                    action
                } = button.dataset;
                if (!orderItems[key]) return;

                if (action === 'add') {
                    orderItems[key].quantity++;
                } else if (action === 'subtract') {
                    orderItems[key].quantity--;
                    if (orderItems[key].quantity <= 0) delete orderItems[key];
                }
                updateOrderSummary();
            });

            amountGivenInput.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                e.target.value = value ? formatRupiah(value) : '';
                updateChange();
            });

            orderTypeSelect.addEventListener('change', (e) => {
                const isDineIn = e.target.value === 'dine-in';
                tableNumberGroup.style.display = isDineIn ? 'block' : 'none';
                tableNumberInput.required = isDineIn;
                if (!isDineIn) tableNumberInput.value = '';
            });

            manualOrderForm.addEventListener('submit', (e) => {
                if (Object.keys(orderItems).length === 0) {
                    e.preventDefault();
                    showMessage("Pilih setidaknya satu item menu.", true);
                    return;
                }
                const total = parseFloat(totalAmountHiddenInput.value);
                const amountGiven = parseFloat(amountGivenInput.value.replace(/\D/g, '')) || 0;
                if (amountGiven < total) {
                    e.preventDefault();
                    showMessage("Uang yang diberikan kurang dari total pesanan.", true);
                    return;
                }
                itemsJsonInput.value = JSON.stringify(Object.values(orderItems));
            });

            // --- Inisialisasi ---
            updateOrderSummary();
            orderTypeSelect.dispatchEvent(new Event('change'));
        });
    </script>
</body>

</html>