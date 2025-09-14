<?php
// Memulai session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/header.php';
require_once '../../db_connect.php';

// Cek apakah ID banner ada di URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID Banner tidak valid.";
    header("Location: kelola_banner.php");
    exit();
}

$banner_id = (int)$_GET['id'];
$sql = "SELECT * FROM banners WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $banner_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Banner tidak ditemukan.";
    header("Location: kelola_banner.php");
    exit();
}

$banner = $result->fetch_assoc();
?>

<div class="container mx-auto p-4 md:p-6">
    <div class="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow-md">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Edit Banner</h1>
        <p class="text-gray-600 mb-6">Perbarui detail untuk banner promosi Anda.</p>
        <a href="kelola_banner.php" class="text-blue-600 hover:underline mb-6 inline-block"><i class="fas fa-arrow-left mr-2"></i>Kembali ke Daftar Banner</a>

        <form action="proses_banner.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $banner['id'] ?>">
            <input type="hidden" name="old_image_path" value="<?= htmlspecialchars($banner['image_url']) ?>">

            <div class="mb-4">
                <label for="title" class="block text-gray-700 text-sm font-bold mb-2">Judul Banner:</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($banner['title']) ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>

            <div class="mb-4">
                <label for="subtitle" class="block text-gray-700 text-sm font-bold mb-2">Subjudul (Deskripsi Singkat):</label>
                <textarea id="subtitle" name="subtitle" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?= htmlspecialchars($banner['subtitle']) ?></textarea>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Gambar Saat Ini:</label>
                <img src="../<?= htmlspecialchars($banner['image_url']) ?>" alt="Banner Image" class="w-48 h-auto rounded-md border p-1">
            </div>

            <div class="mb-4">
                <label for="image" class="block text-gray-700 text-sm font-bold mb-2">Ganti Gambar Banner (Opsional):</label>
                <input type="file" id="image" name="image" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <p class="text-xs text-gray-500 mt-1">Biarkan kosong jika tidak ingin mengganti gambar.</p>
            </div>

            <div class="mb-4">
                <label for="link_url" class="block text-gray-700 text-sm font-bold mb-2">URL Link (Opsional):</label>
                <input type="url" id="link_url" name="link_url" value="<?= htmlspecialchars($banner['link_url']) ?>" placeholder="Contoh: #category-promo" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="mb-4">
                    <label for="order_number" class="block text-gray-700 text-sm font-bold mb-2">Nomor Urut:</label>
                    <input type="number" id="order_number" name="order_number" value="<?= htmlspecialchars($banner['order_number']) ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Status:</label>
                    <div class="flex items-center space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="is_active" value="1" class="form-radio text-blue-600" <?= $banner['is_active'] == 1 ? 'checked' : '' ?>>
                            <span class="ml-2">Aktif</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="is_active" value="0" class="form-radio text-red-600" <?= $banner['is_active'] == 0 ? 'checked' : '' ?>>
                            <span class="ml-2">Tidak Aktif</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end">
                <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors">
                    Perbarui Banner
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once '../includes/footer.php';
$conn->close();
?>