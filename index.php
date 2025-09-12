<?php
// File: index.php
// Halaman untuk pelanggan (dapat diakses melalui QR code di meja)
// Koneksi ke database
//test --- IGNORE ---
require_once 'db_connect.php';

// Memastikan sesi hanya dimulai sekali
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Mendapatkan nomor meja dari parameter URL
$table_id = isset($_GET['table']) ? (int)$_GET['table'] : 1;

// Mengambil semua menu
$menu_items = [];
$sql = "SELECT * FROM menu WHERE is_available = TRUE ORDER BY category, name";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $menu_items[] = $row;
}

// Mengambil menu berdasarkan kategori
$categories = [
    'makanan' => [],
    'minuman' => [],
    'snack' => [],
    'other' => []
];
foreach ($menu_items as $item) {
    if (isset($categories[$item['category']])) {
        $categories[$item['category']][] = $item;
    }
}

// Inisialisasi keranjang
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle Aksi Keranjang via POST (untuk AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['is_ajax'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => '', 'cart_count' => 0, 'cart_items' => [], 'subtotal' => 0, 'ppn' => 0, 'total' => 0];

    if (isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
        $menu_id = (int)$_POST['menu_id'];
        $quantity = 1;

        $sql = "SELECT * FROM menu WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $menu_id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();

        if ($item && $item['stock'] > 0) {
            if (isset($_SESSION['cart'][$menu_id])) {
                $_SESSION['cart'][$menu_id]['quantity']++;
            } else {
                $_SESSION['cart'][$menu_id] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'quantity' => $quantity,
                    'image' => $item['image_url']
                ];
            }
            $response['success'] = true;
            $response['message'] = "{$item['name']} berhasil ditambahkan ke keranjang!";
        } else {
            $response['message'] = "Maaf, stok menu habis.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'remove_from_cart') {
        $menu_id = (int)$_POST['menu_id'];
        if (isset($_SESSION['cart'][$menu_id])) {
            $item_name = $_SESSION['cart'][$menu_id]['name'];
            unset($_SESSION['cart'][$menu_id]);
            $response['success'] = true;
            $response['message'] = "{$item_name} berhasil dihapus dari keranjang.";
        } else {
            $response['message'] = "Item tidak ditemukan di keranjang.";
        }
    }

    // Perbarui data keranjang dalam respons
    $response['cart_items'] = array_values($_SESSION['cart']);
    $response['cart_count'] = array_sum(array_column($_SESSION['cart'], 'quantity'));
    $subtotal = array_sum(array_map(function ($item) {
        return $item['price'] * $item['quantity'];
    }, $_SESSION['cart']));
    $ppn = $subtotal * 0.10;
    $total = $subtotal + $ppn;
    $response['subtotal'] = $subtotal;
    $response['ppn'] = $ppn;
    $response['total'] = $total;

    echo json_encode($response);
    exit();
}

// Menghitung total biaya untuk tampilan di keranjang (untuk halaman awal)
$subtotal = array_sum(array_map(function ($item) {
    return $item['price'] * $item['quantity'];
}, $_SESSION['cart']));
$ppn = $subtotal * 0.10;
$total = $subtotal + $ppn;
$cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));

