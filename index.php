<?php
// File: index.php
// Halaman untuk pelanggan (dapat diakses melalui QR code di meja)

require_once 'db_connect.php';

// Memastikan sesi hanya dimulai sekali
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Inisialisasi keranjang jika belum ada
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle GET request untuk data keranjang (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['is_ajax_get_cart'])) {
    header('Content-Type: application/json');

    $cart = $_SESSION['cart'] ?? [];
    $subtotal = array_sum(array_map(function ($item) {
        return $item['price'] * $item['quantity'];
    }, $cart));
    $ppn = $subtotal * 0.11; // PPN 11%
    $total = $subtotal + $ppn;
    $cart_count = array_sum(array_column($cart, 'quantity'));

    echo json_encode([
        'cart_items' => array_values($cart),
        'cart_count' => $cart_count,
        'subtotal' => $subtotal,
        'ppn' => $ppn,
        'total' => $total,
    ]);
    exit();
}


// Handle Aksi Keranjang via POST (untuk AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['is_ajax'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Aksi tidak valid.'];

    $action = $_POST['action'] ?? '';
    $menu_id = isset($_POST['menu_id']) ? (int)$_POST['menu_id'] : 0;

    switch ($action) {
        case 'add_to_cart':
            $sql = "SELECT * FROM menu WHERE id = ? AND is_available = TRUE";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $menu_id);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();

            if ($item) {
                $current_stock = $item['stock'];
                $cart_quantity = isset($_SESSION['cart'][$menu_id]['quantity']) ? $_SESSION['cart'][$menu_id]['quantity'] : 0;

                if ($current_stock > $cart_quantity) {
                    if (isset($_SESSION['cart'][$menu_id])) {
                        $_SESSION['cart'][$menu_id]['quantity']++;
                    } else {
                        $_SESSION['cart'][$menu_id] = [
                            'id' => $item['id'],
                            'name' => $item['name'],
                            'price' => $item['price'],
                            'quantity' => 1,
                            'image' => $item['image_url']
                        ];
                    }
                    $response['success'] = true;
                    $response['message'] = "{$item['name']} ditambahkan ke keranjang!";
                } else {
                    $response['message'] = "Maaf, stok {$item['name']} tidak mencukupi.";
                }
            } else {
                $response['message'] = "Menu tidak ditemukan atau tidak tersedia.";
            }
            break;

        case 'update_quantity':
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
            if (isset($_SESSION['cart'][$menu_id])) {
                if ($quantity > 0) {
                    // Cek stok sebelum update
                    $sql = "SELECT stock FROM menu WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $menu_id);
                    $stmt->execute();
                    $menu_item = $stmt->get_result()->fetch_assoc();

                    if ($menu_item && $quantity <= $menu_item['stock']) {
                        $_SESSION['cart'][$menu_id]['quantity'] = $quantity;
                        $response['success'] = true;
                        $response['message'] = "Jumlah item diperbarui.";
                    } else {
                        $response['message'] = "Stok tidak mencukupi.";
                    }
                } else {
                    unset($_SESSION['cart'][$menu_id]);
                    $response['success'] = true;
                    $response['message'] = "Item dihapus dari keranjang.";
                }
            }
            break;

        case 'remove_from_cart':
            if (isset($_SESSION['cart'][$menu_id])) {
                $item_name = $_SESSION['cart'][$menu_id]['name'];
                unset($_SESSION['cart'][$menu_id]);
                $response['success'] = true;
                $response['message'] = "{$item_name} berhasil dihapus.";
            }
            break;
    }

    // Perbarui data keranjang dalam respons
    $cart = $_SESSION['cart'];
    $subtotal = array_sum(array_map(function ($item) {
        return $item['price'] * $item['quantity'];
    }, $cart));
    $ppn = $subtotal * 0.11; // PPN 11%
    $total = $subtotal + $ppn;

    $response['cart_items'] = array_values($cart);
    $response['cart_count'] = array_sum(array_column($cart, 'quantity'));
    $response['subtotal'] = $subtotal;
    $response['ppn'] = $ppn;
    $response['total'] = $total;

    echo json_encode($response);
    exit();
}


