<?php
// File: superadmin/penjualan/penjualan.php
require_once '../includes/header.php';
require_once '../../db_connect.php';

// Mengambil informasi toko dari database
$settings = [];
$settingsSql = "SELECT setting_name, setting_value FROM settings WHERE setting_name IN ('cafe_name', 'cafe_address', 'cafe_phone')";
if ($settingsResult = $conn->query($settingsSql)) {
    while ($row = $settingsResult->fetch_assoc()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
    $settingsResult->free();
}

// Menetapkan variabel informasi toko dengan fallback default
$nama_toko = $settings['cafe_name'] ?? 'Nama Toko Anda';
$alamat_toko = $settings['cafe_address'] ?? 'Jalan Alamat Toko No. 123';
$telepon_toko = $settings['cafe_phone'] ?? '0812-3456-7890';

// Ambil daftar semua kasir dari tabel users
$cashiers = [];
// Asumsi: pengguna dengan role 'kasir' dianggap sebagai kasir.
$cashierSql = "SELECT name FROM users WHERE role = 'cashier' ORDER BY name ASC";
if ($cashierResult = $conn->query($cashierSql)) {
    while ($row = $cashierResult->fetch_assoc()) {
        $cashiers[] = $row['name'];
    }
    $cashierResult->free();
}

// Atur tanggal default: 1 bulan terakhir
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Ambil data transaksi berdasarkan rentang tanggal
$transactions = [];

// [PERBAIKAN] Query untuk mengambil data member dengan benar.
// - Mengganti JOIN ke tabel members dari `o.user_id` menjadi `o.member_id`.
// - Kondisi `is_member` sekarang didasarkan pada `o.member_id IS NOT NULL`.
$sql = "SELECT 
            o.id as transaction_id, 
            o.created_at as transaction_date, 
            o.payment_method,
            o.discount_amount as member_discount,
            COALESCE(NULLIF(TRIM(mem.name), ''), NULLIF(TRIM(o.customer_name), ''), 'Guest') as customer_name_display,
            (o.member_id IS NOT NULL) as is_member,
            cashier.name as cashier_name,
            o.total_amount as total_paid,
            o.status as order_status,
            o.subtotal as order_subtotal,
            o.tax as order_tax,
            oi.quantity,
            m.name as menu_name,
            oi.price_per_item as item_price,
            oi.total_price as item_total_price,
            oi.selected_addons
        FROM orders o
        LEFT JOIN users cashier ON o.cashier_id = cashier.id
        LEFT JOIN members mem ON o.member_id = mem.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN menu m ON oi.menu_id = m.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        ORDER BY o.created_at DESC, o.id ASC";

$grouped_transactions = [];
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $transaction_id = $row['transaction_id'];
            if (!isset($grouped_transactions[$transaction_id])) {
                $grouped_transactions[$transaction_id] = [
                    'transaction_id' => $row['transaction_id'],
                    'transaction_date' => $row['transaction_date'],
                    'payment_method' => $row['payment_method'],
                    'member_discount' => $row['member_discount'],
                    'customer_name' => $row['customer_name_display'],
                    'is_member' => $row['is_member'],
                    'cashier_name' => $row['cashier_name'],
                    'total_paid' => $row['total_paid'],
                    'order_status' => $row['order_status'],
                    'order_subtotal' => $row['order_subtotal'],
                    'order_tax' => $row['order_tax'],
                    'items' => []
                ];
            }
            if ($row['menu_name']) {
                $grouped_transactions[$transaction_id]['items'][] = [
                    'quantity' => $row['quantity'],
                    'menu_name' => $row['menu_name'],
                    'item_price' => $row['item_price'],
                    'item_total_price' => $row['item_total_price'],
                    'selected_addons' => $row['selected_addons']
                ];
            }
        }
    } else {
        echo "Error executing query: " . $conn->error;
    }
    $stmt->close();
} else {
    echo "Error preparing statement: " . $conn->error;
}


