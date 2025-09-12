<?php
// File: superadmin/pelanggan/detail_pelanggan.php
require_once '../includes/header.php';
require_once '../../db_connect.php';

// Cek apakah ID ada di URL
if (!isset($_GET['id'])) {
    header("Location: pelanggan.php");
    exit();
}

$user_id = $_GET['id'];
$user = null;

// Ambil data member dari database
$sql = "SELECT id, username, email, phone_number, created_at FROM users WHERE id = ? AND role = 'member'";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
    }
    $stmt->close();
}

?>

<div class="container mx-auto">
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center mb-6">
            <a href="pelanggan.php" title="Kembali" class="text-gray-500 hover:text-blue-600 transition duration-300 text-2xl p-2 rounded-full hover:bg-gray-100 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Detail Member</h1>
        </div>

        <?php if ($user): ?>
        <div class="bg-white rounded-xl shadow-lg p-8">
            <div class="space-y-4">
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Nama Member</h3>
                    <p class="mt-1 text-lg text-gray-900 font-semibold"><?= htmlspecialchars($user['username']) ?></p>
                </div>
                <div class="border-t border-gray-200 pt-4">
                    <h3 class="text-sm font-medium text-gray-500">Email</h3>
                    <p class="mt-1 text-lg text-gray-900"><?= htmlspecialchars($user['email'] ?? 'Tidak ada data') ?></p>
                </div>
                <div class="border-t border-gray-200 pt-4">
                    <h3 class="text-sm font-medium text-gray-500">Nomor Telepon</h3>
                    <p class="mt-1 text-lg text-gray-900"><?= htmlspecialchars($user['phone_number'] ?? 'Tidak ada data') ?></p>
                </div>
                <div class="border-t border-gray-200 pt-4">
                    <h3 class="text-sm font-medium text-gray-500">Tanggal Bergabung</h3>
                    <p class="mt-1 text-lg text-gray-900"><?= date('d F Y, H:i', strtotime($user['created_at'])) ?></p>
                </div>
            </div>
            <div class="mt-8 pt-6 border-t border-gray-200 flex justify-end">
                <a href="edit_pelanggan.php?id=<?= $user['id'] ?>" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
                    <i class="fas fa-edit mr-2"></i> Edit Member
                </a>
            </div>
        </div>
        <?php else: ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-6 rounded-md text-center">
                <p class="font-bold">Data Member Tidak Ditemukan</p>
                <p class="mt-2">Member yang Anda cari mungkin telah dihapus atau tidak ada.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once '../includes/footer.php';
$conn->close();
?>
