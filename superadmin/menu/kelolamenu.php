<?php
// Memulai session untuk menampilkan notifikasi sukses/gagal
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ganti path ini jika perlu
require_once '../../db_connect.php';

// =================================================================
// BAGIAN API & FORM HANDLER
// =================================================================
if (isset($_REQUEST['action'])) {
    $action = $_REQUEST['action'];

    // Handler yang mengandung file upload diletakkan di atas
    if ($action == 'add_menu' && strtoupper($_SERVER["REQUEST_METHOD"]) === "POST") {
        ob_start(); // PERBAIKAN: Mulai output buffering
        $response = ['success' => false, 'message' => 'Terjadi kesalahan yang tidak diketahui.'];

        do {
            if (empty($_POST['name']) || empty($_POST['category']) || !isset($_POST['price']) || !isset($_POST['stock'])) {
                $response['message'] = 'Nama, Kategori, Harga, dan Stok wajib diisi.';
                break;
            }

            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $price = floatval($_POST['price']);
            $discount_price = isset($_POST['discount_price']) && $_POST['discount_price'] !== '' ? floatval($_POST['discount_price']) : 0;
            $category = trim($_POST['category']);
            $stock = intval($_POST['stock']);
            $is_available = intval($_POST['is_available'] ?? 1);
            $addons = $_POST['addons'] ?? [];
            $image_url = null;

            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $target_dir = "../uploads/menu/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                $file_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
                $new_filename = uniqid('menu_', true) . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array($file_extension, $allowed_types)) {
                    if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                        $response['message'] = 'Gagal mengupload gambar.';
                        break;
                    }
                    $image_url = "uploads/menu/" . $new_filename;
                } else {
                    $response['message'] = 'Tipe file gambar tidak valid.';
                    break;
                }
            }

            $conn->begin_transaction();
            try {
                $sql = "INSERT INTO menu (name, description, price, discount_price, category, stock, image_url, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssddsisi", $name, $description, $price, $discount_price, $category, $stock, $image_url, $is_available);
                $stmt->execute();
                $new_menu_id = $conn->insert_id;
                $stmt->close();

                if (!empty($addons) && $new_menu_id) {
                    $stmt_addon = $conn->prepare("INSERT INTO menu_addons (menu_id, addon_group_id) VALUES (?, ?)");
                    foreach ($addons as $addon_group_id) {
                        $stmt_addon->bind_param("ii", $new_menu_id, $addon_group_id);
                        $stmt_addon->execute();
                    }
                    $stmt_addon->close();
                }
                $conn->commit();
                $message = "Menu '" . htmlspecialchars($name) . "' berhasil ditambahkan.";
                // Tetap set session untuk jaga-jaga, tapi notif utama via JS
                $_SESSION['success_message'] = $message;
                $response = ['success' => true, 'message' => $message];
            } catch (Exception $e) {
                $conn->rollback();
                $response['message'] = 'Gagal menambahkan menu: ' . $e->getMessage();
            }
        } while (false);

        ob_end_clean(); // PERBAIKAN: Hapus buffer untuk memastikan hanya JSON yang dikirim
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    if ($action == 'update_menu' && strtoupper($_SERVER["REQUEST_METHOD"]) === "POST") {
        ob_start(); // PERBAIKAN: Mulai output buffering
        $response = ['success' => false, 'message' => 'Terjadi kesalahan yang tidak diketahui.'];

        do {
            if (empty($_POST['id']) || empty($_POST['name']) || empty($_POST['category']) || !isset($_POST['price']) || !isset($_POST['stock'])) {
                $response['message'] = 'Semua field yang wajib diisi harus lengkap.';
                break;
            }

            $id = intval($_POST['id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $price = floatval($_POST['price']);
            $discount_price = isset($_POST['discount_price']) && $_POST['discount_price'] !== '' ? floatval($_POST['discount_price']) : 0;
            $category = trim($_POST['category']);
            $stock = intval($_POST['stock']);
            $is_available = intval($_POST['is_available'] ?? 0);
            $addons = $_POST['addons'] ?? [];
            $old_image_url = $_POST['old_image_url'] ?? '';
            $new_image_url = $old_image_url;

            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $target_dir = "../uploads/menu/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                $file_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
                $new_filename = uniqid('menu_', true) . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array($file_extension, $allowed_types)) {
                    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                        if (!empty($old_image_url) && strpos($old_image_url, 'uploads/menu/') === 0 && file_exists("../" . $old_image_url)) {
                            unlink("../" . $old_image_url);
                        }
                        $new_image_url = "uploads/menu/" . $new_filename;
                    } else {
                        $response['message'] = 'Gagal mengupload gambar baru.';
                        break;
                    }
                } else {
                    $response['message'] = 'Tipe file gambar tidak valid.';
                    break;
                }
            }

            $conn->begin_transaction();
            try {
                $sql = "UPDATE menu SET name = ?, description = ?, price = ?, discount_price = ?, category = ?, stock = ?, image_url = ?, is_available = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssddsisii", $name, $description, $price, $discount_price, $category, $stock, $new_image_url, $is_available, $id);
                $stmt->execute();
                $stmt->close();

                $stmt_delete_addons = $conn->prepare("DELETE FROM menu_addons WHERE menu_id = ?");
                $stmt_delete_addons->bind_param("i", $id);
                $stmt_delete_addons->execute();
                $stmt_delete_addons->close();

                if (!empty($addons)) {
                    $stmt_add_addons = $conn->prepare("INSERT INTO menu_addons (menu_id, addon_group_id) VALUES (?, ?)");
                    foreach ($addons as $addon_group_id) {
                        $stmt_add_addons->bind_param("ii", $id, $addon_group_id);
                        $stmt_add_addons->execute();
                    }
                    $stmt_add_addons->close();
                }
                $conn->commit();
                $message = "Menu '" . htmlspecialchars($name) . "' berhasil diperbarui.";
                // Tetap set session untuk jaga-jaga, tapi notif utama via JS
                $_SESSION['success_message'] = $message;
                $response = ['success' => true, 'message' => $message];
            } catch (Exception $e) {
                $conn->rollback();
                $response['message'] = 'Gagal memperbarui menu: ' . $e->getMessage();
            }
        } while (false);

        ob_end_clean(); // PERBAIKAN: Hapus buffer untuk memastikan hanya JSON yang dikirim
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Aksi tidak valid.'];

    switch ($action) {
        case 'get_menu_details':
            if (isset($_GET['id'])) {
                $id = intval($_GET['id']);
                $stmt = $conn->prepare("SELECT * FROM menu WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $response = ($result->num_rows === 1) ? ['success' => true, 'data' => $result->fetch_assoc()] : ['success' => false, 'message' => 'Menu tidak ditemukan.'];
                } else {
                    $response['message'] = 'Gagal mengambil detail menu.';
                }
                $stmt->close();
            }
            break;

        case 'delete_menu':
            if (strtoupper($_SERVER['REQUEST_METHOD']) === 'DELETE' && isset($_GET['id'])) {
                $id = intval($_GET['id']);
                $stmt_img = $conn->prepare("SELECT image_url, name FROM menu WHERE id = ?");
                $stmt_img->bind_param("i", $id);
                $stmt_img->execute();
                $item = $stmt_img->get_result()->fetch_assoc();
                $stmt_img->close();

                $stmt_delete = $conn->prepare("DELETE FROM menu WHERE id = ?");
                $stmt_delete->bind_param("i", $id);
                if ($stmt_delete->execute() && $stmt_delete->affected_rows > 0) {
                    if ($item && !empty($item['image_url']) && strpos($item['image_url'], 'uploads/menu/') === 0 && file_exists("../" . $item['image_url'])) {
                        unlink("../" . $item['image_url']);
                    }
                    $response = ['success' => true, 'message' => "Menu '" . htmlspecialchars($item['name']) . "' berhasil dihapus."];
                } else {
                    $response['message'] = 'Gagal menghapus menu atau menu tidak ditemukan.';
                }
                $stmt_delete->close();
            }
            break;

        case 'get_categories':
            $sql = "SELECT c.id, c.name, (SELECT COUNT(*) FROM menu WHERE category = c.name) as menu_count FROM menu_categories c ORDER BY c.name ASC";
            $result = $conn->query($sql);
            $categories_data = [];
            while ($row = $result->fetch_assoc()) {
                $row['is_in_use'] = $row['menu_count'] > 0;
                $categories_data[] = $row;
            }
            $response = ['success' => true, 'data' => $categories_data];
            break;

        case 'add_category':
            $data = json_decode(file_get_contents('php://input'), true);
            $name = trim($data['name'] ?? '');
            if (empty($name)) {
                $response['message'] = 'Nama kategori tidak boleh kosong.';
            } else {
                $stmt = $conn->prepare("INSERT INTO menu_categories (name) VALUES (?)");
                $stmt->bind_param("s", $name);
                $response = $stmt->execute() ? ['success' => true, 'message' => 'Kategori berhasil ditambahkan.'] : ['success' => false, 'message' => 'Gagal menambahkan kategori. Mungkin sudah ada.'];
                $stmt->close();
            }
            break;

        case 'update_category':
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
                    $oldName = $stmt_old->get_result()->fetch_assoc()['name'] ?? null;
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
            break;

        case 'delete_category':
            if (strtoupper($_SERVER['REQUEST_METHOD']) === 'DELETE' && isset($_GET['id'])) {
                $id = intval($_GET['id']);
                $stmt_check = $conn->prepare("SELECT (SELECT COUNT(*) FROM menu WHERE category = c.name) as menu_count FROM menu_categories c WHERE c.id = ?");
                $stmt_check->bind_param("i", $id);
                $stmt_check->execute();
                $result = $stmt_check->get_result()->fetch_assoc();
                $stmt_check->close();

                if ($result && $result['menu_count'] > 0) {
                    $response['message'] = 'Kategori tidak dapat dihapus karena masih digunakan.';
                } else {
                    $stmt_delete = $conn->prepare("DELETE FROM menu_categories WHERE id = ?");
                    $stmt_delete->bind_param("i", $id);
                    $response = $stmt_delete->execute() && $stmt_delete->affected_rows > 0 ? ['success' => true, 'message' => 'Kategori berhasil dihapus.'] : ['success' => false, 'message' => 'Gagal menghapus kategori.'];
                    $stmt_delete->close();
                }
            }
            break;

        case 'get_addons':
            $sql = "SELECT ag.id, ag.name FROM addon_groups ag ORDER BY ag.name ASC";
            $groups_result = $conn->query($sql);
            $addon_data = [];
            while ($group = $groups_result->fetch_assoc()) {
                $stmt_opts = $conn->prepare("SELECT id, name, price FROM addon_options WHERE addon_group_id = ? ORDER BY name ASC");
                $stmt_opts->bind_param("i", $group['id']);
                $stmt_opts->execute();
                $options_result = $stmt_opts->get_result();
                $options = [];
                while ($option = $options_result->fetch_assoc()) $options[] = $option;
                $group['options'] = $options;
                $addon_data[] = $group;
                $stmt_opts->close();
            }
            $response = ['success' => true, 'data' => $addon_data];
            break;

        case 'add_addon_group':
            $data = json_decode(file_get_contents('php://input'), true);
            $name = trim($data['name'] ?? '');
            if (!empty($name)) {
                $stmt = $conn->prepare("INSERT INTO addon_groups (name) VALUES (?)");
                $stmt->bind_param("s", $name);
                $response = $stmt->execute() ? ['success' => true, 'message' => 'Grup add-on berhasil ditambahkan.'] : ['success' => false, 'message' => 'Gagal menambahkan grup. Mungkin nama sudah ada.'];
                $stmt->close();
            } else {
                $response['message'] = 'Nama grup add-on tidak boleh kosong.';
            }
            break;

        case 'add_addon_option':
            $data = json_decode(file_get_contents('php://input'), true);
            $group_id = intval($data['group_id'] ?? 0);
            $name = trim($data['name'] ?? '');
            $price = floatval($data['price'] ?? 0);
            if (!empty($name) && $group_id > 0) {
                $stmt = $conn->prepare("INSERT INTO addon_options (addon_group_id, name, price) VALUES (?, ?, ?)");
                $stmt->bind_param("isd", $group_id, $name, $price);
                $response = $stmt->execute() ? ['success' => true, 'message' => 'Opsi berhasil ditambahkan.'] : ['success' => false, 'message' => 'Gagal menambahkan opsi.'];
                $stmt->close();
            } else {
                $response['message'] = 'Data tidak lengkap.';
            }
            break;

        case 'delete_addon_group':
            if (strtoupper($_SERVER['REQUEST_METHOD']) === 'DELETE' && isset($_GET['id'])) {
                $id = intval($_GET['id']);
                $stmt = $conn->prepare("DELETE FROM addon_groups WHERE id = ?");
                $stmt->bind_param("i", $id);
                $response = $stmt->execute() && $stmt->affected_rows > 0 ? ['success' => true, 'message' => 'Grup add-on berhasil dihapus.'] : ['success' => false, 'message' => 'Gagal menghapus grup.'];
                $stmt->close();
            }
            break;

        case 'delete_addon_option':
            if (strtoupper($_SERVER['REQUEST_METHOD']) === 'DELETE' && isset($_GET['id'])) {
                $id = intval($_GET['id']);
                $stmt = $conn->prepare("DELETE FROM addon_options WHERE id = ?");
                $stmt->bind_param("i", $id);
                $response = $stmt->execute() && $stmt->affected_rows > 0 ? ['success' => true, 'message' => 'Opsi add-on berhasil dihapus.'] : ['success' => false, 'message' => 'Gagal menghapus opsi.'];
                $stmt->close();
            }
            break;

        case 'get_menu_addons':
            if (isset($_GET['menu_id'])) {
                $menu_id = intval($_GET['menu_id']);
                $stmt = $conn->prepare("SELECT addon_group_id FROM menu_addons WHERE menu_id = ?");
                $stmt->bind_param("i", $menu_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $addons = [];
                while ($row = $result->fetch_assoc()) $addons[] = $row['addon_group_id'];
                $response = ['success' => true, 'data' => $addons];
            }
            break;
    }
    echo json_encode($response);
    exit();
}

