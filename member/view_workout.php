<?php
// member/view_workout.php
// View a specific workout's details (exercises, sets, reps, notes, etc.)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure only logged-in members can access this page
Session::requireLogin();
if (Session::userType() !== 'member') {
    header('Location: ../admin/dashboard.php');
    exit;
}

$functions = new Functions();
$error = '';
$success = '';
$workout = null;
$exercises = [];

// Get workout ID from URL
$workout_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = Session::userId();

if ($workout_id <= 0) {
    $error = "Invalid workout ID.";
} else {
    try {
        // Fetch workout plan details - ensure it belongs to the logged-in member
        $stmt = $pdo->prepare("
            SELECT wp.*, u.full_name as trainer_name
            FROM workout_plans wp
            LEFT JOIN users u ON wp.trainer_id = u.id
            WHERE wp.id = :workout_id AND wp.member_id = :user_id
        ");
        $stmt->execute([':workout_id' => $workout_id, ':user_id' => $user_id]);
        $workout = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$workout) {
            $error = "Workout not found or you don't have permission to view it.";
        } else {
            // Convert exercises text into lines for display
            if (!empty($workout['exercises'])) {
                $exerciseLines = preg_split('/\r?\n/', trim($workout['exercises']));
                foreach ($exerciseLines as $line) {
                    if ($line !== '') {
                        $exercises[] = ['description' => $line];
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

$user_name = Session::userName();
$page_title = "View Workout - " . APP_NAME;
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
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.stats-card small{opacity:.9}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.badge-primary{background:#cfe2ff;color:#084298}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:15px 20px;transition:.3s}.list-group-item:last-child{border-bottom:none}.list-group-item:hover{background:#f8f9fa;transform:translateX(5px)}.list-group-item i{color:#667eea;margin-right:10px}.chart-container{position:relative;height:300px;margin:20px 0}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}.stats-card h2{font-size:1.8rem}}
        .workout-stats .stat-item{text-align:center;padding:15px;background:#f8f9fa;border-radius:12px;margin-bottom:15px;height:100%}
        .workout-stats .stat-value{font-size:2rem;font-weight:700;color:#667eea}
        .stat-label{color:#6c757d;font-size:.85rem;text-transform:uppercase}
        .exercise-table th{background:#f8f9fa}
    </style>
</head>
<body>
    <div class="loading-spinner" id="loadingSpinner"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>
    <div class="wrapper">
        <!-- Member Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Member Portal</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="my_workouts.php"><i class="fas fa-calendar-alt"></i> My Workouts</a></li>
                <li class="active"><a href="#"><i class="fas fa-eye"></i> View Workout</a></li>
                <li><a href="progress.php"><i class="fas fa-chart-line"></i> Progress</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
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
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown"><i class="fas fa-bell"></i><span class="badge badge-danger badge-pill">3</span></button>
                        <div class="dropdown-menu dropdown-menu-right" style="width:300px">
                            <div class="dropdown-header bg-light">Notifications</div>
                            <a class="dropdown-item" href="#"><strong>New workout assigned</strong><br><small class="text-muted">2 hours ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Membership expiring soon</strong><br><small class="text-muted">3 days left</small></a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-center" href="notifications.php">View all</a>
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
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php Session::displayFlash(); ?>

                <!-- Page Header -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header d-flex justify-content-between align-items-center">
                            <h1><i class="fas fa-dumbbell"></i> Workout Details <small>View and track your exercises</small></h1>
                            <a href="my_workouts.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Workouts</a>
                        </div>
                    </div>
                </div>

                <?php if ($workout): ?>
                    <!-- Workout Info Card -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-info-circle"></i> Workout Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h3 class="mb-3"><?php echo htmlspecialchars($workout['plan_name'] ?? 'Untitled Workout Plan'); ?></h3>
                                    <div class="row mt-4 workout-stats">
                                        <div class="col-md-3 col-6">
                                            <div class="stat-item">
                                                <div class="stat-value"><?php echo !empty($workout['start_date']) ? date('M d', strtotime($workout['start_date'])) : 'N/A'; ?></div>
                                                <div class="stat-label">Start Date</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <div class="stat-item">
                                                <div class="stat-value"><?php echo !empty($workout['duration_weeks']) ? $workout['duration_weeks'] . ' wk' : 'N/A'; ?></div>
                                                <div class="stat-label">Duration</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <div class="stat-item">
                                                <div class="stat-value"><?php echo count($exercises); ?></div>
                                                <div class="stat-label">Exercises</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <div class="stat-item">
                                                <div class="stat-value">
                                                    <span class="badge badge-<?php 
                                                        $status = strtolower($workout['status'] ?? 'active');
                                                        echo $status === 'active' ? 'success' : ($status === 'completed' ? 'info' : ($status === 'cancelled' ? 'danger' : 'secondary'));
                                                    ?> p-2">
                                                        <?php echo ucfirst($status); ?>
                                                    </span>
                                                </div>
                                                <div class="stat-label">Status</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="bg-light p-3 rounded">
                                        <?php if (!empty($workout['trainer_name'])): ?>
                                            <p class="mb-1"><strong><i class="fas fa-user"></i> Trainer:</strong> <?php echo htmlspecialchars($workout['trainer_name']); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($workout['start_date'])): ?>
                                            <p class="mb-1"><strong><i class="fas fa-calendar-day"></i> Start Date:</strong> <?php echo date('F j, Y', strtotime($workout['start_date'])); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($workout['end_date'])): ?>
                                            <p class="mb-1"><strong><i class="fas fa-calendar-check"></i> End Date:</strong> <?php echo date('F j, Y', strtotime($workout['end_date'])); ?></p>
                                        <?php endif; ?>
                                        <p class="mb-0"><strong><i class="fas fa-sticky-note"></i> Notes:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($workout['description'] ?? 'No additional notes.')); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Exercises Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-list-ul"></i> Exercise List</h5>
                            <?php if (($workout['status'] ?? '') != 'completed'): ?>
                            <button class="btn btn-sm btn-success" onclick="markWorkoutComplete(<?php echo $workout_id; ?>)">
                                <i class="fas fa-check-circle"></i> Mark as Completed
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($exercises)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-dumbbell fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No exercises have been added to this workout yet.</p>
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($exercises as $index => $exercise): ?>
                                        <li class="list-group-item">
                                            <strong><?php echo $index + 1; ?>.</strong> <?php echo htmlspecialchars($exercise['description'] ?? 'Exercise'); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="row mt-3">
                        <div class="col-12 text-right">
                            <a href="my_workouts.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Workouts</a>
                            <?php if (($workout['status'] ?? '') != 'completed'): ?>
                            <button class="btn btn-primary" onclick="printWorkout()"><i class="fas fa-print"></i> Print Workout</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Workout not found -->
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-search fa-4x text-muted mb-3"></i>
                            <h4>Workout Not Found</h4>
                            <p class="text-muted">The workout you're looking for doesn't exist or you don't have permission to view it.</p>
                            <a href="my_workouts.php" class="btn btn-primary mt-3"><i class="fas fa-calendar-alt"></i> Go to My Workouts</a>
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

        function printWorkout() {
            window.print();
        }

        function markWorkoutComplete(workoutId) {
            if (confirm('Mark this workout as completed? This action cannot be undone.')) {
                $.ajax({
                    url: 'ajax/complete_workout.php',
                    type: 'POST',
                    data: { workout_id: workoutId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + (response.message || 'Could not mark workout as complete.'));
                        }
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                    }
                });
            }
        }
    </script>
</body>
</html>