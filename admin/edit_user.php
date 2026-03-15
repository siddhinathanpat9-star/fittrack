<?php
// admin/edit_user.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Path to includes (one level up)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure user is admin
Session::requireAdmin();

$functions = new Functions();
$error = '';
$success = '';

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
    Session::setFlash('danger', 'Invalid user ID.');
    header('Location: manage_users.php');
    exit();
}

// Fetch user data
$user = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    Session::setFlash('danger', 'Database error: ' . $e->getMessage());
    header('Location: manage_users.php');
    exit();
}

if (!$user) {
    Session::setFlash('danger', 'User not found.');
    header('Location: manage_users.php');
    exit();
}

// Fetch role-specific data
$member_data = [];
$trainer_data = [];
if ($user['user_type'] === 'member') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM members WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $member_data = $stmt->fetch() ?: [];
    } catch (Exception $e) {
        // table might not exist
    }
} elseif ($user['user_type'] === 'trainer') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM trainers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $trainer_data = $stmt->fetch() ?: [];
    } catch (Exception $e) {
        // table might not exist
    }
}

// Get all trainers for assignment (if editing a member)
$trainers = [];
if ($user['user_type'] === 'member') {
    try {
        $stmt = $pdo->query("SELECT u.id, u.full_name FROM users u JOIN trainers t ON u.id = t.user_id WHERE u.status = 'active' ORDER BY u.full_name");
        $trainers = $stmt->fetchAll();
    } catch (Exception $e) {
        // ignore
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    try {
        $pdo->beginTransaction();

        // Basic info
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $status = $_POST['status'];

        // Validate
        if (empty($full_name)) throw new Exception("Full name is required.");
        if (empty($email)) throw new Exception("Email is required.");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("Invalid email format.");

        // Check email uniqueness (exclude current user)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) throw new Exception("Email already in use by another user.");

        // Update users table
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, status = ? WHERE id = ?");
        $stmt->execute([$full_name, $email, $phone, $address, $status, $user_id]);

        // Optional password change
        if (!empty($_POST['new_password'])) {
            if (strlen($_POST['new_password']) < 6) throw new Exception("Password must be at least 6 characters.");
            if ($_POST['new_password'] !== $_POST['confirm_password']) throw new Exception("Passwords do not match.");
            $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $user_id]);
        }

        // Update role-specific data
        if ($user['user_type'] === 'member') {
            $membership_type = $_POST['membership_type'] ?? 'basic';
            $membership_end = $_POST['membership_end'] ?: null;
            $assigned_trainer = !empty($_POST['assigned_trainer']) ? $_POST['assigned_trainer'] : null;
            $height = !empty($_POST['height']) ? (float)$_POST['height'] : null;
            $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
            $fitness_goals = trim($_POST['fitness_goals'] ?? '');
            $emergency_contact = trim($_POST['emergency_contact'] ?? '');
            $emergency_phone = trim($_POST['emergency_phone'] ?? '');

            if (empty($member_data)) {
                // Insert new member record (should not happen normally)
                $stmt = $pdo->prepare("INSERT INTO members (user_id, membership_type, membership_end, assigned_trainer_id, height, weight, fitness_goals, emergency_contact, emergency_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $membership_type, $membership_end, $assigned_trainer, $height, $weight, $fitness_goals, $emergency_contact, $emergency_phone]);
            } else {
                // Update
                $stmt = $pdo->prepare("UPDATE members SET membership_type = ?, membership_end = ?, assigned_trainer_id = ?, height = ?, weight = ?, fitness_goals = ?, emergency_contact = ?, emergency_phone = ? WHERE user_id = ?");
                $stmt->execute([$membership_type, $membership_end, $assigned_trainer, $height, $weight, $fitness_goals, $emergency_contact, $emergency_phone, $user_id]);
            }
        } elseif ($user['user_type'] === 'trainer') {
            $specialization = trim($_POST['specialization'] ?? '');
            $experience_years = (int)($_POST['experience_years'] ?? 0);
            $hourly_rate = (float)($_POST['hourly_rate'] ?? 0);
            $qualification = trim($_POST['qualification'] ?? '');
            $availability = trim($_POST['availability'] ?? '');

            if (empty($trainer_data)) {
                $stmt = $pdo->prepare("INSERT INTO trainers (user_id, specialization, experience_years, hourly_rate, qualification, availability) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $specialization, $experience_years, $hourly_rate, $qualification, $availability]);
            } else {
                $stmt = $pdo->prepare("UPDATE trainers SET specialization = ?, experience_years = ?, hourly_rate = ?, qualification = ?, availability = ? WHERE user_id = ?");
                $stmt->execute([$specialization, $experience_years, $hourly_rate, $qualification, $availability, $user_id]);
            }
        }

        $pdo->commit();
        Session::setFlash('success', 'User updated successfully.');
        header("Location: edit_user.php?id=$user_id");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$page_title = 'Edit User - ' . APP_NAME;
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
    <!-- DataTables (optional) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
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
        .page-header .btn { float: right; }
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
        .form-group { margin-bottom: 1.5rem; }
        .form-label { font-weight: 600; color: #555; }
        .form-control {
            border: 2px solid #e0e0e0; border-radius: 10px; padding: 0.6rem 1rem;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea; box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none; border-radius: 30px; padding: 0.6rem 2rem;
            font-weight: 600; transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        .btn-outline-secondary {
            border: 2px solid #e0e0e0; color: #666; border-radius: 30px;
            padding: 0.5rem 1.5rem; font-weight: 500; transition: all 0.3s;
        }
        .btn-outline-secondary:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: transparent; color: #fff; transform: translateY(-2px);
        }
        .alert { border: none; border-radius: 10px; padding: 15px 20px; margin-bottom: 30px; }
        .loading-spinner {
            display: none; position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%); z-index: 9999;
        }
        .loading-spinner.active { display: block; }
        .spinner-border { width: 3rem; height: 3rem; color: #667eea; }
        @media (max-width: 768px) {
            #sidebar { margin-left: -280px; }
            #sidebar.active { margin-left: 0; }
            #content { padding: 20px; }
        }
        /* Password strength bar */
        .password-strength { height: 5px; margin-top: 5px; border-radius: 5px; }
        .bg-danger { background-color: #dc3545; }
        .bg-warning { background-color: #ffc107; }
        .bg-info { background-color: #17a2b8; }
        .bg-success { background-color: #28a745; }
    </style>
</head>
<body>
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div>
    </div>
    <div class="wrapper">
        <!-- Sidebar (same as member dashboard, but with admin menu) -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Admin Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="manage_members.php"><i class="fas fa-user"></i> Members</a></li>
                <li><a href="manage_trainers.php"><i class="fas fa-chalkboard-teacher"></i> Trainers</a></li>
                <li><a href="classes/manage_classes.php"><i class="fas fa-calendar-alt"></i> Classes</a></li>
                <li><a href="payments/manage_payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li><a href="membership/membership_plans.php"><i class="fas fa-id-card"></i> Membership Plans</a></li>
                <li><a href="notifications/send_notification.php"><i class="fas fa-bell"></i> Notifications</a></li>
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
                            <span class="ml-2"><?php echo htmlspecialchars(Session::userName()); ?></span>
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
                <?php Session::displayFlash(); ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>

                <div class="page-header">
                    <h1><i class="fas fa-user-edit"></i> Edit User: <?php echo htmlspecialchars($user['full_name']); ?></h1>
                    <a href="manage_users.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Users</a>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-user-circle"></i> Basic Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" id="editUserForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Email *</label>
                                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Phone</label>
                                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" readonly disabled>
                                        <small class="text-muted">Username cannot be changed.</small>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label class="form-label">Address</label>
                                        <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-control">
                                            <option value="active" <?php echo $user['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $user['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">User Type</label>
                                        <input type="text" class="form-control" value="<?php echo ucfirst($user['user_type']); ?>" readonly disabled>
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <h5 class="text-warning"><i class="fas fa-lock"></i> Change Password (leave blank to keep current)</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">New Password</label>
                                        <input type="password" name="new_password" id="new_password" class="form-control">
                                        <div class="password-strength progress mt-2" style="height:5px;">
                                            <div class="progress-bar" id="passwordStrength" style="width:0%;"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Confirm Password</label>
                                        <input type="password" name="confirm_password" id="confirm_password" class="form-control">
                                        <small class="text-danger" id="passwordMatchError" style="display:none;">Passwords do not match.</small>
                                    </div>
                                </div>
                            </div>

                            <?php if ($user['user_type'] === 'member'): ?>
                                <hr>
                                <h5 class="text-success"><i class="fas fa-id-card"></i> Member Details</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Membership Type</label>
                                            <select name="membership_type" class="form-control">
                                                <option value="basic" <?php echo ($member_data['membership_type'] ?? 'basic') == 'basic' ? 'selected' : ''; ?>>Basic</option>
                                                <option value="premium" <?php echo ($member_data['membership_type'] ?? '') == 'premium' ? 'selected' : ''; ?>>Premium</option>
                                                <option value="vip" <?php echo ($member_data['membership_type'] ?? '') == 'vip' ? 'selected' : ''; ?>>VIP</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Membership End Date</label>
                                            <input type="date" name="membership_end" class="form-control" value="<?php echo $member_data['membership_end'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Assign Trainer</label>
                                            <select name="assigned_trainer" class="form-control">
                                                <option value="">None</option>
                                                <?php foreach ($trainers as $t): ?>
                                                    <option value="<?php echo $t['id']; ?>" <?php echo ($member_data['assigned_trainer_id'] ?? '') == $t['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($t['full_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Height (cm)</label>
                                            <input type="number" step="0.1" name="height" class="form-control" value="<?php echo htmlspecialchars($member_data['height'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Weight (kg)</label>
                                            <input type="number" step="0.1" name="weight" class="form-control" value="<?php echo htmlspecialchars($member_data['weight'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Fitness Goals</label>
                                            <textarea name="fitness_goals" class="form-control" rows="2"><?php echo htmlspecialchars($member_data['fitness_goals'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Emergency Contact</label>
                                            <input type="text" name="emergency_contact" class="form-control" value="<?php echo htmlspecialchars($member_data['emergency_contact'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Emergency Phone</label>
                                            <input type="tel" name="emergency_phone" class="form-control" value="<?php echo htmlspecialchars($member_data['emergency_phone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($user['user_type'] === 'trainer'): ?>
                                <hr>
                                <h5 class="text-warning"><i class="fas fa-chalkboard-teacher"></i> Trainer Details</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Specialization</label>
                                            <input type="text" name="specialization" class="form-control" value="<?php echo htmlspecialchars($trainer_data['specialization'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Experience (Years)</label>
                                            <input type="number" name="experience_years" class="form-control" value="<?php echo $trainer_data['experience_years'] ?? 0; ?>" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Hourly Rate ($)</label>
                                            <input type="number" step="0.01" name="hourly_rate" class="form-control" value="<?php echo $trainer_data['hourly_rate'] ?? 0; ?>" min="0">
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label class="form-label">Qualifications</label>
                                            <textarea name="qualification" class="form-control" rows="2"><?php echo htmlspecialchars($trainer_data['qualification'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label class="form-label">Availability</label>
                                            <textarea name="availability" class="form-control" rows="2"><?php echo htmlspecialchars($trainer_data['availability'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <hr>
                            <div class="d-flex justify-content-between">
                                <button type="submit" name="update_user" class="btn btn-primary"><i class="fas fa-save"></i> Update User</button>
                                <a href="manage_users.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i> Cancel</a>
                            </div>
                        </form>
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
    <script>
        $(document).ready(function() {
            $('#sidebarCollapse').on('click', function() {
                $('#sidebar').toggleClass('active');
            });

            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);

            // Password strength meter
            $('#new_password').on('input', function() {
                var password = $(this).val();
                var strength = 0;
                if (password.length >= 6) strength += 20;
                if (password.match(/[a-z]+/)) strength += 20;
                if (password.match(/[A-Z]+/)) strength += 20;
                if (password.match(/[0-9]+/)) strength += 20;
                if (password.match(/[$@#&!]+/)) strength += 20;

                var bar = $('#passwordStrength');
                bar.css('width', strength + '%');
                if (strength <= 20) bar.removeClass().addClass('progress-bar bg-danger');
                else if (strength <= 40) bar.removeClass().addClass('progress-bar bg-warning');
                else if (strength <= 60) bar.removeClass().addClass('progress-bar bg-info');
                else bar.removeClass().addClass('progress-bar bg-success');
            });

            // Password match validation
            $('#confirm_password').on('input', function() {
                var pass = $('#new_password').val();
                var confirm = $(this).val();
                if (pass !== confirm) {
                    $(this).addClass('is-invalid');
                    $('#passwordMatchError').show();
                } else {
                    $(this).removeClass('is-invalid');
                    $('#passwordMatchError').hide();
                }
            });
        });

        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }
    </script>
</body>
</html>