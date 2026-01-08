-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 08, 2026 at 06:06 AM
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
-- Database: `queue_document_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'ชื่อแผนก/ฝ่าย',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `created_at`) VALUES
(1, 'ผู้ดูแลระบบ', '2025-12-15 18:12:35'),
(2, 'ธุรการสำนักปลัด', '2025-12-15 18:12:35'),
(3, 'เจ้าหน้าที่รับหนังสือของกอง', '2025-12-15 18:12:35'),
(4, 'หัวหน้าสำนักปลัด', '2025-12-15 18:12:35'),
(5, 'ปลัด', '2025-12-15 18:12:35'),
(6, 'นายก', '2025-12-16 09:16:51'),
(22, 'กองคลัง', '2025-12-19 02:55:13'),
(23, 'กองการศึกษา', '2025-12-19 02:55:23'),
(24, 'กองช่าง', '2025-12-19 02:55:35'),
(26, 'สาธารณสุข', '2025-12-19 04:26:59'),
(27, 'นิติกร', '2025-12-19 04:27:03'),
(28, 'นักวิเคราะห์', '2025-12-19 04:27:29'),
(29, 'หัวหน้าฝ่ายฯ', '2025-12-19 04:27:35'),
(31, 'นักทรัพยากรบุคคล', '2025-12-19 04:27:49'),
(32, 'ปภ.', '2025-12-19 04:49:08');

-- --------------------------------------------------------

--
-- Table structure for table `department_permissions`
--

CREATE TABLE `department_permissions` (
  `department_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department_permissions`
--

INSERT INTO `department_permissions` (`department_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 6),
(1, 7),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(2, 2),
(2, 9),
(2, 10),
(2, 11),
(2, 12),
(2, 13),
(2, 14),
(4, 1),
(4, 2),
(4, 3),
(4, 4),
(4, 6),
(4, 7),
(4, 9),
(4, 10),
(4, 11),
(4, 12),
(4, 13),
(4, 14),
(5, 2),
(5, 10),
(5, 11),
(5, 12),
(5, 13),
(5, 14),
(6, 2),
(6, 10),
(6, 11),
(6, 12),
(6, 13),
(6, 14),
(22, 10),
(22, 12),
(22, 14),
(23, 10),
(23, 12),
(24, 10),
(24, 12),
(26, 10),
(26, 12),
(27, 10),
(27, 12),
(28, 10),
(28, 12),
(29, 10),
(29, 12),
(31, 10),
(31, 12),
(32, 10),
(32, 12);

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `document_no` varchar(50) NOT NULL COMMENT 'เลขที่เอกสารรันต่อเนื่อง',
  `external_no` varchar(50) DEFAULT NULL COMMENT 'เลขที่หนังสือ (จากภายนอก)',
  `title` varchar(255) NOT NULL COMMENT 'เรื่อง/หัวข้อเอกสาร',
  `book_name` varchar(255) DEFAULT NULL COMMENT 'ชื่อหนังสือ',
  `receive_date` date NOT NULL DEFAULT curdate() COMMENT 'วันที่รับ',
  `sender_id` int(11) DEFAULT NULL COMMENT 'ID ผู้ส่ง (เชื่อมตาราง users)',
  `to_department_id` int(11) DEFAULT NULL COMMENT 'ID แผนกปลายทาง (เชื่อมตาราง departments)',
  `pending_target_depts` text DEFAULT NULL COMMENT 'เก็บ ID แผนกปลายทางแบบ CSV สำหรับหนังสือเกษียณ',
  `document_type_id` int(11) NOT NULL COMMENT 'ประเภทเอกสาร',
  `from_source` varchar(100) DEFAULT NULL COMMENT 'รับจากหน่วยงาน/บุคคล',
  `status` enum('pending','process','success','cancel','draft') DEFAULT 'pending',
  `priority` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=ปกติ, 1=ด่วน, 2=ด่วนที่สุด',
  `remark` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL COMMENT 'ชื่อไฟล์แนบ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_forwarded` tinyint(1) DEFAULT 0 COMMENT '0=รอส่ง, 1=ส่งแล้ว',
  `parent_id` int(11) DEFAULT NULL COMMENT 'เก็บ ID เอกสารต้นทาง'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_attachments`
--

CREATE TABLE `document_attachments` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL COMMENT 'Path ไฟล์ใน Server',
  `file_type` varchar(10) DEFAULT 'pdf',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_logs`
--

