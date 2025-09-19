-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 19, 2025 at 05:18 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_cafe`
--

-- --------------------------------------------------------

--
-- Table structure for table `addon_groups`
--

CREATE TABLE `addon_groups` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `addon_groups`
--

INSERT INTO `addon_groups` (`id`, `name`, `created_at`) VALUES
(1, 'Penyajian', '2025-09-19 12:30:44'),
(2, 'Tingkat Kemanisan', '2025-09-19 12:30:44');

-- --------------------------------------------------------

--
-- Table structure for table `addon_options`
--

CREATE TABLE `addon_options` (
  `id` int(11) NOT NULL,
  `addon_group_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Harga tambahan untuk opsi ini',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `addon_options`
--

INSERT INTO `addon_options` (`id`, `addon_group_id`, `name`, `price`, `created_at`) VALUES
(1, 1, 'Panas', 0.00, '2025-09-19 12:30:44'),
(2, 1, 'Dingin', 1000.00, '2025-09-19 12:30:44'),
(3, 2, 'Tanpa Gula', 0.00, '2025-09-19 12:30:44'),
(4, 2, 'Less Sugar', 0.00, '2025-09-19 12:30:44'),
(5, 2, 'Manis', 0.00, '2025-09-19 12:30:44');

-- --------------------------------------------------------

--
-- Table structure for table `banners`
--

CREATE TABLE `banners` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `subtitle` text DEFAULT NULL,
  `image_url` varchar(255) NOT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `order_number` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `banners`
--

