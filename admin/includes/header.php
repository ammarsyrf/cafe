<?php
// File: includes/header.php

// Authentication check sudah dilakukan di middleware
// Hanya perlu validasi tambahan jika diperlukan

// Panggil file konfigurasi.
require_once __DIR__ . '/../../app/config/config.php';

// Cek jika variabel $pdo benar-benar ada setelah include
if (!isset($pdo)) {
    die("Koneksi database gagal dimuat. Pastikan config.php sudah benar.");
}

// Variabel default untuk nama toko
$nama_toko = 'Toko Kopi Anda'; // Fallback jika query gagal

try {
    // Query disesuaikan untuk mencari 'cafe_name'
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_name = 'cafe_name'");
    $stmt->execute();
    $pengaturan = $stmt->fetch(PDO::FETCH_ASSOC);

    // Jika data ditemukan, timpa variabel default
    if ($pengaturan && !empty($pengaturan['setting_value'])) {
        $nama_toko = $pengaturan['setting_value'];
    }
} catch (PDOException $e) {
    // Biarkan nama toko menggunakan nilai default jika ada error.
    // error_log("Error fetching settings: " . $e->getMessage());
}

// Ambil nama user dari session atau database
$user_name = 'Admin'; // Default fallback

try {
    // Periksa apakah session user ada dan memiliki nama
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        if (!empty($_SESSION['user']['name'])) {
            // Gunakan nama dari session jika ada
            $user_name = $_SESSION['user']['name'];
        } elseif (!empty($_SESSION['user']['id'])) {
            // Jika nama tidak ada di session, ambil dari database berdasarkan ID
            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user']['id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && !empty($user['name'])) {
                $user_name = $user['name'];
            }
        }
    }
} catch (PDOException $e) {
    // Log error untuk debugging
    error_log("Error fetching user name: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?= htmlspecialchars($nama_toko) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        .sidebar-link.active {
            background-color: #5958A1;
            color: #FFFFFF;
            font-weight: 600;
        }

        .sidebar-link.active i {
            color: #FFFFFF;
        }
    </style>
</head>

<body class="bg-gray-100 h-screen overflow-hidden relative">
    <!-- [DIUBAH] Wrapper untuk layout dinamis -->
    <div class="flex h-full">
        <!-- Sidebar -->
        <!-- [DIUBAH] Class sidebar diubah untuk transisi dan posisi mobile -->
        <aside id="sidebar" class="w-64 bg-gray-800 text-white p-4 flex-col fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out z-30 flex">
            <div class="mb-8 text-center">
                <h1 class="text-2xl font-bold text-yellow-400"><?= htmlspecialchars($nama_toko) ?></h1>
                <p class="text-sm text-gray-400">Super Admin Panel</p>
            </div>
            <nav class="flex-grow">
                <a href="<?= BASE_URL ?>admin/index.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-gray-700">
                    <i class="fas fa-tachometer-alt w-6 mr-3"></i> Dashboard
                </a>
                <a href="<?= BASE_URL ?>admin/menu/kelolamenu.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-gray-700 mt-2">
                    <i class="fas fa-book-open w-6 mr-3"></i> Kelola Menu
                </a>
                <a href="<?= BASE_URL ?>admin/banner/kelola_banner.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-gray-700 mt-2">
                    <i class="fas fa-images w-6 mr-3"></i> Kelola Banner
                </a>
                <a href="<?= BASE_URL ?>admin/pelanggan/pelanggan.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-gray-700 mt-2">
                    <i class="fas fa-users w-6 mr-3"></i> Pelanggan
                </a>
                <a href="<?= BASE_URL ?>admin/penjualan/penjualan.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-gray-700 mt-2">
                    <i class="fas fa-cash-register w-6 mr-3"></i> Penjualan
                </a>
                <a href="<?= BASE_URL ?>admin/laporan.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-gray-700 mt-2">
                    <i class="fas fa-chart-pie w-6 mr-3"></i> Laporan
                </a>
                <a href="<?= BASE_URL ?>admin/ulasan.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-gray-700 mt-2">
                    <i class="fas fa-star w-6 mr-3"></i> Ulasan
                </a>
                <a href="<?= BASE_URL ?>admin/barcode_generator.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-gray-700 mt-2">
                    <i class="fas fa-qrcode w-6 mr-3"></i> Barcode Generator
                </a>
                <a href="<?= BASE_URL ?>admin/pengaturan.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-gray-700 mt-2">
                    <i class="fas fa-cog w-6 mr-3"></i> Pengaturan
                </a>
            </nav>
            <div class="mt-auto">
                <a href="<?= BASE_URL ?>auth/admin_logout.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-red-700 mt-2">
                    <i class="fas fa-sign-out-alt w-6 mr-3"></i> Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <!-- [DIUBAH] Wrapper konten utama diubah untuk flexbox -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="bg-white shadow-md p-4 flex justify-between items-center sticky top-0 z-10">
                <!-- [DITAMBAHKAN] Tombol Hamburger untuk mobile -->
                <div class="flex items-center">
                    <button id="sidebar-toggle" class="text-gray-600 focus:outline-none md:hidden mr-4">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                    <h2 class="text-xl font-semibold text-gray-700" id="page-title">Dashboard</h2>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-600 mr-4 hidden sm:block">
                        Selamat Datang, <strong><?= htmlspecialchars($user_name) ?></strong>!
                        <span class="text-xs text-gray-500 ml-1">(<?= ucfirst($_SESSION['admin_role'] ?? '') ?>)</span>
                    </span>
                    <img src="https://placehold.co/40x40/5958A1/FFFFFF?text=A" alt="Admin" class="rounded-full">
                </div>
            </header>
            <main class="p-6 flex-1 overflow-y-auto">
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        const sidebar = document.getElementById('sidebar');
                        const sidebarToggle = document.getElementById('sidebar-toggle');
                        const currentPath = window.location.pathname;
                        const sidebarLinks = document.querySelectorAll('aside .sidebar-link');
                        const pageTitle = document.getElementById('page-title');

                        // [DITAMBAHKAN] Logika untuk membuka/menutup sidebar
                        if (sidebarToggle) {
                            sidebarToggle.addEventListener('click', () => {
                                sidebar.classList.toggle('-translate-x-full');
                            });
                        }

                        // [DITAMBAHKAN] Klik di luar sidebar untuk menutupnya di mobile
                        document.addEventListener('click', (event) => {
                            const isClickInsideSidebar = sidebar.contains(event.target);
                            const isClickOnToggle = sidebarToggle.contains(event.target);

                            if (!isClickInsideSidebar && !isClickOnToggle && !sidebar.classList.contains('-translate-x-full')) {
                                if (window.innerWidth < 768) { // md breakpoint
                                    sidebar.classList.add('-translate-x-full');
                                }
                            }
                        });


                        // --- Logika untuk menandai link aktif (tidak diubah) ---
                        let currentSection = 'dashboard';
                        const pathParts = currentPath.split('/');
                        const superadminIndex = pathParts.indexOf('superadmin');

                        if (superadminIndex > -1 && pathParts.length > superadminIndex + 1) {
                            let potentialSection = pathParts[superadminIndex + 1];
                            currentSection = potentialSection.endsWith('.php') ? potentialSection.replace('.php', '') : potentialSection;
                        }

                        let linkActivated = false;
                        sidebarLinks.forEach(link => {
                            const linkHref = link.getAttribute('href');
                            if (link.closest('.mt-auto')) return; // Skip logout link

                            if (linkHref.includes(currentSection)) {
                                link.classList.add('active');
                                if (pageTitle) {
                                    pageTitle.textContent = link.textContent.trim();
                                }
                                linkActivated = true;
                            }
                        });

                        if (!linkActivated) {
                            const dashboardLink = document.querySelector('a[href*="dashboard.php"]');
                            if (dashboardLink) {
                                dashboardLink.classList.add('active');
                                if (pageTitle) {
                                    pageTitle.textContent = "Dashboard";
                                }
                            }
                        }
                    });
                </script>