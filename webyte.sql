-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 24, 2026 at 01:58 PM
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
-- Database: `webyte`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Present',
  `scanned_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `subject_id`, `status`, `scanned_at`) VALUES
(1, 2, 1, 'Present', '2026-09-20 13:09:26'),
(2, 2, 4, 'Present', '2026-09-20 13:19:33'),
(3, 2, 4, 'Present', '2026-09-22 21:46:44');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `student_number` varchar(30) NOT NULL,
  `email` varchar(100) NOT NULL,
  `scan_datetime` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `attendance_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `time_in` datetime NOT NULL,
  `status` enum('Present','Late','Absent') DEFAULT 'Present',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `thread_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_threads`
--

CREATE TABLE `chat_threads` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_message` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_read` tinyint(1) DEFAULT 1,
  `last_sender_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrolled_classes`
--

CREATE TABLE `enrolled_classes` (
  `enrollment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `date_enrolled` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `institutes`
--

CREATE TABLE `institutes` (
  `id` int(11) NOT NULL,
  `institute_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `institutes`
--

INSERT INTO `institutes` (`id`, `institute_name`) VALUES
(1, 'Institute of Computing and Digital Innovations'),
(2, 'Institute of Foundational Studies');

-- --------------------------------------------------------

--
-- Table structure for table `loginlogs`
--

CREATE TABLE `loginlogs` (
  `log_id` int(100) NOT NULL,
  `student_email` varchar(255) NOT NULL,
  `login_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `reply_to` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int(11) NOT NULL,
  `program_name` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`id`, `program_name`, `created_at`) VALUES
(1, 'Bachelor of Science in Information Systems', '2026-09-10 09:01:31'),
(2, 'Bachelor of Science in Civil Engineering', '2026-09-15 11:44:15'),
(5, 'Bachelor of Science in Social Work', '2026-09-15 11:44:55'),
(6, 'Bachelor of Science in Psychology', '2026-09-15 11:45:02'),
(7, 'Bachelor of Science in Secondary Education - Major in English', '2026-09-15 11:53:12');

-- --------------------------------------------------------

--
-- Table structure for table `qr_sessions`
--

CREATE TABLE `qr_sessions` (
  `id` int(11) NOT NULL,
  `qr_code_hash` varchar(255) NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_subjects`
--

CREATE TABLE `school_subjects` (
  `id` int(11) NOT NULL,
  `subject_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `section_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `program_id` int(11) NOT NULL,
  `year_level` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `section_name`, `created_at`, `program_id`, `year_level`) VALUES
(1, 'Bachelor of Science in Information Systems 201', '2026-09-10 09:02:14', 1, 'Second Year'),
(2, 'Bachelor of Science in Information Systems 202', '2026-09-10 09:02:22', 1, 'Second Year'),
(3, 'Bachelor of Science in Information Systems 203', '2026-09-10 09:02:28', 1, 'Second Year'),
(4, 'Bachelor of Science in Information Systems 204', '2026-09-10 09:02:35', 1, 'Second Year'),
(5, 'Bachelor of Science in Information Systems 205', '2026-09-10 09:02:42', 1, 'Second Year'),
(6, 'Bachelor of Science in Information Systems 101', '2026-09-15 11:45:29', 1, 'First Year'),
(7, 'Bachelor of Science in Information Systems 102', '2026-09-15 11:45:36', 1, 'First Year'),
(8, 'Bachelor of Science in Information Systems 103', '2026-09-15 11:45:54', 1, 'First Year'),
(9, 'Bachelor of Science in Information Systems 104', '2026-09-15 11:46:02', 1, 'First Year'),
(10, 'Bachelor of Science in Information Systems 105', '2026-09-15 11:46:19', 1, 'First Year'),
(11, 'Bachelor of Science in Information Systems 301', '2026-09-15 11:46:39', 1, 'Third Year'),
(12, 'Bachelor of Science in Information Systems 302', '2026-09-15 11:46:55', 1, 'Third Year'),
(13, 'Bachelor of Science in Information Systems 303', '2026-09-15 11:47:01', 1, 'Third Year'),
(14, 'Bachelor of Science in Information Systems 304', '2026-09-15 11:47:08', 1, 'Third Year'),
(15, 'Bachelor of Science in Information Systems 305', '2026-09-15 11:47:15', 1, 'Third Year'),
(16, 'Bachelor of Science in Information Systems 401', '2026-09-15 11:47:38', 1, 'Fourth Year'),
(17, 'Bachelor of Science in Information Systems 402', '2026-09-15 11:47:44', 1, 'Fourth Year'),
(18, 'Bachelor of Science in Information Systems 403', '2026-09-15 11:47:49', 1, 'Fourth Year'),
(19, 'Bachelor of Science in Information Systems 404', '2026-09-15 11:47:58', 1, 'Fourth Year'),
(20, 'Bachelor of Science in Information Systems 405', '2026-09-15 11:48:04', 1, 'Fourth Year'),
(21, 'Bachelor of Science in Civil Engineering 101', '2026-09-15 11:48:44', 2, 'First Year'),
(22, 'Bachelor of Science in Civil Engineering 102', '2026-09-15 11:48:50', 2, 'First Year'),
(23, 'Bachelor of Science in Civil Engineering 103', '2026-09-15 11:48:58', 2, 'First Year'),
(24, 'Bachelor of Science in Civil Engineering 104', '2026-09-15 11:49:06', 2, 'First Year'),
(25, 'Bachelor of Science in Civil Engineering 105', '2026-09-15 11:49:30', 2, 'First Year'),
(26, 'Bachelor of Science in Civil Engineering 201', '2026-09-15 11:49:42', 2, 'Second Year'),
(27, 'Bachelor of Science in Civil Engineering 202', '2026-09-15 11:50:02', 2, 'Second Year'),
(28, 'Bachelor of Science in Civil Engineering 203', '2026-09-15 11:50:20', 2, 'Second Year'),
(29, 'Bachelor of Science in Civil Engineering 204', '2026-09-15 11:50:29', 2, 'Second Year'),
(30, 'Bachelor of Science in Civil Engineering 205', '2026-09-15 11:50:38', 2, 'Second Year'),
(31, 'Bachelor of Science in Civil Engineering 301', '2026-09-15 11:50:56', 2, 'Third Year'),
(32, 'Bachelor of Science in Civil Engineering 302', '2026-09-15 11:51:04', 2, 'Third Year'),
(33, 'Bachelor of Science in Civil Engineering 303', '2026-09-15 11:51:09', 2, 'Third Year'),
(34, 'Bachelor of Science in Civil Engineering 304', '2026-09-15 11:51:15', 2, 'Third Year'),
(35, 'Bachelor of Science in Civil Engineering 305', '2026-09-15 11:51:21', 2, 'Third Year'),
(36, 'Bachelor of Science in Civil Engineering 401', '2026-09-15 11:51:43', 2, 'Fourth Year'),
(37, 'Bachelor of Science in Civil Engineering 402', '2026-09-15 11:51:59', 2, 'Fourth Year'),
(38, 'Bachelor of Science in Civil Engineering 403', '2026-09-15 11:52:05', 2, 'Fourth Year'),
(39, 'Bachelor of Science in Civil Engineering 404', '2026-09-15 11:52:11', 2, 'Fourth Year'),
(40, 'Bachelor of Science in Civil Engineering 405', '2026-09-15 11:52:16', 2, 'Fourth Year'),
(41, 'Bachelor of Science in Psychology 101', '2026-09-15 11:53:41', 6, 'First Year'),
(42, 'Bachelor of Science in Psychology 102', '2026-09-15 11:53:52', 6, 'First Year'),
(43, 'Bachelor of Science in Psychology 103', '2026-09-15 11:54:00', 6, 'First Year'),
(44, 'Bachelor of Science in Psychology 104', '2026-09-15 11:54:10', 6, 'First Year'),
(45, 'Bachelor of Science in Psychology 105', '2026-09-15 11:54:15', 6, 'First Year'),
(46, 'Bachelor of Science in Psychology 201', '2026-09-15 11:54:30', 6, 'Second Year'),
(47, 'Bachelor of Science in Psychology 202', '2026-09-15 11:54:36', 6, 'Second Year'),
(48, 'Bachelor of Science in Psychology 203', '2026-09-15 11:54:42', 6, 'Second Year'),
(49, 'Bachelor of Science in Psychology 204', '2026-09-15 11:54:49', 6, 'Second Year'),
(50, 'Bachelor of Science in Psychology 205', '2026-09-15 11:54:56', 6, 'Second Year'),
(51, 'Bachelor of Science in Psychology 301', '2026-09-15 11:55:08', 6, 'Third Year'),
(52, 'Bachelor of Science in Psychology 302', '2026-09-15 11:55:12', 6, 'Third Year'),
(53, 'Bachelor of Science in Psychology 303', '2026-09-15 11:55:16', 6, 'Third Year'),
(54, 'Bachelor of Science in Psychology 304', '2026-09-15 11:55:21', 6, 'Third Year'),
(55, 'Bachelor of Science in Psychology 305', '2026-09-15 11:55:25', 6, 'Third Year'),
(56, 'Bachelor of Science in Psychology 401', '2026-09-15 11:55:58', 6, 'Fourth Year'),
(57, 'Bachelor of Science in Psychology 402', '2026-09-15 11:56:01', 6, 'Fourth Year'),
(58, 'Bachelor of Science in Psychology 403', '2026-09-15 11:56:05', 6, 'Fourth Year'),
(59, 'Bachelor of Science in Psychology 404', '2026-09-15 11:56:08', 6, 'Fourth Year'),
(60, 'Bachelor of Science in Psychology 405', '2026-09-15 11:56:11', 6, 'Fourth Year'),
(61, 'Bachelor of Science in Secondary Education - Major in English 101', '2026-09-15 11:56:36', 7, 'First Year'),
(62, 'Bachelor of Science in Secondary Education - Major in English 102', '2026-09-15 11:56:40', 7, 'First Year'),
(63, 'Bachelor of Science in Secondary Education - Major in English 103', '2026-09-15 11:56:45', 7, 'First Year'),
(64, 'Bachelor of Science in Secondary Education - Major in English 104', '2026-09-15 11:56:48', 7, 'First Year'),
(65, 'Bachelor of Science in Secondary Education - Major in English 105', '2026-09-15 11:56:51', 7, 'First Year'),
(66, 'Bachelor of Science in Secondary Education - Major in English 201', '2026-09-15 11:57:11', 7, 'Second Year'),
(67, 'Bachelor of Science in Secondary Education - Major in English 202', '2026-09-15 11:57:14', 7, 'Second Year'),
(68, 'Bachelor of Science in Secondary Education - Major in English 203', '2026-09-15 11:57:20', 7, 'Second Year'),
(69, 'Bachelor of Science in Secondary Education - Major in English 204', '2026-09-15 11:57:23', 7, 'Second Year'),
(70, 'Bachelor of Science in Secondary Education - Major in English 205', '2026-09-15 11:57:26', 7, 'Second Year'),
(71, 'Bachelor of Science in Secondary Education - Major in English 301', '2026-09-15 11:57:48', 7, 'Third Year'),
(72, 'Bachelor of Science in Secondary Education - Major in English 302', '2026-09-15 11:57:52', 7, 'Third Year'),
(73, 'Bachelor of Science in Secondary Education - Major in English 303', '2026-09-15 11:57:54', 7, 'Third Year'),
(74, 'Bachelor of Science in Secondary Education - Major in English 304', '2026-09-15 11:57:57', 7, 'Third Year'),
(75, 'Bachelor of Science in Secondary Education - Major in English 305', '2026-09-15 11:58:00', 7, 'Third Year'),
(76, 'Bachelor of Science in Secondary Education - Major in English 401', '2026-09-15 11:58:20', 7, 'Fourth Year'),
(77, 'Bachelor of Science in Secondary Education - Major in English 402', '2026-09-15 11:58:24', 7, 'Fourth Year'),
(78, 'Bachelor of Science in Secondary Education - Major in English 403', '2026-09-15 11:58:27', 7, 'Fourth Year'),
(79, 'Bachelor of Science in Secondary Education - Major in English 404', '2026-09-15 11:58:30', 7, 'Fourth Year'),
(80, 'Bachelor of Science in Secondary Education - Major in English 405', '2026-09-15 11:58:33', 7, 'Fourth Year'),
(81, 'Bachelor of Science in Social Work 101', '2026-09-15 11:58:53', 5, 'First Year'),
(82, 'Bachelor of Science in Social Work 102', '2026-09-15 11:58:57', 5, 'First Year'),
(83, 'Bachelor of Science in Social Work 103', '2026-09-15 11:59:00', 5, 'First Year'),
(84, 'Bachelor of Science in Social Work 104', '2026-09-15 11:59:03', 5, 'First Year'),
(85, 'Bachelor of Science in Social Work 105', '2026-09-15 11:59:06', 5, 'First Year'),
(86, 'Bachelor of Science in Social Work 201', '2026-09-15 11:59:24', 5, 'Second Year'),
(87, 'Bachelor of Science in Social Work 202', '2026-09-15 11:59:27', 5, 'Second Year'),
(88, 'Bachelor of Science in Social Work 203', '2026-09-15 11:59:30', 5, 'Second Year'),
(89, 'Bachelor of Science in Social Work 204', '2026-09-15 11:59:33', 5, 'Second Year'),
(90, 'Bachelor of Science in Social Work 205', '2026-09-15 11:59:37', 5, 'Second Year'),
(91, 'Bachelor of Science in Social Work 301', '2026-09-15 11:59:53', 5, 'Third Year'),
(92, 'Bachelor of Science in Social Work 302', '2026-09-15 11:59:57', 5, 'Third Year'),
(93, 'Bachelor of Science in Social Work 303', '2026-09-15 12:00:00', 5, 'Third Year'),
(94, 'Bachelor of Science in Social Work 304', '2026-09-15 12:00:03', 5, 'Third Year'),
(95, 'Bachelor of Science in Social Work 305', '2026-09-15 12:00:06', 5, 'Third Year'),
(96, 'Bachelor of Science in Social Work 401', '2026-09-15 12:00:33', 5, 'Fourth Year'),
(97, 'Bachelor of Science in Social Work 402', '2026-09-15 12:00:37', 5, 'Fourth Year'),
(98, 'Bachelor of Science in Social Work 403', '2026-09-15 12:00:40', 5, 'Fourth Year'),
(99, 'Bachelor of Science in Social Work 404', '2026-09-15 12:00:43', 5, 'Fourth Year'),
(100, 'Bachelor of Science in Social Work 405', '2026-09-15 12:00:48', 5, 'Fourth Year');

-- --------------------------------------------------------

--
-- Table structure for table `student_enrollments`
--

CREATE TABLE `student_enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `section` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_subjects`
--

CREATE TABLE `student_subjects` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course` varchar(255) NOT NULL,
  `instructor_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_loads`
--

CREATE TABLE `teacher_loads` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `section_name` varchar(100) NOT NULL,
  `subject_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_loads`
--

INSERT INTO `teacher_loads` (`id`, `teacher_id`, `section_name`, `subject_name`) VALUES
(4, 4, 'Bachelor of Science in Information Systems 204', 'Data Structures and Algorithm');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_subjects`
--

CREATE TABLE `teacher_subjects` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject_name` varchar(255) NOT NULL,
  `section_name` varchar(255) DEFAULT NULL,
  `class_code` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_subjects`
--

INSERT INTO `teacher_subjects` (`id`, `teacher_id`, `subject_name`, `section_name`, `class_code`) VALUES
(6, 4, 'Data Structures and Algorithm', 'Bachelor of Science in Information Systems 204', 'F0BFAD');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `reset_code` varchar(10) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `role` varchar(50) NOT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `section` varchar(100) DEFAULT NULL,
  `institute` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `middle_name`, `last_name`, `id_number`, `email`, `password`, `reset_code`, `reset_expires`, `role`, `profile_pic`, `created_at`, `section`, `institute`, `position`) VALUES
(1, 'Hanz Darwin', 'R.', 'San Jose', '2025-3-000002', 'hdsanjosea@kld.edu.ph', '$2y$10$FqjkhuygMzmWmyI9UgiTdO0X0daxOnhKv2JRrxim9yGKIwjwkqD/m', NULL, NULL, 'admin', 'kld-logo.png', '2026-09-10 09:00:20', NULL, NULL, NULL),
(2, 'Hanz Darwin', 'R.', 'San Jose', '2025-1-000006', 'hdsanjoses@kld.edu.ph', '$2y$10$uosaYw03oX5ZEG3HVRwfFe2hzZffkgyfq9fdRILIu3.pnjv79XAiS', NULL, NULL, 'student', 'kld-logo.png', '2026-09-10 09:03:39', 'Bachelor of Science in Information Systems 204', NULL, NULL),
(4, 'Hanz Darwin', 'Rellora', 'San Jose', '2025-2-000002', 'hdsanjosef@kld.edu.ph', '$2y$10$yly8sRsx0X.auO7ZUY7A7u2m4Pcok.zrSpbeT3cWrwni9fvXaLcxO', NULL, NULL, 'faculty', 'kld-logo.png', '2026-09-13 00:03:07', NULL, 'Institute of Foundational Studies', 'Institute Associate Dean'),
(6, 'mark lourence', 'tante', 'patlonag', '025-2-000231', 'mlpatlonag@kld.edu.ph', '$2y$10$dpqS0/5P5LSt0AOB/qJLRugw3Lx2H3zRoVpyeSPbSM.r3GUUqncOy', NULL, NULL, 'student', 'kld-logo.png', '2026-09-22 01:52:05', 'Bachelor of Science in Psychology 202', NULL, NULL),
(7, 'Jan Micah', 'Valenciano', 'Molines', '2025-2-000208', 'jmmolines@kld.edu.ph', '$2y$10$BmGUSOomVbYBNAWi7T71yudzMj6PtfRRVNaVGeZbSBV9VLM5l033e', NULL, NULL, 'student', 'kld-logo.png', '2026-09-22 02:50:05', 'Bachelor of Science in Information Systems 204', NULL, NULL),
(8, 'h6', '5', '4', 'mgaak', 'efnjgkae@kld.edu.ph', '$2y$10$w5jaXfbmJZ.y0.RsrsX4F.MRjPBAXG0ogMF9195fZP4ySmEo7BFIK', NULL, NULL, 'student', 'kld-logo.png', '2026-09-22 03:02:58', 'Bachelor of Science in Civil Engineering 204', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `unique_daily_attendance` (`student_id`,`class_id`,`date`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_threads`
--
ALTER TABLE `chat_threads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_thread` (`admin_id`,`user_id`);

--
-- Indexes for table `enrolled_classes`
--
ALTER TABLE `enrolled_classes`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD UNIQUE KEY `unique_student_class` (`student_id`,`class_id`);

--
-- Indexes for table `institutes`
--
ALTER TABLE `institutes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `loginlogs`
--
ALTER TABLE `loginlogs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `qr_sessions`
--
ALTER TABLE `qr_sessions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `school_subjects`
--
ALTER TABLE `school_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subject_name` (`subject_name`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_id` (`program_id`);

--
-- Indexes for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `fk_student_enrollments_subject` (`subject_id`);

--
-- Indexes for table `student_subjects`
--
ALTER TABLE `student_subjects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `teacher_loads`
--
ALTER TABLE `teacher_loads`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `class_code` (`class_code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_threads`
--
ALTER TABLE `chat_threads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrolled_classes`
--
ALTER TABLE `enrolled_classes`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `institutes`
--
ALTER TABLE `institutes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `loginlogs`
--
ALTER TABLE `loginlogs`
  MODIFY `log_id` int(100) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `qr_sessions`
--
ALTER TABLE `qr_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_subjects`
--
ALTER TABLE `school_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `student_subjects`
--
ALTER TABLE `student_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teacher_loads`
--
ALTER TABLE `teacher_loads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD CONSTRAINT `attendance_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  ADD CONSTRAINT `fk_student_enrollments_subject` FOREIGN KEY (`subject_id`) REFERENCES `teacher_subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
