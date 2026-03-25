<?php
// admin/payments/manage_payments.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Path to root includes folder
$root_includes = __DIR__ . '/../../includes/';

// Include required files
require_once $root_includes . 'config.php';
require_once $root_includes . 'session.php';
require_once $root_includes . 'functions.php';

// Check if user is admin
Session::requireAdmin();

// Initialize functions
$functions = new Functions();
$error = '';
$success = '';

// Check if payments table exists
$payments_table_exists = true;
try {
    $pdo->query("SELECT 1 FROM payments LIMIT 1");
} catch (Exception $e) {
    $payments_table_exists = false;
    $error = "The payments table doesn't exist. Please create the database table first.";
}

// Handle payment status update
if (isset($_POST['update_status']) && $payments_table_exists) {
    try {
        $payment_id = $_POST['payment_id'];
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE payments SET status = ? WHERE id = ?");
        $stmt->execute([$status, $payment_id]);
        
        Session::setFlash('success', 'Payment status updated successfully');
        header('Location: manage_payments.php');
        exit();
    } catch (Exception $e) {
        $error = "Error updating payment: " . $e->getMessage();
    }
}

// Handle payment deletion
if (isset($_GET['delete']) && $payments_table_exists) {
    try {
        $payment_id = (int)$_GET['delete'];
        
        $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        
        Session::setFlash('success', 'Payment deleted successfully');
        header('Location: manage_payments.php');
        exit();
    } catch (Exception $e) {
        $error = "Error deleting payment: " . $e->getMessage();
    }
}

// Handle refund
if (isset($_GET['refund']) && $payments_table_exists) {
    try {
        $payment_id = (int)$_GET['refund'];
        
        $stmt = $pdo->prepare("UPDATE payments SET status = 'refunded' WHERE id = ?");
        $stmt->execute([$payment_id]);
        
        Session::setFlash('success', 'Payment refunded successfully');
        header('Location: manage_payments.php');
        exit();
    } catch (Exception $e) {
        $error = "Error processing refund: " . $e->getMessage();
    }
}

// Get filter parameters
$member_filter = $_GET['member_id'] ?? '';
$method_filter = $_GET['method'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search_term = $_GET['search'] ?? '';

// Build query for payments
$sql = "SELECT p.*, 
               u.full_name as member_name, u.email as member_email, u.phone as member_phone,
               r.full_name as recorded_by_name
        FROM payments p
        JOIN users u ON p.member_id = u.id
        LEFT JOIN users r ON p.recorded_by = r.id
        WHERE 1=1";

$params = [];

if (!empty($member_filter)) {
    $sql .= " AND p.member_id = ?";
    $params[] = $member_filter;
}

if (!empty($method_filter)) {
    $sql .= " AND p.payment_method = ?";
    $params[] = $method_filter;
}

if (!empty($status_filter)) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $sql .= " AND DATE(p.payment_date) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $sql .= " AND DATE(p.payment_date) <= ?";
    $params[] = $date_to;
}

if (!empty($search_term)) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR p.transaction_id LIKE ? OR p.notes LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY p.payment_date DESC";

// Get all payments
$payments = [];
if ($payments_table_exists) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $payments = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = "Error loading payments: " . $e->getMessage();
    }
}

// Get all members for filter dropdown
$members = [];
try {
    $stmt = $pdo->query("SELECT id, full_name FROM users WHERE user_type = 'member' ORDER BY full_name");
    $members = $stmt->fetchAll();
} catch (Exception $e) {
    $members = [];
}

// Get statistics
$stats = [
    'total' => 0,
    'paid' => 0,
    'pending' => 0,
    'failed' => 0,
    'refunded' => 0,
    'total_amount' => 0,
    'today_amount' => 0,
    'month_amount' => 0
];

