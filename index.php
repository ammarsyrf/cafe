<?php
// File: index.php
// Halaman untuk pelanggan (dapat diakses melalui QR code di meja)

require_once 'db_connect.php';
// [DITAMBAHKAN] Memuat semua pengaturan dari database ke dalam variabel $APP_CONFIG
require_once 'config.php';


// Memastikan sesi hanya dimulai sekali
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek status login user
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';

// Inisialisasi keranjang jika belum ada
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (isset($_GET['meja'])) {
    $encodedIdentifier = $_GET['meja'];
    // Decode kembali dari Base64
    $tableIdentifier = base64_decode($encodedIdentifier);

    // Simpan nomor meja ke dalam session agar bisa digunakan saat checkout
    $_SESSION['nomor_meja'] = $tableIdentifier;
}

// --- FUNGSI PERHITUNGAN DISKON ---
function calculate_discount($subtotal, $is_member)
{
    if (!$is_member || $subtotal <= 0) {
        return 0;
    }

    $discount_percentage = 0;
    if ($subtotal >= 200000) {
        $discount_percentage = 0.15; // 15%
    } elseif ($subtotal >= 100000) {
        $discount_percentage = 0.10; // 10%
    } elseif ($subtotal >= 50000) {
        $discount_percentage = 0.05; // 5%
    }

    return $subtotal * $discount_percentage;
}

// --- FUNGSI UNTUK MENGAMBIL DATA KERANJANG TERBARU ---
function get_cart_data($is_logged_in)
{
    $cart = $_SESSION['cart'] ?? [];
    $subtotal = array_sum(array_map(function ($item) {
        return $item['price'] * $item['quantity'];
    }, $cart));

    $discount = calculate_discount($subtotal, $is_logged_in);
    $subtotal_after_discount = $subtotal - $discount;
    $ppn = $subtotal_after_discount * 0.11; // PPN 11% dari harga setelah diskon
    $total = $subtotal_after_discount + $ppn;
    $cart_count = array_sum(array_column($cart, 'quantity'));

    return [
        'cart_items' => array_values($cart),
        'cart_count' => $cart_count,
        'subtotal'   => $subtotal,
        'discount'   => $discount,
        'ppn'        => $ppn,
        'total'      => $total,
    ];
}