CREATE TABLE `document_logs` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL COMMENT 'เช่น create, print, stamp',
  `actor_id` int(11) NOT NULL COMMENT 'ผู้กระทำ',
  `details` text DEFAULT NULL COMMENT 'รายละเอียดเพิ่มเติม',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_types`
--

CREATE TABLE `document_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL COMMENT 'ชื่อประเภทเอกสาร',
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_types`
--

INSERT INTO `document_types` (`id`, `type_name`, `is_active`) VALUES
(1, 'หนังสือเกษียณ', 1),
(2, 'เอกสารทั่วไป', 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'ผู้รับแจ้งเตือน',
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL COMMENT 'ลิงก์ไปหน้าเอกสาร',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `slug` varchar(50) NOT NULL COMMENT 'ชื่อสิทธิ์ภาษาอังกฤษ เช่น queue.create',
  `name` varchar(100) NOT NULL COMMENT 'ชื่อสิทธิ์ภาษาไทย'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `slug`, `name`) VALUES
(1, 'dept_add', 'เพิ่มแผนก'),
(2, 'doc_send', 'เมนูส่งเอกสาร'),
(3, 'user_view', 'เมนูจัดการผู้ใช้งาน'),
(4, 'dept_view', 'เมนูจัดการแผนก'),
(6, 'permission_view', 'เมนูจัดการสิทธิ์'),
(7, 'type_doc_view', 'เมนูประเภทเอกสาร'),
(9, 'admin_dashboard', 'เมนูแดชบอร์ด'),
(10, 'income', 'เมนูเอกสารรับเข้า'),
(11, 'send', 'เมนูส่งเอกสาร'),
(12, 'noti', 'เมนูการแจ้งเตือน'),
(13, 'forward', 'เมนูหน้าส่งต่อ'),
(14, 'search_doc', 'เมนูหน้าค้นหาเอกสาร');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Admin', NULL),
(2, 'user', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `config_key` varchar(50) NOT NULL,
  `config_value` text NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `department_id` int(11) NOT NULL,
  `signature_img_path` varchar(255) DEFAULT NULL COMMENT 'เก็บ Path รูปไฟล์ลายเซ็น (รองรับอนาคต)',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '1=ใช้งาน, 0=ระงับ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `email`, `first_name`, `last_name`, `department_id`, `signature_img_path`, `is_active`, `created_at`) VALUES
(1, 'admin', '$2y$10$jxeNa3IVCXcRJV20X9MwoOutTyVnd4gPTJ1STDxwKkh0Ts.PBSzfG', '', 'Admin', 'SystemTest', 1, NULL, 1, '2025-12-15 18:13:48'),
(21, 'user1', '$2y$10$/Ic5zh5SoeUOATEGorz3iuJGpXFp59ogpP0DAeyqYmlgNmiD24/J.', '', 'หัวหน้าสำนักปลัด', 'ทดสอบ', 4, NULL, 1, '2025-12-21 16:49:12'),
(22, 'user2', '$2y$10$/chRND44z7A.T1LNhbWSYOJj28Q9JnNziaWZu3.Fn31Iy/XbJC0.2', '', 'ปลัด', 'ทดสอบ', 5, NULL, 1, '2025-12-21 16:49:45'),
(23, 'user3', '$2y$10$b15jImjP/GbNeXCoLBzZbeePykH52rSxU1cu/q1QGBUuuH/33m8mC', '', 'นายก', 'นายก', 6, NULL, 1, '2025-12-21 16:50:13'),
(24, 'user4', '$2y$10$6XJkjBecm6cp9VsFrfa.lePqShq/NQmBdwHyGlxRb4lM4v5xMC/RC', '', 'ธุรการสำนักปลัด', 'ทดสอบ', 2, NULL, 1, '2025-12-21 16:50:39'),
(25, 'user5', '$2y$10$a8y.EykGGnS8mmyThcfGa.OXtOZYIQCbA3ue7ilBuH27wncN.Tfbe', '', 'กองคลัง', 'ทดสอบ', 22, NULL, 1, '2025-12-21 16:51:06'),
(26, 'user6', '$2y$10$fa0dj3dhVV1Xn/boEcQSeOoZwwKKS.8UFsEmYzXgIYCQbhBXMZkJ2', '', 'สาธารณะสุข', 'ทดสอบ', 26, NULL, 1, '2025-12-21 16:51:35');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 1),
(21, 2),
(22, 2),
(23, 2),
(24, 2),
(25, 2),
(26, 2);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `department_permissions`
--
ALTER TABLE `department_permissions`
  ADD PRIMARY KEY (`department_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `document_no` (`document_no`),
  ADD KEY `documents_ibfk_type` (`document_type_id`);

--
-- Indexes for table `document_attachments`
--
ALTER TABLE `document_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`);

--
-- Indexes for table `document_logs`
--
ALTER TABLE `document_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `actor_id` (`actor_id`);

--
-- Indexes for table `document_types`
--
ALTER TABLE `document_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `document_attachments`
--
ALTER TABLE `document_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `document_logs`
--
ALTER TABLE `document_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_types`
--
ALTER TABLE `document_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=146;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `department_permissions`
--
ALTER TABLE `department_permissions`
  ADD CONSTRAINT `dept_perm_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dept_perm_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_type` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`);

--
-- Constraints for table `document_attachments`
--
ALTER TABLE `document_attachments`
  ADD CONSTRAINT `document_attachments_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_logs`
--
ALTER TABLE `document_logs`
  ADD CONSTRAINT `document_logs_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_logs_ibfk_2` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notif_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
