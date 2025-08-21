-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 15, 2025 at 09:00 AM
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
-- Database: `job_order_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `ac_parts`
--

CREATE TABLE `ac_parts` (
  `id` int(11) NOT NULL,
  `part_name` varchar(255) NOT NULL,
  `part_code` varchar(100) DEFAULT NULL,
  `part_category` enum('compressor','condenser','evaporator','filter','capacitor','thermostat','fan_motor','refrigerant','electrical','other') NOT NULL,
  `compatible_brands` text DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `labor_cost` decimal(10,2) DEFAULT 0.00,
  `warranty_months` int(11) DEFAULT 12,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ac_parts`
--

INSERT INTO `ac_parts` (`id`, `part_name`, `part_code`, `part_category`, `compatible_brands`, `unit_price`, `labor_cost`, `warranty_months`, `created_at`, `updated_at`) VALUES
(2, 'Condenser Coil', 'COND-001', 'condenser', '[\"All Brands\"]', 1500.00, 500.00, 12, '2025-08-13 12:04:24', '2025-08-13 12:04:24'),
(3, 'Evaporator Coil', 'EVAP-001', 'evaporator', '[\"All Brands\"]', 1200.00, 500.00, 12, '2025-08-13 12:04:24', '2025-08-13 12:04:24'),
(5, 'Capacitor 35uF', 'CAP-35UF', 'compressor', '[\"All Brands\"]', 250.00, 100.00, 6, '2025-08-13 12:04:24', '2025-08-14 03:54:55'),
(6, 'Digital Thermostat', 'THERM-DIG-001', 'thermostat', '[\"All Brands\"]', 800.00, 300.00, 12, '2025-08-13 12:04:24', '2025-08-13 12:04:24'),
(7, 'Fan Motor 1/4HP', 'FAN-025HP', 'fan_motor', '[\"All Brands\"]', 1800.00, 400.00, 18, '2025-08-13 12:04:24', '2025-08-13 12:04:24'),
(9, 'Control Board', 'PCB-001', 'electrical', '[\"Carrier\", \"Daikin\", \"Panasonic\"]', 2500.00, 600.00, 12, '2025-08-13 12:04:24', '2025-08-13 12:04:24'),
(10, 'Drain Pump', 'PUMP-001', 'other', '[\"All Brands\"]', 600.00, 300.00, 12, '2025-08-13 12:04:24', '2025-08-13 12:04:24');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `name`, `email`, `phone`, `profile_picture`, `password`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'Admin - Staff', 'student.joshmcdowelltrapal@gmail.com', '09958714112', 'uploads/profile_pictures/profile_683fca5baffdd.jpg', '$2y$10$G7lz8/ECY2SpEs1WYM6GrOAGvA7t9Cmd4oouJChZ5k627LN0kPHMS', '2025-06-02 12:30:32', '2025-08-15 01:05:10'),
(5, 'eciel', 'Eciel Semeniano', 'joshmcdowelltrapal@gmail.com', '09958714112', 'uploads/profile_pictures/admin_683fcddf3d582.jpg', '$2y$10$0U90HfuS1rVFJQG1I6TkT.8M3w8865/Fep/bfoRtepzGAsOB3twbW', '2025-06-04 04:38:55', '2025-06-04 04:38:55'),
(6, 'fourjs-admin', '4Js Telecommunications', '4jstelcom@gmail.com', '09958714112', 'uploads/profile_pictures/admin_689820804efb1.png', '$2y$10$iwdteayO/dOg4tYQ0pWI6OZPgRMTPyh93fxny457oa5omJXNdjq9u', '2025-08-10 04:30:56', '2025-08-10 04:30:56'),
(7, 'josh-administrator', 'Josh McDowell Trapal', 'joshmcdowellramireztrapal@gmail.com', '09958714112', 'uploads/profile_pictures/admin_689df1d24e917.jpg', '$2y$10$TvS3VYq13.TqZTEVuh6QsuMTULyfinWg2UAvTkWKhQpelllcxRieG', '2025-08-14 14:25:22', '2025-08-14 14:25:40');

-- --------------------------------------------------------

--
-- Table structure for table `aircon_installations`
--