// Handle checkout (tetap dengan redirect karena akan pindah ke halaman lain)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    $table_id = isset($_POST['table_id']) ? (int)$_POST['table_id'] : 1;
    if (empty($_SESSION['cart'])) {
        header("Location: index.php?table=$table_id&error=Keranjang+kosong");
        exit();
    }

    $payment_method = $_POST['payment_method'];
    $subtotal = array_sum(array_map(function ($item) {
        return $item['price'] * $item['quantity'];
    }, $_SESSION['cart']));
    $ppn = $subtotal * 0.11; // PPN 11%
    $total = $subtotal + $ppn;
    $order_type = 'dine-in';
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    $conn->begin_transaction();
    try {
        // Simpan pesanan
        $sql = "INSERT INTO orders (table_id, user_id, order_type, status, subtotal, tax, total_amount, payment_method) VALUES (?, ?, ?, 'pending_payment', ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisddds", $table_id, $user_id, $order_type, $subtotal, $ppn, $total, $payment_method);
        $stmt->execute();
        $order_id = $stmt->insert_id;

        // Simpan item pesanan dan kurangi stok
        foreach ($_SESSION['cart'] as $item) {
            $sql = "INSERT INTO order_items (order_id, menu_id, quantity, price) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiid", $order_id, $item['id'], $item['quantity'], $item['price']);
            $stmt->execute();

            $sql_update_stock = "UPDATE menu SET stock = stock - ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update_stock);
            $stmt_update->bind_param("ii", $item['quantity'], $item['id']);
            $stmt_update->execute();
        }

        $conn->commit();

        $_SESSION['cart'] = [];
        header("Location: index.php?table=$table_id&payment_method=$payment_method&total_amount=$total");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        // Sebaiknya log error ini
        header("Location: index.php?table=$table_id&error=Gagal+membuat+pesanan");
        exit();
    }
}

// Data untuk render halaman
$table_id = isset($_GET['table']) ? (int)$_GET['table'] : 1;
$menu_items = [];
$sql = "SELECT * FROM menu WHERE is_available = TRUE ORDER BY category, name";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $menu_items[] = $row;
}

$categories = [];
foreach ($menu_items as $item) {
    $categories[$item['category']][] = $item;
}
$cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Cafe - Meja <?= htmlspecialchars($table_id) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        .toast-notif {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translate(-50%, -150%);
            transition: transform 0.3s ease-in-out;
            z-index: 1000;
        }

        .toast-notif.show {
            transform: translate(-50%, 0);
        }

        #cart-drawer {
            transition: transform 0.3s ease-in-out;
        }

        #cart-drawer.translate-x-full {
            transform: translateX(100%);
        }

        #cart-backdrop {
            transition: opacity 0.3s ease-in-out;
        }

        .category-nav-item.active {
            color: #2563EB;
            /* blue-600 */
            border-bottom-color: #2563EB;
        }
    </style>
</head>

