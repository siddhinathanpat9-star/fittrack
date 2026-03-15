<?php
// member/attendance.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

$root_path = dirname(__DIR__);
require_once $root_path . '/includes/config.php';
require_once $root_path . '/includes/session.php';
require_once $root_path . '/includes/functions.php';

// Check if user is member
if (!Session::isMember()) {
    Session::setFlash('danger', 'Access denied. Member login required.');
    header('Location: ' . $root_path . '/login.php');
    exit();
}

$member_id = Session::userId();
$user_name = Session::userName(); // for display in topbar
$functions = new Functions();
$error = '';
$success = '';

// Handle check-in
if (isset($_POST['check_in'])) {
    $today = date('Y-m-d');
    $now = date('H:i:s');

    // Check if already checked in today
    $stmt = $pdo->prepare("SELECT id, check_out FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->execute([$member_id, $today]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['check_out']) {
            $error = "You have already checked out today.";
        } else {
            $error = "You are already checked in. Please check out first.";
        }
    } else {
        // Determine status: if after 9 AM, mark as late (customize as needed)
        $status = ($now > '09:00:00') ? 'late' : 'present';
        try {
            $stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, check_in, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$member_id, $today, $now, $status]);
            $success = "Checked in successfully at " . date('h:i A');
        } catch (Exception $e) {
            $error = "Error checking in: " . $e->getMessage();
        }
    }
}

// Handle check-out
if (isset($_POST['check_out'])) {
    $today = date('Y-m-d');
    $now = date('H:i:s');

    $stmt = $pdo->prepare("SELECT id, check_out FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->execute([$member_id, $today]);
    $existing = $stmt->fetch();

    if (!$existing) {
        $error = "You haven't checked in today.";
    } elseif ($existing['check_out']) {
        $error = "You have already checked out today.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE attendance SET check_out = ? WHERE id = ?");
            $stmt->execute([$now, $existing['id']]);
            $success = "Checked out successfully at " . date('h:i A');
        } catch (Exception $e) {
            $error = "Error checking out: " . $e->getMessage();
        }
    }
}

