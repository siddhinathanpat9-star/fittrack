<?php
// admin/export_attendance.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is admin
if (!Session::isAdmin()) {
    Session::setFlash('danger', 'Access denied. Admin login required.');
    header('Location: ../login.php');
    exit();
}

$functions = new Functions();
$error = '';

// Handle export request (if export parameter is set)
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // Fetch all attendance data for export
    try {
        $stmt = $pdo->query("
            SELECT a.id, u.full_name as member_name, a.date, a.check_in, a.check_out, a.status
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            ORDER BY a.date DESC
        ");
        $data = $stmt->fetchAll();

        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="attendance_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        // Add CSV headers
        fputcsv($output, ['ID', 'Member Name', 'Date', 'Check In', 'Check Out', 'Status']);

        // Add data rows
        foreach ($data as $row) {
            fputcsv($output, [
                $row['id'],
                $row['member_name'],
                $row['date'],
                $row['check_in'] ? date('h:i A', strtotime($row['check_in'])) : '-',
                $row['check_out'] ? date('h:i A', strtotime($row['check_out'])) : '-',
                ucfirst($row['status'])
            ]);
        }
        fclose($output);
        exit();
    } catch (Exception $e) {
        $error = "Export failed: " . $e->getMessage();
    }
}

// Fetch attendance data for display
$attendance = [];
try {
    $stmt = $pdo->query("
        SELECT a.id, u.full_name as member_name, a.date, a.check_in, a.check_out, a.status
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        ORDER BY a.date DESC
        LIMIT 500
    ");
    $attendance = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading attendance: " . $e->getMessage();
}

$page_title = 'Export Attendance - ' . APP_NAME;
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
    <!-- DataTables Buttons -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.7.0/css/buttons.bootstrap4.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; overflow-x: hidden; }
        .wrapper { display: flex; width: 100%; align-items: stretch; min-height: 100vh; }
        #sidebar {
            min-width: 280px; max-width: 280px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff; transition: .3s; box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: relative; z-index: 1000;
        }
        #sidebar.active { margin-left: -280px; }
        #sidebar .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        #sidebar .sidebar-header h3 { font-size: 1.8rem; font-weight: 600; }
        #sidebar ul.components { padding: 20px 0; }
        #sidebar ul li a {
            padding: 15px 25px; font-size: 1rem; display: block; color: #fff;
            text-decoration: none; transition: .3s; border-left: 3px solid transparent;
        }
        #sidebar ul li a:hover { background: rgba(255,255,255,0.1); border-left-color: #fff; }
        #sidebar ul li.active > a { background: rgba(255,255,255,0.15); border-left-color: #fff; font-weight: 600; }
        #sidebar ul li a i { margin-right: 10px; width: 25px; text-align: center; }
        #sidebar ul ul a { padding-left: 50px !important; font-size: .9rem !important; }
        #sidebar .sidebar-footer {
            padding: 20px; position: absolute; bottom: 0; width: 100%;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        #content { width: 100%; padding: 30px; min-height: 100vh; transition: .3s; background: #f8f9fa; }
        .navbar-custom {
            background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-radius: 10px; margin-bottom: 30px; padding: 15px 25px;
        }
        .page-header { padding-bottom: 15px; margin: 0 0 30px; border-bottom: 3px solid #667eea; }
        .page-header h1 { font-size: 2rem; font-weight: 600; color: #333; margin: 0; }
        .page-header h1 i { color: #667eea; margin-right: 10px; }
        .page-header small { font-size: 1rem; color: #6c757d; margin-left: 10px; }
        .card {
            border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .card-header {
            background: #fff; border-bottom: 2px solid #f0f0f0; padding: 20px 25px;
            border-radius: 15px 15px 0 0 !important;
        }
        .card-header h5 { margin: 0; font-weight: 600; color: #333; }
        .card-header h5 i { color: #667eea; margin-right: 10px; }
        .card-body { padding: 25px; }
        .table { margin: 0; }
        .table thead th {
            border-top: none; border-bottom: 2px solid #667eea; color: #555;
            font-weight: 600; text-transform: uppercase; font-size: .8rem;
            letter-spacing: .5px; padding: 15px 10px;
        }
        .table tbody td { padding: 15px 10px; vertical-align: middle; border-bottom: 1px solid #f0f0f0; color: #666; }
        .table tbody tr:hover { background: #f8f9fa; }
        .badge { padding: 6px 10px; border-radius: 20px; font-weight: 500; font-size: .75rem; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .loading-spinner {
            display: none; position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%); z-index: 9999;
        }
        .loading-spinner.active { display: block; }
        .spinner-border { width: 3rem; height: 3rem; color: #667eea; }
        .alert { border: none; border-radius: 10px; padding: 15px 20px; margin-bottom: 30px; }
        @media (max-width: 768px) {
            #sidebar { margin-left: -280px; }
            #sidebar.active { margin-left: 0; }
            #content { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div>
    </div>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Admin Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="members/manage_members.php"><i class="fas fa-user"></i> Members</a></li>
                <li><a href="manage_trainers.php"><i class="fas fa-chalkboard-teacher"></i> Trainers</a></li>
                <li><a href="classes/manage_classes.php"><i class="fas fa-calendar-alt"></i> Classes</a></li>
                <li><a href="payments/manage_payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li class="active"><a href="export_attendance.php"><i class="fas fa-clock"></i> Attendance Export</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
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
                    <div class="dropdown ml-3 d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown">
                            <i class="fas fa-user-circle fa-lg"></i>
                            <span class="ml-2"><?php echo htmlspecialchars($user_name); ?></span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profile</a>
                            <a class="dropdown-item" href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid">
                <?php Session::displayFlash(); ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header">
                            <h1><i class="fas fa-clock"></i> Attendance Export</h1>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list"></i> Attendance Records</h5>
                        <div>
                            <a href="?export=csv" class="btn btn-success btn-sm">
                                <i class="fas fa-file-csv"></i> Export CSV
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="attendanceTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Member</th>
                                        <th>Date</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance as $a): ?>
                                    <tr>
                                        <td><?php echo $a['id']; ?></td>
                                        <td><?php echo htmlspecialchars($a['member_name']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($a['date'])); ?></td>
                                        <td><?php echo $a['check_in'] ? date('h:i A', strtotime($a['check_in'])) : '-'; ?></td>
                                        <td><?php echo $a['check_out'] ? date('h:i A', strtotime($a['check_out'])) : '-'; ?></td>
                                        <td>
                                            <?php
                                            $badge = '';
                                            switch ($a['status']) {
                                                case 'present': $badge = 'success'; break;
                                                case 'late': $badge = 'warning'; break;
                                                case 'absent': $badge = 'danger'; break;
                                                default: $badge = 'secondary';
                                            }
                                            ?>
                                            <span class="badge badge-<?php echo $badge; ?>"><?php echo ucfirst($a['status']); ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($attendance)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">No attendance records found.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <!-- DataTables Buttons -->
    <script src="https://cdn.datatables.net/buttons/1.7.0/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.0/js/buttons.bootstrap4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.0/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.0/js/buttons.print.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#sidebarCollapse').on('click', function() {
                $('#sidebar').toggleClass('active');
            });

            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);

            // Initialize DataTable with export buttons
            if ($('#attendanceTable').length && $('#attendanceTable tbody tr').length > 1) {
                $('#attendanceTable').DataTable({
                    pageLength: 25,
                    order: [[2, 'desc']],
                    dom: 'Bfrtip',
                    buttons: [
                        {
                            extend: 'csv',
                            text: '<i class="fas fa-file-csv"></i> CSV',
                            className: 'btn btn-sm btn-outline-success'
                        },
                        {
                            extend: 'excel',
                            text: '<i class="fas fa-file-excel"></i> Excel',
                            className: 'btn btn-sm btn-outline-success'
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Print',
                            className: 'btn btn-sm btn-outline-secondary'
                        }
                    ],
                    language: {
                        search: "<i class='fas fa-search'></i>",
                        searchPlaceholder: "Search...",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        infoEmpty: "Showing 0 to 0 of 0 entries",
                        infoFiltered: "(filtered from _MAX_ total entries)"
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