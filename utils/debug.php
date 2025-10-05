<?php<?php

// Debug script untuk melihat error yang terjadi// Debug script untuk memeriksa data

error_reporting(E_ALL);require_once '../app/config/db_connect.php';

ini_set('display_errors', 1);

echo "<h3>Debug Information</h3>";

echo "<h3>System Debug - Error Detection</h3>";

echo "<hr>";// Test koneksi database

echo "1. Database connection: ";

// 1. Test database connectionif ($conn->ping()) {

echo "<h4>1. Database Connection Test</h4>";    echo "✅ Connected<br>";

try {} else {

    require_once '../app/config/db_connect.php';    echo "❌ Failed<br>";

    if (isset($conn) && $conn) {    exit;

        if ($conn->ping()) {}

            echo "✅ Database connection successful<br>";

            echo "Server info: " . $conn->server_info . "<br>";// Periksa tabel menu

        } else {echo "2. Menu table data:<br>";

            echo "❌ Database ping failed<br>";$sql = "SELECT COUNT(*) as total FROM menu";

        }$result = $conn->query($sql);

    } else {if ($result) {

        echo "❌ Database connection object not created<br>";    $row = $result->fetch_assoc();

    }    echo "Total menu items: " . $row['total'] . "<br>";

} catch (Exception $e) {} else {

    echo "❌ Database connection error: " . $e->getMessage() . "<br>";    echo "Error querying menu table: " . $conn->error . "<br>";

}}



echo "<br>";// Periksa data menu

echo "3. Sample menu items:<br>";

// 2. Test users table structure$sql = "SELECT id, name, category, is_available FROM menu LIMIT 5";

echo "<h4>2. Users Table Structure</h4>";$result = $conn->query($sql);

try {if ($result && $result->num_rows > 0) {

    if (isset($conn)) {    while ($row = $result->fetch_assoc()) {

        $result = $conn->query("DESCRIBE users");        echo "- ID: {$row['id']}, Name: {$row['name']}, Category: {$row['category']}, Available: {$row['is_available']}<br>";

        if ($result) {    }

            echo "✅ Users table exists<br>";} else {

            echo "<table border='1' style='border-collapse: collapse;'>";    echo "No menu items found or error: " . $conn->error . "<br>";

            echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";}

            while ($row = $result->fetch_assoc()) {

                echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";// Periksa tabel settings

            }echo "4. Settings table:<br>";

            echo "</table>";$sql = "SELECT COUNT(*) as total FROM settings";

        } else {$result = $conn->query($sql);

            echo "❌ Users table not found or error: " . $conn->error . "<br>";if ($result) {

        }    $row = $result->fetch_assoc();

    }    echo "Total settings: " . $row['total'] . "<br>";

} catch (Exception $e) {} else {

    echo "❌ Error checking users table: " . $e->getMessage() . "<br>";    echo "Error querying settings table: " . $conn->error . "<br>";

}}



echo "<br>";// Periksa APP_CONFIG

echo "5. APP_CONFIG:<br>";

// 3. Test untuk user adminif (isset($APP_CONFIG) && !empty($APP_CONFIG)) {

echo "<h4>3. Admin Users Check</h4>";    echo "Cafe name: " . ($APP_CONFIG['cafe_name'] ?? 'Not set') . "<br>";

try {} else {

    if (isset($conn)) {    echo "APP_CONFIG is empty or not set<br>";

        $result = $conn->query("SELECT username, name, role FROM users WHERE role IN ('admin', 'superadmin') LIMIT 5");}

        if ($result && $result->num_rows > 0) {

            echo "✅ Admin users found:<br>";$conn->close();

            while ($user = $result->fetch_assoc()) {
                echo "- {$user['name']} ({$user['username']}) - {$user['role']}<br>";
            }
        } else {
            echo "❌ No admin users found<br>";
            echo "SQL Error: " . $conn->error . "<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Error checking admin users: " . $e->getMessage() . "<br>";
}

echo "<br>";

// 4. Test session functionality
echo "<h4>4. Session Test</h4>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "✅ Session is active<br>";
    echo "Session ID: " . session_id() . "<br>";
} else {
    echo "❌ Session not active<br>";
}

echo "<br>";

// 5. Test specific login function
echo "<h4>5. Login Functionality Test</h4>";
if (isset($conn)) {
    $test_username = 'admin';
    
    // Test SQL query that admin_login.php uses
    try {
        $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
        if ($check_column->num_rows > 0) {
            $sql = "SELECT id, username, name, email, password, role, is_active FROM users WHERE username = ? AND role IN ('admin', 'superadmin') LIMIT 1";
            echo "✅ Using query with is_active column<br>";
        } else {
            $sql = "SELECT id, username, name, email, password, role FROM users WHERE username = ? AND role IN ('admin', 'superadmin') LIMIT 1";
            echo "✅ Using query without is_active column<br>";
        }
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            echo "✅ SQL statement prepared successfully<br>";
            $stmt->bind_param("s", $test_username);
            $stmt->execute();
            $result = $stmt->get_result();
            echo "✅ SQL query executed successfully<br>";
            echo "Result rows: " . $result->num_rows . "<br>";
        } else {
            echo "❌ SQL statement preparation failed: " . $conn->error . "<br>";
        }
    } catch (Exception $e) {
        echo "❌ Login test error: " . $e->getMessage() . "<br>";
    }
}

echo "<hr>";
echo "<h4>Quick Actions:</h4>";
echo "<a href='quick_setup.php'>🔧 Run Quick Setup</a> | ";
echo "<a href='../auth/admin_login.php'>🔑 Try Admin Login</a> | ";
echo "<a href='../cashier/login_kasir.php'>💰 Try Kasir Login</a>";

if (isset($conn)) {
    $conn->close();
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.5; }
    h3, h4 { color: #333; }
    table { margin: 10px 0; }
    th, td { padding: 5px 10px; text-align: left; }
    th { background-color: #f5f5f5; }
    a { color: #007cba; text-decoration: none; margin-right: 10px; }
    a:hover { text-decoration: underline; }
</style>