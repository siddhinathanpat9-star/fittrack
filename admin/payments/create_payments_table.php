<?php
// admin/payments/create_payments_table.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Path to root includes folder
$root_includes = __DIR__ . '/../../includes/';

// Include required files
require_once $root_includes . 'config.php';
require_once $root_includes . 'session.php';
require_once $root_includes . 'functions.php'; // Added for consistency

// Check if user is admin
Session::requireAdmin();

$functions = new Functions(); // for potential later use
$messages = [];

try {
    // Create payments table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            member_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            payment_method ENUM('cash', 'card', 'online', 'bank_transfer') DEFAULT 'cash',
            payment_for VARCHAR(100) DEFAULT 'membership',
            transaction_id VARCHAR(100),
            notes TEXT,
            status ENUM('paid', 'pending', 'failed', 'refunded') DEFAULT 'paid',
            recorded_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_member (member_id),
            INDEX idx_date (payment_date),
            INDEX idx_status (status)
        )
    ");
    $messages[] = "✓ Payments table created successfully";

    // Insert sample data if table was empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM payments");
    if ($stmt->fetchColumn() == 0) {
        // Check if there are members
        $member_count = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'member'")->fetchColumn();
        
        if ($member_count > 0) {
            // Insert sample payments
            $pdo->exec("
                INSERT INTO payments (member_id, amount, payment_method, payment_for, status, payment_date)
                SELECT 
                    u.id,
                    CASE FLOOR(RAND() * 3)
                        WHEN 0 THEN 29.99
                        WHEN 1 THEN 49.99
                        ELSE 79.99
                    END,
                    ELT(1 + FLOOR(RAND() * 4), 'cash', 'card', 'online', 'bank_transfer'),
                    'membership',
                    ELT(1 + FLOOR(RAND() * 3), 'paid', 'paid', 'pending'),
                    DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 90) DAY)
                FROM users u
                WHERE u.user_type = 'member'
                LIMIT 50
            ");
            $messages[] = "✓ Sample payment data inserted";
        }
    }

} catch (PDOException $e) {
    $messages[] = "❌ Error: " . $e->getMessage();
}

$user_name = Session::userName();
$page_title = 'Create Payments Table - ' . APP_NAME;
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
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}.alert-success{background:#d4edda;color:#155724}.alert-danger{background:#f8d7da;color:#721c24}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}}
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
                <li>
                    <a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                </li>
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
                    <a href="#trainersSubmenu" data-toggle="collapse">
                        <i class="fas fa-user-tie"></i> Trainers <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="trainersSubmenu">
                        <li><a href="../manage_trainers.php"><i class="fas fa-list"></i> All Trainers</a></li>
                        <li><a href="../trainers/add_trainer.php"><i class="fas fa-user-plus"></i> Add Trainer</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#classesSubmenu" data-toggle="collapse">
                        <i class="fas fa-calendar-alt"></i> Classes <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="classesSubmenu">
                        <li><a href="../classes/manage_classes.php"><i class="fas fa-list"></i> All Classes</a></li>
                        <li><a href="../classes/add_class.php"><i class="fas fa-plus-circle"></i> Add Class</a></li>
                        <li><a href="../classes/class_schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
                    </ul>
                </li>
                <li class="active">
                    <a href="#paymentsSubmenu" data-toggle="collapse" aria-expanded="true">
                        <i class="fas fa-credit-card"></i> Payments <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse show list-unstyled" id="paymentsSubmenu">
                        <li><a href="manage_payments.php"><i class="fas fa-list"></i> Manage Payments</a></li>
                        <li><a href="record_payment.php"><i class="fas fa-plus-circle"></i> Record Payment</a></li>
                        <li><a href="payment_reports.php"><i class="fas fa-chart-bar"></i> Payment Reports</a></li>
                        <li class="active"><a href="create_payments_table.php"><i class="fas fa-database"></i> Create Table</a></li>
                    </ul>
                </li>
                <li><a href="../attendance.php"><i class="fas fa-clock"></i> Attendance</a></li>
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
                    <!-- Notifications dropdown -->
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
                <!-- Page Header -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header">
                            <h1><i class="fas fa-database"></i> Create Payments Table <small>Setup payments table and sample data</small></h1>
                        </div>
                    </div>
                </div>

                <!-- Results Card -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-cogs"></i> Table Creation Results</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($messages as $message): ?>
                            <div class="alert <?php echo strpos($message, '✓') === 0 ? 'alert-success' : 'alert-danger'; ?>">
                                <?php echo $message; ?>
                            </div>
                        <?php endforeach; ?>

                        <hr>
                        
                        <h6>Next Steps:</h6>
                        <ul>
                            <li><a href="manage_payments.php">Go to Manage Payments</a></li>
                            <li><a href="record_payment.php">Record a New Payment</a></li>
                            <li><a href="payment_reports.php">View Payment Reports</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="logoutModalLabel"><i class="fas fa-sign-out-alt"></i> Confirm Logout</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
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

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
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