<?php
// Bagian Backend (PHP)

// Selalu mulai sesi di bagian paling atas
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Periksa apakah permintaan adalah POST (dikirim dari form)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Set header untuk memberitahu browser bahwa responsnya adalah JSON
    header('Content-Type: application/json');

    // Inisialisasi respons default
    $response = ['success' => false, 'message' => 'Terjadi kesalahan.'];

    try {
        // Sertakan koneksi database
        if (!@include_once '../app/config/db_connect.php') {
            throw new Exception("File koneksi database (db_connect.php) tidak ditemukan.");
        }

        // Ambil data dari form dan sanitasi
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validasi input
        if (empty($username) || empty($password)) {
            $response['message'] = 'Username dan password harus diisi.';
            echo json_encode($response);
            exit();
        }

        // Periksa apakah koneksi berhasil dibuat di db_connect.php
        if (!isset($conn) || $conn->connect_error) {
            throw new Exception("Koneksi ke database gagal. Periksa kredensial di db_connect.php.");
        }

        // Cari user berdasarkan username dan role
        // Pertama cek apakah kolom is_active ada
        $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
        if ($check_column->num_rows > 0) {
            $sql = "SELECT id, name, password, role, is_active FROM users WHERE username = ? AND role IN ('cashier', 'admin') LIMIT 1";
        } else {
            $sql = "SELECT id, name, password, role FROM users WHERE username = ? AND role IN ('cashier', 'admin') LIMIT 1";
        }

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Gagal mempersiapkan statement SQL: " . $conn->error);
        }

        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            // User ditemukan, sekarang periksa role dan password
            $kasir = $result->fetch_assoc();

            // Check if account is active (jika kolom ada)
            if (isset($kasir['is_active']) && !$kasir['is_active']) {
                $response['message'] = 'Akun Anda tidak aktif. Hubungi administrator.';
            }
            // Periksa passwordnya
            else if (password_verify($password, $kasir['password'])) {
                // Regenerate session ID untuk keamanan
                session_regenerate_id(true);

                // Password benar, login berhasil
                $_SESSION['kasir'] = [
                    'id' => $kasir['id'],
                    'name' => htmlspecialchars($kasir['name'], ENT_QUOTES, 'UTF-8'),
                    'role' => $kasir['role'],
                    'login_time' => time()
                ];

                $response['success'] = true;
                $response['message'] = 'Login kasir berhasil!';
            } else {
                $response['message'] = 'Username atau password salah.';
            }
        } else {
            $response['message'] = 'Username atau password salah.';
        }

        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        // Tangkap semua jenis error dan kirim sebagai pesan
        $response['message'] = $e->getMessage();
    }

    // Kirim respons dalam format JSON dan hentikan script
    echo json_encode($response);
    exit();
}

// Jika permintaan BUKAN POST, maka tampilkan HTML di bawah ini.
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale-1.0">
    <title>Login Kasir</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="w-full max-w-md bg-white rounded-lg shadow-md p-8">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-2">Login Kasir</h2>
        <p class="text-center text-gray-600 mb-6">Selamat datang kembali. Silakan masuk.</p>

        <!-- Form Login -->
        <form id="loginFormKasir">
            <div class="mb-4">
                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                <input type="text" id="username" name="username" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" placeholder="Masukkan username Anda" required>
            </div>
            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <input type="password" id="password" name="password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" placeholder="********" required>
            </div>

            <!-- Tempat untuk menampilkan pesan error/sukses -->
            <div id="message" class="mb-4 text-sm text-center"></div>

            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                Masuk
            </button>
        </form>
    </div>

    <script>
        const loginForm = document.getElementById('loginFormKasir');
        const messageDiv = document.getElementById('message');

        loginForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            const submitButton = loginForm.querySelector('button[type="submit"]');
            submitButton.textContent = 'Memproses...';
            submitButton.disabled = true;

            const formData = new FormData(loginForm);

            try {
                // Kirim data ke halaman ini sendiri.
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    messageDiv.textContent = 'Login berhasil! Mengalihkan...';
                    messageDiv.className = 'mb-4 text-sm text-center text-green-600';

                    setTimeout(() => {
                        // --- PERBAIKAN DI SINI ---
                        // Mengarahkan ke file di folder yang sama
                        window.location.href = 'index.php';
                    }, 1000);

                } else {
                    messageDiv.textContent = result.message;
                    messageDiv.className = 'mb-4 text-sm text-center text-red-600';
                    submitButton.textContent = 'Masuk';
                    submitButton.disabled = false;
                }
            } catch (error) {
                console.error('Error:', error);
                messageDiv.textContent = 'Terjadi kesalahan. Silakan coba lagi.';
                messageDiv.className = 'mb-4 text-sm text-center text-red-600';
                submitButton.textContent = 'Masuk';
                submitButton.disabled = false;
            }
        });
    </script>

</body>

</html>