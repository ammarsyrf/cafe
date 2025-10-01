<?php
// File: admin_login.php
// Login untuk Admin dan Superadmin

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';

// Jika sudah login sebagai admin, redirect ke dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: superadmin/index.php');
    exit();
}

$error_message = '';
$success_message = '';

// Check for logout success message (hanya jika bukan POST request dan tidak ada error)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['error']) && isset($_GET['message']) && $_GET['message'] == 'logout_success') {
    $success_message = 'Anda telah berhasil logout dari admin panel.';
}

// Check for login error from session (setelah redirect)
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    $success_message = ''; // Clear success message jika ada error
    unset($_SESSION['login_error']); // Hapus setelah digunakan
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Username dan password harus diisi.';
    } else {
        // Cek user di database dengan role admin atau superadmin
        $sql = "SELECT id, username, name, email, password, role FROM users WHERE username = ? AND role IN ('admin', 'superadmin')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Verifikasi password
            if (password_verify($password, $user['password'])) {
                // Set session admin
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_name'] = $user['name'];
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['admin_role'] = $user['role'];

                header('Location: superadmin/index.php');
                exit();
            } else {
                $error_message = 'Username atau password salah.';
            }
        } else {
            $error_message = 'Username tidak ditemukan atau tidak memiliki akses admin.';
        }
        $stmt->close();
    }

    // Jika ada error, redirect untuk membersihkan URL parameters
    if (!empty($error_message)) {
        $_SESSION['login_error'] = $error_message;
        header('Location: admin_login.php?error=1');
        exit();
    }
}