// =================================================================
// BAGIAN RENDER HALAMAN
// =================================================================
$menu_items = [];
$result = $conn->query("SELECT * FROM menu ORDER BY name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) $menu_items[] = $row;
}
$categories = [];
$cat_result = $conn->query("SELECT name FROM menu_categories ORDER BY name ASC");
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) $categories[] = $row['name'];
}
require_once '../includes/header.php';
?>

<div class="container mx-auto p-4 md:p-6 lg:p-8">
    <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2">Kelola Menu</h1>
    <p class="text-gray-600 mb-8">Atur daftar menu, kategori, dan add-on (topping) untuk restoran Anda.</p>
    <div id="ajax-notification-container" class="fixed top-5 right-5 z-[100] w-full max-w-xs sm:max-w-sm"></div>
    <?php
    if (isset($_SESSION['success_message'])) {
        echo "<script>document.addEventListener('DOMContentLoaded', () => { showAjaxNotification('" . addslashes(htmlspecialchars($_SESSION['success_message'])) . "', 'success'); });</script>";
        unset($_SESSION['success_message']);
    }
    ?>
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 bg-white p-4 rounded-lg shadow-md gap-4">
        <div class="flex flex-col sm:flex-row w-full md:w-auto gap-3">
            <button id="openAddMenuModalBtn" type="button" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                </svg>
                <span>Tambah Menu</span>
            </button>
            <button id="openCategoryModalBtn" type="button" class="w-full sm:w-auto bg-gray-700 hover:bg-gray-800 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M.99 6a1 1 0 011-1h1.492A2.002 2.002 0 015.48 3.992l.512.512A2.002 2.002 0 007.484 5H10a1 1 0 110 2H7.484a2.002 2.002 0 00-1.492.512l-.512.512A2.002 2.002 0 013.488 9H2a1 1 0 110-2h1.488a2.002 2.002 0 001.492-.512l.512-.512A2.002 2.002 0 017.484 5H9a1 1 0 011 1zm8.707-1.707a1 1 0 00-1.414-1.414l-1.5 1.5a1 1 0 001.414 1.414l1.5-1.5zM.99 12a1 1 0 011-1h1.492a2.002 2.002 0 011.998-1.48l.512.512A2.002 2.002 0 007.484 11H10a1 1 0 110 2H7.484a2.002 2.002 0 00-1.492.512l-.512.512A2.002 2.002 0 013.488 15H2a1 1 0 110-2h1.488a2.002 2.002 0 001.492-.512l.512-.512A2.002 2.002 0 017.484 11H9a1 1 0 110-2H7.484a2.002 2.002 0 00-1.492.512l-.512.512A2.002 2.002 0 013.488 9H2a1 1 0 01-1-1zm8.707 5.293a1 1 0 00-1.414-1.414l-1.5 1.5a1 1 0 001.414 1.414l1.5-1.5z"></path>
                </svg>
                <span>Kelola Kategori</span>
            </button>
            <button id="openAddonModalBtn" type="button" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>
                <span>Kelola Add-On</span>
            </button>
        </div>
        <div class="w-full md:w-auto md:max-w-xs">
            <div class="relative"><span class="absolute inset-y-0 left-0 flex items-center pl-3"><svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                    </svg></span><input type="text" id="searchInput" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" placeholder="Cari menu..."></div>
        </div>
    </div>

    <div id="menuGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php if (!empty($menu_items)) : ?>
            <?php foreach ($menu_items as $item) : ?>
                <div class="menu-card bg-white rounded-xl shadow-lg overflow-hidden flex flex-col transition-transform transform hover:-translate-y-2" data-id="<?= $item['id'] ?>" data-name="<?= strtolower(htmlspecialchars($item['name'])) ?>" data-category="<?= strtolower(htmlspecialchars($item['category'])) ?>">
                    <div class="h-48 bg-gray-200 flex items-center justify-center relative">
                        <img src="../<?= !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : 'https://placehold.co/400x300/e2e8f0/64748b?text=Gambar' ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover">
                        <?php if ($item['discount_price'] > 0 && $item['discount_price'] < $item['price']) : ?><span class="absolute top-2 left-2 py-1 px-3 text-xs font-bold rounded-full bg-red-600 text-white animate-pulse">DISKON!</span><?php endif; ?>
                        <span class="status-badge absolute top-2 right-2 py-1 px-3 text-xs font-semibold rounded-full <?= $item['is_available'] ? 'bg-green-500 text-white' : 'bg-red-500 text-white' ?>"><?= $item['is_available'] ? 'Tersedia' : 'Habis' ?></span>
                    </div>
                    <div class="p-5 flex-grow">
                        <h3 class="text-xl font-bold text-gray-800 mb-2 truncate"><?= htmlspecialchars($item['name']) ?></h3>
                        <p class="text-sm text-gray-600 mb-3 capitalize">Kategori: <span class="font-medium"><?= htmlspecialchars($item['category']) ?></span></p>
                        <div class="price-container">
                            <?php if ($item['discount_price'] > 0 && $item['discount_price'] < $item['price']) : ?>
                                <div>
                                    <del class="text-sm text-gray-500">Rp <?= number_format($item['price']) ?></del>
                                    <p class="text-lg font-semibold text-red-600">Rp <?= number_format($item['discount_price']) ?></p>
                                </div>
                            <?php else : ?>
                                <p class="text-lg font-semibold text-blue-600">Rp <?= number_format($item['price']) ?></p>
                            <?php endif; ?>
                        </div>
                        <p class="stock-text text-sm font-medium mt-2 <?= $item['stock'] <= 5 && $item['stock'] > 0 ? 'text-yellow-600' : ($item['stock'] == 0 ? 'text-red-600' : 'text-gray-700') ?>">Stok: <?= $item['stock'] ?></p>
                    </div>
                    <div class="p-4 bg-gray-50 border-t flex justify-end items-center gap-3">
                        <button type="button" class="edit-menu-btn text-sm bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-4 rounded-lg" data-id="<?= $item['id'] ?>">Edit</button>
                        <button type="button" class="delete-menu-btn text-sm bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg" data-id="<?= $item['id'] ?>" data-name="<?= htmlspecialchars($item['name']) ?>">Hapus</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
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
<div id="addMenuModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl transform max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-xl font-bold text-gray-800">Tambah Menu Baru</h3>
            <button class="modal-close text-gray-400 hover:text-gray-800 text-3xl">&times;</button>
        </div>
        <div class="p-6 overflow-y-auto" id="addMenuModalContent"></div>
    </div>