INSERT INTO `banners` (`id`, `title`, `subtitle`, `image_url`, `link_url`, `is_active`, `order_number`) VALUES
(7, 'nasi goreng', 'eennaaakkk', 'uploads/banners/banner_68cb9e50c22320.10847140.png', '#category-makanan', 1, 1),
(8, 'Leci Tea', 'enaaakk', 'uploads/banners/banner_68cd43ab63e960.84443641.jpg', '#category-minuman', 1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'Nama lengkap member',
  `email` varchar(255) NOT NULL COMMENT 'Alamat email untuk login, harus unik',
  `password` varchar(255) NOT NULL COMMENT 'Password yang sudah di-hash',
  `phone_number` varchar(20) DEFAULT NULL COMMENT 'Nomor telepon member (opsional)',
  `points` int(11) DEFAULT 0 COMMENT 'Poin loyalitas member (opsional, untuk pengembangan selanjutnya)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu pendaftaran member',
  `profile_image_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabel untuk data member pelanggan';

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `name`, `email`, `password`, `phone_number`, `points`, `created_at`, `profile_image_url`) VALUES
(1, 'ammar', 'ammarsyrf78@gmail.com', '$2y$10$CnuSBx1Fj7HeVnFjc6yFTet/eFFkpSEf7IKg.qt4wIzaoQg640C1W', '087860', 0, '2025-09-12 08:10:36', 'http://localhost/cafe/uploads/profiles/member_1_1758174585.jpg'),
(2, 'budi', 'budi@gmail.com', '$2y$10$Lumj1b92cPbnFnBTcFKcLu7N5Z7FkGJEvsgLXe/3g8t.aIZyOyxBu', '12465', 0, '2025-09-13 04:44:10', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `menu`
--

CREATE TABLE `menu` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(100) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `discount_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu`
--

INSERT INTO `menu` (`id`, `name`, `description`, `price`, `category`, `stock`, `image_url`, `is_available`, `discount_price`) VALUES
(1, 'Kopi Susu', 'Kopi robusta dengan susu dan gula aren', 25000.00, 'minuman', 60, 'https://placehold.co/150x150/d1d5db/000000?text=Kopi+Susu', 1, 0.00),
(3, 'French Fries', 'Kentang goreng renyah dengan saus', 20000.00, 'snack', 50, 'https://placehold.co/150x150/d1d5db/000000?text=French+Fries', 1, 0.00),
(4, 'Le Mineral', 'Air mineral 600ml', 5000.00, 'other', 40, 'uploads/menu/menu_68ccf5f97602e0.53888126.jpg', 1, 0.00),
(6, 'Espresso', '', 15000.00, 'minuman', 40, 'uploads/menu/menu_68cb999225a700.34605172.jpg', 1, 0.00),
(7, 'Kapal Api Special Mix', 'Kopi Kapal Api', 5000.00, 'minuman', 40, 'uploads/menu/menu_68c5640044fff1.77351467.png', 1, 0.00),
(17, 'Aqua', 'dingin enak', 5000.00, 'other', 40, 'uploads/menu/menu_68cb8c704aa975.99812489.jpg', 1, 0.00),
(18, 'Nasi Goreng Spesial', 'Telur,ayam,udang', 25000.00, 'makanan', 50, 'uploads/menu/menu_68cb997de3aa17.61699697.jpg', 1, 0.00),
(19, 'Kopi ABC Susu', 'ueeenaaakkk', 5000.00, 'minuman', 40, 'uploads/menu/menu_68cd5bde6d11a3.47150167.jpg', 1, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `menu_addons`
--

CREATE TABLE `menu_addons` (
  `menu_id` int(11) NOT NULL,
  `addon_group_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_addons`
--

INSERT INTO `menu_addons` (`menu_id`, `addon_group_id`) VALUES
(1, 1),
(19, 1);

-- --------------------------------------------------------

--
-- Table structure for table `menu_categories`
--

CREATE TABLE `menu_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_categories`
--

INSERT INTO `menu_categories` (`id`, `name`, `created_at`) VALUES
(1, 'makanan', '2025-09-16 05:00:27'),
(2, 'minuman', '2025-09-16 05:00:27'),
(3, 'snack', '2025-09-16 05:00:27'),
(4, 'other', '2025-09-16 05:00:27');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `table_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `cashier_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL COMMENT 'Nama pelanggan non-member',
  `member_id` int(11) DEFAULT NULL,
  `order_type` enum('dine-in','take-away') NOT NULL,
  `status` enum('cart','pending_payment','paid','completed','cancelled') NOT NULL DEFAULT 'cart',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Jumlah diskon yang diberikan',
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','transfer','virtual_account','QRIS','manual_cash') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `receipt_printed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `table_id`, `user_id`, `cashier_id`, `customer_name`, `member_id`, `order_type`, `status`, `subtotal`, `discount_amount`, `tax`, `total_amount`, `payment_method`, `created_at`, `updated_at`, `receipt_printed_at`) VALUES
(45, 2, 2, 2, 'tyo', NULL, 'dine-in', 'completed', 25000.00, 0.00, 2750.00, 27750.00, '', '2025-09-12 09:25:06', '2025-09-16 01:56:38', '2025-09-16 08:56:38'),
(46, 1, NULL, 2, 'ammar', NULL, 'dine-in', 'completed', 35000.00, 0.00, 3850.00, 38850.00, 'cash', '2025-09-12 09:28:40', '2025-09-16 01:56:36', '2025-09-16 08:56:36'),
(47, 1, 2, 2, 'budi', NULL, 'dine-in', 'completed', 35000.00, 0.00, 3850.00, 38850.00, 'manual_cash', '2025-09-12 09:29:12', '2025-09-12 10:48:46', '2025-09-12 17:48:46'),
(48, 1, NULL, 2, 'tyo', NULL, 'dine-in', 'completed', 80000.00, 0.00, 8800.00, 88800.00, 'QRIS', '2025-09-12 10:46:49', '2025-09-12 10:47:12', '2025-09-12 17:47:12'),
(49, 1, NULL, 2, 'karyo', NULL, 'dine-in', 'completed', 35000.00, 0.00, 3850.00, 38850.00, 'QRIS', '2025-09-13 12:17:21', '2025-09-16 01:56:34', '2025-09-16 08:56:34'),
(50, 1, NULL, 2, 'tyo', NULL, 'dine-in', 'completed', 20000.00, 0.00, 2200.00, 22200.00, 'QRIS', '2025-09-14 04:12:05', '2025-09-16 01:56:26', '2025-09-16 08:56:26'),
(52, 1, NULL, 2, 'eza', NULL, 'dine-in', 'completed', 5000.00, 0.00, 550.00, 5550.00, 'cash', '2025-09-14 05:06:16', '2025-09-15 12:40:21', '2025-09-15 19:40:21'),
(53, 1, NULL, 2, 'azka', NULL, 'dine-in', 'completed', 85000.00, 0.00, 9350.00, 94350.00, 'QRIS', '2025-09-14 05:10:43', '2025-09-14 05:20:21', '2025-09-14 12:20:21'),
(54, 1, 1, 2, NULL, 1, 'dine-in', 'completed', 115000.00, 11500.00, 11385.00, 114885.00, 'QRIS', '2025-09-14 12:06:33', '2025-09-14 12:13:48', '2025-09-14 19:06:47'),
(56, 1, 1, 2, NULL, NULL, 'dine-in', 'completed', 35000.00, 0.00, 3850.00, 38850.00, 'transfer', '2025-09-15 09:24:55', '2025-09-15 09:26:05', '2025-09-15 16:26:05'),
(57, 1, 1, 2, 'jojo', NULL, 'dine-in', 'completed', 15000.00, 0.00, 1650.00, 16650.00, 'QRIS', '2025-09-15 09:25:10', '2025-09-16 02:28:08', '2025-09-15 16:25:56'),
(58, 1, 1, 2, 'jojo', NULL, 'dine-in', 'pending_payment', 35000.00, 0.00, 3850.00, 38850.00, 'cash', '2025-09-15 09:25:22', '2025-09-16 02:28:03', NULL),
(59, 1, 1, 2, 'jojo', NULL, 'dine-in', 'completed', 50000.00, 2500.00, 5225.00, 52725.00, 'QRIS', '2025-09-15 12:23:46', '2025-09-16 02:28:00', '2025-09-15 19:48:16'),
(60, 1, 1, 2, 'jojo', NULL, 'dine-in', 'completed', 50000.00, 2500.00, 5225.00, 52725.00, 'QRIS', '2025-09-16 02:08:36', '2025-09-16 02:27:57', '2025-09-16 09:09:00'),
(61, 1, NULL, 2, 'jojo', NULL, 'dine-in', 'completed', 55000.00, 0.00, 6050.00, 61050.00, 'transfer', '2025-09-16 02:22:45', '2025-09-16 02:37:24', '2025-09-16 09:37:24'),
(62, 1, NULL, 2, 'eja', NULL, 'dine-in', 'completed', 45000.00, 0.00, 4950.00, 49950.00, 'virtual_account', '2025-09-16 02:30:06', '2025-09-16 02:37:19', '2025-09-16 09:37:19'),
(63, 1, 1, 2, NULL, NULL, 'dine-in', 'completed', 15000.00, 0.00, 1650.00, 16650.00, 'QRIS', '2025-09-16 02:30:33', '2025-09-16 02:37:06', '2025-09-16 09:37:06'),
(64, 1, 1, 2, NULL, NULL, 'dine-in', 'completed', 160000.00, 16000.00, 15840.00, 159840.00, 'QRIS', '2025-09-16 04:08:08', '2025-09-16 04:13:00', '2025-09-16 11:13:00'),
(66, 2, 2, 2, 'danang', NULL, 'dine-in', 'completed', 50000.00, 0.00, 5500.00, 55500.00, 'manual_cash', '2025-09-16 04:16:16', '2025-09-16 04:16:41', '2025-09-16 11:16:41'),
(70, 2, NULL, 2, 'farhan', NULL, 'dine-in', 'completed', 105000.00, 0.00, 11550.00, 116550.00, 'manual_cash', '2025-09-16 04:20:59', '2025-09-16 04:48:21', '2025-09-16 11:48:21'),
(72, 1, 1, 2, NULL, NULL, 'dine-in', 'completed', 80000.00, 4000.00, 8360.00, 84360.00, 'QRIS', '2025-09-16 04:36:51', '2025-09-16 04:36:57', '2025-09-16 11:36:57'),
(73, NULL, NULL, 2, 'jontor', NULL, 'take-away', 'completed', 80000.00, 0.00, 8800.00, 88800.00, 'manual_cash', '2025-09-16 04:40:07', '2025-09-16 04:40:14', '2025-09-16 11:40:14');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_per_item` decimal(10,2) NOT NULL COMMENT 'Harga dasar menu saat dipesan',
  `selected_addons` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Menyimpan add-on yg dipilih, cth: [{"group": "Penyajian", "option": "Dingin", "price": 1000}]' CHECK (json_valid(`selected_addons`)),
  `total_price` decimal(10,2) NOT NULL COMMENT '(quantity * price_per_item) + harga semua addon'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `menu_id`, `quantity`, `price_per_item`, `selected_addons`, `total_price`) VALUES
(96, 53, 4, 1, 5000.00, NULL, 0.00),
(98, 54, 6, 1, 15000.00, NULL, 0.00),
(99, 54, 7, 1, 5000.00, NULL, 0.00),
(100, 54, 1, 1, 25000.00, NULL, 0.00),
(101, 54, 3, 1, 20000.00, NULL, 0.00),
(102, 54, 4, 3, 5000.00, NULL, 0.00),
(108, 57, 6, 1, 15000.00, NULL, 0.00),
(111, 59, 6, 1, 15000.00, NULL, 0.00),
(113, 60, 6, 1, 15000.00, NULL, 0.00),
(115, 61, 6, 1, 15000.00, NULL, 0.00),
(116, 61, 7, 1, 5000.00, NULL, 0.00),
(117, 62, 6, 1, 15000.00, NULL, 0.00),
(118, 62, 7, 1, 5000.00, NULL, 0.00),
(119, 62, 1, 1, 25000.00, NULL, 0.00),
(120, 63, 4, 3, 5000.00, NULL, 0.00),
(122, 64, 6, 1, 15000.00, NULL, 0.00),
(123, 64, 7, 1, 5000.00, NULL, 0.00),
(124, 64, 1, 1, 25000.00, NULL, 0.00),
(125, 64, 3, 4, 20000.00, NULL, 0.00),
(130, 66, 1, 1, 25000.00, NULL, 0.00),
(131, 66, 3, 1, 20000.00, NULL, 0.00),
(132, 66, 7, 1, 5000.00, NULL, 0.00),
(147, 70, 1, 1, 25000.00, NULL, 0.00),
(149, 70, 3, 1, 20000.00, NULL, 0.00),
(150, 70, 4, 1, 5000.00, NULL, 0.00),
(151, 70, 6, 1, 15000.00, NULL, 0.00),
(152, 70, 7, 1, 5000.00, NULL, 0.00),
(158, 72, 3, 4, 20000.00, NULL, 0.00),
(159, 73, 1, 1, 25000.00, NULL, 0.00),
(161, 73, 6, 1, 15000.00, NULL, 0.00),
(162, 73, 7, 1, 5000.00, NULL, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `reply` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_name`, `setting_value`) VALUES
(1, 'cafe_name', 'cafe cafe'),
(2, 'cafe_phone', '08780'),
(3, 'cafe_address', 'jl setia 1'),
(4, 'hour_open', '09:20'),
(5, 'hour_close', '22:00'),
(6, 'social_instagram', ''),
(7, 'social_facebook', ''),
(8, 'social_twitter', ''),
(9, 'days_open', '[]');