// Handle GET request untuk data keranjang (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['is_ajax_get_cart'])) {
    header('Content-Type: application/json');
    echo json_encode(get_cart_data($is_logged_in));
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
            // Ambil harga normal dan harga diskon
            $sql = "SELECT id, name, price, discount_price, stock, image_url FROM menu WHERE id = ? AND is_available = TRUE";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $menu_id);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();

            if ($item) {
                $current_stock = $item['stock'];
                $cart_quantity = isset($_SESSION['cart'][$menu_id]['quantity']) ? $_SESSION['cart'][$menu_id]['quantity'] : 0;

                if ($current_stock > $cart_quantity) {
                    // Tentukan harga yang akan digunakan (harga diskon jika ada dan valid)
                    $price_to_use = (isset($item['discount_price']) && $item['discount_price'] > 0) ? $item['discount_price'] : $item['price'];

                    if (isset($_SESSION['cart'][$menu_id])) {
                        $_SESSION['cart'][$menu_id]['quantity']++;
                    } else {
                        $_SESSION['cart'][$menu_id] = [
                            'id'             => $item['id'],
                            'name'           => $item['name'],
                            'price'          => $price_to_use, // Gunakan harga yang sudah ditentukan
                            'original_price' => $item['price'], // Simpan harga asli untuk display
                            'quantity'       => 1,
                            'image'          => $item['image_url']
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
    $response = array_merge($response, get_cart_data($is_logged_in));
    echo json_encode($response);
    exit();
}


// Handle checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    $table_id = isset($_POST['table_id']) ? (int)$_POST['table_id'] : 1;
    if (empty($_SESSION['cart'])) {
        header("Location: index.php?table=$table_id&error=Keranjang+kosong");
        exit();
    }

    // Validasi nama untuk guest
    $customer_name = null;
    if (!$is_logged_in) {
        if (empty($_POST['customer_name'])) {
            header("Location: index.php?table=$table_id&error=Nama+pemesan+harus+diisi");
            exit();
        }
        $customer_name = trim($_POST['customer_name']);
    }


    $payment_method = $_POST['payment_method'];
    $cart_data = get_cart_data($is_logged_in);

    $subtotal = $cart_data['subtotal'];
    $discount = $cart_data['discount'];
    $ppn = $cart_data['ppn'];
    $total = $cart_data['total'];
    $order_type = 'dine-in';
    $user_id = $is_logged_in ? $_SESSION['user_id'] : null;


    $conn->begin_transaction();
    try {
        // Simpan pesanan
        $sql = "INSERT INTO orders (table_id, user_id, customer_name, order_type, status, subtotal, discount_amount, tax, total_amount, payment_method) VALUES (?, ?, ?, ?, 'pending_payment', ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisssddds", $table_id, $user_id, $customer_name, $order_type, $subtotal, $discount, $ppn, $total, $payment_method);
        $stmt->execute();
        $order_id = $stmt->insert_id;

        // Simpan item pesanan dan kurangi stok
        foreach ($_SESSION['cart'] as $item) {
            // Harga yang disimpan adalah harga yang ada di keranjang (bisa harga normal/diskon)
            $sql = "INSERT INTO order_items (order_id, menu_id, quantity, price) VALUES (?, ?, ?, ?)";
            $stmt_item = $conn->prepare($sql);
            $stmt_item->bind_param("iiid", $order_id, $item['id'], $item['quantity'], $item['price']);
            $stmt_item->execute();

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
        // Sebaiknya log error ini: error_log($e->getMessage());
        header("Location: index.php?table=$table_id&error=Gagal+membuat+pesanan");
        exit();
    }
}

// --- FUNGSI UNTUK MENDAPATKAN IKON KATEGORI ---
function get_category_icon($category)
{
    $category_lower = strtolower($category);
    $icons = [
        'promo'   => 'fas fa-star',
        'makanan' => 'fas fa-utensils',
        'minuman' => 'fas fa-wine-glass',
        'kopi'    => 'fas fa-coffee',
        'snack'   => 'fas fa-cookie-bite',
        'dessert' => 'fas fa-ice-cream',
    ];

    foreach ($icons as $key => $icon) {
        if (strpos($category_lower, $key) !== false) {
            return $icon;
        }
    }
    return 'fas fa-tag'; // Ikon default
}

// Data untuk render halaman
$table_id = isset($_GET['table']) ? (int)$_GET['table'] : 1;
$menu_items = [];

// Query SQL untuk memberlakukan urutan kategori spesifik.
$sql = "SELECT id, name, description, price, discount_price, category, stock, image_url, is_available
        FROM menu
        WHERE is_available = TRUE
        ORDER BY
            CASE
                WHEN category = 'makanan' THEN 1
                WHEN category = 'minuman' THEN 2
                WHEN category = 'snack'   THEN 3
                WHEN category = 'others'  THEN 4
                ELSE 99
            END,
            name ASC";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $menu_items[] = $row;
    }
}

// Logika pengelompokan yang disederhanakan.
$promo_items = [];
$categories_grouped = [];
foreach ($menu_items as $item) {
    $categories_grouped[$item['category']][] = $item;
    if (isset($item['discount_price']) && $item['discount_price'] > 0 && $item['discount_price'] < $item['price']) {
        $promo_items[] = $item;
    }
}

// Buat array final untuk ditampilkan
$display_categories = [];
if (!empty($promo_items)) {
    $display_categories['promo'] = $promo_items;
}
$display_categories = array_merge($display_categories, $categories_grouped);

$cart_count = array_sum(array_column($_SESSION['cart'] ?? [], 'quantity'));

// Mengambil data banner aktif dari database
$banners = [];
$sql_banners = "SELECT title, subtitle, image_url, link_url FROM banners WHERE is_active = TRUE ORDER BY order_number ASC";
$result_banners = $conn->query($sql_banners);
if ($result_banners) {
    while ($row = $result_banners->fetch_assoc()) {
        $banners[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu <?= htmlspecialchars($APP_CONFIG['cafe_name'] ?? 'Cafe') ?> - Meja <?= htmlspecialchars($table_id) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
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

        #cart-drawer,
        #loginModal {
            transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out;
        }

        #cart-drawer.translate-x-full {
            transform: translateX(100%);
        }

        #cart-backdrop {
            transition: opacity 0.3s ease-in-out;
        }

        .category-nav-item.active {
            background-color: #111827;
            /* gray-900 */
            color: white;
            box-shadow: 0 4px 14px 0 rgb(0 0 0 / 10%);
        }

        #category-nav .overflow-x-auto::-webkit-scrollbar {
            display: none;
        }

        #category-nav .overflow-x-auto {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .banner-carousel .swiper-button-next,
        .banner-carousel .swiper-button-prev {
            color: white;
            background-color: rgba(0, 0, 0, 0.3);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            transition: background-color 0.3s;
        }

        .banner-carousel .swiper-button-next:hover,
        .banner-carousel .swiper-button-prev:hover {
            background-color: rgba(0, 0, 0, 0.6);
        }

        .banner-carousel .swiper-button-next::after,
        .banner-carousel .swiper-button-prev::after {
            font-size: 1.2rem;
            font-weight: bold;
        }

        .swiper-pagination-bullet-active {
            background: #ffffff !important;
        }

        .animate-on-scroll {
            opacity: 0;
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
            transform: translateY(20px);
        }

        .animate-on-scroll.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        .menu-card-clickable {
            transition: transform 0.1s ease-out;
        }

        .menu-card-clickable:active {
            transform: scale(0.97);
        }
    </style>
