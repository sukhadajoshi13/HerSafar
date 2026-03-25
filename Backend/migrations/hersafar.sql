-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 06, 2025 at 07:18 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";



CREATE TABLE `admin_user_messages` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `ride_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `seats` int(11) DEFAULT 1,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `email` varchar(191) NOT NULL,
  `message` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `user_id`, `name`, `email`, `message`, `ip_address`, `created_at`) VALUES
(2, 14, 'Joshi Sukhada Deepak', 'dsjoshi13@gmail.com', 'hello', '::1', '2025-10-28 10:42:29'),
(3, 15, 'NEHA SUDHIR NIKAM', 'neha@gmail.com', 'hello there is problem', '::1', '2025-10-31 20:12:42');

-- --------------------------------------------------------

--
-- Table structure for table `driver_applications`
--

CREATE TABLE `driver_applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vehicle_make` varchar(100) DEFAULT NULL,
  `vehicle_model` varchar(100) DEFAULT NULL,
  `vehicle_number` varchar(50) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'pending',
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

CREATE TABLE `groups` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `join_token` char(48) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--

CREATE TABLE `group_members` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('member','admin') DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_messages`
--

CREATE TABLE `group_messages` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `posted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rides`
--

CREATE TABLE `rides` (
  `id` int(11) NOT NULL,
  `share_token` varchar(64) DEFAULT NULL,
  `driver_id` int(11) NOT NULL,
  `from_location` varchar(255) NOT NULL,
  `to_location` varchar(255) NOT NULL,
  `ride_date` date NOT NULL,
  `ride_time` time DEFAULT NULL,
  `seats` int(11) DEFAULT 1,
  `available_seats` int(11) DEFAULT 1,
  `price` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ride_group_shares`
--

CREATE TABLE `ride_group_shares` (
  `id` int(11) NOT NULL,
  `ride_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ride_shares`
--

CREATE TABLE `ride_shares` (
  `id` int(11) NOT NULL,
  `ride_id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `token` varchar(128) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gender` enum('female','male','other') DEFAULT 'female',
  `role` varchar(100) NOT NULL DEFAULT 'passenger',
  `active` tinyint(1) DEFAULT 1,
  `verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `vehicle_make` varchar(120) DEFAULT NULL,
  `vehicle_model` varchar(120) DEFAULT NULL,
  `vehicle_number` varchar(60) DEFAULT NULL,
  `license_number` varchar(120) DEFAULT NULL,
  `aadhar_number` varchar(12) DEFAULT NULL,
  `pan_number` varchar(10) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `driver_status` enum('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  `passenger_verified` tinyint(1) NOT NULL DEFAULT 0,
  `driver_verified` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `gender`, `role`, `active`, `verified`, `created_at`, `vehicle_make`, `vehicle_model`, `vehicle_number`, `license_number`, `aadhar_number`, `pan_number`, `bio`, `driver_status`, `passenger_verified`, `driver_verified`) VALUES
(1, 'Admin', 'admin@hersafar.com', '$2y$10$VYIaHeav1HQ6R2agCcHOQOd0cesaVD/pXu9qJ6Drc0He/me4qLuYy', NULL, 'female', 'admin', 1, 1, '2025-10-27 07:00:29', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'none', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_documents`
--

CREATE TABLE `user_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` enum('aadhar_front','aadhar_back','pan_front','pan_back','license') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime` varchar(80) DEFAULT NULL,
  `size_bytes` int(10) UNSIGNED DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_documents`
--

INSERT INTO `user_documents` (`id`, `user_id`, `type`, `file_path`, `mime`, `size_bytes`, `uploaded_at`) VALUES
(33, 13, 'aadhar_front', '/uploads/docs/13/photos/aadhar_front_uid13_1761627757_aadhar_front_uid4_1761550565_Carpooling_Logo_-_Modern_Lettermark_Style.png', 'image/png', 87614, '2025-10-28 05:02:37'),
(34, 13, 'aadhar_back', '/uploads/docs/13/photos/aadhar_back_uid13_1761627757_aadhar_front_uid4_1761550565_Carpooling_Logo_-_Modern_Lettermark_Style.png', 'image/png', 87614, '2025-10-28 05:02:37'),
(35, 13, 'pan_front', '/uploads/docs/13/photos/pan_front_uid13_1761627757_aadhar_front_uid4_1761550565_Carpooling_Logo_-_Modern_Lettermark_Style.png', 'image/png', 87614, '2025-10-28 05:02:37'),
(36, 13, 'pan_back', '/uploads/docs/13/photos/pan_back_uid13_1761627757_aadhar_front_uid4_1761552442_Carpooling_Logo_-_Modern_Lettermark_Style__1_.png', 'image/png', 87614, '2025-10-28 05:02:37'),
(37, 13, 'license', '/uploads/docs/13/photos/license_uid13_1761627757_aadhar_front_uid4_1761550565_Carpooling_Logo_-_Modern_Lettermark_Style.png', 'image/png', 87614, '2025-10-28 05:02:37'),
(38, 15, 'aadhar_front', '/uploads/docs/15/photos/aadhar_front_uid15_1761920837_1761509155-8b7a6db4a217-logoo.png', 'image/png', 1880, '2025-10-31 14:27:17'),
(39, 15, 'aadhar_back', '/uploads/docs/15/photos/aadhar_back_uid15_1761920837_aadhar_front_uid4_1761552442_Carpooling_Logo_-_Modern_Lettermark_Style__1_.png', 'image/png', 87614, '2025-10-31 14:27:17'),
(40, 15, 'pan_front', '/uploads/docs/15/photos/pan_front_uid15_1761920837_1761507721-ded7326ff191-logoo.png', 'image/png', 1880, '2025-10-31 14:27:17'),
(41, 15, 'pan_back', '/uploads/docs/15/photos/pan_back_uid15_1761920837_1761507721-ded7326ff191-logoo.png', 'image/png', 1880, '2025-10-31 14:27:17'),
(42, 15, 'license', '/uploads/docs/15/photos/license_uid15_1761920837_1761507721-ded7326ff191-logoo.png', 'image/png', 1880, '2025-10-31 14:27:17'),
(43, 14, 'aadhar_front', '/uploads/docs/14/photos/aadhar_front_uid14_1761924497_aadhar_front_uid4_1761550565_Carpooling_Logo_-_Modern_Lettermark_Style.png', 'image/png', 87614, '2025-10-31 15:28:17'),
(44, 14, 'aadhar_back', '/uploads/docs/14/photos/aadhar_back_uid14_1761924497_1761509155-8b7a6db4a217-logoo.png', 'image/png', 1880, '2025-10-31 15:28:17'),
(45, 14, 'pan_front', '/uploads/docs/14/photos/pan_front_uid14_1761924497_aadhar_front_uid4_1761552442_Carpooling_Logo_-_Modern_Lettermark_Style__1_.png', 'image/png', 87614, '2025-10-31 15:28:17'),
(46, 14, 'pan_back', '/uploads/docs/14/photos/pan_back_uid14_1761924497_1761507721-ded7326ff191-logoo.png', 'image/png', 1880, '2025-10-31 15:28:17'),
(47, 16, 'aadhar_front', '/uploads/docs/16/photos/aadhar_front_uid16_1761925213_aadhar_front_uid4_1761550565_Carpooling_Logo_-_Modern_Lettermark_Style.png', 'image/png', 87614, '2025-10-31 15:40:13'),
(48, 17, 'pan_front', '/uploads/docs/17/photos/pan_front_uid17_1761926476_aadhar_front_uid4_1761550565_Carpooling_Logo_-_Modern_Lettermark_Style.png', 'image/png', 87614, '2025-10-31 16:01:16'),
(49, 17, 'license', '/uploads/docs/17/photos/license_uid17_1761927129_aadhar_front_uid4_1761552442_Carpooling_Logo_-_Modern_Lettermark_Style__1_.png', 'image/png', 87614, '2025-10-31 16:12:09'),
(50, 19, 'pan_back', '/uploads/docs/19/photos/pan_back_uid19_1761927856_1761509155-8b7a6db4a217-logoo.png', 'image/png', 1880, '2025-10-31 16:24:16'),
(51, 20, 'aadhar_front', '/uploads/docs/20/photos/aadhar_front_uid20_1761929277_1761509155-8b7a6db4a217-logoo.png', 'image/png', 1880, '2025-10-31 16:47:57'),
(52, 20, 'aadhar_back', '/uploads/docs/20/photos/aadhar_back_uid20_1761929277_aadhar_front_uid4_1761550565_Carpooling_Logo_-_Modern_Lettermark_Style.png', 'image/png', 87614, '2025-10-31 16:47:57'),
(53, 20, 'pan_front', '/uploads/docs/20/photos/pan_front_uid20_1761929277_115201951_PreviewExamForm.PDF', 'application/pdf', 378562, '2025-10-31 16:47:57'),
(54, 20, 'pan_back', '/uploads/docs/20/photos/pan_back_uid20_1761929277_1761509155-8b7a6db4a217-logoo.png', 'image/png', 1880, '2025-10-31 16:47:57'),
(55, 21, 'license', '/uploads/docs/21/photos/license_uid21_1761929376_1761509155-8b7a6db4a217-logoo.png', 'image/png', 1880, '2025-10-31 16:49:36'),
(56, 20, 'pan_front', '/uploads/docs/20/photos/pan_front_uid20_1761929447_aadhar_front_uid4_1761552442_Carpooling_Logo_-_Modern_Lettermark_Style__1_.png', 'image/png', 87614, '2025-10-31 16:50:47'),
(57, 23, 'license', '/uploads/docs/23/photos/license_uid23_1761977722_DS_C-2.pdf', 'application/pdf', 105151, '2025-11-01 06:15:22');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_user_messages`
--
ALTER TABLE `admin_user_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ride_id` (`ride_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `driver_applications`
--
ALTER TABLE `driver_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `join_token` (`join_token`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indexes for table `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_group_user` (`group_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `group_messages`
--
ALTER TABLE `group_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `group_id` (`group_id`,`posted_at`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `token` (`token`);

--
-- Indexes for table `rides`
--
ALTER TABLE `rides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `share_token` (`share_token`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indexes for table `ride_group_shares`
--
ALTER TABLE `ride_group_shares`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ride_id` (`ride_id`,`group_id`),
  ADD KEY `group_id` (`group_id`);

--
-- Indexes for table `ride_shares`
--
ALTER TABLE `ride_shares`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `ride_id` (`ride_id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_documents`
--
ALTER TABLE `user_documents`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_user_messages`
--
ALTER TABLE `admin_user_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `driver_applications`
--
ALTER TABLE `driver_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `group_members`
--
ALTER TABLE `group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `group_messages`
--
ALTER TABLE `group_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rides`
--
ALTER TABLE `rides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `ride_group_shares`
--
ALTER TABLE `ride_group_shares`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ride_shares`
--
ALTER TABLE `ride_shares`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `user_documents`
--
ALTER TABLE `user_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_user_messages`
--
ALTER TABLE `admin_user_messages`
  ADD CONSTRAINT `admin_user_messages_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_user_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`ride_id`) REFERENCES `rides` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `driver_applications`
--
ALTER TABLE `driver_applications`
  ADD CONSTRAINT `fk_driver_app_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `groups`
--
ALTER TABLE `groups`
  ADD CONSTRAINT `groups_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group_messages`
--
ALTER TABLE `group_messages`
  ADD CONSTRAINT `group_messages_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rides`
--
ALTER TABLE `rides`
  ADD CONSTRAINT `rides_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ride_group_shares`
--
ALTER TABLE `ride_group_shares`
  ADD CONSTRAINT `ride_group_shares_ibfk_1` FOREIGN KEY (`ride_id`) REFERENCES `rides` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ride_group_shares_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ride_shares`
--
ALTER TABLE `ride_shares`
  ADD CONSTRAINT `ride_shares_ibfk_1` FOREIGN KEY (`ride_id`) REFERENCES `rides` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ride_shares_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ride_shares_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
