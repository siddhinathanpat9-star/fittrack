<?php
/**
 * Custom autoloader (bypasses Composer's PHP version check)
 * 
 * WARNING: This file is a modified version of Composer's autoload.php.
 * It removes the PHP version guard. Ensure your dependencies actually
 * support your PHP version, otherwise unexpected errors may occur.
 */

// Check if the real autoloader exists
$autoloadReal = __DIR__ . '/composer/autoload_real.php';
if (!file_exists($autoloadReal)) {
    die("Composer autoload_real.php not found. Please run 'composer install'.");
}

require_once $autoloadReal;

// Return the loader instance
return ComposerAutoloaderInit2185d2f99bcd56787481d9357a5972d3::getLoader();