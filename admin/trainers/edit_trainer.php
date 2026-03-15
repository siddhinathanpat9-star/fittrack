<?php
// admin/trainers/edit_trainer.php
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

// Get trainer ID from URL
$trainer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($trainer_id <= 0) {
    Session::setFlash('danger', 'Invalid trainer ID.');
    header('Location: ../manage_trainers.php');
    exit();
}

// Fetch trainer data (user + trainer details)
$trainer = null;
try {
    $stmt = $pdo->prepare("
        SELECT u.*, t.specialization, t.experience_years, t.hourly_rate, 
               t.qualification, t.availability
        FROM users u
        LEFT JOIN trainers t ON u.id = t.user_id
        WHERE u.id = ? AND u.user_type = 'trainer'
    ");
    $stmt->execute([$trainer_id]);
    $trainer = $stmt->fetch();
} catch (Exception $e) {
    Session::setFlash('danger', 'Database error: ' . $e->getMessage());
    header('Location: ../manage_trainers.php');
    exit();
}

if (!$trainer) {
    Session::setFlash('danger', 'Trainer not found.');
    header('Location: ../manage_trainers.php');
    exit();
}

// Initialize functions
$functions = new Functions();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Get form data
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $specialization = trim($_POST['specialization']);
        $experience_years = (int)$_POST['experience_years'];
        $hourly_rate = (float)$_POST['hourly_rate'];
        $qualification = trim($_POST['qualification']);
        $availability = trim($_POST['availability']);
        $status = $_POST['status'];
        
        // Optional password change
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Validation
        $errors = [];

        if (empty($full_name)) {
            $errors[] = "Full name is required";
        }

        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        // Check if email already used by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $trainer_id]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Email already in use by another user";
        }

        if (empty($specialization)) {
            $errors[] = "Specialization is required";
        }

        if ($experience_years < 0) {
            $errors[] = "Experience years must be a positive number";
        }

        if ($hourly_rate < 0) {
            $errors[] = "Hourly rate must be a positive number";
        }

        // Password validation if changing
        if (!empty($new_password)) {
            if (strlen($new_password) < 6) {
                $errors[] = "Password must be at least 6 characters";
            }
            if ($new_password !== $confirm_password) {
                $errors[] = "Passwords do not match";
            }
        }

        if (!empty($errors)) {
            throw new Exception(implode("<br>", $errors));
        }

        // Start transaction
        $pdo->beginTransaction();

        // Update users table
        $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, status = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$full_name, $email, $phone, $address, $status, $trainer_id]);

        // Update password if provided
        if (!empty($new_password)) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $trainer_id]);
        }

        // Update or insert into trainers table
        // Check if trainer record exists
        $stmt = $pdo->prepare("SELECT user_id FROM trainers WHERE user_id = ?");
        $stmt->execute([$trainer_id]);
        if ($stmt->rowCount() > 0) {
            // Update
            $stmt = $pdo->prepare("
                UPDATE trainers SET 
                    specialization = ?, 
                    experience_years = ?, 
                    hourly_rate = ?, 
                    qualification = ?, 
                    availability = ? 
                WHERE user_id = ?
            ");
            $stmt->execute([$specialization, $experience_years, $hourly_rate, $qualification, $availability, $trainer_id]);
        } else {
            // Insert (shouldn't happen, but just in case)
            $stmt = $pdo->prepare("
                INSERT INTO trainers (user_id, specialization, experience_years, hourly_rate, qualification, availability)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$trainer_id, $specialization, $experience_years, $hourly_rate, $qualification, $availability]);
        }

        $pdo->commit();

        Session::setFlash('success', 'Trainer updated successfully!');
        header('Location: edit_trainer.php?id=' . $trainer_id);
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Common specializations list (optional)
$specializations = [
    'Weight Training',
    'Cardio',
    'Yoga',
    'Pilates',
    'CrossFit',
    'Zumba',
    'Personal Training',
    'Nutrition',
    'Physical Therapy',
    'Sports Performance',
    'Bodybuilding',
    'Functional Training',
    'Kickboxing',
    'Aerobics',
    'Senior Fitness',
    'Kids Fitness'
];

