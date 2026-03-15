<?php
// admin/send_bulk_email.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Fix the path - includes folder is outside admin folder
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer if available
$phpmailer_loaded = false;
if (file_exists(__DIR__ . '/vendor/phpmailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/vendor/phpmailer/src/Exception.php';
    require_once __DIR__ . '/vendor/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/vendor/phpmailer/src/SMTP.php';
    $phpmailer_loaded = true;
}

// Now use the Session class from the root includes
Session::requireAdmin();

// Initialize functions
$functions = new Functions();
$error = '';
$success = '';
$preview_mode = isset($_GET['preview']) && $_GET['preview'] == 1;

// Email configuration - Update these with your SMTP settings
$email_config = [
    'use_smtp' => false, // Set to true to use SMTP, false to use mail()
    'smtp_host' => 'smtp.gmail.com', // e.g., smtp.gmail.com
    'smtp_port' => 587, // 587 for TLS, 465 for SSL
    'smtp_secure' => 'tls', // tls or ssl
    'smtp_auth' => true,
    'smtp_username' => 'your-email@gmail.com', // Your email
    'smtp_password' => 'your-app-password', // Your app password or email password
    'from_email' => 'noreply@fittrack.com',
    'from_name' => APP_NAME
];

// For local development, you can use a test mail service
if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1') {
    // In localhost, use mailtrap or similar service
    $email_config['use_smtp'] = true;
    $email_config['smtp_host'] = 'sandbox.smtp.mailtrap.io';
    $email_config['smtp_port'] = 2525;
    $email_config['smtp_username'] = 'your-mailtrap-username';
    $email_config['smtp_password'] = 'your-mailtrap-password';
}

// Get filter parameters
$user_type = $_GET['user_type'] ?? $_POST['user_type'] ?? '';
$status = $_GET['status'] ?? $_POST['status'] ?? 'active';
$membership_type = $_GET['membership_type'] ?? $_POST['membership_type'] ?? '';
$custom_emails = $_POST['custom_emails'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_emails'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $from_email = trim($_POST['from_email']) ?? $email_config['from_email'];
    $from_name = trim($_POST['from_name']) ?? $email_config['from_name'];
    $send_copy = isset($_POST['send_copy']);
    $use_smtp = isset($_POST['use_smtp']) ? true : $email_config['use_smtp'];
    
    // Get recipient list
    $recipients = getRecipientList($pdo, $_POST);
    
    if (empty($recipients)) {
        $error = "No recipients found matching your criteria.";
    } elseif (empty($subject)) {
        $error = "Please enter an email subject.";
    } elseif (empty($message)) {
        $error = "Please enter an email message.";
    } else {
        // Send emails
        $result = sendBulkEmails($recipients, $subject, $message, $from_email, $from_name, $send_copy, $use_smtp, $email_config, $pdo);
        
        if ($result['success'] > 0) {
            $success = "Successfully sent " . $result['success'] . " email(s).";
            if ($result['failed'] > 0) {
                $success .= " " . $result['failed'] . " email(s) failed.";
            }
            
            // Log the activity
            logEmailActivity($pdo, Session::userId(), $subject, count($recipients), $result['success']);
        } else {
            $error = "Failed to send emails. " . ($result['errors'] ? implode(", ", $result['errors']) : "Please check your email configuration.");
        }
    }
}

// Get recipient list based on filters
function getRecipientList($pdo, $filters) {
    $recipients = [];
    
    $user_type = $filters['user_type'] ?? '';
    $status = $filters['status'] ?? 'active';
    $membership_type = $filters['membership_type'] ?? '';
    $custom_emails = $filters['custom_emails'] ?? '';
    
    // Build query for database recipients
    $sql = "SELECT id, full_name, email, user_type FROM users WHERE 1=1";
    $params = [];
    
    if (!empty($user_type) && $user_type != 'all') {
        $sql .= " AND user_type = ?";
        $params[] = $user_type;
    }
    
    if (!empty($status) && $status != 'all') {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    
    if (!empty($membership_type) && $membership_type != 'all') {
        $sql .= " AND id IN (SELECT user_id FROM members WHERE membership_type = ?)";
        $params[] = $membership_type;
    }
    
    // Get users from database
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $recipients = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting recipients: " . $e->getMessage());
    }
    
    // Add custom emails if provided
    if (!empty($custom_emails)) {
        $custom_list = array_map('trim', explode(',', $custom_emails));
        foreach ($custom_list as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = [
                    'id' => null,
                    'full_name' => 'Custom Recipient',
                    'email' => $email,
                    'user_type' => 'custom'
                ];
            }
        }
    }
    
    return $recipients;
}

