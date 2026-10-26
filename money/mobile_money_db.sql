-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 15, 2026 at 07:05 PM
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
-- Database: `mobile_money_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_balances`
--

CREATE TABLE `daily_balances` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `line_id` int(11) NOT NULL,
  `opening_till` decimal(15,2) DEFAULT 0.00,
  `opening_lipa` decimal(15,2) DEFAULT 0.00,
  `closing_till` decimal(15,2) DEFAULT 0.00,
  `closing_lipa` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_cash`
--

CREATE TABLE `daily_cash` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `opening_cash` decimal(15,2) DEFAULT 0.00,
  `closing_cash` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_closing`
--

CREATE TABLE `daily_closing` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `total_till` decimal(15,2) DEFAULT 0.00,
  `total_lipa` decimal(15,2) DEFAULT 0.00,
  `total_float` decimal(15,2) DEFAULT 0.00,
  `total_cash` decimal(15,2) DEFAULT 0.00,
  `total_money` decimal(15,2) DEFAULT 0.00,
  `opened_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) DEFAULT 'open',
  `closed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mobile_lines`
--

CREATE TABLE `mobile_lines` (
  `id` int(11) NOT NULL,
  `line_name` varchar(50) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `network` varchar(30) NOT NULL,
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mobile_lines`
--

INSERT INTO `mobile_lines` (`id`, `line_name`, `phone_number`, `network`, `status`, `created_at`) VALUES
(1, 'Line 1', '0712345678', 'Vodacom', 'active', '2026-09-05 13:51:54'),
(2, 'Line 2', '0712345679', 'Airtel', 'active', '2026-09-05 13:51:54'),
(3, 'Line 3', '0712345680', 'Tigo', 'active', '2026-09-05 13:51:54'),
(4, 'Line 4', '0712345681', 'Halotel', 'active', '2026-09-05 13:51:54');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(20) DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `created_at`) VALUES
(1, 'robert', 'robertmeshack39@gmail.com', '$2a$12$UbLTlovurY8Z5oBY9shpF.9zZz7azkQGRjlqlQvx3KF55Fw/EnW0C', 'admin', '2026-08-25 13:10:14');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `daily_balances`
--
ALTER TABLE `daily_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date_line` (`date`,`line_id`),
  ADD KEY `line_id` (`line_id`);

--
-- Indexes for table `daily_cash`
--
ALTER TABLE `daily_cash`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `date` (`date`);

--
-- Indexes for table `daily_closing`
--
ALTER TABLE `daily_closing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `date` (`date`),
  ADD KEY `closed_by` (`closed_by`);

--
-- Indexes for table `mobile_lines`
--
ALTER TABLE `mobile_lines`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_balances`
--
ALTER TABLE `daily_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `daily_cash`
--
ALTER TABLE `daily_cash`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `daily_closing`
--
ALTER TABLE `daily_closing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `mobile_lines`
--
ALTER TABLE `mobile_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `daily_balances`
--
ALTER TABLE `daily_balances`
  ADD CONSTRAINT `daily_balances_ibfk_1` FOREIGN KEY (`line_id`) REFERENCES `mobile_lines` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `daily_closing`
--
ALTER TABLE `daily_closing`
  ADD CONSTRAINT `daily_closing_ibfk_1` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