$user_name = Session::userName(); // for topbar
$page_title = 'Edit Trainer - ' . APP_NAME;
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
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:250px;max-width:250px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:all 0.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-250px}#sidebar .sidebar-header{padding:20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.5rem;font-weight:600}#sidebar ul.components{padding:15px 0}#sidebar ul li a{padding:12px 20px;font-size:0.95rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:20px;text-align:center}#sidebar ul ul a{padding-left:40px!important;font-size:.85rem!important;background:rgba(0,0,0,0.1)}#sidebar .sidebar-footer{padding:15px;border-top:1px solid rgba(255,255,255,0.1)}#content{width:calc(100% - 250px);padding:20px;min-height:100vh;transition:all 0.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:20px;padding:10px 20px}.page-header{padding-bottom:15px;margin:0 0 25px;border-bottom:3px solid #667eea}.page-header h1{font-size:1.8rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:0.9rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:20px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:20px}.stats-card .card-title{font-size:.8rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:5px}.stats-card h2{font-size:1.8rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:2.5rem;opacity:.3;position:absolute;bottom:10px;right:15px}.stats-card small{opacity:.9}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:25px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:15px 20px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333;font-size:1.2rem}.card-header h5 i{color:#667eea;margin-right:8px}.card-body{padding:20px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.75rem;letter-spacing:.5px;padding:12px 8px}.table tbody td{padding:12px 8px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:5px 8px;border-radius:20px;font-weight:500;font-size:.7rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:12px 20px;transition:.3s}.list-group-item:hover{background:#f8f9fa;transform:translateX(5px)}.list-group-item i{color:#667eea;margin-right:10px}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:12px 20px;margin-bottom:20px}.form-label{font-weight:600;color:#495057;margin-bottom:8px}.form-control{border-radius:8px;border:1px solid #e1e5eb;padding:10px 15px;height:auto}.form-control:focus{border-color:#667eea;box-shadow:0 0 0 0.2rem rgba(102,126,234,0.25)}.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border:none;padding:10px 30px;border-radius:8px;font-weight:600}.btn-primary:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(102,126,234,0.4)}@media(max-width:992px){#sidebar{min-width:200px;max-width:200px}#content{width:calc(100% - 200px)}}@media(max-width:768px){#sidebar{margin-left:-200px}#sidebar.active{margin-left:0}#content{width:100%;padding:15px}.page-header h1{font-size:1.5rem}}
    </style>
