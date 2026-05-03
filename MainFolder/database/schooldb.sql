-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 12, 2026 at 04:44 PM
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
-- Database: `schooldb`
--

-- --------------------------------------------------------

--
-- Table structure for table `alerts`
--

CREATE TABLE `alerts` (
  `alert_id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `emergency_type` varchar(100) NOT NULL,
  `alert_text` text DEFAULT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `location_description` varchar(255) DEFAULT NULL,
  `alert_status` enum('PENDING','DISPATCHED','ACCEPTED','IN_PROGRESS','RESOLVED','CANCELLED') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alert_dispatches`
--

CREATE TABLE `alert_dispatches` (
  `dispatch_id` char(36) NOT NULL,
  `alert_id` char(36) NOT NULL,
  `security_id` char(36) NOT NULL,
  `dispatch_status` enum('NOTIFIED','VIEWED','ACCEPTED','ARRIVED','COMPLETED','DECLINED') NOT NULL,
  `notified_at` datetime DEFAULT NULL,
  `viewed_at` datetime DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `arrived_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `alert_messages`
--

CREATE TABLE `alert_messages` (
  `message_id` char(36) NOT NULL,
  `alert_id` char(36) NOT NULL,
  `sender_user_id` char(36) DEFAULT NULL,
  `sender_security_id` char(36) DEFAULT NULL,
  `message_text` text NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `incident_records`
--

CREATE TABLE `incident_records` (
  `record_id` char(36) NOT NULL,
  `alert_id` char(36) NOT NULL,
  `handled_by_security_id` char(36) NOT NULL,
  `outcome` enum('RESOLVED','FALSE_ALARM','ESCALATED','CANCELLED') NOT NULL,
  `resolution_notes` text DEFAULT NULL,
  `closed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `security_personnel`
--

CREATE TABLE `security_personnel` (
  `security_id` char(36) NOT NULL,
  `staff_id` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `duty_status` enum('ON_DUTY','OFF_DUTY','INACTIVE') NOT NULL,
  `current_latitude` decimal(10,8) DEFAULT NULL,
  `current_longitude` decimal(11,8) DEFAULT NULL,
  `last_location_update` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `university_users`
--

CREATE TABLE `university_users` (
  `user_id` char(36) NOT NULL,
  `university_id` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alerts`
--
ALTER TABLE `alerts`
  ADD PRIMARY KEY (`alert_id`),
  ADD KEY `idx_alerts_user_id` (`user_id`),
  ADD KEY `idx_alerts_status` (`alert_status`);

--
-- Indexes for table `alert_dispatches`
--
ALTER TABLE `alert_dispatches`
  ADD PRIMARY KEY (`dispatch_id`),
  ADD KEY `idx_dispatches_alert_id` (`alert_id`),
  ADD KEY `idx_dispatches_security_id` (`security_id`);

--
-- Indexes for table `alert_messages`
--
ALTER TABLE `alert_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `fk_message_user` (`sender_user_id`),
  ADD KEY `fk_message_security` (`sender_security_id`),
  ADD KEY `idx_messages_alert_id` (`alert_id`);

--
-- Indexes for table `incident_records`
--
ALTER TABLE `incident_records`
  ADD PRIMARY KEY (`record_id`),
  ADD UNIQUE KEY `alert_id` (`alert_id`),
  ADD KEY `idx_records_security_id` (`handled_by_security_id`);

--
-- Indexes for table `security_personnel`
--
ALTER TABLE `security_personnel`
  ADD PRIMARY KEY (`security_id`),
  ADD UNIQUE KEY `staff_id` (`staff_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `university_users`
--
ALTER TABLE `university_users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `university_id` (`university_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alerts`
--
ALTER TABLE `alerts`
  ADD CONSTRAINT `fk_alert_user` FOREIGN KEY (`user_id`) REFERENCES `university_users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `alert_dispatches`
--
ALTER TABLE `alert_dispatches`
  ADD CONSTRAINT `fk_dispatch_alert` FOREIGN KEY (`alert_id`) REFERENCES `alerts` (`alert_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dispatch_security` FOREIGN KEY (`security_id`) REFERENCES `security_personnel` (`security_id`) ON DELETE CASCADE;

--
-- Constraints for table `alert_messages`
--
ALTER TABLE `alert_messages`
  ADD CONSTRAINT `fk_message_alert` FOREIGN KEY (`alert_id`) REFERENCES `alerts` (`alert_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_message_security` FOREIGN KEY (`sender_security_id`) REFERENCES `security_personnel` (`security_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_message_user` FOREIGN KEY (`sender_user_id`) REFERENCES `university_users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `incident_records`
--
ALTER TABLE `incident_records`
  ADD CONSTRAINT `fk_record_alert` FOREIGN KEY (`alert_id`) REFERENCES `alerts` (`alert_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_record_security` FOREIGN KEY (`handled_by_security_id`) REFERENCES `security_personnel` (`security_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
