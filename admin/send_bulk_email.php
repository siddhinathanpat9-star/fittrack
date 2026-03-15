<?php
// admin/send_bulk_email.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Path to root includes folder
$root_includes = __DIR__ . '/../includes/';

// Include core files
require_once $root_includes . 'config.php';
require_once $root_includes . 'session.php';
require_once $root_includes . 'functions.php';

// Load Composer autoloader (adjust path if needed)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    die('Composer autoloader not found. Please run "composer require phpmailer/phpmailer".');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

Session::requireAdmin();

$functions = new Functions();
$error = '';
$success = '';

// ==================== SMTP CONFIGURATION ====================
// Replace these with your Gmail credentials and app password
$smtp_config = [
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'secure'     => 'tls',                // 'tls' for 587, 'ssl' for 465
    'username'   => 'ajayjamale2@gmail.com',   // Your full Gmail address
    'password'   => 'waky trsz wjid olhz', // Your generated app password
    'from_email' => 'ajayjamale2@gmail.com',
    'from_name'  => APP_NAME
];
// ============================================================

// Helper: get list of recipients based on filters
function getRecipientList($pdo, $filters) {
    $recipients = [];
    $user_type = $filters['user_type'] ?? '';
    $status = $filters['status'] ?? 'active';
    $membership_type = $filters['membership_type'] ?? '';
    $custom_emails = $filters['custom_emails'] ?? '';

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

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $recipients = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting recipients: " . $e->getMessage());
    }

    // Add custom emails
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

// Helper: personalize message with placeholders
function personalizeMessage($message, $recipient) {
    $replacements = [
        '{name}'       => $recipient['full_name'],
        '{first_name}' => explode(' ', $recipient['full_name'])[0],
        '{email}'      => $recipient['email'],
        '{user_type}'  => ucfirst($recipient['user_type']),
        '{date}'       => date('F j, Y'),
        '{app_name}'   => APP_NAME
    ];
    return str_replace(array_keys($replacements), array_values($replacements), $message);
}

