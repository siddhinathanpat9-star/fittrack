<?php
// trainer/profile.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

$root_path = dirname(__DIR__);
require_once $root_path . '/includes/config.php';
require_once $root_path . '/includes/session.php';
require_once $root_path . '/includes/functions.php';

// Check if user is trainer or admin
if (!Session::isTrainer() && !Session::isAdmin()) {
    Session::setFlash('danger', 'Access denied. Trainer login required.');
    header('Location: ' . $root_path . '/login.php');
    exit();
}

$trainer_id = Session::userId();
$user_name = Session::userName();
$functions = new Functions();
$error = '';
$success = '';

// Fetch current trainer data
$trainer = null;
try {
    $stmt = $pdo->prepare("
        SELECT u.*, t.specialization, t.experience_years, t.hourly_rate,
               t.qualification, t.availability
        FROM users u
        LEFT JOIN trainers t ON u.id = t.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$trainer_id]);
    $trainer = $stmt->fetch();
} catch (Exception $e) {
    $error = "Error loading profile: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Collect form data
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $specialization = trim($_POST['specialization']);
    $experience_years = (int)$_POST['experience_years'];
    $hourly_rate = (float)$_POST['hourly_rate'];
    $qualification = trim($_POST['qualification']);
    $availability = trim($_POST['availability']);

    // Optional password change
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    $errors = [];
    if (empty($full_name)) $errors[] = "Full name is required.";
    if (empty($email)) $errors[] = "Email is required.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";

    // Check if email already used by another user
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $trainer_id]);
        if ($stmt->fetch()) $errors[] = "Email already in use by another account.";
    }

    if ($experience_years < 0) $errors[] = "Experience years cannot be negative.";
    if ($hourly_rate < 0) $errors[] = "Hourly rate cannot be negative.";

    if (!empty($new_password)) {
        if (strlen($new_password) < 6) $errors[] = "Password must be at least 6 characters.";
        if ($new_password !== $confirm_password) $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Update users table
            $stmt = $pdo->prepare("
                UPDATE users
                SET full_name = ?, email = ?, phone = ?, address = ?
                WHERE id = ?
            ");
            $stmt->execute([$full_name, $email, $phone, $address, $trainer_id]);

            // Update password if provided
            if (!empty($new_password)) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed, $trainer_id]);
            }

            // Update trainers table (insert if not exists)
            $stmt = $pdo->prepare("
                INSERT INTO trainers (user_id, specialization, experience_years, hourly_rate, qualification, availability)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    specialization = VALUES(specialization),
                    experience_years = VALUES(experience_years),
                    hourly_rate = VALUES(hourly_rate),
                    qualification = VALUES(qualification),
                    availability = VALUES(availability)
            ");
            $stmt->execute([
                $trainer_id,
                $specialization,
                $experience_years,
                $hourly_rate,
                $qualification,
                $availability
            ]);

            $pdo->commit();
            $success = "Profile updated successfully.";

            // Refresh data
            $stmt = $pdo->prepare("SELECT u.*, t.specialization, t.experience_years, t.hourly_rate, t.qualification, t.availability FROM users u LEFT JOIN trainers t ON u.id = t.user_id WHERE u.id = ?");
            $stmt->execute([$trainer_id]);
            $trainer = $stmt->fetch();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error updating profile: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

$page_title = 'My Profile - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 4, Font Awesome -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Dashboard styles from reference */
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}}
        /* Form styling */
        .form-label{font-weight:600;color:#495057;margin-bottom:8px}
        .form-control{border-radius:8px;border:1px solid #e1e5eb;padding:10px 15px;height:auto}
        .form-control:focus{border-color:#667eea;box-shadow:0 0 0 0.2rem rgba(102,126,234,0.25)}
        .btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border:none;padding:10px 30px;border-radius:8px;font-weight:600}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(102,126,234,0.4)}
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
                <p>Trainer Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="my_members.php"><i class="fas fa-users"></i> My Members</a></li>
                <li><a href="my_classes.php"><i class="fas fa-calendar-alt"></i> My Classes</a></li>
                <li><a href="attendance.php"><i class="fas fa-clock"></i> Attendance</a></li>
                <li><a href="workout_plans.php"><i class="fas fa-dumbbell"></i> Workout Plans</a></li>
                <li class="active"><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
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
                            <a class="dropdown-item" href="#"><strong>New member assigned</strong><br><small class="text-muted">2 minutes ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Class schedule updated</strong><br><small class="text-muted">1 hour ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Workout plan completed</strong><br><small class="text-muted">3 hours ago</small></a>
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
                            <a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid">
                <!-- Flash messages from session -->
                <?php Session::displayFlash(); ?>

                <!-- Error / Success alerts -->
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
                    <h1><i class="fas fa-user-circle"></i> My Profile <small>View and edit your information</small></h1>
                </div>

                <!-- Profile Form Card -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-user-edit mr-2"></i>Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="full_name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name"
                                           value="<?php echo htmlspecialchars($trainer['full_name'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo htmlspecialchars($trainer['email'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="text" class="form-control" id="phone" name="phone"
                                           value="<?php echo htmlspecialchars($trainer['phone'] ?? ''); ?>">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="address" class="form-label">Address</label>
                                    <input type="text" class="form-control" id="address" name="address"
                                           value="<?php echo htmlspecialchars($trainer['address'] ?? ''); ?>">
                                </div>
                            </div>

                            <hr class="my-4">
                            <h5 class="text-primary"><i class="fas fa-briefcase mr-2"></i>Professional Details</h5>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="specialization" class="form-label">Specialization</label>
                                    <input type="text" class="form-control" id="specialization" name="specialization"
                                           value="<?php echo htmlspecialchars($trainer['specialization'] ?? ''); ?>">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="experience_years" class="form-label">Experience (years)</label>
                                    <input type="number" class="form-control" id="experience_years" name="experience_years"
                                           value="<?php echo $trainer['experience_years'] ?? 0; ?>" min="0">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="hourly_rate" class="form-label">Hourly Rate (₹)</label>
                                    <input type="number" step="0.01" class="form-control" id="hourly_rate" name="hourly_rate"
                                           value="<?php echo $trainer['hourly_rate'] ?? 0; ?>" min="0">
                                </div>
                                <div class="form-group col-12">
                                    <label for="qualification" class="form-label">Qualifications</label>
                                    <textarea class="form-control" id="qualification" name="qualification" rows="3"><?php echo htmlspecialchars($trainer['qualification'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group col-12">
                                    <label for="availability" class="form-label">Availability</label>
                                    <textarea class="form-control" id="availability" name="availability" rows="2"><?php echo htmlspecialchars($trainer['availability'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <hr class="my-4">
                            <h5 class="text-warning"><i class="fas fa-lock mr-2"></i>Change Password (leave blank to keep current)</h5>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>

                            <div class="text-center">
                                <button type="submit" name="update_profile" class="btn btn-primary btn-lg px-5">
                                    <i class="fas fa-save mr-2"></i>Update Profile
                                </button>
                            </div>
                        </form>
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

        // Password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            var pass = document.getElementById('new_password').value;
            var confirm = this.value;
            if (pass !== confirm) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>