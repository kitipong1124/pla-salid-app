-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 19, 2025 at 11:30 AM
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
-- Database: `fish_farming_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `feeding_records`
--

CREATE TABLE `feeding_records` (
  `id` int(11) NOT NULL,
  `pond_id` int(11) NOT NULL,
  `feed_date` date NOT NULL,
  `feed_type` varchar(100) NOT NULL,
  `feed_amount_sacks` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feeding_records`
--

INSERT INTO `feeding_records` (`id`, `pond_id`, `feed_date`, `feed_type`, `feed_amount_sacks`) VALUES
(7, 2, '2025-06-12', 'เบทาโกร 811', 10.5),
(8, 1, '2025-06-12', 'เบทาโกร 811', 15),
(9, 2, '2025-06-13', 'เบทาโกร 812', 9),
(10, 1, '2025-06-13', 'เบทาโกร 811', 13),
(11, 2, '2025-06-14', 'เบทาโกร 811', 11),
(12, 1, '2025-06-14', 'เบทาโกร 811', 17),
(13, 2, '2025-06-15', 'เบทาโกร 811', 14),
(14, 1, '2025-06-15', 'เบทาโกร 811', 16),
(15, 2, '2025-06-16', 'เบทาโกร 811', 22),
(16, 1, '2025-06-16', 'เบทาโกร 811', 13),
(17, 2, '2025-06-17', 'เบทาโกร 811', 14),
(18, 1, '2025-06-17', 'เบทาโกร 811', 20);

-- --------------------------------------------------------

--
-- Table structure for table `fish_releases`
--

