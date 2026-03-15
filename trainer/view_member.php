<?php
// trainer/view_member.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Check if user is trainer or admin
if (!Session::isTrainer() && !Session::isAdmin()) {
    Session::setFlash('danger', 'Access denied. Trainer login required.');
    header('Location: ../login.php');
    exit();
}

$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($member_id <= 0) {
    Session::setFlash('danger', 'Invalid member ID.');
    header('Location: my_members.php');
    exit();
}

$functions = new Functions();
$error = '';

// Fetch member details
$member = null;
try {
    $stmt = $pdo->prepare("
        SELECT u.*, m.membership_type, m.membership_start, m.membership_end,
               m.height, m.weight, m.fitness_goals, m.emergency_contact, m.emergency_phone,
               m.assigned_trainer_id, t.full_name as trainer_name,
               DATEDIFF(m.membership_end, CURDATE()) as days_left
        FROM users u
        JOIN members m ON u.id = m.user_id
        LEFT JOIN users t ON m.assigned_trainer_id = t.id
        WHERE u.id = ? AND u.user_type = 'member'
    ");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

if (!$member) {
    Session::setFlash('danger', 'Member not found.');
    header('Location: my_members.php');
    exit();
}

// Fetch recent attendance (last 10)
$attendance = [];
try {
    $stmt = $pdo->prepare("
        SELECT date, check_in, check_out, status
        FROM attendance
        WHERE user_id = ?
        ORDER BY date DESC
        LIMIT 10
    ");
    $stmt->execute([$member_id]);
    $attendance = $stmt->fetchAll();
} catch (Exception $e) {
    // ignore
}

// Fetch workout plans
$workout_plans = [];
try {
    $stmt = $pdo->prepare("
        SELECT wp.*, u.full_name as trainer_name
        FROM workout_plans wp
        LEFT JOIN users u ON wp.trainer_id = u.id
        WHERE wp.member_id = ?
        ORDER BY wp.created_at DESC
    ");
    $stmt->execute([$member_id]);
    $workout_plans = $stmt->fetchAll();
} catch (Exception $e) {
    // ignore
}

// Fetch recent payments
$payments = [];
try {
    $stmt = $pdo->prepare("
        SELECT amount, payment_date, payment_method, status
        FROM payments
        WHERE member_id = ?
        ORDER BY payment_date DESC
        LIMIT 5
    ");
    $stmt->execute([$member_id]);
    $payments = $stmt->fetchAll();
} catch (Exception $e) {
    // ignore
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
    <!-- Bootstrap 4 CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome 5 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <!-- Chart.js (optional) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}
        .wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}
        #sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}
        #sidebar.active{margin-left:-280px}
        #sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}
        #sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}
        #sidebar ul.components{padding:20px 0}
        #sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}
        #sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}
        #sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}
        #sidebar ul li a i{margin-right:10px;width:25px;text-align:center}
        #sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}
        #sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}
        #content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}
        .navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}
        .page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}
        .page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}
        .page-header h1 i{color:#667eea;margin-right:10px}
        .card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}
        .card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:15px 20px;border-radius:15px 15px 0 0!important}
        .card-header h5{margin:0;font-weight:600;color:#333}
        .card-header h5 i{color:#667eea;margin-right:10px}
        .card-body{padding:20px}
        .table{margin:0}
        .table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px}
        .badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}
        .badge-success{background:#d4edda;color:#155724}
        .badge-warning{background:#fff3cd;color:#856404}
        .badge-danger{background:#f8d7da;color:#721c24}
        .badge-info{background:#d1ecf1;color:#0c5460}
        .alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}
        .info-row{display:flex;margin-bottom:10px;border-bottom:1px solid #f0f0f0;padding-bottom:10px}
        .info-label{font-weight:600;width:150px;color:#555}
        .info-value{color:#333;flex:1}
        @media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}}
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Trainer Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="my_members.php"><i class="fas fa-users"></i> My Members</a></li>
                <li><a href="my_classes.php"><i class="fas fa-calendar-alt"></i> My Classes</a></li>
                <li><a href="attendance.php"><i class="fas fa-clock"></i> Attendance</a></li>
                <li><a href="workout_plans.php"><i class="fas fa-dumbbell"></i> Workout Plans</a></li>
                <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
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
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown">
                            <i class="fas fa-user-circle fa-lg"></i> <span class="ml-2"><?php echo htmlspecialchars($user_name); ?></span>
                        </button>
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

                <?php Session::displayFlash(); ?>

                <!-- Page Header -->
                <div class="page-header">
                    <h1><i class="fas fa-user-circle"></i> Member Profile: <?php echo htmlspecialchars($member['full_name']); ?></h1>
                </div>

                <!-- Member Overview Cards -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-id-card"></i> Membership</h5>
                                <p class="mb-1"><strong>Type:</strong> <?php echo ucfirst($member['membership_type']); ?></p>
                                <p class="mb-1"><strong>Start:</strong> <?php echo date('M d, Y', strtotime($member['membership_start'])); ?></p>
                                <p class="mb-1"><strong>End:</strong> <?php echo date('M d, Y', strtotime($member['membership_end'])); ?></p>
                                <p class="mb-0">
                                    <strong>Status:</strong>
                                    <?php if ($member['days_left'] < 0): ?>
                                        <span class="badge badge-danger">Expired</span>
                                    <?php elseif ($member['days_left'] <= 7): ?>
                                        <span class="badge badge-warning"><?php echo $member['days_left']; ?> days left</span>
                                    <?php else: ?>
                                        <span class="badge badge-success"><?php echo $member['days_left']; ?> days left</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-phone-alt"></i> Contact</h5>
                                <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($member['email']); ?></p>
                                <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></p>
                                <p class="mb-0"><strong>Address:</strong> <?php echo htmlspecialchars($member['address'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-heartbeat"></i> Health</h5>
                                <p class="mb-1"><strong>Height:</strong> <?php echo $member['height'] ? $member['height'] . ' cm' : 'N/A'; ?></p>
                                <p class="mb-1"><strong>Weight:</strong> <?php echo $member['weight'] ? $member['weight'] . ' kg' : 'N/A'; ?></p>
                                <p class="mb-0"><strong>BMI:</strong>
                                    <?php
                                    if ($member['height'] && $member['weight']) {
                                        $bmi = $member['weight'] / (($member['height']/100) * ($member['height']/100));
                                        echo number_format($bmi, 1);
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Info -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-info-circle"></i> Personal Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="info-row">
                                    <span class="info-label">Full Name:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($member['full_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Username:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($member['username']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Email:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($member['email']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Phone:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Address:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($member['address'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Assigned Trainer:</span>
                                    <span class="info-value">
                                        <?php echo $member['trainer_name'] ? htmlspecialchars($member['trainer_name']) : 'Not Assigned'; ?>
                                        <?php if ($member['assigned_trainer_id'] == Session::userId()): ?>
                                            <span class="badge badge-success">You</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-exclamation-triangle"></i> Emergency Contact</h5>
                            </div>
                            <div class="card-body">
                                <div class="info-row">
                                    <span class="info-label">Name:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($member['emergency_contact'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Phone:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($member['emergency_phone'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-bullseye"></i> Fitness Goals</h5>
                            </div>
                            <div class="card-body">
                                <p><?php echo nl2br(htmlspecialchars($member['fitness_goals'] ?? 'No goals specified.')); ?></p>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-calendar-check"></i> Recent Attendance</h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($attendance)): ?>
                                    <p class="text-muted p-3">No attendance records found.</p>
                                <?php else: ?>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Check In</th>
                                                <th>Check Out</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attendance as $a): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($a['date'])); ?></td>
                                                <td><?php echo $a['check_in'] ? date('h:i A', strtotime($a['check_in'])) : '-'; ?></td>
                                                <td><?php echo $a['check_out'] ? date('h:i A', strtotime($a['check_out'])) : '-'; ?></td>
                                                <td>
                                                    <span class="badge badge-<?php
                                                        echo $a['status'] == 'present' ? 'success' : ($a['status'] == 'late' ? 'warning' : 'danger');
                                                    ?>"><?php echo ucfirst($a['status']); ?></span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Workout Plans -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-dumbbell"></i> Workout Plans</h5>
                        <a href="create_workout.php?member_id=<?php echo $member_id; ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-plus-circle"></i> New Plan
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($workout_plans)): ?>
                            <p class="text-muted p-3">No workout plans found.</p>
                        <?php else: ?>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Plan Name</th>
                                        <th>Trainer</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($workout_plans as $plan): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($plan['plan_name']); ?></td>
                                        <td><?php echo htmlspecialchars($plan['trainer_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo $plan['start_date'] ? date('M d, Y', strtotime($plan['start_date'])) : '-'; ?></td>
                                        <td><?php echo $plan['end_date'] ? date('M d, Y', strtotime($plan['end_date'])) : '-'; ?></td>
                                        <td>
                                            <span class="badge badge-<?php
                                                echo $plan['status'] == 'active' ? 'success' : ($plan['status'] == 'completed' ? 'info' : 'secondary');
                                            ?>"><?php echo ucfirst($plan['status']); ?></span>
                                        </td>
                                        <td>
                                            <a href="view_workout.php?id=<?php echo $plan['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_workout.php?id=<?php echo $plan['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Payments -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-credit-card"></i> Recent Payments</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($payments)): ?>
                            <p class="text-muted p-3">No payment records found.</p>
                        <?php else: ?>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($p['payment_date'])); ?></td>
                                        <td>$<?php echo number_format($p['amount'], 2); ?></td>
                                        <td><?php echo ucfirst($p['payment_method']); ?></td>
                                        <td>
                                            <span class="badge badge-success"><?php echo ucfirst($p['status']); ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="mark_attendance.php?member_id=<?php echo $member_id; ?>" class="btn btn-outline-success btn-block py-3">
                                            <i class="fas fa-clock fa-2x mb-2"></i><br>Mark Attendance
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="create_workout.php?member_id=<?php echo $member_id; ?>" class="btn btn-outline-primary btn-block py-3">
                                            <i class="fas fa-dumbbell fa-2x mb-2"></i><br>Create Workout
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="message_member.php?member_id=<?php echo $member_id; ?>" class="btn btn-outline-warning btn-block py-3">
                                            <i class="fas fa-envelope fa-2x mb-2"></i><br>Send Message
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="progress.php?member_id=<?php echo $member_id; ?>" class="btn btn-outline-info btn-block py-3">
                                            <i class="fas fa-chart-line fa-2x mb-2"></i><br>Track Progress
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
    <div class="modal fade" id="logoutModal" tabindex="-1">
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
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <a href="../logout.php" class="btn btn-danger">Logout</a>
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