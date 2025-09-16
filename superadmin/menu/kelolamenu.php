<?php
// Memulai session untuk menampilkan notifikasi sukses/gagal
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../db_connect.php';

// =================================================================
// BAGIAN API & FORM HANDLER
// =================================================================
if (isset($_REQUEST['action'])) {
    $action = $_REQUEST['action'];

    // [PERUBAHAN] Penanganan 'Tambah Menu' sekarang menggunakan AJAX dan mengembalikan JSON
    if ($action == 'add_menu' && strtoupper($_SERVER["REQUEST_METHOD"]) === "POST") {
        header('Content-Type: application/json');
        if (empty($_POST['name']) || empty($_POST['category']) || !isset($_POST['price']) || !isset($_POST['stock'])) {
            echo json_encode(['success' => false, 'message' => 'Nama, Kategori, Harga, dan Stok wajib diisi.']);
            exit();
        }

        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price']);
        $discount_price = isset($_POST['discount_price']) && $_POST['discount_price'] !== '' ? floatval($_POST['discount_price']) : 0;
        $category = trim($_POST['category']);
        $stock = intval($_POST['stock']);
        $is_available = intval($_POST['is_available'] ?? 1);
        $image_url = null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = "../uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

            $file_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
            $new_filename = uniqid('menu_', true) . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($file_extension, $allowed_types)) {
                if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    echo json_encode(['success' => false, 'message' => 'Gagal mengupload gambar.']);
                    exit();
                }
                $image_url = "uploads/" . $new_filename;
            } else {
                echo json_encode(['success' => false, 'message' => 'Tipe file gambar tidak valid (hanya jpg, png, gif, webp).']);
                exit();
            }
        }

        $sql = "INSERT INTO menu (name, description, price, discount_price, category, stock, image_url, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssddsisi", $name, $description, $price, $discount_price, $category, $stock, $image_url, $is_available);

        if ($stmt->execute()) {
            $new_menu_id = $conn->insert_id;
            $stmt_get_new = $conn->prepare("SELECT * FROM menu WHERE id = ?");
            $stmt_get_new->bind_param("i", $new_menu_id);
            $stmt_get_new->execute();
            $new_menu_data = $stmt_get_new->get_result()->fetch_assoc();
            $stmt_get_new->close();

            echo json_encode(['success' => true, 'message' => "Menu '" . htmlspecialchars($name) . "' berhasil ditambahkan.", 'data' => $new_menu_data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menambahkan menu ke database: ' . $stmt->error]);
        }
        $stmt->close();
        exit();
    }

    // Header JSON hanya untuk request AJAX.
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Aksi tidak valid.'];

    // [MENU API] Ambil detail satu menu
    if ($action == 'get_menu_details' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT id, name, description, price, discount_price, category, stock, image_url, is_available FROM menu WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $response = ($result->num_rows === 1)
                ? ['success' => true, 'data' => $result->fetch_assoc()]
                : ['success' => false, 'message' => 'Menu tidak ditemukan.'];
        } else {
            $response['message'] = 'Gagal mengambil detail menu.';
        }
        $stmt->close();
        echo json_encode($response);
        exit();
    }

    // [MENU API] Hapus menu
    if ($action == 'delete_menu' && strtoupper($_SERVER['REQUEST_METHOD']) === 'DELETE' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt_img = $conn->prepare("SELECT image_url, name FROM menu WHERE id = ?");
        $stmt_img->bind_param("i", $id);
        $stmt_img->execute();
        $item = $stmt_img->get_result()->fetch_assoc();
        $stmt_img->close();

        $stmt_delete = $conn->prepare("DELETE FROM menu WHERE id = ?");
        $stmt_delete->bind_param("i", $id);
        if ($stmt_delete->execute() && $stmt_delete->affected_rows > 0) {
            if ($item && !empty($item['image_url']) && strpos($item['image_url'], 'uploads/') === 0 && file_exists("../" . $item['image_url'])) {
                unlink("../" . $item['image_url']);
            }
            $response = ['success' => true, 'message' => "Menu '" . htmlspecialchars($item['name']) . "' berhasil dihapus."];
        } else {
            $response['message'] = 'Gagal menghapus menu atau menu tidak ditemukan.';
        }
        $stmt_delete->close();
        echo json_encode($response);
        exit();
    }

    // [MENU API] Update menu
    if ($action == 'update_menu' && strtoupper($_SERVER["REQUEST_METHOD"]) === "POST") {
        if (empty($_POST['id']) || empty($_POST['name']) || empty($_POST['category']) || !isset($_POST['price']) || !isset($_POST['stock'])) {
            echo json_encode(['success' => false, 'message' => 'Semua field yang wajib diisi harus lengkap.']);
            exit();
        }

        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $discount_price = isset($_POST['discount_price']) && $_POST['discount_price'] !== '' ? floatval($_POST['discount_price']) : 0;
        $category = trim($_POST['category']);
        $stock = intval($_POST['stock']);
        $is_available = intval($_POST['is_available']);
        $old_image_url = $_POST['old_image_url'];
        $new_image_url = $old_image_url;

        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = "../uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            $file_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
            $new_filename = uniqid('menu_', true) . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($file_extension, $allowed_types)) {
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    if (!empty($old_image_url) && strpos($old_image_url, 'uploads/') === 0 && file_exists("../" . $old_image_url)) {
                        unlink("../" . $old_image_url);
                    }
                    $new_image_url = "uploads/" . $new_filename;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Gagal mengupload gambar baru.']);
                    exit();
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Tipe file gambar tidak valid.']);
                exit();
            }
        }

        $sql = "UPDATE menu SET name = ?, description = ?, price = ?, discount_price = ?, category = ?, stock = ?, image_url = ?, is_available = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssddsisii", $name, $description, $price, $discount_price, $category, $stock, $new_image_url, $is_available, $id);

        if ($stmt->execute()) {
            $stmt_get_updated = $conn->prepare("SELECT id, name, description, price, discount_price, category, stock, image_url, is_available FROM menu WHERE id = ?");
            $stmt_get_updated->bind_param("i", $id);
            $stmt_get_updated->execute();
            $updated_data = $stmt_get_updated->get_result()->fetch_assoc();
            $stmt_get_updated->close();
            echo json_encode(['success' => true, 'message' => "Menu '" . htmlspecialchars($name) . "' berhasil diperbarui.", 'data' => $updated_data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui menu: ' . $stmt->error]);
        }
        $stmt->close();
        exit();
    }

    // [KATEGORI API] Ambil semua kategori
    if ($action == 'get_categories' && strtoupper($_SERVER['REQUEST_METHOD']) === 'GET') {
        $sql = "SELECT c.id, c.name, (SELECT COUNT(*) FROM menu WHERE category = c.name) as menu_count FROM menu_categories c ORDER BY c.name ASC";
        $result = $conn->query($sql);
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $row['is_in_use'] = $row['menu_count'] > 0;
            $categories[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $categories]);
        exit();
    }

    // [KATEGORI API] Tambah kategori baru
    if ($action == 'add_category' && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            $response['message'] = 'Nama kategori tidak boleh kosong.';
        } else {
            $stmt = $conn->prepare("INSERT INTO menu_categories (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Kategori berhasil ditambahkan.'];
            } else {
                $response['message'] = 'Gagal menambahkan kategori. Mungkin sudah ada.';
            }
            $stmt->close();
        }
        echo json_encode($response);
        exit();
    }

    // [KATEGORI API] Update nama kategori
    if ($action == 'update_category' && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        $newName = trim($data['name'] ?? '');

        if (empty($newName) || $id === 0) {
            $response['message'] = 'Data tidak lengkap.';
        } else {
            $conn->begin_transaction();
            try {
                $stmt_old = $conn->prepare("SELECT name FROM menu_categories WHERE id = ?");
                $stmt_old->bind_param("i", $id);
                $stmt_old->execute();
                $result_old = $stmt_old->get_result();
                $oldNameRow = $result_old->fetch_assoc();
                $oldName = $oldNameRow['name'] ?? null;
                $stmt_old->close();

                if ($oldName) {
                    $stmt_update_cat = $conn->prepare("UPDATE menu_categories SET name = ? WHERE id = ?");
                    $stmt_update_cat->bind_param("si", $newName, $id);
                    $stmt_update_cat->execute();
                    $stmt_update_cat->close();

                    $stmt_update_menu = $conn->prepare("UPDATE menu SET category = ? WHERE category = ?");
                    $stmt_update_menu->bind_param("ss", $newName, $oldName);
                    $stmt_update_menu->execute();
                    $stmt_update_menu->close();

                    $conn->commit();
                    $response = ['success' => true, 'message' => 'Kategori berhasil diperbarui.'];
                } else {
                    throw new Exception('Kategori lama tidak ditemukan.');
                }
            } catch (Exception $e) {
                $conn->rollback();
                $response['message'] = 'Gagal memperbarui kategori: ' . $e->getMessage();
            }
        }
        echo json_encode($response);
        exit();
    }

    // [KATEGORI API] Hapus kategori
    if ($action == 'delete_category' && strtoupper($_SERVER['REQUEST_METHOD']) === 'DELETE' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt_check = $conn->prepare("SELECT (SELECT COUNT(*) FROM menu WHERE category = c.name) as menu_count FROM menu_categories c WHERE c.id = ?");
        $stmt_check->bind_param("i", $id);
        $stmt_check->execute();
        $result = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($result && $result['menu_count'] > 0) {
            $response['message'] = 'Kategori tidak dapat dihapus karena masih digunakan oleh menu.';
        } else {
            $stmt_delete = $conn->prepare("DELETE FROM menu_categories WHERE id = ?");
            $stmt_delete->bind_param("i", $id);
            if ($stmt_delete->execute() && $stmt_delete->affected_rows > 0) {
                $response = ['success' => true, 'message' => 'Kategori berhasil dihapus.'];
            } else {
                $response['message'] = 'Gagal menghapus kategori atau kategori tidak ditemukan.';
            }
            $stmt_delete->close();
        }
        echo json_encode($response);
        exit();
    }

    echo json_encode($response);
    exit();
}

