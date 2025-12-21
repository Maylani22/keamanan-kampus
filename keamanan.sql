-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 21, 2025 at 05:46 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


CREATE TABLE `kategori` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `kategori` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Pencurian', 'Laporan mengenai pencurian properti kampus', '2025-12-16 16:15:11'),
(2, 'Penganiayaan', 'Laporan mengenai penganiayaan fisik', '2025-12-16 16:15:11'),
(4, 'Kendaraan', 'Masalah keamanan terkait kendaraan', '2025-12-16 16:15:11'),
(5, 'Cyber Security', 'Keamanan digital dan siber', '2025-12-16 16:15:11'),
(6, 'Lainnya', 'Jenis laporan keamanan lainnya', '2025-12-16 16:15:11'),
(9, 'Vandalisme', 'Kerusakan fasilitas dan properti kampus', '2025-12-18 18:40:38');


CREATE TABLE `laporan` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `location_address` text NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('pending','processed','resolved') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `laporan` (`id`, `user_id`, `kategori_id`, `title`, `description`, `location_address`, `latitude`, `longitude`, `status`, `created_at`) VALUES
(3, 2, 4, 'pemberitahuan', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'Universitas Buana Perjuangan Karawang', -6.36000000, 106.83000000, 'resolved', '2025-12-16 20:30:26'),
(5, 3, 4, 'Pencurian', 'ssssssssssssssssssssssssss', 'dekat unsika ', NULL, NULL, 'resolved', '2025-12-17 07:16:37'),
(8, 3, 4, 'pencurian', 'waktu 12.00,motor beat bewarna merah putih dengan nomer ****', 'klari', NULL, NULL, 'resolved', '2025-12-17 14:30:28'),
(9, 3, 4, 'Parkiran TI', 'kehilangan helm', 'PARKIRAN TI', -6.36000000, 106.83000000, 'resolved', '2025-12-17 16:54:27'),
(12, 3, 4, 'd', 'dd', 'Purwasari', -6.39035000, 107.41174300, 'resolved', '2025-12-18 12:51:39'),
(17, 3, 2, 'teswaa', 'aaawaa', 'Tamelang', -6.39098500, 107.41211700, 'processed', '2025-12-20 22:25:54');