// Handle checkout (tetap dengan redirect karena akan pindah ke halaman lain)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    $payment_method = $_POST['payment_method'];
    $subtotal = array_sum(array_map(function ($item) {
        return $item['price'] * $item['quantity'];
    }, $_SESSION['cart']));
    $ppn = $subtotal * 0.10;
    $total = $subtotal + $ppn;

    if (empty($_SESSION['cart'])) {
        header("Location: " . $_SERVER['REQUEST_URI'] . "&message=Keranjang+kosong");
        exit();
    }

    // Simpan pesanan ke database dengan status 'pending_payment'
    $order_type = 'dine-in';
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    $sql = "INSERT INTO orders (table_id, user_id, order_type, status, subtotal, tax, total_amount, payment_method) VALUES (?, ?, ?, 'pending_payment', ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisddds", $table_id, $user_id, $order_type, $subtotal, $ppn, $total, $payment_method);
    $stmt->execute();
    $order_id = $stmt->insert_id;

    foreach ($_SESSION['cart'] as $item) {
        $sql = "INSERT INTO order_items (order_id, menu_id, quantity, price) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiid", $order_id, $item['id'], $item['quantity'], $item['price']);
        $stmt->execute();
    }

    $_SESSION['cart'] = [];
    header("Location: index.php?table=$table_id&payment_method=$payment_method&total_amount=$total");
    exit();
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Cafe - Meja <?= htmlspecialchars($table_id ?? 'Tidak Diketahui') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }

        .toast-notif {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #10B981;
            color: white;
            padding: 1rem 2rem;
            border-radius: 9999px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
        }

        .toast-notif.show {
            opacity: 1;
            visibility: visible;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex flex-col">
    <!-- Navbar -->
    <nav class="bg-white shadow-md py-4 sticky top-0 z-50">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-gray-800">Cafe Bahagia</h1>
            <div class="flex items-center space-x-4">
                <?php if ($table_id) : ?>
                    <span class="bg-gray-200 text-gray-800 px-4 py-2 rounded-full font-medium">Meja: <?= htmlspecialchars($table_id) ?></span>
                <?php endif; ?>
                <button id="loginButton" class="bg-blue-500 text-white px-4 py-2 rounded-full font-medium hover:bg-blue-600 transition-colors">Login Member</button>
                <div class="relative">
                    <button id="cartButton" class="text-gray-600 hover:text-gray-900 transition-colors relative">
                        <i class="fas fa-shopping-cart text-2xl"></i>
                        <span id="cartBadge" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                            <?= $cart_count ?>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8 flex-grow">
        <?php if (isset($_GET['message'])) : ?>
            <div id="toastNotification" class="toast-notif">
                <p class="text-white font-semibold"><?= htmlspecialchars($_GET['message']) ?></p>
            </div>
        <?php endif; ?>

        <div class="space-y-10">
            <?php foreach ($categories as $category => $items) : ?>
                <section>
                    <h2 class="text-3xl font-bold text-gray-700 capitalize mb-6 mt-4"><?= htmlspecialchars($category) ?></h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                        <?php foreach ($items as $item) : ?>
                            <div class="bg-white rounded-xl shadow-lg overflow-hidden transform transition-transform hover:scale-105">
                                <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-48 object-cover">
                                <div class="p-6">
                                    <h3 class="text-xl font-semibold text-gray-800 mb-1"><?= htmlspecialchars($item['name']) ?></h3>
                                    <p class="text-gray-500 text-sm mb-3"><?= htmlspecialchars($item['description']) ?></p>
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-900 font-bold text-lg">Rp <?= number_format($item['price'], 0, ',', '.') ?></span>
                                        <form class="add-to-cart-form" method="POST">
                                            <input type="hidden" name="action" value="add_to_cart">
                                            <input type="hidden" name="menu_id" value="<?= $item['id'] ?>">
                                            <input type="hidden" name="is_ajax" value="1">
                                            <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-full font-medium hover:bg-green-600 transition-colors">Pesan</button>
                                        </form>
                                    </div>
                                    <?php if ($item['stock'] < 5) : ?>
                                        <p class="text-red-500 text-sm mt-2">Stok tinggal sedikit!</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Cart Modal -->
    <div id="cartModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 m-4 relative max-h-[90vh] overflow-y-auto">
            <button id="closeCartModal" class="absolute top-3 right-3 text-gray-500 hover:text-gray-800">
                <i class="fas fa-times text-2xl"></i>
            </button>
            <h2 class="text-2xl font-bold mb-6 text-gray-800">Keranjang Anda</h2>
            <div id="cart-content">
                <!-- Konten keranjang akan diisi oleh JavaScript -->
            </div>
            <div id="empty-cart-message" class="text-gray-500 text-center">Keranjang Anda kosong.</div>
            <div id="cart-summary" class="hidden">
                <div class="border-t pt-4 mt-6 space-y-2">
                    <div class="flex justify-between font-medium text-gray-700">
                        <span>Subtotal</span>
                        <span id="cart-subtotal"></span>
                    </div>
                    <div class="flex justify-between font-medium text-gray-700">
                        <span>PPN (10%)</span>
                        <span id="cart-ppn"></span>
                    </div>
                    <div class="flex justify-between font-bold text-lg text-gray-900">
                        <span>Total</span>
                        <span id="cart-total"></span>
                    </div>
                </div>

                <div class="mt-6 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800">Pilih Metode Pembayaran</h3>
                    <form id="checkoutForm" method="POST">
                        <input type="hidden" name="action" value="checkout">
                        <div class="grid grid-cols-2 gap-4">
                            <label class="p-4 rounded-lg border-2 cursor-pointer has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                                <input type="radio" name="payment_method" value="cash" class="sr-only" checked>
                                <span class="block text-center font-medium">Cash</span>
                                <span class="block text-center text-sm text-gray-500">Bayar di Kasir</span>
                            </label>
                            <label class="p-4 rounded-lg border-2 cursor-pointer has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                                <input type="radio" name="payment_method" value="transfer" class="sr-only">
                                <span class="block text-center font-medium">Transfer Bank</span>
                                <span class="block text-center text-sm text-gray-500">Via Rekening</span>
                            </label>
                            <label class="p-4 rounded-lg border-2 cursor-pointer has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                                <input type="radio" name="payment_method" value="virtual_account" class="sr-only">
                                <span class="block text-center font-medium">Virtual Account</span>
                                <span class="block text-center text-sm text-gray-500">VA Otomatis</span>
                            </label>
                            <label class="p-4 rounded-lg border-2 cursor-pointer has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                                <input type="radio" name="payment_method" value="QRIS" class="sr-only">
                                <span class="block text-center font-medium">QRIS</span>
                                <span class="block text-center text-sm text-gray-500">Scan Kode</span>
                            </label>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition-colors mt-6">Bayar Sekarang</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div id="loginModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-sm m-4 relative">
            <button id="closeLoginModal" class="absolute top-3 right-3 text-gray-500 hover:text-gray-800">
                <i class="fas fa-times text-2xl"></i>
            </button>
            <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Login Member</h2>
            <form action="#" method="POST" class="space-y-4">
                <div>
                    <label for="username" class="block text-gray-700">Username</label>
                    <input type="text" id="username" name="username" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="password" class="block text-gray-700">Password</label>
                    <input type="password" id="password" name="password" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700">Masuk</button>
            </form>
        </div>
    </div>

    <!-- Payment Details Modal -->
    <div id="paymentDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 m-4 relative max-h-[90vh] overflow-y-auto">
            <button id="closePaymentModal" class="absolute top-3 right-3 text-gray-500 hover:text-gray-800">
                <i class="fas fa-times text-2xl"></i>
            </button>
            <h2 class="text-2xl font-bold mb-4 text-gray-800">Instruksi Pembayaran</h2>
            <div id="payment-content">
                <!-- Content will be dynamically loaded here -->
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const cartButton = document.getElementById('cartButton');
            const cartModal = document.getElementById('cartModal');
            const closeCartModal = document.getElementById('closeCartModal');
            const loginButton = document.getElementById('loginButton');
            const loginModal = document.getElementById('loginModal');
            const closeLoginModal = document.getElementById('closeLoginModal');
            const paymentDetailsModal = document.getElementById('paymentDetailsModal');
            const closePaymentModal = document.getElementById('closePaymentModal');
            const paymentContent = document.getElementById('payment-content');
            const checkoutForm = document.getElementById('checkoutForm');
            const toastNotif = document.getElementById('toastNotification');
            const cartContent = document.getElementById('cart-content');
            const cartBadge = document.getElementById('cartBadge');
            const cartSummary = document.getElementById('cart-summary');
            const emptyCartMessage = document.getElementById('empty-cart-message');

            cartButton.addEventListener('click', async () => {
                await updateCartModal();
                cartModal.classList.remove('hidden');
            });
            closeCartModal.addEventListener('click', () => {
                cartModal.classList.add('hidden');
            });

            loginButton.addEventListener('click', () => {
                loginModal.classList.remove('hidden');
            });
            closeLoginModal.addEventListener('click', () => {
                loginModal.classList.add('hidden');
            });

            closePaymentModal.addEventListener('click', () => {
                paymentDetailsModal.classList.add('hidden');
            });

            // Logic to show and hide toast notification
            if (toastNotif) {
                toastNotif.classList.add('show');
                setTimeout(() => {
                    toastNotif.classList.remove('show');
                    // Clean up URL after notification is shown
                    const url = new URL(window.location.href);
                    url.searchParams.delete('message');
                    window.history.replaceState({}, '', url);
                }, 3000); // Hide after 3 seconds
            }

            // Check if payment details should be shown on page load
            const urlParams = new URLSearchParams(window.location.search);
            const paymentMethod = urlParams.get('payment_method');
            const totalAmount = urlParams.get('total_amount');
            if (paymentMethod && totalAmount) {
                cartModal.classList.add('hidden'); // Hide cart if showing
                displayPaymentDetails(paymentMethod, totalAmount);
            }

            // Handle add to cart forms
            document.querySelectorAll('.add-to-cart-form').forEach(form => {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const formData = new FormData(form);

                    try {
                        const response = await fetch('index.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();

                        if (data.success) {
                            showToast(data.message, true);
                            updateCartUI(data);
                        } else {
                            showToast(data.message, false);
                        }

                    } catch (error) {
                        console.error('Error:', error);
                        showToast('Gagal menambahkan item ke keranjang.', false);
                    }
                });
            });

            // Handle remove from cart dynamically
            cartContent.addEventListener('submit', async (e) => {
                if (e.target.classList.contains('remove-from-cart-form')) {
                    e.preventDefault();
                    const formData = new FormData(e.target);

                    try {
                        const response = await fetch('index.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();

                        if (data.success) {
                            showToast(data.message, true);
                            updateCartUI(data);
                        } else {
                            showToast(data.message, false);
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showToast('Gagal menghapus item dari keranjang.', false);
                    }
                }
            });


            function showToast(message, isSuccess) {
                const toast = document.createElement('div');
                toast.className = `toast-notif ${isSuccess ? 'bg-green-500' : 'bg-red-500'}`;
                toast.innerHTML = `<p class="text-white font-semibold">${message}</p>`;
                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.classList.add('show');
                }, 10);

                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => {
                        document.body.removeChild(toast);
                    }, 500);
                }, 3000);
            }

            function updateCartUI(data) {
                cartBadge.textContent = data.cart_count;
                if (data.cart_count > 0) {
                    emptyCartMessage.classList.add('hidden');
                    cartSummary.classList.remove('hidden');
                    cartContent.innerHTML = data.cart_items.map(item => `
                        <div class="flex items-center justify-between p-3 bg-gray-100 rounded-lg">
                            <div class="flex items-center space-x-4">
                                <img src="${item.image}" alt="${item.name}" class="w-16 h-16 object-cover rounded-md">
                                <div>
                                    <p class="font-semibold text-gray-800">${item.name}</p>
                                    <p class="text-sm text-gray-500">Rp ${item.price.toLocaleString('id-ID')}</p>
                                    <p class="text-sm text-gray-500">Jumlah: ${item.quantity}</p>
                                </div>
                            </div>
                            <form class="remove-from-cart-form" method="POST">
                                <input type="hidden" name="action" value="remove_from_cart">
                                <input type="hidden" name="menu_id" value="${item.id}">
                                <input type="hidden" name="is_ajax" value="1">
                                <button type="submit" class="text-red-500 hover:text-red-700 transition-colors">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
                    `).join('');

                    document.getElementById('cart-subtotal').textContent = `Rp ${data.subtotal.toLocaleString('id-ID')}`;
                    document.getElementById('cart-ppn').textContent = `Rp ${data.ppn.toLocaleString('id-ID')}`;
                    document.getElementById('cart-total').textContent = `Rp ${data.total.toLocaleString('id-ID')}`;

                } else {
                    cartContent.innerHTML = '';
                    emptyCartMessage.classList.remove('hidden');
                    cartSummary.classList.add('hidden');
                }
            }

            // Function to fetch and update cart modal content
            async function updateCartModal() {
                try {
                    const response = await fetch('index.php?is_ajax=1&get_cart=1');
                    const data = await response.json();
                    updateCartUI(data);
                } catch (error) {
                    console.error('Failed to fetch cart data:', error);
                }
            }

            function displayPaymentDetails(method, total) {
                let contentHTML = '';
                const formattedTotal = parseFloat(total).toLocaleString('id-ID', {
                    style: 'currency',
                    currency: 'IDR'
                });

                switch (method) {
                    case 'cash':
                        contentHTML = `
                            <div class="p-4 bg-gray-100 rounded-lg">
                                <h3 class="text-xl font-semibold mb-2">Pembayaran Tunai</h3>
                                <p class="text-gray-700">Silakan datang ke kasir dan sebutkan nomor meja Anda **Meja: <?= htmlspecialchars($table_id) ?>** untuk melakukan pembayaran.</p>
                                <p class="mt-4 text-sm text-gray-500">Total Pembayaran: <span class="font-bold text-gray-800">${formattedTotal}</span></p>
                            </div>
                        `;
                        break;
                    case 'virtual_account':
                        contentHTML = `
                            <div class="p-4 bg-gray-100 rounded-lg">
                                <h3 class="text-xl font-semibold mb-2">Pembayaran Virtual Account</h3>
                                <p class="text-gray-700">Mohon lakukan pembayaran sejumlah ${formattedTotal} ke nomor Virtual Account di bawah ini:</p>
                                <div class="bg-white p-4 rounded-lg mt-4 shadow">
                                    <p class="text-lg font-bold text-gray-800">901234567890</p>
                                    <p class="text-sm text-gray-500">a/n Cafe Bahagia</p>
                                </div>
                            </div>
                        `;
                        break;
                    case 'QRIS':
                        contentHTML = `
                            <div class="p-4 bg-gray-100 rounded-lg">
                                <h3 class="text-xl font-semibold mb-2">Pembayaran QRIS</h3>
                                <p class="text-gray-700">Scan QRIS di bawah ini dengan aplikasi pembayaran favorit Anda untuk menyelesaikan transaksi.</p>
                                <img src="https://placehold.co/300x300/e5e7eb/000000?text=QRIS+Code" alt="QRIS Code" class="mx-auto my-4 rounded-lg">
                                <p class="mt-4 text-sm text-gray-500">Total Pembayaran: <span class="font-bold text-gray-800">${formattedTotal}</span></p>
                            </div>
                        `;
                        break;
                    case 'transfer':
                        contentHTML = `
                            <div class="p-4 bg-gray-100 rounded-lg">
                                <h3 class="text-xl font-semibold mb-2">Pembayaran Transfer Bank</h3>
                                <p class="text-gray-700 mb-4">Pilih salah satu bank di bawah ini untuk transfer sejumlah ${formattedTotal}.</p>
                                <div class="space-y-4">
                                    <div class="bg-white p-4 rounded-lg shadow">
                                        <p class="font-semibold text-gray-800">BCA</p>
                                        <p class="text-sm text-gray-500">No. Rek: 123-456-7890</p>
                                        <p class="text-sm text-gray-500">a/n Cafe Bahagia</p>
                                    </div>
                                    <div class="bg-white p-4 rounded-lg shadow">
                                        <p class="font-semibold text-gray-800">Mandiri</p>
                                        <p class="text-sm text-gray-500">No. Rek: 123-456-7890</p>
                                        <p class="text-sm text-gray-500">a/n Cafe Bahagia</p>
                                    </div>
                                    <div class="bg-white p-4 rounded-lg shadow">
                                        <p class="font-semibold text-gray-800">BRI</p>
                                        <p class="text-sm text-gray-500">No. Rek: 123-456-7890</p>
                                        <p class="text-sm text-gray-500">a/n Cafe Bahagia</p>
                                    </div>
                                </div>
                            </div>
                        `;
                        break;
                }

                paymentContent.innerHTML = contentHTML;
                paymentDetailsModal.classList.remove('hidden');
            }
        });
    </script>
</body>

</html>