// Helper: send a single email via PHPMailer
function sendSingleEmail($recipient, $subject, $body, $config) {
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
        $mail->CharSet    = 'UTF-8';

        // Recipients
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($recipient['email'], $recipient['full_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

// Helper: log email to database (optional)
function logEmailToDb($pdo, $recipient, $subject, $body, $status) {
    try {
        $pdo->query("CREATE TABLE IF NOT EXISTS email_log (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NULL,
            recipient_email VARCHAR(255) NOT NULL,
            recipient_name VARCHAR(255),
            subject VARCHAR(255),
            message TEXT,
            status ENUM('sent','failed') DEFAULT 'sent',
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $stmt = $pdo->prepare("INSERT INTO email_log (user_id, recipient_email, recipient_name, subject, message, status) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $recipient['id'],
            $recipient['email'],
            $recipient['full_name'],
            $subject,
            $body,
            $status
        ]);
    } catch (Exception $e) {
        // ignore logging errors
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_emails'])) {
    $subject   = trim($_POST['subject']);
    $message   = trim($_POST['message']);
    $from_email = trim($_POST['from_email']) ?: $smtp_config['from_email'];
    $from_name  = trim($_POST['from_name']) ?: $smtp_config['from_name'];
    $send_copy  = isset($_POST['send_copy']);

    // Get recipients based on filters
    $recipients = getRecipientList($pdo, $_POST);

    if (empty($recipients)) {
        $error = "No recipients found matching your criteria.";
    } elseif (empty($subject)) {
        $error = "Please enter an email subject.";
    } elseif (empty($message)) {
        $error = "Please enter an email message.";
    } else {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];
        foreach ($recipients as $rec) {
            $personalized = personalizeMessage($message, $rec);
            $result = sendSingleEmail($rec, $subject, $personalized, $smtp_config);
            if ($result['success']) {
                $results['success']++;
                logEmailToDb($pdo, $rec, $subject, $personalized, 'sent');
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed to {$rec['email']}: " . $result['error'];
                logEmailToDb($pdo, $rec, $subject, $personalized, 'failed');
            }
        }

        // Send copy to admin if requested
        if ($send_copy && !empty($recipients)) {
            $admin_copy = "This is a copy of the bulk email sent to " . count($recipients) . " recipients.\n\nOriginal Message:\n" . $message;
            $admin_mail = new PHPMailer(true);
            try {
                $admin_mail->isSMTP();
                $admin_mail->Host       = $smtp_config['host'];
                $admin_mail->SMTPAuth   = true;
                $admin_mail->Username   = $smtp_config['username'];
                $admin_mail->Password   = $smtp_config['password'];
                $admin_mail->SMTPSecure = $smtp_config['secure'];
                $admin_mail->Port       = $smtp_config['port'];
                $admin_mail->setFrom($from_email, $from_name);
                $admin_mail->addAddress($smtp_config['from_email'], $from_name);
                $admin_mail->isHTML(false);
                $admin_mail->Subject = "[COPY] " . $subject;
                $admin_mail->Body    = $admin_copy;
                $admin_mail->send();
            } catch (Exception $e) {
                // ignore copy errors
            }
        }

        if ($results['success'] > 0) {
            $success = "Successfully sent " . $results['success'] . " email(s).";
            if ($results['failed'] > 0) {
                $success .= " " . $results['failed'] . " email(s) failed.";
            }
        } else {
            $error = "Failed to send any emails. " . implode(" ", $results['errors']);
        }
    }
}

// Test email functionality
if (isset($_GET['test'])) {
    $test_email = $_GET['test_email'] ?? Session::userEmail();
    if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $result = sendSingleEmail(
            ['email' => $test_email, 'full_name' => 'Test User'],
            'Test Email from ' . APP_NAME,
            '<h3>Test</h3><p>Your SMTP configuration works!</p>',
            $smtp_config
        );
        if ($result['success']) {
            $success = "Test email sent successfully to $test_email";
        } else {
            $error = "Test email failed: " . $result['error'];
        }
    } else {
        $error = "Invalid test email address.";
    }
}

// Fetch statistics for counts (optional)
try {
    $total_users   = $pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
    $total_members = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type='member' AND status='active'")->fetchColumn();
    $total_trainers= $pdo->query("SELECT COUNT(*) FROM users WHERE user_type='trainer' AND status='active'")->fetchColumn();
    $total_admins  = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type='admin' AND status='active'")->fetchColumn();
} catch (Exception $e) {
    $total_users = $total_members = $total_trainers = $total_admins = 0;
}

$user_name = Session::userName();
$page_title = 'Send Bulk Email - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.stats-card small{opacity:.9}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:15px 20px;transition:.3s}.list-group-item:last-child{border-bottom:none}.list-group-item:hover{background:#f8f9fa;transform:translateX(5px)}.list-group-item i{color:#667eea;margin-right:10px}.chart-container{position:relative;height:300px;margin:20px 0}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}.stats-card h2{font-size:1.8rem}}
    </style>
</head>
<body>
    <div class="loading-spinner" id="loadingSpinner"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Administrator Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li>
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                </li>
                <li>
                    <a href="#membersSubmenu" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-users"></i> Members <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="membersSubmenu">
                        <li><a href="members/manage_members.php"><i class="fas fa-list"></i> All Members</a></li>
                        <li><a href="members/add_member.php"><i class="fas fa-user-plus"></i> Add Member</a></li>
                        <li><a href="membership/membership_plans.php"><i class="fas fa-tag"></i> Membership Plans</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#trainersSubmenu" data-toggle="collapse">
                        <i class="fas fa-user-tie"></i> Trainers <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="trainersSubmenu">
                        <li><a href="manage_trainers.php"><i class="fas fa-list"></i> All Trainers</a></li>
                        <li><a href="trainers/add_trainer.php"><i class="fas fa-user-plus"></i> Add Trainer</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#classesSubmenu" data-toggle="collapse">
                        <i class="fas fa-calendar-alt"></i> Classes <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="classesSubmenu">
                        <li><a href="classes/manage_classes.php"><i class="fas fa-list"></i> All Classes</a></li>
                        <li><a href="classes/add_class.php"><i class="fas fa-plus-circle"></i> Add Class</a></li>
                        <li><a href="classes/class_schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#paymentsSubmenu" data-toggle="collapse">
                        <i class="fas fa-credit-card"></i> Payments <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="paymentsSubmenu">
                        <li><a href="payments/manage_payments.php"><i class="fas fa-list"></i> Manage Payments</a></li>
                        <li><a href="payments/record_payment.php"><i class="fas fa-plus-circle"></i> Record Payment</a></li>
                        <li><a href="payments/payment_reports.php"><i class="fas fa-chart-bar"></i> Payment Reports</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#notificationsSubmenu" data-toggle="collapse" aria-expanded="true">
                        <i class="fas fa-bell"></i> Communications <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse show list-unstyled" id="notificationsSubmenu">
                        <li class="active"><a href="send_bulk_email.php"><i class="fas fa-envelope"></i> Bulk Email</a></li>
                        <li><a href="notifications/send_notification.php"><i class="fas fa-bell"></i> Send Notification</a></li>
                    </ul>
                </li>
                <li><a href="attendance.php"><i class="fas fa-clock"></i> Attendance</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
            <div class="sidebar-footer">
                <a href="#" onclick="confirmLogout(event)" class="btn btn-danger btn-block"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-custom">
                <button type="button" id="sidebarCollapse" class="btn btn-outline-primary"><i class="fas fa-bars"></i> Menu</button>
                <div class="ml-auto">
                    <!-- Notifications dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown"><i class="fas fa-bell"></i><span class="badge badge-danger badge-pill">3</span></button>
                        <div class="dropdown-menu dropdown-menu-right" style="width:300px">
                            <div class="dropdown-header bg-light">Notifications</div>
                            <a class="dropdown-item" href="#"><strong>New member registered</strong><br><small class="text-muted">2 minutes ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Payment received</strong><br><small class="text-muted">1 hour ago</small></a>
                            <a class="dropdown-item" href="#"><strong>5 memberships expiring soon</strong><br><small class="text-muted">3 hours ago</small></a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-center" href="#">View all</a>
                        </div>
                    </div>
                    <!-- User dropdown -->
                    <div class="dropdown ml-3 d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown"><i class="fas fa-user-circle fa-lg"></i><span class="ml-2"><?php echo htmlspecialchars($user_name); ?></span></button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                            <a class="dropdown-item" href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php Session::displayFlash(); ?>

                <!-- Page Header -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header">
                            <h1><i class="fas fa-envelope-open-text"></i> Send Bulk Email <small>Send emails to users</small></h1>
                        </div>
                    </div>
                </div>

                <!-- Quick stats row (stats-card style) -->
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-primary text-white">
                            <div class="card-body">
                                <div class="card-title">Active Users</div>
                                <h2><?php echo $total_users; ?></h2>
                                <i class="fas fa-users"></i>
                                <small>Total active</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <div class="card-title">Active Members</div>
                                <h2><?php echo $total_members; ?></h2>
                                <i class="fas fa-user"></i>
                                <small>Members</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <div class="card-title">Active Trainers</div>
                                <h2><?php echo $total_trainers; ?></h2>
                                <i class="fas fa-chalkboard-teacher"></i>
                                <small>Trainers</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-warning text-white">
                            <div class="card-body">
                                <div class="card-title">Admins</div>
                                <h2><?php echo $total_admins; ?></h2>
                                <i class="fas fa-user-cog"></i>
                                <small>Administrators</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Compose Email Card -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-edit"></i> Compose Email</h5>
                        <a href="?test=1&test_email=<?php echo urlencode(Session::userEmail()); ?>" class="btn btn-outline-info btn-sm" onclick="return confirm('Send test email to yourself?')"><i class="fas fa-vial"></i> Test Config</a>
                    </div>
                    <div class="card-body">
                        <form method="post" id="emailForm">
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>From Name</label>
                                    <input type="text" name="from_name" class="form-control" value="<?php echo htmlspecialchars($smtp_config['from_name']); ?>">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>From Email</label>
                                    <input type="email" name="from_email" class="form-control" value="<?php echo htmlspecialchars($smtp_config['from_email']); ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Subject *</label>
                                <input type="text" name="subject" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Message * (HTML allowed)</label>
                                <textarea name="message" class="form-control" rows="10" required><?php echo htmlspecialchars(getDefaultTemplate()); ?></textarea>
                                <small class="text-muted">Placeholders: {name}, {first_name}, {email}, {user_type}, {date}, {app_name}</small>
                            </div>

                            <h5 class="mt-4">Recipient Filters</h5>
                            <div class="row">
                                <div class="col-md-3 form-group">
                                    <label>User Type</label>
                                    <select name="user_type" class="form-control">
                                        <option value="all">All</option>
                                        <option value="member">Members</option>
                                        <option value="trainer">Trainers</option>
                                        <option value="admin">Admins</option>
                                    </select>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Status</label>
                                    <select name="status" class="form-control">
                                        <option value="all">All</option>
                                        <option value="active" selected>Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Membership (if member)</label>
                                    <select name="membership_type" class="form-control">
                                        <option value="all">All</option>
                                        <?php
                                        try {
                                            $types = $pdo->query("SELECT DISTINCT membership_type FROM members")->fetchAll(PDO::FETCH_COLUMN);
                                            foreach ($types as $type) echo "<option value=\"$type\">".ucfirst($type)."</option>";
                                        } catch (Exception $e) {}
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Custom Emails (comma separated)</label>
                                    <input type="text" name="custom_emails" class="form-control" placeholder="user@example.com, other@example.com">
                                </div>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="send_copy" id="sendCopy" checked>
                                <label class="form-check-label" for="sendCopy">Send a copy to myself</label>
                            </div>

                            <button type="submit" name="send_emails" class="btn btn-primary btn-lg" onclick="return confirm('Send emails to selected recipients?')"><i class="fas fa-paper-plane mr-2"></i>Send Emails</button>
                            <button type="button" class="btn btn-secondary btn-lg" onclick="previewMessage()"><i class="fas fa-eye mr-2"></i>Preview</button>
                        </form>
                    </div>
                </div>

                <!-- Preview Card (hidden by default) -->
                <div class="card mt-4" id="previewCard" style="display:none;">
                    <div class="card-header">
                        <h5><i class="fas fa-eye"></i> Preview</h5>
                    </div>
                    <div class="card-body" id="previewContent"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="logoutModalLabel"><i class="fas fa-sign-out-alt"></i> Confirm Logout</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to logout?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times"></i> Cancel</button>
                    <a href="../logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#sidebarCollapse').on('click', function() {
                $('#sidebar').toggleClass('active');
            });

            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });

        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }

        function previewMessage() {
            let subject = document.querySelector('[name=subject]').value;
            let message = document.querySelector('[name=message]').value;
            let preview = document.getElementById('previewContent');
            preview.innerHTML = '<h5>Subject: ' + subject + '</h5><hr>' + message;
            document.getElementById('previewCard').style.display = 'block';
        }
    </script>
</body>
</html>
<?php
function getDefaultTemplate() {
    return '<!DOCTYPE html>
<html><head><style>body{font-family:Arial;line-height:1.6}</style></head>
<body>
    <h2>' . APP_NAME . '</h2>
    <p>Dear {name},</p>
    <p>This is a message from ' . APP_NAME . '.</p>
    <p>Best regards,<br>The ' . APP_NAME . ' Team</p>
</body>
</html>';
}
?>