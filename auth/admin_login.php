<?php
// File: admin_login.php
// Login untuk Admin dan Superadmin

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../app/config/db_connect.php';

// Jika sudah login sebagai admin, redirect ke dashboard
if (isset($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin', 'superadmin'])) {
    header('Location: ../admin/index.php');
    exit();
}

$error_message = '';
$success_message = '';

// Check for logout success message
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['error']) && isset($_GET['message']) && $_GET['message'] == 'logout_success') {
    $success_message = 'Anda telah berhasil logout dari admin panel.';
}

// Check for login error from session
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    $success_message = '';
    unset($_SESSION['login_error']);
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Username dan password harus diisi.';
    } else {
        try {
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

                // Check if user is active (jika kolom ada)
                if (isset($user['is_active']) && $user['is_active'] != 1) {
                    $error_message = 'Akun Anda tidak aktif. Hubungi administrator.';
                } else if (password_verify($password, $user['password'])) {
                    // Login successful
                    session_regenerate_id(true);

                    // Set session user data
                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'role' => $user['role']
                    ];
                    $_SESSION['login_time'] = time();

                    header('Location: ../admin/index.php');
                    exit();
                } else {
                    $error_message = 'Username atau password salah.';
                }
            } else {
                $error_message = 'Username atau password salah.';
            }
            $stmt->close();
        } catch (Exception $e) {
            error_log("Admin login error: " . $e->getMessage());
            $error_message = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }

    // If there's an error, store in session and redirect to clean URL
    if (!empty($error_message)) {
        $_SESSION['login_error'] = $error_message;
        header('Location: admin_login.php?error=1');
        exit();
    }
}

// Handle specific error messages from URL
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'auth_required':
            $error_message = 'Silakan login terlebih dahulu untuk mengakses halaman admin.';
            break;
        case 'insufficient_privileges':
            $error_message = 'Anda tidak memiliki hak akses yang cukup.';
            break;
        case 'session_expired':
            $error_message = 'Sesi Anda telah berakhir. Silakan login kembali.';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Cafe Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-gradient-to-br from-blue-900 to-indigo-800 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-user-shield text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Admin Panel</h1>
            <p class="text-gray-600">Masuk ke sistem manajemen cafe</p>
        </div>

        <!-- Success Message -->
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                <div class="flex">
                    <i class="fas fa-check-circle text-green-500 mr-3 mt-1"></i>
                    <span><?= htmlspecialchars($success_message) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <div class="flex">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3 mt-1"></i>
                    <span><?= htmlspecialchars($error_message) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="" class="space-y-6" autocomplete="off">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-user mr-2"></i>Username
                </label>
                <input type="text"
                    id="username"
                    name="username"
                    required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                    placeholder="Masukkan username admin"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-lock mr-2"></i>Password
                </label>
                <div class="relative">
                    <input type="password"
                        id="password"
                        name="password"
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200 pr-12"
                        placeholder="Masukkan password">
                    <button type="button"
                        onclick="togglePassword()"
                        class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-600 hover:text-gray-800">
                        <i id="passwordIcon" class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit"
                name="login"
                class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-3 px-6 rounded-lg font-semibold hover:from-blue-700 hover:to-indigo-700 transform hover:scale-105 transition duration-200 shadow-lg">
                <i class="fas fa-sign-in-alt mr-2"></i>Masuk Admin Panel
            </button>
        </form>

        <!-- Footer -->
        <div class="mt-8 text-center">
            <p class="text-sm text-gray-500">
                <i class="fas fa-shield-alt mr-1"></i>
                Sistem Keamanan Aktif
            </p>
            <div class="mt-4 pt-4 border-t border-gray-200">
                <a href="../index.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    <i class="fas fa-arrow-left mr-1"></i>Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        }
    </script>

</body>

</html>