if ($payments_table_exists) {
    try {
        // Total payments count
        $stats['total'] = $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn();
        
        // Count by status
        $stats['paid'] = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'paid'")->fetchColumn();
        $stats['pending'] = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
        $stats['failed'] = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'failed'")->fetchColumn();
        $stats['refunded'] = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'refunded'")->fetchColumn();
        
        // Total amount
        $stats['total_amount'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid'")->fetchColumn();
        
        // Today's amount
        $stats['today_amount'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(payment_date) = CURDATE() AND status = 'paid'")->fetchColumn();
        
        // This month's amount
        $stats['month_amount'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE()) AND status = 'paid'")->fetchColumn();
        
    } catch (Exception $e) {
        // Ignore errors
    }
}

$user_name = Session::userName(); // for topbar
$page_title = 'Manage Payments - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 4, Font Awesome, DataTables, Chart.js (optional) -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.css">
    <style>
        /* Copy the exact dashboard styles from the reference */
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.stats-card small{opacity:.9}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:15px 20px;transition:.3s}.list-group-item:last-child{border-bottom:none}.list-group-item:hover{background:#f8f9fa;transform:translateX(5px)}.list-group-item i{color:#667eea;margin-right:10px}.chart-container{position:relative;height:300px;margin:20px 0}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}.stats-card h2{font-size:1.8rem}}
        /* Additional styles for filters and table */
        .avatar-circle{width:35px;height:35px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:bold;font-size:16px}
        .status-select{width:110px;font-size:0.8rem;padding:0.25rem 0.5rem;height:auto}
        .form-label{font-weight:600;color:#495057;margin-bottom:8px}
        .form-control{border-radius:8px;border:1px solid #e1e5eb;padding:10px 15px;height:auto}
        .form-control:focus{border-color:#667eea;box-shadow:0 0 0 0.2rem rgba(102,126,234,0.25)}
    </style>
</head>
<body>
    <!-- Loading Spinner (optional) -->
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
                <li><a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li>
                    <a href="#membersSubmenu" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-users"></i> Members <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="membersSubmenu">
                        <li><a href="../members/manage_members.php"><i class="fas fa-list"></i> All Members</a></li>
                        <li><a href="../members/add_member.php"><i class="fas fa-user-plus"></i> Add Member</a></li>
                        <li><a href="../membership/membership_plans.php"><i class="fas fa-tag"></i> Membership Plans</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#trainersSubmenu" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-user-tie"></i> Trainers <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="trainersSubmenu">
                        <li><a href="../manage_trainers.php"><i class="fas fa-list"></i> All Trainers</a></li>
                        <li><a href="../trainers/add_trainer.php"><i class="fas fa-user-plus"></i> Add Trainer</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#classesSubmenu" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-calendar-alt"></i> Classes <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="classesSubmenu">
                        <li><a href="../classes/manage_classes.php"><i class="fas fa-list"></i> All Classes</a></li>
                        <li><a href="../classes/add_class.php"><i class="fas fa-plus-circle"></i> Add Class</a></li>
                        <li><a href="../classes/class_schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
                    </ul>
                </li>
                <li class="active">
                    <a href="manage_payments.php"><i class="fas fa-credit-card"></i> Payments</a>
                </li>
                <li><a href="../attendance.php"><i class="fas fa-clock"></i> Attendance</a></li>
                <li><a href="../equipment.php"><i class="fas fa-dumbbell"></i> Equipment</a></li>
                <li><a href="../reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
            <div class="sidebar-footer">
                <a href="#" onclick="confirmLogout(event)" class="btn btn-danger btn-block"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg navbar-custom">
                <button type="button" id="sidebarCollapse" class="btn btn-outline-primary">
                    <i class="fas fa-bars"></i> Menu
                </button>
                <div class="ml-auto">
                    <!-- Notifications dropdown (optional) -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown">
                            <i class="fas fa-bell"></i><span class="badge badge-danger badge-pill">3</span>
                        </button>
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
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown">
                            <i class="fas fa-user-circle fa-lg"></i>
                            <span class="ml-2"><?php echo htmlspecialchars($user_name); ?></span>
                        </button>
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
                <!-- Flash messages from session -->
                <?php Session::displayFlash(); ?>

                <!-- Error alert from form processing -->
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                        <?php if (strpos($error, 'doesn\'t exist') !== false): ?>
                            <hr>
                            <button class="btn btn-primary btn-sm" onclick="createTables()">
                                <i class="fas fa-database mr-2"></i>Create Payments Table
                            </button>
                        <?php endif; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="page-header d-flex justify-content-between align-items-center">
                    <h1><i class="fas fa-credit-card"></i> Manage Payments <small>View and manage all transactions</small></h1>
                    <div>
                        <a href="record_payment.php" class="btn btn-primary mr-2">
                            <i class="fas fa-plus-circle mr-2"></i>Record Payment
                        </a>
                        <a href="payment_reports.php" class="btn btn-outline-info">
                            <i class="fas fa-chart-bar mr-2"></i>Reports
                        </a>
                    </div>
                </div>

                <?php if (!$payments_table_exists): ?>
                    <!-- Table Creation Card -->
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-database fa-4x text-muted mb-3"></i>
                            <h3>Payments Table Not Found</h3>
                            <p class="text-muted">The payments table needs to be created before you can manage payments.</p>
                            <button class="btn btn-primary btn-lg" onclick="createTables()">
                                <i class="fas fa-database mr-2"></i>Create Payments Table
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stats-card bg-primary text-white">
                                <div class="card-body">
                                    <div class="card-title">Total Payments</div>
                                    <h3 class="mb-0"><?php echo $stats['total']; ?></h3>
                                    <i class="fas fa-credit-card"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card bg-success text-white">
                                <div class="card-body">
                                    <div class="card-title">Total Revenue</div>
                                    <h3 class="mb-0">₹<?php echo number_format($stats['total_amount'], 2); ?></h3>
                                    <i class="fas fa-rupee-sign"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card bg-info text-white">
                                <div class="card-body">
                                    <div class="card-title">Today's Revenue</div>
                                    <h3 class="mb-0">₹<?php echo number_format($stats['today_amount'], 2); ?></h3>
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card bg-warning text-white">
                                <div class="card-body">
                                    <div class="card-title">This Month</div>
                                    <h3 class="mb-0">₹<?php echo number_format($stats['month_amount'], 2); ?></h3>
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Summary -->
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <div class="card border-success">
                                <div class="card-body text-center">
                                    <h5 class="text-success"><?php echo $stats['paid']; ?></h5>
                                    <small>Paid</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card border-warning">
                                <div class="card-body text-center">
                                    <h5 class="text-warning"><?php echo $stats['pending']; ?></h5>
                                    <small>Pending</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card border-danger">
                                <div class="card-body text-center">
                                    <h5 class="text-danger"><?php echo $stats['failed']; ?></h5>
                                    <small>Failed</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card border-secondary">
                                <div class="card-body text-center">
                                    <h5 class="text-secondary"><?php echo $stats['refunded']; ?></h5>
                                    <small>Refunded</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6 text-center">
                                            <small>Cash</small>
                                            <br>
                                            <strong>
                                                <?php
                                                try {
                                                    echo $pdo->query("SELECT COUNT(*) FROM payments WHERE payment_method = 'cash'")->fetchColumn();
                                                } catch (Exception $e) {
                                                    echo '0';
                                                }
                                                ?>
                                            </strong>
                                        </div>
                                        <div class="col-6 text-center">
                                            <small>Card</small>
                                            <br>
                                            <strong>
                                                <?php
                                                try {
                                                    echo $pdo->query("SELECT COUNT(*) FROM payments WHERE payment_method = 'card'")->fetchColumn();
                                                } catch (Exception $e) {
                                                    echo '0';
                                                }
                                                ?>
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-filter mr-2"></i>Filter Payments</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="" class="form-row">
                                <div class="form-group col-md-2">
                                    <label class="form-label">Member</label>
                                    <select name="member_id" class="form-control">
                                        <option value="">All Members</option>
                                        <?php foreach ($members as $member): ?>
                                            <option value="<?php echo $member['id']; ?>" <?php echo $member_filter == $member['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($member['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label class="form-label">Method</label>
                                    <select name="method" class="form-control">
                                        <option value="">All Methods</option>
                                        <option value="cash" <?php echo $method_filter == 'cash' ? 'selected' : ''; ?>>Cash</option>
                                        <option value="card" <?php echo $method_filter == 'card' ? 'selected' : ''; ?>>Card</option>
                                        <option value="online" <?php echo $method_filter == 'online' ? 'selected' : ''; ?>>Online</option>
                                        <option value="bank_transfer" <?php echo $method_filter == 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                        <option value="razorpay">Razorpay</option>
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-control">
                                        <option value="">All Status</option>
                                        <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                                        <option value="refunded" <?php echo $status_filter == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label class="form-label">From Date</label>
                                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                                </div>

                                <div class="form-group col-md-2">
                                    <label class="form-label">To Date</label>
                                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                                </div>

                                <div class="form-group col-md-2">
                                    <label class="form-label">Search</label>
                                    <input type="text" name="search" class="form-control"
                                           placeholder="Member, transaction..."
                                           value="<?php echo htmlspecialchars($search_term); ?>">
                                </div>

                                <div class="col-12 mt-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search mr-2"></i>Apply Filters
                                    </button>
                                    <a href="manage_payments.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times mr-2"></i>Clear Filters
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Payments Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-list mr-2"></i>Payment History</h5>
                            <span class="badge badge-primary"><?php echo count($payments); ?> payments</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="paymentsTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Date</th>
                                            <th>Member</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>For</th>
                                            <th>Transaction ID</th>
                                            <th>Status</th>
                                            <th>Recorded By</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td><?php echo $payment['id']; ?></td>
                                            <td>
                                                <strong><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></strong>
                                                <br><small class="text-muted"><?php echo date('h:i A', strtotime($payment['payment_date'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="avatar-circle bg-info text-white mr-2">
                                                        <?php echo strtoupper(substr($payment['member_name'], 0, 1)); ?>
                                                    </span>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($payment['member_name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($payment['member_email']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong class="text-success">₹<?php echo number_format($payment['amount'], 2); ?></strong>
                                            </td>
                                            <td>
                                                <?php
                                                // $method_icons = [
                                                //     'cash' => 'fa-money-bill-wave',
                                                //     'card' => 'fa-credit-card',
                                                //     'online' => 'fa-globe',
                                                //     'bank_transfer' => 'fa-university'
                                                // ];
                                                $method_icons = [
    'cash' => 'fa-money-bill-wave',
    'card' => 'fa-credit-card',
    'online' => 'fa-globe',
    'bank_transfer' => 'fa-university',
    'razorpay' => 'fa-bolt'
];
                                                
                                                $method = $payment['payment_method'] ?? 'cash';
$icon = $method_icons[$method] ?? 'fa-money-bill-wave';
                                                ?>
                                                <span class="badge badge-secondary">
                                                    <i class="fas <?php echo $icon; ?> mr-1"></i>
                                                  <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'] ?? 'unknown')); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-info">
                                                 <?php echo ucfirst($payment['payment_for'] ?? 'membership'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($payment['transaction_id'])): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($payment['transaction_id']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                    <select name="status" class="form-control form-control-sm status-select"
                                                            onchange="this.form.submit()">
                                                        <option value="paid" <?php echo $payment['status'] == 'paid' ? 'selected' : ''; ?>>✅ Paid</option>
                                                        <option value="pending" <?php echo $payment['status'] == 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                                                        <option value="failed" <?php echo $payment['status'] == 'failed' ? 'selected' : ''; ?>>❌ Failed</option>
                                                        <option value="refunded" <?php echo $payment['status'] == 'refunded' ? 'selected' : ''; ?>>↩️ Refunded</option>
                                                    </select>
                                                    <input type="hidden" name="update_status" value="1">
                                                </form>
                                            </td>
                                            <td>
                                                <?php if ($payment['recorded_by_name']): ?>
                                                    <small><?php echo htmlspecialchars($payment['recorded_by_name']); ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted">System</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">

<?php if($payment['status'] == 'pending'): ?>

<button 
class="btn btn-sm btn-success pay-btn"
data-id="<?php echo $payment['id']; ?>"
data-amount="<?php echo $payment['amount']; ?>">
<i class="fas fa-credit-card"></i>
</button>

<?php endif; ?>

<a href="view_payment.php?id=<?php echo $payment['id']; ?>" 
class="btn btn-sm btn-info">
<i class="fas fa-eye"></i>
</a>
                                              
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>

                                        <?php if (empty($payments)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-5">
                                                <i class="fas fa-credit-card fa-4x text-muted mb-3"></i>
                                                <h5 class="text-muted">No payments found</h5>
                                                <?php if (!empty($member_filter) || !empty($method_filter) || !empty($status_filter) || !empty($search_term)): ?>
                                                    <p class="text-muted">Try adjusting your filters</p>
                                                <?php else: ?>
                                                    <a href="record_payment.php" class="btn btn-primary mt-3">
                                                        <i class="fas fa-plus-circle mr-2"></i>Record Your First Payment
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats by Method -->
                    <?php if (!empty($payments)): ?>
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-chart-pie mr-2"></i>Payment Summary</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php
                                      $methods = ['cash', 'card', 'online', 'bank_transfer', 'razorpay'];
                                       $method_icons = [
    'cash' => 'fa-money-bill-wave',
    'card' => 'fa-credit-card',
    'online' => 'fa-globe',
    'bank_transfer' => 'fa-university',
    'razorpay' => 'fa-bolt'
];
                                        foreach ($methods as $method):
                                            $stmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(amount), 0) FROM payments WHERE payment_method = ? AND status = 'paid'");
                                            $stmt->execute([$method]);
                                            $row = $stmt->fetch(PDO::FETCH_NUM);
                                            $count = $row ? (int)$row[0] : 0;
                                            $total = $row ? (float)$row[1] : 0;
                                        ?>
                                        <div class="col-md-3">
                                            <div class="text-center p-3 border rounded">
                                                <i class="fas <?php echo $method_icons[$method]; ?> fa-2x text-primary mb-2"></i>
                                                <h5><?php echo ucfirst(str_replace('_', ' ', $method)); ?></h5>
                                                <p class="mb-0"><strong><?php echo $count; ?></strong> payments</p>
                                                <small class="text-success">₹<?php echo number_format($total, 2); ?></small>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
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

    <!-- Table Creation Script -->
    <script>
    function createTables() {
        if (confirm('This will create the payments table in your database. Continue?')) {
            window.location.href = 'create_payments_table.php';
        }
    }
    </script>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function() {
            // Sidebar toggle
            $('#sidebarCollapse').on('click', function() {
                $('#sidebar').toggleClass('active');
            });

            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);

            // Initialize DataTable
            $('#paymentsTable').DataTable({
                pageLength: 25,
                order: [[1, 'desc']],
                language: {
                    search: "<i class='fas fa-search'></i>",
                    searchPlaceholder: "Search payments...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ payments",
                    infoEmpty: "Showing 0 to 0 of 0 payments",
                    infoFiltered: "(filtered from _MAX_ total payments)"
                },
                columnDefs: [
                    { orderable: false, targets: [9] } // Disable sorting on actions column
                ]
            });

            // Confirmation for status changes
            $(document).on('change', '.status-select', function() {
                var newStatus = $(this).find('option:selected').text();
                var amount = $(this).closest('tr').find('td:eq(3)').text().trim();
                var member = $(this).closest('tr').find('td:eq(2)').text().trim();

                return confirm('Change payment status for ' + member + ' (' + amount + ') to ' + newStatus + '?');
            });
        });

        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }

        // Export to CSV function (optional, can be added)
        function exportToCSV() {
            var csv = [];
            var rows = document.querySelectorAll('table tr');

            for (var i = 0; i < rows.length; i++) {
                var row = [], cols = rows[i].querySelectorAll('td, th');

                for (var j = 0; j < cols.length; j++) {
                    var text = cols[j].innerText.replace(/,/g, ';').replace(/\n/g, ' ');
                    row.push('"' + text + '"');
                }
                csv.push(row.join(','));
            }

            var csvContent = csv.join('\n');
            var blob = new Blob([csvContent], { type: 'text/csv' });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');

            a.href = url;
            a.download = 'payments_<?php echo date('Y-m-d'); ?>.csv';
            a.click();
        }
    </script>
    <script>

$(document).on("click",".pay-btn",function(){

console.log("Pay button clicked");

var payment_id = $(this).data("id");
var amount = $(this).data("amount") * 100;

var options = {
key: "rzp_test_SRQKNs34F8ti57",
amount: amount,
currency: "INR",
name: "FitTrack Gym",
description: "Membership Payment",

handler: function (response){

alert("Payment Successful");

window.location.href="verify_payment.php?payment_id="+payment_id+"&razorpay_id="+response.razorpay_payment_id;

}

};

var rzp = new Razorpay(options);
rzp.open();

});

</script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</body>
</html>