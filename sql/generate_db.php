<?php
$schemaFile = __DIR__ . '/current_schema_utf8.sql';
$outputFile = __DIR__ . '/database.sql';

$schema = file_get_contents($schemaFile);

// Remove class_bookings definition block
$schema = preg_replace('/-- Table structure for table `class_bookings`.*?ENGINE=InnoDB[^;]+;/s', '', $schema);

$newTables = "
--
-- Table structure for table `class_bookings`
--
DROP TABLE IF EXISTS `class_bookings`;
CREATE TABLE `class_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schedule_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `status` enum('confirmed','attended','cancelled','no_show') DEFAULT 'confirmed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_booking` (`schedule_id`,`member_id`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `class_bookings_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `class_schedule` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_bookings_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `attendance`
--
DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `check_in` datetime DEFAULT NULL,
  `check_out` datetime DEFAULT NULL,
  `date` date DEFAULT NULL,
  `status` enum('present','absent','late') DEFAULT 'present',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `workouts`
--
DROP TABLE IF EXISTS `workouts`;
CREATE TABLE `workouts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `workout_type` varchar(100) DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `calories_burned` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `workout_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_date` (`user_id`,`created_at`),
  CONSTRAINT `workouts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

// Insert the new tables right before the end of the file
$schema = str_replace('/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;', $newTables . "\n/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;", $schema);

// Ensure SET FOREIGN_KEY_CHECKS=0 is at the top (it's already there in mysqldump)
// Add some DROP TABLEs for safety at the top? It already has them.

file_put_contents($outputFile, $schema);
echo "Database SQL generated successfully.";
