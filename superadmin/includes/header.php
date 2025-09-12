<?php
// File: includes/header.php
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
<body class="bg-gray-100 flex">
    <!-- Sidebar -->
    <aside class="w-64 bg-gray-800 text-white min-h-screen p-4 flex flex-col">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-bold text-yellow-400">Nama Toko Kopi</h1>
            <p class="text-sm text-gray-400">Super Admin Panel</p>
        </div>
        <nav class="flex-grow">
            <!-- Langkah 2: Ubah semua link agar menggunakan BASE_URL -->
            <a href="<?= BASE_URL ?>superadmin/dashboard.php" class="sidebar-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-700">
                <i class="fas fa-tachometer-alt w-6 mr-3"></i> Dashboard
            </a>
            <a href="<?= BASE_URL ?>superadmin/kelolamenu.php" class="sidebar-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-700 mt-2">
                <i class="fas fa-book-open w-6 mr-3"></i> Kelola Menu
            </a>
            <a href="<?= BASE_URL ?>superadmin/pelanggan/pelanggan.php" class="sidebar-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-700 mt-2">
                <i class="fas fa-users w-6 mr-3"></i> Pelanggan
            </a>
            <a href="<?= BASE_URL ?>superadmin/penjualan.php" class="sidebar-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-700 mt-2">
                <i class="fas fa-cash-register w-6 mr-3"></i> Penjualan
            </a>
            <a href="<?= BASE_URL ?>superadmin/laporan.php" class="sidebar-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-700 mt-2">
                <i class="fas fa-chart-pie w-6 mr-3"></i> Laporan
            </a>
            <a href="<?= BASE_URL ?>superadmin/ulasan.php" class="sidebar-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-700 mt-2">
                <i class="fas fa-star w-6 mr-3"></i> Ulasan
            </a>
            <a href="<?= BASE_URL ?>superadmin/pengaturan.php" class="sidebar-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-gray-700 mt-2">
                <i class="fas fa-cog w-6 mr-3"></i> Pengaturan
            </a>
        </nav>
        <div class="mt-auto">
             <a href="<?= BASE_URL ?>logout.php" class="sidebar-link flex items-center py-3 px-4 rounded transition duration-200 hover:bg-red-700 mt-2">
                <i class="fas fa-sign-out-alt w-6 mr-3"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col">
        <header class="bg-white shadow-md p-4 flex justify-between items-center">
            <h2 class="text-xl font-semibold text-gray-700" id="page-title">Dashboard</h2>
            <div class="flex items-center">
                <span class="text-gray-600 mr-4">Selamat Datang, Super Admin!</span>
                <img src="https://placehold.co/40x40/5958A1/FFFFFF?text=A" alt="Admin" class="rounded-full">
            </div>
        </header>
        <main class="flex-grow p-6">

