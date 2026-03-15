<?php
// member/dashboard.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

$root_path = dirname(__DIR__);
require_once $root_path . '/includes/config.php';
require_once $root_path . '/includes/session.php';
require_once $root_path . '/includes/functions.php';

// Check if user is member
if (!Session::isMember()) {
    Session::setFlash('danger', 'Access denied. Member login required.');
    header('Location: ' . $root_path . '/login.php');
    exit();
}

$member_id = Session::userId();
$functions = new Functions();
$error = '';

// Get member details
$member = null;
try {
    $stmt = $pdo->prepare("
        SELECT u.*, m.membership_type, m.membership_start, m.membership_end,
               m.height, m.weight, m.fitness_goals, m.emergency_contact, m.emergency_phone,
               t.full_name as trainer_name, t.id as trainer_id,
               DATEDIFF(m.membership_end, CURDATE()) as days_left
        FROM users u
        JOIN members m ON u.id = m.user_id
        LEFT JOIN users t ON m.assigned_trainer_id = t.id
        WHERE u.id = ?
    ");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();
} catch (Exception $e) {
    $error = "Error loading profile: " . $e->getMessage();
}

// Get attendance stats
$attendance_stats = ['today' => 0, 'total' => 0, 'last_month' => 0];
try {
    // Today's attendance
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND date = CURDATE() AND status = 'present'");
    $stmt->execute([$member_id]);
    $attendance_stats['today'] = $stmt->fetchColumn();

    // Total attendance (present)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND status = 'present'");
    $stmt->execute([$member_id]);
    $attendance_stats['total'] = $stmt->fetchColumn();

    // Last 30 days attendance
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND status = 'present'");
    $stmt->execute([$member_id]);
    $attendance_stats['last_month'] = $stmt->fetchColumn();
} catch (Exception $e) {
    // ignore
}

// Get workout plans count
$workout_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM workout_plans WHERE member_id = ? AND status = 'active'");
    $stmt->execute([$member_id]);
    $workout_count = $stmt->fetchColumn();
} catch (Exception $e) {}

// Get upcoming booked classes
$upcoming_classes = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.class_name, cs.date, cs.start_time, cs.end_time, t.full_name as trainer_name
        FROM class_bookings cb
        JOIN class_schedules cs ON cb.schedule_id = cs.id
        JOIN classes c ON cs.class_id = c.id
        LEFT JOIN users t ON cs.trainer_id = t.id
        WHERE cb.member_id = ? AND cs.date >= CURDATE()
        ORDER BY cs.date, cs.start_time
        LIMIT 5
    ");
    $stmt->execute([$member_id]);
    $upcoming_classes = $stmt->fetchAll();
} catch (Exception $e) {}

