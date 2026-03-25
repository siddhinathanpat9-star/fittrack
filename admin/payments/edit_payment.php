<?php
// admin/payments/edit_payment.php
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

// Get payment ID from URL
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($payment_id <= 0) {
    Session::setFlash('danger', 'Invalid payment ID.');
    header('Location: manage_payments.php');
    exit();
}

// Fetch payment data
$payment = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

if (!$payment) {
    Session::setFlash('danger', 'Payment not found.');
    header('Location: manage_payments.php');
    exit();
}

// Get all members for dropdown (in case we want to reassign)
$members = [];
try {
    $stmt = $pdo->query("SELECT id, full_name FROM users WHERE user_type = 'member' ORDER BY full_name");
    $members = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading members: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $member_id = (int)$_POST['member_id'];
    $amount = (float)$_POST['amount'];
    $payment_date = $_POST['payment_date'] ?: date('Y-m-d H:i:s');
    $payment_method = $_POST['payment_method'];
    $payment_for = $_POST['payment_for'];
    $transaction_id = trim($_POST['transaction_id']) ?: null;
    $notes = trim($_POST['notes']) ?: null;
    $status = $_POST['status'];

    // Basic validation
    $errors = [];
    if ($member_id <= 0) $errors[] = "Please select a member.";
    if ($amount <= 0) $errors[] = "Amount must be greater than zero.";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE payments
                SET member_id = ?, amount = ?, payment_date = ?, payment_method = ?,
                    payment_for = ?, transaction_id = ?, notes = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$member_id, $amount, $payment_date, $payment_method,
                            $payment_for, $transaction_id, $notes, $status, $payment_id]);

            $success = "Payment updated successfully.";
            // Refresh payment data
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch();
        } catch (Exception $e) {
            $error = "Error updating payment: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

$user_name = Session::userName();
$page_title = 'Edit Payment - ' . APP_NAME;
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.stats-card small{opacity:.9}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:15px 20px;transition:.3s}.list-group-item:last-child{border-bottom:none}.list-group-item:hover{background:#f8f9fa;transform:translateX(5px)}.list-group-item i{color:#667eea;margin-right:10px}.chart-container{position:relative;height:300px;margin:20px 0}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}.stats-card h2{font-size:1.8rem}}
    </style>
</head>
<body>
    <div class="loading-spinner" id="loadingSpinner"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>
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
                    <!-- Notifications dropdown (static) -->
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
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown"><i class="fas fa-user-circle fa-lg"></i><span class="ml-2"><?php echo htmlspecialchars($user_name ?? ''); ?></span></button>
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
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php Session::displayFlash(); ?>

                <!-- Page Header -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header">
                            <h1><i class="fas fa-edit"></i> Edit Payment <small>#<?php echo $payment['id']; ?></small></h1>
                        </div>
                    </div>
                </div>

                <!-- Edit Payment Form -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-credit-card"></i> Payment Details</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Member</label>
                                        <select name="member_id" class="form-control" required>
                                            <option value="">Select Member</option>
                                            <?php foreach ($members as $m): ?>
                                                <option value="<?php echo $m['id']; ?>" <?php if ($m['id'] == $payment['member_id']) echo 'selected'; ?>>
                                                    <?php echo htmlspecialchars($m['full_name'] ?? ''); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Amount (₹)</label>
                                        <input type="number" step="0.01" name="amount" class="form-control" value="<?php echo $payment['amount']; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Payment Date</label>
                                        <input type="datetime-local" name="payment_date" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($payment['payment_date'])); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Payment Method</label>
                                       <select name="payment_method" class="form-control">
    <option value="cash" <?php if ($payment['payment_method'] == 'cash') echo 'selected'; ?>>Cash</option>
    <option value="card" <?php if ($payment['payment_method'] == 'card') echo 'selected'; ?>>Card</option>
    <option value="online" <?php if ($payment['payment_method'] == 'online') echo 'selected'; ?>>Online</option>
    <option value="bank_transfer" <?php if ($payment['payment_method'] == 'bank_transfer') echo 'selected'; ?>>Bank Transfer</option>
    <option value="razorpay" <?php if ($payment['payment_method'] == 'razorpay') echo 'selected'; ?>>Razorpay</option>
</select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Payment For</label>
                                        <input type="text" name="payment_for" class="form-control" value="<?php echo htmlspecialchars($payment['payment_for'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Transaction ID</label>
                                        <input type="text" name="transaction_id" class="form-control" value="<?php echo htmlspecialchars($payment['transaction_id'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select name="status" class="form-control">
                                            <option value="paid" <?php if ($payment['status'] == 'paid') echo 'selected'; ?>>Paid</option>
                                            <option value="pending" <?php if ($payment['status'] == 'pending') echo 'selected'; ?>>Pending</option>
                                            <option value="failed" <?php if ($payment['status'] == 'failed') echo 'selected'; ?>>Failed</option>
                                            <option value="refunded" <?php if ($payment['status'] == 'refunded') echo 'selected'; ?>>Refunded</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Notes</label>
                                        <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group text-right">
                                <a href="manage_payments.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="update_payment" class="btn btn-primary">Update Payment</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Quick Actions (optional) -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-6 mb-3">
                                <a href="record_payment.php" class="btn btn-outline-primary btn-block py-3">
                                    <i class="fas fa-plus-circle fa-2x mb-2"></i><br>Record Payment
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="manage_payments.php" class="btn btn-outline-success btn-block py-3">
                                    <i class="fas fa-list fa-2x mb-2"></i><br>All Payments
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="payment_reports.php" class="btn btn-outline-warning btn-block py-3">
                                    <i class="fas fa-chart-bar fa-2x mb-2"></i><br>Reports
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="../dashboard.php" class="btn btn-outline-info btn-block py-3">
                                    <i class="fas fa-tachometer-alt fa-2x mb-2"></i><br>Dashboard
                                </a>
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
        });
        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }
    </script>
</body>
</html>