/**
 * Send bulk emails using PHPMailer or mail()
 */
function sendBulkEmails($recipients, $subject, $message, $from_email, $from_name, $send_copy, $use_smtp, $config, $pdo) {
    global $phpmailer_loaded;
    
    $results = [
        'success' => 0,
        'failed' => 0,
        'errors' => [],
        'details' => []
    ];
    
    foreach ($recipients as $recipient) {
        try {
            $to = $recipient['email'];
            $personalized_message = personalizeMessage($message, $recipient);
            
            if ($use_smtp && $phpmailer_loaded) {
                // Use PHPMailer with SMTP
                $mail = new PHPMailer(true);
                
                // Server settings
                $mail->isSMTP();
                $mail->Host       = $config['smtp_host'];
                $mail->SMTPAuth   = $config['smtp_auth'];
                $mail->Username   = $config['smtp_username'];
                $mail->Password   = $config['smtp_password'];
                $mail->SMTPSecure = $config['smtp_secure'];
                $mail->Port       = $config['smtp_port'];
                
                // Recipients
                $mail->setFrom($from_email, $from_name);
                $mail->addAddress($to, $recipient['full_name']);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $personalized_message;
                $mail->AltBody = strip_tags($personalized_message);
                
                $mail->send();
                $results['success']++;
                
            } else {
                // Fallback to mail() function
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: " . $from_name . " <" . $from_email . ">" . "\r\n";
                
                if (mail($to, $subject, $personalized_message, $headers)) {
                    $results['success']++;
                } else {
                    throw new Exception("mail() function failed");
                }
            }
            
            // Log success
            $results['details'][] = [
                'email' => $to,
                'status' => 'success',
                'name' => $recipient['full_name']
            ];
            
            logEmailInDatabase($pdo, $recipient, $subject, $personalized_message, 'sent');
            
        } catch (Exception $e) {
            $results['failed']++;
            $results['errors'][] = "Failed to send to {$recipient['email']}: " . $e->getMessage();
            $results['details'][] = [
                'email' => $to,
                'status' => 'failed',
                'name' => $recipient['full_name'],
                'error' => $e->getMessage()
            ];
            
            logEmailInDatabase($pdo, $recipient, $subject, $personalized_message, 'failed');
        }
    }
    
    // Send copy to admin if requested
    if ($send_copy && !empty($recipients)) {
        try {
            $admin_copy = "This is a copy of the bulk email sent to " . count($recipients) . " recipients.\n\n";
            $admin_copy .= "Original Message:\n" . $message;
            
            if ($use_smtp && $phpmailer_loaded) {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $config['smtp_host'];
                $mail->SMTPAuth   = $config['smtp_auth'];
                $mail->Username   = $config['smtp_username'];
                $mail->Password   = $config['smtp_password'];
                $mail->SMTPSecure = $config['smtp_secure'];
                $mail->Port       = $config['smtp_port'];
                $mail->setFrom($from_email, $from_name);
                $mail->addAddress($from_email, $from_name);
                $mail->isHTML(false);
                $mail->Subject = "[COPY] " . $subject;
                $mail->Body    = $admin_copy;
                $mail->send();
            } else {
                $headers = "From: " . $from_name . " <" . $from_email . ">" . "\r\n";
                mail($from_email, "[COPY] " . $subject, $admin_copy, $headers);
            }
        } catch (Exception $e) {
            // Silently fail for copy
        }
    }
    
    return $results;
}

/**
 * Personalize message with recipient data
 */
function personalizeMessage($message, $recipient) {
    $replacements = [
        '{name}' => $recipient['full_name'],
        '{first_name}' => explode(' ', $recipient['full_name'])[0],
        '{email}' => $recipient['email'],
        '{user_type}' => ucfirst($recipient['user_type']),
        '{date}' => date('F j, Y'),
        '{app_name}' => APP_NAME
    ];
    
    return str_replace(array_keys($replacements), array_values($replacements), $message);
}

