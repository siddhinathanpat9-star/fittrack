<?php
// Simple script to create an admin account
$host = 'localhost';
$dbname = 'fittrack';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Default admin credentials
    $adminUsername = 'admin';
    $adminEmail = 'admin@fittrack.com';
    $adminPassword = 'password123';
    
    // Hash password
    $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);

    // Check if admin already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$adminUsername, $adminEmail]);
    
    if ($stmt->rowCount() > 0) {
        // Update password if exists
        $stmt = $pdo->prepare("UPDATE users SET password = ?, user_type = 'admin' WHERE username = ? OR email = ?");
        $stmt->execute([$hashedPassword, $adminUsername, $adminEmail]);
        echo "Admin account already exists. Password reset to 'password123'.\n";
    } else {
        // Insert new admin
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, user_type, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
        $stmt->execute([
            $adminUsername,
            $adminEmail,
            $hashedPassword,
            'System Administrator'
        ]);
        echo "Admin account created successfully!\n";
    }
    
    echo "Username: $adminUsername\n";
    echo "Email: $adminEmail\n";
    echo "Password: $adminPassword\n";

} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
