<?php
// Script untuk membuat user admin/superadmin default
require_once '../app/config/db_connect.php';

echo "<h3>Create Default Admin User</h3>";

// Cek apakah tabel users sudah ada
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows == 0) {
    echo "❌ Table 'users' tidak ditemukan. Jalankan setup database terlebih dahulu.<br>";
    exit;
}

// Cek apakah sudah ada user admin
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role IN ('admin', 'superadmin')");
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    echo "✅ User admin sudah ada dalam database.<br>";

    // Tampilkan user admin yang ada
    $result = $conn->query("SELECT id, username, name, role, is_active FROM users WHERE role IN ('admin', 'superadmin')");
    echo "<h4>User Admin yang ada:</h4>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Username</th><th>Name</th><th>Role</th><th>Status</th></tr>";

    while ($user = $result->fetch_assoc()) {
        $status = $user['is_active'] ? 'Active' : 'Inactive';
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['name']}</td>";
        echo "<td>{$user['role']}</td>";
        echo "<td>{$status}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "⚠️ Tidak ada user admin. Membuat user default...<br>";

    // Data default admin
    $admin_data = [
        [
            'username' => 'admin',
            'password' => 'admin123',
            'name' => 'Administrator',
            'email' => 'admin@cafe.com',
            'role' => 'admin'
        ],
        [
            'username' => 'superadmin',
            'password' => 'super123',
            'name' => 'Super Administrator',
            'email' => 'superadmin@cafe.com',
            'role' => 'superadmin'
        ],
        [
            'username' => 'kasir1',
            'password' => 'kasir123',
            'name' => 'Kasir 1',
            'email' => 'kasir1@cafe.com',
            'role' => 'cashier'
        ]
    ];

    $sql = "INSERT INTO users (username, password, name, email, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())";
    $stmt = $conn->prepare($sql);

    foreach ($admin_data as $user) {
        $hashed_password = password_hash($user['password'], PASSWORD_DEFAULT);
        $stmt->bind_param("sssss", $user['username'], $hashed_password, $user['name'], $user['email'], $user['role']);

        if ($stmt->execute()) {
            echo "✅ User '{$user['username']}' berhasil dibuat (password: {$user['password']})<br>";
        } else {
            echo "❌ Gagal membuat user '{$user['username']}': " . $stmt->error . "<br>";
        }
    }

    $stmt->close();
}

// Cek struktur tabel users
echo "<h4>Struktur Tabel Users:</h4>";
$result = $conn->query("DESCRIBE users");
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h4>Info Login:</h4>";
echo "<strong>Admin Login:</strong><br>";
echo "- Username: admin, Password: admin123<br>";
echo "- Username: superadmin, Password: super123<br>";
echo "<strong>Kasir Login:</strong><br>";
echo "- Username: kasir1, Password: kasir123<br>";

$conn->close();
?>

<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
    }

    table {
        border-collapse: collapse;
    }

    th,
    td {
        padding: 8px 12px;
        border: 1px solid #ddd;
    }

    th {
        background-color: #f5f5f5;
    }

    .success {
        color: green;
    }

    .error {
        color: red;
    }

    .warning {
        color: orange;
    }
</style>