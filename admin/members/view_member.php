<?php
/**
 * admin/members/view_member.php - View member details
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

// Ensure only admin can access
Session::requireAdmin();

$error = '';

// Get member ID from URL (this is the user ID)
$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($member_id <= 0) {
    header('Location: manage_members.php');
    exit();
}

// Helper: detect foreign key column for a table
function getForeignKey($pdo, $table, $preferred = ['user_id', 'member_id', 'trainer_id']) {
    $cols = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($preferred as $col) {
        if (in_array($col, $cols)) {
            return $col;
        }
    }
    return 'user_id'; // fallback
}

try {
    // Fetch user and member data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND user_type = 'member'");
    $stmt->execute([$member_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error = "Member not found.";
    } else {
        // Fetch member record
        $member_stmt = $pdo->prepare("SELECT * FROM members WHERE user_id = ?");
        $member_stmt->execute([$member_id]);
        $member = $member_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($member) {
            $user = array_merge($user, $member);
            
            // Fetch membership plan
            $plan_id = isset($member['membership_plan_id']) ? $member['membership_plan_id'] : 
                       (isset($member['plan_id']) ? $member['plan_id'] : null);
            if ($plan_id) {
                $plan_stmt = $pdo->prepare("SELECT * FROM membership_plans WHERE id = ?");
                $plan_stmt->execute([$plan_id]);
                $plan = $plan_stmt->fetch(PDO::FETCH_ASSOC);
                if ($plan) $user = array_merge($user, $plan);
            }
            
            // Determine foreign key columns
            $payments_fk = getForeignKey($pdo, 'payments');
            $attendance_fk = getForeignKey($pdo, 'attendance');
            $class_bookings_fk = getForeignKey($pdo, 'class_bookings');
            
            // Fetch payments
            $payments_stmt = $pdo->prepare("SELECT * FROM payments WHERE $payments_fk = ? ORDER BY payment_date DESC LIMIT 5");
            $payments_stmt->execute([$user['id']]);
            $recent_payments = $payments_stmt->fetchAll();
            
            // Fetch attendance
            $attendance_stmt = $pdo->prepare("SELECT * FROM attendance WHERE $attendance_fk = ? ORDER BY date DESC LIMIT 5");
            $attendance_stmt->execute([$user['id']]);
            $recent_attendance = $attendance_stmt->fetchAll();
            
            // Fetch class bookings with dynamic schedule table detection
            $recent_bookings = [];
            $scheduleTable = null;
            $tables = ['class_schedule', 'class_schedules'];
            foreach ($tables as $table) {
                $check = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($check && $check->rowCount() > 0) {
                    $scheduleTable = $table;
                    break;
                }
            }
            
            if ($scheduleTable) {
                $bookings_stmt = $pdo->prepare("
                    SELECT cb.*, cs.day_of_week, cs.start_time, cs.end_time, c.class_name
                    FROM class_bookings cb
                    JOIN $scheduleTable cs ON cb.schedule_id = cs.schedule_id
                    JOIN classes c ON cs.class_id = c.class_id
                    WHERE cb.$class_bookings_fk = ?
                    ORDER BY cb.booking_date DESC
                    LIMIT 5
                ");
                $bookings_stmt->execute([$user['id']]);
                $recent_bookings = $bookings_stmt->fetchAll();
            }
        } else {
            $error = "Member record not found for this user.";
        }
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $user = null;
}

$page_title = 'View Member - ' . APP_NAME;
$user_name = Session::userName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* (Same styles as provided in view_user.php – kept exactly) */
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border:none;border-radius:8px;padding:8px 20px}.btn-primary:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(102,126,234,0.4)}.btn-outline-primary{border-color:#667eea;color:#667eea}.btn-outline-primary:hover{background:#667eea;color:#fff}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}.avatar-lg{width:120px;height:120px;border-radius:50%;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;display:flex;align-items:center;justify-content:center;font-size:3rem;font-weight:bold;margin:0 auto 20px}.info-row{display:flex;padding:10px 0;border-bottom:1px solid #e9ecef}.info-label{width:140px;font-weight:600;color:#555}.info-value{flex:1;color:#333}.section-title{font-size:1.2rem;font-weight:600;color:#333;margin-bottom:15px;padding-bottom:10px;border-bottom:2px solid #667eea;display:inline-block}.action-buttons{margin-top:20px}.action-buttons .btn{margin:0 5px}.nav-tabs .nav-link{color:#555;font-weight:500}.nav-tabs .nav-link.active{color:#667eea;border-bottom-color:#667eea}.tab-pane{padding:20px 0}
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Administrator Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="../manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li class="active"><a href="manage_members.php"><i class="fas fa-user"></i> Members</a></li>
                <li><a href="../manage_trainers.php"><i class="fas fa-chalkboard-teacher"></i> Trainers</a></li>
                <li><a href="../classes/manage_classes.php"><i class="fas fa-calendar-alt"></i> Classes</a></li>
                <li><a href="../payments/manage_payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li><a href="../membership/membership_plans.php"><i class="fas fa-id-card"></i> Membership Plans</a></li>
                <li><a href="../notifications/send_notification.php"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="../reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../settings.php"><i class="fas fa-cog"></i> Settings</a></li>
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
                    <div class="dropdown ml-3 d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown"><i class="fas fa-user-circle fa-lg"></i><span class="ml-2"><?php echo htmlspecialchars($user_name); ?></span></button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="../profile.php"><i class="fas fa-user"></i> My Profile</a>
                            <a class="dropdown-item" href="../settings.php"><i class="fas fa-cog"></i> Settings</a>
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
                <?php Session::displayFlash(); ?>

                <!-- Page Header -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header">
                            <h1><i class="fas fa-user-circle"></i> Member Details <small>Viewing member #<?php echo $member_id; ?></small></h1>
                        </div>
                    </div>
                </div>

                <?php if ($user): ?>
                <div class="row">
                    <div class="col-lg-4">
                        <!-- Profile Card -->
                        <div class="card">
                            <div class="card-body text-center">
                                <div class="avatar-lg">
                                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                </div>
                                <h3 class="mt-3"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                                <p class="text-muted">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?><br>
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?>
                                </p>
                                <span class="badge badge-<?php echo ($user['status'] ?? '') == 'active' ? 'success' : 'danger'; ?> px-3 py-2">
                                    <?php echo ucfirst($user['status'] ?? 'Inactive'); ?>
                                </span>
                                <span class="badge badge-info px-3 py-2 ml-2">
                                    Member
                                </span>
                                <div class="action-buttons">
                                    <a href="../edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="manage_members.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
                                </div>
                            </div>
                        </div>

                        <!-- Account Info Card -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5><i class="fas fa-info-circle"></i> Account Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="info-row">
                                    <div class="info-label">Username:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['username'] ?? ''); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Email:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Phone:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Address:</div>
                                    <div class="info-value"><?php echo nl2br(htmlspecialchars($user['address'] ?? 'Not provided')); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Joined:</div>
                                    <div class="info-value"><?php echo isset($user['created_at']) ? date('F d, Y', strtotime($user['created_at'])) : 'N/A'; ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Last Login:</div>
                                    <div class="info-value"><?php echo (isset($user['last_login']) && $user['last_login']) ? date('F d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Emergency Contact -->
                        <?php if (!empty($user['emergency_name'])): ?>
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5><i class="fas fa-ambulance"></i> Emergency Contact</h5>
                            </div>
                            <div class="card-body">
                                <div class="info-row">
                                    <div class="info-label">Name:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['emergency_name']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Relationship:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['emergency_relationship'] ?? 'Not specified'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Phone:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['emergency_contact']); ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-lg-8">
                        <!-- Tabs for member-specific details -->
                        <div class="card">
                            <div class="card-header">
                                <ul class="nav nav-tabs card-header-tabs" id="memberTabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="membership-tab" data-toggle="tab" href="#membership" role="tab">Membership</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="payments-tab" data-toggle="tab" href="#payments" role="tab">Payments</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="attendance-tab" data-toggle="tab" href="#attendance" role="tab">Attendance</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="classes-tab" data-toggle="tab" href="#classes" role="tab">Classes</a>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content">
                                    <!-- Membership Tab -->
                                    <div class="tab-pane fade show active" id="membership" role="tabpanel">
                                        <h5 class="section-title">Membership Details</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="info-row">
                                                    <div class="info-label">Plan:</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($user['plan_name'] ?? 'No active plan'); ?></div>
                                                </div>
                                                <div class="info-row">
                                                    <div class="info-label">Price:</div>
                                                    <div class="info-value"><?php echo isset($user['price']) ? '$' . number_format($user['price'], 2) : 'N/A'; ?></div>
                                                </div>
                                                <div class="info-row">
                                                    <div class="info-label">Duration:</div>
                                                    <div class="info-value"><?php echo isset($user['duration_months']) ? $user['duration_months'] . ' months' : 'N/A'; ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-row">
                                                    <div class="info-label">Join Date:</div>
                                                    <div class="info-value"><?php echo isset($user['join_date']) ? date('F d, Y', strtotime($user['join_date'])) : 'N/A'; ?></div>
                                                </div>
                                                <div class="info-row">
                                                    <div class="info-label">Expiry Date:</div>
                                                    <div class="info-value">
                                                        <?php 
                                                        if (isset($user['expiry_date']) && $user['expiry_date']) {
                                                            $expiry = new DateTime($user['expiry_date']);
                                                            $now = new DateTime();
                                                            $diff = $now->diff($expiry)->days;
                                                            $expiry_class = ($expiry < $now) ? 'danger' : ($diff <= 7 ? 'warning' : 'success');
                                                            echo '<span class="badge badge-'.$expiry_class.'">'.date('F d, Y', strtotime($user['expiry_date'])).'</span>';
                                                            if ($expiry >= $now) echo '<br><small>'.$diff.' days remaining</small>';
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if (!empty($user['fitness_goals'])): ?>
                                        <div class="info-row">
                                            <div class="info-label">Fitness Goals:</div>
                                            <div class="info-value"><?php echo nl2br(htmlspecialchars($user['fitness_goals'])); ?></div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($user['medical_conditions'])): ?>
                                        <div class="info-row">
                                            <div class="info-label">Medical Conditions:</div>
                                            <div class="info-value"><?php echo nl2br(htmlspecialchars($user['medical_conditions'])); ?></div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($user['height']) || !empty($user['weight'])): ?>
                                        <h5 class="section-title mt-4">Health Metrics</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="info-row">
                                                    <div class="info-label">Height:</div>
                                                    <div class="info-value"><?php echo !empty($user['height']) ? $user['height'] . ' cm' : 'N/A'; ?></div>
                                                </div>
                                                <div class="info-row">
                                                    <div class="info-label">Weight:</div>
                                                    <div class="info-value"><?php echo !empty($user['weight']) ? $user['weight'] . ' kg' : 'N/A'; ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-row">
                                                    <div class="info-label">BMI:</div>
                                                    <div class="info-value"><?php echo !empty($user['bmi']) ? number_format($user['bmi'], 1) : 'N/A'; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Payments Tab -->
                                    <div class="tab-pane fade" id="payments" role="tabpanel">
                                        <h5 class="section-title">Recent Payments</h5>
                                        <?php if (empty($recent_payments)): ?>
                                            <p class="text-muted text-center py-4">No payment records found.</p>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        32<th>Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Receipt</th> </thead>
                                                    <tbody>
                                                        <?php foreach ($recent_payments as $payment): ?>
                                                            <tr>
                                                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>\\
                                                                <td>$<?php echo number_format($payment['amount'], 2); ?>\\
                                                                <td><?php echo ucfirst($payment['payment_method']); ?>\\
                                                                <td><span class="badge badge-<?php echo $payment['status'] == 'completed' ? 'success' : 'warning'; ?>"><?php echo ucfirst($payment['status']); ?></span>\\
                                                                <td><?php echo htmlspecialchars($payment['receipt_number'] ?? 'N/A'); ?>\\
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Attendance Tab -->
                                    <div class="tab-pane fade" id="attendance" role="tabpanel">
                                        <h5 class="section-title">Recent Attendance</h5>
                                        <?php if (empty($recent_attendance)): ?>
                                            <p class="text-muted text-center py-4">No attendance records found.</p>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        32<th>Date</th><th>Check In</th><th>Check Out</th><th>Type</th> </thead>
                                                    <tbody>
                                                        <?php foreach ($recent_attendance as $att): ?>
                                                            <tr>
                                                                <td><?php echo date('M d, Y', strtotime($att['date'])); ?>\\
                                                                <td><?php echo date('H:i', strtotime($att['check_in'])); ?>\\
                                                                <td><?php echo $att['check_out'] ? date('H:i', strtotime($att['check_out'])) : '-'; ?>\\
                                                                <td><span class="badge badge-info"><?php echo ucfirst($att['type']); ?></span>\\
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Classes Tab -->
                                    <div class="tab-pane fade" id="classes" role="tabpanel">
                                        <h5 class="section-title">Class Bookings</h5>
                                        <?php if (empty($recent_bookings)): ?>
                                            <p class="text-muted text-center py-4">No class bookings found or schedule table missing.</p>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        32<th>Class</th><th>Day</th><th>Time</th><th>Booking Date</th><th>Status</th> </thead>
                                                    <tbody>
                                                        <?php foreach ($recent_bookings as $booking): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($booking['class_name']); ?>\\
                                                                <td><?php echo $booking['day_of_week']; ?>\\
                                                                <td><?php echo date('H:i', strtotime($booking['start_time'])) . ' - ' . date('H:i', strtotime($booking['end_time'])); ?>\\
                                                                <td><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?>\\
                                                                <td><span class="badge badge-<?php echo $booking['status'] == 'booked' ? 'warning' : ($booking['status'] == 'attended' ? 'success' : 'danger'); ?>"><?php echo ucfirst($booking['status']); ?></span>\\
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-sign-out-alt"></i> Confirm Logout</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to logout?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times"></i> Cancel</button>
                    <a href="../../logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
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
    </script>
</body>
</html>