CREATE TABLE `notifikasi` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `laporan_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



INSERT INTO `notifikasi` (`id`, `user_id`, `laporan_id`, `title`, `message`, `status`, `created_at`) VALUES
(1, 3, NULL, 'Laporan Baru Dibuat', 'Laporan Anda \'Pencurian\' telah berhasil dibuat dan sedang dalam proses review.', 'read', '2025-12-17 07:16:37'),
(3, 3, NULL, 'Laporan Baru Dibuat', 'Laporan Anda \'Pencurian\' telah berhasil dibuat dan sedang dalam proses review.', 'read', '2025-12-17 07:16:39'),
(5, 3, NULL, 'Laporan Baru Dibuat', 'Laporan Anda \'Pencurian\' telah berhasil dibuat dan sedang dalam proses review.', 'read', '2025-12-17 07:16:41'),
(8, 3, NULL, 'Laporan Baru Dibuat', 'Laporan Anda \'pencurian\' telah berhasil dibuat dan sedang dalam proses review.', 'read', '2025-12-17 14:30:28'),
(10, 4, 8, 'Status Laporan Diubah', 'Status laporan \'pencurian\' telah berubah dari Menunggu menjadi Diproses', 'read', '2025-12-17 14:40:59'),
(11, 4, 5, 'Status Laporan Diubah', 'Status laporan \'Pencurian\' telah berubah dari Menunggu menjadi Selesai', 'read', '2025-12-17 14:41:26'),
(12, 4, 8, 'Status Laporan Diubah', 'Status laporan \'pencurian\' telah berubah dari Diproses menjadi Selesai', 'read', '2025-12-17 14:41:57'),
(13, 3, NULL, 'Laporan Baru Dibuat', 'Laporan Anda \'Parkiran TI\' telah berhasil dibuat dan sedang dalam proses review.', 'read', '2025-12-17 16:54:27'),
(14, 4, NULL, 'Laporan Baru Masuk', 'Laporan baru \'Parkiran TI\' dari Mei memerlukan review.', 'read', '2025-12-17 16:54:27'),
(15, 3, NULL, 'Laporan Baru Dibuat', 'Laporan Anda \'kehilangan\' telah berhasil dibuat dan sedang dalam proses review.', 'read', '2025-12-18 07:06:32'),
(16, 4, NULL, 'Laporan Baru Masuk', 'Laporan baru \'kehilangan\' dari Mei memerlukan review.', 'read', '2025-12-18 07:06:32'),
(17, 3, NULL, 'Laporan Baru Dibuat', 'Laporan Anda \'p\' telah berhasil dibuat dan sedang dalam proses review.', 'read', '2025-12-18 07:15:25'),
(18, 4, NULL, 'Laporan Baru Masuk', 'Laporan baru \'p\' dari Mei memerlukan review.', 'read', '2025-12-18 07:15:25'),
(29, 3, NULL, 'Laporan Baru Dibuat', 'Laporan Anda \'d\' telah berhasil dibuat dan sedang dalam proses review.', 'read', '2025-12-18 12:51:40'),
(30, 4, NULL, 'Laporan Baru Masuk', 'Laporan baru \'d\' dari Mei memerlukan review.', 'read', '2025-12-18 12:51:40'),
(31, 3, 12, 'Status Laporan Diperbarui', 'Status laporan \'d\' telah diubah dari Menunggu menjadi Selesai', 'read', '2025-12-18 13:55:49'),
(32, 3, 12, 'Status Laporan Diperbarui', 'Status laporan \'d\' telah diubah dari Selesai menjadi Diproses', 'read', '2025-12-18 13:58:58'),
(33, 3, NULL, 'Laporan Baru Dibuat', 'Laporan Anda \'s\' telah berhasil dibuat dan sedang dalam proses review.', 'read', '2025-12-18 16:24:11'),
(34, 4, NULL, 'Laporan Baru Masuk', 'Laporan baru \'s\' dari Mei memerlukan review.', 'read', '2025-12-18 16:24:11'),
(38, 3, 9, 'Status Laporan Diperbarui', 'Status laporan \'Parkiran TI\' telah diubah dari Diproses menjadi Selesai', 'read', '2025-12-18 20:37:07'),
(39, 2, 3, 'Status Laporan Diperbarui', 'Status laporan \'pemberitahuan\' telah diubah dari Diproses menjadi Selesai', 'unread', '2025-12-18 20:37:44'),
(40, 3, 12, 'Status Laporan Diperbarui', 'Status laporan \'d\' telah diubah dari Diproses menjadi Selesai', 'read', '2025-12-18 20:38:16'),
(44, 3, NULL, 'Laporan Baru Dibuat', 'Laporan Anda \'xx\' telah berhasil dibuat dan sedang dalam proses review.', 'read', '2025-12-18 20:51:53'),
(45, 4, NULL, 'Laporan Baru Masuk', 'Laporan baru \'xx\' dari Mei memerlukan review.', 'read', '2025-12-18 20:51:53'),
(48, 3, NULL, 'Laporan Baru Dibuat', 'Laporan Anda \'j\' telah berhasil dibuat dan sedang dalam proses review.', 'read', '2025-12-20 06:53:59'),
(49, 4, NULL, 'Laporan Baru Masuk', 'Laporan baru \'j\' dari Mei memerlukan review.', 'read', '2025-12-20 06:53:59'),
(50, 3, NULL, 'Laporan Baru Dibuat', 'Laporan Anda \'ss\' telah berhasil dibuat dan sedang dalam proses review.', 'read', '2025-12-20 18:36:35'),
(51, 4, NULL, 'Laporan Baru Masuk', 'Laporan baru \'ss\' dari Mei memerlukan review.', 'read', '2025-12-20 18:36:35'),
(52, 3, NULL, 'Laporan Baru Dibuat', 'Laporan Anda \'tes\' telah berhasil dibuat dan sedang dalam proses review.', 'unread', '2025-12-20 22:25:54'),
(53, 4, NULL, 'Laporan Baru Masuk', 'Laporan baru \'tes\' dari Mei memerlukan review.', 'read', '2025-12-20 22:25:54'),
(54, 3, 17, 'Status Laporan Diperbarui', 'Status laporan \'teswaa\' telah diubah dari Menunggu menjadi Diproses', 'unread', '2025-12-20 22:39:25');


CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','mahasiswa') DEFAULT 'mahasiswa',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(2, 'zakiah', 'zakiah@gmail.com', '$2y$10$u3MVfNevORVkuXluVv/UdetIuJBxBhdFKuDxRaNPm7HOtkFbrUggu', 'mahasiswa', '2025-12-16 19:38:20'),
(3, 'Mei', 'xmylni56@gmail.com', '$2y$10$uNvwG6IrONTo8xH.87arMuI36LiH6kX89bsuIRxmletucaSXYdWim', 'mahasiswa', '2025-12-16 23:45:51'),
(4, 'Maylani', 'if24.maylani@mhs.ubpkarawang.ac.id', '$2y$10$JneXsB3GrTl5YXTVJpKfBuvXVtVknBBDlGQaSLm/IS1BEHi3Xrez.', 'admin', '2025-12-17 14:33:40');


ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`);


ALTER TABLE `laporan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `kategori_id` (`kategori_id`);


ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `laporan_id` (`laporan_id`);


ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);


ALTER TABLE `kategori`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;


ALTER TABLE `laporan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;


ALTER TABLE `notifikasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;


ALTER TABLE `laporan`
  ADD CONSTRAINT `laporan_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `laporan_ibfk_2` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`) ON DELETE SET NULL;


ALTER TABLE `notifikasi`
  ADD CONSTRAINT `notifikasi_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifikasi_ibfk_2` FOREIGN KEY (`laporan_id`) REFERENCES `laporan` (`id`) ON DELETE CASCADE;
COMMIT;

