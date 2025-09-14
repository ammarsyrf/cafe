<?php
require_once '../includes/header.php';
?>

<div class="container mx-auto p-4 md:p-6">
    <div class="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow-md">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Tambah Banner Baru</h1>
        <p class="text-gray-600 mb-6">Isi formulir di bawah untuk menambahkan banner promosi baru.</p>
        <a href="kelola_banner.php" class="text-blue-600 hover:underline mb-6 inline-block"><i class="fas fa-arrow-left mr-2"></i>Kembali ke Daftar Banner</a>

        <form action="proses_banner.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">

            <div class="mb-4">
                <label for="title" class="block text-gray-700 text-sm font-bold mb-2">Judul Banner:</label>
                <input type="text" id="title" name="title" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>

            <div class="mb-4">
                <label for="subtitle" class="block text-gray-700 text-sm font-bold mb-2">Subjudul (Deskripsi Singkat):</label>
                <textarea id="subtitle" name="subtitle" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
            </div>

            <div class="mb-4">
                <label for="image" class="block text-gray-700 text-sm font-bold mb-2">Gambar Banner:</label>
                <input type="file" id="image" name="image" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required>
                <p class="text-xs text-gray-500 mt-1">Rekomendasi ukuran: 1200x400 pixels.</p>
            </div>

            <div class="mb-4">
                <label for="link_url" class="block text-gray-700 text-sm font-bold mb-2">Link ke Kategori (Opsional):</label>
                <select id="link_url" name="link_url" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">-- Tidak ada link --</option>
                    <option value="#category-promo">Promo</option>
                    <option value="#category-makanan">Makanan</option>
                    <option value="#category-minuman">Minuman</option>
                    <option value="#category-snack">Snack</option>
                    <option value="#category-others">Others</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Pilih kategori tujuan saat banner di-klik.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="mb-4">
                    <label for="order_number" class="block text-gray-700 text-sm font-bold mb-2">Nomor Urut:</label>
                    <input type="number" id="order_number" name="order_number" value="10" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <p class="text-xs text-gray-500 mt-1">Semakin kecil angka, semakin awal tampil.</p>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Status:</label>
                    <div class="flex items-center space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="is_active" value="1" class="form-radio text-blue-600" checked>
                            <span class="ml-2">Aktif</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="is_active" value="0" class="form-radio text-red-600">
                            <span class="ml-2">Tidak Aktif</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors">
                    Simpan Banner
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>