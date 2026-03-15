<?php
// admin/attendance.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

Session::requireAdmin();

$functions = new Functions();
$error = '';
$success = '';

// Handle date range filter
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to   = $_GET['date_to'] ?? date('Y-m-d');
$member_search = $_GET['member'] ?? '';

// Get attendance records
$attendance = [];
$total_today = 0;
$total_week = 0;
$total_month = 0;

try {
    // Stats
    $stmt = $pdo->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE()");
    $total_today = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM attendance WHERE YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1)");
    $total_week = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM attendance WHERE MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())");
    $total_month = $stmt->fetchColumn();

    // Build query for attendance list
    $sql = "SELECT a.*, u.full_name, u.email, u.username
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            WHERE 1=1";
    $params = [];

    if (!empty($date_from)) {
        $sql .= " AND a.date >= ?";
        $params[] = $date_from;
    }
    if (!empty($date_to)) {
        $sql .= " AND a.date <= ?";
        $params[] = $date_to;
    }
    if (!empty($member_search)) {
        $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
        $search = "%$member_search%";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    $sql .= " ORDER BY a.date DESC, a.check_in DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $attendance = $stmt->fetchAll();

} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

$page_title = 'Attendance Management - ' . APP_NAME;
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
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.stats-card small{opacity:.9}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}.stats-card h2{font-size:1.8rem}}
    </style>
