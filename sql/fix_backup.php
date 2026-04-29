<?php
$file = __DIR__ . '/data_backup_utf8.sql';
$content = file_get_contents($file);
$content = preg_replace('/-- Table structure for table `class_bookings`.*?UNLOCK TABLES;/s', '', $content);
$content = preg_replace('/-- Dumping data for table `class_bookings`.*?UNLOCK TABLES;/s', '', $content);
file_put_contents($file, $content);
echo "Fixed data_backup_utf8.sql\n";
