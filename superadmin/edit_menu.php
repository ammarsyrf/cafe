<?php
// 1. LOGIKA PHP DIEKSEKUSI PERTAMA
// =================================================================
// Memulai session jika belum ada. Ini HARUS dijalankan sebelum output apapun.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hubungkan ke database
require_once '../db_connect.php';

$message = '';
$menu_item = null;
$menu_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Redirect jika tidak ada ID yang valid di URL
if ($menu_id <= 0 && $_SERVER["REQUEST_METHOD"] != "POST") {
    $_SESSION['error_message'] = "ID menu tidak valid.";
    header("Location: kelolamenu.php");
    exit();
}

// Proses form saat disubmit (method POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari form
    $id = (int)$_POST['id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = (int)$_POST['price'];
    $category = $_POST['category'];
    $stock = (int)$_POST['stock'];
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    $image_path = $_POST['current_image'];

    // Proses upload gambar baru jika ada
    if (isset($_FILES["menu_image"]) && !empty($_FILES["menu_image"]["name"]) && $_FILES["menu_image"]["error"] == 0) {
        $target_dir = "../uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $image_name = basename($_FILES["menu_image"]["name"]);
        $imageFileType = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
        $new_filename = uniqid('menu_', true) . '.' . $imageFileType;
        $target_file = $target_dir . $new_filename;
        $uploadOk = 1;

        $check = getimagesize($_FILES["menu_image"]["tmp_name"]);
        if ($check === false) {
            $message = "<div class='bg-red-100 text-red-700 p-3 rounded'>File yang diunggah bukan gambar.</div>";
            $uploadOk = 0;
        }
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($imageFileType, $allowed_types)) {
            $message = "<div class='bg-red-100 text-red-700 p-3 rounded'>Maaf, hanya file JPG, JPEG, PNG, GIF, & WEBP yang diizinkan.</div>";
            $uploadOk = 0;
        }

        if ($uploadOk == 1) {
            if (move_uploaded_file($_FILES["menu_image"]["tmp_name"], $target_file)) {
                if (!empty($image_path) && file_exists('../' . $image_path)) {
                    @unlink('../' . $image_path);
                }
                $image_path = 'uploads/' . $new_filename;
            } else {
                $message = "<div class='bg-red-100 text-red-700 p-3 rounded'>Gagal mengunggah gambar baru.</div>";
            }
        }
    }

    // Lanjutkan update ke database jika tidak ada error dari proses unggah
    if (empty($message)) {
        $sql_update = "UPDATE menu SET name=?, description=?, price=?, category=?, stock=?, image_url=?, is_available=? WHERE id=?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("ssisissi", $name, $description, $price, $category, $stock, $image_path, $is_available, $id);

            if ($stmt_update->execute()) {
                $_SESSION['success_message'] = "Menu berhasil diperbarui!";
                // INI ADALAH FUNGSI YANG MEMBUTUHKAN HEADER BELUM DIKIRIM
                header("Location: kelolamenu.php");
                exit(); // Pastikan skrip berhenti setelah redirect
            } else {
                $message = "<div class='bg-red-100 text-red-700 p-3 rounded'>Gagal memperbarui data menu.</div>";
            }
            $stmt_update->close();
        } else {
            $message = "<div class='bg-red-100 text-red-700 p-3 rounded'>Gagal menyiapkan query update.</div>";
        }
    }

    // Jika ada error, muat ulang data dari POST agar form tetap terisi
    $menu_item = $_POST;
    $menu_item['image_url'] = $_POST['current_image'];
} else {
    // Ambil data menu saat ini untuk ditampilkan di form (Saat halaman dimuat pertama kali)
    $sql_select = "SELECT id, name, description, price, category, stock, image_url, is_available FROM menu WHERE id = ?";
    if ($stmt_select = $conn->prepare($sql_select)) {
        $stmt_select->bind_param("i", $menu_id);
        $stmt_select->execute();
        $result = $stmt_select->get_result();
        if ($result->num_rows == 1) {
            $menu_item = $result->fetch_assoc();
        } else {
            $_SESSION['error_message'] = "Menu dengan ID tersebut tidak ditemukan.";
            header("Location: kelolamenu.php");
            exit();
        }
        $stmt_select->close();
    } else {
        $message = "<div class='bg-red-100 p-3 rounded text-red-700'>Terjadi kesalahan saat menyiapkan data.</div>";
    }
}