// =================================================================
// BAGIAN RENDER HALAMAN
// =================================================================
$menu_items = [];
$result = $conn->query("SELECT id, name, description, price, discount_price, category, stock, image_url, is_available FROM menu ORDER BY name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) $menu_items[] = $row;
}

// Ambil semua kategori untuk dropdown
$categories = [];
$cat_result = $conn->query("SELECT name FROM menu_categories ORDER BY name ASC");
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) $categories[] = $row['name'];
}

$success_message_session = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

require_once '../includes/header.php';
?>

<div class="container mx-auto p-4 md:p-6 lg:p-8">
    <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2">Kelola Menu</h1>
    <p class="text-gray-600 mb-8">Atur daftar menu yang tersedia di restoran Anda secara real-time.</p>

    <div id="ajax-notification-container" class="fixed top-5 right-5 z-[100] w-full max-w-xs sm:max-w-sm"></div>

    <?php if ($success_message_session): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof showAjaxNotification === 'function') {
                    showAjaxNotification('<?= addslashes(htmlspecialchars($success_message_session)) ?>', 'success');
                }
            });
        </script>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row justify-between items-center mb-6 bg-white p-4 rounded-lg shadow-md gap-4">
        <div class="flex flex-col sm:flex-row w-full md:w-auto gap-3">
            <!-- [PERUBAHAN] Mengubah <a> menjadi <button> -->
            <button id="openAddMenuModalBtn" type="button" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center justify-center transition-colors">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                </svg>
                <span>Tambah Menu</span>
            </button>
            <button id="openCategoryModalBtn" type="button" class="w-full sm:w-auto bg-gray-700 hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center justify-center transition-colors">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M.99 6a1 1 0 011-1h1.492A2.002 2.002 0 015.48 3.992l.512.512A2.002 2.002 0 007.484 5H10a1 1 0 110 2H7.484a2.002 2.002 0 00-1.492.512l-.512.512A2.002 2.002 0 013.488 9H2a1 1 0 110-2h1.488a2.002 2.002 0 001.492-.512l.512-.512A2.002 2.002 0 017.484 5H9a1 1 0 011 1zm8.707-1.707a1 1 0 00-1.414-1.414l-1.5 1.5a1 1 0 001.414 1.414l1.5-1.5zM.99 12a1 1 0 011-1h1.492a2.002 2.002 0 011.998-1.48l.512.512A2.002 2.002 0 007.484 11H10a1 1 0 110 2H7.484a2.002 2.002 0 00-1.492.512l-.512.512A2.002 2.002 0 013.488 15H2a1 1 0 110-2h1.488a2.002 2.002 0 001.492-.512l.512-.512A2.002 2.002 0 017.484 11H9a1 1 0 110-2H7.484a2.002 2.002 0 00-1.492.512l-.512.512A2.002 2.002 0 013.488 9H2a1 1 0 01-1-1zm8.707 5.293a1 1 0 00-1.414-1.414l-1.5 1.5a1 1 0 001.414 1.414l1.5-1.5z"></path>
                </svg>
                <span>Kelola Kategori</span>
            </button>
        </div>
        <div class="w-full md:w-auto md:max-w-xs">
            <div class="relative"><span class="absolute inset-y-0 left-0 flex items-center pl-3"><svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                    </svg></span><input type="text" id="searchInput" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" placeholder="Cari menu..."></div>
        </div>
    </div>

    <div id="menuGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php if (!empty($menu_items)): ?>
            <?php foreach ($menu_items as $item): ?>
                <div class="menu-card bg-white rounded-xl shadow-lg overflow-hidden flex flex-col transition-transform transform hover:-translate-y-2" data-id="<?= $item['id'] ?>" data-name="<?= strtolower(htmlspecialchars($item['name'])) ?>" data-category="<?= strtolower(htmlspecialchars($item['category'])) ?>">
                    <div class="h-48 bg-gray-200 flex items-center justify-center relative">
                        <img src="../<?= !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : 'https://placehold.co/400x300/e2e8f0/64748b?text=Gambar' ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover">
                        <?php if ($item['discount_price'] > 0 && $item['discount_price'] < $item['price']): ?><span class="absolute top-2 left-2 py-1 px-3 text-xs font-bold rounded-full bg-red-600 text-white animate-pulse">DISKON!</span><?php endif; ?>
                        <span class="status-badge absolute top-2 right-2 py-1 px-3 text-xs font-semibold rounded-full <?= $item['is_available'] ? 'bg-green-500 text-white' : 'bg-red-500 text-white' ?>"><?= $item['is_available'] ? 'Tersedia' : 'Habis' ?></span>
                    </div>
                    <div class="p-5 flex-grow">
                        <h3 class="text-xl font-bold text-gray-800 mb-2 truncate"><?= htmlspecialchars($item['name']) ?></h3>
                        <p class="text-sm text-gray-600 mb-3 capitalize">Kategori: <span class="font-medium"><?= htmlspecialchars($item['category']) ?></span></p>
                        <div class="price-container">
                            <?php if ($item['discount_price'] > 0 && $item['discount_price'] < $item['price']): ?>
                                <div><del class="text-sm text-gray-500">Rp <?= number_format($item['price']) ?></del>
                                    <p class="text-lg font-semibold text-red-600">Rp <?= number_format($item['discount_price']) ?></p>
                                </div>
                            <?php else: ?>
                                <p class="text-lg font-semibold text-blue-600">Rp <?= number_format($item['price']) ?></p>
                            <?php endif; ?>
                        </div>
                        <p class="stock-text text-sm font-medium mt-2 <?= $item['stock'] <= 5 && $item['stock'] > 0 ? 'text-yellow-600' : ($item['stock'] == 0 ? 'text-red-600' : 'text-gray-700') ?>">Stok: <?= $item['stock'] ?></p>
                    </div>
                    <div class="p-4 bg-gray-50 border-t flex justify-end items-center gap-3">
                        <button type="button" class="edit-menu-btn text-sm bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-4 rounded-lg transition-colors" data-id="<?= $item['id'] ?>">Edit</button>
                        <button type="button" class="delete-menu-btn text-sm bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors" data-id="<?= $item['id'] ?>" data-name="<?= htmlspecialchars($item['name']) ?>">Hapus</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div id="no-menu-placeholder" class="col-span-full bg-white p-12 rounded-lg shadow-md text-center">
                <h3 class="text-xl font-semibold text-gray-700">Belum Ada Menu</h3>
                <p class="text-gray-500 mt-2">Silakan klik tombol "Tambah Menu" untuk mulai.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ================================================================= -->
