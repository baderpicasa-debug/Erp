-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 27, 2025 at 01:49 AM
-- Server version: 8.0.43
-- PHP Version: 8.4.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `anisasa_erb`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `phone`, `email`, `company_name`) VALUES
(1, 'admin', '$2y$10$YKMnHa3yZfJ9sV7dvPE08eHOy689g8VtnAud0z1rpvVPTr3.NRmWC', '966554949105', 'my-msn@msn.com', 'Anisa rest');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int NOT NULL,
  `branch_name` varchar(100) NOT NULL,
  `address` text,
  `phone` varchar(20) DEFAULT NULL,
  `manager_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branch_inventory_logs`
--

CREATE TABLE `branch_inventory_logs` (
  `id` int NOT NULL,
  `batch_id` int NOT NULL DEFAULT '0',
  `employee_id` int NOT NULL,
  `branch_id` int NOT NULL,
  `item_id` int NOT NULL,
  `qty_change` decimal(10,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `branch` varchar(100) DEFAULT NULL,
  `branch_id` int DEFAULT NULL,
  `email` varchar(150) DEFAULT 'user@example.com',
  `phone` varchar(20) DEFAULT '0500000000',
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `name`, `branch`, `branch_id`, `email`, `phone`, `is_active`) VALUES
(1, 'ali', 'nu', NULL, 'mmm@mmm.com', '0554949105', 1);

-- --------------------------------------------------------

--
-- Table structure for table `employees_auth`
--

CREATE TABLE `employees_auth` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_temp_password` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `employees_auth`
--

INSERT INTO `employees_auth` (`id`, `employee_id`, `password`, `is_temp_password`) VALUES
(1, 11, '$2y$10$95BMzeEdW.zuQAfi4n8l4ePfvxIBUzj8i.Mu4M/cVp6iix9yEXirW', 0),
(2, 12, '$2y$10$qFySYmOcdkYWOhUEh9N7ROU2NGunZvITscfQiPD97n.HtXlU2iqjG', 0),
(3, 2, '$2y$10$mhmgFH4vFWLRusOfh/SG7.CYWRfk1VtJkgJhcs.sIScEJkxpZcQEi', 0),
(4, 1, '$2y$10$YRIwRj7NYF6Fl2t0shxlw.pJUM44TScxDFPp142nEDSAZWOj5NP82', 0),
(6, 1, '$2y$10$ZPLIsmc/QG2RcfgaJe7sfuqtkE4b/Gm8nEeCSKfgDDfbfZM1v8XBG', 0),
(7, 1, '$2y$10$wq0uWIL0snnkAKHFcEjTGO1P052AkUTdecEZdRJoCWNAj3GrvcxwK', 0),
(8, 1, '$2y$10$p8PoKj6k65QZcrYKAhxDquBv.WaZRN6yxAasAZA.MTVd3ZrJPS27e', 0);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int NOT NULL,
  `category` varchar(100) NOT NULL,
  `item_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `category`, `item_name`) VALUES