</div>

<div id="editMenuModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl transform max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-xl font-bold text-gray-800">Edit Menu</h3>
            <button class="modal-close text-gray-400 hover:text-gray-800 text-3xl">&times;</button>
        </div>
        <div id="editMenuModalContent" class="p-6 overflow-y-auto"></div>
    </div>
</div>

<div id="categoryModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md transform">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-xl font-bold text-gray-800">Kelola Kategori</h3>
            <button class="modal-close text-gray-400 hover:text-gray-800 text-3xl">&times;</button>
        </div>
        <div class="p-6">
            <div id="categoryListContainer" class="max-h-64 overflow-y-auto mb-6 pr-2 space-y-2"></div>
            <form id="addCategoryForm" class="border-t pt-4">
                <label for="newCategoryName" class="text-sm font-medium text-gray-700 mb-2 block">Tambah Kategori Baru:</label>
                <div class="flex gap-3">
                    <input type="text" id="newCategoryName" name="name" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="cth: Makanan Berat" required>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shrink-0">Tambah</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="addonModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl transform max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-xl font-bold text-gray-800">Kelola Add-On (Topping)</h3>
            <button class="modal-close text-gray-400 hover:text-gray-800 text-3xl">&times;</button>
        </div>
        <div class="p-6 overflow-y-auto space-y-6">
            <div id="addonListContainer" class="space-y-4"></div>
            <div class="border-t pt-4">
                <form id="addAddonGroupForm" class="flex gap-3">
                    <input type="text" id="newAddonGroupName" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Nama Grup Add-On Baru (cth: Ukuran)" required>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shrink-0">Tambah Grup</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="confirmationModal" class="fixed inset-0 bg-black bg-opacity-60 z-[60] hidden items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-sm transform">
        <div class="p-6 text-center">
            <svg class="mx-auto mb-4 text-yellow-500 w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <h3 id="confirmationModalTitle" class="mb-2 text-xl font-bold text-gray-800"></h3>
            <p id="confirmationModalMessage" class="mb-6 text-gray-600"></p>
            <div class="flex justify-center gap-4">
                <button id="confirmationModalCancel" type="button" class="py-2 px-6 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-lg">Batal</button>
                <button id="confirmationModalConfirm" type="button" class="py-2 px-6 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg">Ya, Lanjutkan</button>
            </div>
        </div>
    </div>
