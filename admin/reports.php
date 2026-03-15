<?php
// admin/reports.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Path to root includes
$root_path = dirname(__DIR__);
require_once $root_path . '/includes/config.php';
require_once $root_path . '/includes/session.php';
require_once $root_path . '/includes/functions.php';

// Use the Session class (ensure it has isAdmin method)
if (!Session::isAdmin()) {
    header('Location: ' . $root_path . '/login.php');
    exit();
}

$functions = new Functions(); // if needed

// Get report parameters
$report_type = $_GET['type'] ?? 'membership';
$date_from   = $_GET['date_from'] ?? date('Y-m-01');
$date_to     = $_GET['date_to'] ?? date('Y-m-d');

// Initialize arrays
$report_data   = [];
$chart_labels  = [];
$chart_data    = [];
$error         = '';
$report_title  = '';

try {
    switch ($report_type) {
        case 'membership':
            $report_title = "Membership Distribution";
            $stmt = $pdo->query("
                SELECT 
                    membership_type,
                    COUNT(*) as count,
                    COUNT(*) * 100.0 / (SELECT COUNT(*) FROM members) as percentage
                FROM members 
                GROUP BY membership_type
            ");
            $report_data = $stmt->fetchAll();
            foreach ($report_data as $row) {
                $chart_labels[] = ucfirst($row['membership_type']);
                $chart_data[]   = $row['count'];
            }
            break;

        case 'revenue':
            $report_title = "Revenue Report ({$date_from} to {$date_to})";
            $stmt = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(payment_date, '%Y-%m') as month,
                    COUNT(*) as transaction_count,
                    SUM(amount) as total_amount,
                    AVG(amount) as average_amount
                FROM payments 
                WHERE DATE(payment_date) BETWEEN ? AND ?
                GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
                ORDER BY month DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $report_data = $stmt->fetchAll();
            // Prepare chart data in chronological order
            foreach (array_reverse($report_data) as $row) {
                $chart_labels[] = date('M Y', strtotime($row['month'] . '-01'));
                $chart_data[]   = (float) $row['total_amount'];
            }
            break;

        case 'attendance':
            $report_title = "Attendance (Last 30 Days)";
            $stmt = $pdo->prepare("
                SELECT 
                    DATE(date) as attendance_date,
                    COUNT(*) as total_attendance,
                    COUNT(DISTINCT user_id) as unique_members,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count
                FROM attendance 
                WHERE date BETWEEN ? AND ?
                GROUP BY DATE(date)
                ORDER BY date DESC
                LIMIT 30
            ");
            $stmt->execute([$date_from, $date_to]);
            $report_data = $stmt->fetchAll();
            foreach (array_reverse($report_data) as $row) {
                $chart_labels[] = date('M d', strtotime($row['attendance_date']));
                $chart_data[]   = (int) $row['total_attendance'];
            }
            break;

        case 'trainer':
            $report_title = "Trainer Performance";
            $stmt = $pdo->query("
                SELECT 
                    u.id,
                    u.full_name as trainer_name,
                    COUNT(DISTINCT m.user_id) as total_members,
                    COUNT(DISTINCT wp.id) as total_plans,
                    COUNT(DISTINCT a.user_id) as active_members
                FROM users u
                LEFT JOIN members m ON u.id = m.assigned_trainer_id
                LEFT JOIN workout_plans wp ON u.id = wp.trainer_id AND wp.status = 'active'
                LEFT JOIN attendance a ON a.user_id = m.user_id AND a.date = CURDATE()
                WHERE u.user_type = 'trainer'
                GROUP BY u.id, u.full_name
            ");
            $report_data = $stmt->fetchAll();
            foreach ($report_data as $row) {
                $chart_labels[] = $row['trainer_name'];
                $chart_data[]   = (int) $row['total_members'];
            }
            break;

        case 'user_activity':
            $report_title = "User Activity";
            // Summary stats
            $stmt = $pdo->query("
                SELECT 
                    COUNT(DISTINCT user_id) as active_today,
                    COUNT(DISTINCT CASE WHEN date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN user_id END) as active_week,
                    COUNT(DISTINCT CASE WHEN date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN user_id END) as active_month
                FROM attendance
            ");
            $activity_summary = $stmt->fetch();

            // Activity by user type for chart
            $stmt = $pdo->prepare("
                SELECT 
                    u.user_type,
                    COUNT(DISTINCT u.id) as total_users,
                    COUNT(DISTINCT a.user_id) as active_today
                FROM users u
                LEFT JOIN attendance a ON u.id = a.user_id AND a.date = CURDATE()
                GROUP BY u.user_type
            ");
            $stmt->execute();
            $report_data = $stmt->fetchAll();
            foreach ($report_data as $row) {
                $chart_labels[] = ucfirst($row['user_type']);
                $chart_data[]   = (int) $row['active_today'];
            }
            break;

        default:
            $report_title = "Unknown Report";
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

$user_name = Session::userName();
$page_title = 'Reports - ' . APP_NAME;
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
                <li><a href="payments/manage_payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li><a href="attendance.php"><i class="fas fa-clock"></i> Attendance</a></li>
                <li class="active"><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
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
                            <a class="dropdown-item" href="#"><strong>New report generated</strong><br><small class="text-muted">2 minutes ago</small></a>
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
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php Session::displayFlash(); ?>

                <!-- Page Header -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header">
                            <h1><i class="fas fa-chart-bar"></i> Reports & Analytics <small>Generate and view reports</small></h1>
                        </div>
                    </div>
                </div>

                <!-- Filter Form Card -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-filter"></i> Report Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="form-row">
                            <div class="col-md-3 mb-3">
                                <label for="report_type">Report Type</label>
                                <select class="form-control" id="report_type" name="type">
                                    <option value="membership" <?php echo $report_type == 'membership' ? 'selected' : ''; ?>>Membership Distribution</option>
                                    <option value="revenue"     <?php echo $report_type == 'revenue' ? 'selected' : ''; ?>>Revenue Report</option>
                                    <option value="attendance"  <?php echo $report_type == 'attendance' ? 'selected' : ''; ?>>Attendance Report</option>
                                    <option value="trainer"     <?php echo $report_type == 'trainer' ? 'selected' : ''; ?>>Trainer Performance</option>
                                    <option value="user_activity" <?php echo $report_type == 'user_activity' ? 'selected' : ''; ?>>User Activity</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="date_from">Date From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="date_to">Date To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-filter"></i> Generate
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Title and Action Buttons -->
                <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                    <h4 class="mb-0"><?php echo $report_title; ?></h4>
                    <div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm ml-2" id="exportBtn">
                            <i class="fas fa-download"></i> Export CSV
                        </button>
                    </div>
                </div>

                <!-- Chart (if data exists) -->
                <?php if (!empty($chart_labels) && !empty($chart_data)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-<?php echo $report_type == 'membership' ? 'pie' : ($report_type == 'trainer' ? 'bar' : 'line'); ?>"></i> Visual Representation</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height:400px;">
                            <canvas id="reportChart"></canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Detailed Data Table Card -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-table"></i> Detailed Data</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <?php if ($report_type == 'membership'): ?>
                                <table class="table table-hover" id="reportTable">
                                    <thead><tr><th>Type</th><th>Count</th><th>%</th><th>Est. Revenue (₹)</th></tr></thead>
                                    <tbody>
                                    <?php 
                                    $total = array_sum(array_column($report_data, 'count'));
                                    foreach ($report_data as $row): 
                                        $revenue = $row['count'] * ($row['membership_type'] == 'premium' ? 100 : ($row['membership_type'] == 'vip' ? 200 : 50));
                                    ?>
                                        <tr>
                                            <td><span class="badge badge-<?php echo $row['membership_type'] == 'premium' ? 'success' : ($row['membership_type'] == 'vip' ? 'warning' : 'info'); ?>"><?php echo ucfirst($row['membership_type']); ?></span></td>
                                            <td><?php echo $row['count']; ?></td>
                                            <td><?php echo number_format($row['percentage'], 1); ?>%</td>
                                            <td>₹<?php echo number_format($revenue, 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-info"><tr><th>Total</th><th><?php echo $total; ?></th><th>100%</th><th>₹<?php echo number_format(array_sum(array_column($report_data, 'count')) * 50, 2); ?></th></tr></tfoot>
                                </table>

                            <?php elseif ($report_type == 'revenue'): ?>
                                <table class="table table-hover" id="reportTable">
                                    <thead><tr><th>Month</th><th>Transactions</th><th>Total (₹)</th><th>Avg (₹)</th></tr></thead>
                                    <tbody>
                                    <?php 
                                    $total_rev = 0; $total_tx = 0;
                                    foreach ($report_data as $row): 
                                        $total_rev += $row['total_amount'];
                                        $total_tx  += $row['transaction_count'];
                                    ?>
                                        <tr>
                                            <td><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></td>
                                            <td><?php echo $row['transaction_count']; ?></td>
                                            <td>₹<?php echo number_format($row['total_amount'], 2); ?></td>
                                            <td>₹<?php echo number_format($row['average_amount'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-info"><tr><th>Total/Avg</th><th><?php echo $total_tx; ?></th><th>₹<?php echo number_format($total_rev, 2); ?></th><th>₹<?php echo $total_tx ? number_format($total_rev/$total_tx, 2) : 0; ?></th></tr></tfoot>
                                </table>

                            <?php elseif ($report_type == 'attendance'): ?>
                                <table class="table table-hover" id="reportTable">
                                    <thead><tr><th>Date</th><th>Total</th><th>Unique</th><th>Present</th><th>Late</th><th>Rate</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($report_data as $row): 
                                        $rate = $row['unique_members'] ? ($row['total_attendance'] / $row['unique_members'] * 100) : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($row['attendance_date'])); ?></td>
                                            <td><?php echo $row['total_attendance']; ?></td>
                                            <td><?php echo $row['unique_members']; ?></td>
                                            <td><?php echo $row['present_count']; ?></td>
                                            <td><?php echo $row['late_count']; ?></td>
                                            <td><div class="progress"><div class="progress-bar bg-success" style="width: <?php echo $rate; ?>%"><?php echo number_format($rate,1); ?>%</div></div></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>

                            <?php elseif ($report_type == 'trainer'): ?>
                                <table class="table table-hover" id="reportTable">
                                    <thead><tr><th>Trainer</th><th>Members</th><th>Plans</th><th>Active Today</th><th>Utilization</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($report_data as $row): 
                                        $util = $row['total_members'] ? ($row['active_members'] / $row['total_members'] * 100) : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['trainer_name']); ?></td>
                                            <td><?php echo $row['total_members']; ?></td>
                                            <td><?php echo $row['total_plans']; ?></td>
                                            <td><?php echo $row['active_members']; ?></td>
                                            <td><div class="progress"><div class="progress-bar bg-info" style="width: <?php echo $util; ?>%"><?php echo number_format($util,1); ?>%</div></div></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>

                            <?php elseif ($report_type == 'user_activity'): ?>
                                <div class="row mb-4 p-3">
                                    <div class="col-md-4">
                                        <div class="card stats-card bg-primary text-white">
                                            <div class="card-body">
                                                <div class="card-title">Active Today</div>
                                                <h2><?php echo $activity_summary['active_today'] ?? 0; ?></h2>
                                                <i class="fas fa-calendar-check"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card stats-card bg-success text-white">
                                            <div class="card-body">
                                                <div class="card-title">Active This Week</div>
                                                <h2><?php echo $activity_summary['active_week'] ?? 0; ?></h2>
                                                <i class="fas fa-calendar-week"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card stats-card bg-info text-white">
                                            <div class="card-body">
                                                <div class="card-title">Active This Month</div>
                                                <h2><?php echo $activity_summary['active_month'] ?? 0; ?></h2>
                                                <i class="fas fa-calendar-alt"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <table class="table table-hover" id="reportTable">
                                    <thead><tr><th>User Type</th><th>Total Users</th><th>Active Today</th><th>Activity Rate</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($report_data as $row): 
                                        $rate = $row['total_users'] ? ($row['active_today'] / $row['total_users'] * 100) : 0;
                                    ?>
                                        <tr>
                                            <td><span class="badge badge-<?php echo $row['user_type']=='admin'?'danger':($row['user_type']=='trainer'?'success':'info'); ?>"><?php echo ucfirst($row['user_type']); ?></span></td>
                                            <td><?php echo $row['total_users']; ?></td>
                                            <td><?php echo $row['active_today']; ?></td>
                                            <td><div class="progress"><div class="progress-bar bg-warning" style="width: <?php echo $rate; ?>%"><?php echo number_format($rate,1); ?>%</div></div></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle mr-2"></i> Click "Export CSV" to download this table.
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

        // Chart initialization
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('reportChart')?.getContext('2d');
            if (ctx && <?php echo json_encode(!empty($chart_labels) && !empty($chart_data)); ?>) {
                const chartType = <?php echo json_encode($report_type == 'membership' ? 'pie' : ($report_type == 'trainer' ? 'bar' : 'line')); ?>;
                new Chart(ctx, {
                    type: chartType,
                    data: {
                        labels: <?php echo json_encode($chart_labels); ?>,
                        datasets: [{
                            label: <?php echo json_encode(
                                $report_type == 'revenue' ? 'Revenue (₹)' :
                                ($report_type == 'attendance' ? 'Attendance Count' :
                                ($report_type == 'trainer' ? 'Members Assigned' : 'Count'))
                            ); ?>,
                            data: <?php echo json_encode($chart_data); ?>,
                            backgroundColor: chartType === 'pie' ? [
                                'rgba(54, 162, 235, 0.5)',
                                'rgba(255, 206, 86, 0.5)',
                                'rgba(75, 192, 192, 0.5)',
                                'rgba(255, 99, 132, 0.5)'
                            ] : 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: { display: true, text: <?php echo json_encode($report_title); ?> }
                        }
                    }
                });
            }

            // Export CSV
            document.getElementById('exportBtn')?.addEventListener('click', function() {
                const table = document.getElementById('reportTable');
                if (!table) return;
                let csv = [];
                for (let row of table.querySelectorAll('tr')) {
                    let rowData = [];
                    for (let cell of row.querySelectorAll('td, th')) {
                        let text = cell.innerText.replace(/\s+/g, ' ').trim();
                        rowData.push('"' + text.replace(/"/g, '""') + '"');
                    }
                    csv.push(rowData.join(','));
                }
                const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = <?php echo json_encode($report_type . '_report_' . date('Y-m-d') . '.csv'); ?>;
                a.click();
                URL.revokeObjectURL(url);
            });
        });
    </script>
</body>
</html>