</head>
<body>
    <!-- Loading Spinner -->
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
                <li class="active">
                    <a href="#trainersSubmenu" data-toggle="collapse" aria-expanded="true">
                        <i class="fas fa-user-tie"></i> Trainers <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse show list-unstyled" id="trainersSubmenu">
                        <li class="active"><a href="../manage_trainers.php"><i class="fas fa-list"></i> All Trainers</a></li>
                        <li><a href="add_trainer.php"><i class="fas fa-user-plus"></i> Add Trainer</a></li>
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
                <li><a href="../payments/manage_payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li><a href="../attendance.php"><i class="fas fa-clock"></i> Attendance</a></li>
                <li><a href="../equipment.php"><i class="fas fa-dumbbell"></i> Equipment</a></li>
                <li><a href="../reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
            <div class="sidebar-footer">
                <a href="#" onclick="confirmLogout(event)" class="btn btn-danger btn-block btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg navbar-custom">
                <button type="button" id="sidebarCollapse" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-bars"></i> Menu
                </button>
                <div class="ml-auto d-flex align-items-center">
                    <!-- Notifications dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-light btn-sm dropdown-toggle" data-toggle="dropdown">
                            <i class="fas fa-bell"></i><span class="badge badge-danger badge-pill">3</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" style="width:280px">
                            <div class="dropdown-header bg-light">Notifications</div>
                            <a class="dropdown-item" href="#"><strong>New member registered</strong><br><small class="text-muted">2 minutes ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Payment received</strong><br><small class="text-muted">1 hour ago</small></a>
                            <a class="dropdown-item" href="#"><strong>5 memberships expiring soon</strong><br><small class="text-muted">3 hours ago</small></a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-center" href="#">View all</a>
                        </div>
                    </div>
                    <!-- User dropdown -->
                    <div class="dropdown ml-2 d-inline-block">
                        <button class="btn btn-light btn-sm dropdown-toggle" data-toggle="dropdown">
                            <i class="fas fa-user-circle fa-lg"></i><span class="ml-1 d-none d-sm-inline"><?php echo htmlspecialchars($user_name); ?></span>
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

            <div class="container-fluid px-0">
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
                    <h1><i class="fas fa-user-edit"></i> Edit Trainer <small><?php echo htmlspecialchars($trainer['full_name']); ?></small></h1>
                    <a href="../manage_trainers.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Trainers
                    </a>
                </div>

                <!-- Edit Form -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-chalkboard-teacher mr-2"></i>Trainer Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="editTrainerForm" class="needs-validation" novalidate>
                            <div class="row">
                                <!-- Personal Information -->
                                <div class="col-12">
                                    <h6 class="text-primary mb-3"><i class="fas fa-user-circle mr-2"></i>Personal Information</h6>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="full_name">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="full_name" name="full_name"
                                           value="<?php echo htmlspecialchars($trainer['full_name']); ?>" required>
                                    <div class="invalid-feedback">Please enter full name</div>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="username">Username</label>
                                    <input type="text" class="form-control" id="username"
                                           value="<?php echo htmlspecialchars($trainer['username']); ?>" readonly disabled>
                                    <small class="text-muted">Username cannot be changed</small>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="email">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo htmlspecialchars($trainer['email']); ?>" required>
                                    <div class="invalid-feedback">Please enter a valid email</div>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                           value="<?php echo htmlspecialchars($trainer['phone'] ?? ''); ?>">
                                </div>

                                <div class="col-12 form-group">
                                    <label for="address">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($trainer['address'] ?? ''); ?></textarea>
                                </div>

                                <!-- Account Status -->
                                <div class="col-md-6 form-group">
                                    <label for="status">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="active" <?php echo $trainer['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $trainer['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>

                                <!-- Password Change Section -->
                                <div class="col-12 mt-3">
                                    <h6 class="text-warning mb-3"><i class="fas fa-lock mr-2"></i>Change Password (leave blank to keep current)</h6>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password"
                                           placeholder="Enter new password">
                                    <small class="text-muted">Minimum 6 characters</small>
                                    <div class="progress mt-2" style="height: 5px;">
                                        <div class="progress-bar" id="passwordStrength" style="width: 0%;"></div>
                                    </div>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                           placeholder="Confirm new password">
                                    <div class="invalid-feedback" id="passwordMatchFeedback" style="display: none;">Passwords do not match</div>
                                </div>

                                <!-- Professional Information -->
                                <div class="col-12 mt-3">
                                    <h6 class="text-primary mb-3"><i class="fas fa-briefcase mr-2"></i>Professional Information</h6>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="specialization">Specialization <span class="text-danger">*</span></label>
                                    <select class="form-control" id="specialization" name="specialization" required>
                                        <option value="">Select Specialization</option>
                                        <?php foreach ($specializations as $spec): ?>
                                            <option value="<?php echo $spec; ?>"
                                                <?php echo ($trainer['specialization'] == $spec) ? 'selected' : ''; ?>>
                                                <?php echo $spec; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select specialization</div>
                                </div>

                                <div class="col-md-3 form-group">
                                    <label for="experience_years">Experience (Years) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="experience_years" name="experience_years"
                                           value="<?php echo $trainer['experience_years'] ?? 0; ?>"
                                           min="0" max="50" step="1" required>
                                    <div class="invalid-feedback">Please enter years of experience</div>
                                </div>

                                <div class="col-md-3 form-group">
                                    <label for="hourly_rate">Hourly Rate (₹) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">₹</span>
                                        </div>
                                        <input type="number" class="form-control" id="hourly_rate" name="hourly_rate"
                                               value="<?php echo $trainer['hourly_rate'] ?? 0; ?>"
                                               min="0" max="1000" step="0.01" required>
                                    </div>
                                    <div class="invalid-feedback">Please enter hourly rate</div>
                                </div>

                                <div class="col-12 form-group">
                                    <label for="qualification">Qualifications</label>
                                    <textarea class="form-control" id="qualification" name="qualification" rows="3"
                                              placeholder="List qualifications, certifications, etc."><?php echo htmlspecialchars($trainer['qualification'] ?? ''); ?></textarea>
                                    <small class="text-muted">Separate multiple qualifications with commas</small>
                                </div>

                                <div class="col-12 form-group">
                                    <label for="availability">Availability</label>
                                    <textarea class="form-control" id="availability" name="availability" rows="2"
                                              placeholder="e.g., Weekdays 9am-5pm, Weekends by appointment"><?php echo htmlspecialchars($trainer['availability'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save mr-2"></i>Update Trainer
                                </button>
                                <a href="../manage_trainers.php" class="btn btn-secondary">
                                    <i class="fas fa-times mr-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Help Card -->
                <div class="card mt-4 bg-light">
                    <div class="card-body">
                        <h6><i class="fas fa-info-circle text-primary mr-2"></i>Editing Tips</h6>
                        <ul class="mb-0 small">
                            <li>Changing email may affect login credentials.</li>
                            <li>Leave password fields blank to keep the current password.</li>
                            <li>Specialization helps members choose the right trainer.</li>
                            <li>Hourly rate is used for payroll calculations.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-sign-out-alt"></i> Confirm Logout</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
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

        // Form validation
        (function() {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();

        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', function() {
            var password = this.value;
            var strength = 0;

            if (password.length >= 6) strength += 25;
            if (password.match(/[a-z]+/)) strength += 25;
            if (password.match(/[A-Z]+/)) strength += 25;
            if (password.match(/[0-9]+/)) strength += 25;

            var bar = document.getElementById('passwordStrength');
            bar.style.width = strength + '%';

            if (strength <= 25) {
                bar.className = 'progress-bar bg-danger';
            } else if (strength <= 50) {
                bar.className = 'progress-bar bg-warning';
            } else if (strength <= 75) {
                bar.className = 'progress-bar bg-info';
            } else {
                bar.className = 'progress-bar bg-success';
            }
        });

        // Password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            var password = document.getElementById('new_password').value;
            var confirm = this.value;
            var feedback = document.getElementById('passwordMatchFeedback');

            if (password !== confirm) {
                this.setCustomValidity('Passwords do not match');
                feedback.style.display = 'block';
            } else {
                this.setCustomValidity('');
                feedback.style.display = 'none';
            }
        });

        // Email validation (optional)
        document.getElementById('email').addEventListener('input', function() {
            var email = this.value;
            var pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (!pattern.test(email) && email.length > 0) {
                this.setCustomValidity('Please enter a valid email address');
            } else {
                this.setCustomValidity('');
            }
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>