// Ambil nama cafe untuk header
$cafe_name = 'Admin Panel';
$result_setting = $conn->query("SELECT setting_value FROM settings WHERE setting_name = 'cafe_name' LIMIT 1");
if ($result_setting && $result_setting->num_rows > 0) {
    $cafe_name = $result_setting->fetch_assoc()['setting_value'] . ' - Admin Panel';
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - <?= htmlspecialchars($cafe_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .login-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-size: 400% 400%;
            animation: gradientShift 8s ease infinite;
        }

        @keyframes gradientShift {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .card-shadow {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .input-focus {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .input-focus:focus {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .btn-hover {
            position: relative;
            overflow: hidden;
        }

        .btn-hover::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-hover:hover::before {
            left: 100%;
        }

        .notification-slide {
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .float-animation {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .card-shadow {
                box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.2);
            }
        }
    </style>
</head>

<body class="login-bg min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-8">
    <!-- Floating Particles Background -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-10 w-2 h-2 bg-white bg-opacity-20 rounded-full float-animation"></div>
        <div class="absolute top-40 right-16 w-3 h-3 bg-white bg-opacity-15 rounded-full float-animation" style="animation-delay: -2s;"></div>
        <div class="absolute bottom-32 left-20 w-1 h-1 bg-white bg-opacity-25 rounded-full float-animation" style="animation-delay: -4s;"></div>
        <div class="absolute bottom-20 right-10 w-2 h-2 bg-white bg-opacity-10 rounded-full float-animation" style="animation-delay: -3s;"></div>
    </div>

    <div class="w-full max-w-md mx-auto relative z-10">
        <!-- Combined Login Card -->
        <div class="bg-white bg-opacity-95 backdrop-blur-sm rounded-2xl card-shadow overflow-hidden">
            <!-- Header Section -->
            <div class="px-6 sm:px-8 pt-6 sm:pt-8 pb-4 sm:pb-6 bg-gradient-to-b from-gray-50 to-white">
                <div class="text-center">
                    <div class="mx-auto w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-blue-600 via-purple-600 to-indigo-700 rounded-full flex items-center justify-center mb-4 shadow-lg float-animation">
                        <i class="fas fa-shield-alt text-white text-2xl sm:text-3xl"></i>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-2 tracking-tight">Admin Panel</h1>
                    <p class="text-gray-600 text-sm sm:text-base font-medium"><?= htmlspecialchars($cafe_name) ?></p>
                    <div class="mt-3 flex justify-center space-x-1">
                        <div class="w-1 h-1 bg-blue-500 rounded-full animate-pulse"></div>
                        <div class="w-1 h-1 bg-purple-500 rounded-full animate-pulse" style="animation-delay: 0.2s;"></div>
                        <div class="w-1 h-1 bg-indigo-500 rounded-full animate-pulse" style="animation-delay: 0.4s;"></div>
                    </div>
                </div>
            </div>

            <!-- Form Section -->
            <div class="px-6 sm:px-8 pb-6 sm:pb-8">
                <!-- Notifikasi Error -->
                <?php if (!empty($error_message)): ?>
                    <div id="errorNotification" class="bg-red-50 border border-red-200 text-red-800 p-4 mb-6 rounded-xl shadow-sm notification-slide" role="alert">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-red-500 text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium"><?= htmlspecialchars($error_message) ?></p>
                            </div>
                            <button type="button" onclick="closeNotification('errorNotification')" class="ml-auto -mx-1.5 -my-1.5 bg-red-50 text-red-500 rounded-lg focus:ring-2 focus:ring-red-400 p-1.5 hover:bg-red-100 inline-flex h-8 w-8">
                                <i class="fas fa-times text-sm"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Notifikasi Success -->
                <?php if (!empty($success_message)): ?>
                    <div id="successNotification" class="bg-green-50 border border-green-200 text-green-800 p-4 mb-6 rounded-xl shadow-sm notification-slide" role="alert">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-500 text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium"><?= htmlspecialchars($success_message) ?></p>
                            </div>
                            <button type="button" onclick="closeNotification('successNotification')" class="ml-auto -mx-1.5 -my-1.5 bg-green-50 text-green-500 rounded-lg focus:ring-2 focus:ring-green-400 p-1.5 hover:bg-green-100 inline-flex h-8 w-8">
                                <i class="fas fa-times text-sm"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6 mt-6">
                    <input type="hidden" name="login" value="1">

                    <div class="space-y-1">
                        <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user mr-2 text-blue-500"></i>Username
                        </label>
                        <div class="relative">
                            <input type="text" id="username" name="username" required
                                class="input-focus w-full px-4 py-3 pl-12 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all duration-300 text-gray-700 placeholder-gray-400"
                                placeholder="Masukkan username admin">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-lock mr-2 text-purple-500"></i>Password
                        </label>
                        <div class="relative">
                            <input type="password" id="password" name="password" required
                                class="input-focus w-full px-4 py-3 pl-12 pr-12 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all duration-300 text-gray-700 placeholder-gray-400"
                                placeholder="Masukkan password">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors duration-200 p-1">
                                <i id="password-icon" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" id="loginBtn"
                        class="btn-hover w-full bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 text-white font-bold py-3.5 rounded-xl hover:from-blue-700 hover:via-purple-700 hover:to-indigo-700 transform hover:scale-[1.02] transition-all duration-300 shadow-lg hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-blue-200">
                        <span class="flex items-center justify-center">
                            <i id="loginIcon" class="fas fa-sign-in-alt mr-2"></i>
                            <span id="loginText">Masuk ke Admin Panel</span>
                        </span>
                    </button>
                </form>

                <!-- Footer Links -->
                <div class="mt-8 pt-6 border-t border-gray-200 text-center">
                    <a href="index.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Kembali ke Halaman Utama
                    </a>
                </div>

                <!-- Access Info -->
                <div class="mt-6 bg-gray-50 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-700 mb-2 text-sm">
                        <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                        Tingkat Akses:
                    </h3>
                    <ul class="text-xs text-gray-600 space-y-1">
                        <li><i class="fas fa-crown mr-2 text-yellow-500"></i><strong>Superadmin:</strong> Akses penuh ke semua fitur</li>
                        <li><i class="fas fa-user-tie mr-2 text-blue-500"></i><strong>Admin:</strong> Manajemen menu, pesanan, laporan</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password toggle functionality
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
            }
        }

        // Close notification manually
        function closeNotification(notificationId) {
            const notification = document.getElementById(notificationId);
            if (notification) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-20px)';
                setTimeout(function() {
                    notification.style.display = 'none';
                }, 300);
            }
        }

        // Form submission with loading state
        function handleFormSubmit() {
            const loginBtn = document.getElementById('loginBtn');
            const loginIcon = document.getElementById('loginIcon');
            const loginText = document.getElementById('loginText');

            // Show loading state
            loginBtn.disabled = true;
            loginBtn.classList.add('opacity-75', 'cursor-not-allowed');
            loginIcon.classList.remove('fa-sign-in-alt');
            loginIcon.classList.add('fa-spinner', 'fa-spin');
            loginText.textContent = 'Memproses...';

            return true; // Allow form submission
        }

        // Input validation with real-time feedback
        function validateInput(input) {
            const value = input.value.trim();
            const parent = input.parentElement;

            // Remove existing validation classes
            input.classList.remove('border-red-300', 'border-green-300');

            if (value.length === 0) {
                input.classList.add('border-red-300');
                return false;
            } else if (input.type === 'text' && value.length < 3) {
                input.classList.add('border-red-300');
                return false;
            } else {
                input.classList.add('border-green-300');
                return true;
            }
        }

        // Initialize dynamic features
        document.addEventListener('DOMContentLoaded', function() {
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const loginForm = document.querySelector('form');

            // Auto focus to username
            usernameInput.focus();

            // Add input validation listeners
            usernameInput.addEventListener('blur', function() {
                validateInput(this);
            });

            passwordInput.addEventListener('blur', function() {
                validateInput(this);
            });

            // Real-time form validation
            [usernameInput, passwordInput].forEach(input => {
                input.addEventListener('input', function() {
                    if (this.value.length > 0) {
                        this.classList.remove('border-red-300');
                        this.classList.add('border-blue-300');
                    }
                });
            });

            // Form submission handler
            loginForm.addEventListener('submit', handleFormSubmit);

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Enter key from anywhere focuses on login button
                if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') {
                    e.preventDefault();
                    if (usernameInput.value && passwordInput.value) {
                        loginForm.submit();
                    } else if (!usernameInput.value) {
                        usernameInput.focus();
                    } else {
                        passwordInput.focus();
                    }
                }
            });

            // Auto-hide notifications with smooth animation
            const errorNotification = document.getElementById('errorNotification');
            if (errorNotification) {
                setTimeout(function() {
                    closeNotification('errorNotification');
                }, 5000);
            }

            const successNotification = document.getElementById('successNotification');
            if (successNotification) {
                setTimeout(function() {
                    closeNotification('successNotification');
                }, 5000);
            }

            // Add smooth focus transition effects
            [usernameInput, passwordInput].forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('transform', 'scale-[1.02]');
                    this.parentElement.style.transition = 'transform 0.2s ease';
                });

                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('scale-[1.02]');
                });
            });
        });

        // Add responsive handling
        window.addEventListener('resize', function() {
            // Adjust form for mobile keyboards
            if (window.innerHeight < 600) {
                document.body.classList.add('keyboard-open');
            } else {
                document.body.classList.remove('keyboard-open');
            }
        });
    </script>
</body>

</html>