CREATE TABLE `aircon_installations` (
  `id` int(11) NOT NULL,
  `job_order_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `has_filter` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `aircon_models`
--

CREATE TABLE `aircon_models` (
  `id` int(11) NOT NULL,
  `brand` varchar(50) NOT NULL,
  `model_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aircon_models`
--

INSERT INTO `aircon_models` (`id`, `brand`, `model_name`, `price`, `created_at`) VALUES
(14, 'AUX', 'J Series - 1.0HP Wall Mounted - Inverter', 34000.00, '2025-08-14 13:22:35'),
(15, 'AUX', 'J Series - 2.0HP Wall Mounted - Inverter', 51799.00, '2025-08-14 13:22:59'),
(16, 'AUX', 'J Series - 1.5HP Wall Mounted - Inverter', 38999.00, '2025-08-14 13:23:46'),
(17, 'AUX', 'F Series - 1.0HP Wall Mounted - Inverter', 35499.00, '2025-08-14 13:24:35'),
(18, 'AUX', 'F Series - 1.5HP Wall Mounted - Inverter', 39999.00, '2025-08-14 13:24:50'),
(19, 'AUX', 'F Series - 2.0HP Wall Mounted - Inverter', 49999.00, '2025-08-14 13:25:07'),
(20, 'AUX', 'F Series - 2.5HP Wall Mounted - Inverter', 59999.00, '2025-08-14 13:25:24'),
(21, 'AUX', 'J Series - 3.0HP Wall Mounted - Inverter', 74999.00, '2025-08-14 13:26:07'),
(22, 'TCL', 'TAC-10CSD/KEI2 - 1.0HP Wall Mounted - Inverter', 25998.00, '2025-08-14 13:29:10'),
(23, 'TCL', 'TAC-13CSD/KEI2 - 1.5HP Wall Mounted - Inverter', 27998.00, '2025-08-14 13:29:41'),
(24, 'TCL', 'TAC-25CSD/KEI2 - 2.5HP Wall Mounted - Inverter', 40998.00, '2025-08-14 13:30:21'),
(25, 'TCL', 'TAC-07CWI/UB2 - 0.7HP Window Type - Inverter', 16998.00, '2025-08-14 13:32:09'),
(26, 'TCL', 'TAC-09CWI/UB2 - 1.0HP Window Type - Inverter', 17998.00, '2025-08-14 13:32:46'),
(27, 'TCL', 'TAC-12CWI/UB2 - 1.5HP Window Type - Inverter', 19998.00, '2025-08-14 13:33:20'),
(28, 'CHIQ', 'Morandi CSD-10DA - 1.0HP Wall Mounted - Inverter', 18999.00, '2025-08-14 13:44:01'),
(29, 'CHIQ', 'Morandi CSD-15DA - 1.5HP Wall Mounted - Inverter', 21500.00, '2025-08-14 13:44:42'),
(30, 'CHIQ', 'Morandi CSD-20DA - 2.0HP Wall Mounted - Inverter', 27500.00, '2025-08-14 13:45:12'),
(31, 'CHIQ', 'Morandi CSD-25DA - 2.5HP Wall Mounted - Inverter', 33000.00, '2025-08-14 13:45:34');

-- --------------------------------------------------------

--
-- Table structure for table `cleaning_services`
--

CREATE TABLE `cleaning_services` (
  `id` int(11) NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `service_description` text DEFAULT NULL,
  `service_type` enum('basic_cleaning','deep_cleaning','chemical_wash','coil_cleaning','filter_cleaning') NOT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `unit_type` enum('per_unit','per_hour','per_service') DEFAULT 'per_unit',
  `aircon_type` enum('window','split','cassette','floor_standing','all') DEFAULT 'all',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cleaning_services`
--

INSERT INTO `cleaning_services` (`id`, `service_name`, `service_description`, `service_type`, `base_price`, `unit_type`, `aircon_type`, `created_at`, `updated_at`) VALUES
(1, 'Basic Cleaning', 'Standard cleaning of filters and external parts', 'basic_cleaning', 500.00, 'per_unit', 'all', '2025-08-14 01:47:15', '2025-08-14 01:47:15'),
(2, 'Deep Cleaning', 'Thorough cleaning including coils and internal components', 'deep_cleaning', 1200.00, 'per_unit', 'all', '2025-08-14 01:47:15', '2025-08-14 01:47:15'),
(3, 'Chemical Wash', 'Chemical cleaning for heavily soiled units', 'chemical_wash', 2000.00, 'per_unit', 'all', '2025-08-14 01:47:15', '2025-08-14 01:47:15'),
(4, 'Coil Cleaning', 'Specialized cleaning of evaporator and condenser coils', 'coil_cleaning', 800.00, 'per_unit', 'all', '2025-08-14 01:47:15', '2025-08-14 01:47:15'),
(5, 'Filter Replacement', 'Replace air filters with new ones', 'filter_cleaning', 300.00, 'per_unit', 'all', '2025-08-14 01:47:15', '2025-08-14 01:47:15'),
(6, 'Window AC Basic Clean', 'Basic cleaning for window type units', 'basic_cleaning', 400.00, 'per_unit', 'window', '2025-08-14 01:47:15', '2025-08-14 01:47:15'),
(7, 'Split AC Deep Clean', 'Comprehensive cleaning for split type units', 'deep_cleaning', 1500.00, 'per_unit', 'split', '2025-08-14 01:47:15', '2025-08-14 01:47:15'),
(8, 'Cassette AC Chemical Wash', 'Chemical wash for cassette type units', 'chemical_wash', 2500.00, 'per_unit', 'cassette', '2025-08-14 01:47:15', '2025-08-14 01:47:15'),
(9, 'Basic Cleaning', 'Standard cleaning of filters and external parts', 'basic_cleaning', 500.00, 'per_unit', 'all', '2025-08-14 02:19:57', '2025-08-14 02:19:57'),
(10, 'Deep Cleaning', 'Thorough cleaning including coils and internal components', 'deep_cleaning', 1200.00, 'per_unit', 'all', '2025-08-14 02:19:57', '2025-08-14 02:19:57'),
(11, 'Chemical Wash', 'Chemical cleaning for heavily soiled units', 'chemical_wash', 2000.00, 'per_unit', 'all', '2025-08-14 02:19:57', '2025-08-14 02:19:57'),
(12, 'Coil Cleaning', 'Specialized cleaning of evaporator and condenser coils', 'coil_cleaning', 800.00, 'per_unit', 'all', '2025-08-14 02:19:57', '2025-08-14 02:19:57'),
(13, 'Filter Replacement', 'Replace air filters with new ones', 'filter_cleaning', 300.00, 'per_unit', 'all', '2025-08-14 02:19:57', '2025-08-14 02:19:57'),
(14, 'Window AC Basic Clean', 'Basic cleaning for window type units', 'basic_cleaning', 400.00, 'per_unit', 'window', '2025-08-14 02:19:57', '2025-08-14 02:19:57'),
(17, 'Cassette AC Chemical Wash', 'test ulit', 'chemical_wash', 2500.00, 'per_unit', 'all', '2025-08-14 14:33:17', '2025-08-14 14:33:26');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `phone`, `address`, `created_at`) VALUES
(26, 'Josh McDowell Trapal', '09958714112', 'Ogbot, Bongabong, Oriental Mindoro', '2025-08-07 01:45:50'),
(27, 'Ann Marisse Cuya', '09958714112', 'Sitio Highway Roxas, 5212 Oriental Mindoro', '2025-08-07 01:46:16'),
(28, 'Angel Lamadrid', '09958714113', 'Sitio Manggahan, Dangay, Roxas, Oriental Mindoro', '2025-08-07 02:00:20'),
(29, 'Desiree Ortega', '09958714556', 'Mansalay, Oriental Mindoro', '2025-08-12 11:35:56'),
(30, 'Eciel Semeniano', '09958714116', 'San Mariano, Roxas, Oriental Mindoro', '2025-08-14 03:58:39');