(1, 'drink', 'pipse'),
(2, 'meet', 'chekin'),
(3, 'meet', 'المنتج 2');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `ok` tinyint(1) NOT NULL DEFAULT '0',
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `username`, `ip_address`, `ok`, `ts`) VALUES
(1, 'Admin', '5.41.101.117', 1, '2025-09-23 15:50:06'),
(2, 'ali', '5.41.101.117', 1, '2025-09-23 15:50:26'),
(3, 'Admin', '5.41.101.117', 0, '2025-09-23 15:50:33'),
(4, 'admin', '95.187.144.192', 1, '2025-09-23 20:12:51'),
(5, 'admin', '2.90.232.128', 1, '2025-09-23 23:29:58'),
(6, 'admin', '2.90.216.152', 1, '2025-09-24 00:01:36'),
(7, 'admin', '2.90.216.156', 1, '2025-09-24 00:07:53'),
(8, 'admin', '2.90.232.134', 1, '2025-09-24 00:16:11'),
(9, 'admin', '2.90.232.134', 1, '2025-09-24 00:16:45'),
(10, 'ali', '2.90.232.134', 1, '2025-09-24 00:17:01'),
(11, 'Admin', '66.118.137.96', 1, '2025-09-24 00:53:54'),
(12, 'admin', '66.118.137.96', 1, '2025-09-24 01:16:50'),
(13, 'admin', '66.118.137.96', 1, '2025-09-24 01:27:57'),
(14, 'Ali', '66.118.137.96', 1, '2025-09-24 01:28:59'),
(15, 'admin', '66.118.137.96', 1, '2025-09-24 01:29:42'),
(16, 'admin', '66.118.137.96', 1, '2025-09-24 01:40:35'),
(17, 'Admin', '66.118.137.96', 1, '2025-09-24 01:52:08'),
(18, 'admin', '2.90.240.58', 1, '2025-09-24 01:59:11'),
(19, 'Ali', '2.90.240.58', 1, '2025-09-24 02:03:25'),
(20, 'admin', '151.255.36.5', 1, '2025-09-24 13:09:04'),
(21, 'admin', '151.255.36.5', 1, '2025-09-24 13:16:14'),
(22, 'Admin', '151.255.32.215', 1, '2025-09-24 13:16:58'),
(23, 'admin', '151.255.36.5', 1, '2025-09-24 13:39:22'),
(24, 'ali', '151.255.36.5', 1, '2025-09-24 13:40:10'),
(25, 'admin', '151.255.36.5', 0, '2025-09-24 13:42:48'),
(26, 'admin', '151.255.36.5', 1, '2025-09-24 13:42:56'),
(27, 'admin', '151.255.36.5', 1, '2025-09-24 13:49:26'),
(28, 'ali', '151.255.36.5', 1, '2025-09-24 13:49:46'),
(29, 'admin', '151.255.36.5', 1, '2025-09-24 13:50:01'),
(30, 'ali', '151.255.36.5', 1, '2025-09-24 13:50:46'),
(31, 'admin', '151.255.36.5', 1, '2025-09-24 13:52:04');

-- --------------------------------------------------------

--
-- Table structure for table `store_orders`
--

CREATE TABLE `store_orders` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `branch` varchar(100) DEFAULT NULL,
  `branch_id` int DEFAULT NULL,
  `order_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `store_orders`
--

INSERT INTO `store_orders` (`id`, `employee_id`, `branch`, `branch_id`, `order_date`) VALUES
(1, 1, 'nu', NULL, '2025-09-24 13:51:35'),
(2, 1, 'nu', NULL, '2025-09-24 13:51:45');

-- --------------------------------------------------------

--
-- Table structure for table `store_order_items`
--

CREATE TABLE `store_order_items` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `item_id` int NOT NULL,
  `qty` decimal(10,2) NOT NULL,
  `unit` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `store_order_items`
--

INSERT INTO `store_order_items` (`id`, `order_id`, `item_id`, `qty`, `unit`) VALUES
(1, 1, 1, 1.50, 'Carton'),
(2, 2, 2, 2.00, 'Piece');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `branch_name` (`branch_name`);

--
-- Indexes for table `branch_inventory_logs`
--
ALTER TABLE `branch_inventory_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `fk_inventory_branch` (`branch_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `employees_auth`
--
ALTER TABLE `employees_auth`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_username_time` (`username`,`ts`),
  ADD KEY `idx_ip_time` (`ip_address`,`ts`),
  ADD KEY `idx_attempt_time` (`ts`);

--
-- Indexes for table `store_orders`
--
ALTER TABLE `store_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_store_orders_employee_id` (`employee_id`);

--
-- Indexes for table `store_order_items`
--
ALTER TABLE `store_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_store_order_items_order_id` (`order_id`),
  ADD KEY `fk_store_order_items_item_id` (`item_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branch_inventory_logs`
--
ALTER TABLE `branch_inventory_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employees_auth`
--
ALTER TABLE `employees_auth`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `store_orders`
--
ALTER TABLE `store_orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `store_order_items`
--
ALTER TABLE `store_order_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `branch_inventory_logs`
--
ALTER TABLE `branch_inventory_logs`
  ADD CONSTRAINT `fk_inventory_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_logs_employee_id` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `store_orders`
--
ALTER TABLE `store_orders`
  ADD CONSTRAINT `fk_store_orders_employee_id` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `store_order_items`
--
ALTER TABLE `store_order_items`
  ADD CONSTRAINT `fk_store_order_items_item_id` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_store_order_items_order_id` FOREIGN KEY (`order_id`) REFERENCES `store_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