<body class="bg-gray-50">

    <!-- Navbar -->
    <nav class="bg-white shadow-sm py-3 sticky top-0 z-40">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <h1 class="text-xl md:text-2xl font-extrabold text-gray-800">Cafe Bahagia</h1>
            <div class="flex items-center space-x-4">
                <span class="bg-gray-100 text-gray-800 px-3 py-1.5 rounded-full font-semibold text-sm">Meja: <?= htmlspecialchars($table_id) ?></span>
                <button id="loginButton" class="bg-blue-500 text-white px-4 py-2 rounded-full font-medium hover:bg-blue-600 transition-colors text-sm">Login</button>
            </div>
        </div>
    </nav>

    <!-- Category Nav -->
    <div id="category-nav" class="bg-white sticky top-[60px] z-40 shadow-sm">
        <div class="container mx-auto px-4">
            <div class="flex space-x-4 md:space-x-8 overflow-x-auto whitespace-nowrap -mb-px">
                <?php foreach ($categories as $category => $items) : ?>
                    <a href="#category-<?= strtolower($category) ?>" class="category-nav-item py-3 px-1 text-sm md:text-base font-semibold text-gray-500 hover:text-blue-600 border-b-2 border-transparent transition-colors duration-200">
                        <?= htmlspecialchars(ucfirst($category)) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <div class="space-y-12">
            <?php foreach ($categories as $category => $items) : ?>
                <section id="category-<?= strtolower($category) ?>" class="scroll-mt-32">
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-800 capitalize mb-6"><?= htmlspecialchars($category) ?></h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <?php foreach ($items as $item) : ?>
                            <div class="bg-white rounded-2xl shadow-md overflow-hidden flex flex-col group">
                                <div class="h-48 overflow-hidden">
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover transform transition-transform duration-300 group-hover:scale-110">
                                </div>
                                <div class="p-5 flex-grow flex flex-col">
                                    <h3 class="text-lg font-bold text-gray-800 mb-1"><?= htmlspecialchars($item['name']) ?></h3>
                                    <p class="text-gray-500 text-sm mb-4 flex-grow"><?= htmlspecialchars($item['description']) ?></p>
                                    <div class="flex items-center justify-between mt-auto">
                                        <span class="text-gray-900 font-extrabold text-lg">Rp<?= number_format($item['price'], 0, ',', '.') ?></span>
                                        <form class="add-to-cart-form">
                                            <input type="hidden" name="action" value="add_to_cart">
                                            <input type="hidden" name="menu_id" value="<?= $item['id'] ?>">
                                            <input type="hidden" name="is_ajax" value="1">
                                            <button type="submit" <?= $item['stock'] == 0 ? 'disabled' : '' ?> class="bg-blue-600 text-white px-5 py-2 rounded-full font-semibold text-sm hover:bg-blue-700 transition-all duration-200 disabled:bg-gray-300 disabled:cursor-not-allowed transform hover:scale-105">Pesan</button>
                                        </form>
                                    </div>
                                    <?php if ($item['stock'] <= 5 && $item['stock'] > 0) : ?>
                                        <p class="text-yellow-600 text-xs font-semibold mt-2">Stok terbatas!</p>
                                    <?php elseif ($item['stock'] == 0) : ?>
                                        <p class="text-red-500 text-xs font-semibold mt-2">Stok habis</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Cart Floating Action Button -->
    <button id="cartFab" class="fixed bottom-6 right-6 bg-green-500 text-white rounded-full shadow-lg w-16 h-16 flex items-center justify-center z-50 transform hover:scale-110 transition-transform">
        <i class="fas fa-shopping-cart text-2xl"></i>
        <span id="cartBadgeFab" class="absolute -top-1 -right-1 bg-red-600 text-white text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center border-2 border-green-500"><?= $cart_count ?></span>
    </button>

    <!-- Cart Drawer -->
    <div id="cart-backdrop" class="fixed inset-0 bg-black bg-opacity-60 hidden z-50"></div>
    <div id="cart-drawer" class="fixed top-0 right-0 h-full w-full max-w-md bg-gray-100 shadow-2xl z-50 transform translate-x-full flex flex-col">
        <div class="flex justify-between items-center p-5 border-b bg-white">
            <h2 class="text-xl font-bold text-gray-800">Keranjang Anda</h2>
            <button id="closeCartDrawer" class="text-gray-500 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
        </div>
        <div id="cart-content" class="flex-grow p-5 overflow-y-auto space-y-4">
            <!-- Cart items will be injected here -->
        </div>
        <div id="empty-cart-message" class="flex-grow flex flex-col items-center justify-center text-gray-500 hidden">
            <i class="fas fa-shopping-basket text-6xl text-gray-300 mb-4"></i>
            <p class="text-lg">Keranjang Anda kosong.</p>
        </div>
        <div id="cart-summary" class="p-5 border-t bg-white shadow-inner hidden">
            <div class="space-y-2 mb-4">
                <div class="flex justify-between font-medium text-gray-600"><span>Subtotal</span><span id="cart-subtotal"></span></div>
                <div class="flex justify-between font-medium text-gray-600"><span>PPN (11%)</span><span id="cart-ppn"></span></div>
                <div class="flex justify-between font-bold text-lg text-gray-900"><span>Total</span><span id="cart-total"></span></div>
            </div>
            <form id="checkoutForm" method="POST">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="table_id" value="<?= $table_id ?>">
                <h3 class="text-md font-semibold text-gray-800 mb-3">Metode Pembayaran</h3>
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <label class="p-3 rounded-lg border-2 cursor-pointer has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 transition-all"><input type="radio" name="payment_method" value="cash" class="sr-only" checked><span class="block text-center font-medium text-sm">Cash</span></label>
                    <label class="p-3 rounded-lg border-2 cursor-pointer has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 transition-all"><input type="radio" name="payment_method" value="QRIS" class="sr-only"><span class="block text-center font-medium text-sm">QRIS</span></label>
                    <label class="p-3 rounded-lg border-2 cursor-pointer has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 transition-all"><input type="radio" name="payment_method" value="transfer" class="sr-only"><span class="block text-center font-medium text-sm">Transfer</span></label>
                    <label class="p-3 rounded-lg border-2 cursor-pointer has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 transition-all"><input type="radio" name="payment_method" value="virtual_account" class="sr-only"><span class="block text-center font-medium text-sm">Virtual Acct</span></label>
                </div>
                <button type="submit" class="w-full bg-green-600 text-white font-bold py-3.5 rounded-xl hover:bg-green-700 transition-colors">Bayar Sekarang</button>
            </form>
        </div>
    </div>

    <!-- Modals (Login, Payment Details) -->
    <div id="loginModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <!-- Login modal content -->
    </div>
    <div id="paymentDetailsModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6 m-4 relative">
            <button id="closePaymentModal" class="absolute top-3 right-3 text-gray-500 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
            <h2 class="text-xl font-bold mb-4 text-gray-800">Instruksi Pembayaran</h2>
            <div id="payment-content"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const cartFab = document.getElementById('cartFab');
            const cartDrawer = document.getElementById('cart-drawer');
            const cartBackdrop = document.getElementById('cart-backdrop');
            const closeCartDrawer = document.getElementById('closeCartDrawer');
            const cartContent = document.getElementById('cart-content');
            const cartBadgeFab = document.getElementById('cartBadgeFab');
            const cartSummary = document.getElementById('cart-summary');
            const emptyCartMessage = document.getElementById('empty-cart-message');
            const paymentDetailsModal = document.getElementById('paymentDetailsModal');
            const closePaymentModal = document.getElementById('closePaymentModal');

            const toggleCartDrawer = (show) => {
                cartDrawer.classList.toggle('translate-x-full', !show);
                cartBackdrop.classList.toggle('hidden', !show);
            };

            cartFab.addEventListener('click', () => {
                updateCartData();
                toggleCartDrawer(true);
            });
            closeCartDrawer.addEventListener('click', () => toggleCartDrawer(false));
            cartBackdrop.addEventListener('click', () => toggleCartDrawer(false));

            closePaymentModal.addEventListener('click', () => {
                paymentDetailsModal.classList.add('hidden');
                // Clean up URL after closing payment modal
                const url = new URL(window.location.href);
                url.searchParams.delete('payment_method');
                url.searchParams.delete('total_amount');
                window.history.replaceState({}, '', url);
            });

            // Function to format currency
            const formatCurrency = (amount) => `Rp${Number(amount).toLocaleString('id-ID')}`;

            // Handle adding items to cart
            document.querySelectorAll('.add-to-cart-form').forEach(form => {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const formData = new FormData(form);
                    const response = await sendCartAction(formData);
                    if (response) {
                        showToast(response.message, response.success);
                        if (response.success) updateCartUI(response);
                    }
                });
            });

            // Handle cart actions (update quantity, remove) via event delegation
            cartContent.addEventListener('submit', async (e) => {
                if (e.target.matches('.update-cart-form')) {
                    e.preventDefault();
                    const formData = new FormData(e.target);
                    const response = await sendCartAction(formData);
                    if (response && response.success) {
                        updateCartUI(response);
                        // No toast for simple quantity updates to avoid spam
                    } else if (response) {
                        showToast(response.message, false);
                    }
                }
            });

            cartContent.addEventListener('click', async (e) => {
                if (e.target.matches('.quantity-btn')) {
                    e.preventDefault();
                    const form = e.target.closest('form');
                    const quantityInput = form.querySelector('input[name="quantity"]');
                    let quantity = parseInt(quantityInput.value);
                    const change = parseInt(e.target.dataset.change);

                    quantity += change;
                    if (quantity < 0) quantity = 0; // Prevent negative numbers

                    quantityInput.value = quantity;

                    const formData = new FormData(form);
                    const response = await sendCartAction(formData);
                    if (response && response.success) {
                        updateCartUI(response);
                    } else if (response) {
                        showToast(response.message, false);
                    }
                }
            });

            async function sendCartAction(formData) {
                try {
                    const response = await fetch('index.php', {
                        method: 'POST',
                        body: formData
                    });
                    return await response.json();
                } catch (error) {
                    console.error('Error:', error);
                    showToast('Terjadi kesalahan jaringan.', false);
                    return null;
                }
            }

            async function updateCartData() {
                try {
                    const response = await fetch('index.php?is_ajax_get_cart=1');
                    const data = await response.json();
                    updateCartUI(data);
                } catch (error) {
                    console.error('Failed to fetch cart data:', error);
                }
            }

            function updateCartUI(data) {
                cartBadgeFab.textContent = data.cart_count;
                cartBadgeFab.classList.toggle('hidden', data.cart_count === 0);

                if (data.cart_count > 0) {
                    emptyCartMessage.classList.add('hidden');
                    cartSummary.classList.remove('hidden');

                    cartContent.innerHTML = data.cart_items.map(item => `
                    <div class="flex items-start justify-between bg-white p-3 rounded-lg shadow-sm">
                        <div class="flex items-start space-x-3">
                            <img src="${item.image}" alt="${item.name}" class="w-20 h-20 object-cover rounded-md">
                            <div>
                                <p class="font-bold text-gray-800 text-md">${item.name}</p>
                                <p class="text-sm text-gray-600">${formatCurrency(item.price)}</p>
                            </div>
                        </div>
                        <form class="update-cart-form flex flex-col items-end">
                            <div class="flex items-center rounded-full border bg-gray-50 overflow-hidden">
                                <button data-change="-1" class="quantity-btn px-2 py-1 text-lg font-bold text-gray-600 hover:bg-gray-200">-</button>
                                <input type="number" name="quantity" value="${item.quantity}" class="w-10 text-center font-semibold bg-transparent border-none focus:ring-0" readonly>
                                <button data-change="1" class="quantity-btn px-2 py-1 text-lg font-bold text-gray-600 hover:bg-gray-200">+</button>
                            </div>
                            <input type="hidden" name="action" value="update_quantity">
                            <input type="hidden" name="menu_id" value="${item.id}">
                             <input type="hidden" name="is_ajax" value="1">
                        </form>
                    </div>
                `).join('');

                    document.getElementById('cart-subtotal').textContent = formatCurrency(data.subtotal);
                    document.getElementById('cart-ppn').textContent = formatCurrency(data.ppn);
                    document.getElementById('cart-total').textContent = formatCurrency(data.total);
                } else {
                    cartContent.innerHTML = '';
                    emptyCartMessage.classList.remove('hidden');
                    cartSummary.classList.add('hidden');
                }
            }

            function showToast(message, isSuccess) {
                const existingToast = document.querySelector('.toast-notif');
                if (existingToast) existingToast.remove();

                const toast = document.createElement('div');
                toast.className = `toast-notif p-4 rounded-lg shadow-lg text-white font-semibold ${isSuccess ? 'bg-green-500' : 'bg-red-500'}`;
                toast.textContent = message;
                document.body.appendChild(toast);

                setTimeout(() => toast.classList.add('show'), 10);
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => document.body.removeChild(toast), 300);
                }, 2500);
            }

            // Check for payment details on page load
            const urlParams = new URLSearchParams(window.location.search);
            const paymentMethod = urlParams.get('payment_method');
            const totalAmount = urlParams.get('total_amount');
            if (paymentMethod && totalAmount) {
                displayPaymentDetails(paymentMethod, totalAmount);
            }
            if (urlParams.has('error')) {
                showToast(urlParams.get('error'), false);
            }

            function displayPaymentDetails(method, total) {
                const formattedTotal = formatCurrency(total);
                let contentHTML = '';
                switch (method) {
                    case 'cash':
                        contentHTML = `<p>Silakan ke kasir, sebutkan nomor meja <b>(Meja: ${<?= $table_id ?>})</b> dan bayar sejumlah <b>${formattedTotal}</b>.</p>`;
                        break;
                    case 'QRIS':
                        contentHTML = `<p>Scan QRIS di bawah ini dan bayar sejumlah <b>${formattedTotal}</b>.</p><img src="https://placehold.co/300x300/eee/000?text=QRIS" class="mx-auto my-4">`;
                        break;
                    case 'transfer':
                        contentHTML = `<p>Transfer sejumlah <b>${formattedTotal}</b> ke:<br>BCA: 123456789 (a/n Cafe Bahagia)</p>`;
                        break;
                    case 'virtual_account':
                        contentHTML = `<p>Bayar sejumlah <b>${formattedTotal}</b> ke VA berikut:<br>VA: 901234567890 (a/n Cafe Bahagia)</p>`;
                        break;
                }
                document.getElementById('payment-content').innerHTML = contentHTML;
                paymentDetailsModal.classList.remove('hidden');
            }

            // Sticky category nav active state handler
            const categoryNav = document.getElementById('category-nav');
            const navItems = categoryNav.querySelectorAll('.category-nav-item');
            const sections = document.querySelectorAll('section[id^="category-"]');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        navItems.forEach(nav => {
                            nav.classList.toggle('active', nav.getAttribute('href').substring(1) === entry.target.id);
                        });
                    }
                });
            }, {
                rootMargin: "-100px 0px -50% 0px",
                threshold: 0
            });
            sections.forEach(sec => observer.observe(sec));
        });
    </script>
</body>

</html>