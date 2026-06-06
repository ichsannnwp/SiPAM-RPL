-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 06, 2026 at 11:01 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sipam_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `master_tarif`
--

CREATE TABLE `master_tarif` (
  `id` int(11) NOT NULL,
  `tarif_per_m3` decimal(10,2) NOT NULL,
  `berlaku_mulai` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `master_tarif`
--

INSERT INTO `master_tarif` (`id`, `tarif_per_m3`, `berlaku_mulai`, `is_active`, `created_at`) VALUES
(1, 2500.00, '2024-01-01', 1, '2026-06-06 07:48:38');

-- --------------------------------------------------------

--
-- Table structure for table `meteran`
--

CREATE TABLE `meteran` (
  `id` int(11) NOT NULL,
  `pelanggan_id` int(11) NOT NULL,
  `angka_bulan_ini` int(11) NOT NULL,
  `angka_bulan_lalu` int(11) DEFAULT 0,
  `periode` varchar(7) NOT NULL,
  `tanggal_input` date NOT NULL,
  `foto_meteran` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id` int(11) NOT NULL,
  `pelanggan_id` int(11) NOT NULL,
  `tagihan_id` int(11) DEFAULT NULL,
  `tipe` enum('reminder','eskalasi','info') NOT NULL,
  `pesan` text NOT NULL,
  `is_sent` tinyint(1) DEFAULT 0,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pelanggan`
--

CREATE TABLE `pelanggan` (
  `id` int(11) NOT NULL,
  `kode_pelanggan` varchar(30) DEFAULT NULL,
  `nik` char(16) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `alamat` text NOT NULL,
  `no_telepon` varchar(15) NOT NULL,
  `status` enum('calon','aktif','nonaktif') DEFAULT 'calon',
  `free_period` tinyint(1) DEFAULT 1,
  `tanggal_bergabung` date DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id` int(11) NOT NULL,
  `tagihan_id` int(11) NOT NULL,
  `pelanggan_id` int(11) NOT NULL,
  `jumlah_bayar` decimal(12,2) NOT NULL,
  `metode` enum('tunai','transfer') DEFAULT 'tunai',
  `bukti_transfer` varchar(255) DEFAULT NULL,
  `no_kuitansi` varchar(50) DEFAULT NULL,
  `tanggal_bayar` datetime NOT NULL,
  `dicatat_oleh` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tagihan`
--

CREATE TABLE `tagihan` (
  `id` int(11) NOT NULL,
  `pelanggan_id` int(11) NOT NULL,
  `meteran_id` int(11) NOT NULL,
  `tarif_id` int(11) NOT NULL,
  `periode` varchar(7) NOT NULL,
  `pemakaian_m3` int(11) NOT NULL,
  `total_tagihan` decimal(12,2) NOT NULL,
  `status` enum('belum_bayar','lunas','tunggakan') DEFAULT 'belum_bayar',
  `jatuh_tempo` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','pelanggan','desa') DEFAULT 'pelanggan',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'admin@sipam.com', '$2y$10$sQJEN/zZuzm3qbXjxOsMAePX5MH9/TNsow0NeG9TzgMBrTbWBqsoq', 'admin', '2026-06-06 07:48:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `master_tarif`
--
ALTER TABLE `master_tarif`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `meteran`
--
ALTER TABLE `meteran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_meteran` (`pelanggan_id`,`periode`);

--
-- Indexes for table `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pelanggan_id` (`pelanggan_id`),
  ADD KEY `tagihan_id` (`tagihan_id`);

--
-- Indexes for table `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nik` (`nik`),
  ADD UNIQUE KEY `kode_pelanggan` (`kode_pelanggan`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tagihan_id` (`tagihan_id`),
  ADD UNIQUE KEY `no_kuitansi` (`no_kuitansi`),
  ADD KEY `pelanggan_id` (`pelanggan_id`),
  ADD KEY `dicatat_oleh` (`dicatat_oleh`);

--
-- Indexes for table `tagihan`
--
ALTER TABLE `tagihan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `meteran_id` (`meteran_id`),
  ADD UNIQUE KEY `unique_tagihan` (`pelanggan_id`,`periode`),
  ADD KEY `tarif_id` (`tarif_id`);

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
-- AUTO_INCREMENT for table `master_tarif`
--
ALTER TABLE `master_tarif`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `meteran`
--
ALTER TABLE `meteran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pelanggan`
--
ALTER TABLE `pelanggan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tagihan`
--
ALTER TABLE `tagihan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `meteran`
--
ALTER TABLE `meteran`
  ADD CONSTRAINT `meteran_ibfk_1` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`id`);

--
-- Constraints for table `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD CONSTRAINT `notifikasi_ibfk_1` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`id`),
  ADD CONSTRAINT `notifikasi_ibfk_2` FOREIGN KEY (`tagihan_id`) REFERENCES `tagihan` (`id`);

--
-- Constraints for table `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD CONSTRAINT `pelanggan_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`tagihan_id`) REFERENCES `tagihan` (`id`),
  ADD CONSTRAINT `pembayaran_ibfk_2` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`id`),
  ADD CONSTRAINT `pembayaran_ibfk_3` FOREIGN KEY (`dicatat_oleh`) REFERENCES `users` (`id`);

--
-- Constraints for table `tagihan`
--
ALTER TABLE `tagihan`
  ADD CONSTRAINT `tagihan_ibfk_1` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`id`),
  ADD CONSTRAINT `tagihan_ibfk_2` FOREIGN KEY (`meteran_id`) REFERENCES `meteran` (`id`),
  ADD CONSTRAINT `tagihan_ibfk_3` FOREIGN KEY (`tarif_id`) REFERENCES `master_tarif` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
