<?php
// admin/classes/manage_classes.php
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

// Handle class status update
if (isset($_POST['update_status'])) {
    try {
        $class_id = $_POST['class_id'];
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE classes SET status = ? WHERE id = ?");
        $stmt->execute([$status, $class_id]);
        
        Session::setFlash('success', 'Class status updated successfully');
        header('Location: manage_classes.php');
        exit();
    } catch (Exception $e) {
        $error = "Error updating class: " . $e->getMessage();
    }
}

// Handle class deletion
if (isset($_GET['delete'])) {
    try {
        $class_id = (int)$_GET['delete'];
        
        // Check if class has bookings
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_bookings WHERE class_id = ?");
        $stmt->execute([$class_id]);
        $booking_count = $stmt->fetchColumn();
        
        if ($booking_count > 0) {
            // Soft delete - just mark as inactive
            $stmt = $pdo->prepare("UPDATE classes SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$class_id]);
            Session::setFlash('warning', 'Class has existing bookings. Status set to inactive instead of deleted.');
        } else {
            // Hard delete - no bookings
            $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
            $stmt->execute([$class_id]);
            Session::setFlash('success', 'Class deleted successfully');
        }
        
        header('Location: manage_classes.php');
        exit();
    } catch (Exception $e) {
        $error = "Error deleting class: " . $e->getMessage();
    }
}

// Get filter parameters
$day_filter = $_GET['day'] ?? '';
$trainer_filter = $_GET['trainer'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search_term = $_GET['search'] ?? '';

// Build query for classes
$sql = "SELECT c.*, u.full_name as trainer_name,
        (SELECT COUNT(*) FROM class_bookings WHERE class_id = c.id) as total_bookings,
        (SELECT COUNT(*) FROM class_bookings WHERE class_id = c.id AND booking_date >= CURDATE()) as upcoming_bookings
        FROM classes c
        LEFT JOIN users u ON c.trainer_id = u.id
        WHERE 1=1";

$params = [];

if (!empty($day_filter)) {
    $sql .= " AND c.day_of_week = ?";
    $params[] = $day_filter;
}

if (!empty($trainer_filter)) {
    $sql .= " AND c.trainer_id = ?";
    $params[] = $trainer_filter;
}

if (!empty($status_filter)) {
    $sql .= " AND c.status = ?";
    $params[] = $status_filter;
}

if (!empty($search_term)) {
    $sql .= " AND (c.class_name LIKE ? OR c.description LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY FIELD(c.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), c.start_time";

// Get all classes
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $classes = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading classes: " . $e->getMessage();
    $classes = [];
}

// Get all active trainers for filter
$trainers = [];
try {
    $stmt = $pdo->query("SELECT u.id, u.full_name FROM users u JOIN trainers t ON u.id = t.user_id WHERE u.status = 'active' ORDER BY u.full_name");
    $trainers = $stmt->fetchAll();
} catch (Exception $e) {
    $trainers = [];
}

// Get statistics
try {
    $total_classes = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    $active_classes = $pdo->query("SELECT COUNT(*) FROM classes WHERE status = 'active'")->fetchColumn();
    $inactive_classes = $pdo->query("SELECT COUNT(*) FROM classes WHERE status = 'inactive'")->fetchColumn();
    
    // Count by day
    $monday = $pdo->query("SELECT COUNT(*) FROM classes WHERE day_of_week = 'Monday'")->fetchColumn();
    $tuesday = $pdo->query("SELECT COUNT(*) FROM classes WHERE day_of_week = 'Tuesday'")->fetchColumn();
    $wednesday = $pdo->query("SELECT COUNT(*) FROM classes WHERE day_of_week = 'Wednesday'")->fetchColumn();
    $thursday = $pdo->query("SELECT COUNT(*) FROM classes WHERE day_of_week = 'Thursday'")->fetchColumn();
    $friday = $pdo->query("SELECT COUNT(*) FROM classes WHERE day_of_week = 'Friday'")->fetchColumn();
    $saturday = $pdo->query("SELECT COUNT(*) FROM classes WHERE day_of_week = 'Saturday'")->fetchColumn();
    $sunday = $pdo->query("SELECT COUNT(*) FROM classes WHERE day_of_week = 'Sunday'")->fetchColumn();
    
    // Total bookings
    $total_bookings = $pdo->query("SELECT COUNT(*) FROM class_bookings")->fetchColumn();
    
} catch (Exception $e) {
    $total_classes = $active_classes = $inactive_classes = 0;
    $monday = $tuesday = $wednesday = $thursday = $friday = $saturday = $sunday = 0;
    $total_bookings = 0;
}

$user_name = Session::userName();
$page_title = 'Manage Classes - ' . APP_NAME;
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
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.stats-card small{opacity:.9}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:15px 20px;transition:.3s}.list-group-item:last-child{border-bottom:none}.list-group-item:hover{background:#f8f9fa;transform:translateX(5px)}.list-group-item i{color:#667eea;margin-right:10px}.chart-container{position:relative;height:300px;margin:20px 0}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}.status-select{width:90px;font-size:0.8rem;padding:0.25rem 0.5rem}.btn-group .btn{padding:0.25rem 0.5rem}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}.stats-card h2{font-size:1.8rem}.status-select{width:100%}}
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
                    <a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                </li>
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
                    <a href="#trainersSubmenu" data-toggle="collapse">
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
                        <li class="active"><a href="manage_classes.php"><i class="fas fa-list"></i> All Classes</a></li>
                        <li><a href="add_class.php"><i class="fas fa-plus-circle"></i> Add Class</a></li>
                        <li><a href="class_schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
                    </ul>
                </li>
                <li><a href="../payments/manage_payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li><a href="../attendance.php"><i class="fas fa-clock"></i> Attendance</a></li>
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
                    <!-- Notifications dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown"><i class="fas fa-bell"></i><span class="badge badge-danger badge-pill">3</span></button>
                        <div class="dropdown-menu dropdown-menu-right" style="width:300px">
                            <div class="dropdown-header bg-light">Notifications</div>
                            <a class="dropdown-item" href="#"><strong>New class added</strong><br><small class="text-muted">2 minutes ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Booking cancelled</strong><br><small class="text-muted">1 hour ago</small></a>
                            <a class="dropdown-item" href="#"><strong>5 classes fully booked</strong><br><small class="text-muted">3 hours ago</small></a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-center" href="#">View all</a>
                        </div>
                    </div>
                    <!-- User dropdown -->
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
                            <h1><i class="fas fa-calendar-alt"></i> Manage Classes <small>View and manage all fitness classes</small></h1>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards (stats-card style) -->
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-primary text-white">
                            <div class="card-body">
                                <div class="card-title">Total Classes</div>
                                <h2><?php echo $total_classes; ?></h2>
                                <i class="fas fa-calendar-alt"></i>
                                <small>All classes</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <div class="card-title">Active Classes</div>
                                <h2><?php echo $active_classes; ?></h2>
                                <i class="fas fa-check-circle"></i>
                                <small>Currently active</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-warning text-white">
                            <div class="card-body">
                                <div class="card-title">Inactive</div>
                                <h2><?php echo $inactive_classes; ?></h2>
                                <i class="fas fa-ban"></i>
                                <small>Inactive classes</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <div class="card-title">Total Bookings</div>
                                <h2><?php echo $total_bookings; ?></h2>
                                <i class="fas fa-users"></i>
                                <small>All-time bookings</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Day Distribution Card -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> Classes by Day</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col">Mon: <span class="badge badge-primary"><?php echo $monday; ?></span></div>
                            <div class="col">Tue: <span class="badge badge-primary"><?php echo $tuesday; ?></span></div>
                            <div class="col">Wed: <span class="badge badge-primary"><?php echo $wednesday; ?></span></div>
                            <div class="col">Thu: <span class="badge badge-primary"><?php echo $thursday; ?></span></div>
                            <div class="col">Fri: <span class="badge badge-primary"><?php echo $friday; ?></span></div>
                            <div class="col">Sat: <span class="badge badge-primary"><?php echo $saturday; ?></span></div>
                            <div class="col">Sun: <span class="badge badge-primary"><?php echo $sunday; ?></span></div>
                        </div>
                    </div>
                </div>

                <!-- Filters Card -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-filter"></i> Filter Classes</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="form-row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Day</label>
                                <select name="day" class="form-control">
                                    <option value="">All Days</option>
                                    <option value="Monday" <?php echo $day_filter == 'Monday' ? 'selected' : ''; ?>>Monday</option>
                                    <option value="Tuesday" <?php echo $day_filter == 'Tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                                    <option value="Wednesday" <?php echo $day_filter == 'Wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                                    <option value="Thursday" <?php echo $day_filter == 'Thursday' ? 'selected' : ''; ?>>Thursday</option>
                                    <option value="Friday" <?php echo $day_filter == 'Friday' ? 'selected' : ''; ?>>Friday</option>
                                    <option value="Saturday" <?php echo $day_filter == 'Saturday' ? 'selected' : ''; ?>>Saturday</option>
                                    <option value="Sunday" <?php echo $day_filter == 'Sunday' ? 'selected' : ''; ?>>Sunday</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Trainer</label>
                                <select name="trainer" class="form-control">
                                    <option value="">All Trainers</option>
                                    <?php foreach ($trainers as $trainer): ?>
                                        <option value="<?php echo $trainer['id']; ?>" <?php echo $trainer_filter == $trainer['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($trainer['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Class name..." 
                                       value="<?php echo htmlspecialchars($search_term); ?>">
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Apply Filters
                                </button>
                                <?php if (!empty($day_filter) || !empty($trainer_filter) || !empty($status_filter) || !empty($search_term)): ?>
                                    <a href="manage_classes.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Clear Filters
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Classes Table Card -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list"></i> All Classes</h5>
                        <div>
                            <a href="add_class.php" class="btn btn-primary btn-sm mr-2"><i class="fas fa-plus-circle"></i> Add New Class</a>
                            <a href="class_schedule.php" class="btn btn-outline-info btn-sm"><i class="fas fa-clock"></i> View Schedule</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($classes)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No classes found</h5>
                                <?php if (!empty($day_filter) || !empty($trainer_filter) || !empty($status_filter) || !empty($search_term)): ?>
                                <p class="text-muted">Try adjusting your filters</p>
                                <a href="manage_classes.php" class="btn btn-primary">
                                    <i class="fas fa-times"></i> Clear Filters
                                </a>
                                <?php else: ?>
                                <a href="add_class.php" class="btn btn-primary mt-3">
                                    <i class="fas fa-plus-circle"></i> Add Your First Class
                                </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover" id="classesTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Class Name</th>
                                            <th>Day</th>
                                            <th>Time</th>
                                            <th>Duration</th>
                                            <th>Trainer</th>
                                            <th>Capacity</th>
                                            <th>Bookings</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($classes as $class): ?>
                                        <tr>
                                            <td><?php echo $class['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($class['class_name']); ?></strong>
                                                <?php if (!empty($class['description'])): ?>
                                                    <br><small class="text-muted"><?php echo substr(htmlspecialchars($class['description']), 0, 50); ?>...</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <?php echo $class['day_of_week']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('h:i A', strtotime($class['start_time'])); ?> - 
                                                <?php echo date('h:i A', strtotime($class['end_time'])); ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $start = new DateTime($class['start_time']);
                                                $end = new DateTime($class['end_time']);
                                                $diff = $start->diff($end);
                                                echo $diff->h . ' hr ' . $diff->i . ' min';
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($class['trainer_name']): ?>
                                                    <span class="badge badge-success">
                                                        <i class="fas fa-chalkboard-teacher mr-1"></i>
                                                        <?php echo htmlspecialchars($class['trainer_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Not Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-secondary"><?php echo $class['max_capacity']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    $booking_percentage = ($class['total_bookings'] / $class['max_capacity']) * 100;
                                                    if ($booking_percentage >= 80) echo 'danger';
                                                    elseif ($booking_percentage >= 50) echo 'warning';
                                                    else echo 'success';
                                                ?>">
                                                    <?php echo $class['total_bookings']; ?> total
                                                </span>
                                                <br>
                                                <small class="text-muted"><?php echo $class['upcoming_bookings']; ?> upcoming</small>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                                    <select name="status" class="form-control form-control-sm status-select" 
                                                            onchange="this.form.submit()" style="width: 90px;">
                                                        <option value="active" <?php echo $class['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo $class['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                    </select>
                                                    <input type="hidden" name="update_status" value="1">
                                                </form>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="edit_class.php?id=<?php echo $class['id']; ?>" 
                                                       class="btn btn-sm btn-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="view_class.php?id=<?php echo $class['id']; ?>" 
                                                       class="btn btn-sm btn-info" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="class_bookings.php?id=<?php echo $class['id']; ?>" 
                                                       class="btn btn-sm btn-success" title="View Bookings">
                                                        <i class="fas fa-users"></i>
                                                    </a>
                                                    <a href="?delete=<?php echo $class['id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this class?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions Card -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-6 mb-3">
                                <a href="add_class.php" class="btn btn-outline-primary btn-block py-3">
                                    <i class="fas fa-plus-circle fa-2x mb-2"></i><br>Add Class
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="class_schedule.php" class="btn btn-outline-success btn-block py-3">
                                    <i class="fas fa-calendar-alt fa-2x mb-2"></i><br>View Schedule
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="class_bookings.php" class="btn btn-outline-info btn-block py-3">
                                    <i class="fas fa-users fa-2x mb-2"></i><br>All Bookings
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="export_classes.php" class="btn btn-outline-secondary btn-block py-3">
                                    <i class="fas fa-file-excel fa-2x mb-2"></i><br>Export List
                                </a>
                            </div>
                        </div>
                    </div>
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
                    <a href="../../logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
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

            // Initialize DataTable
            $('#classesTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                language: {
                    search: "<i class='fas fa-search'></i>",
                    searchPlaceholder: "Search classes...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ classes",
                    infoEmpty: "Showing 0 to 0 of 0 classes",
                    infoFiltered: "(filtered from _MAX_ total classes)"
                },
                columnDefs: [
                    { orderable: false, targets: [9] } // Disable sorting on actions column
                ]
            });

            // Confirmation for status changes
            $('.status-select').on('change', function() {
                var className = $(this).closest('tr').find('td:eq(1)').text().trim();
                var newStatus = $(this).val();
                return confirm('Are you sure you want to change status for "' + className + '" to ' + newStatus + '?');
            });
        });

        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }
    </script>
</body>
</html>