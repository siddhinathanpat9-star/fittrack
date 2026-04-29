<?php
$file = __DIR__ . '/database.sql';
$content = file_get_contents($file);
$content = str_replace('/*!40101 SET character_set_client = @saved_cs_client */;', '', $content);
file_put_contents($file, $content);
echo "Fixed database.sql\n";
