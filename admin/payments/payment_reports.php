<?php
// admin/payments/payment_reports.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is admin
if (!Session::isAdmin()) {
    Session::setFlash('danger', 'Access denied.');
    header('Location: ../../login.php');
    exit();
}

$functions = new Functions();
$error = '';

// Get date range from request or default to last 30 days
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));

// Initialize data arrays for charts
$revenue_labels = [];
$revenue_data = [];
$method_labels = [];
$method_data = [];

try {
    // Summary stats
    $total_paid = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'paid'")->fetchColumn();
    $total_pending = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
    $total_failed = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'failed'")->fetchColumn();
    $total_refunded = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'refunded'")->fetchColumn();

    $sum_paid = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'paid'")->fetchColumn();
    $sum_pending = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'pending'")->fetchColumn();
    $sum_failed = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'failed'")->fetchColumn();
    $sum_refunded = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'refunded'")->fetchColumn();

    // Revenue over time (daily for selected range)
    $stmt = $pdo->prepare("
        SELECT DATE(payment_date) as day, SUM(amount) as total
        FROM payments
        WHERE status = 'paid' AND DATE(payment_date) BETWEEN ? AND ?
        GROUP BY DATE(payment_date)
        ORDER BY day
    ");
    $stmt->execute([$date_from, $date_to]);
    $daily = $stmt->fetchAll();
    foreach ($daily as $row) {
        $revenue_labels[] = date('M d', strtotime($row['day']));
        $revenue_data[] = (float)$row['total'];
    }

    // Payment method breakdown (for paid payments)
    $stmt = $pdo->query("
        SELECT payment_method, COUNT(*) as count, SUM(amount) as total
        FROM payments
        WHERE status = 'paid'
        GROUP BY payment_method
        ORDER BY count DESC
    ");
    $methods = $stmt->fetchAll();
    foreach ($methods as $row) {
        $method_labels[] = ucfirst($row['payment_method']);
        $method_data[] = $row['total'];
    }

} catch (Exception $e) {
    $error = "Error loading reports: " . $e->getMessage();
}

// Recent payments (last 10)
$recent_payments = [];
try {
    $stmt = $pdo->query("
        SELECT p.*, u.full_name as member_name
        FROM payments p
        JOIN users u ON p.member_id = u.id
        ORDER BY p.payment_date DESC
        LIMIT 10
    ");
    $recent_payments = $stmt->fetchAll();
} catch (Exception $e) {
    // ignore
}

$user_name = Session::userName();
$page_title = 'Payment Reports - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 4, Font Awesome, DataTables, Chart.js -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.stats-card small{opacity:.9}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}.chart-container{position:relative;height:300px;margin:20px 0}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}.stats-card h2{font-size:1.8rem}}
    </style>
</head>
<body>
    <!-- Loading Spinner (optional) -->
    <div class="loading-spinner" id="loadingSpinner"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>

    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Administrator Panel</p>
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
                <li>
                    <a href="#classesSubmenu" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-calendar-alt"></i> Classes <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="classesSubmenu">
                        <li><a href="../classes/manage_classes.php"><i class="fas fa-list"></i> All Classes</a></li>
                        <li><a href="../classes/add_class.php"><i class="fas fa-plus-circle"></i> Add Class</a></li>
                        <li><a href="../classes/class_schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
                    </ul>
                </li>
                <li class="active">
                    <a href="manage_payments.php"><i class="fas fa-credit-card"></i> Payments</a>
                </li>
                <li><a href="../attendance.php"><i class="fas fa-clock"></i> Attendance</a></li>
                <li><a href="../equipment.php"><i class="fas fa-dumbbell"></i> Equipment</a></li>
                <li><a href="../reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../settings.php"><i class="fas fa-cog"></i> Settings</a></li>
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
                            <a class="dropdown-item" href="#"><strong>New member registered</strong><br><small class="text-muted">2 minutes ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Payment received</strong><br><small class="text-muted">1 hour ago</small></a>
                            <a class="dropdown-item" href="#"><strong>5 memberships expiring soon</strong><br><small class="text-muted">3 hours ago</small></a>
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
                            <a class="dropdown-item" href="../profile.php"><i class="fas fa-user"></i> My Profile</a>
                            <a class="dropdown-item" href="../settings.php"><i class="fas fa-cog"></i> Settings</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid">
                <!-- Flash messages from session -->
                <?php Session::displayFlash(); ?>

                <!-- Error alert from form processing -->
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="page-header d-flex justify-content-between align-items-center">
                    <h1>
                        <i class="fas fa-chart-bar"></i> Payment Reports
                        <small>Analyze revenue and transactions</small>
                    </h1>
                    <a href="manage_payments.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Payments
                    </a>
                </div>

                <!-- Summary Cards (using stats-card) -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <div class="card-title">Paid</div>
                                <h3 class="mb-0"><?php echo $total_paid; ?></h3>
                                <span>₹<?php echo number_format($sum_paid, 2); ?></span>
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card bg-warning text-white">
                            <div class="card-body">
                                <div class="card-title">Pending</div>
                                <h3 class="mb-0"><?php echo $total_pending; ?></h3>
                                <span>₹<?php echo number_format($sum_pending, 2); ?></span>
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card bg-danger text-white">
                            <div class="card-body">
                                <div class="card-title">Failed</div>
                                <h3 class="mb-0"><?php echo $total_failed; ?></h3>
                                <span>₹<?php echo number_format($sum_failed, 2); ?></span>
                                <i class="fas fa-times-circle"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card bg-secondary text-white">
                            <div class="card-body">
                                <div class="card-title">Refunded</div>
                                <h3 class="mb-0"><?php echo $total_refunded; ?></h3>
                                <span>₹<?php echo number_format($sum_refunded, 2); ?></span>
                                <i class="fas fa-undo-alt"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Date Range Filter -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-filter mr-2"></i>Filter by Date</h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="form-row">
                            <div class="form-group col-md-4">
                                <label for="date_from">From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="date_to">To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="form-group col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-filter mr-2"></i>Apply</button>
                                <a href="payment_reports.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Revenue Chart -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line mr-2"></i>Daily Revenue (<?php echo date('M d', strtotime($date_from)); ?> - <?php echo date('M d', strtotime($date_to)); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Payment Method Breakdown -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-pie mr-2"></i>Revenue by Method</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height:250px;">
                                    <canvas id="methodChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5><i class="fas fa-table mr-2"></i>Payment Method Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Method</th>
                                                <th>Count</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($methods as $m): ?>
                                            <tr>
                                                <td><?php echo ucfirst($m['payment_method']); ?></td>
                                                <td><?php echo $m['count']; ?></td>
                                                <td>₹<?php echo number_format($m['total'], 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($methods)): ?>
                                            <tr><td colspan="3" class="text-center">No payment data</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Payments -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history mr-2"></i>Recent Payments</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Member</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_payments as $p): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y h:i A', strtotime($p['payment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($p['member_name']); ?></td>
                                        <td>₹<?php echo number_format($p['amount'], 2); ?></td>
                                        <td><?php echo ucfirst($p['payment_method']); ?></td>
                                        <td>
                                            <?php
                                            $badge = match($p['status']) {
                                                'paid'    => 'success',
                                                'pending' => 'warning',
                                                'failed'  => 'danger',
                                                'refunded'=> 'secondary',
                                                default   => 'secondary'
                                            };
                                            ?>
                                            <span class="badge badge-<?php echo $badge; ?>"><?php echo ucfirst($p['status']); ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($recent_payments)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No recent payments</td>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        // Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Revenue line chart
            const revCtx = document.getElementById('revenueChart')?.getContext('2d');
            if (revCtx && <?php echo json_encode(!empty($revenue_labels)); ?>) {
                new Chart(revCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($revenue_labels); ?>,
                        datasets: [{
                            label: 'Revenue (₹)',
                            data: <?php echo json_encode($revenue_data); ?>,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102,126,234,0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            } else if (revCtx) {
                revCtx.font = '14px Inter';
                revCtx.fillStyle = '#999';
                revCtx.fillText('No data for selected period', 10, 50);
            }

            // Method pie chart
            const methodCtx = document.getElementById('methodChart')?.getContext('2d');
            if (methodCtx && <?php echo json_encode(!empty($method_labels)); ?>) {
                new Chart(methodCtx, {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode($method_labels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($method_data); ?>,
                            backgroundColor: ['#667eea', '#764ba2', '#28a745', '#ffc107']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            } else if (methodCtx) {
                methodCtx.font = '14px Inter';
                methodCtx.fillStyle = '#999';
                methodCtx.fillText('No payment method data', 10, 50);
            }
        });
    </script>
</body>
</html>