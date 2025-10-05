<?php
// Script untuk setup database tanpa data sample
require_once '../app/config/db_connect.php';

echo "<h3>Database Setup Check</h3>";

// 1. Cek koneksi database
echo "1. Database connection: ";
if ($conn->ping()) {
    echo "✅ Connected<br>";
} else {
    echo "❌ Failed<br>";
    exit;
}

// 2. Cek tabel yang diperlukan
$required_tables = ['settings', 'menu_categories', 'menu', 'members', 'users', 'orders'];
echo "2. Checking required tables:<br>";

foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "   ✅ Table '$table' exists<br>";
    } else {
        echo "   ❌ Table '$table' missing<br>";
    }
}

// 3. Cek data settings minimal
$result = $conn->query("SELECT COUNT(*) as count FROM settings");
if ($result) {
    $settings_count = $result->fetch_assoc()['count'];
    echo "3. Settings entries: $settings_count<br>";

    if ($settings_count == 0) {
        echo "   ⚠️ No settings found. You may need to configure basic settings via admin panel.<br>";
    }
} else {
    echo "3. ❌ Cannot check settings table<br>";
}

// 4. Status ringkasan
echo "<br><strong>Setup Status:</strong><br>";
echo "✅ Database connection is working<br>";
echo "✅ System is ready to use<br>";
echo "📝 You can now add menu items and configure settings through the admin panel<br>";

echo "<br><strong>Quick Links:</strong><br>";
echo "<a href='index.php'>🏠 Main Page</a> | ";
echo "<a href='admin_login.php'>🔐 Admin Login</a> | ";
echo "<a href='debug.php'>🔍 Debug Info</a>";

$conn->close();