</head>
<body>
    <div class="loading-spinner" id="loadingSpinner"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>
    <div class="wrapper">
        <!-- Sidebar (same as dashboard) -->
        <nav id="sidebar">
            <div class="sidebar-header"><i class="fas fa-dumbbell fa-3x mb-3"></i><h3><?php echo APP_NAME; ?></h3><p>Administrator Panel</p></div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li>
                    <a href="#membersSubmenu" data-toggle="collapse"><i class="fas fa-users"></i> Members <i class="fas fa-chevron-down float-right"></i></a>
                    <ul class="collapse list-unstyled" id="membersSubmenu">
                        <li><a href="manage_members.php"><i class="fas fa-list"></i> All Members</a></li>
                        <li><a href="add_member.php"><i class="fas fa-user-plus"></i> Add Member</a></li>
                        <li><a href="membership_plans.php"><i class="fas fa-tag"></i> Membership Plans</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#trainersSubmenu" data-toggle="collapse"><i class="fas fa-user-tie"></i> Trainers <i class="fas fa-chevron-down float-right"></i></a>
                    <ul class="collapse list-unstyled" id="trainersSubmenu">
                        <li><a href="manage_trainers.php"><i class="fas fa-list"></i> All Trainers</a></li>
                        <li><a href="add_trainer.php"><i class="fas fa-user-plus"></i> Add Trainer</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#classesSubmenu" data-toggle="collapse"><i class="fas fa-calendar-alt"></i> Classes <i class="fas fa-chevron-down float-right"></i></a>
                    <ul class="collapse list-unstyled" id="classesSubmenu">
                        <li><a href="manage_classes.php"><i class="fas fa-list"></i> All Classes</a></li>
                        <li><a href="add_class.php"><i class="fas fa-plus-circle"></i> Add Class</a></li>
                        <li><a href="class_schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
                    </ul>
                </li>
                <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li class="active"><a href="attendance.php"><i class="fas fa-clock"></i> Attendance</a></li>
                <li><a href="equipment.php"><i class="fas fa-dumbbell"></i> Equipment</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
            <div class="sidebar-footer"><a href="#" onclick="confirmLogout(event)" class="btn btn-danger btn-block"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-custom">
                <button type="button" id="sidebarCollapse" class="btn btn-outline-primary"><i class="fas fa-bars"></i> Menu</button>
                <div class="ml-auto">
                    <div class="dropdown">
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
                    <div class="dropdown ml-3">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown"><i class="fas fa-user-circle fa-lg"></i><span class="ml-2"><?php echo htmlspecialchars(Session::userName()); ?></span></button>
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
                <?php Session::displayFlash(); ?>

                <!-- Page Header -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header">
                            <h1><i class="fas fa-clock"></i> Attendance Management</h1>
                        </div>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-xl-4 col-md-6">
                        <div class="card stats-card bg-primary text-white">
                            <div class="card-body">
                                <div class="card-title">Today's Attendance</div>
                                <h2><?php echo number_format($total_today); ?></h2>
                                <i class="fas fa-calendar-check"></i>
                                <small>Checked in today</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <div class="card-title">This Week</div>
                                <h2><?php echo number_format($total_week); ?></h2>
                                <i class="fas fa-calendar-week"></i>
                                <small>Last 7 days</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <div class="card-title">This Month</div>
                                <h2><?php echo number_format($total_month); ?></h2>
                                <i class="fas fa-calendar-alt"></i>
                                <small>Current month</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Form -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-filter"></i> Filter Attendance</h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="form-row">
                            <div class="form-group col-md-4">
                                <label for="date_from">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="date_to">To Date</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="member">Member Name / Email</label>
                                <input type="text" class="form-control" id="member" name="member" placeholder="Search member..." value="<?php echo htmlspecialchars($member_search); ?>">
                            </div>
                            <div class="form-group col-md-12">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply Filters</button>
                                <a href="attendance.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
                                <a href="mark_attendance.php" class="btn btn-success float-right"><i class="fas fa-plus-circle"></i> Mark Attendance</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Attendance Table -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Attendance Records</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="attendanceTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Member</th>
                                        <th>Email</th>
                                        <th>Date</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance as $a): ?>
                                    <?php
                                        $check_in = $a['check_in'] ? date('h:i A', strtotime($a['check_in'])) : '-';
                                        $check_out = $a['check_out'] ? date('h:i A', strtotime($a['check_out'])) : '-';
                                        $duration = '';
                                        if ($a['check_in'] && $a['check_out']) {
                                            $in = new DateTime($a['check_in']);
                                            $out = new DateTime($a['check_out']);
                                            $diff = $in->diff($out);
                                            $duration = $diff->h . ' hr ' . $diff->i . ' min';
                                        }
                                        $status_class = '';
                                        $status_text = ucfirst($a['status']);
                                        switch ($a['status']) {
                                            case 'present': $status_class = 'badge-success'; break;
                                            case 'late': $status_class = 'badge-warning'; break;
                                            case 'absent': $status_class = 'badge-danger'; break;
                                            default: $status_class = 'badge-secondary';
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo $a['id']; ?></td>
                                        <td><?php echo htmlspecialchars($a['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($a['email']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($a['date'])); ?></td>
                                        <td><?php echo $check_in; ?></td>
                                        <td><?php echo $check_out; ?></td>
                                        <td><?php echo $duration ?: '-'; ?></td>
                                        <td><span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($attendance)): ?>
                                    <tr><td colspan="8" class="text-center py-4">No attendance records found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions (optional) -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header"><h5><i class="fas fa-bolt"></i> Quick Actions</h5></div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="mark_attendance.php" class="btn btn-outline-primary btn-block py-3">
                                            <i class="fas fa-clock fa-2x mb-2"></i><br>Mark Attendance
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="export_attendance.php" class="btn btn-outline-success btn-block py-3">
                                            <i class="fas fa-file-excel fa-2x mb-2"></i><br>Export to CSV
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="reports.php?type=attendance" class="btn btn-outline-warning btn-block py-3">
                                            <i class="fas fa-chart-bar fa-2x mb-2"></i><br>Attendance Reports
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6 mb-3">
                                        <a href="members/manage_members.php" class="btn btn-outline-info btn-block py-3">
                                            <i class="fas fa-users fa-2x mb-2"></i><br>Manage Members
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
            $('#sidebarCollapse').on('click', function() {
                $('#sidebar').toggleClass('active');
            });

            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);

            if ($('#attendanceTable').length) {
                $('#attendanceTable').DataTable({
                    pageLength: 25,
                    order: [[3, 'desc']],
                    language: {
                        search: "<i class='fas fa-search'></i>",
                        searchPlaceholder: "Search records...",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ records",
                        infoEmpty: "Showing 0 to 0 of 0 records",
                        infoFiltered: "(filtered from _MAX_ total records)"
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