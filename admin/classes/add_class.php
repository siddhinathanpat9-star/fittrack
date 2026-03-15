<?php
// admin/classes/add_class.php
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

// Initialize functions
$functions = new Functions();
$error = '';
$success = '';

// Get all active trainers for assignment
$trainers = [];
try {
    $stmt = $pdo->query("SELECT u.id, u.full_name FROM users u JOIN trainers t ON u.id = t.user_id WHERE u.status = 'active' ORDER BY u.full_name");
    $trainers = $stmt->fetchAll();
} catch (Exception $e) {
    $trainers = [];
}

// Days of week options
$days_of_week = [
    'Monday' => 'Monday',
    'Tuesday' => 'Tuesday',
    'Wednesday' => 'Wednesday',
    'Thursday' => 'Thursday',
    'Friday' => 'Friday',
    'Saturday' => 'Saturday',
    'Sunday' => 'Sunday'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Get form data
        $class_name = trim($_POST['class_name']);
        $description = trim($_POST['description']);
        $trainer_id = !empty($_POST['trainer_id']) ? $_POST['trainer_id'] : null;
        $day_of_week = $_POST['day_of_week'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $max_capacity = (int)$_POST['max_capacity'];
        $status = $_POST['status'] ?? 'active';
        
        // Validation
        $errors = [];
        
        if (empty($class_name)) {
            $errors[] = "Class name is required";
        } elseif (strlen($class_name) < 3) {
            $errors[] = "Class name must be at least 3 characters";
        }
        
        if (empty($day_of_week)) {
            $errors[] = "Day of week is required";
        }
        
        if (empty($start_time)) {
            $errors[] = "Start time is required";
        }
        
        if (empty($end_time)) {
            $errors[] = "End time is required";
        }
        
        if (!empty($start_time) && !empty($end_time) && $start_time >= $end_time) {
            $errors[] = "End time must be after start time";
        }
        
        if ($max_capacity < 1) {
            $errors[] = "Maximum capacity must be at least 1";
        } elseif ($max_capacity > 100) {
            $errors[] = "Maximum capacity cannot exceed 100";
        }
        
        // Check for scheduling conflicts with same trainer
        if (!empty($trainer_id) && empty($errors)) {
            $stmt = $pdo->prepare("
                SELECT id FROM classes 
                WHERE trainer_id = ? 
                AND day_of_week = ? 
                AND status = 'active'
                AND (
                    (start_time <= ? AND end_time > ?) OR
                    (start_time < ? AND end_time >= ?) OR
                    (start_time >= ? AND end_time <= ?)
                )
            ");
            $stmt->execute([
                $trainer_id, 
                $day_of_week, 
                $end_time, $start_time,
                $end_time, $start_time,
                $start_time, $end_time
            ]);
            
            if ($stmt->rowCount() > 0) {
                $errors[] = "Trainer already has a class scheduled at this time";
            }
        }
        
        if (!empty($errors)) {
            throw new Exception(implode("<br>", $errors));
        }
        
        // Insert class
        $stmt = $pdo->prepare("
            INSERT INTO classes (class_name, description, trainer_id, day_of_week, start_time, end_time, max_capacity, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        if (!$stmt->execute([$class_name, $description, $trainer_id, $day_of_week, $start_time, $end_time, $max_capacity, $status])) {
            throw new Exception("Failed to create class");
        }
        
        $class_id = $pdo->lastInsertId();
        
        // Log the activity
        if (method_exists($functions, 'logActivity')) {
            $functions->logActivity(Session::userId(), 'add_class', "Added new class: $class_name");
        }
        
        Session::setFlash('success', 'Class added successfully!');
        header('Location: manage_classes.php');
        exit();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$user_name = Session::userName();
$page_title = 'Add Class - ' . APP_NAME;
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
                <li class="active">
                    <a href="#classesSubmenu" data-toggle="collapse" aria-expanded="true">
                        <i class="fas fa-calendar-alt"></i> Classes <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse show list-unstyled" id="classesSubmenu">
                        <li><a href="manage_classes.php"><i class="fas fa-list"></i> All Classes</a></li>
                        <li class="active"><a href="add_class.php"><i class="fas fa-plus-circle"></i> Add Class</a></li>
                        <li><a href="class_schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
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
                            <a class="dropdown-item" href="#"><strong>New class added</strong><br><small class="text-muted">2 minutes ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Booking cancelled</strong><br><small class="text-muted">1 hour ago</small></a>
                            <a class="dropdown-item" href="#"><strong>5 classes fully booked</strong><br><small class="text-muted">3 hours ago</small></a>
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
                            <h1><i class="fas fa-plus-circle"></i> Add New Class <small>Create a new fitness class</small></h1>
                        </div>
                    </div>
                </div>

                <!-- Add Class Form Card -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-plus"></i> Class Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="addClassForm" class="needs-validation" novalidate>
                            <!-- Basic Information -->
                            <div class="row">
                                <div class="col-12">
                                    <h6 class="text-primary">Basic Information</h6>
                                    <hr>
                                </div>
                                
                                <div class="col-md-8 form-group">
                                    <label for="class_name">Class Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="class_name" name="class_name" 
                                           value="<?php echo htmlspecialchars($_POST['class_name'] ?? ''); ?>" 
                                           placeholder="e.g., Morning Yoga, HIIT Workout, Spin Class" required>
                                    <div class="invalid-feedback">Please enter a class name</div>
                                </div>
                                
                                <div class="col-md-4 form-group">
                                    <label for="max_capacity">Max Capacity <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="max_capacity" name="max_capacity" 
                                           value="<?php echo htmlspecialchars($_POST['max_capacity'] ?? '20'); ?>" 
                                           min="1" max="100" required>
                                    <div class="invalid-feedback">Please enter a valid capacity (1-100)</div>
                                </div>
                                
                                <div class="col-12 form-group">
                                    <label for="description">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" 
                                              placeholder="Describe the class, what to expect, benefits, etc."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                    <small class="text-muted">Brief description of the class (optional)</small>
                                </div>
                            </div>

                            <!-- Schedule Information -->
                            <div class="row mt-3">
                                <div class="col-12">
                                    <h6 class="text-primary">Schedule Information</h6>
                                    <hr>
                                </div>
                                
                                <div class="col-md-4 form-group">
                                    <label for="day_of_week">Day of Week <span class="text-danger">*</span></label>
                                    <select class="form-control" id="day_of_week" name="day_of_week" required>
                                        <option value="">Select Day</option>
                                        <?php foreach ($days_of_week as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" <?php echo (isset($_POST['day_of_week']) && $_POST['day_of_week'] == $value) ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a day</div>
                                </div>
                                
                                <div class="col-md-4 form-group">
                                    <label for="start_time">Start Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="start_time" name="start_time" 
                                           value="<?php echo htmlspecialchars($_POST['start_time'] ?? '09:00'); ?>" required>
                                    <div class="invalid-feedback">Please enter start time</div>
                                </div>
                                
                                <div class="col-md-4 form-group">
                                    <label for="end_time">End Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="end_time" name="end_time" 
                                           value="<?php echo htmlspecialchars($_POST['end_time'] ?? '10:00'); ?>" required>
                                    <div class="invalid-feedback">Please enter end time</div>
                                </div>
                                
                                <div class="col-12" id="duration_preview" style="display: none;">
                                    <div class="alert alert-info">
                                        <i class="fas fa-clock mr-2"></i>
                                        Duration: <span id="duration_display"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Trainer Assignment -->
                            <div class="row mt-3">
                                <div class="col-12">
                                    <h6 class="text-primary">Trainer Assignment</h6>
                                    <hr>
                                </div>
                                
                                <div class="col-md-8 form-group">
                                    <label for="trainer_id">Assign Trainer</label>
                                    <select class="form-control" id="trainer_id" name="trainer_id">
                                        <option value="">No Trainer Assigned</option>
                                        <?php foreach ($trainers as $trainer): ?>
                                            <option value="<?php echo $trainer['id']; ?>" <?php echo (isset($_POST['trainer_id']) && $_POST['trainer_id'] == $trainer['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($trainer['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Select a trainer to lead this class</small>
                                </div>
                                
                                <div class="col-md-4 form-group">
                                    <label for="status">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : 'selected'; ?>>Active</option>
                                        <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Schedule Preview -->
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="card bg-light" id="schedule_preview" style="display: none;">
                                        <div class="card-body">
                                            <h6><i class="fas fa-calendar-check mr-2"></i>Class Schedule Preview</h6>
                                            <p class="mb-0" id="preview_text"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">
                            
                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save mr-2"></i>Add Class
                                </button>
                                <a href="manage_classes.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-times mr-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tips Card -->
                <div class="card mt-4 bg-light">
                    <div class="card-body">
                        <h6><i class="fas fa-lightbulb text-primary mr-2"></i>Tips for Creating Classes</h6>
                        <ul class="mb-0 small">
                            <li>Choose a descriptive class name that clearly indicates the activity</li>
                            <li>Add a detailed description including difficulty level, equipment needed, and benefits</li>
                            <li>Consider peak hours when scheduling classes</li>
                            <li>Start with a conservative capacity and increase based on demand</li>
                            <li>Assign trainers based on their specialization and availability</li>
                            <li>Check for scheduling conflicts with the same trainer</li>
                        </ul>
                    </div>
                </div>

                <!-- Sample Class Ideas -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-lightbulb mr-2"></i>Popular Class Ideas</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center p-2 border rounded">
                                    <i class="fas fa-spa fa-2x text-primary mb-2"></i>
                                    <h6>Yoga</h6>
                                    <small class="text-muted">Flexibility & Relaxation</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-2 border rounded">
                                    <i class="fas fa-heartbeat fa-2x text-danger mb-2"></i>
                                    <h6>HIIT</h6>
                                    <small class="text-muted">High Intensity</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-2 border rounded">
                                    <i class="fas fa-bicycle fa-2x text-success mb-2"></i>
                                    <h6>Spin</h6>
                                    <small class="text-muted">Indoor Cycling</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-2 border rounded">
                                    <i class="fas fa-music fa-2x text-warning mb-2"></i>
                                    <h6>Zumba</h6>
                                    <small class="text-muted">Dance Fitness</small>
                                </div>
                            </div>
                        </div>
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

        // Calculate and display duration
        function calculateDuration() {
            var startTime = document.getElementById('start_time').value;
            var endTime = document.getElementById('end_time').value;
            
            if (startTime && endTime) {
                var start = new Date('1970-01-01T' + startTime + 'Z');
                var end = new Date('1970-01-01T' + endTime + 'Z');
                
                if (end > start) {
                    var diffMs = end - start;
                    var diffHrs = Math.floor(diffMs / 3600000);
                    var diffMins = Math.floor((diffMs % 3600000) / 60000);
                    
                    document.getElementById('duration_display').innerHTML = diffHrs + ' hour' + (diffHrs !== 1 ? 's' : '') + ' ' + diffMins + ' minute' + (diffMins !== 1 ? 's' : '');
                    document.getElementById('duration_preview').style.display = 'block';
                } else {
                    document.getElementById('duration_preview').style.display = 'none';
                }
            }
        }

        // Update schedule preview
        function updatePreview() {
            var className = document.getElementById('class_name').value;
            var day = document.getElementById('day_of_week').value;
            var startTime = document.getElementById('start_time').value;
            var endTime = document.getElementById('end_time').value;
            var trainerSelect = document.getElementById('trainer_id');
            var trainerName = trainerSelect.options[trainerSelect.selectedIndex]?.text || 'No trainer';
            var capacity = document.getElementById('max_capacity').value;
            
            if (className && day && startTime && endTime) {
                var preview = '<strong>' + className + '</strong> on ' + day + ' from ' + 
                             formatTime(startTime) + ' to ' + formatTime(endTime) + 
                             ' with ' + trainerName + '. Capacity: ' + capacity + ' people.';
                
                document.getElementById('preview_text').innerHTML = preview;
                document.getElementById('schedule_preview').style.display = 'block';
            } else {
                document.getElementById('schedule_preview').style.display = 'none';
            }
        }

        // Format time to AM/PM
        function formatTime(time) {
            var parts = time.split(':');
            var hours = parseInt(parts[0]);
            var minutes = parts[1];
            var ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            return hours + ':' + minutes + ' ' + ampm;
        }

        // Event listeners
        document.getElementById('start_time').addEventListener('change', function() {
            calculateDuration();
            updatePreview();
        });

        document.getElementById('end_time').addEventListener('change', function() {
            calculateDuration();
            updatePreview();
        });

        document.getElementById('class_name').addEventListener('input', updatePreview);
        document.getElementById('day_of_week').addEventListener('change', updatePreview);
        document.getElementById('trainer_id').addEventListener('change', updatePreview);
        document.getElementById('max_capacity').addEventListener('input', updatePreview);

        // Initialize preview on page load
        document.addEventListener('DOMContentLoaded', function() {
            calculateDuration();
            updatePreview();
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>