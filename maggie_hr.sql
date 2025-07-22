-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 21, 2025 at 01:44 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `maggie_hr`
--

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Administration', 'Manages employee relations and company policies.', '2025-07-19 09:04:13', '2025-07-21 09:35:21'),
(2, 'Commercial', 'Handles sales, marketing, and customer relations upodates', '2025-07-19 09:04:13', '2025-07-21 09:19:00'),
(3, 'Technical', 'Manages technical operations and development', '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(4, 'Corporate Affairs', 'Handles legal, compliance, and corporate governance', '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(5, 'Fort-Aqua', 'Water management and supply operations', '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(6, 'Maragua', 'maragua', '2025-07-21 09:23:20', '2025-07-21 09:23:20');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `national_id` int(10) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `designation` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `address` text DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `employment_type` varchar(20) NOT NULL,
  `employee_type` varchar(20) NOT NULL,
  `profile_image_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `employee_status` enum('active','inactive','resigned','fired','retired') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_id`, `first_name`, `last_name`, `national_id`, `email`, `designation`, `phone`, `date_of_birth`, `address`, `department_id`, `section_id`, `position`, `salary`, `hire_date`, `employment_type`, `employee_type`, `profile_image_url`, `created_at`, `updated_at`, `employee_status`) VALUES
(104, 'EMP002', 'dan', 'Wambui', 40135584, 'karenjuduncan750@gmail.com', 'Innovation', 'undefined', '1981-03-11', 'Kihoya', 1, 2, NULL, NULL, '2025-07-17', 'contract', 'section_head', NULL, '2025-07-21 08:00:23', '2025-07-21 08:33:27', 'retired'),
(106, 'Emp003', 'Evans', 'Mwangi', 390765, 'mwangi21@gmail.com', '0', '0789654123', '2000-12-12', 'Muranga', 4, 9, NULL, NULL, '2025-07-18', 'permanent', 'manager', NULL, '2025-07-21 09:43:18', '2025-07-21 09:43:18', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `name`, `description`, `department_id`, `created_at`, `updated_at`) VALUES
(1, 'Human Resources', 'Employee management and policies', 1, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(2, 'Finance', 'Financial planning and accounting', 1, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(3, 'Sales', 'Direct sales operations', 2, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(4, 'Marketing', 'Brand promotion and advertising', 2, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(5, 'Customer Service', 'Customer support and relations', 2, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(6, 'Software Development', 'Application and system development', 3, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(7, 'IT Support', 'Technical support and maintenance', 3, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(8, 'Network Operations', 'Network infrastructure management', 3, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(9, 'Legal Affairs', 'Legal compliance and contracts', 4, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(10, 'Public Relations', 'Media and public communications', 4, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(11, 'Water Supply', 'Water distribution and supply management', 5, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(12, 'customer care Maragua', 'customer care', 6, '2025-07-21 09:36:58', '2025-07-21 09:36:58');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('super_admin','hr_manager','dept_head','section_head','manager','employee') DEFAULT 'employee',
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_image_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `first_name`, `last_name`, `password`, `role`, `phone`, `address`, `profile_image_url`, `created_at`, `updated_at`) VALUES
('admin-001', 'admin@company.com', 'dan', 'Wambui', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', '0112554479', 'Kihoya', NULL, '2025-07-19 09:04:12', '2025-07-21 10:14:13'),
('dept-001', 'depthead@company.com', 'Department', 'Head', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dept_head', NULL, NULL, NULL, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
('hr-001', 'hr@company.com', 'HR', 'Manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'hr_manager', NULL, NULL, NULL, '2025-07-19 09:04:12', '2025-07-19 09:04:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
