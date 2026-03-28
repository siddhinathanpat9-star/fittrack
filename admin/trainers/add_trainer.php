<?php
// admin/trainers/add_trainer.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Fix the path - go up two levels to root includes folder
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

// Now use the Session class
Session::requireAdmin();

// Initialize functions
$functions = new Functions();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Get form data
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $specialization = trim($_POST['specialization']);
        $experience_years = (int)$_POST['experience_years'];
        $hourly_rate = (float)$_POST['hourly_rate'];
        $qualification = trim($_POST['qualification']);
        $availability = trim($_POST['availability']);
        $status = $_POST['status'] ?? 'active';
        
        // Validation
        $errors = [];
        
        if (empty($username)) {
            $errors[] = "Username is required";
        } elseif (strlen($username) < 3) {
            $errors[] = "Username must be at least 3 characters";
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = "Username can only contain letters, numbers, and underscores";
        }
        
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        if (empty($password)) {
            $errors[] = "Password is required";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        } elseif ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
        
        if (empty($full_name)) {
            $errors[] = "Full name is required";
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
        
        // Check if username or email already exists
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->rowCount() > 0) {
                $errors[] = "Username or email already exists";
            }
        }
        
        if (!empty($errors)) {
            throw new Exception(implode("<br>", $errors));
        }
        
        // Create user
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, full_name, phone, address, user_type, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'trainer', ?, NOW())
        ");
        
        if (!$stmt->execute([$username, $email, $hashed_password, $full_name, $phone, $address, $status])) {
            throw new Exception("Failed to create user account");
        }
        
        $user_id = $pdo->lastInsertId();
        
        // Create trainer record
        $stmt = $pdo->prepare("
            INSERT INTO trainers (user_id, specialization, experience_years, hourly_rate, qualification, availability) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt->execute([$user_id, $specialization, $experience_years, $hourly_rate, $qualification, $availability])) {
            throw new Exception("Failed to create trainer record");
        }
        
        $pdo->commit();
        
        // Log the activity
        $functions->logActivity(Session::userId(), 'add_trainer', "Added new trainer: $full_name");
        
        Session::setFlash('success', 'Trainer added successfully!');
        header('Location: manage_trainers.php');
        exit();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Get specialization options (can be from database or predefined list)
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

