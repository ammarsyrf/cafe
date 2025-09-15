-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 15, 2025 at 06:52 AM
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
(1, 'Promo Kopi Spesial', 'Nikmati diskon 20% untuk semua varian kopi susu.', 'https://placehold.co/1200x400/3498db/ffffff?text=Promo+Kopi', '#category-kopi', 1, 10),
(2, 'Menu Makanan Baru!', 'Coba aneka hidangan pasta terbaru kami sekarang juga.', 'https://placehold.co/1200x400/2ecc71/ffffff?text=Makanan+Baru', '#category-makanan', 1, 20),
(3, 'Happy Hour Setiap Hari', 'Beli 1 Gratis 1 untuk semua minuman dari jam 3-5 sore.', 'https://placehold.co/1200x400/e74c3c/ffffff?text=Happy+Hour', '#category-minuman', 1, 30);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu pendaftaran member'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabel untuk data member pelanggan';

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `name`, `email`, `password`, `phone_number`, `points`, `created_at`) VALUES
(1, 'ammarsyrf', 'ammarsyrf78@gmail.com', '$2y$10$CnuSBx1Fj7HeVnFjc6yFTet/eFFkpSEf7IKg.qt4wIzaoQg640C1W', '087860', 0, '2025-09-12 08:10:36'),
(2, 'budi', 'budi@gmail.com', '$2y$10$Lumj1b92cPbnFnBTcFKcLu7N5Z7FkGJEvsgLXe/3g8t.aIZyOyxBu', '12465', 0, '2025-09-13 04:44:10');

-- --------------------------------------------------------

--
-- Table structure for table `menu`
--

