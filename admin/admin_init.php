<?php
/**
 * FitTrack Admin Initializer Script
 * Run this script once to create the initial admin user
 * 
 * WARNING: Delete this file after running for security reasons!
 */

// Fix the path - go up one directory to find config
$root_path = dirname(__DIR__); // This goes up to fittrack_version_2/
require_once $root_path . '/config/database.php';

// Admin credentials
$username = 'admin';
$password = 'admin123';
$email = 'admin@fittrack.com';
$full_name = 'System Administrator';
$user_type = 'admin';

// Colors for terminal output (if running from command line)
if (php_sapi_name() === 'cli') {
    $green = "\033[32m";
    $red = "\033[31m";
    $yellow = "\033[33m";
    $reset = "\033[0m";
} else {
    $green = $red = $yellow = $reset = '';
    
    // Add basic HTML styling for web output
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>FitTrack Admin Initializer</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; line-height: 1.6; background: #f4f4f4; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .success { color: green; background: #d4edda; padding: 15px; border-radius: 5px; border-left: 4px solid green; }
            .error { color: red; background: #f8d7da; padding: 15px; border-radius: 5px; border-left: 4px solid red; }
            .warning { color: orange; background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid orange; }
            .info { background: #e7f3ff; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; }
            .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; 
                   text-decoration: none; border-radius: 5px; border: none; cursor: pointer; margin-right: 10px; }
            .btn-warning { background: #ffc107; color: #000; }
            .btn-danger { background: #dc3545; }
            .credentials { background: #f8f9fa; padding: 20px; border-radius: 5px; font-family: monospace; }
            hr { border: 1px solid #eee; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>";
}

echo "\n";
echo "========================================\n";
echo "   FitTrack Admin Initializer Script    \n";
echo "========================================\n\n";

try {
    // Check connection first
    if (!isset($pdo)) {
        throw new Exception("Database connection failed. Please check your database configuration.");
    }
    
    // Check if admin already exists
    $stmt = $pdo->prepare("SELECT id, user_type FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    
    if ($stmt->rowCount() > 0) {
        $existing = $stmt->fetch();
        
        if ($existing['user_type'] === 'admin') {
            echo "<div class='warning'>";
            echo "⚠ Admin user already exists!\n\n";
            echo "Username: {$username}\n";
            echo "Email: {$email}\n";
            echo "Password: [hidden for security]\n\n";
            echo "</div>";
            
            // Option to reset password
            if (php_sapi_name() !== 'cli') {
                echo "<form method='POST' style='margin-top: 20px;'>";
                echo "<input type='hidden' name='action' value='reset'>";
                echo "<button type='submit' name='reset_password' class='btn btn-warning'>Reset Admin Password</button>";
                echo "</form>";
                
                if (isset($_POST['reset_password'])) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $update->execute([$hashed_password, $existing['id']]);
                    echo "<div class='success'>✓ Password reset successfully! New password: {$password}</div>";
                }
            }
        } else {
            echo "<div class='error'>";
            echo "✗ Username or email already exists but is not an admin!\n";
            echo "Please choose different credentials or delete the existing user.\n";
            echo "</div>";
        }
    } else {
        // Create admin user
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, full_name, user_type, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'active', NOW())
        ");
        
        if ($stmt->execute([$username, $email, $hashed_password, $full_name, $user_type])) {
            $admin_id = $pdo->lastInsertId();
            
            echo "<div class='success'>";
            echo "✅ Admin user created successfully!\n\n";
            echo "</div>";
            
            echo "<div class='credentials'>";
            echo "<strong>Admin Details:</strong><br>";
            echo "--------------<br>";
            echo "ID: {$admin_id}<br>";
            echo "Username: {$username}<br>";
            echo "Email: {$email}<br>";
            echo "Password: {$password}<br>";
            echo "Full Name: {$full_name}<br>";
            echo "User Type: {$user_type}<br>";
            echo "</div>";
            
            echo "<div class='warning'>";
            echo "⚠ IMPORTANT: Please change the password after first login!<br>";
            echo "⚠ For security, delete this file after use!<br>";
            echo "</div>";
            
            // Create login link for web interface
            if (php_sapi_name() !== 'cli') {
                echo "<div style='margin-top: 30px;'>";
                echo "<a href='../login.php' class='btn'>Go to Login Page</a>";
                echo "<a href='delete_this_file.php' onclick='return confirm(\"Are you sure you want to delete this file? Make sure admin is working first!\")' class='btn btn-danger'>Delete This File</a>";
                echo "</div>";
            }
        } else {
            echo "<div class='error'>";
            echo "✗ Failed to create admin user!\n";
            echo "</div>";
        }
    }
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "Database Error: " . $e->getMessage() . "\n";
    echo "</div>";
    
    // Helpful troubleshooting
    echo "<div class='info'>";
    echo "<strong>Troubleshooting Tips:</strong><br>";
    echo "1. Make sure your database is running<br>";
    echo "2. Check if database 'fittrack' exists<br>";
    echo "3. Verify database credentials in config/database.php<br>";
    echo "4. Path being used: " . $root_path . "/config/database.php<br>";
    echo "</div>";
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "Error: " . $e->getMessage() . "\n";
    echo "</div>";
}

echo "\n";

if (php_sapi_name() !== 'cli') {
    echo "</div></body></html>";
}
?>