<!-- MODALS -->
<!-- ================================================================= -->

<!-- [BARU] Modal untuk TAMBAH MENU -->
<div id="addMenuModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden items-center justify-center p-4 transition-opacity duration-300 opacity-0">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl transform transition-transform duration-300 scale-95 max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-center p-4 border-b sticky top-0 bg-white z-10">
            <h3 class="text-xl font-bold text-gray-800">Tambah Menu Baru</h3>
            <button id="closeAddMenuModal" class="text-gray-400 hover:text-gray-800 text-3xl leading-none">&times;</button>
        </div>
        <div class="p-6 overflow-y-auto">
            <form id="addMenuForm" method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="action" value="add_menu">
                <div>
                    <label for="add_name" class="block text-sm font-medium text-gray-700 mb-1">Nama Menu <span class="text-red-500">*</span></label>
                    <input type="text" id="add_name" name="name" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="cth: Nasi Goreng Spesial" required>
                </div>
                <div>
                    <label for="add_description" class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                    <textarea id="add_description" name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="cth: Nasi goreng dengan telur, ayam, dan udang."></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="add_category" class="block text-sm font-medium text-gray-700 mb-1">Kategori <span class="text-red-500">*</span></label>
                        <select id="add_category" name="category" class="w-full px-4 py-2 border border-gray-300 rounded-lg" required>
                            <option value="" disabled selected>Pilih Kategori</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="add_stock" class="block text-sm font-medium text-gray-700 mb-1">Stok <span class="text-red-500">*</span></label>
                        <input type="number" id="add_stock" name="stock" class="w-full px-4 py-2 border border-gray-300 rounded-lg" min="0" value="0" required>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="add_price" class="block text-sm font-medium text-gray-700 mb-1">Harga Normal (Rp) <span class="text-red-500">*</span></label>
                        <input type="number" id="add_price" name="price" class="w-full px-4 py-2 border border-gray-300 rounded-lg" min="0" placeholder="cth: 25000" required>
                    </div>
                    <div>
                        <label for="add_discount_price" class="block text-sm font-medium text-gray-700 mb-1">Harga Diskon (Rp)</label>
                        <input type="number" id="add_discount_price" name="discount_price" class="w-full px-4 py-2 border border-gray-300 rounded-lg" min="0" placeholder="Kosongkan jika tidak ada diskon">
                    </div>
                </div>
                <div>
                    <label for="add_image" class="block text-sm font-medium text-gray-700 mb-1">Gambar Menu</label>
                    <input type="file" id="add_image" name="image" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-500 mt-2">Format yang didukung: JPG, PNG, GIF, WEBP.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status Ketersediaan</label>
                    <div class="flex items-center gap-6">
                        <label class="flex items-center"><input type="radio" name="is_available" value="1" class="form-radio" checked><span class="ml-2">Tersedia</span></label>
                        <label class="flex items-center"><input type="radio" name="is_available" value="0" class="form-radio"><span class="ml-2">Habis</span></label>
                    </div>
                </div>
                <div class="flex justify-end gap-4 border-t pt-6 mt-4">
                    <button type="button" id="cancelAddMenu" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded-lg">Batal</button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg flex items-center justify-center">Simpan Menu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal untuk EDIT MENU -->
