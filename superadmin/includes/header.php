<?php
// File: includes/header.php

// REVISI: Panggil session_start() di paling atas, sebelum output apa pun.
// Ini akan memperbaiki error "headers already sent".
session_start();

// Langkah 1: Panggil file konfigurasi untuk mendapatkan BASE_URL.
// Path ini ('../../config.php') berarti "naik dua level direktori" untuk menemukan config.php
require_once __DIR__ . '/../../config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Nama Toko Kopi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .sidebar-link.active {
            background-color: #5958A1;
            color: #FFFFFF;
            font-weight: 600;
        }
        .sidebar-link.active i { color: #FFFFFF; }
    </style>
</head>
<!-- REVISI: Menambahkan class 'h-screen' dan 'overflow-hidden' untuk mencegah body scroll -->
<body class="bg-gray-100 flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <!-- REVISI: Menghapus 'min-h-screen' karena tinggi diatur oleh body -->
    <aside class="w-64 bg-gray-800 text-white p-4 flex flex-col">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-bold text-yellow-400">Nama Toko Kopi</h1>
            <p class="text-sm text-gray-400">Super Admin Panel</p>
        </div>
        <nav class="flex-grow">
            <!-- REVISI: Mengubah 'rounded' menjadi 'rounded-lg' untuk corner radius ~10px -->
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
    <!-- REVISI: Menghapus 'flex-col' dan menambahkan 'overflow-y-auto' agar area ini bisa di-scroll -->
    <div class="flex-1 overflow-y-auto">
        <!-- REVISI: Menambahkan 'sticky top-0 z-10' agar header konten menempel di atas saat di-scroll -->
        <header class="bg-white shadow-md p-4 flex justify-between items-center sticky top-0 z-10">
            <h2 class="text-xl font-semibold text-gray-700" id="page-title">Dashboard</h2>
            <div class="flex items-center">
                <span class="text-gray-600 mr-4">Selamat Datang, Super Admin!</span>
                <img src="https://placehold.co/40x40/5958A1/FFFFFF?text=A" alt="Admin" class="rounded-full">
            </div>
        </header>
        <!-- REVISI: Menghapus 'flex-grow' -->
        <main class="p-6">
<!-- REVISI: JavaScript untuk menu aktif ditambahkan di sini -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const currentPath = window.location.pathname;
    const sidebarLinks = document.querySelectorAll('aside .sidebar-link');
    const pageTitle = document.getElementById('page-title');
    
    // Temukan seksi utama saat ini (contoh: 'dashboard', 'pelanggan', 'penjualan')
    let currentSection = 'dashboard'; // Default
    const pathParts = currentPath.split('/');
    const superadminIndex = pathParts.indexOf('superadmin');

    if (superadminIndex > -1 && pathParts.length > superadminIndex + 1) {
        let potentialSection = pathParts[superadminIndex + 1];
        if (potentialSection.endsWith('.php')) {
            currentSection = potentialSection.replace('.php', '');
        } else {
            currentSection = potentialSection; // Ini adalah nama folder
        }
    }

    // Loop melalui link dan aktifkan yang cocok
    let linkActivated = false;
    sidebarLinks.forEach(link => {
        const linkHref = link.getAttribute('href');
        if (linkHref.includes(currentSection)) {
            link.classList.add('active');
            if (pageTitle) {
                pageTitle.textContent = link.textContent.trim();
            }
            linkActivated = true;
        }
    });

    // Fallback jika tidak ada yang cocok, aktifkan dashboard
    if (!linkActivated) {
        const dashboardLink = document.querySelector('a[href*="dashboard.php"]');
        if (dashboardLink) {
            dashboardLink.classList.add('active');
        }
    }
});
</script>

