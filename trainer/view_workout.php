<?php
// trainer/view_workout.php
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
$exercises = [];

// Get workout ID from URL
$workout_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($workout_id <= 0) {
    $error = "Invalid workout ID.";
} else {
    try {
        // Fetch workout details from workout_plans, including member/trainer names
        $stmt = $pdo->prepare("
            SELECT wp.*, 
                   u.full_name as trainer_name,
                   m.full_name as member_name
            FROM workout_plans wp
            LEFT JOIN users u ON wp.trainer_id = u.id
            LEFT JOIN users m ON wp.member_id = m.id
            WHERE wp.id = :id AND wp.trainer_id = :trainer_id
        ");
        $stmt->execute([
            ':id' => $workout_id,
            ':trainer_id' => Session::userId() // Ensure the plan belongs to this trainer
        ]);
        $workout = $stmt->fetch();

        if (!$workout) {
            $error = "Workout plan not found or you don't have permission to view it.";
        } else {
            // Convert exercises text from workout_plan into list entries for display
            $exercises = [];
            if (!empty($workout['exercises'])) {
                $exerciseLines = preg_split('/\r?\n/', trim($workout['exercises']));
                foreach ($exerciseLines as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $exercises[] = ['description' => $line];
                    }
                }
            }
        }
    } catch (Exception $e) {
        $error = "Error loading workout: " . $e->getMessage();
    }
}

$user_name = Session::userName();
$page_title = 'View Workout - ' . APP_NAME;
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
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-info{background:#d1ecf1;color:#0c5460}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}.btn-primary{background:#667eea;border:none}.btn-primary:hover{background:#5a67d8}.btn-outline-primary{color:#667eea;border-color:#667eea}.btn-outline-primary:hover{background:#667eea;border-color:#667eea}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}}
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
                <p>Trainer Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="workouts.php"><i class="fas fa-calendar-alt"></i> My Workouts</a></li>
                <li><a href="members.php"><i class="fas fa-users"></i> My Members</a></li>
                <li><a href="schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
                <li><a href="progress.php"><i class="fas fa-chart-line"></i> Member Progress</a></li>
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
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown"><i class="fas fa-bell"></i><span class="badge badge-danger badge-pill">2</span></button>
                        <div class="dropdown-menu dropdown-menu-right" style="width:300px">
                            <div class="dropdown-header bg-light">Notifications</div>
                            <a class="dropdown-item" href="#"><strong>New member assigned</strong><br><small class="text-muted">5 minutes ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Workout reminder</strong><br><small class="text-muted">1 hour ago</small></a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-center" href="#">View all</a>
                        </div>
                    </div>
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
                <?php Session::displayFlash(); ?>

                <!-- Page Header -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header">
                            <h1><i class="fas fa-dumbbell"></i> Workout Details 
                                <small><?php echo $workout ? htmlspecialchars($workout['plan_name']) : 'Workout not found'; ?></small>
                            </h1>
                        </div>
                    </div>
                </div>

                <?php if ($workout): ?>
                    <!-- Workout Information Card -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-info-circle"></i> Workout Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong><i class="fas fa-tag"></i> Plan Name:</strong> <?php echo htmlspecialchars($workout['plan_name']); ?></p>
                                    <p><strong><i class="fas fa-align-left"></i> Description:</strong> <?php echo nl2br(htmlspecialchars($workout['description'] ?? 'No description provided.')); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong><i class="fas fa-user"></i> Trainer:</strong> <?php echo htmlspecialchars($workout['trainer_name']); ?></p>
                                    <?php if (!empty($workout['member_name'])): ?>
                                        <p><strong><i class="fas fa-user-friends"></i> Assigned to:</strong> <?php echo htmlspecialchars($workout['member_name']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($workout['start_date'])): ?>
                                        <p><strong><i class="fas fa-calendar-day"></i> Start Date:</strong> <?php echo date('F j, Y', strtotime($workout['start_date'])); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($workout['end_date'])): ?>
                                        <p><strong><i class="fas fa-calendar-check"></i> End Date:</strong> <?php echo date('F j, Y', strtotime($workout['end_date'])); ?></p>
                                    <?php endif; ?>
                                    <p><strong><i class="fas fa-clock"></i> Created:</strong> <?php echo date('F j, Y', strtotime($workout['created_at'])); ?></p>
                                    <p><strong><i class="fas fa-flag-checkered"></i> Status:</strong> 
                                        <span class="badge badge-<?php echo $workout['status'] == 'active' ? 'success' : ($workout['status'] == 'completed' ? 'info' : 'warning'); ?>">
                                            <?php echo ucfirst($workout['status'] ?? 'active'); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Exercises Card -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-list-ul"></i> Exercises</h5>
                            <a href="workout_plans.php" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit Workout</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($exercises)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-dumbbell fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No exercises added to this workout yet.</p>
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($exercises as $index => $exercise): ?>
                                        <li class="list-group-item">
                                            <strong><?php echo $index + 1; ?>.</strong>
                                            <?php echo htmlspecialchars($exercise['description']); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="row">
                        <div class="col-md-12">
                            <a href="workout_plans.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Workout Plans</a>
                            <?php if (!empty($workout['member_id'])): ?>
                                <a href="member_progress.php?member_id=<?php echo $workout['member_id']; ?>" class="btn btn-info"><i class="fas fa-chart-line"></i> View Member Progress</a>
                            <?php endif; ?>
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
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
            if ($('.dataTable').length) {
                $('.dataTable').DataTable({
                    pageLength: 5,
                    lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "All"]],
                    order: [[0, 'desc']]
                });
            }
        });
        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }
    </script>
</body>
</html>