<div id="editMenuModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden items-center justify-center p-4 transition-opacity duration-300 opacity-0">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl transform transition-transform duration-300 scale-95 max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-center p-4 border-b sticky top-0 bg-white z-10">
            <h3 class="text-xl font-bold text-gray-800">Edit Menu</h3>
            <button id="closeEditMenuModal" class="text-gray-400 hover:text-gray-800 text-3xl leading-none">&times;</button>
        </div>
        <div id="editMenuModalContent" class="p-6 overflow-y-auto">
            <div class="text-center py-10">Memuat data...</div>
        </div>
    </div>
</div>

<!-- Modal untuk KELOLA KATEGORI -->
<div id="categoryModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden items-center justify-center p-4 transition-opacity duration-300 opacity-0">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md transform transition-transform duration-300 scale-95">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-xl font-bold text-gray-800">Kelola Kategori</h3>
            <button id="closeCategoryModal" class="text-gray-400 hover:text-gray-800 text-3xl leading-none">&times;</button>
        </div>
        <div class="p-6">
            <div id="categoryListContainer" class="max-h-64 overflow-y-auto mb-6 pr-2 space-y-2">
                <div class="text-center text-gray-500 py-4">Memuat data...</div>
            </div>
            <form id="addCategoryForm" class="border-t pt-4">
                <label for="newCategoryName" class="text-sm font-medium text-gray-700 mb-2 block">Tambah Kategori Baru:</label>
                <div id="category-error-message" class="text-red-500 text-sm mb-2"></div>
                <div class="flex gap-3">
                    <input type="text" id="newCategoryName" name="name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="cth: Makanan Berat" required>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg flex items-center justify-center shrink-0"><span>Tambah</span></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal KONFIRMASI -->
