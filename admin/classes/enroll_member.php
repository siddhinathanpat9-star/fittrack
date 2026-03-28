<?php
// admin/classes/enroll_member.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

Session::requireAdmin();

$functions = new Functions();
$error = '';
$success = '';

// Get class_id from URL
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

if ($class_id <= 0) {
    $error = "Invalid class ID.";
}

// Fetch class details
$class = null;
if (!$error) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
        $stmt->execute([$class_id]);
        $class = $stmt->fetch();
        if (!$class) {
            $error = "Class not found.";
        }
    } catch (Exception $e) {
        $error = "Error loading class: " . $e->getMessage();
    }
}

// Get current enrolled count and capacity check
$enrolled_count = 0;
$capacity_exceeded = false;
if ($class && !$error) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_enrollments WHERE class_id = ? AND status = 'enrolled'");
        $stmt->execute([$class_id]);
        $enrolled_count = $stmt->fetchColumn();
        
        if (isset($class['capacity']) && $class['capacity'] > 0 && $enrolled_count >= $class['capacity']) {
            $capacity_exceeded = true;
        }
    } catch (Exception $e) {
        // Suppress error if class_enrollments table doesn't exist
        if (strpos($e->getMessage(), "class_enrollments") === false) {
            $error = "Error checking capacity: " . $e->getMessage();
        }
    }
}

// Fetch list of active members (not already enrolled in this class)
$members = [];
if ($class && !$error && !$capacity_exceeded) {
    try {
        // Members who are active and not enrolled in this class
        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name, u.email
            FROM users u
            WHERE u.user_type = 'member' 
              AND u.status = 'active'
              AND u.id NOT IN (
                  SELECT user_id FROM class_enrollments 
                  WHERE class_id = ? AND status = 'enrolled'
              )
            ORDER BY u.full_name ASC
        ");
        $stmt->execute([$class_id]);
        $members = $stmt->fetchAll();
    } catch (Exception $e) {
        // If class_enrollments table doesn't exist, just load all active members
        if (strpos($e->getMessage(), "class_enrollments") !== false) {
            try {
                $stmt = $pdo->prepare("
                    SELECT u.id, u.full_name, u.email
                    FROM users u
                    WHERE u.user_type = 'member' 
                      AND u.status = 'active'
                    ORDER BY u.full_name ASC
                ");
                $stmt->execute();
                $members = $stmt->fetchAll();
            } catch (Exception $e2) {
                $error = "Error loading members: " . $e2->getMessage();
            }
        } else {
            $error = "Error loading members: " . $e->getMessage();
        }
    }
}

// Process enrollment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll']) && !$error) {
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        $error = "Please select a member.";
    } else {
        try {
            // Check again for capacity (to avoid race conditions)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_enrollments WHERE class_id = ? AND status = 'enrolled'");
            $stmt->execute([$class_id]);
            $current_count = $stmt->fetchColumn();
            
            if ($class['capacity'] > 0 && $current_count >= $class['capacity']) {
                $error = "This class is now full. Enrollment not possible.";
            } else {
                // Check if member is already enrolled
                $stmt = $pdo->prepare("SELECT id FROM class_enrollments WHERE class_id = ? AND user_id = ? AND status = 'enrolled'");
                $stmt->execute([$class_id, $user_id]);
                if ($stmt->fetch()) {
                    $error = "Member is already enrolled in this class.";
                } else {
                    // Insert enrollment
                    $stmt = $pdo->prepare("
                        INSERT INTO class_enrollments (class_id, user_id, enrollment_date, status) 
                        VALUES (?, ?, NOW(), 'enrolled')
                    ");
                    $stmt->execute([$class_id, $user_id]);
                    $success = "Member successfully enrolled in the class.";
                    
                    // Refresh enrollment count and member list after successful enrollment
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_enrollments WHERE class_id = ? AND status = 'enrolled'");
                    $stmt->execute([$class_id]);
                    $enrolled_count = $stmt->fetchColumn();
                    
                    // Refresh member list
                    $stmt = $pdo->prepare("
                        SELECT u.id, u.full_name, u.email
                        FROM users u
                        WHERE u.user_type = 'member' 
                          AND u.status = 'active'
                          AND u.id NOT IN (
                              SELECT user_id FROM class_enrollments 
                              WHERE class_id = ? AND status = 'enrolled'
                          )
                        ORDER BY u.full_name ASC
                    ");
                    $stmt->execute([$class_id]);
                    $members = $stmt->fetchAll();
                }
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$user_name = Session::userName();
$page_title = 'Enroll Member - ' . APP_NAME;
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
        /* Reuse the same styles as dashboard */
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.btn-primary{background:#667eea;border-color:#667eea}.btn-primary:hover{background:#5a67d8;border-color:#5a67d8}.btn-outline-primary{color:#667eea;border-color:#667eea}.btn-outline-primary:hover{background:#667eea;border-color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:15px 20px}.list-group-item:last-child{border-bottom:none}.list-group-item i{color:#667eea;margin-right:10px}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}}
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
                    <!-- Notifications dropdown (static example) -->
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
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php Session::displayFlash(); ?>

                <!-- Page Header -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header">
                            <h1><i class="fas fa-user-plus"></i> Enroll Member <small><?php echo htmlspecialchars($class['class_name'] ?? 'Class'); ?></small></h1>
                        </div>
                    </div>
                </div>

                <!-- Class Info Card -->
                <?php if ($class): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> Class Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Class Name:</strong> <?php echo htmlspecialchars($class['class_name']); ?><br>
                                <strong>Schedule:</strong> <?php echo htmlspecialchars($class['schedule'] ?? 'Not specified'); ?><br>
                                <strong>Trainer:</strong> <?php echo htmlspecialchars($class['trainer'] ?? 'Not assigned'); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Capacity:</strong> <?php echo ($class['capacity'] ?? 0) > 0 ? $class['capacity'] : 'Unlimited'; ?><br>
                                <strong>Enrolled:</strong> <?php echo $enrolled_count; ?>
                                <?php if (($class['capacity'] ?? 0) > 0 && $enrolled_count >= $class['capacity']): ?>
                                    <span class="badge badge-danger ml-2">Full</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($class['description'])): ?>
                            <div class="mt-3">
                                <strong>Description:</strong><br>
                                <?php echo nl2br(htmlspecialchars($class['description'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Enrollment Form Card -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-user-check"></i> Enroll a Member</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$class): ?>
                            <div class="alert alert-warning">Class information could not be loaded.</div>
                            <a href="manage_classes.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Classes</a>
                        <?php elseif ($capacity_exceeded): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> This class has reached its maximum capacity (<?php echo $class['capacity']; ?> enrolled). No more enrollments can be added.
                            </div>
                            <a href="manage_classes.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Classes</a>
                        <?php elseif (empty($members) && !$error): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No active members available to enroll. All active members are already enrolled in this class.
                            </div>
                            <a href="manage_classes.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Classes</a>
                        <?php else: ?>
                            <form method="POST" action="">
                                <div class="form-group">
                                    <label for="user_id">Select Member <span class="text-danger">*</span></label>
                                    <select name="user_id" id="user_id" class="form-control" required>
                                        <option value="">-- Choose a member --</option>
                                        <?php foreach ($members as $member): ?>
                                            <option value="<?php echo $member['id']; ?>">
                                                <?php echo htmlspecialchars($member['full_name'] . ' (' . $member['email'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <button type="submit" name="enroll" class="btn btn-success"><i class="fas fa-check-circle"></i> Enroll Member</button>
                                    <a href="manage_classes.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Modal (same as dashboard) -->
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