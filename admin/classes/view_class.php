<?php
// admin/classes/view_class.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

// Require admin access
Session::requireAdmin();

$functions = new Functions();
$error = '';
$success = '';

// Get class ID from URL
$class_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($class_id <= 0) {
    header('Location: manage_classes.php');
    exit;
}

// Fetch class details
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name as trainer_name, u.email as trainer_email
        FROM classes c
        LEFT JOIN users u ON c.trainer_id = u.id AND u.user_type = 'trainer'
        WHERE c.id = ?
    ");
    $stmt->execute([$class_id]);
    $class = $stmt->fetch();

    if (!$class) {
        $error = "Class not found.";
    }

    // Fetch enrolled members for this class
    try {
        $stmt_members = $pdo->prepare("
            SELECT u.id, u.full_name, u.email, u.status, ce.enrolled_at
            FROM class_enrollments ce
            JOIN users u ON ce.user_id = u.id
            WHERE ce.class_id = ? AND u.user_type = 'member'
            ORDER BY ce.enrolled_at DESC
        ");
        $stmt_members->execute([$class_id]);
        $enrolled_members = $stmt_members->fetchAll();
    } catch (Exception $e) {
        $enrolled_members = [];
    }

    // Fetch upcoming schedules for this class (optional)
    try {
        $stmt_schedule = $pdo->prepare("
            SELECT * FROM class_schedule
            WHERE class_id = ? AND schedule_date >= CURDATE()
            ORDER BY schedule_date ASC, start_time ASC
            LIMIT 5
        ");
        $stmt_schedule->execute([$class_id]);
        $upcoming_schedules = $stmt_schedule->fetchAll();
    } catch (Exception $e) {
        $upcoming_schedules = [];
    }

} catch (Exception $e) {
    $error = "Error loading class details: " . $e->getMessage();
    $class = null;
    $enrolled_members = [];
    $upcoming_schedules = [];
}

$user_name = Session::userName();
$page_title = 'View Class - ' . APP_NAME;
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
    <style>
        /* Copy all the CSS from dashboard.php here (or include it externally) */
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}}
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar (same as dashboard) -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Administrator Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="../manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="../members/manage_members.php"><i class="fas fa-user"></i> Members</a></li>
                <li><a href="../manage_trainers.php"><i class="fas fa-chalkboard-teacher"></i> Trainers</a></li>
                <li class="active"><a href="manage_classes.php"><i class="fas fa-calendar-alt"></i> Classes</a></li>
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
                            <h1><i class="fas fa-calendar-alt"></i> Class Details 
                                <small><?php echo htmlspecialchars($class['name'] ?? 'Class not found'); ?></small>
                            </h1>
                        </div>
                    </div>
                </div>

                <?php if ($class): ?>
                    <div class="row">
                        <!-- Class Information Card -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-info-circle"></i> Class Information</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th style="width: 30%">Class Name</th>
                                            <td><?php echo htmlspecialchars($class['name'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Description</th>
                                            <td><?php echo nl2br(htmlspecialchars($class['description'] ?? 'No description')); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Capacity</th>
                                            <td><?php echo htmlspecialchars((string)($class['capacity'] ?? 0)); ?> participants</td>
                                        </tr>
                                        <tr>
                                            <th>Enrolled Members</th>
                                            <td><?php echo count($enrolled_members); ?> / <?php echo htmlspecialchars((string)($class['capacity'] ?? 0)); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Trainer</th>
                                            <td>
                                                <?php if ($class['trainer_name']): ?>
                                                    <strong><?php echo htmlspecialchars($class['trainer_name']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($class['trainer_email']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td>
                                                <?php if ($class['status'] == 'active'): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Created At</th>
                                            <td><?php echo date('M d, Y', strtotime($class['created_at'])); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Upcoming Schedules Card -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-clock"></i> Upcoming Schedule</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($upcoming_schedules)): ?>
                                        <p class="text-muted text-center py-3">No upcoming schedules found.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Time</th>
                                                        <th>Room</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($upcoming_schedules as $schedule): ?>
                                                    <tr>
                                                        <td><?php echo date('M d, Y', strtotime($schedule['schedule_date'])); ?></td>
                                                        <td><?php echo date('h:i A', strtotime($schedule['start_time'])); ?> - <?php echo date('h:i A', strtotime($schedule['end_time'])); ?></td>
                                                        <td><?php echo htmlspecialchars($schedule['room'] ?? 'TBD'); ?></td>
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

                    <!-- Enrolled Members Card -->
                    <div class="card mt-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-users"></i> Enrolled Members (<?php echo count($enrolled_members); ?>)</h5>
                            <?php if (count($enrolled_members) < ($class['capacity'] ?? 0)): ?>
                                <a href="enroll_member.php?class_id=<?php echo $class_id; ?>" class="btn btn-sm btn-primary"><i class="fas fa-user-plus"></i> Enroll Member</a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($enrolled_members)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No members enrolled yet.</p>
                                    <a href="enroll_member.php?class_id=<?php echo $class_id; ?>" class="btn btn-primary"><i class="fas fa-user-plus"></i> Enroll First Member</a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Enrolled Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($enrolled_members as $member): ?>
                                            <tr>
                                                <td><?php echo $member['id']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-circle bg-info text-white mr-2" style="width:35px;height:35px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;">
                                                            <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                                                        </div>
                                                        <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($member['enrolled_at'])); ?></td>
                                                <td><span class="badge badge-<?php echo $member['status'] == 'active' ? 'success' : 'danger'; ?>"><?php echo ucfirst($member['status']); ?></span></td>
                                                <td>
                                                    <a href="remove_enrollment.php?class_id=<?php echo $class_id; ?>&user_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this member from class?')"><i class="fas fa-trash"></i> Remove</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 col-6 mb-3">
                                    <a href="edit_class.php?id=<?php echo $class_id; ?>" class="btn btn-outline-primary btn-block py-3">
                                        <i class="fas fa-edit fa-2x mb-2"></i><br>Edit Class
                                    </a>
                                </div>
                                <div class="col-md-3 col-6 mb-3">
                                    <a href="add_schedule.php?class_id=<?php echo $class_id; ?>" class="btn btn-outline-success btn-block py-3">
                                        <i class="fas fa-clock fa-2x mb-2"></i><br>Add Schedule
                                    </a>
                                </div>
                                <div class="col-md-3 col-6 mb-3">
                                    <a href="enroll_member.php?class_id=<?php echo $class_id; ?>" class="btn btn-outline-warning btn-block py-3">
                                        <i class="fas fa-user-plus fa-2x mb-2"></i><br>Enroll Member
                                    </a>
                                </div>
                                <div class="col-md-3 col-6 mb-3">
                                    <a href="manage_classes.php" class="btn btn-outline-info btn-block py-3">
                                        <i class="fas fa-arrow-left fa-2x mb-2"></i><br>Back to Classes
                                    </a>
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
            // Initialize DataTable if needed
            if ($('.dataTable').length) {
                $('.dataTable').DataTable({
                    pageLength: 10,
                    order: [[3, 'desc']]
                });
            }
        });
        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }
    </script>
</body>
</html>