<?php
// File: superadmin/tambah_menu.php

// Memulai session jika belum ada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Panggil koneksi DB terlebih dahulu untuk logika di bawah
require_once '../db_connect.php';

$message = '';

// --- SEMUA LOGIKA PEMROSESAN FORM DIPINDAHKAN KE ATAS ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = (float)$_POST['price'];
    // [MODIFIED] Ambil harga diskon, set ke NULL jika kosong
    $discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $stock = (int)$_POST['stock'];
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    $image_path = '';

    // Proses unggah gambar
    if (isset($_FILES["menu_image"]) && $_FILES["menu_image"]["error"] == 0) {
        $target_dir = "../uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $image_name = basename($_FILES["menu_image"]["name"]);
        $imageFileType = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $imageFileType;
        $target_file = $target_dir . $new_filename;
        $uploadOk = 1;

        // Validasi file gambar
        $check = getimagesize($_FILES["menu_image"]["tmp_name"]);
        if ($check === false) {
            $message = "<div class='bg-red-100 text-red-700 p-3 rounded'>File bukan gambar.</div>";
            $uploadOk = 0;
        }
        if ($_FILES["menu_image"]["size"] > 2000000) { // Batas 2MB
            $message = "<div class='bg-red-100 text-red-700 p-3 rounded'>Ukuran gambar terlalu besar.</div>";
            $uploadOk = 0;
        }
        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
            $message = "<div class='bg-red-100 text-red-700 p-3 rounded'>Hanya format JPG, JPEG, & PNG yang diizinkan.</div>";
            $uploadOk = 0;
        }

        if ($uploadOk == 1) {
            if (move_uploaded_file($_FILES["menu_image"]["tmp_name"], $target_file)) {
                $image_path = 'uploads/' . $new_filename; // Simpan path relatif
            } else {
                $message = "<div class='bg-red-100 text-red-700 p-3 rounded'>Gagal mengunggah gambar.</div>";
            }
        }
    }

    if (empty($message)) {
        // [MODIFIED] Query INSERT diubah untuk menyertakan discount_price
        $sql = "INSERT INTO menu (name, description, price, discount_price, category, stock, image_url, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            // [MODIFIED] Bind parameter diubah (ssddsi si -> string, string, double, double, string, integer, string, integer)
            $stmt->bind_param("ssddissi", $name, $description, $price, $discount_price, $category, $stock, $image_path, $is_available);
            if ($stmt->execute()) {
                // Redirect sekarang bisa berjalan tanpa error
                $_SESSION['success_message'] = "Menu '{$name}' berhasil ditambahkan!";
                header("Location: kelolamenu.php");
                exit();
            } else {
                $message = "<div class='bg-red-100 text-red-700 p-3 rounded'>Error: " . $stmt->error . "</div>";
            }
            $stmt->close();
        }
    }
}

// --- SETELAH SEMUA LOGIKA SELESAI, BARU TAMPILKAN HTML ---
require_once 'includes/header.php';
?>

<div class="container mx-auto p-4 md:p-6">
    <?php if (!empty($message)) echo $message; ?>

    <form action="tambah_menu.php" method="POST" enctype="multipart/form-data">
        <div class="bg-white p-8 rounded-xl shadow-lg max-w-4xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Tambah Menu Baru</h1>
                <div>
                    <a href="kelolamenu.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg">Batal</a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Simpan Menu</button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Kolom Kiri: Unggah Gambar -->
                <div class="lg:col-span-1">
                    <div id="imagePreview" class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer h-64 flex items-center justify-center bg-gray-50">
                        <span class="text-gray-500">Klik untuk unggah gambar</span>
                    </div>
                    <input type="file" name="menu_image" id="menu_image" class="hidden" accept="image/*">
                    <p class="text-xs text-gray-500 mt-2">Format: JPG, PNG, JPEG. Ukuran maks: 2MB.</p>
                </div>

                <!-- Kolom Kanan: Detail Menu -->
                <div class="lg:col-span-2 space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Nama Menu</label>
                        <input type="text" name="name" id="name" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="price" class="block text-sm font-medium text-gray-700">Harga Normal (Rp)</label>
                            <input type="number" name="price" id="price" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" step="0.01">
                        </div>
                        <!-- [ADDED] Input untuk Harga Diskon -->
                        <div>
                            <label for="discount_price" class="block text-sm font-medium text-gray-700">Harga Diskon (Rp) <span class="text-xs text-gray-500">- Opsional</span></label>
                            <input type="number" name="discount_price" id="discount_price" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" step="0.01" placeholder="Kosongkan jika tidak diskon">
                        </div>
                    </div>
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700">Kategori</label>
                        <select name="category" id="category" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                            <option value="makanan">Makanan</option>
                            <option value="minuman">Minuman</option>
                            <option value="kopi">Kopi</option>
                            <option value="snack">Snack</option>
                            <option value="dessert">Dessert</option>
                        </select>
                    </div>
                    <div>
                        <label for="stock" class="block text-sm font-medium text-gray-700">Stok</label>
                        <input type="number" name="stock" id="stock" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                    </div>
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Deskripsi</label>
                        <textarea name="description" id="description" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2"></textarea>
                    </div>
                    <div>
                        <label for="is_available" class="flex items-center">
                            <input type="checkbox" name="is_available" id="is_available" checked class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-900">Tersedia untuk dijual</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    document.getElementById('imagePreview').addEventListener('click', function() {
        document.getElementById('menu_image').click();
    });

    document.getElementById('menu_image').addEventListener('change', function(event) {
        const [file] = event.target.files;
        if (file) {
            const previewContainer = document.getElementById('imagePreview');
            previewContainer.innerHTML = ''; // Hapus teks placeholder
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.classList.add('max-h-full', 'max-w-full', 'rounded-lg', 'object-cover');
            previewContainer.appendChild(img);
        }
    });
</script>

<?php
require_once 'includes/footer.php';
$conn->close();
?>