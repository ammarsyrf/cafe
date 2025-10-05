<?php
// Script untuk setup member default dan test login
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../app/config/db_connect.php';

echo "<h3>Member Login Fix & Setup</h3>";

// 1. Test database connection
if (!$conn || $conn->connect_error) {
    die("❌ Database connection failed: " . ($conn->connect_error ?? "Connection object not found"));
}
echo "✅ Database connected<br><br>";

// 2. Check members table structure
echo "<strong>Checking members table...</strong><br>";
$result = $conn->query("SHOW TABLES LIKE 'members'");
if ($result->num_rows == 0) {
    echo "❌ Members table doesn't exist. Creating it...<br>";

    $create_sql = "CREATE TABLE members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    if ($conn->query($create_sql)) {
        echo "✅ Members table created successfully<br>";
    } else {
        echo "❌ Failed to create members table: " . $conn->error . "<br>";
        exit;
    }
} else {
    echo "✅ Members table exists<br>";
}

// 3. Check if there are any members
$result = $conn->query("SELECT COUNT(*) as count FROM members");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    echo "<strong>No members found. Creating default member...</strong><br>";

    $email = 'member@cafe.com';
    $password = password_hash('member123', PASSWORD_DEFAULT);
    $name = 'Member Demo';
    $phone = '081234567890';

    $sql = "INSERT INTO members (name, email, password, phone) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $name, $email, $password, $phone);

    if ($stmt->execute()) {
        echo "✅ Default member created:<br>";
        echo "- Email: $email<br>";
        echo "- Password: member123<br>";
    } else {
        echo "❌ Failed to create default member: " . $stmt->error . "<br>";
    }
} else {
    echo "✅ Found {$row['count']} members in database<br>";

    // Show existing members
    $result = $conn->query("SELECT name, email FROM members LIMIT 5");
    echo "<strong>Existing members:</strong><br>";
    while ($member = $result->fetch_assoc()) {
        echo "- {$member['name']} ({$member['email']})<br>";
    }
}

echo "<br>";

// 4. Test login functionality
echo "<strong>Testing member login functionality...</strong><br>";
$test_email = 'member@cafe.com';
$test_password = 'member123';

$sql = "SELECT id, name, password FROM members WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $test_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $member = $result->fetch_assoc();
    echo "✅ Test member found: {$member['name']}<br>";

    if (password_verify($test_password, $member['password'])) {
        echo "✅ Password verification successful<br>";
    } else {
        echo "❌ Password verification failed<br>";
    }
} else {
    echo "❌ Test member not found<br>";
}

echo "<br><hr><br>";

// 5. Path verification
echo "<strong>Path Verification:</strong><br>";
$auth_files = [
    '../auth/login.php',
    '../auth/register.php',
    '../auth/logout.php'
];

foreach ($auth_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file not found<br>";
    }
}

echo "<br>";
echo "<strong>Test Login:</strong><br>";
echo "1. Go to main page: <a href='../index.php'>../index.php</a><br>";
echo "2. Click login and use:<br>";
echo "   - Email: member@cafe.com<br>";
echo "   - Password: member123<br>";

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

    strong {
        color: #555;
    }
</style>