// Hitung total untuk ringkasan statistik
$total_penjualan = array_sum(array_column($grouped_transactions, 'total_paid'));
$total_diskon = array_sum(array_column($grouped_transactions, 'member_discount'));
$jumlah_transaksi = count($grouped_transactions);
?>

<div class="container mx-auto p-4 md:p-6">
    <!-- Header Halaman -->
    <div class="flex flex-col md:flex-row justify-between items-start mb-6 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Riwayat Penjualan</h1>
            <p class="text-gray-600 mt-1">Menampilkan data dari <span class="font-semibold"><?= date('d M Y', strtotime($start_date)) ?></span> hingga <span class="font-semibold"><?= date('d M Y', strtotime($end_date)) ?></span>.</p>
        </div>
        <!-- Form Filter Tanggal -->
        <form method="GET" action="" class="flex items-center gap-2 bg-white p-3 rounded-lg shadow-md w-full md:w-auto">
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="border-gray-300 p-2 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
            <span class="text-gray-500">hingga</span>
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="border-gray-300 p-2 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-funnel-fill" viewBox="0 0 16 16">
                    <path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.74.439L7 11.583V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5v-2z" />
                </svg>
                <span>Filter</span>
            </button>
        </form>
    </div>

    <!-- Ringkasan Statistik -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl shadow-lg flex items-center gap-4">
            <div class="bg-blue-100 p-3 rounded-full"><svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v.01"></path>
                </svg></div>
            <div>
                <p class="text-sm text-gray-500">Total Penjualan</p>
                <p class="text-2xl font-bold text-gray-800">Rp <?= number_format($total_penjualan, 0, ',', '.') ?></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-lg flex items-center gap-4">
            <div class="bg-red-100 p-3 rounded-full"><svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                </svg></div>
            <div>
                <p class="text-sm text-gray-500">Total Diskon</p>
                <p class="text-2xl font-bold text-gray-800">Rp <?= number_format($total_diskon, 0, ',', '.') ?></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-lg flex items-center gap-4">
            <div class="bg-green-100 p-3 rounded-full"><svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M7 9l4-4 4 4M4 12h16M4 16h16"></path>
                </svg></div>
            <div>
                <p class="text-sm text-gray-500">Jumlah Transaksi</p>
                <p class="text-2xl font-bold text-gray-800"><?= $jumlah_transaksi ?></p>
            </div>
        </div>
    </div>

    <!-- Tabel Transaksi -->
    <div class="bg-white rounded-xl shadow-lg">
        <div class="p-4 border-b">
            <input type="text" id="searchInput" class="w-full md:w-1/3 pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" placeholder="Cari ID, nama pelanggan, atau kasir...">
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tanggal & Waktu</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama Pelanggan</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Kasir Transaksi</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Pesanan</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Metode Bayar</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Diskon (Rp)</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Total Bayar (Rp)</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody id="transaction-table-body">
                    <?php if (!empty($grouped_transactions)) : ?>
                        <?php foreach ($grouped_transactions as $tx_id => $tx) : ?>
                            <!-- Baris Utama (Bisa Diklik) -->
                            <tr class="transaction-row cursor-pointer" onclick="toggleDetails(this)" data-id="#<?= $tx['transaction_id'] ?>" data-name="<?= strtolower(htmlspecialchars($tx['customer_name'] ?? 'guest')) ?>" data-cashier="<?= strtolower(htmlspecialchars($tx['cashier_name'] ?? '')) ?>">
                                <td class="px-5 py-4 border-b border-gray-200 text-sm">
                                    #<?= $tx['transaction_id'] ?>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm"><?= date('d M Y, H:i', strtotime($tx['transaction_date'])) ?></td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm font-medium">
                                    <div class="flex items-center">
                                        <span class="<?= $tx['is_member'] ? 'text-green-700 font-bold' : 'text-gray-900' ?>">
                                            <?= htmlspecialchars($tx['customer_name']) ?>
                                        </span>
                                        <?php if ($tx['is_member']) : ?>
                                            <span class="ml-2 text-xs font-semibold bg-green-100 text-green-800 px-2 py-0.5 rounded-full">MEMBER</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm">
                                    <?php
                                    if (!empty($tx['cashier_name'])) {
                                        echo htmlspecialchars($tx['cashier_name']);
                                    } else {
                                        echo '<span class="font-semibold text-red-600">ID Kasir Hilang!</span>';
                                    }
                                    ?>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm text-gray-600">
                                    <span class="flex items-center">
                                        <?= count($tx['items']) ?> item
                                        <svg class="dropdown-icon w-4 h-4 ml-2 text-gray-500 transition-transform transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </span>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm capitalize"><?= str_replace('_', ' ', $tx['payment_method']) ?></td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm text-right text-red-600 font-semibold"><?= number_format($tx['member_discount'] ?? 0, 0, ',', '.') ?></td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm text-right font-bold text-gray-900"><?= number_format($tx['total_paid'], 0, ',', '.') ?></td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm text-center">
                                    <span class="capitalize px-3 py-1 text-xs font-semibold rounded-full 
                                        <?php
                                        switch ($tx['order_status']) {
                                            case 'completed':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'pending':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'cancelled':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?= htmlspecialchars($tx['order_status']) ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4 border-b border-gray-200 text-sm text-center">
                                    <button onclick="event.stopPropagation(); printReceipt('<?= $tx['transaction_id'] ?>')" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-1 px-3 rounded inline-flex items-center text-xs">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer-fill mr-1" viewBox="0 0 16 16">
                                            <path d="M5 1a2 2 0 0 0-2 2v1h10V3a2 2 0 0 0-2-2H5zm6 8H5a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1z" />
                                            <path d="M0 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-1v-2a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v2H2a2 2 0 0 1-2-2V7zm2.5 1a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z" />
                                        </svg>
                                        Cetak
                                    </button>
                                </td>
                            </tr>
                            <!-- Baris Detail (Tersembunyi) -->
                            <tr class="transaction-details-row bg-gray-50" style="display: none;">
                                <td colspan="10" class="p-4 border-b border-gray-200">
                                    <div class="max-w-md">
                                        <h4 class="text-md font-bold text-gray-700 mb-2">Detail Pesanan:</h4>
                                        <?php if (!empty($tx['items'])) : ?>
                                            <div class="space-y-1 text-sm mb-4">
                                                <?php foreach ($tx['items'] as $item) : ?>
                                                    <div class="py-2 border-b last:border-b-0">
                                                        <div class="space-y-1">
                                                            <div class="flex justify-between">
                                                                <span class="font-semibold"><?= htmlspecialchars($item['quantity']) ?>x <?= htmlspecialchars($item['menu_name']) ?></span>
                                                                <span>Rp <?= number_format($item['quantity'] * $item['item_price'], 0, ',', '.') ?></span>
                                                            </div>

                                                            <?php
                                                            $addons = json_decode($item['selected_addons'], true);
                                                            if (is_array($addons) && !empty($addons)) {
                                                                foreach ($addons as $addon) {
                                                                    $addon_price = $addon['price'] ?? 0;
                                                                    $addon_total = $item['quantity'] * $addon_price;
                                                                    echo '<div class="flex justify-between pl-4 text-gray-600 text-xs">';
                                                                    echo '<span>+ ' . htmlspecialchars($addon['option_name'] ?? 'Addon') . '</span>';
                                                                    echo '<span>' . 'Rp ' . number_format($addon_total, 0, ',', '.') . '</span>';
                                                                    echo '</div>';
                                                                }
                                                            }
                                                            ?>
                                                        </div>
                                                        <div class="flex justify-between items-center font-bold text-gray-800 border-t border-dashed mt-2 pt-1">
                                                            <span>Total Item</span>
                                                            <span>Rp <?= number_format($item['item_total_price'], 0, ',', '.') ?></span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <hr class="my-3 border-gray-200">

                                            <div class="text-sm text-gray-800 space-y-1 text-right">
                                                <div class="flex justify-between">
                                                    <span class="font-semibold">Subtotal:</span>
                                                    <span class="font-mono">Rp <?= number_format($tx['order_subtotal'], 0, ',', '.') ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="font-semibold">PPN (11%):</span>
                                                    <span class="font-mono">Rp <?= number_format($tx['order_tax'], 0, ',', '.') ?></span>
                                                </div>

                                                <?php if ($tx['is_member'] && $tx['member_discount'] > 0) : ?>
                                                    <div class="flex justify-between text-red-600">
                                                        <span class="font-semibold">Diskon Member:</span>
                                                        <span class="font-mono">- Rp <?= number_format($tx['member_discount'], 0, ',', '.') ?></span>
                                                    </div>
                                                <?php endif; ?>

                                                <hr class="my-1 border-gray-300">
                                                <div class="flex justify-between font-bold text-md mt-1">
                                                    <span>Total Bayar:</span>
                                                    <span class="font-mono">Rp <?= number_format($tx['total_paid'], 0, ',', '.') ?></span>
                                                </div>
                                            </div>
                                        <?php else : ?>
                                            <p class="text-sm text-gray-500">Tidak ada item dalam pesanan ini.</p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="10" class="text-center py-10 text-gray-500">Tidak ada transaksi pada rentang tanggal yang dipilih.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Menyimpan data transaksi dan info toko dalam variabel JavaScript
    const transactionsData = <?= json_encode(array_values($grouped_transactions)); ?>;
    const NAMA_TOKO = "<?= addslashes($nama_toko) ?>";
    const ALAMAT_TOKO = "<?= addslashes($alamat_toko) ?>";
    const TELEPON_TOKO = "<?= addslashes($telepon_toko) ?>";

    function toggleDetails(rowElement) {
        const detailsRow = rowElement.nextElementSibling;
        const icon = rowElement.querySelector('.dropdown-icon');

        document.querySelectorAll('.transaction-row.is-open').forEach(openRow => {
            if (openRow !== rowElement) {
                openRow.nextElementSibling.style.display = 'none';
                openRow.classList.remove('is-open');
                const openIcon = openRow.querySelector('.dropdown-icon');
                if (openIcon) openIcon.classList.remove('rotate-180');
            }
        });

        if (detailsRow.style.display === 'none') {
            detailsRow.style.display = '';
            rowElement.classList.add('is-open');
            if (icon) icon.classList.add('rotate-180');
        } else {
            detailsRow.style.display = 'none';
            rowElement.classList.remove('is-open');
            if (icon) icon.classList.remove('rotate-180');
        }
    }


    function printReceipt(transactionId) {
        const tx = transactionsData.find(t => t.transaction_id == transactionId);
        if (!tx) {
            return;
        }

        let itemsHtml = tx.items.map(item => {
            const basePrice = parseFloat(item.item_price);
            const qty = parseInt(item.quantity, 10);
            const baseItemTotal = basePrice * qty;

            let addonHtml = '';
            try {
                if (item.selected_addons && typeof item.selected_addons === 'string') {
                    const addons = JSON.parse(item.selected_addons);
                    if (Array.isArray(addons) && addons.length > 0) {
                        addonHtml = addons.map(addon => {
                            const addonPrice = parseFloat(addon.price || 0);
                            const addonName = addon.option_name || 'Addon';
                            const addonTotal = addonPrice * qty;
                            return `
                        <tr>
                            <td colspan="3" style="padding-left: 15px; font-size: 11px;">+ ${addonName}</td>
                            <td style="text-align: right; font-size: 11px;">${addonPrice > 0 ? number_format(addonTotal) : ''}</td>
                        </tr>`;
                        }).join('');
                    }
                }
            } catch (e) {
                console.error("Gagal mem-parsing JSON addons:", item.selected_addons);
            }

            // [BARU] Menambahkan Total per Item di struk
            const itemTotalHtml = `
                <tr>
                    <td colspan="4" style="padding: 2px 0;"><div style="border-top: 1px dashed #000;"></div></td>
                </tr>
                <tr>
                    <td colspan="2" style="padding-left: 15px; font-size: 11px; font-weight: bold;">Total Item</td>
                    <td colspan="2" style="text-align: right; font-size: 11px; font-weight: bold;">${number_format(item.item_total_price)}</td>
                </tr>`;

            return `
                <tr>
                    <td colspan="4" style="padding-top: 5px; font-weight: bold;">${item.menu_name}</td>
                </tr>
                <tr>
                    <td colspan="2" style="padding-left: 15px;">${qty} x ${number_format(basePrice)}</td>
                    <td colspan="2" style="text-align: right;">${number_format(baseItemTotal)}</td>
                </tr>
                ${addonHtml}
                ${itemTotalHtml}
            `;
        }).join('');

        const ppn = Math.round(tx.order_tax);
        const customerLabel = tx.is_member ? 'Member' : 'Pelanggan';

        const receiptContent = `
        <html>
            <head>
                <title>Struk Transaksi #${tx.transaction_id}</title>
                <style>
                    body { font-family: 'Courier New', monospace; font-size: 12px; margin: 0; padding: 10px; }
                    .container { width: 300px; margin: auto; }
                    h2, p { text-align: center; margin: 5px 0; }
                    hr { border: none; border-top: 1px solid #000; margin: 8px 0; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { padding: 1px 0; }
                    .text-right { text-align: right; }
                    .footer { text-align: center; margin-top: 15px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h2>${NAMA_TOKO}</h2>
                    <p>${ALAMAT_TOKO}</p>
                    <p>Telp: ${TELEPON_TOKO}</p>
                    <hr>
                    <p>ID: #${tx.transaction_id}<br>
                    Kasir: ${tx.cashier_name || '-'}<br>
                    ${customerLabel}: ${tx.customer_name || 'Guest'}<br>
                    Tanggal: ${new Date(tx.transaction_date).toLocaleString('id-ID')}</p>
                    <hr>
                    <table>
                        <tbody>
                            ${itemsHtml}
                        </tbody>
                    </table>
                    <hr>
                    <table>
                        <tbody>
                            <tr>
                                <td>Subtotal</td>
                                <td class="text-right">Rp ${number_format(tx.order_subtotal)}</td>
                            </tr>
                            <tr>
                                <td>PPN (11%)</td>
                                <td class="text-right">Rp ${number_format(ppn)}</td>
                            </tr>
                            <tr>
                                <td>Diskon</td>
                                <td class="text-right">- Rp ${number_format(tx.member_discount)}</td>
                            </tr>
                            <tr>
                                <td><strong>Total Bayar</strong></td>
                                <td class="text-right"><strong>Rp ${number_format(tx.total_paid)}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                    <hr>
                    <p class="footer">Terima kasih atas kunjungan Anda!</p>
                </div>
            </body>
        </html>
    `;

        const printWindow = window.open('', '_blank', 'width=320,height=500');
        printWindow.document.write(receiptContent);
        printWindow.document.close();
        printWindow.focus();
    }

    // Helper function untuk format angka
    function number_format(number) {
        const numericValue = parseFloat(number);
        if (isNaN(numericValue)) {
            return '0';
        }
        const options = {
            maximumFractionDigits: 0,
            minimumFractionDigits: 0
        };
        return new Intl.NumberFormat('id-ID', options).format(numericValue);
    }

    document.getElementById('searchInput').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('.transaction-row');

        rows.forEach(row => {
            let transactionId = row.getAttribute('data-id').toLowerCase();
            let customerName = row.getAttribute('data-name').toLowerCase();
            let cashierName = row.getAttribute('data-cashier').toLowerCase();
            let detailsRow = row.nextElementSibling;

            if (transactionId.includes(filter) || customerName.includes(filter) || cashierName.includes(filter)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
                if (detailsRow && detailsRow.classList.contains('transaction-details-row')) {
                    detailsRow.style.display = 'none';
                    const icon = row.querySelector('.dropdown-icon');
                    if (icon) {
                        icon.classList.remove('rotate-180');
                    }
                }
            }
        });
    });
</script>

<?php
require_once '../includes/footer.php';
$conn->close();
?>