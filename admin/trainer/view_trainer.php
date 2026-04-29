<?php
/**
 * admin/trainers/view_trainer.php - View trainer details (Auto-adapting version)
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

// Get trainer ID from URL (this is the user id)
$trainer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($trainer_id <= 0) {
    header('Location: ../manage_trainers.php');
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

// Helper: detect primary key column for a table
function getPrimaryKey($pdo, $table) {
    $stmt = $pdo->query("SHOW KEYS FROM $table WHERE Key_name = 'PRIMARY'");
    $primary = $stmt->fetch(PDO::FETCH_ASSOC);
    return $primary ? $primary['Column_name'] : 'id'; // fallback to 'id'
}

try {
    // Fetch trainer record (joins users and trainers)
    $stmt = $pdo->prepare("
        SELECT u.*, t.*
        FROM users u
        JOIN trainers t ON u.id = t.user_id
        WHERE u.id = ? AND u.user_type = 'trainer'
    ");
    $stmt->execute([$trainer_id]);
    $trainer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trainer) {
        $error = "Trainer not found.";
    } else {
        // Determine foreign key for classes table (e.g., 'trainer_id' or 'user_id')
        $classes_fk = getForeignKey($pdo, 'classes');
        // Determine primary key for classes table (e.g., 'id')
        $classes_pk = getPrimaryKey($pdo, 'classes');
        
        // Fetch classes assigned to this trainer, ordered by the primary key
        $classes_stmt = $pdo->prepare("SELECT * FROM classes WHERE $classes_fk = ? ORDER BY $classes_pk DESC");
        $classes_stmt->execute([$trainer['id']]); // id from users table
        $assigned_classes = $classes_stmt->fetchAll();
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $trainer = null;
}

$page_title = 'View Trainer - ' . APP_NAME;
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
        /* All styles are identical to view_user.php (kept as is) */
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border:none;border-radius:8px;padding:8px 20px}.btn-primary:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(102,126,234,0.4)}.btn-outline-primary{border-color:#667eea;color:#667eea}.btn-outline-primary:hover{background:#667eea;color:#fff}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}.avatar-lg{width:120px;height:120px;border-radius:50%;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;display:flex;align-items:center;justify-content:center;font-size:3rem;font-weight:bold;margin:0 auto 20px}.info-row{display:flex;padding:10px 0;border-bottom:1px solid #e9ecef}.info-label{width:140px;font-weight:600;color:#555}.info-value{flex:1;color:#333}.section-title{font-size:1.2rem;font-weight:600;color:#333;margin-bottom:15px;padding-bottom:10px;border-bottom:2px solid #667eea;display:inline-block}.action-buttons{margin-top:20px}.action-buttons .btn{margin:0 5px}.nav-tabs .nav-link{color:#555;font-weight:500}.nav-tabs .nav-link.active{color:#667eea;border-bottom-color:#667eea}.tab-pane{padding:20px 0}
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
                <li class="active"><a href="../manage_trainers.php"><i class="fas fa-chalkboard-teacher"></i> Trainers</a></li>
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
                            <h1><i class="fas fa-chalkboard-teacher"></i> Trainer Details <small>Viewing trainer #<?php echo $trainer_id; ?></small></h1>
                        </div>
                    </div>
                </div>

                <?php if ($trainer): ?>
                <div class="row">
                    <div class="col-lg-4">
                        <!-- Profile Card -->
                        <div class="card">
                            <div class="card-body text-center">
                                <div class="avatar-lg">
                                    <?php echo strtoupper(substr($trainer['full_name'], 0, 1)); ?>
                                </div>
                                <h3 class="mt-3"><?php echo htmlspecialchars($trainer['full_name']); ?></h3>
                                <p class="text-muted">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($trainer['email']); ?><br>
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($trainer['phone'] ?? 'N/A'); ?>
                                </p>
                                <span class="badge badge-<?php echo ($trainer['status'] ?? '') == 'active' ? 'success' : 'danger'; ?> px-3 py-2">
                                    <?php echo ucfirst($trainer['status'] ?? 'Inactive'); ?>
                                </span>
                                <span class="badge badge-info px-3 py-2 ml-2">
                                    Trainer
                                </span>
                                <div class="action-buttons">
                                    <a href="../edit_user.php?id=<?php echo $trainer['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="../manage_trainers.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
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
                                    <div class="info-value"><?php echo htmlspecialchars($trainer['username'] ?? ''); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Email:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($trainer['email'] ?? ''); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Phone:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($trainer['phone'] ?? 'Not provided'); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Address:</div>
                                    <div class="info-value"><?php echo nl2br(htmlspecialchars($trainer['address'] ?? 'Not provided')); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Joined:</div>
                                    <div class="info-value"><?php echo isset($trainer['created_at']) ? date('F d, Y', strtotime($trainer['created_at'])) : 'N/A'; ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Last Login:</div>
                                    <div class="info-value"><?php echo (isset($trainer['last_login']) && $trainer['last_login']) ? date('F d, Y H:i', strtotime($trainer['last_login'])) : 'Never'; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <!-- Tabs for trainer-specific details -->
                        <div class="card">
                            <div class="card-header">
                                <ul class="nav nav-tabs card-header-tabs" id="trainerTabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="profile-tab" data-toggle="tab" href="#profile" role="tab">Professional Info</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="classes-tab" data-toggle="tab" href="#classes" role="tab">Assigned Classes</a>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content">
                                    <!-- Professional Info Tab -->
                                    <div class="tab-pane fade show active" id="profile" role="tabpanel">
                                        <h5 class="section-title">Professional Information</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="info-row">
                                                    <div class="info-label">Specialization:</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($trainer['specialization'] ?? 'Not specified'); ?></div>
                                                </div>
                                                <div class="info-row">
                                                    <div class="info-label">Experience:</div>
                                                    <div class="info-value"><?php echo isset($trainer['experience_years']) ? $trainer['experience_years'] . ' years' : 'N/A'; ?></div>
                                                </div>
                                                <div class="info-row">
                                                    <div class="info-label">Qualification:</div>
                                                    <div class="info-value"><?php echo nl2br(htmlspecialchars($trainer['qualification'] ?? 'N/A')); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-row">
                                                    <div class="info-label">Joined (as trainer):</div>
                                                    <div class="info-value"><?php echo isset($trainer['joining_date']) ? date('F d, Y', strtotime($trainer['joining_date'])) : 'N/A'; ?></div>
                                                </div>
                                                <div class="info-row">
                                                    <div class="info-label">Salary:</div>
                                                    <div class="info-value"><?php echo isset($trainer['salary']) ? '$' . number_format($trainer['salary'], 2) : 'N/A'; ?></div>
                                                </div>
                                                <div class="info-row">
                                                    <div class="info-label">Certification:</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($trainer['certification'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if (!empty($trainer['bio'])): ?>
                                        <div class="info-row mt-3">
                                            <div class="info-label">Bio:</div>
                                            <div class="info-value"><?php echo nl2br(htmlspecialchars($trainer['bio'])); ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Assigned Classes Tab -->
                                    <div class="tab-pane fade" id="classes" role="tabpanel">
                                        <h5 class="section-title">Assigned Classes</h5>
                                        <?php if (empty($assigned_classes)): ?>
                                            <p class="text-muted text-center py-4">No classes assigned to this trainer.</p>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        32<th>Class Name</th><th>Capacity</th><th>Difficulty</th><th>Status</th><th>Actions</th> </thead>
                                                    <tbody>
                                                        <?php foreach ($assigned_classes as $class): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                                                <td><?php echo $class['capacity']; ?></td>
                                                                <td><span class="badge badge-<?php echo ($class['difficulty'] ?? '') == 'beginner' ? 'success' : (($class['difficulty'] ?? '') == 'intermediate' ? 'warning' : 'danger'); ?>"><?php echo ucfirst($class['difficulty'] ?? 'N/A'); ?></span></td>
                                                                <td><span class="badge badge-<?php echo ($class['status'] ?? '') == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($class['status'] ?? 'Inactive'); ?></span></td>
                                                                <td><a href="../classes/view_class.php?id=<?php echo $class[$classes_pk]; ?>" class="btn btn-sm btn-primary">View</a></td>
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