-- --------------------------------------------------------

--
-- Table structure for table `tables`
--

CREATE TABLE `tables` (
  `id` int(11) NOT NULL,
  `table_number` int(11) NOT NULL,
  `is_occupied` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tables`
--

INSERT INTO `tables` (`id`, `table_number`, `is_occupied`) VALUES
(1, 1, 0),
(2, 2, 0),
(3, 3, 0),
(4, 4, 0),
(5, 5, 0),
(6, 6, 0),
(7, 7, 0),
(8, 8, 0),
(9, 9, 0),
(10, 10, 0),
(11, 11, 0),
(12, 12, 0),
(13, 13, 0),
(14, 14, 0),
(15, 15, 0),
(16, 16, 0),
(17, 17, 0),
(18, 18, 0),
(19, 19, 0),
(20, 20, 0);

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `total_paid` decimal(10,2) NOT NULL,
  `amount_given` decimal(10,2) DEFAULT NULL,
  `change_amount` decimal(10,2) DEFAULT NULL,
  `payment_method` enum('cash','transfer','virtual_account','QRIS','manual_cash') NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `order_id`, `user_id`, `total_paid`, `amount_given`, `change_amount`, `payment_method`, `transaction_date`) VALUES
(24, 45, NULL, 27750.00, 30000.00, 2250.00, 'manual_cash', '2025-09-12 09:25:06'),
(25, 46, NULL, 38850.00, 40000.00, 1150.00, 'cash', '2025-09-12 09:28:55'),
(26, 47, NULL, 38850.00, 40000.00, 1150.00, 'manual_cash', '2025-09-12 09:29:12'),
(28, 52, NULL, 5550.00, 10000.00, 4450.00, 'cash', '2025-09-14 05:06:25'),
(31, 66, NULL, 55500.00, 60000.00, 4500.00, 'manual_cash', '2025-09-16 04:16:16'),
(35, 70, NULL, 116550.00, 120000.00, 3450.00, 'manual_cash', '2025-09-16 04:20:59'),
(37, 73, NULL, 88800.00, 100000.00, 11200.00, 'manual_cash', '2025-09-16 04:40:07');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` enum('admin','cashier','superadmin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `name`, `role`, `created_at`) VALUES
(1, 'superadmin', 'superadmin@gmail.com', '$2y$10$A4tADK23lBTbyEZwkTbDlOgmTopF1IgNRoYpCMvqAFMiZe6lJnZh6', 'Super Admin', 'superadmin', '2025-09-10 07:37:48'),
(2, 'kasir1', 'kasir1@gmail.com', '$2y$10$4coRCBRreShwUQnUJnc8uudXSKucNPexcPH6lscUG2SkYzsicpF22', 'karyo', 'cashier', '2025-09-10 07:37:48');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addon_groups`
--
ALTER TABLE `addon_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `addon_options`
--
ALTER TABLE `addon_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `addon_group_id` (`addon_group_id`);

--
-- Indexes for table `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_email` (`email`);

--
-- Indexes for table `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `menu_addons`
--
ALTER TABLE `menu_addons`
  ADD PRIMARY KEY (`menu_id`,`addon_group_id`),
  ADD KEY `addon_group_id` (`addon_group_id`);

--
-- Indexes for table `menu_categories`
--
ALTER TABLE `menu_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `table_id` (`table_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `menu_id` (`menu_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_id` (`member_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `tables`
--
ALTER TABLE `tables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `table_number` (`table_number`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addon_groups`
--
ALTER TABLE `addon_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `addon_options`
--
ALTER TABLE `addon_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `menu`
--
ALTER TABLE `menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `menu_categories`
--
ALTER TABLE `menu_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=163;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `tables`
--
ALTER TABLE `tables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=183;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addon_options`
--
ALTER TABLE `addon_options`
  ADD CONSTRAINT `addon_options_ibfk_1` FOREIGN KEY (`addon_group_id`) REFERENCES `addon_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `menu_addons`
--
ALTER TABLE `menu_addons`
  ADD CONSTRAINT `menu_addons_ibfk_1` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `menu_addons_ibfk_2` FOREIGN KEY (`addon_group_id`) REFERENCES `addon_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
