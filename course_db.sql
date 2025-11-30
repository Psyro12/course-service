-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 30, 2025 at 04:25 PM
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
-- Database: `course_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_courses`
--

CREATE TABLE `tbl_courses` (
  `course_id` int(11) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `units` int(11) NOT NULL,
  `departments` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_courses`
--

INSERT INTO `tbl_courses` (`course_id`, `course_name`, `units`, `departments`, `created_at`) VALUES
(1, 'Intro to Programming', 3, 'Computer Science', '2025-11-30 13:39:40'),
(2, 'Data Structures', 3, 'Computer Science', '2025-11-30 13:39:40'),
(3, 'Algorithms', 4, 'Computer Science', '2025-11-30 13:39:40'),
(4, 'Calculus I', 5, 'Mathematics', '2025-11-30 13:39:40');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_courses_prerequisites`
--

CREATE TABLE `tbl_courses_prerequisites` (
  `course_id` int(11) NOT NULL,
  `prerequisite_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_courses_prerequisites`
--

INSERT INTO `tbl_courses_prerequisites` (`course_id`, `prerequisite_id`) VALUES
(2, 1),
(3, 2);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_student_schedules`
--

CREATE TABLE `tbl_student_schedules` (
  `student_schedule_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `schedule_time` time NOT NULL,
  `schedule_day` varchar(20) DEFAULT NULL,
  `schedule_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_student_schedules`
--

INSERT INTO `tbl_student_schedules` (`student_schedule_id`, `course_id`, `schedule_time`, `schedule_day`, `schedule_date`) VALUES
(1, 1, '08:00:00', 'Monday', '2023-10-01'),
(2, 2, '10:00:00', 'Wednesday', '2023-10-03');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_teacher_schedules`
--

CREATE TABLE `tbl_teacher_schedules` (
  `teacher_schedule_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `schedule_time` time NOT NULL,
  `schedule_day` varchar(20) DEFAULT NULL,
  `schedule_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_teacher_schedules`
--

INSERT INTO `tbl_teacher_schedules` (`teacher_schedule_id`, `course_id`, `schedule_time`, `schedule_day`, `schedule_date`) VALUES
(1, 1, '08:00:00', 'Monday', '2023-10-01'),
(2, 3, '13:00:00', 'Friday', '2023-10-05');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_courses`
--
ALTER TABLE `tbl_courses`
  ADD PRIMARY KEY (`course_id`);

--
-- Indexes for table `tbl_courses_prerequisites`
--
ALTER TABLE `tbl_courses_prerequisites`
  ADD PRIMARY KEY (`course_id`,`prerequisite_id`),
  ADD KEY `prerequisite_id` (`prerequisite_id`);

--
-- Indexes for table `tbl_student_schedules`
--
ALTER TABLE `tbl_student_schedules`
  ADD PRIMARY KEY (`student_schedule_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `tbl_teacher_schedules`
--
ALTER TABLE `tbl_teacher_schedules`
  ADD PRIMARY KEY (`teacher_schedule_id`),
  ADD KEY `course_id` (`course_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_courses`
--
ALTER TABLE `tbl_courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_student_schedules`
--
ALTER TABLE `tbl_student_schedules`
  MODIFY `student_schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_teacher_schedules`
--
ALTER TABLE `tbl_teacher_schedules`
  MODIFY `teacher_schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_courses_prerequisites`
--
ALTER TABLE `tbl_courses_prerequisites`
  ADD CONSTRAINT `tbl_courses_prerequisites_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `tbl_courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_courses_prerequisites_ibfk_2` FOREIGN KEY (`prerequisite_id`) REFERENCES `tbl_courses` (`course_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_student_schedules`
--
ALTER TABLE `tbl_student_schedules`
  ADD CONSTRAINT `tbl_student_schedules_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `tbl_courses` (`course_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_teacher_schedules`
--
ALTER TABLE `tbl_teacher_schedules`
  ADD CONSTRAINT `tbl_teacher_schedules_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `tbl_courses` (`course_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
