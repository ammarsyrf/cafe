<?php
// File: includes/header.php

// Mulai session hanya jika belum ada yang aktif.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Panggil file konfigurasi. Variabel $pdo yang dibuat di dalam config.php
// akan otomatis tersedia di file ini setelah baris ini dieksekusi.
require_once __DIR__ . '/../../config.php';

// Cek jika variabel $pdo benar-benar ada setelah include
if (!isset($pdo)) {
    die("Koneksi database gagal dimuat. Pastikan config.php sudah benar.");
}

// Variabel default untuk nama toko
$nama_toko = 'Toko Kopi Anda'; // Fallback jika query gagal

try {
    // Pastikan variabel koneksi database ($pdo) tersedia dari config.php

    // --- PERUBAHAN DI SINI ---
    // Query disesuaikan untuk mencari 'cafe_name' sesuai dengan data di database Anda.
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_name = 'cafe_name'");
    $stmt->execute();

    // Fetch hasilnya
    $pengaturan = $stmt->fetch(PDO::FETCH_ASSOC);

    // Jika data ditemukan dan tidak kosong, timpa variabel default dengan nilai yang benar
    if ($pengaturan && !empty($pengaturan['setting_value'])) {
        $nama_toko = $pengaturan['setting_value'];
    }
} catch (PDOException $e) {
    // Jika terjadi error koneksi atau query, biarkan nama toko menggunakan nilai default.
    // Untuk debugging, Anda bisa menampilkan error: error_log("Error fetching settings: " . $e->getMessage());
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

<body class="bg-gray-100 flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside class="w-64 bg-gray-800 text-white p-4 flex flex-col">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-bold text-yellow-400"><?= htmlspecialchars($nama_toko) ?></h1>
            <p class="text-sm text-gray-400">Super Admin Panel</p>
        </div>
        <nav class="flex-grow">
            <a href="<?= BASE_URL ?>superadmin/dashboard.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-gray-700">
                <i class="fas fa-tachometer-alt w-6 mr-3"></i> Dashboard
            </a>
            <a href="<?= BASE_URL ?>superadmin/kelolamenu.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-gray-700 mt-2">
                <i class="fas fa-book-open w-6 mr-3"></i> Kelola Menu
            </a>
            <a href="<?= BASE_URL ?>superadmin/pelanggan/pelanggan.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-gray-700 mt-2">
                <i class="fas fa-users w-6 mr-3"></i> Pelanggan
            </a>
            <a href="<?= BASE_URL ?>superadmin/penjualan/penjualan.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-gray-700 mt-2">
                <i class="fas fa-cash-register w-6 mr-3"></i> Penjualan
            </a>
            <a href="<?= BASE_URL ?>superadmin/laporan.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-gray-700 mt-2">
                <i class="fas fa-chart-pie w-6 mr-3"></i> Laporan
            </a>
            <a href="<?= BASE_URL ?>superadmin/ulasan.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-gray-700 mt-2">
                <i class="fas fa-star w-6 mr-3"></i> Ulasan
            </a>
            <!-- PENAMBAHAN FITUR BARCODE GENERATOR -->
            <a href="<?= BASE_URL ?>superadmin/barcode_generator.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-gray-700 mt-2">
                <i class="fas fa-qrcode w-6 mr-3"></i> Barcode Generator
            </a>
            <!-- AKHIR PENAMBAHAN -->
            <a href="<?= BASE_URL ?>superadmin/pengaturan.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-gray-700 mt-2">
                <i class="fas fa-cog w-6 mr-3"></i> Pengaturan
            </a>
        </nav>
        <div class="mt-auto">
            <a href="<?= BASE_URL ?>logout.php" class="sidebar-link flex items-center py-3 px-4 rounded-lg transition duration-200 hover:bg-red-700 mt-2">
                <i class="fas fa-sign-out-alt w-6 mr-3"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 overflow-y-auto">
        <header class="bg-white shadow-md p-4 flex justify-between items-center sticky top-0 z-10">
            <h2 class="text-xl font-semibold text-gray-700" id="page-title">Dashboard</h2>
            <div class="flex items-center">
                <span class="text-gray-600 mr-4">Selamat Datang, Super Admin!</span>
                <img src="https://placehold.co/40x40/5958A1/FFFFFF?text=A" alt="Admin" class="rounded-full">
            </div>
        </header>
        <main class="p-6">
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const currentPath = window.location.pathname;
                    const sidebarLinks = document.querySelectorAll('aside .sidebar-link');
                    const pageTitle = document.getElementById('page-title');

                    let currentSection = 'dashboard';
                    const pathParts = currentPath.split('/');
                    const superadminIndex = pathParts.indexOf('superadmin');

                    if (superadminIndex > -1 && pathParts.length > superadminIndex + 1) {
                        let potentialSection = pathParts[superadminIndex + 1];
                        if (potentialSection.endsWith('.php')) {
                            currentSection = potentialSection.replace('.php', '');
                        } else {
                            currentSection = potentialSection;
                        }
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