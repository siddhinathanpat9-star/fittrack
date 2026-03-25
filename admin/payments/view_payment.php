<?php
// admin/payments/view_payment.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

Session::requireAdmin();

$functions = new Functions();
$error = '';

// Get payment ID from URL
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($payment_id <= 0) {
    Session::setFlash('danger', 'Invalid payment ID.');
    header('Location: manage_payments.php');
    exit();
}

// Fetch payment details with member name and recorded by name
try {
    $stmt = $pdo->prepare("
        SELECT p.*,
               m.full_name as member_name,
               r.full_name as recorded_by_name
        FROM payments p
        LEFT JOIN users m ON p.member_id = m.id
        LEFT JOIN users r ON p.recorded_by = r.id
        WHERE p.id = ?
    ");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        Session::setFlash('danger', 'Payment not found.');
        header('Location: manage_payments.php');
        exit();
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

$page_title = 'Payment Details - ' . APP_NAME;
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
        /* Reuse the same CSS as dashboard */
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.stats-card small{opacity:.9}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.badge-secondary{background:#e2e3e5;color:#383d41}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:15px 20px;transition:.3s}.list-group-item:last-child{border-bottom:none}.list-group-item:hover{background:#f8f9fa;transform:translateX(5px)}.list-group-item i{color:#667eea;margin-right:10px}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}}
    </style>
</head>
<body>
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
                <li><a href="../manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="../members/manage_members.php"><i class="fas fa-user"></i> Members</a></li>
                <li><a href="../manage_trainers.php"><i class="fas fa-chalkboard-teacher"></i> Trainers</a></li>
                <li><a href="../classes/manage_classes.php"><i class="fas fa-calendar-alt"></i> Classes</a></li>
                <li class="active"><a href="manage_payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
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

                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header d-flex justify-content-between align-items-center">
                            <h1><i class="fas fa-credit-card"></i> Payment Details</h1>
                            <a href="manage_payments.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Payments</a>
                        </div>
                    </div>
                </div>

                <?php if ($payment): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-info-circle"></i> Payment Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th>ID:</th>
                                        <td><strong>#<?php echo $payment['id']; ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th>Amount:</th>
                                        <td><span class="badge badge-success" style="font-size:1rem;">₹<?php echo number_format($payment['amount'], 2); ?></span></td>
                                    </tr>
                                    <tr>
                                        <th>Date:</th>
                                        <td><?php echo date('M d, Y h:i A', strtotime($payment['payment_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Method:</th>
                                        <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>For:</th>
                                       <td><?php echo htmlspecialchars($payment['member_email'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Transaction ID:</th>
                                        <td><?php echo $payment['transaction_id'] ?: '<span class="text-muted">N/A</span>'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td>
                                            <?php
                                            $badge = match($payment['status']) {
                                                'paid'    => 'success',
                                                'pending' => 'warning',
                                                'failed'  => 'danger',
                                                'refunded'=> 'secondary',
                                                default   => 'secondary'
                                            };
                                            ?>
                                            <span class="badge badge-<?php echo $badge; ?>"><?php echo ucfirst($payment['status']); ?></span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-user"></i> Member Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th>Name:</th>
                                        <td><strong><?php echo htmlspecialchars($payment['member_name']); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th>Member ID:</th>
                                        <td>#<?php echo $payment['member_id']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email:</th>
                                        <td><?php echo htmlspecialchars($payment['member_email'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Phone:</th>
                                        <td><?php echo htmlspecialchars($payment['member_phone'] ?? 'N/A'); ?></td>
                                    </tr>
                                </table>
                                <a href="../members/view_member.php?id=<?php echo $payment['member_id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> View Member Profile</a>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-clipboard"></i> Notes & Metadata</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Notes:</strong><br><?php echo nl2br(htmlspecialchars($payment['notes'] ?? 'No notes')); ?></p>
                                <hr>
                                <p><strong>Recorded by:</strong> <?php echo htmlspecialchars($payment['recorded_by_name'] ?: 'System'); ?></p>
                                <p><strong>Created at:</strong> 
                                    <?php 
                                    if (!empty($payment['created_at'])) {
                                        echo date('M d, Y h:i A', strtotime($payment['created_at']));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </p>
                                <?php if (!empty($payment['updated_at']) && $payment['updated_at'] != ($payment['created_at'] ?? '')): ?>
                                <p><strong>Last updated:</strong> 
                                    <?php 
                                    if (!empty($payment['updated_at'])) {
                                        echo date('M d, Y h:i A', strtotime($payment['updated_at']));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-cog"></i> Actions</h5>
                            </div>
                            <div class="card-body">
                                <a href="edit_payment.php?id=<?php echo $payment['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
                                <a href="record_payment.php?clone=<?php echo $payment['id']; ?>" class="btn btn-info"><i class="fas fa-copy"></i> Clone</a>
                                <a href="manage_payments.php?delete=<?php echo $payment['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this payment?')"><i class="fas fa-trash"></i> Delete</a>
                                <a href="print_receipt.php?id=<?php echo $payment['id']; ?>" class="btn btn-secondary" target="_blank"><i class="fas fa-print"></i> Print Receipt</a>
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