$user_name = Session::userName();
$page_title = 'Add Trainer - ' . APP_NAME;
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
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}.btn-primary{background:#667eea;border-color:#667eea}.btn-primary:hover{background:#5a67d8;border-color:#5a67d8}.form-group{margin-bottom:1rem}.form-control{border-radius:10px;border:1px solid #e2e8f0;padding:10px 15px}.form-control:focus{border-color:#667eea;box-shadow:0 0 0 0.2rem rgba(102,126,234,0.25)}.progress{height:5px;border-radius:5px}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}}
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
                <li class="active">
                    <a href="#trainersSubmenu" data-toggle="collapse" aria-expanded="true">
                        <i class="fas fa-user-tie"></i> Trainers <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse show list-unstyled" id="trainersSubmenu">
                        <li><a href="../manage_trainers.php"><i class="fas fa-list"></i> All Trainers</a></li>
                        <li class="active"><a href="add_trainer.php"><i class="fas fa-user-plus"></i> Add Trainer</a></li>
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
                <li><a href="../payments/manage_payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
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
                            <a class="dropdown-item" href="#"><strong>New trainer added</strong><br><small class="text-muted">2 minutes ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Class scheduled</strong><br><small class="text-muted">1 hour ago</small></a>
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
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php Session::displayFlash(); ?>

                <!-- Page Header -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header">
                            <h1><i class="fas fa-user-plus"></i> Add New Trainer <small>Create a new trainer account</small></h1>
                        </div>
                    </div>
                </div>

                <!-- Add Trainer Form Card -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chalkboard-teacher"></i> Trainer Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="addTrainerForm" class="needs-validation" novalidate>
                            <div class="row">
                                <!-- Personal Information -->
                                <div class="col-12">
                                    <h6 class="text-primary mb-3"><i class="fas fa-user-circle mr-2"></i>Personal Information</h6>
                                </div>
                                
                                <div class="col-md-6 form-group">
                                    <label for="full_name">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                                           placeholder="Enter full name" required>
                                    <div class="invalid-feedback">Please enter full name</div>
                                </div>
                                
                                <div class="col-md-6 form-group">
                                    <label for="username">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                           placeholder="Enter username" required>
                                    <div class="invalid-feedback">Please enter username</div>
                                    <small class="text-muted">Letters, numbers, and underscores only</small>
                                </div>
                                
                                <div class="col-md-6 form-group">
                                    <label for="email">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           placeholder="Enter email address" required>
                                    <div class="invalid-feedback">Please enter a valid email</div>
                                </div>
                                
                                <div class="col-md-6 form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                                           placeholder="Enter phone number">
                                </div>
                                
                                <div class="col-12 form-group">
                                    <label for="address">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2" 
                                              placeholder="Enter full address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                                </div>
                                
                                <!-- Account Information -->
                                <div class="col-12 mt-3">
                                    <h6 class="text-primary mb-3"><i class="fas fa-lock mr-2"></i>Account Information</h6>
                                </div>
                                
                                <div class="col-md-6 form-group">
                                    <label for="password">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Enter password" required>
                                    <div class="invalid-feedback">Please enter password</div>
                                    <small class="text-muted">Minimum 6 characters</small>
                                    <div class="progress mt-2" style="height: 5px;">
                                        <div class="progress-bar" id="passwordStrength" style="width: 0%;"></div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 form-group">
                                    <label for="confirm_password">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="Confirm password" required>
                                    <div class="invalid-feedback">Passwords do not match</div>
                                </div>
                                
                                <div class="col-md-6 form-group">
                                    <label for="status">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="active" selected>Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
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
                                                <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == $spec) ? 'selected' : ''; ?>>
                                                <?php echo $spec; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select specialization</div>
                                </div>
                                
                                <div class="col-md-3 form-group">
                                    <label for="experience_years">Experience (Years) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="experience_years" name="experience_years" 
                                           value="<?php echo htmlspecialchars($_POST['experience_years'] ?? '0'); ?>" 
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
                                               value="<?php echo htmlspecialchars($_POST['hourly_rate'] ?? '0'); ?>" 
                                               min="0" max="1000" step="0.01" required>
                                    </div>
                                    <div class="invalid-feedback">Please enter hourly rate</div>
                                </div>
                                
                                <div class="col-12 form-group">
                                    <label for="qualification">Qualifications</label>
                                    <textarea class="form-control" id="qualification" name="qualification" rows="3" 
                                              placeholder="List qualifications, certifications, etc."><?php echo htmlspecialchars($_POST['qualification'] ?? ''); ?></textarea>
                                    <small class="text-muted">Separate multiple qualifications with commas</small>
                                </div>
                                
                                <div class="col-12 form-group">
                                    <label for="availability">Availability</label>
                                    <textarea class="form-control" id="availability" name="availability" rows="2" 
                                              placeholder="e.g., Weekdays 9am-5pm, Weekends by appointment"><?php echo htmlspecialchars($_POST['availability'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save mr-2"></i>Add Trainer
                                </button>
                                <a href="../manage_trainers.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-times mr-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Help Card -->
                <div class="card mt-4 bg-light">
                    <div class="card-body">
                        <h6><i class="fas fa-info-circle text-primary mr-2"></i>Trainer Information Tips</h6>
                        <ul class="mb-0 small">
                            <li>Trainers will receive login credentials to access their dashboard</li>
                            <li>They can manage classes, view assigned members, and track attendance</li>
                            <li>Hourly rate is used for payroll calculations</li>
                            <li>Specialization helps members choose the right trainer</li>
                            <li>You can assign members to trainers after creation</li>
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

        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            var password = this.value;
            var strength = 0;
            
            if (password.length >= 6) strength += 20;
            if (password.match(/[a-z]+/)) strength += 20;
            if (password.match(/[A-Z]+/)) strength += 20;
            if (password.match(/[0-9]+/)) strength += 20;
            if (password.match(/[$@#&!]+/)) strength += 20;
            
            var strengthBar = document.getElementById('passwordStrength');
            strengthBar.style.width = strength + '%';
            
            if (strength <= 20) {
                strengthBar.className = 'progress-bar bg-danger';
            } else if (strength <= 40) {
                strengthBar.className = 'progress-bar bg-warning';
            } else if (strength <= 60) {
                strengthBar.className = 'progress-bar bg-info';
            } else {
                strengthBar.className = 'progress-bar bg-success';
            }
        });

        // Password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            var password = document.getElementById('password').value;
            var confirm = this.value;
            
            if (password !== confirm) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Username validation
        document.getElementById('username').addEventListener('input', function() {
            var username = this.value;
            var pattern = /^[a-zA-Z0-9_]+$/;
            
            if (!pattern.test(username) && username.length > 0) {
                this.setCustomValidity('Username can only contain letters, numbers, and underscores');
            } else {
                this.setCustomValidity('');
            }
        });

        // Email validation
        document.getElementById('email').addEventListener('input', function() {
            var email = this.value;
            var pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!pattern.test(email) && email.length > 0) {
                this.setCustomValidity('Please enter a valid email address');
            } else {
                this.setCustomValidity('');
            }
        });

        // Experience years validation
        document.getElementById('experience_years').addEventListener('input', function() {
            var value = parseInt(this.value);
            if (value < 0) {
                this.setCustomValidity('Experience cannot be negative');
            } else if (value > 50) {
                this.setCustomValidity('Experience cannot exceed 50 years');
            } else {
                this.setCustomValidity('');
            }
        });

        // Hourly rate validation
        document.getElementById('hourly_rate').addEventListener('input', function() {
            var value = parseFloat(this.value);
            if (value < 0) {
                this.setCustomValidity('Hourly rate cannot be negative');
            } else if (value > 1000) {
                this.setCustomValidity('Hourly rate cannot exceed $1000');
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