CREATE TABLE `fish_releases` (
  `id` int(11) NOT NULL,
  `pond_id` int(11) NOT NULL,
  `release_date` date NOT NULL,
  `fish_amount` int(11) NOT NULL,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fish_releases`
--

INSERT INTO `fish_releases` (`id`, `pond_id`, `release_date`, `fish_amount`, `total_cost`, `created_at`) VALUES
(1, 1, '2025-06-17', 100000, 12000.00, '2025-06-17 09:20:55'),
(2, 2, '2024-06-01', 150000, 21000.00, '2025-06-17 11:14:23'),
(4, 3, '2025-05-04', 10000, 3000.00, '2025-06-19 06:24:14');

-- --------------------------------------------------------

--
-- Table structure for table `fish_sizes`
--

CREATE TABLE `fish_sizes` (
  `id` int(11) NOT NULL,
  `pond_id` int(11) NOT NULL,
  `record_date` date NOT NULL,
  `fish_count` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fish_sizes`
--

INSERT INTO `fish_sizes` (`id`, `pond_id`, `record_date`, `fish_count`) VALUES
(1, 1, '2025-01-01', 52),
(2, 1, '2025-02-01', 35.1),
(3, 1, '2025-03-01', 24.5),
(4, 1, '2025-04-01', 18.4),
(5, 1, '2025-05-01', 13.4),
(6, 1, '2025-06-01', 10.1),
(7, 1, '2025-07-01', 8.2),
(8, 1, '2025-08-01', 6.3),
(9, 1, '2025-09-01', 5.8),
(10, 1, '2025-10-01', 4.4),
(11, 2, '2025-01-01', 47),
(12, 2, '2025-02-01', 30),
(13, 2, '2025-03-01', 23),
(14, 2, '2025-04-01', 18),
(15, 2, '2025-05-01', 13),
(16, 2, '2025-06-01', 10),
(17, 2, '2025-07-01', 8),
(18, 2, '2025-08-01', 6),
(19, 2, '2025-09-01', 5),
(20, 2, '2025-10-01', 4),
(21, 1, '2025-06-18', 9.8),
(22, 1, '2025-11-18', 3.8);

-- --------------------------------------------------------

--
-- Table structure for table `ponds`
--

CREATE TABLE `ponds` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `size_rai` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ponds`
--

INSERT INTO `ponds` (`id`, `name`, `size_rai`) VALUES
(1, 'บ่ออนามัย', 20),
(2, 'บ่อพรี่บูม', 100),
(3, 'ฺBor_Christina', 57);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','owner') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(3, 'kitipong', '$2y$10$hGn0nLr/V/hBJroXCcpJlONCYP7dSJlS3Hpc5PJIG2q10TDDj/Kwe', 'owner'),
(4, 'admin', '$2y$10$OWKGKP1bf4Ah6qr9ZvGfs.1M4jIgYgjLAYoEk/5sVNU0qez9ZN0BW', 'admin'),
(5, 'bambam', '$2y$10$/pmZ1GpCAYpmx0zwccutIufdK.7xoN/kpFRijCaeaziiYF0C6zrb2', 'owner');

-- --------------------------------------------------------

--
-- Table structure for table `user_ponds`
--

CREATE TABLE `user_ponds` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `pond_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_ponds`
--

INSERT INTO `user_ponds` (`id`, `user_id`, `pond_id`) VALUES
(2, 3, 1),
(3, 5, 3);

-- --------------------------------------------------------

--
-- Table structure for table `water_quality`
--

CREATE TABLE `water_quality` (
  `id` int(11) NOT NULL,
  `pond_id` int(11) NOT NULL,
  `check_date` date NOT NULL,
  `ph` float NOT NULL,
  `ammonium` float NOT NULL,
  `nitrite` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `water_quality`
--

INSERT INTO `water_quality` (`id`, `pond_id`, `check_date`, `ph`, `ammonium`, `nitrite`) VALUES
(1, 1, '2025-06-16', 7.5, 0.25, 0.1),
(2, 1, '2025-06-17', 9, 1, 5),
(3, 2, '2025-06-18', 7.5, 0, 0),
(4, 3, '2025-06-18', 13, 3, 1),
(5, 1, '2025-06-19', 8, 1.5, 1),
(6, 1, '2025-06-18', 7, 0.5, 0.25);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `feeding_records`
--
ALTER TABLE `feeding_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pond_id` (`pond_id`);

--
-- Indexes for table `fish_releases`
--
ALTER TABLE `fish_releases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pond_id` (`pond_id`);

--
-- Indexes for table `fish_sizes`
--
ALTER TABLE `fish_sizes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pond_id` (`pond_id`);

--
-- Indexes for table `ponds`
--
ALTER TABLE `ponds`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_ponds`
--
ALTER TABLE `user_ponds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `pond_id` (`pond_id`);

--
-- Indexes for table `water_quality`
--
ALTER TABLE `water_quality`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pond_id` (`pond_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `feeding_records`
--
ALTER TABLE `feeding_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `fish_releases`
--
ALTER TABLE `fish_releases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `fish_sizes`
--
ALTER TABLE `fish_sizes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `ponds`
--
ALTER TABLE `ponds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_ponds`
--
ALTER TABLE `user_ponds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `water_quality`
--
ALTER TABLE `water_quality`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `feeding_records`
--
ALTER TABLE `feeding_records`
  ADD CONSTRAINT `feeding_records_ibfk_1` FOREIGN KEY (`pond_id`) REFERENCES `ponds` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fish_releases`
--
ALTER TABLE `fish_releases`
  ADD CONSTRAINT `fish_releases_ibfk_1` FOREIGN KEY (`pond_id`) REFERENCES `ponds` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fish_sizes`
--
ALTER TABLE `fish_sizes`
  ADD CONSTRAINT `fish_sizes_ibfk_1` FOREIGN KEY (`pond_id`) REFERENCES `ponds` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_ponds`
--
ALTER TABLE `user_ponds`
  ADD CONSTRAINT `user_ponds_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_ponds_ibfk_2` FOREIGN KEY (`pond_id`) REFERENCES `ponds` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `water_quality`
--
ALTER TABLE `water_quality`
  ADD CONSTRAINT `water_quality_ibfk_1` FOREIGN KEY (`pond_id`) REFERENCES `ponds` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
