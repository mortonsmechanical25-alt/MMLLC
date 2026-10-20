-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 20, 2026 at 03:20 PM
-- Server version: 10.6.23-MariaDB-0ubuntu0.22.04.1
-- PHP Version: 8.4.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `deskpri`
--

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `identity` char(64) NOT NULL,
  `attempts` int(11) NOT NULL,
  `window_start` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `records`
--

CREATE TABLE `records` (
  `owner` int(10) UNSIGNED NOT NULL,
  `id` varchar(100) NOT NULL,
  `kind` varchar(20) NOT NULL,
  `data` longtext NOT NULL,
  `updated` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Dumping data for table `records`
--

INSERT INTO `records` (`owner`, `id`, `kind`, `data`, `updated`) VALUES
(1, '052df8e8-d578-4abb-9ad5-0e91bc5ff798', 'customer', '{\"id\":\"052df8e8-d578-4abb-9ad5-0e91bc5ff798\",\"kind\":\"customer\",\"customer\":\"Brandon Wolinski\",\"phone\":\"(603) 229-7008\",\"email\":\"\",\"vehicle\":\"2016 Ford F-250 Super Duty\",\"identifier\":\"SKISMEC\",\"issue\":\"\",\"notes\":\"\",\"status\":\"Draft\",\"start\":\"2026-10-10T16:20\",\"end\":\"2026-10-10T17:20\",\"name\":\"\",\"partNumber\":\"\",\"barcode\":\"\",\"supplier\":\"\",\"stock\":0,\"reorder\":2,\"cost\":0,\"sell\":0,\"hourlyRate\":0,\"notificationChannels\":[],\"number\":\"INV-3608761\",\"issued\":\"2026-10-10\",\"due\":\"2026-10-10\",\"tax\":0,\"discount\":0,\"lines\":[{\"description\":\"\",\"qty\":1,\"price\":0}],\"address\":\"90 Winnepocket RD Webster, NH 03303\",\"updatedAt\":\"2026-10-10T20:21:42.935Z\"}', '2026-10-10 20:21:42'),
(1, '0ba2af07-83dc-4da1-84f4-c0949a1741a2', 'part', '{\"id\":\"0ba2af07-83dc-4da1-84f4-c0949a1741a2\",\"kind\":\"part\",\"customer\":\"\",\"phone\":\"\",\"email\":\"\",\"vehicle\":\"\",\"identifier\":\"\",\"issue\":\"\",\"notes\":\"\",\"status\":\"Draft\",\"start\":\"2026-10-10T16:32\",\"end\":\"2026-10-10T17:32\",\"name\":\"BrakeBest Select Pro Rear Semi-Metallic Brake Pads\",\"partNumber\":\"SPM1691\",\"barcode\":\"887243334570\",\"supplier\":\"O\'Reilly\",\"stock\":1,\"reorder\":0,\"cost\":56.6,\"sell\":89.99,\"hourlyRate\":0,\"notificationChannels\":[],\"number\":\"INV-4342364\",\"issued\":\"2026-10-10\",\"due\":\"2026-10-10\",\"tax\":0,\"discount\":0,\"lines\":[{\"description\":\"\",\"qty\":1,\"price\":0}],\"updatedAt\":\"2026-10-10T20:35:04.181Z\"}', '2026-10-10 20:35:04'),
(1, '60c593c7-39a6-428d-a15c-7356d905d445', 'part', '{\"id\":\"60c593c7-39a6-428d-a15c-7356d905d445\",\"kind\":\"part\",\"customer\":\"\",\"phone\":\"\",\"email\":\"\",\"vehicle\":\"\",\"identifier\":\"\",\"issue\":\"\",\"notes\":\"\",\"status\":\"Draft\",\"start\":\"2026-10-10T16:25\",\"end\":\"2026-10-10T17:25\",\"name\":\"BrakeBest Select Rear Semi-Metallic Brake Pads\",\"partNumber\":\"SM1691\",\"barcode\":\"8872433345701\",\"supplier\":\"O\'Reilly\",\"stock\":1,\"reorder\":0,\"cost\":43.6,\"sell\":69.99,\"hourlyRate\":0,\"notificationChannels\":[],\"number\":\"INV-3900915\",\"issued\":\"2026-10-10\",\"due\":\"2026-10-10\",\"tax\":0,\"discount\":0,\"lines\":[{\"description\":\"\",\"qty\":1,\"price\":0}],\"updatedAt\":\"2026-10-10T20:35:15.000Z\"}', '2026-10-10 20:35:15'),
(1, '6de1ee3f-4848-4d0d-9f75-24259ec5fa37', 'invoice', '{\"id\":\"6de1ee3f-4848-4d0d-9f75-24259ec5fa37\",\"kind\":\"invoice\",\"customer\":\"Brandon Wolinski\",\"phone\":\"(603) 229-7008\",\"email\":\"\",\"vehicle\":\"2016 Ford F-250 Super Duty\",\"identifier\":\"SKISMEC\",\"issue\":\"Rear Brakes (Pads & Rotors)\\nOil Change\",\"notes\":\"\",\"status\":\"Paid\",\"start\":\"2026-10-10T16:43\",\"end\":\"2026-10-10T17:43\",\"name\":\"\",\"partNumber\":\"\",\"barcode\":\"\",\"supplier\":\"\",\"stock\":0,\"reorder\":2,\"cost\":0,\"sell\":0,\"hourlyRate\":75,\"notificationChannels\":[],\"number\":\"INV-3595789\",\"issued\":\"2026-10-10\",\"due\":\"2026-10-10\",\"tax\":0,\"discount\":9,\"lines\":[{\"description\":\"Labor\",\"qty\":3,\"price\":75},{\"description\":\"Wix Oil Filter\",\"partNumber\":\"51372\",\"unit\":\"Parts\",\"qty\":1,\"price\":8.88},{\"description\":\"Mobile1 5Q 5W-30\",\"partNumber\":\"1-5-30-5QT\",\"unit\":\"Parts\",\"qty\":1,\"price\":44.99},{\"description\":\"Mobile1 1Q 5W-30\",\"partNumber\":\"1-5-30\",\"unit\":\"Parts\",\"qty\":2,\"price\":13.99},{\"description\":\"BrakeBest Select Pro Rear Semi-Metallic Brake Pads\",\"partNumber\":\"SPM1691\",\"unit\":\"Parts\",\"qty\":1,\"price\":89.99},{\"description\":\"BrakeBest Select Rear Brake Rotors\",\"partNumber\":\"672353RGS\",\"unit\":\"Parts\",\"qty\":2,\"price\":221.99}],\"customerId\":\"052df8e8-d578-4abb-9ad5-0e91bc5ff798\",\"address\":\"90 Winnepocket RD Webster, NH 03303\",\"location\":\"90 Winnepocket RD Webster, NH 03303\",\"timeSessions\":[],\"repairStatus\":\"Scheduled\",\"total\":83182,\"updatedAt\":\"2026-10-10T23:07:06.478Z\",\"sourceEstimate\":\"EST-5013555\",\"sourceEstimateId\":\"98bb364a-ad31-4e6b-9931-e872788cb46b\",\"cashPayments\":[{\"id\":\"7806d43b-7025-4e9d-b4c7-c106fa96eaab\",\"amount\":83182,\"date\":\"2026-10-20\",\"method\":\"Cash\",\"recordedAt\":\"2026-10-20T20:12:19.776Z\"}],\"paymentMethod\":\"Cash\"}', '2026-10-20 20:12:19'),
(1, '84871a2c-7165-481d-8879-52d97ae0585d', 'part', '{\"id\":\"84871a2c-7165-481d-8879-52d97ae0585d\",\"kind\":\"part\",\"customer\":\"\",\"phone\":\"\",\"email\":\"\",\"vehicle\":\"\",\"identifier\":\"\",\"issue\":\"\",\"notes\":\"\",\"status\":\"Draft\",\"start\":\"2026-10-10T16:35\",\"end\":\"2026-10-10T17:35\",\"name\":\"Wix Oil Filter\",\"partNumber\":\"51372\",\"barcode\":\"765809513723\",\"supplier\":\"O\'Reilly\",\"stock\":1,\"reorder\":0,\"cost\":8.88,\"sell\":8.88,\"hourlyRate\":0,\"notificationChannels\":[],\"number\":\"INV-4520378\",\"issued\":\"2026-10-10\",\"due\":\"2026-10-10\",\"tax\":0,\"discount\":0,\"lines\":[{\"description\":\"\",\"qty\":1,\"price\":0}],\"updatedAt\":\"2026-10-10T20:36:48.941Z\"}', '2026-10-10 20:43:24'),
(1, '96e047ee-5a16-43d8-904d-c127b80a1406', 'part', '{\"id\":\"96e047ee-5a16-43d8-904d-c127b80a1406\",\"kind\":\"part\",\"customer\":\"\",\"phone\":\"\",\"email\":\"\",\"vehicle\":\"\",\"identifier\":\"\",\"issue\":\"\",\"notes\":\"\",\"status\":\"Draft\",\"start\":\"2026-10-10T16:40\",\"end\":\"2026-10-10T17:40\",\"name\":\"BrakeBest Select Rear Brake Rotors\",\"partNumber\":\"672353RGS\",\"barcode\":\"641801222903\",\"supplier\":\"O\'Reilly\",\"stock\":2,\"reorder\":0,\"cost\":221.99,\"sell\":221.99,\"hourlyRate\":0,\"notificationChannels\":[],\"number\":\"INV-4853857\",\"issued\":\"2026-10-10\",\"due\":\"2026-10-10\",\"tax\":0,\"discount\":0,\"lines\":[{\"description\":\"\",\"qty\":1,\"price\":0}],\"updatedAt\":\"2026-10-10T20:42:44.617Z\"}', '2026-10-10 20:43:03'),
(1, '98bb364a-ad31-4e6b-9931-e872788cb46b', 'estimate', '{\"id\":\"98bb364a-ad31-4e6b-9931-e872788cb46b\",\"kind\":\"estimate\",\"customer\":\"Brandon Wolinski\",\"phone\":\"(603) 229-7008\",\"email\":\"\",\"vehicle\":\"2016 Ford F-250 Super Duty\",\"identifier\":\"SKISMEC\",\"issue\":\"Rear Brakes (Pads & Rotors)\\nOil Change\",\"notes\":\"\",\"status\":\"Accepted\",\"start\":\"2026-10-10T16:43\",\"end\":\"2026-10-10T17:43\",\"name\":\"\",\"partNumber\":\"\",\"barcode\":\"\",\"supplier\":\"\",\"stock\":0,\"reorder\":2,\"cost\":0,\"sell\":0,\"hourlyRate\":75,\"notificationChannels\":[],\"number\":\"EST-5013555\",\"issued\":\"2026-10-10\",\"due\":\"2026-10-10\",\"tax\":0,\"discount\":0,\"lines\":[{\"description\":\"Labor\",\"qty\":3,\"price\":75},{\"description\":\"Wix Oil Filter\",\"partNumber\":\"51372\",\"unit\":\"Parts\",\"qty\":1,\"price\":8.88},{\"description\":\"Mobile1 5Q 5W-30\",\"partNumber\":\"1-5-30-5QT\",\"unit\":\"Parts\",\"qty\":1,\"price\":44.99},{\"description\":\"Mobile1 1Q 5W-30\",\"partNumber\":\"1-5-30\",\"unit\":\"Parts\",\"qty\":2,\"price\":13.99},{\"description\":\"BrakeBest Select Pro Rear Semi-Metallic Brake Pads\",\"partNumber\":\"SPM1691\",\"unit\":\"Parts\",\"qty\":1,\"price\":89.99},{\"description\":\"BrakeBest Select Rear Brake Rotors\",\"partNumber\":\"672353RGS\",\"unit\":\"Parts\",\"qty\":2,\"price\":221.99}],\"customerId\":\"052df8e8-d578-4abb-9ad5-0e91bc5ff798\",\"address\":\"90 Winnepocket RD Webster, NH 03303\",\"location\":\"90 Winnepocket RD Webster, NH 03303\",\"timeSessions\":[{\"id\":\"54e7f2bd-43af-42f6-a67c-26873f087432\",\"start\":\"2026-10-10T20:52:03.800Z\",\"end\":\"2026-10-10T23:04:44.508Z\",\"stopId\":\"54cb3309-d04a-4f0b-947b-be3893bf1101\"}],\"repairStatus\":\"Working on vehicle\",\"total\":84082,\"updatedAt\":\"2026-10-10T23:06:27.773Z\"}', '2026-10-10 23:06:27'),
(1, 'a3b5fd7b-c01c-4820-96e0-328417c4fbb0', 'part', '{\"id\":\"a3b5fd7b-c01c-4820-96e0-328417c4fbb0\",\"kind\":\"part\",\"customer\":\"\",\"phone\":\"\",\"email\":\"\",\"vehicle\":\"\",\"identifier\":\"\",\"issue\":\"\",\"notes\":\"\",\"status\":\"Draft\",\"start\":\"2026-10-10T16:37\",\"end\":\"2026-10-10T17:37\",\"name\":\"Mobile1 5Q 5W-30\",\"partNumber\":\"1-5-30-5QT\",\"barcode\":\"071924448995\",\"supplier\":\"O\'Reilly\",\"stock\":1,\"reorder\":0,\"cost\":44.99,\"sell\":44.99,\"hourlyRate\":0,\"notificationChannels\":[],\"number\":\"INV-4630110\",\"issued\":\"2026-10-10\",\"due\":\"2026-10-10\",\"tax\":0,\"discount\":0,\"lines\":[{\"description\":\"\",\"qty\":1,\"price\":0}],\"updatedAt\":\"2026-10-10T20:40:21.989Z\"}', '2026-10-10 20:43:22'),
(1, 'fd61f30c-5154-4f68-964b-965bed36b677', 'part', '{\"id\":\"fd61f30c-5154-4f68-964b-965bed36b677\",\"kind\":\"part\",\"customer\":\"\",\"phone\":\"\",\"email\":\"\",\"vehicle\":\"\",\"identifier\":\"\",\"issue\":\"\",\"notes\":\"\",\"status\":\"Draft\",\"start\":\"2026-10-10T16:38\",\"end\":\"2026-10-10T17:38\",\"name\":\"Mobile1 1Q 5W-30\",\"partNumber\":\"1-5-30\",\"barcode\":\"071924248120\",\"supplier\":\"O\'Reilly\",\"stock\":2,\"reorder\":0,\"cost\":13.99,\"sell\":13.99,\"hourlyRate\":0,\"notificationChannels\":[],\"number\":\"INV-4703359\",\"issued\":\"2026-10-10\",\"due\":\"2026-10-10\",\"tax\":0,\"discount\":0,\"lines\":[{\"description\":\"\",\"qty\":1,\"price\":0}],\"updatedAt\":\"2026-10-10T20:40:45.599Z\"}', '2026-10-10 20:43:17');

-- --------------------------------------------------------

--
-- Table structure for table `stock_scans`
--

CREATE TABLE `stock_scans` (
  `owner` int(10) UNSIGNED NOT NULL,
  `id` varchar(100) NOT NULL,
  `part` varchar(100) NOT NULL,
  `status` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Dumping data for table `stock_scans`
--

INSERT INTO `stock_scans` (`owner`, `id`, `part`, `status`) VALUES
(1, '0f6bccc8-fe11-4512-b6c4-c3b9a8eadcbe', 'fd61f30c-5154-4f68-964b-965bed36b677', 'applied'),
(1, '6b82644c-ea65-4386-a225-555f59f0222f', '96e047ee-5a16-43d8-904d-c127b80a1406', 'applied'),
(1, '74281525-9407-4593-934f-78572950a64b', '96e047ee-5a16-43d8-904d-c127b80a1406', 'applied'),
(1, '8f3493ab-fb06-4cec-a68b-339b3a9f1b11', 'fd61f30c-5154-4f68-964b-965bed36b677', 'applied'),
(1, 'e992c3a4-09f6-428a-83b8-7a78f6e203cf', 'a3b5fd7b-c01c-4820-96e0-328417c4fbb0', 'applied'),
(1, 'f30e0e80-92c1-4ff7-933a-3d8d6c44a2f1', '84871a2c-7165-481d-8879-52d97ae0585d', 'applied');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(254) NOT NULL,
  `password_hash` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`) VALUES
(1, 'mortonsmechanical25@gmail.com', '$2y$10$9gkqXtEMpVCZouHKlml5ZOJ8eTT/DunmmIXGT2IeOuV.jmKbL1Ate');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`identity`);

--
-- Indexes for table `records`
--
ALTER TABLE `records`
  ADD PRIMARY KEY (`owner`,`id`),
  ADD KEY `owner` (`owner`,`kind`);

--
-- Indexes for table `stock_scans`
--
ALTER TABLE `stock_scans`
  ADD PRIMARY KEY (`owner`,`id`);

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
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