</div>

<div id="categoryOptionsContainer" style="display: none;">
    <?php foreach ($categories as $category) : ?>
        <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
    <?php endforeach; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let allAddons = [];
        const categoryOptionsHTML = document.getElementById('categoryOptionsContainer').innerHTML;

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
        const showAjaxNotification = (message, type = 'success') => {
            const container = document.getElementById('ajax-notification-container');
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            const notification = document.createElement('div');
            notification.className = `p-4 rounded-lg text-white ${bgColor} shadow-lg mb-3 transform translate-x-full opacity-0 transition-all duration-300 ease-out`;
            notification.textContent = message;
            container.appendChild(notification);
            setTimeout(() => notification.classList.remove('translate-x-full', 'opacity-0'), 10);
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        };
        const showConfirmationModal = (title, message, callback) => {
            const modal = document.getElementById('confirmationModal');
            modal.querySelector('#confirmationModalTitle').textContent = title;
            modal.querySelector('#confirmationModalMessage').textContent = message;
            const confirmBtn = modal.querySelector('#confirmationModalConfirm');
            const cancelBtn = modal.querySelector('#confirmationModalCancel');

            const confirmHandler = () => {
                callback();
                hideModal(modal);
                confirmBtn.removeEventListener('click', confirmHandler);
                cancelBtn.removeEventListener('click', cancelHandler);
            };
            const cancelHandler = () => {
                hideModal(modal);
                confirmBtn.removeEventListener('click', confirmHandler);
                cancelBtn.removeEventListener('click', cancelHandler);
            };

            confirmBtn.addEventListener('click', confirmHandler);
            cancelBtn.addEventListener('click', cancelHandler);
            showModal(modal);
        };

        const showModal = (modal) => {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        };
        const hideModal = (modal) => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        };

        document.body.addEventListener('click', function(e) {
            // Handle closing modals via .modal-close or .modal-cancel buttons
            if (e.target.classList.contains('modal-close') || e.target.classList.contains('modal-cancel')) {
                const modal = e.target.closest('.fixed.flex'); // Find the closest visible modal
                if (modal) {
                    hideModal(modal);
                }
            }
        });

        const renderMenuFormHTML = (item = null) => {
            const isEdit = item !== null;
            const addonCheckboxesHTML = allAddons.length > 0 ?
                allAddons.map(group => `
                <label class="flex items-center space-x-2 text-sm">
                    <input type="checkbox" name="addons[]" value="${group.id}" class="form-checkbox rounded">
                    <span>${htmlspecialchars(group.name)}</span>
                </label>
            `).join('') :
                '<p class="col-span-full text-center text-gray-500">Tidak ada add-on tersedia.</p>';

            return `
        <form id="${isEdit ? 'editMenuForm' : 'addMenuForm'}" method="POST" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="action" value="${isEdit ? 'update_menu' : 'add_menu'}">
            ${isEdit ? `<input type="hidden" name="id" value="${item.id}">` : ''}
            ${isEdit ? `<input type="hidden" name="old_image_url" value="${htmlspecialchars(item.image_url || '')}">` : ''}
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Nama Menu <span class="text-red-500">*</span></label><input type="text" name="name" value="${isEdit ? htmlspecialchars(item.name) : ''}" class="w-full px-4 py-2 border border-gray-300 rounded-lg" required></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label><textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg">${isEdit ? htmlspecialchars(item.description || '') : ''}</textarea></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Kategori <span class="text-red-500">*</span></label><select name="category" class="w-full px-4 py-2 border border-gray-300 rounded-lg" required>${categoryOptionsHTML}</select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Stok <span class="text-red-500">*</span></label><input type="number" name="stock" value="${isEdit ? item.stock : '0'}" class="w-full px-4 py-2 border border-gray-300 rounded-lg" required min="0"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Harga Normal (Rp) <span class="text-red-500">*</span></label><input type="number" name="price" value="${isEdit ? item.price : ''}" class="w-full px-4 py-2 border border-gray-300 rounded-lg" required min="0"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Harga Diskon (Rp)</label><input type="number" name="discount_price" value="${isEdit && item.discount_price > 0 ? item.discount_price : ''}" class="w-full px-4 py-2 border border-gray-300 rounded-lg" min="0"></div>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Gambar Menu</label><input type="file" name="image" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-2">Status</label><div class="flex items-center gap-6"><label class="flex items-center"><input type="radio" name="is_available" value="1" class="form-radio" ${!isEdit || item.is_available == 1 ? 'checked' : ''}><span class="ml-2">Tersedia</span></label><label class="flex items-center"><input type="radio" name="is_available" value="0" class="form-radio" ${isEdit && item.is_available == 0 ? 'checked' : ''}><span class="ml-2">Habis</span></label></div></div>
            <div class="border-t pt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Pilihan Add-On / Topping</label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 max-h-40 overflow-y-auto p-2 bg-gray-50 rounded-lg">${addonCheckboxesHTML}</div>
            </div>
            <div class="flex justify-end gap-4 border-t pt-6 mt-4">
                <button type="button" class="modal-cancel bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded-lg">Batal</button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg">Simpan</button>
            </div>
        </form>
        `;
        };

        document.getElementById('openAddMenuModalBtn').addEventListener('click', () => {
            const modal = document.getElementById('addMenuModal');
            const content = document.getElementById('addMenuModalContent');
            content.innerHTML = renderMenuFormHTML();

            const form = content.querySelector('#addMenuForm');
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                submitMenuForm(form);
            });

            showModal(modal);
        });

        document.getElementById('menuGrid').addEventListener('click', async (e) => {
            if (e.target.classList.contains('edit-menu-btn')) {
                const id = e.target.dataset.id;
                const response = await fetch(`?action=get_menu_details&id=${id}`);
                const result = await response.json();
                if (result.success) {
                    const modal = document.getElementById('editMenuModal');
                    const content = document.getElementById('editMenuModalContent');
                    content.innerHTML = renderMenuFormHTML(result.data);
                    content.querySelector(`select[name="category"]`).value = result.data.category;

                    const addonResponse = await fetch(`?action=get_menu_addons&menu_id=${id}`);
                    const addonResult = await addonResponse.json();
                    if (addonResult.success) {
                        addonResult.data.forEach(addonId => {
                            const checkbox = content.querySelector(`input[name="addons[]"][value="${addonId}"]`);
                            if (checkbox) checkbox.checked = true;
                        });
                    }

                    const form = content.querySelector('#editMenuForm');
                    form.addEventListener('submit', (e) => {
                        e.preventDefault();
                        submitMenuForm(form);
                    });

                    showModal(modal);
                } else {
                    showAjaxNotification(result.message, 'error');
                }
            }
            if (e.target.classList.contains('delete-menu-btn')) {
                const id = e.target.dataset.id;
                const name = e.target.dataset.name;
                showConfirmationModal('Hapus Menu?', `Yakin ingin menghapus menu "${name}"?`, async () => {
                    const response = await fetch(`?action=delete_menu&id=${id}`, {
                        method: 'DELETE'
                    });
                    const result = await response.json();
                    showAjaxNotification(result.message, result.success ? 'success' : 'error');
                    if (result.success) {
                        e.target.closest('.menu-card').remove();
                    }
                });
            }
        });

        // Fungsi untuk menangani submit form menu (Tambah & Edit)
        const submitMenuForm = async (form) => {
            const submitButton = form.querySelector('button[type="submit"]');
            if (!submitButton) return;

            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = 'Menyimpan...';

            const formData = new FormData(form);
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Error ${response.status}: ${errorText || response.statusText}`);
                }

                const result = await response.json();

                if (result.success) {
                    // PERBAIKAN: Tampilkan notifikasi, tutup modal, lalu reload setelah jeda.
                    const activeModal = form.closest('.fixed.inset-0');
                    if (activeModal) {
                        hideModal(activeModal);
                    }
                    showAjaxNotification(result.message, 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 500); // Jeda 1.5 detik agar notifikasi terbaca
                } else {
                    showAjaxNotification(result.message || 'Terjadi kesalahan dari server.', 'error');
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }
            } catch (error) {
                console.error("Submit Error:", error);
                showAjaxNotification('Gagal memproses permintaan. Cek konsol browser untuk detail.', 'error');
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
        };

        const addonModal = document.getElementById('addonModal');
        const addonListContainer = document.getElementById('addonListContainer');
        document.getElementById('openAddonModalBtn').addEventListener('click', () => {
            showModal(addonModal);
            fetchAddons();
        });

        const fetchAddons = async () => {
            const response = await fetch(`?action=get_addons`);
            const result = await response.json();
            if (result.success) {
                allAddons = result.data;
                renderAddons(allAddons);
            }
        };
        const renderAddons = (addons) => {
            addonListContainer.innerHTML = addons.length > 0 ? addons.map(group => `
            <div class="p-4 border rounded-lg bg-gray-50">
                <div class="flex justify-between items-center mb-2"><h4 class="font-bold text-lg">${htmlspecialchars(group.name)}</h4><button class="delete-group-btn text-red-600 hover:text-red-800 font-bold" data-id="${group.id}">Hapus Grup</button></div>
                <ul class="space-y-1 mb-3">${group.options.map(opt => `<li class="flex justify-between items-center text-sm p-1"><span>${htmlspecialchars(opt.name)}</span><span class="font-semibold">Rp ${Number(opt.price).toLocaleString('id-ID')}<button class="delete-option-btn text-red-500 hover:text-red-700 ml-2" data-id="${opt.id}">&times;</button></span></li>`).join('')}</ul>
                <form class="add-option-form flex gap-2 text-sm">
                    <input type="hidden" name="group_id" value="${group.id}"><input type="text" name="name" class="w-full px-2 py-1 border rounded" placeholder="Nama Opsi Baru" required><input type="number" name="price" class="w-32 px-2 py-1 border rounded" placeholder="Harga" min="0" value="0" required><button type="submit" class="bg-green-500 text-white px-3 rounded text-xs">Tambah</button>
                </form>
            </div>`).join('') : `<p class="text-center text-gray-500">Belum ada grup add-on.</p>`;
        };
        document.getElementById('addAddonGroupForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const input = document.getElementById('newAddonGroupName');
            const name = input.value.trim();
            if (!name) return;
            const response = await fetch(`?action=add_addon_group`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    name
                })
            });
            const result = await response.json();
            showAjaxNotification(result.message, result.success ? 'success' : 'error');
            if (result.success) {
                input.value = '';
                fetchAddons();
            }
        });
        addonListContainer.addEventListener('submit', async e => {
            if (e.target.classList.contains('add-option-form')) {
                e.preventDefault();
                const form = e.target;
                const data = {
                    group_id: form.group_id.value,
                    name: form.name.value.trim(),
                    price: form.price.value
                };
                if (!data.name) return;
                const response = await fetch(`?action=add_addon_option`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    fetchAddons();
                    showAjaxNotification('Opsi berhasil ditambah.', 'success');
                } else {
                    showAjaxNotification(result.message || 'Gagal menambah opsi.', 'error');
                }
            }
        });
        addonListContainer.addEventListener('click', e => {
            if (e.target.classList.contains('delete-group-btn')) {
                showConfirmationModal('Hapus Grup?', 'Ini akan menghapus semua opsinya.', async () => {
                    const response = await fetch(`?action=delete_addon_group&id=${e.target.dataset.id}`, {
                        method: 'DELETE'
                    });
                    const result = await response.json();
                    showAjaxNotification(result.message, result.success ? 'success' : 'error');
                    if (result.success) fetchAddons();
                });
            }
            if (e.target.classList.contains('delete-option-btn')) {
                showConfirmationModal('Hapus Opsi?', 'Yakin ingin menghapus opsi ini?', async () => {
                    const response = await fetch(`?action=delete_addon_option&id=${e.target.dataset.id}`, {
                        method: 'DELETE'
                    });
                    const result = await response.json();
                    showAjaxNotification(result.message, result.success ? 'success' : 'error');
                    if (result.success) fetchAddons();
                });
            }
        });

        const categoryModal = document.getElementById('categoryModal');
        const categoryListContainer = document.getElementById('categoryListContainer');
        document.getElementById('openCategoryModalBtn').addEventListener('click', () => {
            showModal(categoryModal);
            fetchCategories();
        });
        const fetchCategories = async () => {
            const response = await fetch(`?action=get_categories`);
            const result = await response.json();
            if (result.success) renderCategories(result.data);
        };
        const renderCategories = (categories) => {
            categoryListContainer.innerHTML = categories.length > 0 ? categories.map(cat => {
                const deleteBtnDisabled = cat.is_in_use ? 'disabled' : '';
                const deleteBtnClasses = cat.is_in_use ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-red-500 hover:bg-red-600 text-white';
                return `
            <div class="category-item flex justify-between items-center bg-gray-50 p-2 rounded-lg" data-id="${cat.id}">
                <div class="flex-grow"><span class="category-name text-gray-800">${htmlspecialchars(cat.name)}</span><input type="text" class="category-edit-input hidden w-full px-2 py-1 border rounded" value="${htmlspecialchars(cat.name)}"></div>
                <div class="flex-shrink-0 flex items-center gap-2 ml-4">
                    <div class="display-mode"><button class="edit-category-btn text-xs py-1 px-2 rounded bg-yellow-500 hover:bg-yellow-600 text-white">Edit</button><button class="delete-category-btn text-xs py-1 px-2 rounded ${deleteBtnClasses}" data-name="${htmlspecialchars(cat.name)}" ${deleteBtnDisabled}>Hapus</button></div>
                    <div class="edit-mode hidden"><button class="save-category-btn text-xs py-1 px-2 rounded bg-green-500 hover:bg-green-600 text-white">Simpan</button><button class="cancel-edit-btn text-xs py-1 px-2 rounded bg-gray-400 hover:bg-gray-500 text-white">Batal</button></div>
                </div>
            </div>`;
            }).join('') : `<p class="text-center text-gray-500">Belum ada kategori.</p>`;
        };
        document.getElementById('addCategoryForm').addEventListener('submit', async e => {
            e.preventDefault();
            const input = document.getElementById('newCategoryName');
            const name = input.value.trim();
            if (!name) return;
            const response = await fetch('?action=add_category', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    name
                })
            });
            const result = await response.json();
            showAjaxNotification(result.message, result.success ? 'success' : 'error');
            if (result.success) setTimeout(() => window.location.reload());
        });
        categoryListContainer.addEventListener('click', async e => {
            const target = e.target;
            const item = target.closest('.category-item');
            if (!item) return;
            const id = item.dataset.id;
            const nameEl = item.querySelector('.category-name');
            const inputEl = item.querySelector('.category-edit-input');
            const setEditMode = (isEditing) => {
                item.querySelector('.display-mode').classList.toggle('hidden', isEditing);
                item.querySelector('.edit-mode').classList.toggle('hidden', !isEditing);
                nameEl.classList.toggle('hidden', isEditing);
                inputEl.classList.toggle('hidden', !isEditing);
            };

            if (target.classList.contains('edit-category-btn')) {
                setEditMode(true);
                inputEl.focus();
            } else if (target.classList.contains('cancel-edit-btn')) {
                inputEl.value = nameEl.textContent;
                setEditMode(false);
            } else if (target.classList.contains('delete-category-btn')) {
                showConfirmationModal('Hapus Kategori?', `Yakin ingin hapus kategori "${target.dataset.name}"?`, async () => {
                    const response = await fetch(`?action=delete_category&id=${id}`, {
                        method: 'DELETE'
                    });
                    const result = await response.json();
                    showAjaxNotification(result.message, result.success ? 'success' : 'error');
                    if (result.success) setTimeout(() => window.location.reload());
                });
            } else if (target.classList.contains('save-category-btn')) {
                const newName = inputEl.value.trim();
                if (!newName) return showAjaxNotification('Nama tidak boleh kosong', 'error');
                const response = await fetch('?action=update_category', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id,
                        name: newName
                    })
                });
                const result = await response.json();
                showAjaxNotification(result.message, result.success ? 'success' : 'error');
                if (result.success) setTimeout(() => window.location.reload());
            }
        });

        // Add Search Input Functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase().trim();
            document.querySelectorAll('.menu-card').forEach(card => {
                let menuName = card.dataset.name;
                card.style.display = menuName.includes(filter) ? 'flex' : 'none';
            });
        });

        fetchAddons();
    });
</script>

<?php
require_once '../includes/footer.php';
$conn->close();
?>