<?php
// Script untuk memeriksa dan memperbaiki struktur database
require_once '../app/config/db_connect.php';

echo "<h3>Database Structure Check & Fix</h3>";

// 1. Cek struktur tabel users
echo "<h4>1. Struktur Tabel Users Saat Ini:</h4>";
$result = $conn->query("DESCRIBE users");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";

    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // 2. Cek kolom yang diperlukan
    echo "<h4>2. Memeriksa Kolom yang Diperlukan:</h4>";
    $required_columns = ['id', 'username', 'password', 'name', 'email', 'role', 'is_active', 'created_at'];

    foreach ($required_columns as $col) {
        if (in_array($col, $columns)) {
            echo "✅ Kolom '$col' sudah ada<br>";
        } else {
            echo "❌ Kolom '$col' tidak ada - akan ditambahkan<br>";

            switch ($col) {
                case 'is_active':
                    $sql = "ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1";
                    if ($conn->query($sql)) {
                        echo "✅ Kolom 'is_active' berhasil ditambahkan<br>";
                    } else {
                        echo "❌ Gagal menambahkan kolom 'is_active': " . $conn->error . "<br>";
                    }
                    break;
                case 'created_at':
                    $sql = "ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                    if ($conn->query($sql)) {
                        echo "✅ Kolom 'created_at' berhasil ditambahkan<br>";
                    } else {
                        echo "❌ Gagal menambahkan kolom 'created_at': " . $conn->error . "<br>";
                    }
                    break;
                case 'email':
                    $sql = "ALTER TABLE users ADD COLUMN email VARCHAR(255) DEFAULT NULL";
                    if ($conn->query($sql)) {
                        echo "✅ Kolom 'email' berhasil ditambahkan<br>";
                    } else {
                        echo "❌ Gagal menambahkan kolom 'email': " . $conn->error . "<br>";
                    }
                    break;
            }
        }
    }
} else {
    echo "❌ Tidak dapat mengakses tabel users: " . $conn->error . "<br>";
}

// 3. Cek apakah ada user admin
echo "<h4>3. Memeriksa User Admin:</h4>";
$result = $conn->query("SELECT * FROM users WHERE role IN ('admin', 'superadmin') LIMIT 5");
if ($result && $result->num_rows > 0) {
    echo "✅ User admin ditemukan:<br>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Username</th><th>Name</th><th>Role</th><th>Status</th></tr>";

    while ($user = $result->fetch_assoc()) {
        $status = isset($user['is_active']) ? ($user['is_active'] ? 'Active' : 'Inactive') : 'Unknown';
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

    // Buat user admin default
    $admin_users = [
        ['admin', 'admin123', 'Administrator', 'admin'],
        ['superadmin', 'super123', 'Super Admin', 'superadmin'],
        ['kasir1', 'kasir123', 'Kasir 1', 'cashier']
    ];

    foreach ($admin_users as $user_data) {
        $username = $user_data[0];
        $password = password_hash($user_data[1], PASSWORD_DEFAULT);
        $name = $user_data[2];
        $role = $user_data[3];

        $sql = "INSERT INTO users (username, password, name, role, is_active) VALUES (?, ?, ?, ?, 1)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $username, $password, $name, $role);

        if ($stmt->execute()) {
            echo "✅ User '$username' berhasil dibuat (password: {$user_data[1]})<br>";
        } else {
            echo "❌ Gagal membuat user '$username': " . $stmt->error . "<br>";
        }
    }
}

// 4. Test koneksi database
echo "<h4>4. Test Koneksi Database:</h4>";
if ($conn->ping()) {
    echo "✅ Database connection OK<br>";
    echo "Database: " . $conn->get_server_info() . "<br>";
} else {
    echo "❌ Database connection failed<br>";
}

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

    h3,
    h4 {
        color: #333;
    }
</style>