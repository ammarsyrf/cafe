<?php
// File: member.php
// Halaman ini mengelola profil untuk member yang sedang login.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login sebagai member
if (!isset($_SESSION['member']) || $_SESSION['member']['role'] !== 'member') {
    header('Location: login.php');
    exit();
}

require_once 'app/config/db_connect.php';
require_once 'app/config/config.php'; // Untuk BASE_URL

// --- [PENAMBAHAN] AMBIL NAMA KAFE DARI PENGATURAN ---
$cafe_name = 'Nama Cafe'; // Nama default
$result_setting = $conn->query("SELECT setting_value FROM settings WHERE setting_name = 'cafe_name' LIMIT 1");
if ($result_setting && $result_setting->num_rows > 0) {
    $cafe_name = $result_setting->fetch_assoc()['setting_value'];
}

// Definisikan path untuk unggahan
define('UPLOAD_DIR', __DIR__ . '/uploads/profiles/');
define('UPLOAD_URL', BASE_URL . 'uploads/profiles/');

$member_id = $_SESSION['member']['id'];
$error_message = '';
$success_message = '';

// --- LOGIKA UNTUK UPDATE PROFIL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Basic form validation
    $name = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));
    $email = trim(htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'));
    $phone = trim(htmlspecialchars($_POST['phone_number'] ?? '', ENT_QUOTES, 'UTF-8'));
    $current_image_url = trim(htmlspecialchars($_POST['current_image_url'] ?? '', ENT_QUOTES, 'UTF-8'));

    // Validate input
    if (empty($name) || empty($email)) {
        $error_message = 'Nama dan email harus diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Format email tidak valid.';
    } elseif (!empty($phone) && !preg_match('/^[0-9+\-\s]+$/', $phone)) {
        $error_message = 'Format nomor telepon tidak valid.';
    } else {
        $profile_image_url = $current_image_url;

        // Handle file upload securely
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK) {
            try {
                $file = $_FILES['profile_image'];

                // Basic file validation
                $allowed_types = ['jpg', 'jpeg', 'png'];
                $max_size = 2097152; // 2MB limit
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($file_ext, $allowed_types)) {
                    throw new Exception('Format file tidak diizinkan. Gunakan JPG, JPEG, atau PNG.');
                }
                if ($file['size'] > $max_size) {
                    throw new Exception('Ukuran file terlalu besar. Maksimal 2MB.');
                }

                // Create upload directory if not exists
                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0755, true);
                }

                // Delete old image securely
                if ($current_image_url) {
                    $old_file_path = str_replace(UPLOAD_URL, UPLOAD_DIR, $current_image_url);
                    if (file_exists($old_file_path) && is_file($old_file_path)) {
                        unlink($old_file_path);
                    }
                }

                // Generate secure filename
                $new_filename = 'member_' . $member_id . '_' . time() . '.' . $file_ext;

                if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $new_filename)) {
                    $profile_image_url = UPLOAD_URL . $new_filename;
                } else {
                    throw new RuntimeException('Failed to move uploaded file.');
                }
            } catch (Exception $e) {
                $error_message = 'Gagal mengunggah gambar: ' . $e->getMessage();
            }
        }
    }

    if (empty($error_message)) {
        $stmt = $conn->prepare("UPDATE members SET name = ?, email = ?, phone_number = ?, profile_image_url = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $email, $phone, $profile_image_url, $member_id);
        if ($stmt->execute()) {
            $success_message = "Profil berhasil diperbarui.";
            $_SESSION['member']['name'] = $name;
        } else {
            $error_message = "Gagal memperbarui profil.";
        }
        $stmt->close();
    }
}


// --- AMBIL DATA TERBARU MEMBER ---
$member = null;
$stmt_member = $conn->prepare("SELECT * FROM members WHERE id = ?");
$stmt_member->bind_param("i", $member_id);
$stmt_member->execute();
$result = $stmt_member->get_result();
if ($result->num_rows > 0) {
    $member = $result->fetch_assoc();
} else {
    header('Location: auth/logout.php');
    exit();
}
$stmt_member->close();

// --- AMBIL RIWAYAT PESANAN DENGAN DETAIL ITEM & ADDON ---

// 1. Ambil semua pesanan utama (orders) untuk member ini
$orders = [];
$sql_orders = "SELECT id, created_at as order_date, status, total_amount, subtotal, tax, discount_amount
               FROM orders
               WHERE user_id = ? AND status IN ('completed', 'paid')
               ORDER BY created_at DESC";

