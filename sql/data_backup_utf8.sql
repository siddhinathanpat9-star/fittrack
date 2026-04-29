-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: fittrack
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--


--
-- Dumping data for table `class_bookings_old`
--

LOCK TABLES `class_bookings_old` WRITE;
/*!40000 ALTER TABLE `class_bookings_old` DISABLE KEYS */;
/*!40000 ALTER TABLE `class_bookings_old` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `class_schedule`
--

LOCK TABLES `class_schedule` WRITE;
/*!40000 ALTER TABLE `class_schedule` DISABLE KEYS */;
/*!40000 ALTER TABLE `class_schedule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `email_log`
--

LOCK TABLES `email_log` WRITE;
/*!40000 ALTER TABLE `email_log` DISABLE KEYS */;
INSERT INTO `email_log` VALUES (1,1,'admin@fittrack.com','System Admin','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html>\r\n<head>\r\n    <style>\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n        .header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; text-align: center; }\r\n        .content { padding: 20px; background: #f9f9f9; }\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n        .button { background: #4158D0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"container\">\r\n        <div class=\"header\">\r\n            <h2>Welcome to FitTrack Gym!</h2>\r\n        </div>\r\n        <div class=\"content\">\r\n            <p>Dear System Admin,</p>\r\n            <p>Welcome to FitTrack Gym! We\'re excited to have you as part of our community.</p>\r\n            <p>Your account has been successfully created. You can now log in and start using all our features.</p>\r\n            <p style=\"text-align: center; margin: 30px 0;\">\r\n                <a href=\"http://localhost/fittrack_version_2/login.php\" class=\"button\">Login to Your Account</a>\r\n            </p>\r\n            <p>If you have any questions, feel free to contact our support team.</p>\r\n            <p>Best regards,<br>The FitTrack Gym Team</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>&copy; 2026 FitTrack Gym. All rights reserved.</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>','failed','2026-02-26 05:09:36'),(2,2,'ds6277792@gmail.com','Devanand Sonawne','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html>\r\n<head>\r\n    <style>\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n        .header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; text-align: center; }\r\n        .content { padding: 20px; background: #f9f9f9; }\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n        .button { background: #4158D0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"container\">\r\n        <div class=\"header\">\r\n            <h2>Welcome to FitTrack Gym!</h2>\r\n        </div>\r\n        <div class=\"content\">\r\n            <p>Dear Devanand Sonawne,</p>\r\n            <p>Welcome to FitTrack Gym! We\'re excited to have you as part of our community.</p>\r\n            <p>Your account has been successfully created. You can now log in and start using all our features.</p>\r\n            <p style=\"text-align: center; margin: 30px 0;\">\r\n                <a href=\"http://localhost/fittrack_version_2/login.php\" class=\"button\">Login to Your Account</a>\r\n            </p>\r\n            <p>If you have any questions, feel free to contact our support team.</p>\r\n            <p>Best regards,<br>The FitTrack Gym Team</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>&copy; 2026 FitTrack Gym. All rights reserved.</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>','failed','2026-02-26 05:09:38'),(3,1,'admin@fittrack.com','System Admin','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html>\r\n<head>\r\n    <style>\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n        .header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; text-align: center; }\r\n        .content { padding: 20px; background: #f9f9f9; }\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n        .button { background: #4158D0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"container\">\r\n        <div class=\"header\">\r\n            <h2>Welcome to FitTrack Gym!</h2>\r\n        </div>\r\n        <div class=\"content\">\r\n            <p>Dear System Admin,</p>\r\n            <p>Welcome to FitTrack Gym! We\'re excited to have you as part of our community.</p>\r\n            <p>Your account has been successfully created. You can now log in and start using all our features.</p>\r\n            <p style=\"text-align: center; margin: 30px 0;\">\r\n                <a href=\"http://localhost/fittrack_version_2/login.php\" class=\"button\">Login to Your Account</a>\r\n            </p>\r\n            <p>If you have any questions, feel free to contact our support team.</p>\r\n            <p>Best regards,<br>The FitTrack Gym Team</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>&copy; 2026 FitTrack Gym. All rights reserved.</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>','failed','2026-02-26 05:09:55'),(4,2,'ds6277792@gmail.com','Devanand Sonawne','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html>\r\n<head>\r\n    <style>\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n        .header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; text-align: center; }\r\n        .content { padding: 20px; background: #f9f9f9; }\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n        .button { background: #4158D0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"container\">\r\n        <div class=\"header\">\r\n            <h2>Welcome to FitTrack Gym!</h2>\r\n        </div>\r\n        <div class=\"content\">\r\n            <p>Dear Devanand Sonawne,</p>\r\n            <p>Welcome to FitTrack Gym! We\'re excited to have you as part of our community.</p>\r\n            <p>Your account has been successfully created. You can now log in and start using all our features.</p>\r\n            <p style=\"text-align: center; margin: 30px 0;\">\r\n                <a href=\"http://localhost/fittrack_version_2/login.php\" class=\"button\">Login to Your Account</a>\r\n            </p>\r\n            <p>If you have any questions, feel free to contact our support team.</p>\r\n            <p>Best regards,<br>The FitTrack Gym Team</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>&copy; 2026 FitTrack Gym. All rights reserved.</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>','failed','2026-02-26 05:09:57'),(5,1,'admin@fittrack.com','System Admin','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html>\r\n<head>\r\n    <style>\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n        .header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; text-align: center; }\r\n        .content { padding: 20px; background: #f9f9f9; }\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n        .button { background: #4158D0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"container\">\r\n        <div class=\"header\">\r\n            <h2>Welcome to FitTrack Gym!</h2>\r\n        </div>\r\n        <div class=\"content\">\r\n            <p>Dear System Admin,</p>\r\n            <p>Welcome to FitTrack Gym! We\'re excited to have you as part of our community.</p>\r\n            <p>Your account has been successfully created. You can now log in and start using all our features.</p>\r\n            <p style=\"text-align: center; margin: 30px 0;\">\r\n                <a href=\"http://localhost/fittrack_version_2/login.php\" class=\"button\">Login to Your Account</a>\r\n            </p>\r\n            <p>If you have any questions, feel free to contact our support team.</p>\r\n            <p>Best regards,<br>The FitTrack Gym Team</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>&copy; 2026 FitTrack Gym. All rights reserved.</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>','failed','2026-02-26 05:10:47'),(6,2,'ds6277792@gmail.com','Devanand Sonawne','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html>\r\n<head>\r\n    <style>\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n        .header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; text-align: center; }\r\n        .content { padding: 20px; background: #f9f9f9; }\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n        .button { background: #4158D0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"container\">\r\n        <div class=\"header\">\r\n            <h2>Welcome to FitTrack Gym!</h2>\r\n        </div>\r\n        <div class=\"content\">\r\n            <p>Dear Devanand Sonawne,</p>\r\n            <p>Welcome to FitTrack Gym! We\'re excited to have you as part of our community.</p>\r\n            <p>Your account has been successfully created. You can now log in and start using all our features.</p>\r\n            <p style=\"text-align: center; margin: 30px 0;\">\r\n                <a href=\"http://localhost/fittrack_version_2/login.php\" class=\"button\">Login to Your Account</a>\r\n            </p>\r\n            <p>If you have any questions, feel free to contact our support team.</p>\r\n            <p>Best regards,<br>The FitTrack Gym Team</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>&copy; 2026 FitTrack Gym. All rights reserved.</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>','failed','2026-02-26 05:10:49'),(7,1,'admin@fittrack.com','System Admin','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html>\r\n<head>\r\n    <style>\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n        .header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; text-align: center; }\r\n        .content { padding: 20px; background: #f9f9f9; }\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n        .button { background: #4158D0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"container\">\r\n        <div class=\"header\">\r\n            <h2>Welcome to FitTrack Gym!</h2>\r\n        </div>\r\n        <div class=\"content\">\r\n            <p>Dear System Admin,</p>\r\n            <p>Welcome to FitTrack Gym! We\'re excited to have you as part of our community.</p>\r\n            <p>Your account has been successfully created. You can now log in and start using all our features.</p>\r\n            <p style=\"text-align: center; margin: 30px 0;\">\r\n                <a href=\"http://localhost/fittrack_version_2/login.php\" class=\"button\">Login to Your Account</a>\r\n            </p>\r\n            <p>If you have any questions, feel free to contact our support team.</p>\r\n            <p>Best regards,<br>The FitTrack Gym Team</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>&copy; 2026 FitTrack Gym. All rights reserved.</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>','failed','2026-02-26 05:11:04'),(8,2,'ds6277792@gmail.com','Devanand Sonawne','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html>\r\n<head>\r\n    <style>\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n        .header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; text-align: center; }\r\n        .content { padding: 20px; background: #f9f9f9; }\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n        .button { background: #4158D0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"container\">\r\n        <div class=\"header\">\r\n            <h2>Welcome to FitTrack Gym!</h2>\r\n        </div>\r\n        <div class=\"content\">\r\n            <p>Dear Devanand Sonawne,</p>\r\n            <p>Welcome to FitTrack Gym! We\'re excited to have you as part of our community.</p>\r\n            <p>Your account has been successfully created. You can now log in and start using all our features.</p>\r\n            <p style=\"text-align: center; margin: 30px 0;\">\r\n                <a href=\"http://localhost/fittrack_version_2/login.php\" class=\"button\">Login to Your Account</a>\r\n            </p>\r\n            <p>If you have any questions, feel free to contact our support team.</p>\r\n            <p>Best regards,<br>The FitTrack Gym Team</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>&copy; 2026 FitTrack Gym. All rights reserved.</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>','failed','2026-02-26 05:11:06'),(9,1,'admin@fittrack.com','System Admin','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html>\r\n<head>\r\n    <style>\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n        .header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; text-align: center; }\r\n        .content { padding: 20px; background: #f9f9f9; }\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n        .button { background: #4158D0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"container\">\r\n        <div class=\"header\">\r\n            <h2>Welcome to FitTrack Gym!</h2>\r\n        </div>\r\n        <div class=\"content\">\r\n            <p>Dear System Admin,</p>\r\n            <p>Welcome to FitTrack Gym! We\'re excited to have you as part of our community.</p>\r\n            <p>Your account has been successfully created. You can now log in and start using all our features.</p>\r\n            <p style=\"text-align: center; margin: 30px 0;\">\r\n                <a href=\"http://localhost/fittrack_version_2/login.php\" class=\"button\">Login to Your Account</a>\r\n            </p>\r\n            <p>If you have any questions, feel free to contact our support team.</p>\r\n            <p>Best regards,<br>The FitTrack Gym Team</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>&copy; 2026 FitTrack Gym. All rights reserved.</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>','failed','2026-02-26 05:16:49'),(10,2,'ds6277792@gmail.com','Devanand Sonawne','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html>\r\n<head>\r\n    <style>\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n        .header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; text-align: center; }\r\n        .content { padding: 20px; background: #f9f9f9; }\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n        .button { background: #4158D0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"container\">\r\n        <div class=\"header\">\r\n            <h2>Welcome to FitTrack Gym!</h2>\r\n        </div>\r\n        <div class=\"content\">\r\n            <p>Dear Devanand Sonawne,</p>\r\n            <p>Welcome to FitTrack Gym! We\'re excited to have you as part of our community.</p>\r\n            <p>Your account has been successfully created. You can now log in and start using all our features.</p>\r\n            <p style=\"text-align: center; margin: 30px 0;\">\r\n                <a href=\"http://localhost/fittrack_version_2/login.php\" class=\"button\">Login to Your Account</a>\r\n            </p>\r\n            <p>If you have any questions, feel free to contact our support team.</p>\r\n            <p>Best regards,<br>The FitTrack Gym Team</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>&copy; 2026 FitTrack Gym. All rights reserved.</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>','failed','2026-02-26 05:16:51'),(11,1,'admin@fittrack.com','System Admin','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html>\r\n<head>\r\n    <style>\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n        .header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; text-align: center; }\r\n        .content { padding: 20px; background: #f9f9f9; }\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"container\">\r\n        <div class=\"header\">\r\n            <h2>FitTrack Gym</h2>\r\n        </div>\r\n        <div class=\"content\">\r\n            <p>Dear System Admin,</p>\r\n            <p>This is a message from FitTrack Gym.</p>\r\n            <p>We wanted to reach out and share some important information with you.</p>\r\n            <p>Best regards,<br>The FitTrack Gym Team</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>&copy; 2026 FitTrack Gym. All rights reserved.</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>','failed','2026-02-26 05:17:32'),(12,2,'ds6277792@gmail.com','Devanand Sonawne','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html>\r\n<head>\r\n    <style>\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n        .header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; text-align: center; }\r\n        .content { padding: 20px; background: #f9f9f9; }\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"container\">\r\n        <div class=\"header\">\r\n            <h2>FitTrack Gym</h2>\r\n        </div>\r\n        <div class=\"content\">\r\n            <p>Dear Devanand Sonawne,</p>\r\n            <p>This is a message from FitTrack Gym.</p>\r\n            <p>We wanted to reach out and share some important information with you.</p>\r\n            <p>Best regards,<br>The FitTrack Gym Team</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>&copy; 2026 FitTrack Gym. All rights reserved.</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>','failed','2026-02-26 05:17:34'),(13,2,'ds6277792@gmail.com','Devanand Sonawne','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html>\r\n<head>\r\n    <style>\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n        .header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; text-align: center; }\r\n        .content { padding: 20px; background: #f9f9f9; }\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"container\">\r\n        <div class=\"header\">\r\n            <h2>FitTrack Gym</h2>\r\n        </div>\r\n        <div class=\"content\">\r\n            <p>Dear Devanand Sonawne,</p>\r\n            <p>This is a message from FitTrack Gym.</p>\r\n            <p>We wanted to reach out and share some important information with you.</p>\r\n            <p>Best regards,<br>The FitTrack Gym Team</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>&copy; 2026 FitTrack Gym. All rights reserved.</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>','failed','2026-02-26 06:55:34'),(14,3,'siddhinathanpat9@gmail.com','Siddhinath Anpat','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html>\r\n<head>\r\n    <style>\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n        .header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; text-align: center; }\r\n        .content { padding: 20px; background: #f9f9f9; }\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"container\">\r\n        <div class=\"header\">\r\n            <h2>FitTrack Gym</h2>\r\n        </div>\r\n        <div class=\"content\">\r\n            <p>Dear Siddhinath Anpat,</p>\r\n            <p>This is a message from FitTrack Gym.</p>\r\n            <p>We wanted to reach out and share some important information with you.</p>\r\n            <p>Best regards,<br>The FitTrack Gym Team</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>&copy; 2026 FitTrack Gym. All rights reserved.</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>','failed','2026-02-26 06:55:36'),(15,2,'ds6277792@gmail.com','Devanand Sonawne','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html>\r\n<head>\r\n    <style>\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n        .header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; text-align: center; }\r\n        .content { padding: 20px; background: #f9f9f9; }\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"container\">\r\n        <div class=\"header\">\r\n            <h2>FitTrack Gym</h2>\r\n        </div>\r\n        <div class=\"content\">\r\n            <p>Dear Devanand Sonawne,</p>\r\n            <p>This is a message from FitTrack Gym.</p>\r\n            <p>We wanted to reach out and share some important information with you.</p>\r\n            <p>Best regards,<br>The FitTrack Gym Team</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>&copy; 2026 FitTrack Gym. All rights reserved.</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>','failed','2026-02-26 07:04:06'),(16,3,'siddhinathanpat9@gmail.com','Siddhinath Anpat','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html>\r\n<head>\r\n    <style>\r\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n        .header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; text-align: center; }\r\n        .content { padding: 20px; background: #f9f9f9; }\r\n        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n    </style>\r\n</head>\r\n<body>\r\n    <div class=\"container\">\r\n        <div class=\"header\">\r\n            <h2>FitTrack Gym</h2>\r\n        </div>\r\n        <div class=\"content\">\r\n            <p>Dear Siddhinath Anpat,</p>\r\n            <p>This is a message from FitTrack Gym.</p>\r\n            <p>We wanted to reach out and share some important information with you.</p>\r\n            <p>Best regards,<br>The FitTrack Gym Team</p>\r\n        </div>\r\n        <div class=\"footer\">\r\n            <p>&copy; 2026 FitTrack Gym. All rights reserved.</p>\r\n        </div>\r\n    </div>\r\n</body>\r\n</html>','failed','2026-02-26 07:04:08'),(17,1,'admin@fittrack.com','System Admin','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html><head><style>body{font-family:Arial;line-height:1.6}</style></head>\r\n<body>\r\n    <h2>FitTrack Gym</h2>\r\n    <p>Dear System Admin,</p>\r\n    <p>This is a message from FitTrack Gym.</p>\r\n    <p>Best regards,<br>The FitTrack Gym Team</p>\r\n</body>\r\n</html>','sent','2026-02-26 07:30:50'),(18,2,'ds6277792@gmail.com','Devanand Sonawne','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html><head><style>body{font-family:Arial;line-height:1.6}</style></head>\r\n<body>\r\n    <h2>FitTrack Gym</h2>\r\n    <p>Dear Devanand Sonawne,</p>\r\n    <p>This is a message from FitTrack Gym.</p>\r\n    <p>Best regards,<br>The FitTrack Gym Team</p>\r\n</body>\r\n</html>','sent','2026-02-26 07:30:56'),(19,3,'siddhinathanpat9@gmail.com','Siddhinath Anpat','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html><head><style>body{font-family:Arial;line-height:1.6}</style></head>\r\n<body>\r\n    <h2>FitTrack Gym</h2>\r\n    <p>Dear Siddhinath Anpat,</p>\r\n    <p>This is a message from FitTrack Gym.</p>\r\n    <p>Best regards,<br>The FitTrack Gym Team</p>\r\n</body>\r\n</html>','sent','2026-02-26 07:31:01'),(20,NULL,'siddhinathanpat9@gmail.com','Custom Recipient','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html><head><style>body{font-family:Arial;line-height:1.6}</style></head>\r\n<body>\r\n    <h2>FitTrack Gym</h2>\r\n    <p>Dear Custom Recipient,</p>\r\n    <p>This is a message from FitTrack Gym.</p>\r\n    <p>Best regards,<br>The FitTrack Gym Team</p>\r\n</body>\r\n</html>','sent','2026-02-26 07:31:06'),(21,1,'admin@fittrack.com','System Admin','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html><head><style>body{font-family:Arial;line-height:1.6}</style></head>\r\n<body>\r\n    <h2>FitTrack Gym</h2>\r\n    <p>Dear System Admin,</p>\r\n    <p>This is a message from FitTrack Gym.</p>\r\n    <p>Best regards,<br>The FitTrack Gym Team</p>\r\n</body>\r\n</html>','sent','2026-03-14 18:47:35'),(22,2,'ds6277792@gmail.com','Devanand Sonawne','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html><head><style>body{font-family:Arial;line-height:1.6}</style></head>\r\n<body>\r\n    <h2>FitTrack Gym</h2>\r\n    <p>Dear Devanand Sonawne,</p>\r\n    <p>This is a message from FitTrack Gym.</p>\r\n    <p>Best regards,<br>The FitTrack Gym Team</p>\r\n</body>\r\n</html>','sent','2026-03-14 18:47:43'),(23,3,'siddhinathanpat9@gmail.com','Siddhinath Anpat','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html><head><style>body{font-family:Arial;line-height:1.6}</style></head>\r\n<body>\r\n    <h2>FitTrack Gym</h2>\r\n    <p>Dear Siddhinath Anpat,</p>\r\n    <p>This is a message from FitTrack Gym.</p>\r\n    <p>Best regards,<br>The FitTrack Gym Team</p>\r\n</body>\r\n</html>','sent','2026-03-14 18:47:48'),(24,4,'sidhuanpat07@gmail.com','Rohit Dilip Gore','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html><head><style>body{font-family:Arial;line-height:1.6}</style></head>\r\n<body>\r\n    <h2>FitTrack Gym</h2>\r\n    <p>Dear Rohit Dilip Gore,</p>\r\n    <p>This is a message from FitTrack Gym.</p>\r\n    <p>Best regards,<br>The FitTrack Gym Team</p>\r\n</body>\r\n</html>','sent','2026-03-14 18:47:53'),(25,5,'ompatil123@gmail.com','om patil','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html><head><style>body{font-family:Arial;line-height:1.6}</style></head>\r\n<body>\r\n    <h2>FitTrack Gym</h2>\r\n    <p>Dear om patil,</p>\r\n    <p>This is a message from FitTrack Gym.</p>\r\n    <p>Best regards,<br>The FitTrack Gym Team</p>\r\n</body>\r\n</html>','sent','2026-03-14 18:47:57'),(26,NULL,'siddhinathanpat9@gmail.com','Custom Recipient','Welcome to FitTrack Gym!','<!DOCTYPE html>\r\n<html><head><style>body{font-family:Arial;line-height:1.6}</style></head>\r\n<body>\r\n    <h2>FitTrack Gym</h2>\r\n    <p>Dear Custom Recipient,</p>\r\n    <p>This is a message from FitTrack Gym.</p>\r\n    <p>Best regards,<br>The FitTrack Gym Team</p>\r\n</body>\r\n</html>','sent','2026-03-14 18:48:02');
/*!40000 ALTER TABLE `email_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `equipment`
--

LOCK TABLES `equipment` WRITE;
/*!40000 ALTER TABLE `equipment` DISABLE KEYS */;
/*!40000 ALTER TABLE `equipment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `exercises`
--

LOCK TABLES `exercises` WRITE;
/*!40000 ALTER TABLE `exercises` DISABLE KEYS */;
INSERT INTO `exercises` VALUES (1,'Push Up','Basic chest exercise','Chest','None','beginner',NULL,'2026-02-25 16:10:23'),(2,'Pull Up','Back and biceps exercise','Back','Pull up bar','intermediate',NULL,'2026-02-25 16:10:23'),(3,'Squat','Leg exercise','Legs','None','beginner',NULL,'2026-02-25 16:10:23'),(4,'Deadlift','Full body exercise','Full Body','Barbell','advanced',NULL,'2026-02-25 16:10:23'),(5,'Bench Press','Chest exercise','Chest','Barbell, Bench','intermediate',NULL,'2026-02-25 16:10:23'),(6,'Shoulder Press','Shoulder exercise','Shoulders','Dumbbells','intermediate',NULL,'2026-02-25 16:10:23');
/*!40000 ALTER TABLE `exercises` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `members`
--

LOCK TABLES `members` WRITE;
/*!40000 ALTER TABLE `members` DISABLE KEYS */;
INSERT INTO `members` VALUES (1,4,'basic','2026-02-26','2026-03-26',169.00,70.00,'build musules','3121321231','5656656565',2),(2,5,'basic','2026-03-12','2026-04-12',NULL,NULL,NULL,NULL,NULL,3);
/*!40000 ALTER TABLE `members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `membership_plans`
--

LOCK TABLES `membership_plans` WRITE;
/*!40000 ALTER TABLE `membership_plans` DISABLE KEYS */;
INSERT INTO `membership_plans` VALUES (1,'Basic','Access to gym equipment and locker rooms',800.00,30,'Gym access, Locker room, Cardio equipment',0,0,0,0,2,0,0,0,'active','2026-02-25 17:19:13',0),(2,'Premium','All Basic features + group classes',1000.00,30,'Gym access, Locker room, Group classes, Sauna access',0,0,0,0,5,0,1,0,'active','2026-02-25 17:19:13',0),(3,'VIP','All Premium features + personal training',1200.00,30,'Gym access, Locker room, Group classes, Personal training, Diet plan, Massage',0,0,0,0,10,0,4,1,'active','2026-02-25 17:19:13',0);
/*!40000 ALTER TABLE `membership_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
INSERT INTO `messages` VALUES (1,2,4,'Welcome to FitTrack Gym!','welcome',0,NULL,'2026-03-14 02:47:59'),(2,2,4,'Welcome to FitTrack Gym!','thanks to join the gym',0,NULL,'2026-03-15 06:18:19');
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `notification_recipients`
--

LOCK TABLES `notification_recipients` WRITE;
/*!40000 ALTER TABLE `notification_recipients` DISABLE KEYS */;
INSERT INTO `notification_recipients` VALUES (1,1,1,0,NULL),(2,1,2,0,NULL),(3,1,4,0,NULL),(4,1,3,0,NULL);
/*!40000 ALTER TABLE `notification_recipients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,1,'system maintenance','aaasdd','success','2026-02-28 08:20:16');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (2,5,1200.00,'2026-03-14 05:11:51','',NULL,NULL,NULL,'VIP Plan',NULL,'paid',NULL,NULL,'2026-03-15 12:54:38','2026-03-15 12:54:38'),(5,5,800.00,'2026-03-15 07:57:01','',NULL,NULL,NULL,'Basic Plan',NULL,'paid',NULL,'pay_SRR7afemp5TzyZ','2026-03-15 12:54:38','2026-03-15 12:54:38'),(6,5,800.00,'2026-03-15 08:01:07','',NULL,NULL,NULL,'Basic Plan',NULL,'paid',NULL,NULL,'2026-03-15 12:54:38','2026-03-15 12:54:38'),(7,1,999.00,'2026-03-15 08:06:32','',NULL,NULL,NULL,NULL,NULL,'paid',NULL,'pay_ABC123XYZ','2026-03-15 12:54:38','2026-03-15 12:54:38'),(8,4,800.00,'2026-03-16 14:55:23','',NULL,NULL,NULL,'Basic Membership',NULL,'paid',NULL,NULL,'2026-03-16 14:55:23','2026-03-16 14:55:23'),(9,5,800.00,'2026-03-16 15:17:02','',NULL,NULL,NULL,'Basic Membership',NULL,'paid',NULL,NULL,'2026-03-16 15:17:02','2026-03-16 15:17:02'),(10,5,1000.00,'2026-03-16 15:23:19','',NULL,NULL,NULL,'Premium Membership',NULL,'paid',NULL,NULL,'2026-03-16 15:23:19','2026-03-16 15:23:19'),(11,5,1200.00,'2026-03-16 15:29:24','',NULL,NULL,NULL,'VIP Membership',NULL,'paid',NULL,NULL,'2026-03-16 15:29:24','2026-03-16 15:29:24'),(12,5,1000.00,'2026-03-16 15:32:37','',NULL,NULL,NULL,'Premium Membership',NULL,'paid',NULL,NULL,'2026-03-16 15:32:37','2026-03-16 15:32:37'),(13,1,500.00,'2026-03-16 15:34:54','Razorpay',NULL,NULL,NULL,'Test Plan',NULL,'paid',NULL,NULL,'2026-03-16 15:34:54','2026-03-16 15:34:54'),(14,5,800.00,'2026-03-16 15:43:38','Razorpay',NULL,NULL,NULL,'Basic Membership',NULL,'paid',NULL,NULL,'2026-03-16 15:43:38','2026-03-16 15:43:38'),(15,5,1200.00,'2026-03-18 14:35:59','Razorpay',NULL,NULL,NULL,'VIP Membership',NULL,'paid',NULL,NULL,'2026-03-18 14:35:59','2026-03-18 14:35:59');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `progress_tracking`
--

LOCK TABLES `progress_tracking` WRITE;
/*!40000 ALTER TABLE `progress_tracking` DISABLE KEYS */;
/*!40000 ALTER TABLE `progress_tracking` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('date_format','M d, Y','Date display format','text','system','2026-02-26 10:53:39','2026-02-26 10:53:39'),('default_membership_days','30','Default membership duration in days','number','membership','2026-02-26 10:53:39','2026-02-26 10:53:39'),('default_membership_price','29.99','Default membership price','number','membership','2026-02-26 10:53:39','2026-02-26 10:53:39'),('enable_registration','1','Allow new user registration','boolean','membership','2026-02-26 10:53:39','2026-02-26 10:53:39'),('gym_address','123 Fitness Street, City, State 12345','Physical address','textarea','general','2026-02-26 10:53:39','2026-02-26 10:53:39'),('gym_email','info@fittrack.com','General contact email','email','general','2026-02-26 10:53:39','2026-02-26 10:53:39'),('gym_name','FitTrack Gym','Name of the gym','text','general','2026-02-26 10:53:39','2026-02-26 10:53:39'),('gym_phone','+1 234 567 890','Contact phone number','text','general','2026-02-26 10:53:39','2026-02-26 10:53:39'),('gym_website','https://www.fittrack.com','Website URL','url','general','2026-02-26 10:53:39','2026-02-26 10:53:39'),('items_per_page','20','Number of items per page in listings','number','system','2026-02-26 10:53:39','2026-02-26 10:53:39'),('smtp_from_email','noreply@fittrack.com','Default from email','email','email','2026-02-26 10:53:39','2026-02-26 10:53:39'),('smtp_from_name','FitTrack Gym','Default from name','text','email','2026-02-26 10:53:39','2026-02-26 10:53:39'),('smtp_host','','SMTP server host','text','email','2026-02-26 10:53:39','2026-02-26 10:53:39'),('smtp_password','','SMTP password','text','email','2026-02-26 10:53:39','2026-02-26 10:53:39'),('smtp_port','587','SMTP port','number','email','2026-02-26 10:53:39','2026-02-26 10:53:39'),('smtp_secure','tls','SMTP encryption (tls/ssl)','text','email','2026-02-26 10:53:39','2026-02-26 10:53:39'),('smtp_username','','SMTP username','text','email','2026-02-26 10:53:39','2026-02-26 10:53:39'),('timezone','America/New_York','Default timezone','text','system','2026-02-26 10:53:39','2026-02-26 10:53:39'),('time_format','h:i A','Time display format','text','system','2026-02-26 10:53:39','2026-02-26 10:53:39');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `trainers`
--

LOCK TABLES `trainers` WRITE;
/*!40000 ALTER TABLE `trainers` DISABLE KEYS */;
INSERT INTO `trainers` VALUES (1,2,'Weight Tranner',2,'','',100.00),(2,3,'yoga',5,'sport kotha','6 am to 9 pm',150.00);
/*!40000 ALTER TABLE `trainers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin@fittrack.com','$2y$10$GtzMw2jUt3hgBhwXJ1UD..BBb.JOhHa1lfLM01pa1n1xTa/8N0AVO','System Admin',NULL,NULL,'admin',NULL,'2026-02-25 15:39:48','2026-02-25 15:56:42','active'),(2,'deva','ds6277792@gmail.com','$2y$10$4Aq3spcrlIwLOEwErjSVouXznq5301UJJ0VBckU.nsy1HrqPGY/cS','Devanand Sonawne','7856945625','K.tadawale','trainer',NULL,'2026-02-25 16:12:13','2026-02-25 16:12:13','active'),(3,'sidhu','siddhinathanpat9@gmail.com','$2y$10$64GWaT33kDkx.TmrAcH3cefnTq2n3areeKN3UjN2qwIZUATe5oPZe','Siddhinath Anpat','08308759124','K.tadawale','trainer',NULL,'2026-02-26 06:53:37','2026-03-17 07:59:03','active'),(4,'rohit','sidhuanpat7@gmail.com','$2y$10$2UHfag5f5Gf/tgh0F2rHiejrdUPkBw5pCkL0pNkn46m87NEWATTWK','Rohit Dilip Gore','9209317553','Ktadawale','member',NULL,'2026-02-26 09:56:48','2026-03-16 09:06:26','active'),(5,'om','ompatil123@gmail.com','$2y$10$y7FLVOPQTLJgpyYYFgZpquC/B744SPBHn6PizyJnczxU3Fm9SaZiG','om patil','9508747591',NULL,'member',NULL,'2026-03-12 09:56:31','2026-03-12 09:56:31','active');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `workout_plans`
--

LOCK TABLES `workout_plans` WRITE;
/*!40000 ALTER TABLE `workout_plans` DISABLE KEYS */;
/*!40000 ALTER TABLE `workout_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `workouts`
--

LOCK TABLES `workouts` WRITE;
/*!40000 ALTER TABLE `workouts` DISABLE KEYS */;
/*!40000 ALTER TABLE `workouts` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-29 20:40:55
