<?php
// Script sederhana untuk membuat user admin jika belum ada
require_once '../app/config/db_connect.php';

echo "<h3>Quick Admin Setup</h3>";

// Test koneksi database
if (!$conn || $conn->connect_error) {
    die("❌ Database connection failed: " . ($conn->connect_error ?? "Connection object not found"));
}

echo "✅ Database connected successfully<br><br>";

// Cek struktur tabel users
echo "<strong>Checking users table structure...</strong><br>";
$result = $conn->query("SHOW COLUMNS FROM users");
if (!$result) {
    die("❌ Users table not found: " . $conn->error);
}

$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}
echo "Available columns: " . implode(', ', $columns) . "<br><br>";

// Tambahkan kolom is_active jika belum ada
if (!in_array('is_active', $columns)) {
    echo "<strong>Adding missing is_active column...</strong><br>";
    $sql = "ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1";
    if ($conn->query($sql)) {
        echo "✅ Column is_active added successfully<br>";
    } else {
        echo "❌ Failed to add is_active column: " . $conn->error . "<br>";
    }
}

// Tambahkan kolom email jika belum ada
if (!in_array('email', $columns)) {
    echo "<strong>Adding missing email column...</strong><br>";
    $sql = "ALTER TABLE users ADD COLUMN email VARCHAR(255) DEFAULT NULL";
    if ($conn->query($sql)) {
        echo "✅ Column email added successfully<br>";
    } else {
        echo "❌ Failed to add email column: " . $conn->error . "<br>";
    }
}

echo "<br>";

// Cek apakah ada user admin
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role IN ('admin', 'superadmin')");
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    echo "<strong>Existing admin users:</strong><br>";
    $result = $conn->query("SELECT username, name, role FROM users WHERE role IN ('admin', 'superadmin', 'cashier')");
    while ($user = $result->fetch_assoc()) {
        echo "- {$user['name']} ({$user['username']}) - {$user['role']}<br>";
    }
} else {
    echo "<strong>No admin users found. Creating default users...</strong><br>";

    $users = [
        ['admin', 'admin123', 'Administrator', 'admin@cafe.com', 'admin'],
        ['superadmin', 'super123', 'Super Administrator', 'superadmin@cafe.com', 'superadmin'],
        ['kasir1', 'kasir123', 'Kasir Pertama', 'kasir1@cafe.com', 'cashier']
    ];

    foreach ($users as $userData) {
        $username = $userData[0];
        $password = password_hash($userData[1], PASSWORD_DEFAULT);
        $name = $userData[2];
        $email = $userData[3];
        $role = $userData[4];

        $sql = "INSERT INTO users (username, password, name, email, role, is_active) VALUES (?, ?, ?, ?, ?, 1)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $username, $password, $name, $email, $role);

        if ($stmt->execute()) {
            echo "✅ Created user: $username (password: {$userData[1]})<br>";
        } else {
            echo "❌ Failed to create $username: " . $stmt->error . "<br>";
        }
    }
}

echo "<br><hr><br>";
echo "<strong>Login Information:</strong><br>";
echo "Admin Panel: <a href='../auth/admin_login.php'>../auth/admin_login.php</a><br>";
echo "- admin / admin123<br>";
echo "- superadmin / super123<br><br>";
echo "Cashier Panel: <a href='../cashier/login_kasir.php'>../cashier/login_kasir.php</a><br>";
echo "- kasir1 / kasir123<br>";

$conn->close();
?>

<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
        line-height: 1.6;
    }

    h3 {
        color: #333;
    }

    a {
        color: #007cba;
        text-decoration: none;
    }

    a:hover {
        text-decoration: underline;
    }
</style>