/**
 * Log email in database
 */
function logEmailInDatabase($pdo, $recipient, $subject, $message, $status) {
    try {
        // Check if email_log table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'email_log'");
        if ($stmt->rowCount() == 0) {
            createEmailLogTable($pdo);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO email_log (user_id, recipient_email, recipient_name, subject, message, status, sent_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $recipient['id'],
            $recipient['email'],
            $recipient['full_name'],
            $subject,
            $message,
            $status
        ]);
    } catch (Exception $e) {
        error_log("Error logging email: " . $e->getMessage());
    }
}

/**
 * Create email log table if it doesn't exist
 */
function createEmailLogTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS email_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NULL,
        recipient_email VARCHAR(255) NOT NULL,
        recipient_name VARCHAR(255),
        subject VARCHAR(255),
        message TEXT,
        status ENUM('sent', 'failed') DEFAULT 'sent',
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (sent_at)
    )";
    
    $pdo->exec($sql);
}

/**
 * Log email activity
 */
function logEmailActivity($pdo, $admin_id, $subject, $total, $success) {
    try {
        // Check if activity_log table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'activity_log'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO activity_log (user_id, action, details, created_at) 
                VALUES (?, 'bulk_email', ?, NOW())
            ");
            
            $details = "Sent bulk email: '$subject' to $total recipients ($success successful)";
            $stmt->execute([$admin_id, $details]);
        }
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}

/**
 * Test email configuration
 */
if (isset($_GET['test'])) {
    $test_email = $_GET['test_email'] ?? Session::userEmail();
    $use_smtp = isset($_GET['smtp']) ? true : false;
    
    if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $subject = "Test Email from " . APP_NAME;
        $message = "<h3>Test Email</h3><p>This is a test email to verify your email configuration.</p>";
        
        $test_result = sendTestEmail($test_email, $subject, $message, $use_smtp, $email_config);
        
        if ($test_result['success']) {
            $success = "Test email sent successfully to " . $test_email;
            if (!$use_smtp) {
                $success .= " (using mail() function)";
            } else {
                $success .= " (using SMTP)";
            }
        } else {
            $error = "Failed to send test email: " . $test_result['error'];
        }
    } else {
        $error = "Invalid test email address.";
    }
}

/**
 * Send a test email
 */
