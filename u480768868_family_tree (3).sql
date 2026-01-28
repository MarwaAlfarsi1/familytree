-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jan 28, 2026 at 07:40 PM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u480768868_family_tree`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_requests`
--

CREATE TABLE `account_requests` (
  `id` int(11) NOT NULL,
  `person_id` int(11) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `membership_number` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `requested_username` varchar(100) NOT NULL,
  `requested_password_hash` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_id` int(11) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `email`, `username`, `password`, `created_at`) VALUES
(1, 'admin@example.com', NULL, '$2y$10$Cy84FoGnY4dOdrDtnkdw4eDcyNpg5jqnH1HQv7fiL01igG1.Q1BAq', '2025-12-30 13:08:05'),
(3, 'admin@familytree.com', NULL, '$2y$10$hE9d8ViqruTimW5bpaTIeOzPInPe89f2lTgty4uiP6.mbwQeUUfli', '2026-01-05 20:40:44');

-- --------------------------------------------------------

--
-- Table structure for table `persons`
--

CREATE TABLE `persons` (
  `id` int(10) UNSIGNED NOT NULL,
  `membership_number` varchar(50) DEFAULT NULL,
  `tree_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `gender` enum('male','female') NOT NULL,
  `birth_date` date DEFAULT NULL,
  `birth_place` varchar(255) DEFAULT NULL,
  `death_date` date DEFAULT NULL,
  `residence_location` varchar(255) DEFAULT NULL,
  `phone_number` varchar(50) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `tribe` varchar(255) DEFAULT NULL,
  `father_id` int(10) UNSIGNED DEFAULT NULL,
  `mother_id` int(10) UNSIGNED DEFAULT NULL,
  `generation_level` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `is_root` tinyint(1) NOT NULL DEFAULT 0,
  `spouse_person_id` int(10) UNSIGNED DEFAULT NULL,
  `second_spouse_person_id` int(11) DEFAULT NULL,
  `second_spouse_is_external` tinyint(1) DEFAULT 0,
  `second_external_tree_id` int(11) DEFAULT NULL,
  `spouse_is_external` tinyint(1) NOT NULL DEFAULT 0,
  `external_tree_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `photo` varchar(255) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `external_father_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trees`
--

CREATE TABLE `trees` (
  `id` int(10) UNSIGNED NOT NULL,
  `root_person_id` int(10) UNSIGNED DEFAULT NULL,
  `tree_type` enum('main','external') NOT NULL DEFAULT 'main',
  `title` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `trees`
--

INSERT INTO `trees` (`id`, `root_person_id`, `tree_type`, `title`, `created_at`, `name`) VALUES
(1, NULL, 'main', 'الشجرة الرئيسية', '2026-01-04 15:24:56', NULL),
(2, NULL, 'external', 'عائلة خارجية لـ جمانة', '2026-01-04 19:24:23', NULL),
(3, NULL, 'external', NULL, '2026-01-05 21:21:39', 'صالح الكعبي - عائلة خارجية'),
(4, NULL, 'external', 'عائلة خارجية لـ مروه', '2026-01-06 08:55:08', NULL),
(5, NULL, 'external', 'عائلة خارجية لـ منار', '2026-01-08 20:07:27', NULL),
(6, NULL, 'external', NULL, '2026-01-08 20:10:45', 'يوسف المعمري - عائلة خارجية'),
(7, NULL, 'external', 'عائلة خارجية لـ مرهون', '2026-01-12 16:40:40', NULL),
(8, NULL, 'external', 'عائلة خارجية لـ سعيد', '2026-01-13 17:02:15', NULL),
(9, NULL, 'external', 'عائلة خارجية لـ مبارك', '2026-01-13 17:03:13', NULL),
(10, NULL, 'external', 'عائلة خارجية لـ شيخة', '2026-01-17 18:17:29', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_requests`
--
ALTER TABLE `account_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_person_id` (`person_id`),
  ADD KEY `idx_username` (`requested_username`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`);

--
-- Indexes for table `persons`
--
ALTER TABLE `persons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tree` (`tree_id`),
  ADD KEY `idx_father` (`father_id`),
  ADD KEY `idx_mother` (`mother_id`),
  ADD KEY `idx_generation` (`generation_level`),
  ADD KEY `idx_spouse` (`spouse_person_id`),
  ADD KEY `idx_external_tree` (`external_tree_id`),
  ADD KEY `idx_membership_number` (`membership_number`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_tribe` (`tribe`);

--
-- Indexes for table `trees`
--
ALTER TABLE `trees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_root_person_id` (`root_person_id`),
  ADD KEY `idx_tree_type` (`tree_type`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_requests`
--
ALTER TABLE `account_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `persons`
--
ALTER TABLE `persons`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `trees`
--
ALTER TABLE `trees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
