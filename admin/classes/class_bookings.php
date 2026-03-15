<?php
// admin/classes/class_bookings.php
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

// Handle booking status update
if (isset($_POST['update_status'])) {
    try {
        $booking_id = $_POST['booking_id'];
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE class_bookings SET status = ? WHERE id = ?");
        $stmt->execute([$status, $booking_id]);
        
        Session::setFlash('success', 'Booking status updated successfully');
        header('Location: class_bookings.php' . (isset($_GET['class_id']) ? '?class_id=' . $_GET['class_id'] : ''));
        exit();
    } catch (Exception $e) {
        $error = "Error updating booking: " . $e->getMessage();
    }
}

// Handle booking deletion
if (isset($_GET['delete'])) {
    try {
        $booking_id = (int)$_GET['delete'];
        
        $stmt = $pdo->prepare("DELETE FROM class_bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        
        Session::setFlash('success', 'Booking deleted successfully');
        header('Location: class_bookings.php' . (isset($_GET['class_id']) ? '?class_id=' . $_GET['class_id'] : ''));
        exit();
    } catch (Exception $e) {
        $error = "Error deleting booking: " . $e->getMessage();
    }
}

// Handle bulk attendance marking
if (isset($_POST['mark_attendance'])) {
    try {
        $booking_ids = $_POST['booking_ids'] ?? [];
        $attendance_status = $_POST['attendance_status'] ?? 'attended';
        
        if (empty($booking_ids)) {
            throw new Exception("No bookings selected");
        }
        
        $placeholders = implode(',', array_fill(0, count($booking_ids), '?'));
        $stmt = $pdo->prepare("UPDATE class_bookings SET status = ? WHERE id IN ($placeholders)");
        
        $params = array_merge([$attendance_status], $booking_ids);
        $stmt->execute($params);
        
        Session::setFlash('success', 'Attendance marked for ' . count($booking_ids) . ' bookings');
        header('Location: class_bookings.php' . (isset($_GET['class_id']) ? '?class_id=' . $_GET['class_id'] : ''));
        exit();
    } catch (Exception $e) {
        $error = "Error marking attendance: " . $e->getMessage();
    }
}

// Get filter parameters
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';
$member_search = $_GET['member_search'] ?? '';

// Build query for bookings
$sql = "SELECT cb.*, 
               c.class_name, c.day_of_week, c.start_time, c.end_time, c.max_capacity,
               u.full_name as member_name, u.email as member_email, u.phone as member_phone,
               t.full_name as trainer_name
        FROM class_bookings cb
        JOIN classes c ON cb.class_id = c.id
        JOIN users u ON cb.member_id = u.id
        LEFT JOIN users t ON c.trainer_id = t.id
        WHERE 1=1";

$params = [];

if ($class_id) {
    $sql .= " AND cb.class_id = ?";
    $params[] = $class_id;
}

if (!empty($date_from)) {
    $sql .= " AND cb.booking_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $sql .= " AND cb.booking_date <= ?";
    $params[] = $date_to;
}

if (!empty($status_filter)) {
    $sql .= " AND cb.status = ?";
    $params[] = $status_filter;
}

if (!empty($member_search)) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$member_search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY cb.booking_date DESC, c.start_time";

// Get all bookings
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading bookings: " . $e->getMessage();
    $bookings = [];
}

// Get class details if specific class is selected
$class_details = null;
if ($class_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name as trainer_name,
                   (SELECT COUNT(*) FROM class_bookings WHERE class_id = c.id) as total_bookings,
                   (SELECT COUNT(*) FROM class_bookings WHERE class_id = c.id AND status = 'attended') as attended_count,
                   (SELECT COUNT(*) FROM class_bookings WHERE class_id = c.id AND status = 'cancelled') as cancelled_count
            FROM classes c
            LEFT JOIN users u ON c.trainer_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$class_id]);
        $class_details = $stmt->fetch();
    } catch (Exception $e) {
        // Ignore error
    }
}

// Get all classes for filter dropdown
$all_classes = [];
try {
    $stmt = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name");
    $all_classes = $stmt->fetchAll();
} catch (Exception $e) {
    $all_classes = [];
}

