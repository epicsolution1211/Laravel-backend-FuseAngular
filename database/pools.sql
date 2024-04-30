-- phpMyAdmin SQL Dump
-- version 4.9.10
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 01, 2023 at 11:44 AM
-- Server version: 8.0.32-0ubuntu0.22.04.2
-- PHP Version: 7.3.33-9+ubuntu22.04.1+deb.sury.org+1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u_atavism_vps`
--

-- --------------------------------------------------------

--
-- Table structure for table `pools`
--

CREATE TABLE `pools` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `start_datetime` varchar(255) NOT NULL,
  `end_datetime` varchar(255) NOT NULL,
  `duration` varchar(255) NOT NULL,
  `status` int NOT NULL DEFAULT '0' COMMENT 'Active=1, Inactive=0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
);

--
-- Dumping data for table `pools`
--

INSERT INTO `pools` (`id`, `title`, `start_datetime`, `end_datetime`, `duration`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Pool for sms gateway', '1676347806', '1676383806', '600', 1, '2023-02-14 04:09:33', '2023-02-14 04:10:06'),
(2, 'For SMS and Email functionality get votes', '1676353594', '1676389594', '600', 1, '2023-02-14 05:46:27', '2023-02-14 05:46:34'),
(3, 'Push notification votes collection', '1676634570', '1676664570', '500', 1, '2023-02-17 11:49:12', '2023-02-17 11:49:30'),
(4, 'Test Feature', '1677048373', '1677084373', '600', 1, '2023-02-22 06:42:30', '2023-02-22 06:46:13'),
(5, 'Append Feature', '1677048373', '1677084373', '600', 1, '2023-02-22 06:42:30', '2023-02-22 06:46:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `pools`
--
ALTER TABLE `pools`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `pools`
--
ALTER TABLE `pools`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
