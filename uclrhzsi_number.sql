-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 10, 2026 at 02:06 PM
-- Server version: 11.4.12-MariaDB
-- PHP Version: 8.4.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `uclrhzsi_number`
--

-- --------------------------------------------------------

--
-- Table structure for table `catalog`
--

CREATE TABLE `catalog` (
  `id` int(11) NOT NULL,
  `service_type` enum('Telegram','WhatsApp') NOT NULL,
  `name` varchar(100) NOT NULL,
  `country` varchar(100) NOT NULL,
  `price_cost_usd` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_cost_inr` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_usd` decimal(10,2) NOT NULL,
  `price_inr` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `catalog`
--

INSERT INTO `catalog` (`id`, `service_type`, `name`, `country`, `price_cost_usd`, `price_cost_inr`, `price_usd`, `price_inr`, `stock`, `status`) VALUES
(1, 'Telegram', 'India', 'India', 0.28, 25.00, 0.51, 45.00, 112, 'active'),
(2, 'Telegram', 'india-best-quality', 'India', 0.34, 30.00, 0.56, 50.00, 48, 'active'),
(3, 'Telegram', 'india-free-as-bird', 'India', 0.17, 15.00, 0.28, 25.00, 122, 'active'),
(4, 'Telegram', 'india-spam-acc', 'India', 0.11, 10.00, 0.18, 16.00, 98, 'active'),
(5, 'Telegram', 'indian-new-acc', 'India', 0.14, 12.00, 0.25, 22.00, 339, 'active'),
(6, 'Telegram', 'indian-old-2020', 'India', 0.67, 60.00, 1.12, 100.00, 34, 'active'),
(7, 'Telegram', 'indian-old-2021', 'India', 0.45, 40.00, 0.79, 70.00, 51, 'active'),
(8, 'Telegram', 'indian-old-2022', 'India', 0.43, 38.00, 0.73, 65.00, 123, 'active'),
(9, 'Telegram', 'indian-old-2023', 'India', 0.39, 35.00, 0.67, 60.00, 135, 'active'),
(10, 'Telegram', 'indian-old-2024', 'India', 0.34, 30.00, 0.56, 50.00, 78, 'active'),
(11, 'Telegram', 'myanmar', 'Myanmar', 0.20, 18.00, 0.34, 30.00, 13, 'active'),
(12, 'Telegram', 'usa', 'USA', 0.22, 20.00, 0.39, 35.00, 17, 'active'),
(13, 'Telegram', 'Vietnam', 'Vietnam', 0.43, 38.00, 0.70, 62.00, 42, 'active'),
(14, 'Telegram', 'Canada', 'Canada', 0.39, 35.00, 0.65, 58.00, 30, 'active'),
(15, 'Telegram', 'Chile', 'Chile', 0.51, 45.00, 0.81, 72.00, 33, 'active'),
(16, 'Telegram', 'Afghanistan', 'Afghanistan', 0.51, 45.00, 0.81, 72.00, 33, 'active'),
(17, 'Telegram', 'Greenland', 'Greenland', 0.90, 80.00, 1.51, 134.00, 42, 'active'),
(18, 'Telegram', 'United Arab Emirates', 'United Arab Emirates', 1.35, 120.00, 2.15, 191.00, 32, 'active'),
(19, 'Telegram', 'Fiji', 'Fiji', 0.79, 70.00, 1.29, 115.00, 40, 'active'),
(20, 'Telegram', 'Russia', 'Russia', 1.35, 120.00, 2.25, 200.00, 39, 'active'),
(21, 'Telegram', 'France', 'France', 1.01, 90.00, 1.72, 153.00, 38, 'active'),
(22, 'Telegram', 'China', 'China', 1.07, 95.00, 1.72, 153.00, 42, 'active'),
(23, 'Telegram', 'Turkey', 'Turkey', 0.84, 75.00, 1.39, 124.00, 48, 'active'),
(24, 'Telegram', 'Germany', 'Germany', 0.90, 80.00, 1.55, 138.00, 36, 'active'),
(25, 'WhatsApp', 'USA WhatsApp', 'USA', 1.69, 150.00, 2.81, 250.00, 10, 'active'),
(26, 'WhatsApp', 'Philippines WhatsApp', 'Philippines', 0.67, 60.00, 1.12, 100.00, 8, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `subject` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `status` enum('open','resolved') DEFAULT 'open',
  `admin_response` text DEFAULT NULL,
  `admin_deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_messages`
--

CREATE TABLE `complaint_messages` (
  `id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `sender` enum('user','admin') NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_otps`
--

CREATE TABLE `email_otps` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `otp` varchar(255) NOT NULL,
  `purpose` enum('signup','forgot_password') NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `attempts` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `email_otps`
--

INSERT INTO `email_otps` (`id`, `email`, `otp`, `purpose`, `expires_at`, `is_used`, `attempts`, `created_at`, `updated_at`) VALUES
(1, 'skalus888@gmail.com', '$2y$10$5nHWkNNLd61//6rufJypHOjs09.NKP58rH.TcNTVYmJWqTQpeXAJm', 'signup', '2026-07-17 16:55:45', 0, 0, '2026-07-17 11:15:45', '2026-07-17 11:15:45'),
(2, 'skalus@gmail.com', '$2y$10$RRtI2mBfQiIIFuiReL242Onc7quXWLOsdi2eqkwPko5OfASHU5Xfi', 'signup', '2026-07-17 16:58:38', 0, 0, '2026-07-17 11:18:38', '2026-07-17 11:18:38'),
(3, 'skalus888@gmail.com', '$2y$10$sW33Y52Y9qpXD1AC6ffpW.Hq4nZ1WxGjZQ5NpQ8xtlVoV5fwVvLkC', 'signup', '2026-07-17 16:58:50', 0, 0, '2026-07-17 11:18:50', '2026-07-17 11:18:50'),
(4, 'penseva370@gmail.com', '$2y$10$JXYN7dAFWMmh2Zxj9HOIu.JSQ1PT9eWteSAZgcrELm9WG5N5sQXaa', 'signup', '2026-07-17 23:36:42', 0, 0, '2026-07-17 17:56:42', '2026-07-17 17:56:42');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(150) NOT NULL,
  `attempted_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`ip_address`, `username`, `attempted_at`) VALUES
('152.59.28.160', 'nutrl786', '2026-08-02 04:58:50');

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `catalog_id` int(11) NOT NULL,
  `service_type` enum('Telegram','WhatsApp') NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `price_cost_inr` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_paid_inr` decimal(10,2) NOT NULL,
  `payment_method` varchar(20) DEFAULT 'UPI',
  `utr_number` varchar(50) NOT NULL,
  `screenshot_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `virtual_number_provided` varchar(50) DEFAULT NULL,
  `otp_provided` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `smtp_settings`
--

CREATE TABLE `smtp_settings` (
  `id` int(11) NOT NULL,
  `host` varchar(255) DEFAULT NULL,
  `port` int(11) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` text DEFAULT NULL,
  `encryption` varchar(20) DEFAULT NULL,
  `from_email` varchar(255) DEFAULT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `active` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `smtp_settings`
--

INSERT INTO `smtp_settings` (`id`, `host`, `port`, `username`, `password`, `encryption`, `from_email`, `from_name`, `active`) VALUES
(1, 'smtp-relay.brevo.com', 587, '', '', 'tls', 'no-reply@mangonumbers.com', 'Mango Numbers', 1);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('allow_signups', '1'),
('allow_website_usage', '1');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `username` varchar(150) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `status` varchar(20) DEFAULT 'active',
  `deletion_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `username`, `mobile`, `password`, `role`, `status`, `deletion_reason`, `created_at`) VALUES
(1, 'Administrator', 'admin@mangonumbers.com', 'nutrl786', NULL, '$2y$10$GVK/A6yIS17gA6g.n.rMTuRkhg1FPqFR.9EKNiVx784xFiR.MlVky', 'admin', 'active', NULL, '2026-07-14 05:50:55'),
(2, 'Standard User', 'user@mangonumbers.com', 'user', NULL, '$2y$10$BLuiZeq68uQhcFB.pNBB5eVMzi/SZpgB2c9QF00X235L9bO2JGzeW', 'user', 'active', NULL, '2026-07-14 05:50:55');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `catalog`
--
ALTER TABLE `catalog`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `complaint_messages`
--
ALTER TABLE `complaint_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `complaint_id` (`complaint_id`);

--
-- Indexes for table `email_otps`
--
ALTER TABLE `email_otps`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD KEY `ip_time_idx` (`ip_address`,`attempted_at`),
  ADD KEY `user_time_idx` (`username`,`attempted_at`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `catalog_id` (`catalog_id`);

--
-- Indexes for table `smtp_settings`
--
ALTER TABLE `smtp_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `catalog`
--
ALTER TABLE `catalog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_messages`
--
ALTER TABLE `complaint_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_otps`
--
ALTER TABLE `email_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `smtp_settings`
--
ALTER TABLE `smtp_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `complaint_messages`
--
ALTER TABLE `complaint_messages`
  ADD CONSTRAINT `complaint_messages_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchases_ibfk_2` FOREIGN KEY (`catalog_id`) REFERENCES `catalog` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
