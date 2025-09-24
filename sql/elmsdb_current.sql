-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 25, 2025 at 12:18 AM
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
-- Database: `elmsdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_name`, `manager_id`, `description`) VALUES
(1, 'Human Resources', NULL, 'Handles all HR related activities'),
(2, 'Medicine', NULL, 'Medical department for doctors and physicians'),
(3, 'OPD', 2, 'Outpatient Department'),
(4, 'Administration', NULL, 'Hospital administration department'),
(5, 'IT', 4, 'IT department');

-- --------------------------------------------------------

--
-- Table structure for table `leave_balances`
--

CREATE TABLE `leave_balances` (
  `balance_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type` varchar(50) NOT NULL,
  `total_entitlement` int(11) NOT NULL DEFAULT 0,
  `used_days` int(11) NOT NULL DEFAULT 0,
  `remaining_days` int(11) NOT NULL DEFAULT 0,
  `year` year(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_balances`
--

INSERT INTO `leave_balances` (`balance_id`, `employee_id`, `leave_type`, `total_entitlement`, `used_days`, `remaining_days`, `year`) VALUES
(1, 3, 'Annual Leave', 21, 5, 16, '2025'),
(2, 3, 'Sick Leave', 14, 2, 12, '2025'),
(3, 3, 'Emergency Leave', 5, 1, 4, '2025');

-- --------------------------------------------------------

--
-- Table structure for table `leave_policies`
--

CREATE TABLE `leave_policies` (
  `policy_id` int(11) NOT NULL,
  `leave_type` varchar(50) NOT NULL,
  `entitlement_days` int(11) NOT NULL,
  `carry_forward` tinyint(1) DEFAULT 0,
  `max_carry_forward_days` int(11) DEFAULT 0,
  `approval_flow` enum('manager','admin','both') DEFAULT 'manager',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_policies`
--

INSERT INTO `leave_policies` (`policy_id`, `leave_type`, `entitlement_days`, `carry_forward`, `max_carry_forward_days`, `approval_flow`, `description`, `created_at`, `is_active`) VALUES
(1, 'Annual Leave', 21, 1, 7, 'manager', NULL, '2025-09-15 12:26:21', 1),
(2, 'Sick Leave', 14, 0, 0, 'manager', NULL, '2025-09-15 12:26:21', 1),
(3, 'Maternity Leave', 90, 0, 0, 'admin', NULL, '2025-09-15 12:26:21', 1),
(4, 'Paternity Leave', 14, 0, 0, 'manager', NULL, '2025-09-15 12:26:21', 1),
(5, 'Emergency Leave', 5, 0, 0, 'manager', NULL, '2025-09-15 12:26:21', 1),
(6, 'Unpaid Leave', 0, 0, 0, 'admin', NULL, '2025-09-15 12:26:21', 1);

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `request_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `manager_id` int(11) DEFAULT NULL,
  `admin_override` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`request_id`, `employee_id`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `manager_id`, `admin_override`, `created_at`, `updated_at`) VALUES
(1, 3, 'Annual Leave', '2025-09-22', '2025-09-25', 'Family vacation', 'cancelled', 2, 0, '2025-09-15 12:26:21', '2025-09-16 14:22:28'),
(2, 3, 'Sick Leave', '2025-09-12', '2025-09-13', 'Flu', 'approved', 2, 0, '2025-09-15 12:26:21', '2025-09-15 12:26:21'),
(3, 3, 'Annual Leave', '2025-09-19', '2025-09-22', 'hmmm', 'cancelled', 2, 0, '2025-09-15 20:17:03', '2025-09-16 11:06:10'),
(4, 3, 'Annual Leave', '2025-09-19', '2025-09-22', 'hmmm', 'rejected', 2, 0, '2025-09-15 20:18:13', '2025-09-16 11:03:40'),
(5, 3, 'Emergency Leave', '2025-09-19', '2025-09-21', 'qwertyuio', 'cancelled', 2, 0, '2025-09-16 14:23:33', '2025-09-16 20:14:49'),
(6, 3, 'Sick Leave', '2025-09-16', '2025-09-21', 'dfghn cfghn', 'cancelled', 2, 0, '2025-09-16 18:08:23', '2025-09-16 20:14:22'),
(7, 3, 'Annual Leave', '2025-09-17', '2025-09-20', 'qwertyuio', 'rejected', 2, 0, '2025-09-17 02:12:31', '2025-09-17 02:26:13'),
(8, 3, 'Annual Leave', '2025-09-17', '2025-09-20', 'qwertyuio', 'rejected', 2, 0, '2025-09-17 02:26:41', '2025-09-17 20:15:13'),
(9, 2, 'Annual Leave', '2025-09-22', '2025-09-28', 'dfghjk', 'cancelled', NULL, 0, '2025-09-21 03:52:50', '2025-09-21 06:01:27');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` enum('email','system') DEFAULT 'system',
  `status` enum('unread','read') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `message`, `type`, `status`, `created_at`) VALUES
(2, 2, 'New leave request from Jane Employee for Annual Leave from Sep 17, 2025 to Sep 20, 2025.', 'system', 'read', '2025-09-17 02:12:32');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager','employee') NOT NULL DEFAULT 'employee',
  `department_id` int(11) DEFAULT NULL,
  `contact_info` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `profile_picture` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `role`, `department_id`, `contact_info`, `created_at`, `updated_at`, `is_active`, `profile_picture`) VALUES
(1, 'Kojo Administrator', 'admin@sfxhospital.org', '$2y$10$dRUMIGDbl050lojPaDRKO.JCxADUuH5AzX1J2H0xfcSdSZ6K1ThiO', 'admin', 1, '', '2025-09-15 12:26:20', '2025-09-24 22:12:42', 1, 'profile_1_1758751962.jpeg'),
(2, 'John Manager', 'manager@sfxhospital.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 3, 'Ext: 1002', '2025-09-15 12:26:20', '2025-09-24 22:14:15', 1, 'profile_2_1758752055.jpeg'),
(3, 'Jane Employee', 'employee@sfxhospital.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 3, 'Ext: 1003', '2025-09-15 12:26:20', '2025-09-24 22:15:35', 1, 'profile_3_1758752135.jpeg'),
(4, 'John Pual', 'jp@sfxhospital.org', '$2y$10$aZWrV8ZN5TsLlp0ybtaRLepDm.bWOaAvv9iauZe8pfiNlhwPUYUX.', 'manager', 5, '0550612763', '2025-09-15 16:30:39', '2025-09-15 16:30:39', 1, NULL),
(5, 'Michael Adisah', 'ma@sfxhospital.org', '$2y$10$Z8bIedtpEm2aCQfLePYyQeAirbSUQd/g5lSSUuwLbP4Zi0Y69SgSW', 'employee', 5, '098765432', '2025-09-16 14:09:58', '2025-09-16 14:09:58', 1, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD PRIMARY KEY (`balance_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `leave_policies`
--
ALTER TABLE `leave_policies`
  ADD PRIMARY KEY (`policy_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `department_id` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `leave_balances`
--
ALTER TABLE `leave_balances`
  MODIFY `balance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `leave_policies`
--
ALTER TABLE `leave_policies`
  MODIFY `policy_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD CONSTRAINT `leave_balances_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`manager_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