CREATE TABLE `menu` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` enum('makanan','minuman','snack','other') NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `discount_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu`
--

INSERT INTO `menu` (`id`, `name`, `description`, `price`, `category`, `stock`, `image_url`, `is_available`, `discount_price`) VALUES
(1, 'Kopi Susu', 'Kopi robusta dengan susu dan gula aren', 25000.00, 'minuman', 45, 'https://placehold.co/150x150/d1d5db/000000?text=Kopi+Susu', 1, NULL),
(2, 'Nasi Goreng Spesial', 'Nasi goreng dengan telur, sosis, dan bakso', 35000.00, 'makanan', 15, 'https://placehold.co/150x150/d1d5db/000000?text=Nasi+Goreng', 1, NULL),
(3, 'French Fries', 'Kentang goreng renyah dengan saus', 20000.00, 'snack', 38, 'https://placehold.co/150x150/d1d5db/000000?text=French+Fries', 1, NULL),
(4, 'Air Mineral', 'Air mineral 600ml', 5000.00, 'other', 86, 'https://placehold.co/150x150/d1d5db/000000?text=Air+Mineral', 1, NULL),
(6, 'Espresso', '', 15000.00, 'minuman', 19, 'https://placehold.co/150x150/d1d5db/000000?text=Espresso', 1, NULL),
(7, 'Kapal Api', 'Kopi Kapal Api', 5000.00, 'minuman', 23, 'uploads/menu_68c5640044fff1.77351467.png', 1, NULL);

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
(43, NULL, 2, NULL, 'tyo', NULL, 'dine-in', 'completed', 60000.00, 0.00, 6600.00, 66600.00, 'manual_cash', '2025-09-12 09:17:29', '2025-09-14 12:13:44', NULL),
(44, NULL, 2, 2, 'azka', NULL, 'dine-in', 'completed', 60000.00, 0.00, 6600.00, 66600.00, '', '2025-09-12 09:22:06', '2025-09-12 09:22:06', NULL),
(45, 2, 2, 2, 'tyo', NULL, 'dine-in', 'completed', 25000.00, 0.00, 2750.00, 27750.00, '', '2025-09-12 09:25:06', '2025-09-12 09:25:06', NULL),
(46, 1, NULL, 2, 'ammar', NULL, 'dine-in', 'completed', 35000.00, 0.00, 3850.00, 38850.00, 'cash', '2025-09-12 09:28:40', '2025-09-12 09:28:55', NULL),
(47, 1, 2, 2, 'budi', NULL, 'dine-in', 'completed', 35000.00, 0.00, 3850.00, 38850.00, 'manual_cash', '2025-09-12 09:29:12', '2025-09-12 10:48:46', '2025-09-12 17:48:46'),
(48, 1, NULL, 2, 'tyo', NULL, 'dine-in', 'completed', 80000.00, 0.00, 8800.00, 88800.00, 'QRIS', '2025-09-12 10:46:49', '2025-09-12 10:47:12', '2025-09-12 17:47:12'),
(49, 1, NULL, 2, 'karyo', NULL, 'dine-in', 'completed', 35000.00, 0.00, 3850.00, 38850.00, 'QRIS', '2025-09-13 12:17:21', '2025-09-13 12:17:51', NULL),
(50, 1, NULL, 2, 'tyo', NULL, 'dine-in', 'completed', 20000.00, 0.00, 2200.00, 22200.00, 'QRIS', '2025-09-14 04:12:05', '2025-09-14 05:06:27', NULL),
(51, NULL, 2, 2, 'farhan', NULL, 'dine-in', 'completed', 105000.00, 0.00, 11550.00, 116550.00, 'manual_cash', '2025-09-14 04:58:22', '2025-09-14 04:58:31', '2025-09-14 11:58:31'),
(52, 1, NULL, 2, 'eza', NULL, 'dine-in', 'completed', 5000.00, 0.00, 550.00, 5550.00, 'cash', '2025-09-14 05:06:16', '2025-09-14 05:06:25', NULL),
(53, 1, NULL, 2, 'azka', NULL, 'dine-in', 'completed', 85000.00, 0.00, 9350.00, 94350.00, 'QRIS', '2025-09-14 05:10:43', '2025-09-14 05:20:21', '2025-09-14 12:20:21'),
(54, 1, 1, 2, NULL, 1, 'dine-in', 'completed', 115000.00, 11500.00, 11385.00, 114885.00, 'QRIS', '2025-09-14 12:06:33', '2025-09-14 12:13:48', '2025-09-14 19:06:47'),
(55, NULL, 2, 2, 'santoso', NULL, 'take-away', 'completed', 80000.00, 0.00, 8800.00, 88800.00, 'manual_cash', '2025-09-14 13:44:08', '2025-09-14 13:44:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `menu_id`, `quantity`, `price`) VALUES
(74, 43, 1, 1, 25000.00),
(75, 43, 2, 1, 35000.00),
(76, 44, 1, 1, 25000.00),
(77, 44, 2, 1, 35000.00),
(78, 45, 1, 1, 25000.00),
(79, 46, 2, 1, 35000.00),
(80, 47, 2, 1, 35000.00),
(81, 48, 2, 1, 35000.00),
(82, 48, 1, 1, 25000.00),
(83, 48, 3, 1, 20000.00),
(84, 49, 2, 1, 35000.00),
(85, 50, 2, 1, 20000.00),
(86, 51, 1, 1, 25000.00),
(87, 51, 2, 1, 35000.00),
(88, 51, 3, 1, 20000.00),
(89, 51, 4, 1, 5000.00),
(90, 51, 6, 1, 15000.00),
(91, 51, 7, 1, 5000.00),
(92, 52, 7, 1, 5000.00),
(93, 53, 2, 1, 35000.00),
(94, 53, 1, 1, 25000.00),
(95, 53, 3, 1, 20000.00),
(96, 53, 4, 1, 5000.00),
(97, 54, 2, 1, 35000.00),
(98, 54, 6, 1, 15000.00),
(99, 54, 7, 1, 5000.00),
(100, 54, 1, 1, 25000.00),
(101, 54, 3, 1, 20000.00),
(102, 54, 4, 3, 5000.00),
(103, 55, 1, 1, 25000.00),
(104, 55, 2, 1, 35000.00),
(105, 55, 6, 1, 15000.00),
(106, 55, 7, 1, 5000.00);

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
(4, 4, 0);

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
(22, 43, NULL, 66600.00, 100000.00, 33400.00, 'cash', '2025-09-12 09:17:29'),
(23, 44, NULL, 66600.00, 100000.00, 33400.00, 'cash', '2025-09-12 09:22:06'),
(24, 45, NULL, 27750.00, 30000.00, 2250.00, 'manual_cash', '2025-09-12 09:25:06'),
(25, 46, NULL, 38850.00, 40000.00, 1150.00, 'cash', '2025-09-12 09:28:55'),
(26, 47, NULL, 38850.00, 40000.00, 1150.00, 'manual_cash', '2025-09-12 09:29:12'),
(27, 51, NULL, 116550.00, 120000.00, 3450.00, 'manual_cash', '2025-09-14 04:58:22'),
(28, 52, NULL, 5550.00, 10000.00, 4450.00, 'cash', '2025-09-14 05:06:25'),
(29, 55, NULL, 88800.00, 100000.00, 11200.00, 'manual_cash', '2025-09-14 13:44:08');

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
-- AUTO_INCREMENT for table `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `menu`
--
ALTER TABLE `menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

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
