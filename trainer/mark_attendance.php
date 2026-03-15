<?php
// trainer/mark_attendance.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Path to root includes
$root_path = dirname(__DIR__);
require_once $root_path . '/includes/config.php';
require_once $root_path . '/includes/session.php';
require_once $root_path . '/includes/functions.php';

// Check if user is trainer or admin
if (!Session::isTrainer() && !Session::isAdmin()) {
    header('Location: ' . $root_path . '/login.php');
    exit();
}

$trainer_id = Session::userId();
$user_name = Session::userName();
$functions = new Functions();
$error = '';
$success = '';

// Get selected date (default today)
$selected_date = $_GET['date'] ?? date('Y-m-d');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $attendance_data = $_POST['attendance'] ?? []; // array of member_id => status
    $date = $_POST['attendance_date'] ?? $selected_date;

    try {
        $pdo->beginTransaction();

        foreach ($attendance_data as $member_id => $status) {
            // Check if member is assigned to this trainer (security)
            $stmt = $pdo->prepare("SELECT user_id FROM members WHERE user_id = ? AND assigned_trainer_id = ?");
            $stmt->execute([$member_id, $trainer_id]);
            if ($stmt->rowCount() == 0 && !Session::isAdmin()) {
                // If not assigned and not admin, skip
                continue;
            }

            // Insert or update attendance
            $stmt = $pdo->prepare("
                INSERT INTO attendance (user_id, date, status, check_in) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = ?, check_in = ?
            ");
            // For simplicity, we set check_in to current time if present, else null
            $check_in = ($status == 'present' || $status == 'late') ? date('H:i:s') : null;
            $stmt->execute([$member_id, $date, $status, $check_in, $status, $check_in]);
        }

        $pdo->commit();
        $success = "Attendance marked successfully for " . count($attendance_data) . " member(s).";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error saving attendance: " . $e->getMessage();
    }
}

// Fetch trainer's assigned members
$members = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.email, 
               a.status as attendance_status
        FROM users u
        JOIN members m ON u.id = m.user_id
        LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
        WHERE m.assigned_trainer_id = ? AND u.status = 'active'
        ORDER BY u.full_name
    ");
    $stmt->execute([$selected_date, $trainer_id]);
    $members = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading members: " . $e->getMessage();
}

$page_title = 'Mark Attendance - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 4, Font Awesome -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Dashboard styles from reference */
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}}
        /* Table and form styling */
        .table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.form-control{border-radius:8px;border:1px solid #e1e5eb;padding:10px 15px;height:auto}.form-control:focus{border-color:#667eea;box-shadow:0 0 0 0.2rem rgba(102,126,234,0.25)}.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border:none;padding:10px 30px;border-radius:8px;font-weight:600}.btn-primary:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(102,126,234,0.4)}.btn-outline-primary{border-color:#667eea;color:#667eea}.btn-outline-primary:hover{background:#667eea;color:#fff}
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
                <p>Trainer Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="my_members.php"><i class="fas fa-users"></i> My Members</a></li>
                <li><a href="my_classes.php"><i class="fas fa-calendar-alt"></i> My Classes</a></li>
                <li class="active"><a href="mark_attendance.php"><i class="fas fa-clock"></i> Mark Attendance</a></li>
                <li><a href="workout_plans.php"><i class="fas fa-dumbbell"></i> Workout Plans</a></li>
                <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
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
                            <a class="dropdown-item" href="#"><strong>New member assigned</strong><br><small class="text-muted">2 minutes ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Class schedule updated</strong><br><small class="text-muted">1 hour ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Workout plan completed</strong><br><small class="text-muted">3 hours ago</small></a>
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
                            <a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid">
                <!-- Flash messages from session -->
                <?php Session::displayFlash(); ?>

                <!-- Error / Success alerts -->
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- Page Header with Date Picker -->
                <div class="page-header d-flex justify-content-between align-items-center">
                    <h1>
                        <i class="fas fa-clock"></i> Mark Attendance
                        <small>Record member attendance</small>
                    </h1>
                    <form method="get" class="form-inline">
                        <div class="input-group">
                            <input type="date" name="date" class="form-control" value="<?php echo $selected_date; ?>">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="fas fa-calendar-alt"></i> Load
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Attendance Card -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-check mr-2"></i>
                            Attendance for <?php echo date('F j, Y', strtotime($selected_date)); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($members)): ?>
                            <p class="text-muted">No active members assigned to you.</p>
                        <?php else: ?>
                            <form method="post" id="attendanceForm">
                                <input type="hidden" name="attendance_date" value="<?php echo $selected_date; ?>">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Member</th>
                                                <th>Email</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($members as $member): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                                <td>
                                                    <select name="attendance[<?php echo $member['id']; ?>]" class="form-control">
                                                        <option value="">-- Not marked --</option>
                                                        <option value="present" <?php echo ($member['attendance_status'] ?? '') == 'present' ? 'selected' : ''; ?>>Present</option>
                                                        <option value="late" <?php echo ($member['attendance_status'] ?? '') == 'late' ? 'selected' : ''; ?>>Late</option>
                                                        <option value="absent" <?php echo ($member['attendance_status'] ?? '') == 'absent' ? 'selected' : ''; ?>>Absent</option>
                                                    </select>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="submit" name="mark_attendance" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save mr-2"></i>Save Attendance
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
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
                    <a href="../logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
            // Sidebar toggle
            $('#sidebarCollapse').on('click', function() {
                $('#sidebar').toggleClass('active');
            });

            // Auto-hide alerts after 5 seconds
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