// Get recent attendance (for table)
$recent_attendance = [];
try {
    $stmt = $pdo->prepare("
        SELECT date, check_in, check_out, status
        FROM attendance
        WHERE user_id = ?
        ORDER BY date DESC
        LIMIT 5
    ");
    $stmt->execute([$member_id]);
    $recent_attendance = $stmt->fetchAll();
} catch (Exception $e) {}

// Get active workout plans (for display)
$workout_plans = [];
try {
    $stmt = $pdo->prepare("
        SELECT wp.*, u.full_name as trainer_name
        FROM workout_plans wp
        LEFT JOIN users u ON wp.trainer_id = u.id
        WHERE wp.member_id = ? AND wp.status = 'active'
        ORDER BY wp.created_at DESC
    ");
    $stmt->execute([$member_id]);
    $workout_plans = $stmt->fetchAll();
} catch (Exception $e) {}

// Get recent payments
$recent_payments = [];
try {
    $stmt = $pdo->prepare("
        SELECT amount, payment_date, payment_method, status
        FROM payments
        WHERE member_id = ?
        ORDER BY payment_date DESC
        LIMIT 5
    ");
    $stmt->execute([$member_id]);
    $recent_payments = $stmt->fetchAll();
} catch (Exception $e) {}

// Get payment summary
$payment_summary = ['total_paid' => 0, 'pending' => 0];
try {
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE member_id = ? AND status = 'paid'");
    $stmt->execute([$member_id]);
    $payment_summary['total_paid'] = $stmt->fetchColumn() ?: 0;

    $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE member_id = ? AND status = 'pending'");
    $stmt->execute([$member_id]);
    $payment_summary['pending'] = $stmt->fetchColumn() ?: 0;
} catch (Exception $e) {}

$page_title = 'Member Dashboard - ' . APP_NAME;
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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; overflow-x: hidden; }
        .wrapper { display: flex; width: 100%; align-items: stretch; min-height: 100vh; }
        #sidebar {
            min-width: 280px; max-width: 280px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff; transition: .3s; box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: relative; z-index: 1000;
        }
        #sidebar.active { margin-left: -280px; }
        #sidebar .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        #sidebar .sidebar-header h3 { font-size: 1.8rem; font-weight: 600; }
        #sidebar ul.components { padding: 20px 0; }
        #sidebar ul li a {
            padding: 15px 25px; font-size: 1rem; display: block; color: #fff;
            text-decoration: none; transition: .3s; border-left: 3px solid transparent;
        }
        #sidebar ul li a:hover { background: rgba(255,255,255,0.1); border-left-color: #fff; }
        #sidebar ul li.active > a { background: rgba(255,255,255,0.15); border-left-color: #fff; font-weight: 600; }
        #sidebar ul li a i { margin-right: 10px; width: 25px; text-align: center; }
        #sidebar ul ul a { padding-left: 50px !important; font-size: .9rem !important; }
        #sidebar .sidebar-footer {
            padding: 20px; position: absolute; bottom: 0; width: 100%;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        #content { width: 100%; padding: 30px; min-height: 100vh; transition: .3s; background: #f8f9fa; }
        .navbar-custom {
            background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-radius: 10px; margin-bottom: 30px; padding: 15px 25px;
        }
        .page-header { padding-bottom: 15px; margin: 0 0 30px; border-bottom: 3px solid #667eea; }
        .page-header h1 { font-size: 2rem; font-weight: 600; color: #333; margin: 0; }
        .page-header h1 i { color: #667eea; margin-right: 10px; }
        .page-header small { font-size: 1rem; color: #6c757d; margin-left: 10px; }
        .stats-card {
            border: none; border-radius: 15px; margin-bottom: 25px; transition: .3s;
            overflow: hidden; position: relative;
        }
        .stats-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        .stats-card .card-body { padding: 25px; }
        .stats-card .card-title { font-size: .9rem; text-transform: uppercase; letter-spacing: 1px; opacity: .9; margin-bottom: 10px; }
        .stats-card h2 { font-size: 2.2rem; font-weight: 700; margin: 0 0 5px; }
        .stats-card i { font-size: 3rem; opacity: .3; position: absolute; bottom: 15px; right: 15px; }
        .stats-card small { opacity: .9; }
        .card {
            border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .card-header {
            background: #fff; border-bottom: 2px solid #f0f0f0; padding: 20px 25px;
            border-radius: 15px 15px 0 0 !important;
        }
        .card-header h5 { margin: 0; font-weight: 600; color: #333; }
        .card-header h5 i { color: #667eea; margin-right: 10px; }
        .card-body { padding: 25px; }
        .table { margin: 0; }
        .table thead th {
            border-top: none; border-bottom: 2px solid #667eea; color: #555;
            font-weight: 600; text-transform: uppercase; font-size: .8rem;
            letter-spacing: .5px; padding: 15px 10px;
        }
        .table tbody td { padding: 15px 10px; vertical-align: middle; border-bottom: 1px solid #f0f0f0; color: #666; }
        .table tbody tr:hover { background: #f8f9fa; }
        .badge { padding: 6px 10px; border-radius: 20px; font-weight: 500; font-size: .75rem; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .list-group-item {
            border: none; border-bottom: 1px solid #f0f0f0; padding: 15px 20px;
            transition: .3s;
        }
        .list-group-item:last-child { border-bottom: none; }
        .list-group-item:hover { background: #f8f9fa; transform: translateX(5px); }
        .list-group-item i { color: #667eea; margin-right: 10px; }
        .chart-container { position: relative; height: 300px; margin: 20px 0; }
        .loading-spinner {
            display: none; position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%); z-index: 9999;
        }
        .loading-spinner.active { display: block; }
        .spinner-border { width: 3rem; height: 3rem; color: #667eea; }
        .alert { border: none; border-radius: 10px; padding: 15px 20px; margin-bottom: 30px; }
        @media (max-width: 768px) {
            #sidebar { margin-left: -280px; }
            #sidebar.active { margin-left: 0; }
            #content { padding: 20px; }
            .stats-card h2 { font-size: 1.8rem; }
        }
    </style>
</head>
<body>
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div>
    </div>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Member Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="attendance.php"><i class="fas fa-clock"></i> My Attendance</a></li>
                <li><a href="workouts.php"><i class="fas fa-dumbbell"></i> Workout Plans</a></li>
                <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li><a href="classes.php"><i class="fas fa-calendar-alt"></i> Book Classes</a></li>
            </ul>
            <div class="sidebar-footer">
                <a href="#" onclick="confirmLogout(event)" class="btn btn-danger btn-block">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-custom">
                <button type="button" id="sidebarCollapse" class="btn btn-outline-primary">
                    <i class="fas fa-bars"></i> Menu
                </button>
                <div class="ml-auto">
                    <!-- Notifications dropdown (optional) -->
                    <div class="dropdown ml-3 d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown">
                            <i class="fas fa-user-circle fa-lg"></i>
                            <span class="ml-2"><?php echo htmlspecialchars($user_name); ?></span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid">
                <?php Session::displayFlash(); ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header">
                            <h1><i class="fas fa-tachometer-alt"></i> Dashboard <small>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</small></h1>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-primary text-white">
                            <div class="card-body">
                                <div class="card-title">Membership Status</div>
                                <h2><?php echo ucfirst($member['membership_type'] ?? 'N/A'); ?></h2>
                                <i class="fas fa-id-card"></i>
                                <?php if (isset($member['days_left'])): ?>
                                    <?php if ($member['days_left'] < 0): ?>
                                        <small class="text-white-50">Expired</small>
                                    <?php else: ?>
                                        <small class="text-white-50"><?php echo $member['days_left']; ?> days left</small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <div class="card-title">Today's Attendance</div>
                                <h2><?php echo $attendance_stats['today']; ?></h2>
                                <i class="fas fa-calendar-check"></i>
                                <small class="text-white-50">Checked in today</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <div class="card-title">Active Workouts</div>
                                <h2><?php echo $workout_count; ?></h2>
                                <i class="fas fa-dumbbell"></i>
                                <small class="text-white-50">Current plans</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-warning text-white">
                            <div class="card-body">
                                <div class="card-title">Total Paid</div>
                                <h2><?php echo '₹ ' . number_format($payment_summary['total_paid'], 2); ?></h2>
                                <i class="fas fa-rupee-sign"></i>
                                <small class="text-white-50">Pending: <?php echo '₹ ' . number_format($payment_summary['pending'], 2); ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Stats Row (optional) -->
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-secondary text-white">
                            <div class="card-body">
                                <div class="card-title">Total Attendance</div>
                                <h2><?php echo $attendance_stats['total']; ?></h2>
                                <i class="fas fa-clock"></i>
                                <small class="text-white-50">All time</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-dark text-white">
                            <div class="card-body">
                                <div class="card-title">Last 30 Days</div>
                                <h2><?php echo $attendance_stats['last_month']; ?></h2>
                                <i class="fas fa-calendar-alt"></i>
                                <small class="text-white-50">Visits</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card" style="background:#6f42c1; color:#fff;">
                            <div class="card-body">
                                <div class="card-title">Trainer</div>
                                <h6><?php echo htmlspecialchars($member['trainer_name'] ?? 'Not Assigned'); ?></h6>
                                <i class="fas fa-user-tie"></i>
                                <small class="text-white-50">Assigned</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card" style="background:#20c997; color:#fff;">
                            <div class="card-body">
                                <div class="card-title">Next Class</div>
                                <?php if (!empty($upcoming_classes)): ?>
                                    <h6><?php echo htmlspecialchars($upcoming_classes[0]['class_name']); ?></h6>
                                    <small><?php echo date('M d, H:i', strtotime($upcoming_classes[0]['date'] . ' ' . $upcoming_classes[0]['start_time'])); ?></small>
                                <?php else: ?>
                                    <h6>None</h6>
                                    <small>No bookings</small>
                                <?php endif; ?>
                                <i class="fas fa-calendar-day"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row (optional, can be removed if not needed) -->
                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-line"></i> Attendance Trend (Last 7 Days)</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="attendanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-pie"></i> Workout Types</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="workoutChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Attendance & Workout Plans -->
                <div class="row mt-4">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="fas fa-history"></i> Recent Attendance</h5>
                                <a href="attendance.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($recent_attendance)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No attendance records yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Check In</th>
                                                    <th>Check Out</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_attendance as $a): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($a['date'])); ?></td>
                                                    <td><?php echo $a['check_in'] ? date('h:i A', strtotime($a['check_in'])) : '-'; ?></td>
                                                    <td><?php echo $a['check_out'] ? date('h:i A', strtotime($a['check_out'])) : '-'; ?></td>
                                                    <td>
                                                        <?php
                                                        $badge = match($a['status']) {
                                                            'present' => 'success',
                                                            'late'    => 'warning',
                                                            'absent'  => 'danger',
                                                            default   => 'secondary'
                                                        };
                                                        ?>
                                                        <span class="badge bg-<?php echo $badge; ?>"><?php echo ucfirst($a['status']); ?></span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="fas fa-dumbbell"></i> Active Workout Plans</h5>
                                <a href="workouts.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($workout_plans)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-dumbbell fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No active workout plans.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($workout_plans as $plan): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($plan['plan_name']); ?></h6>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($plan['trainer_name'] ?: 'N/A'); ?>
                                                    </small>
                                                </div>
                                                <span class="badge badge-info">Active</span>
                                            </div>
                                            <p class="mb-0 mt-2 small"><?php echo nl2br(htmlspecialchars(substr($plan['description'], 0, 100))); ?><?php if (strlen($plan['description']) > 100) echo '...'; ?></p>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Payments & Upcoming Classes -->
                <div class="row mt-4">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="fas fa-credit-card"></i> Recent Payments</h5>
                                <a href="payments.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($recent_payments)): ?>
                                    <div class="text-center py-4">
                                        <p class="text-muted mb-0">No payment records yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Amount</th>
                                                    <th>Method</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_payments as $p): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($p['payment_date'])); ?></td>
                                                    <td><?php echo '₹ ' . number_format($p['amount'], 2); ?></td>
                                                    <td><?php echo ucfirst($p['payment_method']); ?></td>
                                                    <td>
                                                        <?php if ($p['status'] == 'paid'): ?>
                                                            <span class="badge badge-success">Paid</span>
                                                        <?php elseif ($p['status'] == 'pending'): ?>
                                                            <span class="badge badge-warning">Pending</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger"><?php echo ucfirst($p['status']); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="fas fa-calendar-alt"></i> Upcoming Classes</h5>
                                <a href="classes.php" class="btn btn-sm btn-primary">Book More</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($upcoming_classes)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No upcoming classes.</p>
                                        <a href="classes.php" class="btn btn-primary"><i class="fas fa-calendar-plus"></i> Book a Class</a>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($upcoming_classes as $class): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($class['class_name']); ?></h6>
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($class['date'])); ?>
                                                        <i class="fas fa-clock ml-2"></i> <?php echo substr($class['start_time'], 0, 5); ?> - <?php echo substr($class['end_time'], 0, 5); ?>
                                                        <br><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($class['trainer_name'] ?? 'Not Assigned'); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="attendance.php" class="btn btn-outline-primary btn-block py-3">
                                            <i class="fas fa-clock fa-2x mb-2"></i><br>Mark Attendance
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="workouts.php" class="btn btn-outline-success btn-block py-3">
                                            <i class="fas fa-dumbbell fa-2x mb-2"></i><br>View Workouts
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="payments.php" class="btn btn-outline-warning btn-block py-3">
                                            <i class="fas fa-credit-card fa-2x mb-2"></i><br>Make Payment
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="classes.php" class="btn btn-outline-info btn-block py-3">
                                            <i class="fas fa-calendar-alt fa-2x mb-2"></i><br>Book a Class
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
                    <a href="../logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
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

            // Initialize DataTables if any table has the class
            if ($('.dataTable').length) {
                $('.dataTable').DataTable({
                    pageLength: 5,
                    lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "All"]],
                    order: [[0, 'desc']]
                });
            }

            // Initialize charts (dummy data - replace with real data if available)
            initCharts();
        });

        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }

        function initCharts() {
            // Attendance trend for last 7 days (example)
            var ctx = document.getElementById('attendanceChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5', 'Day 6', 'Day 7'],
                        datasets: [{
                            label: 'Attendance',
                            data: [1, 0, 1, 1, 0, 1, 1], // Replace with actual data
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102,126,234,0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, max: 1, stepSize: 1 } }
                    }
                });
            }

            // Workout types pie chart (example)
            var ctx2 = document.getElementById('workoutChart');
            if (ctx2) {
                new Chart(ctx2, {
                    type: 'doughnut',
                    data: {
                        labels: ['Cardio', 'Strength', 'Yoga', 'Other'],
                        datasets: [{
                            data: [5, 8, 3, 2],
                            backgroundColor: ['#667eea', '#764ba2', '#28a745', '#ffc107'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom' } }
                    }
                });
            }
        }
    </script>
</body>
</html>