function sendTestEmail($to, $subject, $message, $use_smtp, $config) {
    global $phpmailer_loaded;
    
    $result = ['success' => false, 'error' => ''];
    
    try {
        if ($use_smtp && $phpmailer_loaded) {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $config['smtp_host'];
            $mail->SMTPAuth   = $config['smtp_auth'];
            $mail->Username   = $config['smtp_username'];
            $mail->Password   = $config['smtp_password'];
            $mail->SMTPSecure = $config['smtp_secure'];
            $mail->Port       = $config['smtp_port'];
            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = strip_tags($message);
            $mail->send();
            $result['success'] = true;
        } else {
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: " . $config['from_name'] . " <" . $config['from_email'] . ">\r\n";
            
            if (mail($to, $subject, $message, $headers)) {
                $result['success'] = true;
            } else {
                throw new Exception("mail() function failed");
            }
        }
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

// Get counts for statistics
try {
    $total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
    $total_members = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'member' AND status = 'active'")->fetchColumn();
    $total_trainers = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'trainer' AND status = 'active'")->fetchColumn();
    $total_admins = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'admin' AND status = 'active'")->fetchColumn();
    
    // Get membership types for filter
    $membership_types = $pdo->query("SELECT DISTINCT membership_type FROM members WHERE membership_type IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $total_users = $total_members = $total_trainers = $total_admins = 0;
    $membership_types = [];
}

$page_title = 'Send Bulk Email - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-2 d-md-block bg-light sidebar">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">
                            <i class="fas fa-users me-2"></i>Manage Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="members/manage_members.php">
                            <i class="fas fa-user me-2"></i>Members
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_trainers.php">
                            <i class="fas fa-chalkboard-teacher me-2"></i>Trainers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="send_bulk_email.php">
                            <i class="fas fa-envelope me-2"></i>Bulk Email
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="notifications/send_notification.php">
                            <i class="fas fa-bell me-2"></i>Notifications
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="email_log.php">
                            <i class="fas fa-history me-2"></i>Email Log
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-10 ms-sm-auto px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-envelope-open-text me-2"></i>Send Bulk Email
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="dropdown me-2">
                        <button class="btn btn-outline-info dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-vial me-2"></i>Test Email
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?test=1&test_email=<?php echo urlencode(Session::userEmail()); ?>&smtp=0">Test with mail()</a></li>
                            <li><a class="dropdown-item" href="?test=1&test_email=<?php echo urlencode(Session::userEmail()); ?>&smtp=1">Test with SMTP</a></li>
                        </ul>
                    </div>
                    <a href="email_log.php" class="btn btn-outline-secondary">
                        <i class="fas fa-history me-2"></i>View Email Log
                    </a>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Email Configuration Alert -->
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Email Configuration:</strong> 
                <?php if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1'): ?>
                    You're running on localhost. For testing, we recommend using a service like 
                    <a href="https://mailtrap.io" target="_blank">Mailtrap</a> or 
                    <a href="https://github.com/mailhog/MailHog" target="_blank">MailHog</a>.
                    Update the SMTP settings in the configuration.
                <?php else: ?>
                    Update your SMTP settings in the configuration file.
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

            <!-- Quick Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body text-center">
                            <h5><?php echo number_format($total_users); ?></h5>
                            <p class="mb-0">Active Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body text-center">
                            <h5><?php echo number_format($total_members); ?></h5>
                            <p class="mb-0">Active Members</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body text-center">
                            <h5><?php echo number_format($total_trainers); ?></h5>
                            <p class="mb-0">Active Trainers</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body text-center">
                            <h5><?php echo number_format($total_admins); ?></h5>
                            <p class="mb-0">Administrators</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Email Form -->
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="emailTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="compose-tab" data-bs-toggle="tab" href="#compose" role="tab">
                                <i class="fas fa-edit me-2"></i>Compose Email
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="recipients-tab" data-bs-toggle="tab" href="#recipients" role="tab">
                                <i class="fas fa-users me-2"></i>Select Recipients
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="preview-tab" data-bs-toggle="tab" href="#preview" role="tab">
                                <i class="fas fa-eye me-2"></i>Preview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="templates-tab" data-bs-toggle="tab" href="#templates" role="tab">
                                <i class="fas fa-file me-2"></i>Templates
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="config-tab" data-bs-toggle="tab" href="#config" role="tab">
                                <i class="fas fa-cog me-2"></i>Configuration
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body">
                    <form method="POST" action="" id="emailForm">
                        <div class="tab-content">
                            <!-- Compose Tab -->
                            <div class="tab-pane fade show active" id="compose" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="from_name" class="form-label">From Name</label>
                                        <input type="text" class="form-control" id="from_name" name="from_name" 
                                               value="<?php echo htmlspecialchars($email_config['from_name']); ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="from_email" class="form-label">From Email</label>
                                        <input type="email" class="form-control" id="from_email" name="from_email" 
                                               value="<?php echo htmlspecialchars($email_config['from_email']); ?>">
                                    </div>
                                    
                                    <div class="col-12 mb-3">
                                        <label for="subject" class="form-label">Subject *</label>
                                        <input type="text" class="form-control" id="subject" name="subject" 
                                               value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="col-12 mb-3">
                                        <label for="message" class="form-label">Message *</label>
                                        <textarea class="form-control" id="message" name="message" rows="12" required><?php 
                                            echo htmlspecialchars($_POST['message'] ?? getDefaultEmailTemplate()); 
                                        ?></textarea>
                                        <small class="text-muted">
                                            Available placeholders: {name}, {first_name}, {email}, {user_type}, {date}, {app_name}
                                        </small>
                                    </div>
                                    
                                    <div class="col-12">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="send_copy" name="send_copy" checked>
                                            <label class="form-check-label" for="send_copy">
                                                Send a copy to myself
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Recipients Tab -->
                            <div class="tab-pane fade" id="recipients" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="user_type" class="form-label">User Type</label>
                                        <select class="form-select" id="user_type" name="user_type">
                                            <option value="all">All Users</option>
                                            <option value="member" <?php echo $user_type == 'member' ? 'selected' : ''; ?>>Members Only</option>
                                            <option value="trainer" <?php echo $user_type == 'trainer' ? 'selected' : ''; ?>>Trainers Only</option>
                                            <option value="admin" <?php echo $user_type == 'admin' ? 'selected' : ''; ?>>Admins Only</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="all">All Status</option>
                                            <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="membership_type" class="form-label">Membership Type</label>
                                        <select class="form-select" id="membership_type" name="membership_type">
                                            <option value="all">All Memberships</option>
                                            <?php foreach ($membership_types as $type): ?>
                                                <option value="<?php echo $type; ?>" <?php echo $membership_type == $type ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst($type); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-12 mb-3">
                                        <label for="custom_emails" class="form-label">Additional Email Addresses</label>
                                        <textarea class="form-control" id="custom_emails" name="custom_emails" rows="2" 
                                                  placeholder="Enter email addresses separated by commas"><?php echo htmlspecialchars($custom_emails); ?></textarea>
                                        <small class="text-muted">Example: user1@example.com, user2@example.com</small>
                                    </div>
                                    
                                    <div class="col-12">
                                        <button type="button" class="btn btn-info" onclick="previewRecipients()">
                                            <i class="fas fa-search me-2"></i>Preview Recipients
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Recipient Preview -->
                                <div id="recipientPreview" class="mt-4" style="display: none;">
                                    <h6>Recipient Preview:</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Type</th>
                                                </tr>
                                            </thead>
                                            <tbody id="recipientList">
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Preview Tab -->
                            <div class="tab-pane fade" id="preview" role="tabpanel">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h5 id="previewSubject"></h5>
                                        <hr>
                                        <div id="previewMessage" class="p-3 bg-white rounded"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Templates Tab -->
                            <div class="tab-pane fade" id="templates" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6>Welcome Email</h6>
                                                <p class="small text-muted">Send welcome message to new users</p>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadTemplate('welcome')">
                                                    Use Template
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6>Membership Reminder</h6>
                                                <p class="small text-muted">Remind members about expiring membership</p>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadTemplate('reminder')">
                                                    Use Template
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6>Promotion</h6>
                                                <p class="small text-muted">Send promotional offers and discounts</p>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadTemplate('promotion')">
                                                    Use Template
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6>Event Invitation</h6>
                                                <p class="small text-muted">Invite users to gym events</p>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadTemplate('event')">
                                                    Use Template
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6>Holiday Schedule</h6>
                                                <p class="small text-muted">Notify about holiday hours</p>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadTemplate('holiday')">
                                                    Use Template
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6>Maintenance Notice</h6>
                                                <p class="small text-muted">Inform about equipment maintenance</p>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadTemplate('maintenance')">
                                                    Use Template
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Configuration Tab -->
                            <div class="tab-pane fade" id="config" role="tabpanel">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>SMTP Configuration Guide:</strong>
                                    <p class="mb-0 mt-2">To use SMTP, update the following settings in the code:</p>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-header bg-primary text-white">
                                                <h6 class="mb-0">Gmail Configuration</h6>
                                            </div>
                                            <div class="card-body">
                                                <pre class="bg-light p-2">
$email_config = [
    'use_smtp' => true,
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password'
];</pre>
                                                <p class="small text-muted mt-2">
                                                    <i class="fas fa-key me-1"></i> For Gmail, use an App Password if 2FA is enabled.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-header bg-success text-white">
                                                <h6 class="mb-0">Mailtrap (Testing)</h6>
                                            </div>
                                            <div class="card-body">
                                                <pre class="bg-light p-2">
$email_config = [
    'use_smtp' => true,
    'smtp_host' => 'sandbox.smtp.mailtrap.io',
    'smtp_port' => 2525,
    'smtp_username' => 'your-mailtrap-username',
    'smtp_password' => 'your-mailtrap-password'
];</pre>
                                                <p class="small text-muted mt-2">
                                                    <i class="fas fa-flask me-1"></i> Perfect for local development testing.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="use_smtp" name="use_smtp" 
                                           <?php echo $email_config['use_smtp'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="use_smtp">
                                        Use SMTP (recommended) - Uncheck to use PHP mail() function
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" name="send_emails" class="btn btn-primary" onclick="return confirm('Send emails to selected recipients?')">
                                <i class="fas fa-paper-plane me-2"></i>Send Emails
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="previewEmail()">
                                <i class="fas fa-eye me-2"></i>Preview
                            </button>
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php
/**
 * Get default email template
 */
function getDefaultEmailTemplate() {
    return '<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>' . APP_NAME . '</h2>
        </div>
        <div class="content">
            <p>Dear {name},</p>
            <p>This is a message from ' . APP_NAME . '.</p>
            <p>We wanted to reach out and share some important information with you.</p>
            <p>Best regards,<br>The ' . APP_NAME . ' Team</p>
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' ' . APP_NAME . '. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
}
?>

<style>
.sidebar {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 48px 0 0;
    box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
    background-color: #f8f9fa;
    height: 100vh;
}

.sidebar .nav-link {
    font-weight: 500;
    color: #333;
    padding: 0.75rem 1rem;
}

.sidebar .nav-link.active {
    color: #007bff;
    background-color: #e9ecef;
}

.sidebar .nav-link:hover {
    background-color: #e9ecef;
}

.sidebar .nav-link i {
    margin-right: 0.5rem;
    width: 1.5rem;
    text-align: center;
}

.card-header-tabs {
    margin-bottom: -1.5rem;
}

@media (max-width: 768px) {
    .sidebar {
        position: static;
        height: auto;
        padding-top: 0;
    }
}
</style>

<script>
function previewRecipients() {
    var formData = $('#emailForm').serialize();
    
    $.ajax({
        url: 'ajax_preview_recipients.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.recipients && response.recipients.length > 0) {
                var html = '';
                response.recipients.forEach(function(recipient) {
                    html += '<tr>';
                    html += '<td>' + recipient.name + '</td>';
                    html += '<td>' + recipient.email + '</td>';
                    html += '<td>' + recipient.type + '</td>';
                    html += '</tr>';
                });
                
                $('#recipientList').html(html);
                $('#recipientPreview').show();
            } else {
                alert('No recipients found matching your criteria.');
            }
        },
        error: function() {
            alert('Error loading recipients.');
        }
    });
}

function previewEmail() {
    var subject = $('#subject').val();
    var message = $('#message').val();
    var name = 'Sample User';
    
    // Replace placeholders with sample data
    var previewMessage = message
        .replace(/{name}/g, name)
        .replace(/{first_name}/g, name.split(' ')[0])
        .replace(/{email}/g, 'user@example.com')
        .replace(/{user_type}/g, 'Member')
        .replace(/{date}/g, new Date().toLocaleDateString())
        .replace(/{app_name}/g, '<?php echo APP_NAME; ?>');
    
    $('#previewSubject').text('Subject: ' + subject);
    $('#previewMessage').html(previewMessage);
    
    // Switch to preview tab
    $('#preview-tab').tab('show');
}

function loadTemplate(type) {
    var templates = {
        'welcome': `<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        .button { background: #4158D0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Welcome to <?php echo APP_NAME; ?>!</h2>
        </div>
        <div class="content">
            <p>Dear {name},</p>
            <p>Welcome to <?php echo APP_NAME; ?>! We're excited to have you as part of our community.</p>
            <p>Your account has been successfully created. You can now log in and start using all our features.</p>
            <p style="text-align: center; margin: 30px 0;">
                <a href="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/fittrack_version_2/login.php'; ?>" class="button">Login to Your Account</a>
            </p>
            <p>If you have any questions, feel free to contact our support team.</p>
            <p>Best regards,<br>The <?php echo APP_NAME; ?> Team</p>
        </div>
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>`,
        
        'reminder': `<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #FF9800, #F44336); color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        .warning { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Membership Reminder</h2>
        </div>
        <div class="content">
            <p>Dear {name},</p>
            <div class="warning">
                <p><strong>Your membership is expiring soon!</strong></p>
            </div>
            <p>This is a friendly reminder that your membership at <?php echo APP_NAME; ?> will expire soon.</p>
            <p>To ensure uninterrupted access to our facilities and services, please renew your membership at your earliest convenience.</p>
            <p>You can renew online through your member portal or visit our front desk.</p>
            <p>If you have any questions, please don't hesitate to contact us.</p>
            <p>Best regards,<br>The <?php echo APP_NAME; ?> Team</p>
        </div>
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>`,
        
        'promotion': `<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #4CAF50, #8BC34A); color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        .offer { background: #e8f5e8; border: 2px dashed #4CAF50; padding: 20px; text-align: center; border-radius: 10px; }
        .offer h3 { color: #4CAF50; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Special Offer Just for You!</h2>
        </div>
        <div class="content">
            <p>Dear {name},</p>
            <div class="offer">
                <h3>🎉 20% OFF 🎉</h3>
                <p>on your next membership renewal</p>
                <p style="font-size: 24px; font-weight: bold;">Use Code: FITNESS20</p>
            </div>
            <p>As a valued member of <?php echo APP_NAME; ?>, we're offering you an exclusive discount on your next membership renewal.</p>
            <p>This offer is valid until the end of this month. Don't miss out!</p>
            <p>Best regards,<br>The <?php echo APP_NAME; ?> Team</p>
        </div>
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>`,
        
        'event': `<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #9C27B0, #673AB7); color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        .event { background: white; border: 1px solid #ddd; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .event-date { background: #9C27B0; color: white; display: inline-block; padding: 5px 15px; border-radius: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>You're Invited!</h2>
        </div>
        <div class="content">
            <p>Dear {name},</p>
            <div class="event">
                <h3>🏋️ Fitness Workshop</h3>
                <p class="event-date">Saturday, 15th June • 10:00 AM</p>
                <p>Join us for a special fitness workshop led by our expert trainers. Learn new techniques, meet other members, and have fun!</p>
                <p><strong>Location:</strong> Main Studio</p>
                <p><strong>Duration:</strong> 2 hours</p>
                <p>Spaces are limited. Reserve your spot today!</p>
            </div>
            <p>We hope to see you there!</p>
            <p>Best regards,<br>The <?php echo APP_NAME; ?> Team</p>
        </div>
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>`,
        
        'holiday': `<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #FF5722, #FF9800); color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        .holiday { background: #fff3e0; border: 1px solid #ffe0b2; padding: 20px; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Holiday Schedule</h2>
        </div>
        <div class="content">
            <p>Dear {name},</p>
            <div class="holiday">
                <h3>🎄 Holiday Hours</h3>
                <p>Please note our special hours for the upcoming holiday:</p>
                <ul>
                    <li><strong>Monday:</strong> 8:00 AM - 8:00 PM</li>
                    <li><strong>Tuesday (Holiday):</strong> CLOSED</li>
                    <li><strong>Wednesday:</strong> Regular hours resume</li>
                </ul>
                <p>All classes on Tuesday are cancelled. We apologize for any inconvenience.</p>
            </div>
            <p>Wishing you and your family a happy holiday!</p>
            <p>Best regards,<br>The <?php echo APP_NAME; ?> Team</p>
        </div>
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>`,
        
        'maintenance': `<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #607D8B, #455A64); color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        .notice { background: #e3f2fd; border: 1px solid #bbdefb; padding: 20px; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Scheduled Maintenance</h2>
        </div>
        <div class="content">
            <p>Dear {name},</p>
            <div class="notice">
                <h3>🔧 Equipment Maintenance</h3>
                <p>Please be advised that some equipment will be under maintenance:</p>
                <ul>
                    <li><strong>Date:</strong> Monday, 10th June</li>
                    <li><strong>Time:</strong> 9:00 AM - 1:00 PM</li>
                    <li><strong>Equipment:</strong> Treadmills (Section A)</li>
                </ul>
                <p>Alternative equipment will be available in Section B. We appreciate your understanding.</p>
            </div>
            <p>Thank you for your patience while we work to improve your gym experience.</p>
            <p>Best regards,<br>The <?php echo APP_NAME; ?> Team</p>
        </div>
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>`
    };
    
    if (templates[type]) {
        $('#message').val(templates[type]);
        $('#subject').val(getSubjectForTemplate(type));
        $('#compose-tab').tab('show');
    }
}

function getSubjectForTemplate(type) {
    var subjects = {
        'welcome': 'Welcome to <?php echo APP_NAME; ?>!',
        'reminder': 'Membership Expiration Reminder',
        'promotion': 'Special Offer Just for You!',
        'event': 'You\'re Invited: Fitness Workshop',
        'holiday': 'Holiday Schedule Notice',
        'maintenance': 'Scheduled Maintenance Notice'
    };
    
    return subjects[type] || 'Message from <?php echo APP_NAME; ?>';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>