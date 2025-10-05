<?php
// Debug script untuk memeriksa data
require_once '../app/config/db_connect.php';

echo "<h3>Debug Information</h3>";

// Test koneksi database
echo "1. Database connection: ";
if ($conn->ping()) {
    echo "✅ Connected<br>";
} else {
    echo "❌ Failed<br>";
    exit;
}

// Periksa tabel menu
echo "2. Menu table data:<br>";
$sql = "SELECT COUNT(*) as total FROM menu";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    echo "Total menu items: " . $row['total'] . "<br>";
} else {
    echo "Error querying menu table: " . $conn->error . "<br>";
}

// Periksa data menu
echo "3. Sample menu items:<br>";
$sql = "SELECT id, name, category, is_available FROM menu LIMIT 5";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "- ID: {$row['id']}, Name: {$row['name']}, Category: {$row['category']}, Available: {$row['is_available']}<br>";
    }
} else {
    echo "No menu items found or error: " . $conn->error . "<br>";
}

// Periksa tabel settings
echo "4. Settings table:<br>";
$sql = "SELECT COUNT(*) as total FROM settings";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    echo "Total settings: " . $row['total'] . "<br>";
} else {
    echo "Error querying settings table: " . $conn->error . "<br>";
}

// Periksa APP_CONFIG
echo "5. APP_CONFIG:<br>";
if (isset($APP_CONFIG) && !empty($APP_CONFIG)) {
    echo "Cafe name: " . ($APP_CONFIG['cafe_name'] ?? 'Not set') . "<br>";
} else {
    echo "APP_CONFIG is empty or not set<br>";
}

$conn->close();
