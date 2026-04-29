<?php
// trainer/dashboard.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Path to root includes folder
$root_includes = __DIR__ . '/../includes/';

// Include required files
require_once $root_includes . 'config.php';
require_once $root_includes . 'session.php';
require_once $root_includes . 'functions.php';

// Check if user is trainer or admin
if (!Session::isTrainer() && !Session::isAdmin()) {
    Session::setFlash('danger', 'Access denied. Trainer login required.');
    header('Location: ../login.php');
    exit();
}

// Get trainer ID from session
$trainer_id = Session::userId();

// Initialize functions
$functions = new Functions();
$error = '';

// Get trainer's profile
$trainer = null;
try {
    $stmt = $pdo->prepare("SELECT u.*, t.specialization, t.experience_years, t.hourly_rate, t.qualification, t.availability
                           FROM users u
                           LEFT JOIN trainers t ON u.id = t.user_id
                           WHERE u.id = ?");
    $stmt->execute([$trainer_id]);
    $trainer = $stmt->fetch();
} catch (Exception $e) {
    $error = "Error loading profile: " . $e->getMessage();
}

// Get assigned members
$assigned_members = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.email, u.phone, u.status,
               m.membership_type, m.membership_end, m.fitness_goals,
               DATEDIFF(m.membership_end, CURDATE()) as days_left
        FROM users u
        JOIN members m ON u.id = m.user_id
        WHERE m.assigned_trainer_id = ? AND u.status = 'active'
        ORDER BY u.full_name
    ");
    $stmt->execute([$trainer_id]);
    $assigned_members = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading members: " . $e->getMessage();
}

// Get classes taught by this trainer
$my_classes = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM class_bookings cb JOIN class_schedule cs ON cb.schedule_id = cs.id WHERE cs.class_id = c.id AND cb.booking_date >= CURDATE()) as upcoming_bookings,
               (SELECT COUNT(*) FROM class_bookings cb JOIN class_schedule cs ON cb.schedule_id = cs.id WHERE cs.class_id = c.id AND cb.booking_date = CURDATE()) as today_bookings
        FROM classes c
        WHERE c.trainer_id = ? AND c.status = 'active'
        ORDER BY FIELD(c.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), c.start_time
    ");
    $stmt->execute([$trainer_id]);
    $my_classes = $stmt->fetchAll();
} catch (Exception $e) {
    // Classes table might not exist
    $my_classes = [];
}

// Get today's schedule
$today_schedule = [];
$today = date('l'); // e.g., 'Monday'
try {
    $stmt = $pdo->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM class_bookings cb JOIN class_schedule cs ON cb.schedule_id = cs.id WHERE cs.class_id = c.id AND cb.booking_date = CURDATE()) as booked_count
        FROM classes c
        WHERE c.trainer_id = ? AND c.day_of_week = ? AND c.status = 'active'
        ORDER BY c.start_time
    ");
    $stmt->execute([$trainer_id, $today]);
    $today_schedule = $stmt->fetchAll();
} catch (Exception $e) {
    // Ignore
}

// Get upcoming classes (next 7 days)
$upcoming_classes = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.class_name, c.day_of_week, c.start_time, c.end_time,
               cb.booking_date,
               u.full_name as member_name,
               cb.status as booking_status
        FROM class_bookings cb
        JOIN class_schedule cs ON cb.schedule_id = cs.id
        JOIN classes c ON cs.class_id = c.id
        JOIN users u ON cb.member_id = u.id
        WHERE c.trainer_id = ? AND cb.booking_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY cb.booking_date, c.start_time
    ");
    $stmt->execute([$trainer_id]);
    $upcoming_classes = $stmt->fetchAll();
} catch (Exception $e) {
    // Ignore
}

