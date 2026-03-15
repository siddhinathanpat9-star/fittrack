<?php
// member/payments.php
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
$user_name = Session::userName();
$functions = new Functions();
$error = '';
$success = '';

// Fetch active membership plans from the database
$plans = [];
try {
    $stmt = $pdo->query("SELECT id, name, price, description, features, duration_days FROM membership_plans WHERE status = 'active' ORDER BY sort_order, price");
    $plans = $stmt->fetchAll();
} catch (Exception $e) {
    // If table doesn't exist, just log and continue – the plans section will show a message
    error_log("Failed to fetch membership plans: " . $e->getMessage());
}

// Handle membership booking request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_membership'])) {
    $plan_id = $_POST['plan_id'] ?? '';
    $plan_name = $_POST['plan_name'] ?? '';
    $amount = (float) ($_POST['amount'] ?? 0);
    
    if ($plan_id && $amount > 0) {
        try {
            // Insert a pending payment record – do NOT include 'notes' column if it doesn't exist
            $stmt = $pdo->prepare("
                INSERT INTO payments (member_id, amount, payment_date, payment_method, payment_for, status, recorded_by)
                VALUES (?, ?, NOW(), ?, ?, 'pending', NULL)
            ");
            $stmt->execute([$member_id, $amount, 'manual', $plan_name . ' Plan']);
            
            $success = "Your membership request for the {$plan_name} plan has been submitted. Please pay at the gym to activate.";
        } catch (Exception $e) {
            $error = "Error submitting request: " . $e->getMessage();
        }
    } else {
        $error = "Invalid plan selection.";
    }
}

// Fetch payment history – removed 'notes' column (caused the error)
$payments = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, amount, payment_date, payment_method, status
        FROM payments
        WHERE member_id = ?
        ORDER BY payment_date DESC
    ");
    $stmt->execute([$member_id]);
    $payments = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading payments: " . $e->getMessage();
}

// Calculate summary
$total_paid = 0;
$last_payment = null;
$pending_count = 0;
foreach ($payments as $p) {
    if ($p['status'] == 'paid') {
        $total_paid += $p['amount'];
        if (!$last_payment || $p['payment_date'] > $last_payment) {
            $last_payment = $p['payment_date'];
        }
    } elseif ($p['status'] == 'pending') {
        $pending_count++;
    }
}

$page_title = 'My Payments - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 4, Font Awesome, DataTables -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.stats-card small{opacity:.9}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.badge-secondary{background:#e2e3e5;color:#383d41}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}.stats-card h2{font-size:1.8rem}}
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
                <li><a href="attendance.php"><i class="fas fa-clock"></i> My Attendance</a></li>
                <li><a href="workouts.php"><i class="fas fa-dumbbell"></i> Workout Plans</a></li>
                <li class="active"><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
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
                    <!-- User dropdown -->
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
                <!-- Error / Success messages -->
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

                <!-- Page Header -->
                <div class="page-header">
                    <h1><i class="fas fa-credit-card"></i> My Payments <small>Your transaction history</small></h1>
                </div>

                <!-- Payment Summary Cards (₹ currency) -->
                <div class="row">
                    <div class="col-xl-4 col-md-4">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <div class="card-title">Total Paid</div>
                                <h2>₹<?php echo number_format($total_paid, 2); ?></h2>
                                <i class="fas fa-rupee-sign"></i>
                                <small>Lifetime</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-4">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <div class="card-title">Last Payment</div>
                                <h2><?php echo $last_payment ? date('M d, Y', strtotime($last_payment)) : 'N/A'; ?></h2>
                                <i class="fas fa-calendar-check"></i>
                                <small>Most recent</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-4">
                        <div class="card stats-card bg-warning text-white">
                            <div class="card-body">
                                <div class="card-title">Pending Requests</div>
                                <h2><?php echo $pending_count; ?></h2>
                                <i class="fas fa-clock"></i>
                                <small>Awaiting approval</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Membership Plans – Dynamic from Database -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-tag mr-2"></i>Membership Plans – Book & Pay at Gym</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if (empty($plans)): ?>
                                <div class="col-12 text-center py-4">
                                    <p class="text-muted">No membership plans available at the moment.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($plans as $plan): 
                                    // Determine card header color based on price or name (optional)
                                    $color = 'info';
                                    if (strpos(strtolower($plan['name']), 'premium') !== false) $color = 'success';
                                    if (strpos(strtolower($plan['name']), 'vip') !== false) $color = 'warning';
                                ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card text-center h-100">
                                        <div class="card-header bg-<?php echo $color; ?> text-white">
                                            <h5><?php echo htmlspecialchars($plan['name']); ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <h2 class="text-<?php echo $color; ?>">₹<?php echo number_format($plan['price'], 2); ?></h2>
                                            <p class="text-muted">
                                                <?php echo $plan['duration_days'] . ' days'; ?>
                                            </p>
                                            <?php if (!empty($plan['description'])): ?>
                                                <p class="small"><?php echo nl2br(htmlspecialchars($plan['description'])); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($plan['features'])): 
                                                $features = explode("\n", trim($plan['features']));
                                            ?>
                                                <ul class="list-unstyled">
                                                    <?php foreach ($features as $feature): ?>
                                                        <?php if (trim($feature)): ?>
                                                            <li><i class="fas fa-check text-success mr-2"></i><?php echo htmlspecialchars(trim($feature)); ?></li>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                            <form method="post">
                                                <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                                <input type="hidden" name="plan_name" value="<?php echo htmlspecialchars($plan['name']); ?>">
                                                <input type="hidden" name="amount" value="<?php echo $plan['price']; ?>">
                                                <!-- <button type="submit" name="book_membership" class="btn btn-<?php echo $color; ?> btn-block">
                                                    <i class="fas fa-calendar-check mr-2"></i>Book This Plan
                                                </button> -->
                                                <button type="button"
