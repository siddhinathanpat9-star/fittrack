<?php
// trainer/edit_workout.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure only trainers can access this page
Session::requireTrainer();

$functions = new Functions();
$error = '';
$success = '';
$workout = null;

// Get workout ID from URL
$workout_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($workout_id <= 0) {
    $error = "Invalid workout ID.";
}

// Fetch workout data if ID is valid
if (empty($error)) {
    try {
        // Fetch workout plan and verify it belongs to the current trainer
        $stmt = $pdo->prepare("
            SELECT wp.*, u.full_name as trainer_name, m.full_name as member_name
            FROM workout_plans wp
            LEFT JOIN users u ON wp.trainer_id = u.id
            LEFT JOIN users m ON wp.member_id = m.id
            WHERE wp.id = :id AND wp.trainer_id = :trainer_id
        ");
        $stmt->execute([
            ':id' => $workout_id,
            ':trainer_id' => Session::userId()
        ]);
        $workout = $stmt->fetch();
        
        if (!$workout) {
            $error = "Workout not found or you don't have permission to edit it.";
        }
    } catch (Exception $e) {
        $error = "Error loading workout: " . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_workout']) && $workout) {
    $plan_name = trim($_POST['plan_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $exercises = trim($_POST['exercises'] ?? '');
    $duration_weeks = (int)($_POST['duration_weeks'] ?? 0);
    $start_date = $_POST['start_date'] ?: null;
    $end_date = $_POST['end_date'] ?: null;
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    $validation_errors = [];
    if (empty($plan_name)) $validation_errors[] = "Plan name is required.";
    if ($duration_weeks <= 0) $validation_errors[] = "Please enter a valid duration in weeks.";
    if (!in_array($status, ['active', 'completed', 'cancelled'])) $status = 'active';
    
    if (empty($validation_errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE workout_plans
                SET plan_name = :plan_name,
                    description = :description,
                    exercises = :exercises,
                    duration_weeks = :duration_weeks,
                    start_date = :start_date,
                    end_date = :end_date,
                    status = :status
                WHERE id = :id AND trainer_id = :trainer_id
            ");
            $stmt->execute([
                ':plan_name' => $plan_name,
                ':description' => $description,
                ':exercises' => $exercises,
                ':duration_weeks' => $duration_weeks ?: null,
                ':start_date' => $start_date,
                ':end_date' => $end_date,
                ':status' => $status,
                ':id' => $workout_id,
                ':trainer_id' => Session::userId()
            ]);
            
            $success = "Workout plan updated successfully!";
            // Refresh workout data
            $stmt = $pdo->prepare("SELECT * FROM workout_plans WHERE id = :id AND trainer_id = :trainer_id");
            $stmt->execute([':id' => $workout_id, ':trainer_id' => Session::userId()]);
            $workout = $stmt->fetch();
        } catch (Exception $e) {
            $error = "Error updating workout plan: " . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $validation_errors);
    }
}

$user_name = Session::userName();
$page_title = 'Edit Workout - ' . APP_NAME;
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
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 10px 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 500;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        .btn-outline-secondary:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="loading-spinner" id="loadingSpinner"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>
    <div class="wrapper">
        <!-- Trainer Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Trainer Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="workouts.php"><i class="fas fa-dumbbell"></i> My Workouts</a></li>
                <li><a href="add_workout.php"><i class="fas fa-plus-circle"></i> Add Workout</a></li>
                <li class="active"><a href="#"><i class="fas fa-edit"></i> Edit Workout</a></li>
                <li><a href="clients.php"><i class="fas fa-users"></i> My Clients</a></li>
                <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> Schedule</a></li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
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
                    <!-- Notifications dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown"><i class="fas fa-bell"></i><span class="badge badge-danger badge-pill">2</span></button>
                        <div class="dropdown-menu dropdown-menu-right" style="width:300px">
                            <div class="dropdown-header bg-light">Notifications</div>
                            <a class="dropdown-item" href="#"><strong>New client assigned</strong><br><small class="text-muted">1 hour ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Workout feedback received</strong><br><small class="text-muted">3 hours ago</small></a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-center" href="#">View all</a>
                        </div>
                    </div>
                    <!-- User dropdown -->
                    <div class="dropdown ml-3 d-inline-block">
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
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                <?php Session::displayFlash(); ?>

                <!-- Page Header -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header">
                            <h1><i class="fas fa-edit"></i> Edit Workout <small>Update your workout routine</small></h1>
                        </div>
                    </div>
                </div>

                <?php if ($workout): ?>
                <!-- Edit Workout Plan Form Card -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-dumbbell"></i> <?php echo htmlspecialchars($workout['plan_name'] ?? 'Workout Plan'); ?> - Plan Details</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="editWorkoutForm">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="plan_name">Plan Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="plan_name" name="plan_name" value="<?php echo htmlspecialchars($workout['plan_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="duration_weeks">Duration (weeks) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="duration_weeks" name="duration_weeks" value="<?php echo (int)$workout['duration_weeks']; ?>" min="1" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="start_date">Start Date</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($workout['start_date']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="end_date">End Date</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($workout['end_date']); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="active" <?php echo ($workout['status'] ?? '') == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="completed" <?php echo ($workout['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo ($workout['status'] ?? '') == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($workout['description'] ?? ''); ?></textarea>
                                <small class="form-text text-muted">Brief overview of the workout plan.</small>
                            </div>

                            <div class="form-group">
                                <label for="exercises">Exercises</label>
                                <textarea class="form-control" id="exercises" name="exercises" rows="5"><?php echo htmlspecialchars($workout['exercises'] ?? ''); ?></textarea>
                                <small class="form-text text-muted">List exercises separated by newline.</small>
                            </div>

                            <div class="border-top pt-3 mt-3">
                                <button type="submit" name="update_workout" class="btn btn-primary"><i class="fas fa-save"></i> Update Plan</button>
                                <a href="workout_plans.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Additional Info Card -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> Workout Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item"><i class="fas fa-calendar-alt"></i> Created: <?php echo date('M d, Y', strtotime($workout['created_at'])); ?></li>
                                    <?php $lastUpdated = !empty($workout['updated_at']) ? $workout['updated_at'] : $workout['created_at']; ?>
                                    <li class="list-group-item"><i class="fas fa-clock"></i> Last Updated: <?php echo date('M d, Y', strtotime($lastUpdated)); ?></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item"><i class="fas fa-calendar-alt"></i> Duration Weeks: <strong><?php echo (int)$workout['duration_weeks']; ?></strong></li>
                                    <li class="list-group-item"><i class="fas fa-flag-checkered"></i> Status: <span class="badge badge-<?php echo $workout['status'] == 'active' ? 'success' : ($workout['status'] == 'completed' ? 'info' : 'danger'); ?>"><?php echo ucfirst($workout['status']); ?></span></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Error State when workout not found -->
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-frown fa-4x text-muted mb-3"></i>
                        <h4 class="mb-3">Workout Plan Not Found</h4>
                        <p class="text-muted">The workout plan you're trying to edit doesn't exist or you don't have permission.</p>
                        <a href="workout_plans.php" class="btn btn-primary mt-3"><i class="fas fa-dumbbell"></i> Go to Workout Plans</a>
                    </div>
                </div>
                <?php endif; ?>
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
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#sidebarCollapse').on('click', function() {
                $('#sidebar').toggleClass('active');
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
            
            // Form validation
            $('#editWorkoutForm').on('submit', function(e) {
                let isValid = true;
                const name = $('#name').val().trim();
                const duration = $('#duration_minutes').val();
                
                if (name === '') {
                    alert('Please enter workout name');
                    isValid = false;
                } else if (duration === '' || duration < 1) {
                    alert('Please enter valid duration (minimum 1 minute)');
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
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