// Get statistics
try {
    $total_bookings = $pdo->query("SELECT COUNT(*) FROM class_bookings")->fetchColumn();
    $today_bookings = $pdo->query("SELECT COUNT(*) FROM class_bookings WHERE booking_date = CURDATE()")->fetchColumn();
    $upcoming_bookings = $pdo->query("SELECT COUNT(*) FROM class_bookings WHERE booking_date > CURDATE() AND status = 'booked'")->fetchColumn();
    $attended_bookings = $pdo->query("SELECT COUNT(*) FROM class_bookings WHERE status = 'attended'")->fetchColumn();
    $cancelled_bookings = $pdo->query("SELECT COUNT(*) FROM class_bookings WHERE status = 'cancelled'")->fetchColumn();
} catch (Exception $e) {
    $total_bookings = $today_bookings = $upcoming_bookings = $attended_bookings = $cancelled_bookings = 0;
}

$user_name = Session::userName(); // for topbar
$page_title = ($class_details ? $class_details['class_name'] . ' - ' : '') . 'Class Bookings - ' . APP_NAME;
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
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.badge-secondary{background:#e9ecef;color:#6c757d}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:15px 20px;transition:.3s}.list-group-item:hover{background:#f8f9fa;transform:translateX(5px)}.list-group-item i{color:#667eea;margin-right:10px}.chart-container{position:relative;height:300px;margin:20px 0}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}.stats-card h2{font-size:1.8rem}}
        .avatar-circle{width:30px;height:30px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:bold;font-size:14px}
        .status-select{width:110px;font-size:0.8rem;padding:0.25rem 0.5rem;height:auto}
    </style>
</head>
<body>
    <!-- Loading Spinner -->
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
                <li class="active">
                    <a href="#classesSubmenu" data-toggle="collapse" aria-expanded="true">
                        <i class="fas fa-calendar-alt"></i> Classes <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse show list-unstyled" id="classesSubmenu">
                        <li><a href="manage_classes.php"><i class="fas fa-list"></i> All Classes</a></li>
                        <li><a href="add_class.php"><i class="fas fa-plus-circle"></i> Add Class</a></li>
                        <li><a href="class_schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
                        <li class="active"><a href="class_bookings.php"><i class="fas fa-calendar-check"></i> Bookings</a></li>
                    </ul>
                </li>
                <li><a href="../payments/manage_payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
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
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="page-header d-flex justify-content-between align-items-center">
                    <h1>
                        <i class="fas fa-calendar-check"></i> 
                        <?php if ($class_details): ?>
                            <?php echo htmlspecialchars($class_details['class_name']); ?> - Bookings
                        <?php else: ?>
                            Class Bookings
                        <?php endif; ?>
                        <small>Manage and track class attendance</small>
                    </h1>
                    <div>
                        <a href="manage_classes.php" class="btn btn-outline-secondary mr-2">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Classes
                        </a>
                        <?php if ($class_details): ?>
                            <a href="edit_class.php?id=<?php echo $class_id; ?>" class="btn btn-primary">
                                <i class="fas fa-edit mr-2"></i>Edit Class
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card stats-card bg-primary text-white">
                            <div class="card-body">
                                <div class="card-title">Total Bookings</div>
                                <h3 class="mb-0"><?php echo $total_bookings; ?></h3>
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <div class="card-title">Today</div>
                                <h3 class="mb-0"><?php echo $today_bookings; ?></h3>
                                <i class="fas fa-calendar-day"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <div class="card-title">Upcoming</div>
                                <h3 class="mb-0"><?php echo $upcoming_bookings; ?></h3>
                                <i class="fas fa-calendar-week"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card bg-warning text-white">
                            <div class="card-body">
                                <div class="card-title">Attended</div>
                                <h3 class="mb-0"><?php echo $attended_bookings; ?></h3>
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card bg-danger text-white">
                            <div class="card-body">
                                <div class="card-title">Cancelled</div>
                                <h3 class="mb-0"><?php echo $cancelled_bookings; ?></h3>
                                <i class="fas fa-times-circle"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card bg-secondary text-white">
                            <div class="card-body">
                                <div class="card-title">Attendance Rate</div>
                                <h3 class="mb-0">
                                    <?php 
                                    $attendance_rate = $total_bookings > 0 ? round(($attended_bookings / $total_bookings) * 100) : 0;
                                    echo $attendance_rate . '%';
                                    ?>
                                </h3>
                                <i class="fas fa-percent"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Class Details if selected -->
                <?php if ($class_details): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle mr-2"></i>Class Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Class Name:</strong><br>
                                <?php echo htmlspecialchars($class_details['class_name']); ?>
                            </div>
                            <div class="col-md-2">
                                <strong>Day:</strong><br>
                                <?php echo $class_details['day_of_week']; ?>
                            </div>
                            <div class="col-md-2">
                                <strong>Time:</strong><br>
                                <?php echo date('h:i A', strtotime($class_details['start_time'])); ?> - 
                                <?php echo date('h:i A', strtotime($class_details['end_time'])); ?>
                            </div>
                            <div class="col-md-2">
                                <strong>Trainer:</strong><br>
                                <?php echo $class_details['trainer_name'] ?? 'Not Assigned'; ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Capacity:</strong><br>
                                <?php echo $class_details['total_bookings']; ?>/<?php echo $class_details['max_capacity']; ?> total bookings
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-filter mr-2"></i>Filter Bookings</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="form-row">
                            <?php if (!$class_id): ?>
                            <div class="form-group col-md-2">
                                <label>Class</label>
                                <select name="class_id" class="form-control">
                                    <option value="">All Classes</option>
                                    <?php foreach ($all_classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['class_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="form-group col-md-2">
                                <label>From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>

                            <div class="form-group col-md-2">
                                <label>To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>

                            <div class="form-group col-md-2">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="booked" <?php echo $status_filter == 'booked' ? 'selected' : ''; ?>>Booked</option>
                                    <option value="attended" <?php echo $status_filter == 'attended' ? 'selected' : ''; ?>>Attended</option>
                                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="no_show" <?php echo $status_filter == 'no_show' ? 'selected' : ''; ?>>No Show</option>
                                </select>
                            </div>

                            <div class="form-group col-md-3">
                                <label>Search Member</label>
                                <input type="text" name="member_search" class="form-control"
                                       placeholder="Name, email, phone..."
                                       value="<?php echo htmlspecialchars($member_search); ?>">
                            </div>

                            <div class="form-group col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>

                            <div class="col-12 mt-2">
                                <a href="class_bookings.php<?php echo $class_id ? '?class_id=' . $class_id : ''; ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-times mr-2"></i>Clear Filters
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <?php if (!empty($bookings)): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="POST" id="bulkActionForm">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAll">
                                        <label class="form-check-label" for="selectAll">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <select name="attendance_status" class="form-control form-control-sm">
                                        <option value="attended">Mark as Attended</option>
                                        <option value="no_show">Mark as No Show</option>
                                        <option value="cancelled">Mark as Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <button type="submit" name="mark_attendance" class="btn btn-sm btn-primary"
                                            onclick="return confirm('Update selected bookings?')">
                                        <i class="fas fa-check-circle mr-2"></i>Apply to Selected
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Bookings Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list mr-2"></i>
                            <?php echo count($bookings); ?> Booking<?php echo count($bookings) != 1 ? 's' : ''; ?> Found
                        </h5>
                        <button class="btn btn-sm btn-success" onclick="exportToCSV()">
                            <i class="fas fa-file-excel mr-2"></i>Export CSV
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="bookingsTable">
                                <thead>
                                    <tr>
                                        <th width="30"></th>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Class</th>
                                        <th>Time</th>
                                        <th>Member</th>
                                        <th>Contact</th>
                                        <th>Trainer</th>
                                        <th>Status</th>
                                        <th>Booked On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="booking-checkbox" form="bulkActionForm"
                                                   name="booking_ids[]" value="<?php echo $booking['id']; ?>">
                                        </td>
                                        <td><?php echo $booking['id']; ?></td>
                                        <td>
                                            <strong><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></strong>
                                            <br><small class="text-muted"><?php echo $booking['day_of_week']; ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['class_name']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo date('h:i A', strtotime($booking['start_time'])); ?> -
                                            <?php echo date('h:i A', strtotime($booking['end_time'])); ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="avatar-circle bg-info text-white mr-2">
                                                    <?php echo strtoupper(substr($booking['member_name'], 0, 1)); ?>
                                                </span>
                                                <strong><?php echo htmlspecialchars($booking['member_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <i class="fas fa-envelope fa-fw text-muted"></i> <?php echo htmlspecialchars($booking['member_email']); ?><br>
                                            <i class="fas fa-phone fa-fw text-muted"></i> <?php echo htmlspecialchars($booking['member_phone'] ?? 'N/A'); ?>
                                        </td>
                                        <td>
                                            <?php if ($booking['trainer_name']): ?>
                                                <span class="badge badge-success">
                                                    <?php echo htmlspecialchars($booking['trainer_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">TBA</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <select name="status" class="form-control form-control-sm status-select"
                                                        onchange="this.form.submit()">
                                                    <option value="booked" <?php echo $booking['status'] == 'booked' ? 'selected' : ''; ?>>📅 Booked</option>
                                                    <option value="attended" <?php echo $booking['status'] == 'attended' ? 'selected' : ''; ?>>✅ Attended</option>
                                                    <option value="cancelled" <?php echo $booking['status'] == 'cancelled' ? 'selected' : ''; ?>>❌ Cancelled</option>
                                                    <option value="no_show" <?php echo $booking['status'] == 'no_show' ? 'selected' : ''; ?>>🚫 No Show</option>
                                                </select>
                                                <input type="hidden" name="update_status" value="1">
                                            </form>
                                        </td>
                                        <td>
                                            <small><?php echo date('M d, Y', strtotime($booking['created_at'])); ?></small>
                                            <br><small class="text-muted"><?php echo date('h:i A', strtotime($booking['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <a href="?delete=<?php echo $booking['id']; ?><?php echo $class_id ? '&class_id=' . $class_id : ''; ?>"
                                               class="btn btn-sm btn-danger"
                                               title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this booking?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php if (empty($bookings)): ?>
                                    <tr>
                                        <td colspan="11" class="text-center py-5">
                                            <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                                            <h5 class="text-muted">No bookings found</h5>
                                            <p class="text-muted">Try adjusting your filters</p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats by Status -->
                <?php if (!empty($bookings)): ?>
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-pie mr-2"></i>Booking Statistics</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $status_counts = [
                                    'booked' => 0,
                                    'attended' => 0,
                                    'cancelled' => 0,
                                    'no_show' => 0
                                ];

                                foreach ($bookings as $booking) {
                                    $status_counts[$booking['status']] = ($status_counts[$booking['status']] ?? 0) + 1;
                                }
                                ?>
                                <div class="row text-center">
                                    <div class="col-3">
                                        <div class="p-3 border rounded">
                                            <h5 class="text-primary">📅 <?php echo $status_counts['booked']; ?></h5>
                                            <small>Booked</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="p-3 border rounded">
                                            <h5 class="text-success">✅ <?php echo $status_counts['attended']; ?></h5>
                                            <small>Attended</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="p-3 border rounded">
                                            <h5 class="text-danger">❌ <?php echo $status_counts['cancelled']; ?></h5>
                                            <small>Cancelled</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="p-3 border rounded">
                                            <h5 class="text-warning">🚫 <?php echo $status_counts['no_show']; ?></h5>
                                            <small>No Show</small>
                                        </div>
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
            $('#bookingsTable').DataTable({
                pageLength: 25,
                order: [[2, 'desc']],
                language: {
                    search: "<i class='fas fa-search'></i>",
                    searchPlaceholder: "Search bookings...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ bookings",
                    infoEmpty: "Showing 0 to 0 of 0 bookings",
                    infoFiltered: "(filtered from _MAX_ total bookings)"
                },
                columnDefs: [
                    { orderable: false, targets: [0, 10] } // Disable sorting on checkbox and actions columns
                ]
            });

            // Select all checkbox functionality
            $('#selectAll').on('change', function() {
                $('.booking-checkbox').prop('checked', $(this).prop('checked'));
            });

            // Update select all when individual checkboxes change
            $(document).on('change', '.booking-checkbox', function() {
                if ($('.booking-checkbox:checked').length === $('.booking-checkbox').length) {
                    $('#selectAll').prop('checked', true);
                } else {
                    $('#selectAll').prop('checked', false);
                }
            });
        });

        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }

        // Export to CSV function
        function exportToCSV() {
            var csv = [];
            var rows = document.querySelectorAll('table tr');

            for (var i = 0; i < rows.length; i++) {
                var row = [], cols = rows[i].querySelectorAll('td, th');

                for (var j = 0; j < cols.length; j++) {
                    // Skip checkbox column
                    if (j === 0) continue;

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
            a.download = 'class_bookings_<?php echo date('Y-m-d'); ?>.csv';
            a.click();
        }

        // Confirmation for status changes
        $(document).on('change', '.status-select', function() {
            var newStatus = $(this).find('option:selected').text();
            var row = $(this).closest('tr');
            var memberName = row.find('td:eq(5)').text().trim();
            var className = row.find('td:eq(3)').text().trim();

            return confirm('Change status for ' + memberName + '\'s booking in ' + className + ' to ' + newStatus + '?');
        });
    </script>
</body>
</html>