class="btn btn-<?php echo $color; ?> btn-block pay-btn"
data-amount="<?php echo $plan['price']; ?>"
data-plan="<?php echo htmlspecialchars($plan['name']); ?>">
<i class="fas fa-credit-card mr-2"></i>Pay Now
</button>
                                            </form>
                                            <small class="text-muted">Pay at gym to activate</small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Payment History Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-history"></i> Payment History</h5>
                        <?php if (!empty($payments)): ?>
                            <span class="badge badge-info"><?php echo count($payments); ?> records</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($payments)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No payment records found.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover" id="paymentsTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $p): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y h:i A', strtotime($p['payment_date'])); ?></td>
                                            <td><strong>₹<?php echo number_format($p['amount'], 2); ?></strong></td>
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
                                          <td>

<?php if($p['status'] == 'pending'): ?>

<button 
class="btn btn-sm btn-success pay-history-btn"
data-id="<?php echo $p['id']; ?>"
data-amount="<?php echo $p['amount']; ?>">
<i class="fas fa-credit-card"></i> Pay
</button>

<?php endif; ?>

<a href="receipt.php?id=<?php echo $p['id']; ?>" 
class="btn btn-sm btn-info" 
title="View Receipt">
<i class="fas fa-file-invoice"></i>
</a>

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

            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);

            // Initialize DataTable if table exists
            if ($('#paymentsTable').length) {
                $('#paymentsTable').DataTable({
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                    order: [[0, 'desc']],
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search payments..."
                    }
                });
            }
        });

        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }
    </script>
    <script>

$('.pay-btn').click(function(){

$('.pay-history-btn').click(function(){

var payment_id = $(this).data('id');
var amount = $(this).data('amount') * 100;

var options = {
"key": "rzp_test_SRQKNs34F8ti57",
"amount": amount,
"currency": "INR",
"name": "FitTrack Gym",
"description": "Membership Payment",
"handler": function (response){

alert("Payment Successful\nPayment ID: " + response.razorpay_payment_id);

window.location.href="verify_payment.php?payment_id="+payment_id+"&razorpay_id="+response.razorpay_payment_id;

}
};

var rzp = new Razorpay(options);
rzp.open();

});
    var amount = $(this).data('amount') * 100; // Razorpay paise
    var plan = $(this).data('plan');

    var options = {
        "key": "rzp_test_SRQKNs34F8ti57",
        "amount": amount,
        "currency": "INR",
        "name": "FitTrack Gym",
        "description": plan + " Membership",
        "handler": function (response){

            alert("Payment Successful\nPayment ID: " + response.razorpay_payment_id);

            window.location.href="success.php?payment_id="+response.razorpay_payment_id;

        }
    };

    var rzp = new Razorpay(options);
    rzp.open();

});

</script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</body>
</html>