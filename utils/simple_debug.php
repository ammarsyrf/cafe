<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Quick Error Check</h3>";

// Test 1: Database connection
echo "Testing database connection...<br>";
try {
    require_once '../app/config/db_connect.php';
    if (isset($conn) && $conn->ping()) {
        echo " Database OK<br>";
    } else {
        echo " Database failed<br>";
    }
} catch (Exception $e) {
    echo " DB Error: " . $e->getMessage() . "<br>";
}

// Test 2: Users table
echo "<br>Testing users table...<br>";
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch_assoc();
    echo " Users table OK - {$row['count']} users found<br>";
} catch (Exception $e) {
    echo " Users table error: " . $e->getMessage() . "<br>";
}

// Test 3: Admin users
echo "<br>Testing admin users...<br>";
try {
    $result = $conn->query("SELECT username, role FROM users WHERE role IN ('admin', 'superadmin')");
    if ($result->num_rows > 0) {
        echo " Admin users found:<br>";
        while ($user = $result->fetch_assoc()) {
            echo "- {$user['username']} ({$user['role']})<br>";
        }
    } else {
        echo " No admin users found<br>";
    }
} catch (Exception $e) {
    echo " Admin check error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<a href='../auth/admin_login.php'>Test Admin Login</a><br>";
echo "<a href='../cashier/login_kasir.php'>Test Kasir Login</a>";
?>
