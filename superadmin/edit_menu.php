<?php
require_once 'includes/header.php';
require_once '../db_connect.php';

$message = '';
$menu_item = null;
$menu_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil data menu saat ini untuk ditampilkan di form
if ($menu_id > 0 && $_SERVER["REQUEST_METHOD"] != "POST") {
    $sql_select = "SELECT * FROM menu WHERE id = ?";
    if ($stmt_select = $conn->prepare($sql_select)) {
        $stmt_select->bind_param("i", $menu_id);
        $stmt_select->execute();
        $result = $stmt_select->get_result();
        if ($result->num_rows == 1) {
            $menu_item = $result->fetch_assoc();
        } else {
            $message = "<div class='bg-red-100 p-3 rounded text-red-700'>Menu tidak ditemukan.</div>";
        }
        $stmt_select->close();
    }
}

// Proses form saat disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = (int)$_POST['id'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = (int)$_POST['price'];
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $stock = (int)$_POST['stock'];
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    
    // Ambil path gambar saat ini dari hidden input
    $image_path = $_POST['current_image'];

    // Cek jika ada gambar baru yang diunggah
    if (isset($_FILES["menu_image"]) && !empty($_FILES["menu_image"]["name"]) && $_FILES["menu_image"]["error"] == 0) {
        $target_dir = "../uploads/";
        $image_name = basename($_FILES["menu_image"]["name"]);
        $imageFileType = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $imageFileType;
        $target_file = $target_dir . $new_filename;
        $uploadOk = 1;

        // Validasi (bisa ditambahkan validasi ukuran dan tipe file di sini)
        $check = getimagesize($_FILES["menu_image"]["tmp_name"]);
        if($check === false) {
            $message = "<div class='bg-red-100 text-red-700 p-3 rounded'>File bukan gambar.</div>";
            $uploadOk = 0;
        }

        if ($uploadOk == 1 && move_uploaded_file($_FILES["menu_image"]["tmp_name"], $target_file)) {
            // Hapus gambar lama jika ada dan bukan placeholder
            if (!empty($image_path) && file_exists('../' . $image_path)) {
                unlink('../' . $image_path);
            }
            // Perbarui path gambar dengan yang baru
            $image_path = 'uploads/' . $new_filename;
        } else {
            $message = "<div class='bg-red-100 text-red-700 p-3 rounded'>Gagal mengunggah gambar baru.</div>";
        }
    }

    // Lanjutkan update ke database jika tidak ada error unggah
    if (empty($message)) {
        $sql_update = "UPDATE menu SET name=?, description=?, price=?, category=?, stock=?, image_url=?, is_available=? WHERE id=?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("ssisisii", $name, $description, $price, $category, $stock, $image_path, $is_available, $id);
            if ($stmt_update->execute()) {
                header("Location: kelolamenu.php");
                exit();
            } else {
                $message = "<div class='bg-red-100 text-red-700 p-3 rounded'>Gagal memperbarui data menu.</div>";
            }
            $stmt_update->close();
        }
    }
    // Muat ulang data item setelah post gagal agar form tetap terisi
    $menu_item = $_POST;
    $menu_item['image_url'] = $_POST['current_image'];

}
?>

<div class="container mx-auto">
    <h1 class="text-3xl font-bold mb-6 text-gray-800">Edit Menu</h1>
    
    <?php if(!empty($message)) echo $message; ?>

    <?php if ($menu_item): ?>
    <div class="bg-white p-8 rounded-xl shadow-lg">
        <form action="edit_menu.php?id=<?= $menu_id; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $menu_item['id']; ?>">
            <input type="hidden" name="current_image" value="<?= htmlspecialchars($menu_item['image_url']); ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Kolom Kiri: Form Data -->
                <div class="md:col-span-2 space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Nama Menu</label>
                        <input type="text" name="name" id="name" required class="mt-1 block w-full border rounded-md p-2" value="<?= htmlspecialchars($menu_item['name']); ?>">
                    </div>
                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700">Harga (Rp)</label>
                        <input type="number" name="price" id="price" required class="mt-1 block w-full border rounded-md p-2" value="<?= $menu_item['price']; ?>">
                    </div>
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700">Kategori</label>
                        <select name="category" id="category" required class="mt-1 block w-full border rounded-md p-2">
                            <option value="makanan" <?= $menu_item['category'] == 'makanan' ? 'selected' : ''; ?>>Makanan</option>
                            <option value="minuman" <?= $menu_item['category'] == 'minuman' ? 'selected' : ''; ?>>Minuman</option>
                            <option value="snack" <?= $menu_item['category'] == 'snack' ? 'selected' : ''; ?>>Snack</option>
                            <option value="other" <?= $menu_item['category'] == 'other' ? 'selected' : ''; ?>>Lainnya</option>
                        </select>
                    </div>
                    <div>
                        <label for="stock" class="block text-sm font-medium text-gray-700">Stok</label>
                        <input type="number" name="stock" id="stock" required class="mt-1 block w-full border rounded-md p-2" value="<?= $menu_item['stock']; ?>">
                    </div>
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Deskripsi</label>
                        <textarea name="description" id="description" rows="4" class="mt-1 block w-full border rounded-md p-2"><?= htmlspecialchars($menu_item['description']); ?></textarea>
                    </div>
                    <div>
                        <label for="is_available" class="flex items-center">
                            <input type="checkbox" name="is_available" id="is_available" class="h-4 w-4" <?= !empty($menu_item['is_available']) && $menu_item['is_available'] ? 'checked' : ''; ?>>
                            <span class="ml-2 text-sm">Tersedia untuk dijual</span>
                        </label>
                    </div>
                </div>
                <!-- Kolom Kanan: Gambar -->
                <div class="md:col-span-1">
                    <label class="block text-sm font-medium text-gray-700">Gambar Menu</label>
                    <img src="../<?= !empty($menu_item['image_url']) ? htmlspecialchars($menu_item['image_url']) : 'https://placehold.co/400x300/e2e8f0/64748b?text=Gambar' ?>" alt="Gambar saat ini" class="mt-2 rounded-lg w-full h-48 object-cover border">
                    <p class="text-xs text-gray-500 mt-2">Unggah gambar baru untuk mengganti:</p>
                    <input type="file" name="menu_image" class="mt-2 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-4">
                <a href="kelolamenu.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg">Batal</a>
                <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-lg">Perbarui Menu</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
$conn->close();
?>

