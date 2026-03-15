<?php
// admin/membership/add_plan.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Path to root includes folder
$root_includes = __DIR__ . '/../../includes/';

require_once $root_includes . 'config.php';
require_once $root_includes . 'session.php';
require_once $root_includes . 'functions.php';

Session::requireAdmin();

$error = '';
$success = '';

// ------------------------------------------------------------------
// Dynamically determine which columns exist in membership_plans
// ------------------------------------------------------------------
$existingColumns = [];
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM membership_plans");
    $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Table might not exist – will be handled on insert
}

// List of possible columns (the ones we might insert)
$possibleColumns = [
    'name', 'description', 'price', 'duration_days', 'features',
    'max_classes_per_week', 'personal_training_sessions',
    'access_to_equipment', 'access_to_classes', 'access_to_sauna', 'access_to_pool',
    'guest_passes', 'status', 'sort_order'
];

// Build the INSERT query dynamically based on existing columns
$insertColumns = [];
foreach ($possibleColumns as $col) {
    if (in_array($col, $existingColumns)) {
        $insertColumns[] = $col;
    }
}
$insertColumnsList = implode(', ', $insertColumns);
$placeholders = ':' . implode(', :', $insertColumns);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_plan'])) {
    // Collect and sanitize inputs
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $duration_days = (int) ($_POST['duration_days'] ?? 0);
    $features = trim($_POST['features'] ?? '');
    $max_classes_per_week = !empty($_POST['max_classes_per_week']) ? (int) $_POST['max_classes_per_week'] : null;
    $personal_training_sessions = (int) ($_POST['personal_training_sessions'] ?? 0);
    $guest_passes = (int) ($_POST['guest_passes'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    $sort_order = (int) ($_POST['sort_order'] ?? 0);

    // Checkboxes – only present if checked
    $access_to_equipment = isset($_POST['access_to_equipment']) ? 1 : 0;
    $access_to_classes   = isset($_POST['access_to_classes']) ? 1 : 0;
    $access_to_sauna     = isset($_POST['access_to_sauna']) ? 1 : 0;
    $access_to_pool      = isset($_POST['access_to_pool']) ? 1 : 0;

    // Basic validation
    $errors = [];
    if (empty($name)) $errors[] = 'Plan name is required.';
    if ($price <= 0) $errors[] = 'Price must be greater than zero.';
    if ($duration_days <= 0) $errors[] = 'Duration must be greater than zero.';

    if (empty($errors)) {
        try {
            // Prepare data array for insertion (only existing columns)
            $data = [];
            foreach ($insertColumns as $col) {
                switch ($col) {
                    case 'name': $data[':name'] = $name; break;
                    case 'description': $data[':description'] = $description; break;
                    case 'price': $data[':price'] = $price; break;
                    case 'duration_days': $data[':duration_days'] = $duration_days; break;
                    case 'features': $data[':features'] = $features; break;
                    case 'max_classes_per_week': $data[':max_classes_per_week'] = $max_classes_per_week; break;
                    case 'personal_training_sessions': $data[':personal_training_sessions'] = $personal_training_sessions; break;
                    case 'access_to_equipment': $data[':access_to_equipment'] = $access_to_equipment; break;
                    case 'access_to_classes': $data[':access_to_classes'] = $access_to_classes; break;
                    case 'access_to_sauna': $data[':access_to_sauna'] = $access_to_sauna; break;
                    case 'access_to_pool': $data[':access_to_pool'] = $access_to_pool; break;
                    case 'guest_passes': $data[':guest_passes'] = $guest_passes; break;
                    case 'status': $data[':status'] = $status; break;
                    case 'sort_order': $data[':sort_order'] = $sort_order; break;
                }
            }

            if (!empty($insertColumns)) {
                $sql = "INSERT INTO membership_plans ($insertColumnsList) VALUES ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($data);

                Session::setFlash('success', 'Membership plan added successfully.');
                header('Location: membership_plans.php');
                exit();
            } else {
                $error = "No columns available to insert – table structure issue.";
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

$user_name = Session::userName();
$page_title = 'Add Membership Plan - ' . APP_NAME;
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
                <li class="active">
                    <a href="#membersSubmenu" data-toggle="collapse" aria-expanded="true">
                        <i class="fas fa-users"></i> Members <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse show list-unstyled" id="membersSubmenu">
                        <li><a href="../members/manage_members.php"><i class="fas fa-list"></i> All Members</a></li>
                        <li><a href="../members/add_member.php"><i class="fas fa-user-plus"></i> Add Member</a></li>
                        <li class="active"><a href="membership_plans.php"><i class="fas fa-tag"></i> Membership Plans</a></li>
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
                    <a href="#communicationsSubmenu" data-toggle="collapse">
                        <i class="fas fa-bell"></i> Communications <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="communicationsSubmenu">
                        <li><a href="../send_bulk_email.php"><i class="fas fa-envelope"></i> Bulk Email</a></li>
                        <li><a href="../notifications/send_notification.php"><i class="fas fa-bell"></i> Send Notification</a></li>
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
                            <h1><i class="fas fa-plus-circle"></i> Add Membership Plan <small>Create a new plan</small></h1>
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

                <!-- Form Card -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-id-card"></i> Plan Details</h5>
                        <a href="membership_plans.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Plans</a>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="row">
                                <!-- Basic Info -->
                                <div class="col-md-6 form-group">
                                    <label for="name">Plan Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="price">Price (₹) *</label>
                                    <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="duration_days">Duration (days) *</label>
                                    <input type="number" class="form-control" id="duration_days" name="duration_days" value="<?php echo htmlspecialchars($_POST['duration_days'] ?? '30'); ?>" required>
                                </div>
                                <div class="col-12 form-group">
                                    <label for="description">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-12 form-group">
                                    <label for="features">Features (one per line or comma separated)</label>
                                    <textarea class="form-control" id="features" name="features" rows="4" placeholder="Enter features..."><?php echo htmlspecialchars($_POST['features'] ?? ''); ?></textarea>
                                </div>

                                <!-- Limits & Add-ons -->
                                <div class="col-md-4 form-group">
                                    <label for="max_classes_per_week">Max Classes/Week</label>
                                    <input type="number" class="form-control" id="max_classes_per_week" name="max_classes_per_week" value="<?php echo htmlspecialchars($_POST['max_classes_per_week'] ?? ''); ?>" placeholder="Unlimited if empty">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="personal_training_sessions">Personal Training Sessions (per month)</label>
                                    <input type="number" class="form-control" id="personal_training_sessions" name="personal_training_sessions" value="<?php echo htmlspecialchars($_POST['personal_training_sessions'] ?? '0'); ?>">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="guest_passes">Guest Passes (per month)</label>
                                    <input type="number" class="form-control" id="guest_passes" name="guest_passes" value="<?php echo htmlspecialchars($_POST['guest_passes'] ?? '0'); ?>">
                                </div>

                                <!-- Access Checkboxes (only shown if corresponding column exists) -->
                                <?php
                                $showAccess = in_array('access_to_equipment', $existingColumns) ||
                                              in_array('access_to_classes', $existingColumns) ||
                                              in_array('access_to_sauna', $existingColumns) ||
                                              in_array('access_to_pool', $existingColumns);
                                if ($showAccess):
                                ?>
                                <div class="col-12">
                                    <label class="d-block">Access Included:</label>
                                    <?php if (in_array('access_to_equipment', $existingColumns)): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="access_to_equipment" name="access_to_equipment" <?php echo isset($_POST['access_to_equipment']) ? 'checked' : 'checked'; ?>>
                                        <label class="form-check-label" for="access_to_equipment">Equipment</label>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (in_array('access_to_classes', $existingColumns)): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="access_to_classes" name="access_to_classes" <?php echo isset($_POST['access_to_classes']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="access_to_classes">Group Classes</label>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (in_array('access_to_sauna', $existingColumns)): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="access_to_sauna" name="access_to_sauna" <?php echo isset($_POST['access_to_sauna']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="access_to_sauna">Sauna</label>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (in_array('access_to_pool', $existingColumns)): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="access_to_pool" name="access_to_pool" <?php echo isset($_POST['access_to_pool']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="access_to_pool">Pool</label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <!-- Status & Sort -->
                                <div class="col-md-6 form-group mt-3">
                                    <label for="status">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-6 form-group mt-3">
                                    <label for="sort_order">Sort Order</label>
                                    <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?php echo htmlspecialchars($_POST['sort_order'] ?? '0'); ?>">
                                </div>
                            </div>

                            <hr>
                            <button type="submit" name="add_plan" class="btn btn-primary"><i class="fas fa-save mr-2"></i>Save Plan</button>
                            <a href="membership_plans.php" class="btn btn-secondary">Cancel</a>
                        </form>
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