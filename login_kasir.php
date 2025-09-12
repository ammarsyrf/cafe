<?php
// File: login_kasir.php
session_start();
require_once 'db_connect.php';

// Jika sudah login, redirect ke halaman kasir
if (isset($_SESSION['id']) && $_SESSION['role'] === 'cashier') {
    header("Location: kasir.php");
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error_message = 'Username dan password tidak boleh kosong.';
    } else {
        $sql = "SELECT id, name, password, role FROM users WHERE username = ? AND role = 'cashier'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Verifikasi password (asumsi password di-hash dengan password_hash())
            if (password_verify($password, $user['password'])) {
                // Login berhasil
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                header("Location: kasir.php");
                exit();
            } else {
                $error_message = 'Username atau password salah.';
            }
        } else {
            $error_message = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Kasir</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-xl shadow-lg">
        <h1 class="text-3xl font-bold text-center text-gray-800">Login Kasir</h1>
        <form method="POST" class="space-y-6">
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
                </div>
            <?php endif; ?>
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                <input type="text" name="username" id="username" required
                    class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" id="password" required
                    class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div>
                <button type="submit"
                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Masuk
                </button>
            </div>
        </form>
    </div>
</body>

</html>