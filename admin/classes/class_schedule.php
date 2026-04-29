<?php
// admin/classes/class_schedule.php
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

// Handle booking cancellation if needed
if (isset($_GET['cancel_booking'])) {
    try {
        $booking_id = (int)$_GET['cancel_booking'];
        $stmt = $pdo->prepare("UPDATE class_bookings SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$booking_id]);
        Session::setFlash('success', 'Booking cancelled successfully');
        header('Location: class_schedule.php');
        exit();
    } catch (Exception $e) {
        $error = "Error cancelling booking: " . $e->getMessage();
    }
}

// Get all active classes grouped by day
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$schedule = [];
$class_counts = [];
$total_classes = 0;
$total_trainers = 0;
$upcoming_bookings = [];

// Get time slots for each day
try {
    foreach ($days as $day) {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   u.full_name as trainer_name,
                   u.id as trainer_id,
                   (SELECT COUNT(*) FROM class_bookings cb JOIN class_schedule cs ON cb.schedule_id = cs.id WHERE cs.class_id = c.id AND cb.booking_date >= CURDATE()) as upcoming_bookings,
                   (SELECT COUNT(*) FROM class_bookings cb JOIN class_schedule cs ON cb.schedule_id = cs.id WHERE cs.class_id = c.id AND cb.booking_date = CURDATE()) as today_bookings
            FROM classes c
            LEFT JOIN users u ON c.trainer_id = u.id
            WHERE c.day_of_week = ? AND c.status = 'active'
            ORDER BY c.start_time
        ");
        $stmt->execute([$day]);
        $schedule[$day] = $stmt->fetchAll();
        $class_counts[$day] = count($schedule[$day]);
    }
    
    // Get total stats
    $total_classes = array_sum($class_counts);
    $total_trainers = $pdo->query("SELECT COUNT(DISTINCT trainer_id) FROM classes WHERE status = 'active' AND trainer_id IS NOT NULL")->fetchColumn();
    
    // Get upcoming bookings for next 7 days
    $stmt = $pdo->prepare("
        SELECT cb.*, c.class_name, c.start_time, c.end_time, u.full_name as member_name, c.day_of_week
        FROM class_bookings cb
        JOIN class_schedule cs ON cb.schedule_id = cs.id
        JOIN classes c ON cs.class_id = c.id
        JOIN users u ON cb.member_id = u.id
        WHERE cb.booking_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND cb.status = 'booked'
        ORDER BY cb.booking_date, c.start_time
    ");
    $stmt->execute();
    $upcoming_bookings = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Error loading schedule: " . $e->getMessage();
    $schedule = [];
    $class_counts = [];
    $upcoming_bookings = [];
}

// Get current week dates
$week_dates = [];
$today = new DateTime();
for ($i = 0; $i < 7; $i++) {
    $date = clone $today;
    $date->modify("monday this week +$i days");
    $week_dates[$days[$i]] = $date->format('Y-m-d');
}

$user_name = Session::userName();
$page_title = 'Class Schedule - ' . APP_NAME;
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

/* Original schedule styles (preserved) */
.schedule-container { background: #fff; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); overflow: hidden; }
.schedule-header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 20px; }
.schedule-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #e0e0e0; border: 1px solid #e0e0e0; }
.schedule-day { background: #f8f9fa; min-height: 400px; position: relative; }
.schedule-day-header { background: #e9ecef; padding: 15px; text-align: center; font-weight: 600; border-bottom: 2px solid #4158D0; }
.schedule-day-header .date { font-size: 0.85rem; color: #6c757d; margin-top: 5px; }
.schedule-day-header .class-count { position: absolute; top: 10px; right: 10px; background: #4158D0; color: white; border-radius: 20px; padding: 2px 8px; font-size: 0.75rem; }
.schedule-items { padding: 10px; }
.class-card { background: white; border-radius: 10px; padding: 12px; margin-bottom: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-left: 4px solid #4158D0; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; position: relative; }
.class-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(65, 88, 208, 0.2); }
.class-card.warning { border-left-color: #ffc107; }
.class-card.danger { border-left-color: #dc3545; }
.class-card.success { border-left-color: #28a745; }
.class-time { font-size: 0.85rem; color: #4158D0; font-weight: 600; margin-bottom: 5px; }
.class-time i { margin-right: 5px; }
.class-name { font-weight: 600; margin-bottom: 5px; font-size: 1rem; }
.class-trainer { font-size: 0.8rem; color: #6c757d; margin-bottom: 8px; }
.class-trainer i { margin-right: 5px; color: #28a745; }
.class-capacity { display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; }
.capacity-bar { flex-grow: 1; height: 5px; background: #e9ecef; border-radius: 5px; margin: 0 10px; overflow: hidden; }
.capacity-fill { height: 100%; background: linear-gradient(90deg, #4158D0, #C850C0); border-radius: 5px; }
.class-actions { position: absolute; top: 10px; right: 10px; opacity: 0; transition: opacity 0.2s; }
.class-card:hover .class-actions { opacity: 1; }
.class-actions .btn-sm { padding: 2px 5px; font-size: 0.7rem; }
.timeline-container { margin-top: 30px; }
.timeline-header { display: grid; grid-template-columns: 100px repeat(7, 1fr); background: #f8f9fa; padding: 10px; font-weight: 600; border-bottom: 2px solid #4158D0; }
.timeline-row { display: grid; grid-template-columns: 100px repeat(7, 1fr); border-bottom: 1px solid #e9ecef; min-height: 60px; }
.timeline-time { padding: 10px; background: #f8f9fa; font-weight: 600; color: #4158D0; border-right: 1px solid #e9ecef; }
.timeline-cell { padding: 5px; border-right: 1px solid #e9ecef; position: relative; }
.timeline-event { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 5px 8px; border-radius: 5px; font-size: 0.8rem; cursor: pointer; transition: transform 0.2s; margin: 2px 0; }
.timeline-event:hover { transform: scale(1.02); box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
@media (max-width: 768px) { .schedule-grid { grid-template-columns: 1fr; } .timeline-header, .timeline-row { display: none; } .mobile-view { display: block; } }
.mobile-view { display: none; }
.mobile-day-card { background: white; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; }
.mobile-day-header { background: linear-gradient(135deg, #4158D0, #C850C0); color: white; padding: 15px; font-weight: 600; }
.mobile-day-header small { opacity: 0.8; margin-left: 10px; }
.mobile-class-item { padding: 15px; border-bottom: 1px solid #e9ecef; }
.mobile-class-item:last-child { border-bottom: none; }
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
                        <li><a href="manage_classes.php"><i class="fas fa-list"></i> All Classes</a></li>
                        <li><a href="add_class.php"><i class="fas fa-plus-circle"></i> Add Class</a></li>
                        <li class="active"><a href="class_schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
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
                            <h1><i class="fas fa-calendar-alt"></i> Weekly Class Schedule <small>View and manage all classes</small></h1>
                        </div>
                    </div>
                </div>

                <!-- Summary Stats (stats-card style) -->
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-primary text-white">
                            <div class="card-body">
                                <div class="card-title">Total Weekly Classes</div>
                                <h2><?php echo $total_classes; ?></h2>
                                <i class="fas fa-calendar-alt"></i>
                                <small>All classes</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <div class="card-title">Active Trainers</div>
                                <h2><?php echo $total_trainers; ?></h2>
                                <i class="fas fa-chalkboard-teacher"></i>
                                <small>Teaching this week</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <div class="card-title">Upcoming Bookings</div>
                                <h2><?php echo count($upcoming_bookings); ?></h2>
                                <i class="fas fa-users"></i>
                                <small>Next 7 days</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-warning text-white">
                            <div class="card-body">
                                <div class="card-title">Busiest Day</div>
                                <h2>
                                    <?php 
                                    if (!empty($class_counts)) {
                                        $max_day = array_search(max($class_counts), $class_counts);
                                        echo $max_day ? substr($max_day, 0, 3) : 'N/A';
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </h2>
                                <i class="fas fa-chart-line"></i>
                                <small>Most classes</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- View Toggle -->
                <div class="btn-group mb-3" role="group">
                    <button type="button" class="btn btn-outline-primary active" id="gridViewBtn">
                        <i class="fas fa-th-large mr-2"></i>Grid View
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="timelineViewBtn">
                        <i class="fas fa-clock mr-2"></i>Timeline View
                    </button>
                </div>

                <!-- Grid View -->
                <div id="gridView">
                    <div class="schedule-container">
                        <div class="schedule-header">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h4 class="mb-0">Weekly Class Schedule</h4>
                                    <small>Week of <?php echo $week_dates['Monday']; ?></small>
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-light btn-sm" onclick="window.print()">
                                        <i class="fas fa-print mr-2"></i>Print
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Desktop Grid View -->
                        <div class="schedule-grid d-none d-md-grid">
                            <?php foreach ($days as $day): ?>
                                <div class="schedule-day">
                                    <div class="schedule-day-header">
                                        <?php echo substr($day, 0, 3); ?>
                                        <div class="date"><?php echo date('M d', strtotime($week_dates[$day])); ?></div>
                                        <span class="class-count"><?php echo $class_counts[$day]; ?></span>
                                    </div>
                                    <div class="schedule-items">
                                        <?php if (empty($schedule[$day])): ?>
                                            <div class="text-center text-muted p-3">
                                                <small>No classes</small>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($schedule[$day] as $class): 
                                                $capacity_percentage = ($class['upcoming_bookings'] / $class['max_capacity']) * 100;
                                                $card_class = 'class-card';
                                                if ($capacity_percentage >= 80) $card_class .= ' danger';
                                                elseif ($capacity_percentage >= 50) $card_class .= ' warning';
                                                else $card_class .= ' success';
                                            ?>
                                                <div class="<?php echo $card_class; ?>" 
                                                     onclick="viewClassDetails(<?php echo $class['id']; ?>)">
                                                    <div class="class-time">
                                                        <i class="far fa-clock"></i>
                                                        <?php echo date('h:i A', strtotime($class['start_time'])); ?> - 
                                                        <?php echo date('h:i A', strtotime($class['end_time'])); ?>
                                                    </div>
                                                    <div class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></div>
                                                    <div class="class-trainer">
                                                        <i class="fas fa-chalkboard-teacher"></i>
                                                        <?php echo $class['trainer_name'] ?? 'TBA'; ?>
                                                    </div>
                                                    <div class="class-capacity">
                                                        <small><?php echo $class['upcoming_bookings']; ?>/<?php echo $class['max_capacity']; ?></small>
                                                        <div class="capacity-bar">
                                                            <div class="capacity-fill" style="width: <?php echo $capacity_percentage; ?>%;"></div>
                                                        </div>
                                                    </div>
                                                    <div class="class-actions">
                                                        <a href="edit_class.php?id=<?php echo $class['id']; ?>" 
                                                           class="btn btn-sm btn-light" 
                                                           onclick="event.stopPropagation();">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Mobile View -->
                        <div class="mobile-view d-md-none">
                            <?php foreach ($days as $day): ?>
                                <div class="mobile-day-card">
                                    <div class="mobile-day-header">
                                        <?php echo $day; ?> 
                                        <small><?php echo date('M d', strtotime($week_dates[$day])); ?></small>
                                    </div>
                                    <?php if (empty($schedule[$day])): ?>
                                        <div class="text-center text-muted p-4">
                                            <i class="fas fa-calendar-times fa-2x mb-2"></i>
                                            <p>No classes scheduled</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($schedule[$day] as $class): ?>
                                            <div class="mobile-class-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($class['class_name']); ?></h6>
                                                        <p class="mb-1">
                                                            <i class="far fa-clock text-primary mr-1"></i>
                                                            <?php echo date('h:i A', strtotime($class['start_time'])); ?> - 
                                                            <?php echo date('h:i A', strtotime($class['end_time'])); ?>
                                                        </p>
                                                        <p class="mb-0">
                                                            <i class="fas fa-chalkboard-teacher text-success mr-1"></i>
                                                            <?php echo $class['trainer_name'] ?? 'TBA'; ?>
                                                        </p>
                                                    </div>
                                                    <a href="edit_class.php?id=<?php echo $class['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </div>
                                                <div class="mt-2">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small>Capacity: <?php echo $class['upcoming_bookings']; ?>/<?php echo $class['max_capacity']; ?></small>
                                                        <span class="badge badge-<?php 
                                                            $cap = ($class['upcoming_bookings'] / $class['max_capacity']) * 100;
                                                            if ($cap >= 80) echo 'danger';
                                                            elseif ($cap >= 50) echo 'warning';
                                                            else echo 'success';
                                                        ?>">
                                                            <?php echo round($cap); ?>% full
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Timeline View (Hidden by default) -->
                <div id="timelineView" style="display: none;">
                    <div class="schedule-container">
                        <div class="schedule-header">
                            <h4 class="mb-0">Weekly Timeline</h4>
                        </div>
                        
                        <!-- Desktop Timeline -->
                        <div class="d-none d-md-block">
                            <div class="timeline-container">
                                <div class="timeline-header">
                                    <div>Time</div>
                                    <?php foreach ($days as $day): ?>
                                        <div><?php echo substr($day, 0, 3); ?> <?php echo date('d', strtotime($week_dates[$day])); ?></div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php 
                                $time_slots = ['06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00'];
                                foreach ($time_slots as $time): 
                                ?>
                                    <div class="timeline-row">
                                        <div class="timeline-time"><?php echo date('h:i A', strtotime($time)); ?></div>
                                        <?php foreach ($days as $day): ?>
                                            <div class="timeline-cell">
                                                <?php 
                                                if (!empty($schedule[$day])) {
                                                    foreach ($schedule[$day] as $class) {
                                                        $class_start = date('H:i', strtotime($class['start_time']));
                                                        $class_end = date('H:i', strtotime($class['end_time']));
                                                        $slot_start = date('H:i', strtotime($time));
                                                        $slot_end = date('H:i', strtotime($time . ' +1 hour'));
                                                        
                                                        if ($class_start >= $slot_start && $class_start < $slot_end) {
                                                            echo '<div class="timeline-event" onclick="viewClassDetails(' . $class['id'] . ')">';
                                                            echo '<strong>' . htmlspecialchars($class['class_name']) . '</strong><br>';
                                                            echo '<small>' . ($class['trainer_name'] ?? 'TBA') . '</small>';
                                                            echo '</div>';
                                                        }
                                                    }
                                                }
                                                ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Mobile Timeline (same as grid for mobile) -->
                        <div class="d-md-none">
                            <p class="text-center p-4">Timeline view is available on desktop. Please use grid view on mobile.</p>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Bookings Section -->
                <?php if (!empty($upcoming_bookings)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-check mr-2"></i>Upcoming Bookings (Next 7 Days)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Class</th>
                                        <th>Time</th>
                                        <th>Member</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_bookings as $booking): ?>
                                    <tr>
                                        <td><?php echo date('D, M d', strtotime($booking['booking_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($booking['class_name']); ?></td>
                                        <td><?php echo date('h:i A', strtotime($booking['start_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($booking['member_name']); ?></td>
                                        <td><span class="badge badge-success">Booked</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Class Details Modal (placeholder, not used but kept) -->
    <div class="modal fade" id="classDetailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-info-circle mr-2"></i>Class Details</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" id="classDetailsContent">
                    Loading...
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <a href="#" id="editClassBtn" class="btn btn-primary">Edit Class</a>
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
        });

        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }

        // Toggle between grid and timeline views
        document.getElementById('gridViewBtn').addEventListener('click', function() {
            document.getElementById('gridView').style.display = 'block';
            document.getElementById('timelineView').style.display = 'none';
            this.classList.add('active');
            document.getElementById('timelineViewBtn').classList.remove('active');
        });

        document.getElementById('timelineViewBtn').addEventListener('click', function() {
            document.getElementById('gridView').style.display = 'none';
            document.getElementById('timelineView').style.display = 'block';
            this.classList.add('active');
            document.getElementById('gridViewBtn').classList.remove('active');
        });

        // View class details (redirects to edit page)
        function viewClassDetails(classId) {
            window.location.href = 'edit_class.php?id=' + classId;
        }

        // Auto-refresh every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>