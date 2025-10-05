<?php
// Script untuk test login functionality
require_once '../app/config/db_connect.php';

echo "<h3>Login System Test</h3>";

// Test database connection
if (!$conn || $conn->connect_error) {
    die("❌ Database connection failed");
}
echo "✅ Database connection OK<br><br>";

// Test admin login functionality
echo "<h4>Testing Admin Login:</h4>";
$username = 'admin';
$password = 'admin123';

// Cek apakah kolom is_active ada
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
if ($check_column->num_rows > 0) {
    $sql = "SELECT id, username, name, email, password, role, is_active FROM users WHERE username = ? AND role IN ('admin', 'superadmin') LIMIT 1";
} else {
    $sql = "SELECT id, username, name, email, password, role FROM users WHERE username = ? AND role IN ('admin', 'superadmin') LIMIT 1";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "✅ User found: {$user['name']} ({$user['role']})<br>";

    if (password_verify($password, $user['password'])) {
        echo "✅ Password verification successful<br>";
    } else {
        echo "❌ Password verification failed<br>";
    }

    if (isset($user['is_active'])) {
        echo "✅ Account status: " . ($user['is_active'] ? 'Active' : 'Inactive') . "<br>";
    } else {
        echo "⚠️ is_active column not present (assuming active)<br>";
    }
} else {
    echo "❌ Admin user not found<br>";
}

echo "<br>";

// Test kasir login functionality
echo "<h4>Testing Kasir Login:</h4>";
$username = 'kasir1';
$password = 'kasir123';

// Cek apakah kolom is_active ada
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
if ($check_column->num_rows > 0) {
    $sql = "SELECT id, name, password, role, is_active FROM users WHERE username = ? AND role IN ('cashier', 'admin') LIMIT 1";
} else {
    $sql = "SELECT id, name, password, role FROM users WHERE username = ? AND role IN ('cashier', 'admin') LIMIT 1";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $kasir = $result->fetch_assoc();
    echo "✅ Kasir found: {$kasir['name']} ({$kasir['role']})<br>";

    if (password_verify($password, $kasir['password'])) {
        echo "✅ Password verification successful<br>";
    } else {
        echo "❌ Password verification failed<br>";
    }

    if (isset($kasir['is_active'])) {
        echo "✅ Account status: " . ($kasir['is_active'] ? 'Active' : 'Inactive') . "<br>";
    } else {
        echo "⚠️ is_active column not present (assuming active)<br>";
    }
} else {
    echo "❌ Kasir user not found<br>";
}

echo "<br><hr><br>";
echo "<strong>Next Steps:</strong><br>";
echo "1. Run <a href='quick_setup.php'>quick_setup.php</a> to create missing users<br>";
echo "2. Try logging in to <a href='../auth/admin_login.php'>admin panel</a><br>";
echo "3. Try logging in to <a href='../cashier/login_kasir.php'>kasir panel</a><br>";

$conn->close();
?>

<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
        line-height: 1.6;
    }

    h3,
    h4 {
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