<div id="confirmationModal" class="fixed inset-0 bg-black bg-opacity-60 z-[60] hidden items-center justify-center p-4 transition-opacity duration-300 opacity-0">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-sm transform transition-transform duration-300 scale-95">
        <div class="p-6 text-center">
            <svg class="mx-auto mb-4 text-yellow-500 w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <h3 id="confirmationModalTitle" class="mb-2 text-xl font-bold text-gray-800">Judul Konfirmasi</h3>
            <p id="confirmationModalMessage" class="mb-6 text-gray-600">Pesan konfirmasi akan muncul di sini.</p>
            <div class="flex justify-center gap-4">
                <button id="confirmationModalCancel" type="button" class="py-2 px-6 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-lg">Batal</button>
                <button id="confirmationModalConfirm" type="button" class="py-2 px-6 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg">Ya, Lanjutkan</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const htmlspecialchars = (str) => {
            if (typeof str !== 'string') return '';
            return str.replace(/[&<>"']/g, match => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            })[match]);
        };

        const notificationContainer = document.getElementById('ajax-notification-container');
        const showAjaxNotification = (message, type = 'success') => {
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            const notification = document.createElement('div');
            notification.className = `p-4 rounded-lg text-white ${bgColor} shadow-lg mb-3 transform translate-x-full opacity-0 transition-all duration-300 ease-out`;
            notification.textContent = message;
            notificationContainer.appendChild(notification);
            setTimeout(() => notification.classList.remove('translate-x-full', 'opacity-0'), 10);
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(20px)';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        };

        const confirmationModal = document.getElementById('confirmationModal');
        const confirmationModalTitle = document.getElementById('confirmationModalTitle');
        const confirmationModalMessage = document.getElementById('confirmationModalMessage');
        const confirmationModalConfirm = document.getElementById('confirmationModalConfirm');
        const confirmationModalCancel = document.getElementById('confirmationModalCancel');
        let onConfirmCallback = () => {};

        const showConfirmationModal = (title, message, callback) => {
            confirmationModalTitle.textContent = title;
            confirmationModalMessage.textContent = message;
            onConfirmCallback = callback;
            confirmationModal.classList.remove('hidden');
            confirmationModal.classList.add('flex');
            setTimeout(() => {
                confirmationModal.style.opacity = '1';
                confirmationModal.querySelector('div > div').style.transform = 'scale(1)';
            }, 10);
        };

        const hideConfirmationModal = () => {
            confirmationModal.style.opacity = '0';
            confirmationModal.querySelector('div > div').style.transform = 'scale(0.95)';
            setTimeout(() => {
                confirmationModal.classList.add('hidden');
                confirmationModal.classList.remove('flex');
            }, 300);
        };

        confirmationModalConfirm.addEventListener('click', () => {
            if (typeof onConfirmCallback === 'function') onConfirmCallback();
            hideConfirmationModal();
        });
        confirmationModalCancel.addEventListener('click', hideConfirmationModal);
        confirmationModal.addEventListener('click', (e) => {
            if (e.target === confirmationModal) hideConfirmationModal();
        });

        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase().trim();
            document.querySelectorAll('.menu-card').forEach(card => {
                let menuName = card.dataset.name;
                card.style.display = menuName.includes(filter) ? 'flex' : 'none';
            });
        });

        const menuGrid = document.getElementById('menuGrid');

        const renderMenuCardHTML = (item) => {
            const imageUrl = item.image_url ? `../${htmlspecialchars(item.image_url)}` : 'https://placehold.co/400x300/e2e8f0/64748b?text=Gambar';
            const discountBadge = item.discount_price > 0 && parseFloat(item.discount_price) < parseFloat(item.price) ? `<span class="absolute top-2 left-2 py-1 px-3 text-xs font-bold rounded-full bg-red-600 text-white animate-pulse">DISKON!</span>` : '';
            const availabilityBadge = `<span class="status-badge absolute top-2 right-2 py-1 px-3 text-xs font-semibold rounded-full ${item.is_available == 1 ? 'bg-green-500 text-white' : 'bg-red-500 text-white'}">${item.is_available == 1 ? 'Tersedia' : 'Habis'}</span>`;
            let priceHTML = (item.discount_price > 0 && parseFloat(item.discount_price) < parseFloat(item.price)) ?
                `<div><del class="text-sm text-gray-500">Rp ${Number(item.price).toLocaleString('id-ID')}</del><p class="text-lg font-semibold text-red-600">Rp ${Number(item.discount_price).toLocaleString('id-ID')}</p></div>` :
                `<p class="text-lg font-semibold text-blue-600">Rp ${Number(item.price).toLocaleString('id-ID')}</p>`;
            const stockClass = item.stock <= 5 && item.stock > 0 ? 'text-yellow-600' : (item.stock == 0 ? 'text-red-600' : 'text-gray-700');

            return `<div class="h-48 bg-gray-200 flex items-center justify-center relative">
                    <img src="${imageUrl}" alt="${htmlspecialchars(item.name)}" class="w-full h-full object-cover">
                    ${discountBadge} ${availabilityBadge}
                </div>
                <div class="p-5 flex-grow">
                    <h3 class="text-xl font-bold text-gray-800 mb-2 truncate">${htmlspecialchars(item.name)}</h3>
                    <p class="text-sm text-gray-600 mb-3 capitalize">Kategori: <span class="font-medium">${htmlspecialchars(item.category)}</span></p>
                    <div class="price-container">${priceHTML}</div>
                    <p class="stock-text text-sm font-medium mt-2 ${stockClass}">Stok: ${item.stock}</p>
                </div>
                <div class="p-4 bg-gray-50 border-t flex justify-end items-center gap-3">
                    <button type="button" class="edit-menu-btn text-sm bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-4 rounded-lg transition-colors" data-id="${item.id}">Edit</button>
                    <button type="button" class="delete-menu-btn text-sm bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors" data-id="${item.id}" data-name="${htmlspecialchars(item.name)}">Hapus</button>
                </div>`;
        };

        // [BARU] Fungsi untuk menambahkan kartu menu baru ke grid
        const addMenuCardToDOM = (item) => {
            const placeholder = document.getElementById('no-menu-placeholder');
            if (placeholder) {
                placeholder.remove();
            }
            const newCard = document.createElement('div');
            newCard.className = 'menu-card bg-white rounded-xl shadow-lg overflow-hidden flex flex-col transition-transform transform hover:-translate-y-2';
            newCard.dataset.id = item.id;
            newCard.dataset.name = item.name.toLowerCase();
            newCard.dataset.category = item.category.toLowerCase();
            newCard.innerHTML = renderMenuCardHTML(item);
            menuGrid.appendChild(newCard);
        };

        const updateMenuCardInDOM = (updatedData) => {
            const card = menuGrid.querySelector(`.menu-card[data-id="${updatedData.id}"]`);
            if (card) {
                card.dataset.name = updatedData.name.toLowerCase();
                card.dataset.category = updatedData.category.toLowerCase();
                card.innerHTML = renderMenuCardHTML(updatedData);
            }
        };

        // =================================================================
        // [BARU] Logika Modal TAMBAH MENU
        // =================================================================
        const addMenuModal = document.getElementById('addMenuModal');
        const addMenuForm = document.getElementById('addMenuForm');

        const toggleAddMenuModal = (show) => {
            const modalContentDiv = addMenuModal.querySelector('div');
            if (show) {
                addMenuModal.classList.remove('hidden');
                addMenuModal.classList.add('flex');
                setTimeout(() => {
                    addMenuModal.style.opacity = '1';
                    modalContentDiv.style.transform = 'scale(1)';
                }, 10);
            } else {
                addMenuModal.style.opacity = '0';
                modalContentDiv.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    addMenuModal.classList.add('hidden');
                    addMenuModal.classList.remove('flex');
                    addMenuForm.reset(); // Reset form saat ditutup
                }, 300);
            }
        };

        document.getElementById('openAddMenuModalBtn').addEventListener('click', () => toggleAddMenuModal(true));
        document.getElementById('closeAddMenuModal').addEventListener('click', () => toggleAddMenuModal(false));
        document.getElementById('cancelAddMenu').addEventListener('click', () => toggleAddMenuModal(false));
        addMenuModal.addEventListener('click', (e) => {
            if (e.target === addMenuModal) toggleAddMenuModal(false);
        });

        addMenuForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitButton = addMenuForm.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = `<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span>Menyimpan...</span>`;

            const formData = new FormData(addMenuForm);

            try {
                const response = await fetch('kelolamenu.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (!result.success) throw new Error(result.message);

                showAjaxNotification(result.message, 'success');
                addMenuCardToDOM(result.data);
                toggleAddMenuModal(false);
            } catch (error) {
                showAjaxNotification(error.message, 'error');
            } finally {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
        });

        // =================================================================
        // Logika Modal EDIT MENU
        // =================================================================
        const editMenuModal = document.getElementById('editMenuModal');
        const editMenuModalContent = document.getElementById('editMenuModalContent');

        menuGrid.addEventListener('click', async (e) => {
            const target = e.target;
            if (target.classList.contains('edit-menu-btn')) {
                const menuId = target.dataset.id;
                toggleEditMenuModal(true);
                try {
                    const response = await fetch(`?action=get_menu_details&id=${menuId}`);
                    const result = await response.json();
                    if (!result.success) throw new Error(result.message || 'Gagal memuat data menu.');
                    renderEditForm(result.data);
                } catch (error) {
                    editMenuModalContent.innerHTML = `<div class="text-center text-red-500 py-10">${error.message}</div>`;
                }
            }

            if (target.classList.contains('delete-menu-btn')) {
                const menuId = target.dataset.id;
                const menuName = target.dataset.name;
                showConfirmationModal(
                    'Hapus Menu',
                    `Yakin ingin menghapus menu "${menuName}"? Aksi ini tidak dapat dibatalkan.`,
                    async () => {
                        try {
                            const response = await fetch(`?action=delete_menu&id=${menuId}`, {
                                method: 'DELETE'
                            });
                            const result = await response.json();
                            if (!result.success) throw new Error(result.message);
                            showAjaxNotification(result.message, 'success');
                            target.closest('.menu-card').remove();
                            if (menuGrid.children.length === 0) {
                                menuGrid.innerHTML = `<div id="no-menu-placeholder" class="col-span-full bg-white p-12 rounded-lg shadow-md text-center"><h3 class="text-xl font-semibold text-gray-700">Belum Ada Menu</h3><p class="text-gray-500 mt-2">Silakan klik tombol "Tambah Menu" untuk mulai.</p></div>`;
                            }
                        } catch (error) {
                            showAjaxNotification(error.message, 'error');
                        }
                    }
                );
            }
        });

        const toggleEditMenuModal = (show) => {
            const modalContentDiv = editMenuModal.querySelector('div');
            if (show) {
                editMenuModal.classList.remove('hidden');
                editMenuModal.classList.add('flex');
                setTimeout(() => {
                    editMenuModal.style.opacity = '1';
                    modalContentDiv.style.transform = 'scale(1)';
                }, 10);
            } else {
                editMenuModal.style.opacity = '0';
                modalContentDiv.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    editMenuModal.classList.add('hidden');
                    editMenuModal.classList.remove('flex');
                    editMenuModalContent.innerHTML = '<div class="text-center py-10">Memuat data...</div>';
                }, 300);
            }
        };

        const renderEditForm = (item) => {
            const categoryOptions = document.getElementById('categoryOptionsContainer').innerHTML;
            editMenuModalContent.innerHTML = `<form id="editMenuForm" class="space-y-6">
            <input type="hidden" name="action" value="update_menu">
            <input type="hidden" name="id" value="${item.id}">
            <input type="hidden" name="old_image_url" value="${htmlspecialchars(item.image_url || '')}">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Nama Menu <span class="text-red-500">*</span></label><input type="text" name="name" value="${htmlspecialchars(item.name)}" class="w-full px-4 py-2 border border-gray-300 rounded-lg" required></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label><textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg">${htmlspecialchars(item.description || '')}</textarea></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Kategori <span class="text-red-500">*</span></label><select name="category" id="edit_category" class="w-full px-4 py-2 border border-gray-300 rounded-lg" required>${categoryOptions}</select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Stok <span class="text-red-500">*</span></label><input type="number" name="stock" value="${item.stock}" class="w-full px-4 py-2 border border-gray-300 rounded-lg" required min="0"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Harga Normal (Rp) <span class="text-red-500">*</span></label><input type="number" name="price" value="${item.price}" class="w-full px-4 py-2 border border-gray-300 rounded-lg" required min="0"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Harga Diskon (Rp)</label><input type="number" name="discount_price" value="${item.discount_price > 0 ? item.discount_price : ''}" class="w-full px-4 py-2 border border-gray-300 rounded-lg" min="0" placeholder="Kosongkan jika tidak ada"></div>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Ganti Gambar Menu</label><div class="mt-2 flex items-center gap-4"><img src="../${item.image_url || 'https://placehold.co/100x100/e2e8f0/64748b?text=Lama'}" class="h-20 w-20 rounded-lg object-cover"><input type="file" name="image" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"></div><p class="text-xs text-gray-500 mt-2">Kosongkan jika tidak ingin mengubah gambar.</p></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Status Ketersediaan</label><div class="flex items-center gap-6"><label class="flex items-center"><input type="radio" name="is_available" value="1" class="form-radio" ${item.is_available == 1 ? 'checked' : ''}><span class="ml-2">Tersedia</span></label><label class="flex items-center"><input type="radio" name="is_available" value="0" class="form-radio" ${item.is_available == 0 ? 'checked' : ''}><span class="ml-2">Habis</span></label></div></div>
            <div class="flex justify-end gap-4 border-t pt-6 mt-4">
                <button type="button" id="cancelEditMenu" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded-lg">Batal</button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg flex items-center justify-center">Simpan</button>
            </div>
        </form>`;
            document.getElementById('edit_category').value = item.category;
            document.getElementById('cancelEditMenu').addEventListener('click', () => toggleEditMenuModal(false));
        };

        editMenuModalContent.addEventListener('submit', async (e) => {
            if (e.target.id === 'editMenuForm') {
                e.preventDefault();
                const form = e.target;
                const submitButton = form.querySelector('button[type="submit"]');
                const originalButtonText = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = `<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span>Menyimpan...</span>`;
                const formData = new FormData(form);
                try {
                    const response = await fetch('kelolamenu.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (!result.success) throw new Error(result.message);
                    showAjaxNotification(result.message, 'success');
                    updateMenuCardInDOM(result.data);
                    toggleEditMenuModal(false);
                } catch (error) {
                    showAjaxNotification(error.message, 'error');
                } finally {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }
            }
        });

        document.getElementById('closeEditMenuModal').addEventListener('click', () => toggleEditMenuModal(false));
        editMenuModal.addEventListener('click', (e) => {
            if (e.target === editMenuModal) toggleEditMenuModal(false);
        });

        const categoryModal = document.getElementById('categoryModal');
        const categoryModalContent = categoryModal.querySelector('div > div');
        const openCategoryModalBtn = document.getElementById('openCategoryModalBtn');
        const closeCategoryModalBtn = document.getElementById('closeCategoryModal');
        const addCategoryForm = document.getElementById('addCategoryForm');
        const categoryListContainer = document.getElementById('categoryListContainer');
        const categoryError = document.getElementById('category-error-message');

        const categoryOptionsContainer = document.createElement('div');
        categoryOptionsContainer.id = 'categoryOptionsContainer';
        categoryOptionsContainer.style.display = 'none';
        document.body.appendChild(categoryOptionsContainer);

        const updateCategoryDropdowns = async () => {
            try {
                const response = await fetch(`?action=get_categories`);
                const result = await response.json();
                if (!result.success) return;
                let optionsHTML = '';
                result.data.forEach(cat => {
                    optionsHTML += `<option value="${htmlspecialchars(cat.name)}">${htmlspecialchars(cat.name)}</option>`;
                });
                categoryOptionsContainer.innerHTML = optionsHTML;
            } catch (e) {
                console.error("Gagal update dropdown kategori:", e);
            }
        };
        updateCategoryDropdowns();

        const toggleCategoryModal = (show) => {
            if (show) {
                categoryModal.classList.remove('hidden');
                categoryModal.classList.add('flex');
                setTimeout(() => {
                    categoryModal.style.opacity = '1';
                    categoryModalContent.style.transform = 'scale(1)';
                }, 10);
                fetchCategories();
            } else {
                categoryModal.style.opacity = '0';
                categoryModalContent.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    categoryModal.classList.add('hidden');
                    categoryModal.classList.remove('flex');
                    categoryError.textContent = '';
                }, 300);
            }
        };

        const fetchCategories = async () => {
            categoryListContainer.innerHTML = '<div class="text-center text-gray-500 py-4">Memuat data...</div>';
            try {
                const response = await fetch(`?action=get_categories`);
                if (!response.ok) throw new Error('Gagal mengambil data dari server.');
                const result = await response.json();
                if (!result.success) throw new Error(result.message);
                renderCategories(result.data);
            } catch (error) {
                categoryListContainer.innerHTML = `<div class="text-center text-red-500 py-4">${error.message}</div>`;
            }
        };

        const renderCategories = (categories) => {
            categoryListContainer.innerHTML = (categories.length === 0) ?
                '<div class="text-center text-gray-500 py-4">Belum ada kategori.</div>' :
                '';
            categories.forEach(category => {
                const categoryEl = document.createElement('div');
                categoryEl.className = 'category-item flex justify-between items-center bg-gray-50 p-2 rounded-lg';
                categoryEl.dataset.id = category.id;
                const deleteBtnDisabled = category.is_in_use ? 'disabled' : '';
                const deleteBtnClasses = category.is_in_use ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-red-500 hover:bg-red-600 text-white';
                const deleteBtnTitle = category.is_in_use ? 'Kategori ini digunakan oleh menu' : 'Hapus kategori';
                categoryEl.innerHTML = `<div class="flex-grow flex items-center">
                <span class="category-name text-gray-800">${htmlspecialchars(category.name)}</span>
                <input type="text" class="category-edit-input hidden w-full px-2 py-1 border rounded" value="${htmlspecialchars(category.name)}">
            </div>
            <div class="flex-shrink-0 flex items-center gap-2 ml-4">
                <div class="display-mode">
                    <button class="edit-category-btn text-xs font-semibold py-1 px-2 rounded-md bg-yellow-500 hover:bg-yellow-600 text-white" data-id="${category.id}">Edit</button>
                    <button class="delete-category-btn text-xs font-semibold py-1 px-2 rounded-md ${deleteBtnClasses}" data-id="${category.id}" data-name="${htmlspecialchars(category.name)}" ${deleteBtnDisabled} title="${deleteBtnTitle}">Hapus</button>
                </div>
                <div class="edit-mode hidden">
                    <button class="save-category-btn text-xs font-semibold py-1 px-2 rounded-md bg-green-500 hover:bg-green-600 text-white" data-id="${category.id}">Simpan</button>
                    <button class="cancel-edit-btn text-xs font-semibold py-1 px-2 rounded-md bg-gray-400 hover:bg-gray-500 text-white">Batal</button>
                </div>
            </div>`;
                categoryListContainer.appendChild(categoryEl);
            });
        };

        openCategoryModalBtn.addEventListener('click', () => toggleCategoryModal(true));
        closeCategoryModalBtn.addEventListener('click', () => toggleCategoryModal(false));
        categoryModal.addEventListener('click', (e) => {
            if (e.target === categoryModal) toggleCategoryModal(false);
        });

        addCategoryForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const newCategoryName = document.getElementById('newCategoryName').value.trim();
            categoryError.textContent = '';
            if (!newCategoryName) {
                categoryError.textContent = 'Nama kategori tidak boleh kosong.';
                return;
            }
            try {
                const response = await fetch(`?action=add_category`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        name: newCategoryName
                    })
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Terjadi kesalahan.');
                document.getElementById('newCategoryName').value = '';
                showAjaxNotification(result.message, 'success');
                fetchCategories();
                updateCategoryDropdowns();
            } catch (error) {
                categoryError.textContent = error.message;
            }
        });

        categoryListContainer.addEventListener('click', async (e) => {
            const target = e.target;
            const categoryItem = target.closest('.category-item');
            if (!categoryItem) return;
            const categoryId = categoryItem.dataset.id;

            if (target.classList.contains('delete-category-btn')) {
                const categoryName = target.dataset.name;
                showConfirmationModal('Hapus Kategori', `Yakin ingin menghapus kategori "${categoryName}"?`,
                    async () => {
                        try {
                            const response = await fetch(`?action=delete_category&id=${categoryId}`, {
                                method: 'DELETE'
                            });
                            const result = await response.json();
                            if (!response.ok || !result.success) throw new Error(result.message);
                            showAjaxNotification(result.message, 'success');
                            fetchCategories();
                            updateCategoryDropdowns();
                        } catch (error) {
                            showAjaxNotification(`Gagal menghapus: ${error.message}`, 'error');
                        }
                    }
                );
            } else if (target.classList.contains('edit-category-btn')) {
                categoryItem.querySelector('.display-mode').classList.add('hidden');
                categoryItem.querySelector('.edit-mode').classList.remove('hidden');
                categoryItem.querySelector('.category-name').classList.add('hidden');
                const input = categoryItem.querySelector('.category-edit-input');
                input.classList.remove('hidden');
                input.focus();
            } else if (target.classList.contains('cancel-edit-btn')) {
                categoryItem.querySelector('.display-mode').classList.remove('hidden');
                categoryItem.querySelector('.edit-mode').classList.add('hidden');
                categoryItem.querySelector('.category-name').classList.remove('hidden');
                const input = categoryItem.querySelector('.category-edit-input');
                input.classList.add('hidden');
                input.value = categoryItem.querySelector('.category-name').textContent;
            } else if (target.classList.contains('save-category-btn')) {
                const input = categoryItem.querySelector('.category-edit-input');
                const newName = input.value.trim();
                if (!newName) {
                    showAjaxNotification('Nama kategori tidak boleh kosong.', 'error');
                    return;
                }
                try {
                    const response = await fetch(`?action=update_category`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id: categoryId,
                            name: newName
                        })
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.message);
                    showAjaxNotification(result.message, 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } catch (error) {
                    showAjaxNotification(`Gagal menyimpan: ${error.message}`, 'error');
                }
            }
        });
    });
</script>

<?php
require_once '../includes/footer.php';
$conn->close();
?>