// 2. OUTPUT HTML DIMULAI DI SINI
// =================================================================
// Setelah semua logika selesai, baru kita panggil header.php
require_once 'includes/header.php';
?>

<div class="container mx-auto p-4 md:p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Edit Menu</h1>
        <a href="kelolamenu.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg inline-flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left-short mr-2" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M12 8a.5.5 0 0 1-.5.5H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5a.5.5 0 0 1 .5.5z" />
            </svg>
            Kembali ke Daftar
        </a>
    </div>

    <?php if (!empty($message)) echo $message; ?>

    <?php if ($menu_item): ?>
        <div class="bg-white p-8 rounded-xl shadow-lg max-w-4xl mx-auto">
            <form action="edit_menu.php?id=<?= $menu_id; ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= $menu_item['id']; ?>">
                <input type="hidden" name="current_image" value="<?= htmlspecialchars($menu_item['image_url'] ?? ''); ?>">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="md:col-span-2 space-y-5">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nama Menu</label>
                            <input type="text" name="name" id="name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500 p-2" value="<?= htmlspecialchars($menu_item['name'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="price" class="block text-sm font-medium text-gray-700 mb-1">Harga (Rp)</label>
                            <input type="number" name="price" id="price" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500 p-2" value="<?= (int)($menu_item['price'] ?? 0); ?>">
                        </div>
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                            <select name="category" id="category" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500 p-2">
                                <option value="makanan" <?= ($menu_item['category'] ?? '') == 'makanan' ? 'selected' : ''; ?>>Makanan</option>
                                <option value="minuman" <?= ($menu_item['category'] ?? '') == 'minuman' ? 'selected' : ''; ?>>Minuman</option>
                                <option value="snack" <?= ($menu_item['category'] ?? '') == 'snack' ? 'selected' : ''; ?>>Snack</option>
                                <option value="other" <?= ($menu_item['category'] ?? '') == 'other' ? 'selected' : ''; ?>>Lainnya</option>
                            </select>
                        </div>
                        <div>
                            <label for="stock" class="block text-sm font-medium text-gray-700 mb-1">Stok</label>
                            <input type="number" name="stock" id="stock" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500 p-2" value="<?= (int)($menu_item['stock'] ?? 0); ?>">
                        </div>
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                            <textarea name="description" id="description" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500 p-2"><?= htmlspecialchars($menu_item['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gambar Menu</label>
                        <div class="mt-2 p-4 border-2 border-dashed rounded-lg">
                            <img id="image-preview" src="../<?= !empty($menu_item['image_url']) ? htmlspecialchars($menu_item['image_url']) : 'https://placehold.co/400x300/e2e8f0/64748b?text=Pilih+Gambar' ?>" alt="Gambar saat ini" class="rounded-lg w-full h-48 object-cover border mb-4">
                            <p class="text-xs text-gray-500 mb-2">Ganti gambar (opsional):</p>
                            <input type="file" name="menu_image" id="menu_image" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100 cursor-pointer" onchange="previewImage(event)">
                        </div>
                        <div class="mt-4">
                            <label for="is_available" class="flex items-center cursor-pointer">
                                <input type="checkbox" name="is_available" id="is_available" class="h-4 w-4 rounded border-gray-300 text-yellow-600 focus:ring-yellow-500" <?= !empty($menu_item['is_available']) && $menu_item['is_available'] ? 'checked' : ''; ?>>
                                <span class="ml-2 text-sm text-gray-800">Tersedia untuk dijual</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-4">
                    <a href="kelolamenu.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-6 rounded-lg transition-colors">Batal</a>
                    <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-6 rounded-lg transition-colors">Perbarui Menu</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
    function previewImage(event) {
        const reader = new FileReader();
        reader.onload = function() {
            const output = document.getElementById('image-preview');
            output.src = reader.result;
        };
        if (event.target.files[0]) {
            reader.readAsDataURL(event.target.files[0]);
        }
    }
</script>

<?php
// 3. Terakhir, panggil footer
require_once 'includes/footer.php';
$conn->close();
?>