-- --------------------------------------------------------

--
-- Table structure for table `job_orders`
--

CREATE TABLE `job_orders` (
  `id` int(11) NOT NULL,
  `job_order_number` varchar(20) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_address` text NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `service_type` enum('installation','repair','survey') NOT NULL,
  `aircon_model_id` int(11) DEFAULT NULL,
  `assigned_technician_id` int(11) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `price` decimal(10,2) DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `customer_id` int(11) DEFAULT NULL,
  `additional_fee` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `part_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_orders`
--

INSERT INTO `job_orders` (`id`, `job_order_number`, `customer_name`, `customer_address`, `customer_phone`, `service_type`, `aircon_model_id`, `assigned_technician_id`, `status`, `price`, `base_price`, `created_by`, `due_date`, `completed_at`, `created_at`, `updated_at`, `customer_id`, `additional_fee`, `discount`, `part_id`) VALUES
(54, 'JO-20250812-4895', 'Desiree Ortega', 'Mansalay, Oriental Mindoro', '09958714556', 'survey', NULL, 2, 'completed', 500.00, 500.00, 6, NULL, '2025-08-12 11:36:37', '2025-08-12 11:35:56', '2025-08-12 11:36:37', 29, 0.00, 0.00, NULL),
(55, 'JO-20250812-2464', 'Desiree Ortega', 'Mansalay, Oriental Mindoro', '09958714556', 'installation', 4, 2, 'completed', 18999.00, 18999.00, NULL, NULL, '2025-08-12 11:37:18', '2025-08-12 11:37:09', '2025-08-12 11:37:18', 29, 0.00, 0.00, NULL),
(56, 'JO-20250812-1563', 'Desiree Ortega', 'Mansalay, Oriental Mindoro', '09958714556', 'installation', 1, 2, 'completed', 15999.00, 15999.00, NULL, NULL, '2025-08-12 11:37:19', '2025-08-12 11:37:09', '2025-08-12 11:37:19', 29, 0.00, 0.00, NULL),
(57, 'JO-20250812-2909', 'Desiree Ortega', 'Mansalay, Oriental Mindoro', '09958714556', 'installation', 2, 2, 'completed', 24999.00, 24999.00, NULL, NULL, '2025-08-12 11:37:20', '2025-08-12 11:37:09', '2025-08-12 11:37:20', 29, 0.00, 0.00, NULL),
(58, 'JO-20250812-3341', 'Desiree Ortega', 'Mansalay, Oriental Mindoro', '09958714556', 'installation', 3, 2, 'completed', 32999.00, 32999.00, NULL, NULL, '2025-08-12 11:37:20', '2025-08-12 11:37:09', '2025-08-12 11:37:20', 29, 0.00, 0.00, NULL),
(59, 'JO-20250812-6636', 'Desiree Ortega', 'Mansalay, Oriental Mindoro', '09958714556', 'installation', 1, 2, 'completed', 15999.00, 15999.00, NULL, NULL, '2025-08-12 11:37:21', '2025-08-12 11:37:09', '2025-08-12 11:37:21', 29, 0.00, 0.00, NULL),
(60, 'JO-20250812-4069', 'Desiree Ortega', 'Mansalay, Oriental Mindoro', '09958714556', 'installation', 1, 2, 'completed', 15999.00, 15999.00, NULL, NULL, '2025-08-12 11:37:32', '2025-08-12 11:37:09', '2025-08-12 11:37:32', 29, 0.00, 0.00, NULL),
(61, 'JO-20250813-0094', 'Ann Marisse Cuya', 'Sitio Highway Roxas, 5212 Oriental Mindoro', '09958714112', 'survey', NULL, 9, 'in_progress', 500.00, 500.00, 6, NULL, NULL, '2025-08-13 11:38:49', '2025-08-13 11:38:57', 27, 0.00, 0.00, NULL),
(62, 'JO-20250813-4456', 'Ann Marisse Cuya', 'Sitio Highway Roxas, 5212 Oriental Mindoro', '09958714112', 'repair', 1, 3, 'completed', 1200.00, 1200.00, 6, NULL, '2025-08-13 12:29:08', '2025-08-13 12:28:57', '2025-08-13 12:49:36', 27, 0.00, 0.00, NULL),
(63, 'JO-20250814-5089', 'Ann Marisse Cuya', 'Sitio Highway Roxas, 5212 Oriental Mindoro', '09958714112', 'repair', 21, 9, 'completed', 2500.00, 2500.00, 6, NULL, '2025-08-14 01:56:13', '2025-08-14 01:56:09', '2025-08-14 01:56:13', 27, 0.00, 0.00, NULL),
(64, 'JO-20250814-3989', 'Eciel Semeniano', 'San Mariano, Roxas, Oriental Mindoro', '09958714116', 'survey', NULL, 9, 'completed', 500.00, 500.00, 6, NULL, '2025-08-14 04:18:24', '2025-08-14 03:58:39', '2025-08-14 04:18:24', 30, 1.00, 1.00, NULL),
(65, 'JO-20250814-4037', 'Eciel Semeniano', 'San Mariano, Roxas, Oriental Mindoro', '09958714116', 'installation', 1, 9, 'completed', 15999.00, 15999.00, NULL, NULL, '2025-08-14 04:18:23', '2025-08-14 03:59:02', '2025-08-14 04:18:23', 30, 0.00, 0.00, NULL),
(66, 'JO-20250814-2065', 'Eciel Semeniano', 'San Mariano, Roxas, Oriental Mindoro', '09958714116', 'installation', 1, 3, 'completed', 15999.00, 15999.00, NULL, NULL, '2025-08-14 03:59:17', '2025-08-14 03:59:14', '2025-08-14 03:59:17', 30, 0.00, 0.00, NULL),
(67, 'JO-20250814-8690', 'Eciel Semeniano', 'San Mariano, Roxas, Oriental Mindoro', '09958714116', 'installation', 2, 3, 'completed', 24999.00, 24999.00, NULL, NULL, '2025-08-14 04:18:21', '2025-08-14 03:59:14', '2025-08-14 04:18:21', 30, 0.00, 0.00, NULL),
(68, 'JO-20250814-1531', 'Eciel Semeniano', 'San Mariano, Roxas, Oriental Mindoro', '09958714116', 'repair', NULL, 9, 'completed', 600.00, 600.00, 6, NULL, '2025-08-14 04:18:20', '2025-08-14 03:59:26', '2025-08-14 04:27:31', 30, 0.00, 0.00, 10),
(71, 'JO-20250814-7462', 'Eciel Semeniano', 'San Mariano, Roxas, Oriental Mindoro', '09958714116', 'repair', 5, 3, 'completed', 250.00, 250.00, 6, NULL, '2025-08-14 04:18:18', '2025-08-14 04:17:27', '2025-08-14 04:18:18', 30, 0.00, 0.00, 5),
(72, 'JO-20250814-1673', 'Angel Lamadrid', 'Sitio Manggahan, Dangay, Roxas, Oriental Mindoro', '09958714113', 'survey', NULL, 10, 'completed', 500.00, 500.00, 6, NULL, '2025-08-14 05:26:32', '2025-08-14 04:29:32', '2025-08-14 05:26:32', 28, 0.00, 0.00, NULL),
(73, 'JO-20250814-6301', 'Angel Lamadrid', 'Sitio Manggahan, Dangay, Roxas, Oriental Mindoro', '09958714113', 'repair', NULL, 10, 'completed', 800.00, 800.00, 6, NULL, '2025-08-14 05:26:32', '2025-08-14 04:29:47', '2025-08-14 05:26:32', 28, 0.00, 0.00, 6),
(74, 'JO-20250814-6675', 'Eciel Semeniano', 'San Mariano, Roxas, Oriental Mindoro', '09958714116', 'installation', 28, 10, 'completed', 18999.00, 18999.00, NULL, NULL, '2025-08-14 13:46:55', '2025-08-14 13:46:11', '2025-08-14 13:46:55', 30, 0.00, 0.00, NULL),
(75, 'JO-20250814-2168', 'Eciel Semeniano', 'San Mariano, Roxas, Oriental Mindoro', '09958714116', 'installation', 21, 10, 'completed', 74999.00, 74999.00, NULL, NULL, '2025-08-14 13:46:54', '2025-08-14 13:46:11', '2025-08-14 13:46:54', 30, 0.00, 0.00, NULL),
(76, 'JO-20250815-7011', 'Desiree Ortega', 'Mansalay, Oriental Mindoro', '09958714556', 'installation', 30, 9, 'pending', 27500.00, 27500.00, NULL, NULL, NULL, '2025-08-15 02:49:13', '2025-08-15 02:49:13', 29, 0.00, 0.00, NULL),
(77, 'JO-20250815-3281', 'Desiree Ortega', 'Mansalay, Oriental Mindoro', '09958714556', 'installation', 29, 9, 'pending', 21500.00, 21500.00, NULL, NULL, NULL, '2025-08-15 02:49:13', '2025-08-15 02:49:13', 29, 0.00, 0.00, NULL),
(78, 'JO-20250815-5994', 'Desiree Ortega', 'Mansalay, Oriental Mindoro', '09958714556', 'installation', 27, 9, 'pending', 19998.00, 19998.00, NULL, NULL, NULL, '2025-08-15 02:49:13', '2025-08-15 02:49:13', 29, 0.00, 0.00, NULL),
(79, 'JO-20250815-0370', 'Desiree Ortega', 'Mansalay, Oriental Mindoro', '09958714556', 'installation', 22, 9, 'pending', 25998.00, 25998.00, NULL, NULL, NULL, '2025-08-15 02:49:13', '2025-08-15 02:49:13', 29, 0.00, 0.00, NULL),
(80, 'JO-20250815-6261', 'Desiree Ortega', 'Mansalay, Oriental Mindoro', '09958714556', 'installation', 19, 9, 'pending', 49999.00, 49999.00, NULL, NULL, NULL, '2025-08-15 02:49:13', '2025-08-15 02:49:13', 29, 0.00, 0.00, NULL),
(81, 'JO-20250815-9433', 'Desiree Ortega', 'Mansalay, Oriental Mindoro', '09958714556', 'installation', 16, 9, 'pending', 38999.00, 38999.00, NULL, NULL, NULL, '2025-08-15 02:49:13', '2025-08-15 02:49:13', 29, 0.00, 0.00, NULL),
(82, 'JO-20250815-4879', 'Desiree Ortega', 'Mansalay, Oriental Mindoro', '09958714556', 'installation', 21, 9, 'pending', 74999.00, 74999.00, NULL, NULL, NULL, '2025-08-15 02:49:13', '2025-08-15 02:49:13', 29, 0.00, 0.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `technicians`
--

CREATE TABLE `technicians` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `technicians`
--

INSERT INTO `technicians` (`id`, `username`, `password`, `name`, `email`, `phone`, `profile_picture`, `created_at`, `updated_at`) VALUES
(3, 'eciel', '$2y$10$UObZIT9OlApxi4rd/C47f.1WmkZJr3Vgq/Rs3ByDf1OkYfCIErsb.', 'Eciel Semeniano', 'ecielsemeniano@gmail.com', '09958714113', 'uploads/profile_pictures/technician_3_1749013989.jpg', '2025-06-03 06:56:19', '2025-06-04 05:13:09'),
(9, 'maan-admin', '$2y$10$tk3olYazbTW4nt5UOboJQOFEaEtSoo2BUTrFDWtVLAcrW17izQzqS', 'Marianne Dela Cruz', NULL, '09958714110', 'uploads/profile_pictures/technician_1755002855_689b37e7157d0.png', '2025-08-12 12:47:35', '2025-08-12 12:47:35'),
(10, 'dhodz', '$2y$10$jphN2Zc65VXIzUbMkRCig.nCZEsgXF1XN0k5vFMkYlEe4aUJ0Tle2', 'Josh McDowell Trapal', NULL, '09452592763', 'uploads/profile_pictures/technician_1755145758_689d661ebb60f.png', '2025-08-14 04:29:18', '2025-08-14 04:29:18');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ac_parts`
--
ALTER TABLE `ac_parts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ac_parts_category` (`part_category`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `idx_username` (`username`),
  ADD UNIQUE KEY `idx_email` (`email`);

--
-- Indexes for table `aircon_installations`
--
ALTER TABLE `aircon_installations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_order_id` (`job_order_id`);

--
-- Indexes for table `aircon_models`
--
ALTER TABLE `aircon_models`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cleaning_services`
--
ALTER TABLE `cleaning_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cleaning_services_type` (`service_type`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customers_name_phone` (`name`,`phone`);

--
-- Indexes for table `job_orders`
--
ALTER TABLE `job_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `job_order_number` (`job_order_number`),
  ADD KEY `aircon_model_id` (`aircon_model_id`),
  ADD KEY `assigned_technician_id` (`assigned_technician_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_job_orders_customer_id` (`customer_id`),
  ADD KEY `idx_job_orders_status` (`status`),
  ADD KEY `idx_job_orders_part_id` (`part_id`);

--
-- Indexes for table `technicians`
--
ALTER TABLE `technicians`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `idx_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ac_parts`
--
ALTER TABLE `ac_parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `aircon_installations`
--
ALTER TABLE `aircon_installations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `aircon_models`
--
ALTER TABLE `aircon_models`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `cleaning_services`
--
ALTER TABLE `cleaning_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `job_orders`
--
ALTER TABLE `job_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `technicians`
--
ALTER TABLE `technicians`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `aircon_installations`
--
ALTER TABLE `aircon_installations`
  ADD CONSTRAINT `aircon_installations_ibfk_1` FOREIGN KEY (`job_order_id`) REFERENCES `job_orders` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `job_orders`
--
ALTER TABLE `job_orders`
  ADD CONSTRAINT `fk_job_orders_created_by` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_job_orders_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_job_orders_part_id` FOREIGN KEY (`part_id`) REFERENCES `ac_parts` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