$stmt_orders = $conn->prepare($sql_orders);
$stmt_orders->bind_param("i", $member_id);
$stmt_orders->execute();
$result_orders = $stmt_orders->get_result();
if ($result_orders) {
    $orders = $result_orders->fetch_all(MYSQLI_ASSOC);
}
$stmt_orders->close();

// 2. Ambil semua item pesanan (order_items) untuk pesanan-pesanan di atas
$order_ids = array_column($orders, 'id');
if (!empty($order_ids)) {
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $types = str_repeat('i', count($order_ids));

    $sql_items = "SELECT
                    oi.order_id,
                    oi.quantity,
                    oi.price_per_item,
                    oi.total_price,
                    oi.selected_addons,
                    m.name as menu_name
                  FROM order_items oi
                  JOIN menu m ON oi.menu_id = m.id
                  WHERE oi.order_id IN ($placeholders)";

    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bind_param($types, ...$order_ids);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    $all_order_items = [];
    while ($row = $result_items->fetch_assoc()) {
        $all_order_items[$row['order_id']][] = $row;
    }
    $stmt_items->close();

    // 3. Gabungkan item ke dalam array pesanan utama
    foreach ($orders as $key => $order) {
        $orders[$key]['items'] = $all_order_items[$order['id']] ?? [];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Member - <?= htmlspecialchars($member['name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .tab-button.active {
            color: #1f2937;
            background-color: #f3f4f6;
        }
    </style>
</head>

<body class="bg-gray-50">

    <!-- Navbar -->
    <nav class="bg-white shadow-sm sticky top-0 z-40">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <a href="index.php" class="text-xl font-black text-gray-900 tracking-tighter"><?= htmlspecialchars($cafe_name); ?></a>
            <div class="flex items-center space-x-2">
                <a href="index.php" class="text-gray-800 px-4 py-2 rounded-full font-bold hover:bg-gray-100 text-sm flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Kembali
                </a>
                <a href="auth/logout.php" class="bg-red-500 text-white px-4 py-2 rounded-full font-bold hover:bg-red-600 text-sm">Logout</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-8 max-w-lg">
        <!-- Notifikasi -->
        <?php if ($success_message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md" role="alert">
                <p><?= $success_message ?></p>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
                <p><?= $error_message ?></p>
            </div>
        <?php endif; ?>

        <!-- Kartu Member Digital -->
        <section class="bg-gradient-to-br from-gray-800 to-gray-900 text-white p-6 rounded-2xl shadow-lg mb-8">
            <div class="flex justify-between items-start mb-4">
                <div class="flex items-center gap-4">
                    <img src="<?= htmlspecialchars($member['profile_image_url'] ?? 'https://placehold.co/64x64/e2e8f0/64748b?text=' . substr($member['name'], 0, 1)) ?>" alt="Profil" class="w-16 h-16 rounded-full object-cover border-2 border-gray-500">
                    <div>
                        <p class="text-sm text-gray-300">Selamat Datang,</p>
                        <h2 class="text-2xl font-bold"><?= htmlspecialchars($member['name']); ?></h2>
                    </div>
                </div>
                <div class="text-right"><i class="fas fa-star text-yellow-400 text-2xl"></i></div>
            </div>
            <div class="text-center my-6">
                <p class="text-lg font-semibold text-gray-300">Poin Anda Saat Ini</p>
                <p class="text-5xl font-extrabold tracking-tight"><?= number_format($member['points'] ?? 0); ?> Poin</p>
            </div>
        </section>

        <!-- Menu Navigasi Member -->
        <div class="bg-white rounded-full shadow-md p-2 flex justify-around mb-8">
            <button onclick="switchTab('profil')" class="tab-button active w-full text-center font-semibold p-3 rounded-full"><i class="fas fa-user mr-2 opacity-80"></i>Profil</button>
            <button onclick="switchTab('riwayat')" class="tab-button w-full text-center font-semibold text-gray-500 p-3 rounded-full"><i class="fas fa-receipt mr-2 opacity-80"></i>Riwayat</button>
            <button onclick="switchTab('keuntungan')" class="tab-button w-full text-center font-semibold text-gray-500 p-3 rounded-full"><i class="fas fa-tags mr-2 opacity-80"></i>Keuntungan</button>
        </div>

        <!-- Konten Dinamis -->
        <div id="tab-content">
            <!-- Konten Tab: Profil -->
            <div id="profil-content" class="tab-pane active space-y-6">
                <div class="bg-white p-6 rounded-xl shadow-sm">
                    <h3 class="font-bold text-xl mb-4 text-gray-800">Ubah Informasi Akun</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="current_image_url" value="<?= htmlspecialchars($member['profile_image_url'] ?? '') ?>">
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs font-semibold text-gray-500">FOTO PROFIL</label>
                                <div class="mt-1 flex items-center gap-4">
                                    <img id="image-preview" src="<?= htmlspecialchars($member['profile_image_url'] ?? 'https://placehold.co/80x80/e2e8f0/64748b?text=' . substr($member['name'], 0, 1)) ?>" class="w-20 h-20 rounded-full object-cover">
                                    <input type="file" name="profile_image" id="profile_image" class="hidden" onchange="previewImage(event)">
                                    <label for="profile_image" class="cursor-pointer bg-white py-2 px-3 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">Ganti Foto</label>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-500">NAMA LENGKAP</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($member['name']); ?>" class="mt-1 w-full border rounded-lg p-2">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-500">EMAIL</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($member['email']); ?>" class="mt-1 w-full border rounded-lg p-2">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-500">NOMOR TELEPON</label>
                                <input type="text" name="phone_number" value="<?= htmlspecialchars($member['phone_number']); ?>" class="mt-1 w-full border rounded-lg p-2">
                            </div>
                        </div>
                        <button type="submit" class="mt-6 w-full bg-gray-800 text-white py-3 rounded-lg font-semibold hover:bg-gray-900">Simpan Perubahan</button>
                    </form>
                </div>
            </div>

            <!-- Konten Tab: Riwayat Pesanan -->
            <div id="riwayat-content" class="tab-pane hidden space-y-4">
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                            <div class="p-5 cursor-pointer flex justify-between items-center" onclick="toggleOrderDetails(<?= $order['id']; ?>)">
                                <div class="flex-1">
                                    <p class="font-bold text-gray-800">Pesanan #<?= htmlspecialchars($order['id']); ?></p>
                                    <p class="text-sm text-gray-500"><?= date('d M Y, H:i', strtotime($order['order_date'])); ?></p>
                                </div>
                                <div class="text-right mx-4">
                                    <p class="font-bold text-lg">Rp <?= number_format($order['total_amount'] ?? 0); ?></p>
                                    <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-1 rounded-full"><?= htmlspecialchars(ucfirst($order['status'])); ?></span>
                                </div>
                                <i id="icon-<?= $order['id']; ?>" class="fas fa-chevron-down text-gray-400 transition-transform"></i>
                            </div>
                            <div id="details-<?= $order['id']; ?>" class="hidden px-5 pb-5 border-t border-gray-200">
                                <h4 class="font-semibold text-gray-700 pt-4 mb-3">Rincian Item:</h4>
                                <div class="space-y-3 text-sm mb-4">
                                    <?php if (!empty($order['items'])): ?>
                                        <?php foreach ($order['items'] as $item): ?>
                                            <div class="pb-3 border-b border-dashed last:border-b-0">
                                                <div class="flex justify-between items-start mb-1">
                                                    <span class="font-medium text-gray-800"><?= $item['quantity']; ?>x <?= htmlspecialchars($item['menu_name']); ?></span>
                                                    <span class="font-medium text-gray-800">Rp <?= number_format($item['price_per_item'] * $item['quantity']); ?></span>
                                                </div>
                                                <?php
                                                if (!empty($item['selected_addons'])) {
                                                    $addons = json_decode($item['selected_addons'], true);
                                                    if (is_array($addons)) {
                                                        foreach ($addons as $addon) {
                                                ?>
                                                            <div class="flex justify-between items-center text-xs text-gray-500 pl-4">
                                                                <span>+ <?= htmlspecialchars($addon['name'] ?? $addon['option_name'] ?? ''); ?></span>
                                                                <span>Rp <?= number_format($addon['price'] ?? 0); ?></span>
                                                            </div>
                                                <?php
                                                        }
                                                    }
                                                }
                                                ?>
                                                <div class="flex justify-between items-center mt-2 font-bold text-gray-900">
                                                    <span>Total Item</span>
                                                    <span>Rp <?= number_format($item['total_price']); ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-gray-500">Tidak ada item dalam pesanan ini.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="border-t border-gray-200 pt-3">
                                    <h4 class="font-semibold text-gray-700 mb-2">Rincian Biaya:</h4>
                                    <div class="space-y-1 text-sm text-gray-600">
                                        <div class="flex justify-between">
                                            <span>Subtotal</span>
                                            <span>Rp <?= number_format($order['subtotal']); ?></span>
                                        </div>
                                        <?php if ($order['discount_amount'] > 0): ?>
                                            <div class="flex justify-between text-red-500">
                                                <span>Diskon</span>
                                                <span>- Rp <?= number_format($order['discount_amount']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex justify-between">
                                            <span>PPN (11%)</span>
                                            <span>Rp <?= number_format($order['tax']); ?></span>
                                        </div>
                                        <div class="flex justify-between font-bold text-gray-800 mt-2 pt-2 border-t">
                                            <span>Total Bayar</span>
                                            <span>Rp <?= number_format($order['total_amount']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-white p-6 rounded-xl shadow-sm text-center">
                        <i class="fas fa-box-open text-4xl text-gray-300 mb-3"></i>
                        <p class="text-center text-gray-500">Anda belum memiliki riwayat pesanan.</p>
                    </div>
                <?php endif; ?>
            </div>


            <!-- Konten Tab: Keuntungan -->
            <div id="keuntungan-content" class="tab-pane hidden space-y-4">
                <div class="bg-white p-6 rounded-xl shadow-sm">
                    <h3 class="font-bold text-xl mb-4 text-gray-800">Diskon Khusus Member</h3>
                    <ul class="list-disc list-inside text-gray-700 space-y-2">
                        <li>Diskon <b>5%</b> untuk total belanja di atas Rp 50.000</li>
                        <li>Diskon <b>10%</b> untuk total belanja di atas Rp 100.000</li>
                        <li>Diskon <b>15%</b> untuk total belanja di atas Rp 200.000</li>
                    </ul>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm">
                    <h3 class="font-bold text-xl mb-4 text-gray-800">Tukar Poin Anda</h3>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg mb-3">
                        <div>
                            <p class="font-bold text-gray-800">Gratis 1 Kopi Susu</p>
                            <p class="text-sm text-yellow-600 font-semibold">100 Poin</p>
                        </div>
                        <button onclick="showPoinModal('Gratis 1 Kopi Susu')" class="bg-yellow-500 text-white px-4 py-2 rounded-lg font-bold hover:bg-yellow-600">Tukar</button>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div>
                            <p class="font-bold text-gray-800">Potongan Harga Rp 10.000</p>
                            <p class="text-sm text-yellow-600 font-semibold">500 Poin</p>
                        </div>
                        <button onclick="showPoinModal('Potongan Harga Rp 10.000')" class="bg-yellow-500 text-white px-4 py-2 rounded-lg font-bold hover:bg-yellow-600">Tukar</button>
                    </div>
                    <div class="mt-4 text-center text-sm text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>
                        Fitur penukaran poin belum tersedia. Silakan tunggu update selanjutnya.
                    </div>
                    <!-- Modal for interactive notification -->
                    <div id="poinModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 hidden">
                        <div class="bg-white rounded-xl shadow-lg p-8 max-w-sm w-full text-center relative">
                            <button onclick="closePoinModal()" class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 text-xl">&times;</button>
                            <div class="mb-4">
                                <i class="fas fa-gift text-yellow-500 text-4xl mb-2"></i>
                                <h4 id="poinModalTitle" class="font-bold text-lg text-gray-800 mb-2"></h4>
                                <p class="text-gray-600">Fitur penukaran poin untuk hadiah ini belum tersedia.<br>Silakan tunggu update selanjutnya.</p>
                            </div>
                            <button onclick="closePoinModal()" class="bg-yellow-500 text-white px-6 py-2 rounded-lg font-bold hover:bg-yellow-600 mt-2">Tutup</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        function showPoinModal(reward) {
            document.getElementById('poinModalTitle').textContent = reward;
            document.getElementById('poinModal').classList.remove('hidden');
        }

        function closePoinModal() {
            document.getElementById('poinModal').classList.add('hidden');
        }

        function switchTab(tabName) {
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));
            const activePane = document.getElementById(tabName + '-content');
            if (activePane) {
                activePane.classList.remove('hidden');
            }
            document.querySelectorAll('.tab-button').forEach(b => {
                b.classList.remove('active');
                b.classList.add('text-gray-500');
            });
            event.currentTarget.classList.add('active');
            event.currentTarget.classList.remove('text-gray-500');
        }

        function previewImage(event) {
            const reader = new FileReader();
            const preview = document.getElementById('image-preview');
            reader.onload = () => {
                if (preview) {
                    preview.src = reader.result;
                }
            }
            if (event.target.files[0]) {
                reader.readAsDataURL(event.target.files[0]);
            }
        }

        function toggleOrderDetails(orderId) {
            const details = document.getElementById('details-' + orderId);
            const icon = document.getElementById('icon-' + orderId);
            if (details && icon) {
                details.classList.toggle('hidden');
                icon.classList.toggle('rotate-180');
            }
        }
    </script>
</body>

</html>