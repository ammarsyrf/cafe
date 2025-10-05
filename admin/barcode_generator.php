<?php
// Masukkan header
require_once 'includes/header.php';

// Inisialisasi variabel untuk menyimpan path file QR code dan data meja
$qrCodeFile = null;
$tableIdentifier = '';
$errorMessage = '';

// Cek jika form telah disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan bersihkan input nama/nomor meja
    $tableIdentifier = trim($_POST['table_identifier']);

    if (!empty($tableIdentifier)) {
        // --- PROSES "HASH" / ENKRIPSI ---
        // Kita tidak menggunakan hash satu arah (seperti md5), karena kita perlu mengambil kembali
        // nama mejanya. Base64 adalah encoding dua arah yang cocok untuk ini.
        // Ini akan mengubah "Meja 5" menjadi "TWVqYSA1"
        $encodedIdentifier = base64_encode($tableIdentifier);

        // Buat URL lengkap menuju halaman menu dengan parameter meja
        // Contoh: http://domain-cafe.com/menu.php?meja=TWVqYSA1
        $menuUrl = rtrim(BASE_URL, '/') . '/menu.php?meja=' . urlencode($encodedIdentifier);

        // Path ke library PHP QR Code. Pastikan path ini benar.
        // Anda harus mengunduh library ini dan meletakkannya di folder libs/phpqrcode
        $phpqrcodePath = __DIR__ . '/../libs/phpqrcode/qrlib.php';

        if (file_exists($phpqrcodePath)) {
            require_once $phpqrcodePath;

            // Direktori untuk menyimpan gambar QR code
            $qrDir = __DIR__ . '/qrcodes/';
            if (!is_dir($qrDir)) {
                mkdir($qrDir, 0775, true);
            }

            // Sanitasi nama file untuk menghindari karakter yang tidak valid
            $safeFileName = preg_replace('/[^a-zA-Z0-9_-]/', '', $tableIdentifier);
            $fileName = 'meja-' . $safeFileName . '-' . time() . '.png';
            $filePath = $qrDir . $fileName;

            // Generate QR Code
            // Parameter: (data, nama file, level koreksi error, ukuran pixel, margin)
            QRcode::png($menuUrl, $filePath, QR_ECLEVEL_L, 10, 2);

            // Simpan path relatif untuk ditampilkan di tag <img>
            $qrCodeFile = 'qrcodes/' . $fileName;
        } else {
            $errorMessage = "Error: Library 'phpqrcode' tidak ditemukan. Mohon unduh dan letakkan di folder 'libs/phpqrcode'.";
        }
    } else {
        $errorMessage = "Nama atau nomor meja tidak boleh kosong.";
    }
}
?>

<style>
    /* CSS untuk menyembunyikan elemen yang tidak perlu saat mencetak */
    @media print {
        body * {
            visibility: hidden;
        }

        #printableArea,
        #printableArea * {
            visibility: visible;
        }

        #printableArea {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }

        .no-print {
            display: none;
        }
    }
</style>

<div class="container mx-auto">
    <div class="bg-white p-8 rounded-xl shadow-lg max-w-2xl mx-auto">
        <h3 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">Generate QR Code Meja</h3>

        <?php if ($errorMessage): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?= htmlspecialchars($errorMessage) ?></span>
            </div>
        <?php endif; ?>

        <!-- Form Generator -->
        <form method="POST" action="barcode_generator.php" class="space-y-4 no-print">
            <div>
                <label for="table_identifier" class="block text-sm font-medium text-gray-700 mb-1">Nama atau Nomor Meja</label>
                <input type="text" id="table_identifier" name="table_identifier" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Contoh: Meja 5, VIP 2, Teras Atas" required>
                <p class="text-xs text-gray-500 mt-1">Ini akan ditampilkan di bawah QR Code saat dicetak.</p>
            </div>
            <div>
                <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-cogs mr-2"></i>Generate QR Code
                </button>
            </div>
        </form>

        <!-- Hasil QR Code -->
        <?php if ($qrCodeFile): ?>
            <div id="printableArea" class="mt-8 border-t pt-6 text-center">
                <h4 class="text-lg font-semibold text-gray-700">QR Code untuk:</h4>
                <p class="text-2xl font-bold text-indigo-600 mb-4"><?= htmlspecialchars($tableIdentifier) ?></p>
                <div class="flex justify-center">
                    <img src="<?= htmlspecialchars($qrCodeFile) ?>" alt="QR Code untuk <?= htmlspecialchars($tableIdentifier) ?>" class="border-4 border-gray-200 rounded-lg p-2">
                </div>
                <div class="mt-6 flex justify-center space-x-4 no-print">
                    <button onclick="printQRCode()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-print mr-2"></i> Cetak
                    </button>
                    <a href="<?= htmlspecialchars($qrCodeFile) ?>" download="QRCode-<?= preg_replace('/[^a-zA-Z0-9_-]/', '', $tableIdentifier) ?>.png" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <i class="fas fa-download mr-2"></i> Unduh
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function printQRCode() {
        window.print();
    }
</script>

<?php
// Masukkan footer
require_once 'includes/footer.php';
?>