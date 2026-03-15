<?php
// includes/config.php
// FitTrack Gym Management System - Main Configuration File

// ------------------------------------------------------------------------
// Error Reporting (disable in production)
// ------------------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ------------------------------------------------------------------------
// Session Management
// ------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ------------------------------------------------------------------------
// Database Configuration
// ------------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'fittrack');
define('DB_USER', 'root');
define('DB_PASS', '');

// ------------------------------------------------------------------------
// Application Settings
// ------------------------------------------------------------------------
define('APP_NAME', 'FitTrack Gym');
define('APP_VERSION', '2.0');
define('TIMEZONE', 'Asia/Kolkata');
date_default_timezone_set(TIMEZONE);

// ------------------------------------------------------------------------
// Base URL (auto-detected with fallback)
// ------------------------------------------------------------------------
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = rtrim(dirname($scriptPath), '/\\') . '/';
    define('BASE_URL', $protocol . $host . $dir);
}

// ------------------------------------------------------------------------
// SMTP Configuration (for password reset emails)
// ------------------------------------------------------------------------
// IMPORTANT: Replace these placeholders with your actual SMTP credentials.
// For Gmail, generate an App Password at https://support.google.com/accounts/answer/185833
define('SMTP_HOST', 'smtp.gmail.com');          // SMTP server address
define('SMTP_PORT', 587);                        // Port (587 for TLS, 465 for SSL)
define('SMTP_SECURE', 'tls');                    // Encryption: 'tls' or 'ssl'
define('SMTP_USERNAME', 'your-email@gmail.com'); // SMTP username (full email)
define('SMTP_PASSWORD', 'your-app-password');    // SMTP password (app password for Gmail)
define('SMTP_FROM_EMAIL', 'noreply@fittrack.com'); // Default from address
define('SMTP_FROM_NAME', APP_NAME);               // Default from name

// ------------------------------------------------------------------------
// Razorpay Configuration (for online payments)
// ------------------------------------------------------------------------
// Get your API keys from https://dashboard.razorpay.com
// Use test keys (rzp_test_...) for development, live keys (rzp_live_...) for production
define('RAZORPAY_KEY_ID', 'rzp_test_xxxxxxxxxxxx');      // Replace with your actual Key ID
define('RAZORPAY_KEY_SECRET', 'xxxxxxxxxxxxxxxx');       // Replace with your actual Key Secret
define('RAZORPAY_WEBHOOK_SECRET', 'xxxxxxxxxxxxxxxx');   // Optional, for webhook verification

// ------------------------------------------------------------------------
// Composer Autoload (loads PHPMailer, Razorpay SDK, and other dependencies)
// ------------------------------------------------------------------------
require_once __DIR__ . '/../vendor/autoload.php';

// ------------------------------------------------------------------------
// Database Connection (PDO)
// ------------------------------------------------------------------------
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // Log error securely in production
    error_log("Database connection failed: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}

// ------------------------------------------------------------------------
// Email Helper Functions (using PHPMailer)
// ------------------------------------------------------------------------

/**
 * Send an email using PHPMailer with the configured SMTP settings.
 *
 * @param string $to      Recipient email address
 * @param string $subject Email subject
 * @param string $body    HTML body content
 * @param string $altBody Plain text alternative (optional)
 * @return bool           True on success, false on failure
 */
function sendEmail($to, $subject, $body, $altBody = '') {
    // Verify that all required SMTP constants are defined
    $requiredConstants = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_USERNAME', 'SMTP_PASSWORD'];
    foreach ($requiredConstants as $const) {
        if (!defined($const)) {
            error_log("Missing SMTP constant: $const");
            return false;
        }
    }

    // Check if PHPMailer is available
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("PHPMailer class not found. Make sure vendor/autoload.php is loaded.");
        return false;
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
        $mail->Port       = defined('SMTP_PORT') ? SMTP_PORT : 587;
        $mail->Timeout    = 30; // seconds

        // Optional debug (uncomment for troubleshooting)
        // $mail->SMTPDebug = 2;
        // $mail->Debugoutput = 'html';

        // Recipients
        $mail->setFrom(
            defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : SMTP_USERNAME,
            defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : APP_NAME
        );
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);

        $mail->send();
        return true;
    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log("PHPMailer Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send a password reset email.
 *
 * @param string $email Recipient email
 * @param string $token Reset token
 * @param string $type  User type (admin/trainer/member) – for display only
 * @return bool         True on success, false on failure
 */
function sendPasswordResetEmail($email, $token, $type = 'member') {
    $reset_link = BASE_URL . "reset_password.php?token=" . urlencode($token) . "&type=" . urlencode($type);
    $subject = "Password Reset Request - " . APP_NAME;

    $body = "<!DOCTYPE html>
    <html>
    <head><title>Password Reset</title></head>
    <body style='font-family: Arial, sans-serif;'>
        <h2>Password Reset Request</h2>
        <p>You requested a password reset. Click the link below to reset your password:</p>
        <p><a href='{$reset_link}'>{$reset_link}</a></p>
        <p>This link will expire in 1 hour.</p>
        <p>If you did not request this, please ignore this email. No changes have been made to your account.</p>
        <hr>
        <p style='color: #666; font-size: 0.9em;'>This is an automated message from " . APP_NAME . ". Please do not reply.</p>
    </body>
    </html>";

    $altBody = "You requested a password reset. Copy this link into your browser: {$reset_link}\n\nThis link expires in 1 hour.\n\nIf you did not request this, please ignore this email.";

    return sendEmail($email, $subject, $body, $altBody);
}