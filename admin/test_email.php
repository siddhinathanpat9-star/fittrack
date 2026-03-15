<?php
// admin/test_email.php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Your SMTP configuration
$config = [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'secure' => 'tls',
    'username' => 'ajayjamale2@gmail.com',
    'password' => 'waky trsz wjid olhz',
    'from_email' => 'ajayjamale2@gmail.com',
    'from_name' => 'FitTrack Test'
];

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = $config['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['username'];
    $mail->Password   = $config['password'];
    $mail->SMTPSecure = $config['secure'];
    $mail->Port       = $config['port'];
    
    // Recipients
    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($config['from_email'], 'Your Name'); // Send to yourself
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'PHPMailer Test';
    $mail->Body    = '<h1>Test Successful!</h1><p>Your PHPMailer configuration is working correctly.</p>';
    
    $mail->send();
    echo 'Test email sent successfully!';
} catch (Exception $e) {
    echo "Test failed: {$mail->ErrorInfo}";
}