</head>

<body class="bg-white">

    <!-- Navbar -->
    <nav class="bg-white/80 backdrop-blur-lg shadow-sm py-3 sticky top-0 z-40">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <h1 class="text-xl md:text-2xl font-black text-gray-900"><?= htmlspecialchars($APP_CONFIG['cafe_name'] ?? 'Nama Cafe') ?></h1>
            <div class="flex items-center space-x-2 md:space-x-4">
                <span class="bg-gray-100 text-gray-800 px-3 py-1.5 rounded-full font-bold text-xs md:text-sm">Meja: <?= htmlspecialchars($table_id) ?></span>
                <div id="authButtonContainer">
                    <?php if ($is_logged_in) : ?>
                        <div class="flex items-center space-x-2">
                            <span class="font-semibold text-green-600 text-sm hidden sm:block"><i class="fas fa-star mr-1"></i> Member</span>
                            <!-- [DIUBAH] Tombol Profile Ditambahkan -->
                            <a href="member.php" title="Lihat Profil" class="bg-blue-600 text-white w-10 h-10 flex items-center justify-center rounded-full font-bold hover:bg-blue-700 transition-colors text-sm">
                                <i class="fas fa-user"></i>
                            </a>
                            <a href="logout.php" class="bg-gray-800 text-white px-3 md:px-4 py-2 rounded-full font-bold hover:bg-gray-900 transition-colors text-sm">Logout</a>
                        </div>
                    <?php else : ?>
                        <button id="loginButton" class="bg-gray-800 text-white px-3 md:px-4 py-2 rounded-full font-bold hover:bg-gray-900 transition-colors text-sm">Login</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4">
        <!-- [DESAIN BARU] Carousel Banner Section -->
        <?php if (!empty($banners)) : ?>
            <section class="w-full pt-6 md:pt-8 animate-on-scroll">
                <div class="swiper-container banner-carousel rounded-2xl md:rounded-3xl shadow-lg overflow-hidden">
                    <div class="swiper-wrapper">
                        <?php foreach ($banners as $banner) : ?>
                            <div class="swiper-slide relative">
                                <a href="<?= htmlspecialchars($banner['link_url'] ?? '#') ?>">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                                    <img src="<?= htmlspecialchars($banner['image_url']) ?>" alt="<?= htmlspecialchars($banner['title']) ?>" class="w-full h-56 md:h-80 object-cover">
                                    <div class="absolute bottom-0 left-0 p-5 md:p-8">
                                        <h2 class="text-white text-2xl md:text-4xl font-extrabold"><?= htmlspecialchars($banner['title']) ?></h2>
                                        <p class="text-white/90 text-sm md:text-base mt-1 max-w-lg"><?= htmlspecialchars($banner['subtitle']) ?></p>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-pagination"></div>
                    <div class="swiper-button-next hidden sm:flex"></div>
                    <div class="swiper-button-prev hidden sm:flex"></div>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <!-- Category Nav -->
    <div id="category-nav" class="bg-white sticky top-[68px] z-30 py-4 md:py-5 border-b border-gray-100">
        <div class="container mx-auto px-4">
            <div class="flex space-x-3 overflow-x-auto whitespace-nowrap">
                <?php foreach ($display_categories as $category => $items) : ?>
                    <a href="#category-<?= strtolower(htmlspecialchars($category)) ?>" class="category-nav-item flex items-center space-x-2 text-sm md:text-base font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-full px-4 py-2.5 transition-all duration-300">
                        <i class="<?= get_category_icon($category) ?> w-5 text-center text-gray-500"></i>
                        <span><?= htmlspecialchars(ucfirst($category)) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8 md:py-12">
        <div class="space-y-16">
            <?php foreach ($display_categories as $category => $items) : ?>
                <section id="category-<?= strtolower(htmlspecialchars($category)) ?>" class="scroll-mt-36 animate-on-scroll">
                    <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 capitalize mb-8"><?= htmlspecialchars($category) ?></h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-6 gap-y-10">
                        <?php foreach ($items as $item) : ?>
                            <!-- [PERUBAHAN] Kartu Menu dengan border dan padding -->
                            <div class="bg-white rounded-2xl p-4 flex flex-col group border border-gray-100 shadow-md hover:shadow-xl transition-shadow duration-300 <?= $item['stock'] > 0 ? 'menu-card-clickable cursor-pointer' : 'opacity-60' ?>">
                                <div class="h-56 w-full rounded-xl overflow-hidden relative mb-4">
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110">
                                    <?php if (isset($item['discount_price']) && $item['discount_price'] > 0 && $item['discount_price'] < $item['price']) : ?>
                                        <div class="absolute top-3 right-3 bg-red-600 text-white text-xs font-bold px-2.5 py-1 rounded-full">PROMO</div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow">
                                    <h3 class="text-lg font-bold text-gray-800 mb-1 truncate"><?= htmlspecialchars($item['name']) ?></h3>
                                    <p class="text-gray-500 text-sm mb-3 line-clamp-2"><?= htmlspecialchars($item['description']) ?></p>
                                </div>
                                <div class="flex items-center justify-between mt-auto">
                                    <div class="flex flex-col items-start">
                                        <?php if (isset($item['discount_price']) && $item['discount_price'] > 0 && $item['discount_price'] < $item['price']) : ?>
                                            <span class="text-gray-900 font-extrabold text-lg">Rp<?= number_format($item['discount_price'], 0, ',', '.') ?></span>
                                            <del class="text-gray-400 text-sm -mt-1">Rp<?= number_format($item['price'], 0, ',', '.') ?></del>
                                        <?php else : ?>
                                            <span class="text-gray-900 font-extrabold text-lg">Rp<?= number_format($item['price'], 0, ',', '.') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <form class="add-to-cart-form">
                                        <input type="hidden" name="action" value="add_to_cart">
                                        <input type="hidden" name="menu_id" value="<?= $item['id'] ?>">
                                        <input type="hidden" name="is_ajax" value="1">
                                        <button type="submit" <?= $item['stock'] == 0 ? 'disabled' : '' ?> class="bg-gray-800 text-white w-10 h-10 rounded-full font-semibold text-lg hover:bg-gray-900 disabled:bg-gray-200 disabled:cursor-not-allowed transform transition-transform active:scale-90">+</button>
                                    </form>
                                </div>
                                <?php if ($item['stock'] == 0) : ?>
                                    <p class="text-red-500 text-xs font-semibold mt-2">Stok habis</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white mt-12 py-10">
        <div class="container mx-auto px-4 text-center">
            <h3 class="text-2xl font-bold mb-2"><?= htmlspecialchars($APP_CONFIG['cafe_name'] ?? 'Nama Cafe') ?></h3>
            <?php if (!empty($APP_CONFIG['cafe_address'])) : ?>
                <p class="text-gray-400 mb-1"><i class="fas fa-map-marker-alt mr-2"></i><?= htmlspecialchars($APP_CONFIG['cafe_address']) ?></p>
            <?php endif; ?>
            <?php if (!empty($APP_CONFIG['cafe_phone'])) : ?>
                <p class="text-gray-400 mb-1"><i class="fas fa-phone mr-2"></i><?= htmlspecialchars($APP_CONFIG['cafe_phone']) ?></p>
            <?php endif; ?>
            <?php if (!empty($APP_CONFIG['hour_open']) && !empty($APP_CONFIG['hour_close'])) : ?>
                <p class="text-gray-400 mb-4"><i class="fas fa-clock mr-2"></i>Buka: <?= htmlspecialchars($APP_CONFIG['hour_open']) ?> - <?= htmlspecialchars($APP_CONFIG['hour_close']) ?></p>
            <?php endif; ?>

            <div class="flex justify-center space-x-4">
                <?php if (!empty($APP_CONFIG['social_instagram'])) : ?>
                    <a href="<?= htmlspecialchars($APP_CONFIG['social_instagram']) ?>" target="_blank" class="text-gray-300 hover:text-white transition-colors text-2xl"><i class="fab fa-instagram"></i></a>
                <?php endif; ?>
                <?php if (!empty($APP_CONFIG['social_facebook'])) : ?>
                    <a href="<?= htmlspecialchars($APP_CONFIG['social_facebook']) ?>" target="_blank" class="text-gray-300 hover:text-white transition-colors text-2xl"><i class="fab fa-facebook"></i></a>
                <?php endif; ?>
                <?php if (!empty($APP_CONFIG['social_twitter'])) : ?>
                    <a href="<?= htmlspecialchars($APP_CONFIG['social_twitter']) ?>" target="_blank" class="text-gray-300 hover:text-white transition-colors text-2xl"><i class="fab fa-twitter"></i></a>
                <?php endif; ?>
            </div>
            <p class="text-gray-500 text-sm mt-6">&copy; <?= date('Y') ?> <?= htmlspecialchars($APP_CONFIG['cafe_name'] ?? 'Nama Cafe') ?>. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- MODALS AND DRAWERS (No design change needed) -->
    <!-- Cart Floating Action Button -->
    <button id="cartFab" class="fixed bottom-6 right-6 bg-green-500 text-white rounded-full shadow-lg w-16 h-16 flex items-center justify-center z-50 transform hover:scale-110 transition-transform">
        <i class="fas fa-shopping-cart text-2xl"></i>
        <span id="cartBadgeFab" class="absolute -top-1 -right-1 bg-red-600 text-white text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center border-2 border-white <?= $cart_count == 0 ? 'hidden' : '' ?>"><?= $cart_count ?></span>
    </button>
    <div id="cart-backdrop" class="fixed inset-0 bg-black bg-opacity-60 hidden z-[60]"></div>
    <div id="cart-drawer" class="fixed top-0 right-0 h-full w-full max-w-md bg-gray-100 shadow-2xl z-[70] transform translate-x-full flex flex-col">
        <div class="flex justify-between items-center p-5 border-b bg-white">
            <h2 class="text-xl font-bold text-gray-800">Keranjang Anda</h2><button id="closeCartDrawer" class="text-gray-500 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
        </div>
        <div id="cart-content" class="flex-grow p-5 overflow-y-auto space-y-4"></div>
        <div id="empty-cart-message" class="flex-grow flex flex-col items-center justify-center text-gray-500 hidden"><i class="fas fa-shopping-basket text-6xl text-gray-300 mb-4"></i>
            <p class="text-lg">Keranjang Anda kosong.</p>
        </div>
        <div id="cart-summary" class="p-5 border-t bg-white shadow-inner hidden">
            <form id="checkoutForm" method="POST">
                <?php if (!$is_logged_in) : ?><div class="mb-4"><label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">Nama Pemesan</label><input type="text" id="customer_name" name="customer_name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Masukkan nama Anda">
                        <p class="text-xs text-gray-500 mt-1">Diperlukan agar kasir dapat memanggil nama Anda.</p>
                    </div><?php endif; ?>
                <div class="space-y-2 mb-4">
                    <div class="flex justify-between font-medium text-gray-600"><span>Subtotal</span><span id="cart-subtotal"></span></div>
                    <div id="discount-row" class="flex justify-between font-medium text-green-600 hidden"><span>Diskon Member</span><span id="cart-discount"></span></div>
                    <div class="flex justify-between font-medium text-gray-600"><span>PPN (11%)</span><span id="cart-ppn"></span></div>
                    <div class="flex justify-between font-bold text-lg text-gray-900 border-t pt-2 mt-2"><span>Total</span><span id="cart-total"></span></div>
                </div>
                <input type="hidden" name="action" value="checkout"><input type="hidden" name="table_id" value="<?= $table_id ?>">
                <h3 class="text-md font-semibold text-gray-800 mb-3">Metode Pembayaran</h3>
                <div class="grid grid-cols-2 gap-3 mb-4"><label class="p-3 rounded-lg border-2 cursor-pointer has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 transition-all"><input type="radio" name="payment_method" value="cash" class="sr-only" checked><span class="block text-center font-medium text-sm">Cash</span></label><label class="p-3 rounded-lg border-2 cursor-pointer has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 transition-all"><input type="radio" name="payment_method" value="QRIS" class="sr-only"><span class="block text-center font-medium text-sm">QRIS</span></label><label class="p-3 rounded-lg border-2 cursor-pointer has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 transition-all"><input type="radio" name="payment_method" value="transfer" class="sr-only"><span class="block text-center font-medium text-sm">Transfer</span></label><label class="p-3 rounded-lg border-2 cursor-pointer has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 transition-all"><input type="radio" name="payment_method" value="virtual_account" class="sr-only"><span class="block text-center font-medium text-sm">Virtual Acct</span></label></div>
                <button type="submit" class="w-full bg-green-600 text-white font-bold py-3.5 rounded-xl hover:bg-green-700 transition-colors">Bayar Sekarang</button>
            </form>
        </div>
    </div>
    <div id="loginModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-[80] opacity-0 pointer-events-none">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-sm m-4 relative transform scale-95 transition-transform duration-300">
            <button id="closeLoginModal" class="absolute top-3 right-3 text-gray-400 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
            <div id="login-form-container" class="p-8">
                <h2 class="text-2xl font-bold mb-1 text-center text-gray-800">Login Member</h2>
                <p class="text-center text-gray-500 mb-6">Dapatkan diskon spesial untuk setiap pesanan!</p>
                <div id="login-error" class="text-red-500 text-sm text-center mb-4"></div>
                <form id="loginForm">
                    <div class="mb-4"><label for="login_email" class="block text-sm font-medium text-gray-700 mb-1">Email</label><input type="email" id="login_email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                    <div class="mb-6"><label for="login_password" class="block text-sm font-medium text-gray-700 mb-1">Password</label><input type="password" id="login_password" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></div><button type="submit" class="w-full bg-blue-600 text-white font-bold py-2.5 rounded-md hover:bg-blue-700 transition-colors">Login</button>
                </form>
                <p class="text-center text-sm text-gray-600 mt-6">Belum punya akun? <a href="#" id="showRegister" class="font-semibold text-blue-600 hover:underline">Daftar di sini</a></p>
            </div>
            <div id="register-form-container" class="p-8 hidden">
                <h2 class="text-2xl font-bold mb-1 text-center text-gray-800">Daftar Member Baru</h2>
                <p class="text-center text-gray-500 mb-6">Gratis! Dapatkan diskon menarik sekarang juga.</p>
                <div id="register-error" class="text-red-500 text-sm text-center mb-4"></div>
                <div id="register-success" class="text-green-500 text-sm text-center mb-4"></div>
                <form id="registerForm">
                    <div class="mb-4"><label for="register_name" class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label><input type="text" id="register_name" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                    <div class="mb-4"><label for="register_email" class="block text-sm font-medium text-gray-700 mb-1">Email</label><input type="email" id="register_email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                    <div class="mb-6"><label for="register_password" class="block text-sm font-medium text-gray-700 mb-1">Password</label><input type="password" id="register_password" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></div><button type="submit" class="w-full bg-blue-600 text-white font-bold py-2.5 rounded-md hover:bg-blue-700 transition-colors">Daftar</button>
                </form>
                <p class="text-center text-sm text-gray-600 mt-6">Sudah punya akun? <a href="#" id="showLogin" class="font-semibold text-blue-600 hover:underline">Login di sini</a></p>
            </div>
        </div>
    </div>
    <div id="paymentDetailsModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center hidden z-[80] p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6 m-4 relative"><button id="closePaymentModal" class="absolute top-3 right-3 text-gray-500 hover:text-gray-800"><i class="fas fa-times text-2xl"></i></button>
            <h2 class="text-xl font-bold mb-4 text-gray-800">Instruksi Pembayaran</h2>
            <div id="payment-content"></div>
        </div>
    </div>

    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const CAFE_NAME = '<?= htmlspecialchars($APP_CONFIG['cafe_name'] ?? 'Cafe', ENT_QUOTES) ?>';
            const TABLE_ID = <?= $table_id ?>;

            const bannerCarousel = new Swiper('.banner-carousel', {
                loop: true,
                autoplay: {
                    delay: 5000,
                    disableOnInteraction: false,
                },
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
            });

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                    }
                });
            }, {
                threshold: 0.1
            });
            document.querySelectorAll('.animate-on-scroll').forEach(el => observer.observe(el));


            // Sisa Javascript (dengan penambahan logika klik kartu)
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
            const authButtonContainer = document.getElementById('authButtonContainer');
            const loginModal = document.getElementById('loginModal');
            const closeLoginModal = document.getElementById('closeLoginModal');
            const loginFormContainer = document.getElementById('login-form-container');
            const registerFormContainer = document.getElementById('register-form-container');
            const showRegister = document.getElementById('showRegister');
            const showLogin = document.getElementById('showLogin');
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            const checkoutForm = document.getElementById('checkoutForm');
            const toggleCartDrawer = (show) => {
                cartDrawer.classList.toggle('translate-x-full', !show);
                cartBackdrop.classList.toggle('hidden', !show);
            };
            const toggleLoginModal = (show) => {
                if (show) {
                    loginModal.classList.remove('opacity-0', 'pointer-events-none');
                    loginModal.querySelector('div').classList.remove('scale-95');
                } else {
                    loginModal.classList.add('opacity-0');
                    loginModal.querySelector('div').classList.add('scale-95');
                    setTimeout(() => loginModal.classList.add('pointer-events-none'), 300);
                }
            };
            checkoutForm.addEventListener('submit', (e) => {
                const customerNameInput = document.getElementById('customer_name');
                if (customerNameInput && customerNameInput.value.trim() === '') {
                    e.preventDefault();
                    showToast('Nama pemesan tidak boleh kosong.', false);
                    customerNameInput.focus();
                }
            });
            authButtonContainer.addEventListener('click', (e) => {
                if (e.target.id === 'loginButton') {
                    toggleLoginModal(true);
                }
            });
            closeLoginModal.addEventListener('click', () => toggleLoginModal(false));
            cartFab.addEventListener('click', () => {
                updateCartData();
                toggleCartDrawer(true);
            });
            closeCartDrawer.addEventListener('click', () => toggleCartDrawer(false));
            cartBackdrop.addEventListener('click', () => {
                toggleCartDrawer(false);
                toggleLoginModal(false);
            });
            closePaymentModal.addEventListener('click', () => {
                paymentDetailsModal.classList.add('hidden');
                const url = new URL(window.location.href);
                url.searchParams.delete('payment_method');
                url.searchParams.delete('total_amount');
                window.history.replaceState({}, '', url);
            });
            showRegister.addEventListener('click', (e) => {
                e.preventDefault();
                loginFormContainer.classList.add('hidden');
                registerFormContainer.classList.remove('hidden');
            });
            showLogin.addEventListener('click', (e) => {
                e.preventDefault();
                registerFormContainer.classList.add('hidden');
                loginFormContainer.classList.remove('hidden');
            });
            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(loginForm);
                const response = await fetch('login.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, true);
                    toggleLoginModal(false);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    document.getElementById('login-error').textContent = result.message;
                }
            });
            registerForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(registerForm);
                const response = await fetch('register.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                const registerError = document.getElementById('register-error');
                const registerSuccess = document.getElementById('register-success');
                registerError.textContent = '';
                registerSuccess.textContent = '';
                if (result.success) {
                    registerSuccess.textContent = result.message;
                    registerForm.reset();
                    setTimeout(() => {
                        registerFormContainer.classList.add('hidden');
                        loginFormContainer.classList.remove('hidden');
                        registerSuccess.textContent = '';
                    }, 2000);
                } else {
                    registerError.textContent = result.message;
                }
            });
            const formatCurrency = (amount) => `Rp${Number(amount).toLocaleString('id-ID')}`;
            document.querySelectorAll('.add-to-cart-form').forEach(form => {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const response = await sendCartAction(new FormData(form));
                    if (response) {
                        showToast(response.message, response.success);
                        if (response.success) updateCartUI(response);
                    }
                });
            });

            // [DITAMBAHKAN] Logika untuk membuat kartu bisa diklik
            document.querySelectorAll('.menu-card-clickable').forEach(card => {
                card.addEventListener('click', function(e) {
                    if (e.target.closest('button')) {
                        return;
                    }
                    const submitButton = this.querySelector('.add-to-cart-form button[type="submit"]');
                    if (submitButton && !submitButton.disabled) {
                        submitButton.click();
                    }
                });
            });

            cartContent.addEventListener('click', async (e) => {
                if (e.target.matches('.quantity-btn')) {
                    e.preventDefault();
                    const form = e.target.closest('form');
                    const quantityInput = form.querySelector('input[name="quantity"]');
                    let quantity = parseInt(quantityInput.value) + parseInt(e.target.dataset.change);
                    if (quantity < 0) quantity = 0;
                    quantityInput.value = quantity;
                    const response = await sendCartAction(new FormData(form));
                    if (response) {
                        if (response.success) {
                            updateCartUI(response);
                        } else {
                            showToast(response.message, false);
                            updateCartData();
                        }
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
                    cartContent.innerHTML = data.cart_items.map(item => {
                        const priceDisplay = (item.original_price && item.price < item.original_price) ? `<div><p class="font-bold text-red-600 text-md">${formatCurrency(item.price)}</p><del class="text-xs text-gray-500">${formatCurrency(item.original_price)}</del></div>` : `<p class="font-bold text-gray-800 text-md">${formatCurrency(item.price)}</p>`;
                        return `<div class="flex items-start justify-between bg-white p-3 rounded-lg shadow-sm"><div class="flex items-start space-x-3"><img src="${item.image}" alt="${item.name}" class="w-20 h-20 object-cover rounded-md"><div><p class="font-bold text-gray-800 text-md">${item.name}</p>${priceDisplay}</div></div><form class="update-cart-form flex flex-col items-end"><div class="flex items-center rounded-full border bg-gray-50 overflow-hidden"><button data-change="-1" class="quantity-btn px-2 py-1 text-lg font-bold text-gray-600 hover:bg-gray-200">-</button><input type="number" name="quantity" value="${item.quantity}" class="w-10 text-center font-semibold bg-transparent border-none focus:ring-0" readonly><button data-change="1" class="quantity-btn px-2 py-1 text-lg font-bold text-gray-600 hover:bg-gray-200">+</button></div><input type="hidden" name="action" value="update_quantity"><input type="hidden" name="menu_id" value="${item.id}"><input type="hidden" name="is_ajax" value="1"></form></div>`
                    }).join('');
                    document.getElementById('cart-subtotal').textContent = formatCurrency(data.subtotal);
                    document.getElementById('cart-ppn').textContent = formatCurrency(data.ppn);
                    document.getElementById('cart-total').textContent = formatCurrency(data.total);
                    const discountRow = document.getElementById('discount-row');
                    const discountEl = document.getElementById('cart-discount');
                    if (data.discount > 0) {
                        discountEl.textContent = `- ${formatCurrency(data.discount)}`;
                        discountRow.classList.remove('hidden');
                    } else {
                        discountRow.classList.add('hidden');
                    }
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
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('payment_method') && urlParams.get('total_amount')) {
                displayPaymentDetails(urlParams.get('payment_method'), urlParams.get('total_amount'));
            }
            if (urlParams.has('error')) {
                showToast(urlParams.get('error'), false);
                const cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete('error');
                window.history.replaceState({}, '', cleanUrl);
            }

            function displayPaymentDetails(method, total) {
                const formattedTotal = formatCurrency(total);
                let contentHTML = '';
                switch (method) {
                    case 'cash':
                        contentHTML = `<p>Silakan ke kasir, sebutkan nomor meja <b>(Meja: ${TABLE_ID})</b> dan bayar sejumlah <b>${formattedTotal}</b>.</p><p class="mt-4 text-sm text-center text-gray-500">Terima kasih atas pesanan Anda!</p>`;
                        break;
                    case 'QRIS':
                        contentHTML = `<p>Scan QRIS di bawah ini dan bayar sejumlah <b>${formattedTotal}</b>.</p><img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=ContohDataQRIS" class="mx-auto my-4 rounded-lg">`;
                        break;
                    case 'transfer':
                        contentHTML = `<p>Transfer sejumlah <b>${formattedTotal}</b> ke:<br><b class="text-lg">BCA: 123456789</b><br>(a/n ${CAFE_NAME})</p>`;
                        break;
                    case 'virtual_account':
                        contentHTML = `<p>Bayar sejumlah <b>${formattedTotal}</b> ke VA berikut:<br><b class="text-lg">VA: 901234567890</b><br>(a/n ${CAFE_NAME})</p>`;
                        break;
                }
                document.getElementById('payment-content').innerHTML = contentHTML;
                paymentDetailsModal.classList.remove('hidden');
            }
            const categoryNavObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        document.querySelectorAll('.category-nav-item').forEach(nav => {
                            const isActive = nav.getAttribute('href').substring(1) === entry.target.id;
                            nav.classList.toggle('active', isActive);
                            if (isActive) {
                                nav.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'nearest',
                                    inline: 'center'
                                });
                            }
                        });
                    }
                });
            }, {
                rootMargin: "-100px 0px -50% 0px",
                threshold: 0
            });
            document.querySelectorAll('section[id^="category-"]').forEach(sec => categoryNavObserver.observe(sec));
            updateCartData();
        });
    </script>
</body>

</html>