// Get attendance history
$attendance = [];
try {
    $stmt = $pdo->prepare("
        SELECT date, check_in, check_out, status
        FROM attendance
        WHERE user_id = ?
        ORDER BY date DESC
        LIMIT 30
    ");
    $stmt->execute([$member_id]);
    $attendance = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading attendance: " . $e->getMessage();
}

// Get statistics
$stats = [];
try {
    // Total visits
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ?");
    $stmt->execute([$member_id]);
    $stats['total'] = $stmt->fetchColumn();

    // This month
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())");
    $stmt->execute([$member_id]);
    $stats['month'] = $stmt->fetchColumn();

    // Average check-in time (for present days)
    $stmt = $pdo->prepare("
        SELECT AVG(TIME_TO_SEC(check_in)) FROM attendance
        WHERE user_id = ? AND status IN ('present', 'late') AND check_in IS NOT NULL
    ");
    $stmt->execute([$member_id]);
    $avg_seconds = $stmt->fetchColumn();
    if ($avg_seconds) {
        $stats['avg_checkin'] = date('h:i A', mktime(0, 0, $avg_seconds));
    } else {
        $stats['avg_checkin'] = 'N/A';
    }

    // Most common status
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as cnt FROM attendance
        WHERE user_id = ?
        GROUP BY status
        ORDER BY cnt DESC
        LIMIT 1
    ");
    $stmt->execute([$member_id]);
    $row = $stmt->fetch();
    $stats['most_common'] = $row ? ucfirst($row['status']) : 'N/A';

} catch (Exception $e) {
    // ignore
}

// Determine today's check-in status for buttons
$today = date('Y-m-d');
$checked_in_today = false;
$checked_out_today = false;
foreach ($attendance as $a) {
    if ($a['date'] == $today) {
        $checked_in_today = true;
        if ($a['check_out']) $checked_out_today = true;
        break;
    }
}

$page_title = 'My Attendance - ' . APP_NAME;
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
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:15px 20px;transition:.3s}.list-group-item:hover{background:#f8f9fa;transform:translateX(5px)}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}.stats-card h2{font-size:1.8rem}}
        /* Quick action buttons (check in/out) */
        .quick-action-btn{transition:.3s;border-radius:10px}.quick-action-btn:hover{transform:translateY(-3px);box-shadow:0 5px 15px rgba(0,0,0,0.1)}
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
                <p>Member Area</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li class="active"><a href="attendance.php"><i class="fas fa-clock"></i> My Attendance</a></li>
                <li><a href="workouts.php"><i class="fas fa-dumbbell"></i> Workout Plans</a></li>
                <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li><a href="classes.php"><i class="fas fa-calendar-alt"></i> Book Classes</a></li>
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
                <!-- Flash messages -->
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="page-header">
                    <h1><i class="fas fa-clock"></i> My Attendance <small>Track your check‑ins</small></h1>
                </div>

                <!-- Check In/Out Quick Actions -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <?php if (!$checked_in_today): ?>
                            <form method="post">
                                <button type="submit" name="check_in" class="btn btn-success btn-lg btn-block quick-action-btn py-3">
                                    <i class="fas fa-sign-in-alt fa-2x mb-2"></i><br>Check In
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-lg btn-block py-3" disabled>
                                <i class="fas fa-check-circle fa-2x mb-2"></i><br>Checked In
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php if ($checked_in_today && !$checked_out_today): ?>
                            <form method="post">
                                <button type="submit" name="check_out" class="btn btn-warning btn-lg btn-block quick-action-btn py-3">
                                    <i class="fas fa-sign-out-alt fa-2x mb-2"></i><br>Check Out
                                </button>
                            </form>
                        <?php elseif ($checked_in_today && $checked_out_today): ?>
                            <button class="btn btn-secondary btn-lg btn-block py-3" disabled>
                                <i class="fas fa-check-circle fa-2x mb-2"></i><br>Checked Out
                            </button>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-lg btn-block py-3" disabled>
                                <i class="fas fa-sign-out-alt fa-2x mb-2"></i><br>Check Out
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistics Cards (like admin dashboard) -->
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-primary text-white">
                            <div class="card-body">
                                <div class="card-title">Total Visits</div>
                                <h2><?php echo $stats['total'] ?? 0; ?></h2>
                                <i class="fas fa-calendar-check"></i>
                                <small>All time</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <div class="card-title">This Month</div>
                                <h2><?php echo $stats['month'] ?? 0; ?></h2>
                                <i class="fas fa-calendar-alt"></i>
                                <small><?php echo date('F Y'); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <div class="card-title">Avg Check‑in</div>
                                <h2><?php echo $stats['avg_checkin']; ?></h2>
                                <i class="fas fa-clock"></i>
                                <small>Typical time</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-warning text-white">
                            <div class="card-body">
                                <div class="card-title">Most Often</div>
                                <h2><?php echo $stats['most_common']; ?></h2>
                                <i class="fas fa-chart-pie"></i>
                                <small>Status</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance History Table (with DataTables) -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-history"></i> Recent Attendance</h5>
                        <?php if (!empty($attendance)): ?>
                            <span class="badge badge-info">Last 30 records</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($attendance)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No attendance records yet.</p>
                                <p>Use the buttons above to check in.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover dataTable" id="attendanceTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Check In</th>
                                            <th>Check Out</th>
                                            <th>Duration</th>
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
                                                <?php
                                                if ($a['check_in'] && $a['check_out']) {
                                                    $in = new DateTime($a['check_in']);
                                                    $out = new DateTime($a['check_out']);
                                                    $diff = $in->diff($out);
                                                    echo $diff->format('%h hr %i min');
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $badge = match($a['status']) {
                                                    'present' => 'success',
                                                    'late'    => 'warning',
                                                    'absent'  => 'danger',
                                                    default   => 'secondary'
                                                };
                                                ?>
                                                <span class="badge badge-<?php echo $badge; ?>"><?php echo ucfirst($a['status']); ?></span>
                                            </td>
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
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function() {
            // Sidebar toggle
            $('#sidebarCollapse').on('click', function() {
                $('#sidebar').toggleClass('active');
            });

            // Auto-hide alerts
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);

            // Initialize DataTable if table exists
            if ($('#attendanceTable').length) {
                $('#attendanceTable').DataTable({
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                    order: [[0, 'desc']], // sort by date descending
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search records..."
                    }
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