// Get recent attendance of assigned members
$recent_attendance = [];
if (!empty($assigned_members)) {
    $member_ids = array_column($assigned_members, 'id');
    $placeholders = implode(',', array_fill(0, count($member_ids), '?'));
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.full_name as member_name
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            WHERE a.user_id IN ($placeholders)
            ORDER BY a.date DESC
            LIMIT 20
        ");
        $stmt->execute($member_ids);
        $recent_attendance = $stmt->fetchAll();
    } catch (Exception $e) {
        // Attendance table may not exist
    }
}

$user_name = Session::userName();
$page_title = 'Trainer Dashboard - ' . APP_NAME;
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
                <p>Trainer Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li class="active">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                </li>
                <li>
                    <a href="my_members.php"><i class="fas fa-users"></i> My Members</a>
                </li>
                <li>
                    <a href="my_classes.php"><i class="fas fa-calendar-alt"></i> My Classes</a>
                </li>
                <li>
                    <a href="attendance.php"><i class="fas fa-clock"></i> Attendance</a>
                </li>
                <li>
                    <a href="workout_plans.php"><i class="fas fa-dumbbell"></i> Workout Plans</a>
                </li>
                <li>
                    <a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
                </li>
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
                            <a class="dropdown-item" href="#"><strong>New class assigned</strong><br><small class="text-muted">2 minutes ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Member checked in</strong><br><small class="text-muted">1 hour ago</small></a>
                            <a class="dropdown-item" href="#"><strong>5 workout plans due</strong><br><small class="text-muted">3 hours ago</small></a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-center" href="#">View all</a>
                        </div>
                    </div>
                    <!-- User dropdown -->
                    <div class="dropdown ml-3 d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown"><i class="fas fa-user-circle fa-lg"></i><span class="ml-2"><?php echo htmlspecialchars($user_name); ?></span></button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid">
                <!-- Page Header -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header">
                            <h1><i class="fas fa-chalkboard-teacher"></i> Welcome, <?php echo htmlspecialchars($user_name); ?>! <small><?php echo date('l, F j, Y'); ?></small></h1>
                        </div>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Trainer Profile Summary Card (light background) -->
                <?php if ($trainer): ?>
                <div class="card bg-light mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <strong><i class="fas fa-tag mr-2"></i>Specialization</strong>
                                <p><?php echo htmlspecialchars($trainer['specialization'] ?? 'General'); ?></p>
                            </div>
                            <div class="col-md-2">
                                <strong><i class="fas fa-briefcase mr-2"></i>Experience</strong>
                                <p><?php echo $trainer['experience_years'] ?? 0; ?> years</p>
                            </div>
                            <div class="col-md-2">
                                <strong><i class="fas fa-rupee-sign mr-2"></i>Hourly Rate</strong>
                                <p>₹<?php echo number_format($trainer['hourly_rate'] ?? 0, 2); ?></p>
                            </div>
                            <div class="col-md-5">
                                <strong><i class="fas fa-clock mr-2"></i>Availability</strong>
                                <p><?php echo nl2br(htmlspecialchars($trainer['availability'] ?? 'Not specified')); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Statistics Cards (stats-card style) -->
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-primary text-white">
                            <div class="card-body">
                                <div class="card-title">Assigned Members</div>
                                <h2><?php echo count($assigned_members); ?></h2>
                                <i class="fas fa-users"></i>
                                <small>Total members</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <div class="card-title">Classes Taught</div>
                                <h2><?php echo count($my_classes); ?></h2>
                                <i class="fas fa-calendar-alt"></i>
                                <small>Active classes</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <div class="card-title">Today's Classes</div>
                                <h2><?php echo count($today_schedule); ?></h2>
                                <i class="fas fa-calendar-day"></i>
                                <small>Classes today</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-warning text-white">
                            <div class="card-body">
                                <div class="card-title">Upcoming Bookings</div>
                                <h2><?php echo count($upcoming_classes); ?></h2>
                                <i class="fas fa-calendar-week"></i>
                                <small>Next 7 days</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Today's Schedule Card -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-day"></i> Today's Schedule (<?php echo $today; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($today_schedule)): ?>
                            <p class="text-muted mb-0">No classes scheduled for today.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Class</th>
                                            <th>Time</th>
                                            <th>Booked</th>
                                            <th>Capacity</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($today_schedule as $class): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                            <td><?php echo date('h:i A', strtotime($class['start_time'])) . ' - ' . date('h:i A', strtotime($class['end_time'])); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $class['booked_count'] >= $class['max_capacity'] ? 'danger' : 'success'; ?>">
                                                    <?php echo $class['booked_count']; ?>/<?php echo $class['max_capacity']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $class['max_capacity']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Assigned Members Card -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-users"></i> My Assigned Members</h5>
                        <a href="my_members.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($assigned_members)): ?>
                            <p class="text-muted p-3">You have no assigned members yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Contact</th>
                                            <th>Membership</th>
                                            <th>Expiry</th>
                                            <th>Goals</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assigned_members as $member): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($member['full_name']); ?></strong></td>
                                            <td>
                                                <a href="mailto:<?php echo $member['email']; ?>"><?php echo $member['email']; ?></a><br>
                                                <small><?php echo $member['phone'] ?? 'No phone'; ?></small>
                                            </td>
                                            <td><span class="badge badge-info"><?php echo ucfirst($member['membership_type']); ?></span></td>
                                            <td>
                                                <?php
                                                if ($member['days_left'] < 0) {
                                                    echo '<span class="badge badge-danger">Expired</span>';
                                                } elseif ($member['days_left'] <= 7) {
                                                    echo '<span class="badge badge-warning">' . $member['days_left'] . ' days left</span>';
                                                } else {
                                                    echo '<span class="badge badge-success">' . date('M d, Y', strtotime($member['membership_end'])) . '</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars(substr($member['fitness_goals'] ?? '', 0, 50)) . (strlen($member['fitness_goals'] ?? '') > 50 ? '...' : ''); ?></small>
                                            </td>
                                            <td>
                                                <a href="view_member.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                                <a href="create_workout.php?member_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-success"><i class="fas fa-dumbbell"></i></a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Classes & Recent Attendance (two columns) -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-calendar-week"></i> Upcoming Bookings (Next 7 Days)</h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($upcoming_classes)): ?>
                                    <p class="text-muted p-3">No upcoming bookings.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($upcoming_classes as $booking): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($booking['class_name']); ?></strong><br>
                                                <small class="text-muted">
                                                    <?php echo date('D, M d', strtotime($booking['booking_date'])); ?> at
                                                    <?php echo date('h:i A', strtotime($booking['start_time'])); ?>
                                                </small>
                                                <br>
                                                <small>Member: <?php echo htmlspecialchars($booking['member_name']); ?></small>
                                            </div>
                                            <span class="badge badge-<?php echo $booking['booking_status'] == 'booked' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($booking['booking_status']); ?>
                                            </span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-clock"></i> Recent Member Attendance</h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($recent_attendance)): ?>
                                    <p class="text-muted p-3">No recent attendance records.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach (array_slice($recent_attendance, 0, 10) as $att): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($att['member_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo date('M d, Y', strtotime($att['date'])); ?></small>
                                            </div>
                                            <span class="badge badge-<?php echo $att['status'] == 'present' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($att['status']); ?>
                                            </span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
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
                                <a href="mark_attendance.php" class="btn btn-outline-primary btn-block py-3">
                                    <i class="fas fa-check-circle fa-2x mb-2"></i><br>Mark Attendance
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="workout_plans.php" class="btn btn-outline-success btn-block py-3">
                                    <i class="fas fa-dumbbell fa-2x mb-2"></i><br>Create Workout Plan
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="my_members.php" class="btn btn-outline-info btn-block py-3">
                                    <i class="fas fa-users fa-2x mb-2"></i><br>View Members
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="my_classes.php" class="btn btn-outline-warning btn-block py-3">
                                    <i class="fas fa-calendar-alt fa-2x mb-2"></i><br>My Classes
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
    </script>
</body>
</html>