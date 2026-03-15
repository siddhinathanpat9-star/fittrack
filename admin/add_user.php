<?php
// admin/add_user.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::requireAdmin();

$functions = new Functions();
$error = '';
$success = '';

// Get trainers for assignment dropdown (for member creation)
$trainers = [];
try {
    $stmt = $pdo->query("SELECT u.id, u.full_name FROM users u JOIN trainers t ON u.id = t.user_id WHERE u.status = 'active' ORDER BY u.full_name");
    $trainers = $stmt->fetchAll();
} catch (Exception $e) {
    $trainers = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $user_type = $_POST['user_type'];
        $status = $_POST['status'] ?? 'active';
        
        $errors = [];
        
        if (empty($username)) $errors[] = "Username is required";
        elseif (strlen($username) < 3) $errors[] = "Username must be at least 3 characters";
        elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) $errors[] = "Username can only contain letters, numbers, and underscores";
        
        if (empty($email)) $errors[] = "Email is required";
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
        
        if (empty($password)) $errors[] = "Password is required";
        elseif (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
        elseif ($password !== $confirm_password) $errors[] = "Passwords do not match";
        
        if (empty($full_name)) $errors[] = "Full name is required";
        
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->rowCount() > 0) $errors[] = "Username or email already exists";
        }
        
        if (!empty($errors)) throw new Exception(implode("<br>", $errors));
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, phone, address, user_type, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt->execute([$username, $email, $hashed_password, $full_name, $phone, $address, $user_type, $status])) throw new Exception("Failed to create user account");
        
        $user_id = $pdo->lastInsertId();
        
        if ($user_type === 'member') {
            $membership_type = $_POST['membership_type'] ?? 'basic';
            $membership_end = !empty($_POST['membership_end']) ? $_POST['membership_end'] : date('Y-m-d', strtotime('+1 month'));
            $assigned_trainer = !empty($_POST['assigned_trainer']) ? $_POST['assigned_trainer'] : null;
            $height = !empty($_POST['height']) ? $_POST['height'] : null;
            $weight = !empty($_POST['weight']) ? $_POST['weight'] : null;
            $fitness_goals = trim($_POST['fitness_goals'] ?? '');
            $emergency_contact = trim($_POST['emergency_contact'] ?? '');
            $emergency_phone = trim($_POST['emergency_phone'] ?? '');
            
            $stmt = $pdo->query("SHOW TABLES LIKE 'members'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("INSERT INTO members (user_id, membership_type, membership_start, membership_end, assigned_trainer_id, height, weight, fitness_goals, emergency_contact, emergency_phone) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $membership_type, $membership_end, $assigned_trainer, $height, $weight, $fitness_goals, $emergency_contact, $emergency_phone]);
            }
        } elseif ($user_type === 'trainer') {
            $specialization = trim($_POST['specialization'] ?? '');
            $experience_years = (int)($_POST['experience_years'] ?? 0);
            $hourly_rate = (float)($_POST['hourly_rate'] ?? 0);
            $qualification = trim($_POST['qualification'] ?? '');
            $availability = trim($_POST['availability'] ?? '');
            
            $stmt = $pdo->query("SHOW TABLES LIKE 'trainers'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("INSERT INTO trainers (user_id, specialization, experience_years, hourly_rate, qualification, availability) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $specialization, $experience_years, $hourly_rate, $qualification, $availability]);
            }
        }
        
        $pdo->commit();
        
        if (method_exists($functions, 'logActivity')) $functions->logActivity(Session::userId(), 'add_user', "Added new $user_type: $full_name");
        
        Session::setFlash('success', ucfirst($user_type) . ' added successfully!');
        header('Location: manage_users.php');
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$user_name = Session::userName();
$page_title = 'Add User - ' . APP_NAME;
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
    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-dumbbell fa-3x mb-3"></i>
            <h3><?php echo APP_NAME; ?></h3>
            <p>Administrator Panel</p>
        </div>
        <ul class="list-unstyled components">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li>
                <a href="#membersSubmenu" data-toggle="collapse"><i class="fas fa-users"></i> Members <i class="fas fa-chevron-down float-right"></i></a>
                <ul class="collapse list-unstyled" id="membersSubmenu">
                    <li><a href="members/manage_members.php"><i class="fas fa-list"></i> All Members</a></li>
                    <li><a href="members/add_member.php"><i class="fas fa-user-plus"></i> Add Member</a></li>
                    <li><a href="membership/membership_plans.php"><i class="fas fa-tag"></i> Membership Plans</a></li>
                </ul>
            </li>
            <li>
                <a href="#trainersSubmenu" data-toggle="collapse"><i class="fas fa-chalkboard-teacher"></i> Trainers <i class="fas fa-chevron-down float-right"></i></a>
                <ul class="collapse list-unstyled" id="trainersSubmenu">
                    <li><a href="manage_trainers.php"><i class="fas fa-list"></i> All Trainers</a></li>
                    <li><a href="add_trainer.php"><i class="fas fa-user-plus"></i> Add Trainer</a></li>
                </ul>
            </li>
            <li>
                <a href="#classesSubmenu" data-toggle="collapse"><i class="fas fa-calendar-alt"></i> Classes <i class="fas fa-chevron-down float-right"></i></a>
                <ul class="collapse list-unstyled" id="classesSubmenu">
                    <li><a href="classes/manage_classes.php"><i class="fas fa-list"></i> All Classes</a></li>
                    <li><a href="classes/add_class.php"><i class="fas fa-plus-circle"></i> Add Class</a></li>
                    <li><a href="classes/class_schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
                </ul>
            </li>
            <li><a href="payments/manage_payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li><a href="attendance.php"><i class="fas fa-clock"></i> Attendance</a></li>
            <li><a href="equipment.php"><i class="fas fa-dumbbell"></i> Equipment</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li class="active"><a href="manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="notifications/send_notification.php"><i class="fas fa-bell"></i> Notifications</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
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
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" data-toggle="dropdown"><i class="fas fa-bell"></i><span class="badge badge-danger badge-pill">3</span></button>
                    <div class="dropdown-menu dropdown-menu-right" style="width:300px">
                        <div class="dropdown-header bg-light">Notifications</div>
                        <a class="dropdown-item" href="#"><strong>New member registered</strong><br><small class="text-muted">2 minutes ago</small></a>
                        <a class="dropdown-item" href="#"><strong>Payment received</strong><br><small class="text-muted">1 hour ago</small></a>
                        <a class="dropdown-item" href="#"><strong>5 memberships expiring soon</strong><br><small class="text-muted">3 hours ago</small></a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-center" href="notifications/manage_notifications.php">View all</a>
                    </div>
                </div>
                <div class="dropdown ml-3">
                    <button class="btn btn-light dropdown-toggle" data-toggle="dropdown"><i class="fas fa-user-circle fa-lg"></i><span class="ml-2"><?php echo htmlspecialchars($user_name); ?></span></button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                        <a class="dropdown-item" href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </nav>

        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-user-plus"></i> Add New User <small>Create a new user account</small></h1>
            </div>

            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>
            <?php Session::displayFlash(); ?>

            <!-- Form Card -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-user-edit"></i> User Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="addUserForm" class="needs-validation" novalidate>
                        <!-- Basic Information -->
                        <div class="row">
                            <div class="col-12"><h6 class="text-primary">Basic Information</h6><hr></div>
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" placeholder="Enter full name" required>
                                <div class="invalid-feedback">Please enter full name</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="Enter username" required>
                                <div class="invalid-feedback">Please enter username</div>
                                <small class="text-muted">Letters, numbers, and underscores only</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="Enter email address" required>
                                <div class="invalid-feedback">Please enter a valid email</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="Enter phone number">
                            </div>
                            <div class="col-12 mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2" placeholder="Enter full address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- Account Information -->
                        <div class="row mt-3">
                            <div class="col-12"><h6 class="text-primary">Account Information</h6><hr></div>
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
                                <div class="invalid-feedback">Please enter password</div>
                                <small class="text-muted">Minimum 6 characters</small>
                                <div class="progress mt-2" style="height:5px"><div class="progress-bar" id="passwordStrength" style="width:0%"></div></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
                                <div class="invalid-feedback">Passwords do not match</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="user_type" class="form-label">User Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="user_type" name="user_type" required onchange="toggleUserTypeFields()">
                                    <option value="">Select User Type</option>
                                    <option value="member" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'member') ? 'selected' : ''; ?>>Member</option>
                                    <option value="trainer" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'trainer') ? 'selected' : ''; ?>>Trainer</option>
                                    <option value="admin" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                </select>
                                <div class="invalid-feedback">Please select user type</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <!-- Member Specific Fields -->
                        <div id="member_fields" style="display:none;">
                            <div class="row mt-3">
                                <div class="col-12"><h6 class="text-success">Member Details</h6><hr></div>
                                <div class="col-md-4 mb-3">
                                    <label for="membership_type" class="form-label">Membership Type</label>
                                    <select class="form-control" id="membership_type" name="membership_type">
                                        <option value="basic" selected>Basic</option>
                                        <option value="premium">Premium</option>
                                        <option value="vip">VIP</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="membership_end" class="form-label">Membership End Date</label>
                                    <input type="date" class="form-control" id="membership_end" name="membership_end" value="<?php echo date('Y-m-d', strtotime('+1 month')); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="assigned_trainer" class="form-label">Assign Trainer</label>
                                    <select class="form-control" id="assigned_trainer" name="assigned_trainer">
                                        <option value="">None</option>
                                        <?php foreach ($trainers as $trainer): ?>
                                            <option value="<?php echo $trainer['id']; ?>"><?php echo htmlspecialchars($trainer['full_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="height" class="form-label">Height (cm)</label>
                                    <input type="number" step="0.01" class="form-control" id="height" name="height" value="<?php echo htmlspecialchars($_POST['height'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="weight" class="form-label">Weight (kg)</label>
                                    <input type="number" step="0.01" class="form-control" id="weight" name="weight" value="<?php echo htmlspecialchars($_POST['weight'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="fitness_goals" class="form-label">Fitness Goals</label>
                                    <textarea class="form-control" id="fitness_goals" name="fitness_goals" rows="2"><?php echo htmlspecialchars($_POST['fitness_goals'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="emergency_contact" class="form-label">Emergency Contact Name</label>
                                    <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" value="<?php echo htmlspecialchars($_POST['emergency_contact'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="emergency_phone" class="form-label">Emergency Contact Phone</label>
                                    <input type="tel" class="form-control" id="emergency_phone" name="emergency_phone" value="<?php echo htmlspecialchars($_POST['emergency_phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Trainer Specific Fields -->
                        <div id="trainer_fields" style="display:none;">
                            <div class="row mt-3">
                                <div class="col-12"><h6 class="text-warning">Trainer Details</h6><hr></div>
                                <div class="col-md-6 mb-3">
                                    <label for="specialization" class="form-label">Specialization</label>
                                    <input type="text" class="form-control" id="specialization" name="specialization" value="<?php echo htmlspecialchars($_POST['specialization'] ?? ''); ?>" placeholder="e.g., Weight Training, Yoga, Cardio">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="experience_years" class="form-label">Experience (Years)</label>
                                    <input type="number" class="form-control" id="experience_years" name="experience_years" value="<?php echo htmlspecialchars($_POST['experience_years'] ?? '0'); ?>" min="0" step="1">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="hourly_rate" class="form-label">Hourly Rate ($)</label>
                                    <input type="number" class="form-control" id="hourly_rate" name="hourly_rate" value="<?php echo htmlspecialchars($_POST['hourly_rate'] ?? '0'); ?>" min="0" step="0.01">
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="qualification" class="form-label">Qualifications</label>
                                    <textarea class="form-control" id="qualification" name="qualification" rows="2"><?php echo htmlspecialchars($_POST['qualification'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="availability" class="form-label">Availability</label>
                                    <textarea class="form-control" id="availability" name="availability" rows="2" placeholder="e.g., Weekdays 9am-5pm, Weekends by appointment"><?php echo htmlspecialchars($_POST['availability'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save mr-2"></i>Create User</button>
                            <a href="manage_users.php" class="btn btn-secondary btn-lg"><i class="fas fa-times mr-2"></i>Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tips Card -->
            <div class="card mt-4 bg-light">
                <div class="card-body">
                    <h6><i class="fas fa-info-circle text-primary mr-2"></i>User Creation Tips</h6>
                    <ul class="mb-0 small">
                        <li>Username must be unique and can only contain letters, numbers, and underscores</li>
                        <li>Password must be at least 6 characters long</li>
                        <li>Email will be used for notifications and password recovery</li>
                        <li>Members get automatic 1-month membership if not specified</li>
                        <li>You can assign trainers to members during creation</li>
                    </ul>
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
            <div class="modal-body"><p>Are you sure you want to logout?</p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times"></i> Cancel</button>
                <a href="../public/logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
$(document).ready(function() {
    $('#sidebarCollapse').on('click', function() { $('#sidebar').toggleClass('active'); });
    setTimeout(function() { $('.alert').fadeOut('slow'); }, 5000);
});

function confirmLogout(e) { e.preventDefault(); $('#logoutModal').modal('show'); }

function toggleUserTypeFields() {
    var userType = document.getElementById('user_type').value;
    document.getElementById('member_fields').style.display = userType === 'member' ? 'block' : 'none';
    document.getElementById('trainer_fields').style.display = userType === 'trainer' ? 'block' : 'none';
}

// Form validation
(function() {
    'use strict';
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
            form.classList.add('was-validated');
        }, false);
    });
})();

// Password strength
document.getElementById('password')?.addEventListener('input', function() {
    var pwd = this.value, strength = 0;
    if (pwd.length >= 6) strength += 20;
    if (/[a-z]/.test(pwd)) strength += 20;
    if (/[A-Z]/.test(pwd)) strength += 20;
    if (/[0-9]/.test(pwd)) strength += 20;
    if (/[$@#&!]/.test(pwd)) strength += 20;
    var bar = document.getElementById('passwordStrength');
    if (bar) {
        bar.style.width = strength + '%';
        bar.className = strength <= 20 ? 'progress-bar bg-danger' : strength <= 40 ? 'progress-bar bg-warning' : strength <= 60 ? 'progress-bar bg-info' : 'progress-bar bg-success';
    }
});

// Password match
document.getElementById('confirm_password')?.addEventListener('input', function() {
    var pwd = document.getElementById('password').value;
    this.setCustomValidity(pwd !== this.value ? 'Passwords do not match' : '');
});

// Username validation
document.getElementById('username')?.addEventListener('input', function() {
    var username = this.value;
    this.setCustomValidity(username && !/^[a-zA-Z0-9_]+$/.test(username) ? 'Username can only contain letters, numbers, and underscores' : '');
});

// Email validation
document.getElementById('email')?.addEventListener('input', function() {
    var email = this.value;
    this.setCustomValidity(email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? 'Please enter a valid email address' : '');
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() { toggleUserTypeFields(); });

// Prevent form resubmission on refresh
if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);
</script>
</body>
</html>