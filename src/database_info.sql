-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: [TIMESTAMP_REMOVED]
-- Server version: [VERSION_REMOVED]
-- PHP Version: [VERSION_REMOVED]

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `your_database_name`
--

-- --------------------------------------------------------

--
-- Table structure for table `models`
--

CREATE TABLE `models` (
  `id` int(11) NOT NULL COMMENT 'Unique model ID',
  `project_id` int(11) NOT NULL COMMENT 'Links to projects.id',
  `name` varchar(100) NOT NULL COMMENT 'Name of model',
  `file_path` varchar(255) NOT NULL COMMENT 'Path to the model file',
  `format` varchar(10) NOT NULL COMMENT 'Model format',
  `size_mb` float NOT NULL COMMENT 'File size im mb',
  `lat` double DEFAULT NULL,
  `lng` double DEFAULT NULL,
  `altitude_show` double DEFAULT NULL,
  `altitude_fact` double DEFAULT NULL,
  `tilt` double NOT NULL COMMENT 'Tilt angle (degrees)',
  `roll` double NOT NULL COMMENT 'Roll angle (degrees)',
  `scale` double NOT NULL COMMENT 'Scale factor',
  `uploaded_at` datetime NOT NULL COMMENT 'When uploaded',
  `marker_width` double DEFAULT 0.00007,
  `marker_length` double DEFAULT 0.00007,
  `marker_height` double DEFAULT 3.5,
  `processing_notes` text DEFAULT NULL COMMENT 'Notes about model processing (centering, positioning, etc.)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL COMMENT 'Project ID',
  `user_id` int(11) NOT NULL COMMENT 'References users.id',
  `name` varchar(100) NOT NULL COMMENT 'Project name',
  `description` text NOT NULL COMMENT 'Project description',
  `address` varchar(255) NOT NULL COMMENT 'Address',
  `city` varchar(100) NOT NULL COMMENT 'City',
  `country` varchar(100) NOT NULL COMMENT 'Country',
  `lat` double NOT NULL COMMENT 'Latitude',
  `lng` double NOT NULL COMMENT 'Longitude',
  `altitude` double NOT NULL COMMENT 'Camera altitude (m)',
  `camera_range` double NOT NULL COMMENT 'Camera distance (m)',
  `camera_tilt` double NOT NULL COMMENT 'Camera angle (degrees)',
  `camera_heading` decimal(10,6) DEFAULT 0.000000,
  `folder_path` varchar(255) NOT NULL COMMENT 'Path to the folder',
  `created_at` datetime NOT NULL,
  `active_model_id` int(11) DEFAULT NULL,
  `size` decimal(10,2) DEFAULT NULL,
  `size_unit` varchar(10) DEFAULT 'sq.m',
  `price` decimal(15,2) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'EUR',
  `floor` int(11) DEFAULT NULL,
  `bedrooms` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_videos`
--

CREATE TABLE `project_videos` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shared_pages`
--

CREATE TABLE `shared_pages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `shared_path` varchar(500) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `view_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `project_name` varchar(100) DEFAULT NULL COMMENT 'Project name',
  `description` text DEFAULT NULL COMMENT 'Project description',
  `address` varchar(255) DEFAULT NULL COMMENT 'Address',
  `city` varchar(100) DEFAULT NULL COMMENT 'City',
  `country` varchar(100) DEFAULT NULL COMMENT 'Country',
  `camera_lat` double DEFAULT NULL COMMENT 'Camera latitude',
  `camera_lng` double DEFAULT NULL COMMENT 'Camera longitude',
  `altitude` double DEFAULT NULL COMMENT 'Camera altitude (m)',
  `camera_range` double DEFAULT NULL COMMENT 'Camera distance (m)',
  `camera_tilt` double DEFAULT NULL COMMENT 'Camera angle (degrees)',
  `camera_heading` decimal(10,6) DEFAULT 0.000000,
  `folder_path` varchar(255) DEFAULT NULL COMMENT 'Path to the folder',
  `active_model_id` int(11) DEFAULT NULL,
  `size` decimal(10,2) DEFAULT NULL,
  `size_unit` varchar(10) DEFAULT 'sq.m',
  `price` decimal(15,2) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'EUR',
  `floor` int(11) DEFAULT NULL,
  `bedrooms` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL COMMENT 'Name of model',
  `file_path` varchar(255) DEFAULT NULL COMMENT 'Path to the model file',
  `format` varchar(10) DEFAULT NULL COMMENT 'Model format',
  `size_mb` float DEFAULT NULL COMMENT 'File size in mb',
  `lat` double DEFAULT NULL,
  `lng` double DEFAULT NULL,
  `altitude_show` double DEFAULT NULL,
  `altitude_fact` double DEFAULT NULL,
  `tilt` double DEFAULT NULL COMMENT 'Tilt angle (degrees)',
  `roll` double DEFAULT NULL COMMENT 'Roll angle (degrees)',
  `scale` double DEFAULT NULL COMMENT 'Scale factor',
  `uploaded_at` datetime DEFAULT NULL COMMENT 'When uploaded',
  `marker_width` double DEFAULT 0.00007,
  `marker_length` double DEFAULT 0.00007,
  `marker_height` double DEFAULT 3.5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `starts_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL,
  `plan_name` varchar(50) NOT NULL,
  `request_limit` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_available` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether this subscription plan is available for purchase (1=available, 0=hidden)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL COMMENT 'Internal user ID',
  `username` varchar(50) NOT NULL COMMENT 'For standard accounts',
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL COMMENT 'Password hash for authentication',
  `google_id` varchar(50) NOT NULL COMMENT 'External OAuth ID',
  `name` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL,
  `last_login` datetime NOT NULL,
  `subscription_type` varchar(50) DEFAULT 'Free',
  `subscription_expired` date DEFAULT NULL,
  `api_key` varchar(255) DEFAULT NULL COMMENT 'User API key',
  `requests_used` int(11) NOT NULL DEFAULT 0,
  `requests_total` int(11) NOT NULL DEFAULT 10,
  `requests_used_shown` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `viewpoints`
--

CREATE TABLE `viewpoints` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `lat` double NOT NULL,
  `lng` double NOT NULL,
  `altitude` double NOT NULL,
  `tilt` float NOT NULL,
  `heading` float NOT NULL,
  `range_value` float NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `models`
--
ALTER TABLE `models`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `Project-User ID` (`user_id`),
  ADD KEY `idx_projects_is_published` (`is_published`);

--
-- Indexes for table `project_videos`
--
ALTER TABLE `project_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_id` (`project_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `shared_pages`
--
ALTER TABLE `shared_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_project_share` (`user_id`,`project_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_project_id` (`project_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user` (`user_id`),
  ADD KEY `fk_plan` (`plan_id`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_api_key` (`api_key`);

--
-- Indexes for table `viewpoints`
--
ALTER TABLE `viewpoints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `models`
--
ALTER TABLE `models`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique model ID';

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Project ID';

--
-- AUTO_INCREMENT for table `project_videos`
--
ALTER TABLE `project_videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shared_pages`
--
ALTER TABLE `shared_pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Internal user ID';

--
-- AUTO_INCREMENT for table `viewpoints`
--
ALTER TABLE `viewpoints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `models`
--
ALTER TABLE `models`
  ADD CONSTRAINT `models_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `Project-User ID` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_videos`
--
ALTER TABLE `project_videos`
  ADD CONSTRAINT `project_videos_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_videos_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shared_pages`
--
ALTER TABLE `shared_pages`
  ADD CONSTRAINT `shared_pages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shared_pages_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `fk_plan` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `viewpoints`
--
ALTER TABLE `viewpoints`
  ADD CONSTRAINT `viewpoints_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
