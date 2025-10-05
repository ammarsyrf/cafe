<?php
// File: admin/ulasan.php
require_once 'includes/header.php';
require_once '../app/config/db_connect.php';

// Proses form jika admin mengirim balasan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply_text'])) {
    $review_id = $_POST['review_id'];
    $reply_text = trim($_POST['reply_text']);

    if (!empty($reply_text)) {
        // Asumsi ada kolom 'reply' di tabel 'reviews'
        $sql_reply = "UPDATE reviews SET reply = ? WHERE id = ?";
        if ($stmt_reply = $conn->prepare($sql_reply)) {
            $stmt_reply->bind_param("si", $reply_text, $review_id);
            $stmt_reply->execute();
            $stmt_reply->close();
            // Redirect untuk menghindari resubmit form
            header("Location: ulasan.php?status=replied");
            exit();
        }
    }
}

// Logika untuk filter rating
$rating_filter = $_GET['rating'] ?? 'all';
$sql_where = "";
if ($rating_filter != 'all' && is_numeric($rating_filter)) {
    $sql_where = "WHERE r.rating = " . intval($rating_filter);
}

// Ambil data ulasan dari database
$reviews = [];
// PERBAIKAN: Join ke tabel 'members' menggunakan 'member_id' dan ambil 'name'
$sql = "SELECT r.id, r.rating, r.comment, r.reply, r.created_at, m.name 
        FROM reviews r
        JOIN members m ON r.member_id = m.id
        $sql_where
        ORDER BY r.created_at DESC";

$result = $conn->query($sql);
if ($result) {
    $reviews = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="container mx-auto">
    <!-- Header dan Filter -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <h1 class="text-3xl font-bold text-gray-800">Ulasan Pelanggan</h1>
        <form method="GET" class="flex items-center gap-2 bg-white p-2 rounded-lg shadow">
            <select name="rating" onchange="this.form.submit()" class="border p-2 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="all" <?= $rating_filter == 'all' ? 'selected' : '' ?>>Semua Rating</option>
                <option value="5" <?= $rating_filter == '5' ? 'selected' : '' ?>>★★★★★ (5)</option>
                <option value="4" <?= $rating_filter == '4' ? 'selected' : '' ?>>★★★★☆ (4)</option>
                <option value="3" <?= $rating_filter == '3' ? 'selected' : '' ?>>★★★☆☆ (3)</option>
                <option value="2" <?= $rating_filter == '2' ? 'selected' : '' ?>>★★☆☆☆ (2)</option>
                <option value="1" <?= $rating_filter == '1' ? 'selected' : '' ?>>★☆☆☆☆ (1)</option>
            </select>
        </form>
    </div>

    <!-- Notifikasi Sukses -->
    <?php if (isset($_GET['status']) && $_GET['status'] == 'replied'): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md" role="alert">
            <p>Balasan Anda telah berhasil dikirim.</p>
        </div>
    <?php endif; ?>

    <!-- Daftar Ulasan -->
    <div class="space-y-6">
        <?php if (!empty($reviews)): ?>
            <?php foreach ($reviews as $review): ?>
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <!-- PERBAIKAN: Tampilkan 'name' dari tabel member, bukan 'username' -->
                            <p class="font-bold text-lg text-gray-800"><?= htmlspecialchars($review['name']) ?></p>
                            <p class="text-sm text-gray-500"><?= date('d M Y, H:i', strtotime($review['created_at'])) ?></p>
                        </div>
                        <div class="flex items-center text-yellow-500">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-yellow-500' : 'text-gray-300' ?>"></i>
                            <?php endfor; ?>
                            <span class="ml-2 text-sm text-gray-600">(<?= $review['rating'] ?>)</span>
                        </div>
                    </div>
                    <p class="text-gray-700 mt-4"><?= nl2br(htmlspecialchars($review['comment'] ?? '')) ?></p>

                    <!-- Area Balasan -->
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <?php if (!empty($review['reply'])): ?>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="font-semibold text-gray-700">Balasan Anda:</p>
                                <p class="text-gray-600 mt-1"><?= nl2br(htmlspecialchars($review['reply'])) ?></p>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                <textarea name="reply_text" rows="2" class="w-full border rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Tulis balasan Anda di sini..."></textarea>
                                <button type="submit" class="mt-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-semibold">
                                    Kirim Balasan
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-lg p-10 text-center text-gray-500">
                <p>Tidak ada ulasan yang ditemukan untuk filter yang dipilih.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once 'includes/footer.php';
$conn->close();
?>