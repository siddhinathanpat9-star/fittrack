<?php
// admin/classes/export_classes.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Path to includes (two levels up from admin/classes)
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is admin
if (!Session::isAdmin()) {
    Session::setFlash('danger', 'Access denied.');
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// If download parameter is present, output CSV and exit
if (isset($_GET['download'])) {
    // Set CSV headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="classes_' . date('Y-m-d') . '.csv"');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add CSV headers
    fputcsv($output, [
        'ID',
        'Class Name',
        'Description',
        'Trainer ID',
        'Trainer Name',
        'Day of Week',
        'Start Time',
        'End Time',
        'Max Capacity',
        'Status',
        'Created At'
    ]);

    // Fetch classes with trainer name
    try {
        $stmt = $pdo->query("
            SELECT c.*, u.full_name as trainer_name
            FROM classes c
            LEFT JOIN users u ON c.trainer_id = u.id
            ORDER BY c.id
        ");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['class_name'],
                $row['description'],
                $row['trainer_id'],
                $row['trainer_name'],
                $row['day_of_week'],
                $row['start_time'],
                $row['end_time'],
                $row['max_capacity'],
                $row['status'],
                $row['created_at']
            ]);
        }
    } catch (Exception $e) {
        // In case of error, we can't output anything after headers
        // Just log and exit silently
        error_log("Export error: " . $e->getMessage());
    }

    fclose($output);
    exit();
}

$user_name = Session::userName();
$page_title = 'Export Classes - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 4, Font Awesome, DataTables (optional) -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <style>
        /* Dashboard styles from reference */
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:250px;max-width:250px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:all 0.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-250px}#sidebar .sidebar-header{padding:20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.5rem;font-weight:600}#sidebar ul.components{padding:15px 0}#sidebar ul li a{padding:12px 20px;font-size:0.95rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:20px;text-align:center}#sidebar ul ul a{padding-left:40px!important;font-size:.85rem!important;background:rgba(0,0,0,0.1)}#sidebar .sidebar-footer{padding:15px;border-top:1px solid rgba(255,255,255,0.1)}#content{width:calc(100% - 250px);padding:20px;min-height:100vh;transition:all 0.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:20px;padding:10px 20px}.page-header{padding-bottom:15px;margin:0 0 25px;border-bottom:3px solid #667eea}.page-header h1{font-size:1.8rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:0.9rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:20px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:20px}.stats-card .card-title{font-size:.8rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:5px}.stats-card h2{font-size:1.8rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:2.5rem;opacity:.3;position:absolute;bottom:10px;right:15px}.stats-card small{opacity:.9}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:25px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:15px 20px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333;font-size:1.2rem}.card-header h5 i{color:#667eea;margin-right:8px}.card-body{padding:20px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.75rem;letter-spacing:.5px;padding:12px 8px}.table tbody td{padding:12px 8px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:5px 8px;border-radius:20px;font-weight:500;font-size:.7rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:12px 20px;transition:.3s}.list-group-item:hover{background:#f8f9fa;transform:translateX(5px)}.list-group-item i{color:#667eea;margin-right:10px}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:12px 20px;margin-bottom:20px}.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border:none}.btn-primary:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(102,126,234,0.4)}@media(max-width:992px){#sidebar{min-width:200px;max-width:200px}#content{width:calc(100% - 200px)}}@media(max-width:768px){#sidebar{margin-left:-200px}#sidebar.active{margin-left:0}#content{width:100%;padding:15px}.page-header h1{font-size:1.5rem}}
    </style>
</head>
<body>
    <div class="loading-spinner" id="loadingSpinner"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-2x mb-2"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p class="small mb-0">Administrator Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
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
                    <a href="#trainersSubmenu" data-toggle="collapse" aria-expanded="false">
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
                        <li><a href="class_schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
                        <li class="active"><a href="export_classes.php"><i class="fas fa-file-export"></i> Export</a></li>
                    </ul>
                </li>
                <li><a href="../payments/manage_payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li><a href="../attendance.php"><i class="fas fa-clock"></i> Attendance</a></li>
                <li><a href="../reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
            <div class="sidebar-footer">
                <a href="#" onclick="confirmLogout(event)" class="btn btn-danger btn-block btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-custom">
                <button type="button" id="sidebarCollapse" class="btn btn-outline-primary btn-sm"><i class="fas fa-bars"></i> Menu</button>
                <div class="ml-auto d-flex align-items-center">
                    <!-- Notifications dropdown (static) -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-light btn-sm dropdown-toggle" data-toggle="dropdown"><i class="fas fa-bell"></i><span class="badge badge-danger badge-pill">3</span></button>
                        <div class="dropdown-menu dropdown-menu-right" style="width:280px">
                            <div class="dropdown-header bg-light">Notifications</div>
                            <a class="dropdown-item" href="#"><strong>New class added</strong><br><small class="text-muted">2 minutes ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Class booking</strong><br><small class="text-muted">1 hour ago</small></a>
                            <a class="dropdown-item" href="#"><strong>5 memberships expiring soon</strong><br><small class="text-muted">3 hours ago</small></a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-center" href="#">View all</a>
                        </div>
                    </div>
                    <!-- User dropdown -->
                    <div class="dropdown ml-2 d-inline-block">
                        <button class="btn btn-light btn-sm dropdown-toggle" data-toggle="dropdown"><i class="fas fa-user-circle fa-lg"></i><span class="ml-1 d-none d-sm-inline"><?php echo htmlspecialchars($user_name); ?></span></button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="../profile.php"><i class="fas fa-user"></i> My Profile</a>
                            <a class="dropdown-item" href="../settings.php"><i class="fas fa-cog"></i> Settings</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid px-0">
                <?php Session::displayFlash(); ?>

                <!-- Page Header -->
                <div class="page-header d-flex justify-content-between align-items-center">
                    <h1><i class="fas fa-file-export"></i> Export Classes <small>Download class list as CSV</small></h1>
                    <a href="manage_classes.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Classes
                    </a>
                </div>

                <!-- Export Card -->
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-download mr-2"></i>Download Class List</h5>
                            </div>
                            <div class="card-body text-center p-5">
                                <i class="fas fa-file-csv fa-5x text-primary mb-4"></i>
                                <h4>Export Classes to CSV</h4>
                                <p class="text-muted mb-4">
                                    Click the button below to download a CSV file containing all class information,
                                    including class name, trainer, schedule, and capacity.
                                </p>
                                <a href="?download=1" class="btn btn-success btn-lg">
                                    <i class="fas fa-file-excel mr-2"></i>Export to CSV
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
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="logoutModalLabel"><i class="fas fa-sign-out-alt"></i> Confirm Logout</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to logout?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times"></i> Cancel</button>
                    <a href="../../logout.php" class="btn btn-danger btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
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