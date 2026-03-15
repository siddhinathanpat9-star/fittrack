<?php
// admin/notifications/send_notification.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Path to root includes folder
$root_includes = __DIR__ . '/../../includes/';

// Include required files
require_once $root_includes . 'config.php';
require_once $root_includes . 'session.php';
require_once $root_includes . 'functions.php';

// Check if user is admin
Session::requireAdmin();

$functions = new Functions(); // for potential later use
$error = '';
$success = '';

// Pre-select recipient type from URL (e.g., ?to=trainers)
$preselect_to = isset($_GET['to']) ? $_GET['to'] : '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_notification'])) {
    $recipient_type = $_POST['recipient_type'];
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $type = $_POST['type'] ?? 'info';

    // Validation
    $errors = [];
    if (empty($title)) {
        $errors[] = "Title is required.";
    }
    if (empty($message)) {
        $errors[] = "Message is required.";
    }
    if (!in_array($recipient_type, ['all', 'admin', 'trainer', 'member'])) {
        $errors[] = "Invalid recipient type.";
    }

    if (empty($errors)) {
        try {
            // Determine which users to notify
            $sql = "SELECT id FROM users WHERE 1=1";
            $params = [];
            if ($recipient_type !== 'all') {
                $sql .= " AND user_type = ?";
                $params[] = $recipient_type;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($users)) {
                throw new Exception("No users found of the selected type.");
            }

            // Begin transaction
            $pdo->beginTransaction();

            // Insert master notification
            $stmt = $pdo->prepare("
                INSERT INTO notifications (sender_id, title, message, type, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([Session::userId(), $title, $message, $type]);
            $notification_id = $pdo->lastInsertId();

            // Insert recipient records
            $stmt = $pdo->prepare("
                INSERT INTO notification_recipients (notification_id, user_id)
                VALUES (?, ?)
            ");
            foreach ($users as $user_id) {
                $stmt->execute([$notification_id, $user_id]);
            }

            $pdo->commit();

            $success = "Notification sent to " . count($users) . " user(s).";

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error sending notification: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

$user_name = Session::userName();
$page_title = 'Send Notification - ' . APP_NAME;
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
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}.btn-primary{background:#667eea;border-color:#667eea}.btn-primary:hover{background:#5a67d8;border-color:#5a67d8}.form-group{margin-bottom:1rem}.form-control{border-radius:10px;border:1px solid #e2e8f0;padding:10px 15px}.form-control:focus{border-color:#667eea;box-shadow:0 0 0 0.2rem rgba(102,126,234,0.25)}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}}
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
                <li>
                    <a href="#paymentsSubmenu" data-toggle="collapse">
                        <i class="fas fa-credit-card"></i> Payments <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="paymentsSubmenu">
                        <li><a href="../payments/manage_payments.php"><i class="fas fa-list"></i> Manage Payments</a></li>
                        <li><a href="../payments/record_payment.php"><i class="fas fa-plus-circle"></i> Record Payment</a></li>
                        <li><a href="../payments/payment_reports.php"><i class="fas fa-chart-bar"></i> Payment Reports</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#communicationsSubmenu" data-toggle="collapse" aria-expanded="true">
                        <i class="fas fa-bell"></i> Communications <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse show list-unstyled" id="communicationsSubmenu">
                        <li><a href="../send_bulk_email.php"><i class="fas fa-envelope"></i> Bulk Email</a></li>
                        <li class="active"><a href="send_notification.php"><i class="fas fa-bell"></i> Send Notification</a></li>
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
                            <h1><i class="fas fa-paper-plane"></i> Send Notification <small>Send a notification to users</small></h1>
                        </div>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php Session::displayFlash(); ?>

                <!-- Notification Form Card -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-edit"></i> Compose Notification</h5>
                        <a href="manage_notifications.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-history"></i> Notification History</a>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="notificationForm" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="recipient_type">Send To <span class="text-danger">*</span></label>
                                    <select class="form-control" id="recipient_type" name="recipient_type" required>
                                        <option value="">Select Recipients</option>
                                        <option value="all" <?php echo ($preselect_to == 'all') ? 'selected' : ''; ?>>All Users</option>
                                        <option value="admin" <?php echo ($preselect_to == 'admin') ? 'selected' : ''; ?>>Admins Only</option>
                                        <option value="trainer" <?php echo ($preselect_to == 'trainers' || $preselect_to == 'trainer') ? 'selected' : ''; ?>>Trainers Only</option>
                                        <option value="member" <?php echo ($preselect_to == 'members' || $preselect_to == 'member') ? 'selected' : ''; ?>>Members Only</option>
                                    </select>
                                    <div class="invalid-feedback">Please select recipient group.</div>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="type">Notification Type</label>
                                    <select class="form-control" id="type" name="type">
                                        <option value="info">Info (Blue)</option>
                                        <option value="success">Success (Green)</option>
                                        <option value="warning">Warning (Yellow)</option>
                                        <option value="danger">Danger (Red)</option>
                                    </select>
                                </div>

                                <div class="col-12 form-group">
                                    <label for="title">Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           placeholder="e.g., System Maintenance" required>
                                    <div class="invalid-feedback">Please enter a title.</div>
                                </div>

                                <div class="col-12 form-group">
                                    <label for="message">Message <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="message" name="message" rows="5" 
                                              placeholder="Enter your notification message here..." required></textarea>
                                    <div class="invalid-feedback">Please enter a message.</div>
                                </div>
                            </div>

                            <hr>
                            <div class="d-flex justify-content-between">
                                <button type="submit" name="send_notification" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane mr-2"></i>Send Notification
                                </button>
                                <button type="reset" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-undo mr-2"></i>Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Info Card -->
                <div class="card mt-4 bg-light">
                    <div class="card-body">
                        <h6><i class="fas fa-info-circle text-primary mr-2"></i>About Notifications</h6>
                        <ul class="mb-0 small">
                            <li>Notifications will appear in the recipient's dashboard and notification bell.</li>
                            <li>Recipients can mark them as read.</li>
                            <li>You can view sent notifications in the <a href="manage_notifications.php">Notification History</a>.</li>
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

        // Form validation (Bootstrap 4)
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>
</body>
</html>