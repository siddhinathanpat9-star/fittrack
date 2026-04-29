<?php
// member/my_classes.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure only members can access this page
Session::requireMember();

$functions = new Functions();
$error = '';
$success = '';

$member_id = Session::userId();
$user_name = Session::userName();

// Handle unenroll action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unenroll'])) {
    $class_id = intval($_POST['class_id']);
    
    try {
        // Verify ownership
        $check = $pdo->prepare("SELECT id FROM class_enrollments WHERE class_id = ? AND user_id = ?");
        $check->execute([$class_id, $member_id]);
        
        if ($check->rowCount() > 0) {
            $stmt = $pdo->prepare("DELETE FROM class_enrollments WHERE class_id = ? AND user_id = ?");
            if ($stmt->execute([$class_id, $member_id])) {
                Session::setFlash('success', 'Successfully unenrolled from class!');
            } else {
                Session::setFlash('danger', 'Failed to unenroll. Please try again.');
            }
        } else {
            Session::setFlash('danger', 'You are not enrolled in this class.');
        }
    } catch (Exception $e) {
        Session::setFlash('danger', 'Error: ' . $e->getMessage());
    }
    
    header('Location: my_classes.php');
    exit;
}

// Fetch enrolled classes
$enrolled_classes = [];
try {
    $stmt = $pdo->prepare("
        SELECT ce.id AS enrollment_id,
               ce.enrollment_date,
               c.id AS class_id,
               c.class_name,
               c.description,
               c.day_of_week,
               c.start_time,
               c.end_time,
               c.max_capacity,
               u.full_name AS trainer_name,
               u.id AS trainer_id,
               (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) AS enrolled_count
        FROM class_enrollments ce
        INNER JOIN classes c ON ce.class_id = c.id
        LEFT JOIN trainers t ON c.trainer_id = t.user_id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE ce.user_id = ? AND c.status = 'active'
        ORDER BY c.day_of_week ASC, c.start_time ASC
    ");
    $stmt->execute([$member_id]);
    $enrolled_classes = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading classes: " . $e->getMessage();
}

// Fetch upcoming schedules for enrolled classes
$upcoming_schedules = [];
if (!empty($enrolled_classes)) {
    try {
        $class_ids = array_column($enrolled_classes, 'class_id');
        $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
        
        $stmt = $pdo->prepare("
            SELECT cs.id AS schedule_id,
                   cs.class_id,
                   cs.schedule_date,
                   cs.start_time,
                   cs.end_time,
                   cs.location,
                   (SELECT COUNT(*) FROM class_bookings WHERE schedule_id = cs.id) AS booked_count,
                   cs.max_capacity
            FROM class_schedule cs
            WHERE cs.class_id IN ($placeholders)
              AND cs.schedule_date >= CURDATE()
            ORDER BY cs.schedule_date ASC, cs.start_time ASC
            LIMIT 20
        ");
        $stmt->execute($class_ids);
        $upcoming_schedules = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = "Error loading schedules: " . $e->getMessage();
    }
}

$page_title = 'My Classes - ' . APP_NAME;
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
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.class-card{border:none;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);margin-bottom:25px;transition:.3s;overflow:hidden}.class-card:hover{transform:translateY(-5px);box-shadow:0 10px 25px rgba(0,0,0,0.15)}.class-card-header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:20px;border-radius:15px 15px 0 0}.class-card-header h5{margin:0;font-weight:600;font-size:1.3rem}.class-card-body{padding:20px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.badge{padding:6px 12px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-primary{background:#d1ecf1;color:#0c5460}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-info{background:#d1ecf1;color:#0c5460}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}.class-card-header h5{font-size:1rem}}
        .class-info-item{margin-bottom:12px;font-size:.95rem}.class-info-item strong{color:#667eea;min-width:100px;display:inline-block}
    </style>
</head>
<body>
    <div class="loading-spinner" id="loadingSpinner"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>
    <div class="wrapper">
        <!-- Sidebar (Member Version) -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Member Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="active"><a href="my_classes.php"><i class="fas fa-book"></i> My Classes</a></li>
                <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> Schedule</a></li>
                <li><a href="progress.php"><i class="fas fa-chart-line"></i> Progress</a></li>
                <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
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
                    <!-- User dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown"><i class="fas fa-user-circle fa-lg"></i><span class="ml-2"><?php echo htmlspecialchars($user_name); ?></span></button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
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
                            <h1><i class="fas fa-book"></i> My Classes <small>Your enrolled classes</small></h1>
                        </div>
                    </div>
                </div>

                <!-- Enrolled Classes -->
                <div class="row">
                    <div class="col-md-12">
                        <?php if (empty($enrolled_classes)): ?>
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">You are not enrolled in any classes yet.</p>
                                    <a href="schedule.php" class="btn btn-primary"><i class="fas fa-search"></i> Browse Classes</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($enrolled_classes as $class): ?>
                                <div class="col-lg-6 col-md-12 mb-4">
                                    <div class="class-card">
                                        <div class="class-card-header">
                                            <h5><?php echo htmlspecialchars($class['class_name']); ?></h5>
                                            <span class="badge badge-light"><?php echo htmlspecialchars($class['day_of_week']); ?></span>
                                        </div>
                                        <div class="class-card-body">
                                            <div class="class-info-item">
                                                <strong>Time:</strong> 
                                                <?php echo date('h:i A', strtotime($class['start_time'])); ?> - <?php echo date('h:i A', strtotime($class['end_time'])); ?>
                                            </div>
                                            <div class="class-info-item">
                                                <strong>Trainer:</strong> 
                                                <?php echo $class['trainer_name'] ? htmlspecialchars($class['trainer_name']) : 'Not assigned'; ?>
                                            </div>
                                            <div class="class-info-item">
                                                <strong>Capacity:</strong> 
                                                <?php echo $class['enrolled_count']; ?>/<?php echo $class['max_capacity']; ?>
                                            </div>
                                            <div class="class-info-item">
                                                <strong>Enrolled:</strong> 
                                                <?php echo date('M d, Y', strtotime($class['enrollment_date'])); ?>
                                            </div>
                                            <hr>
                                            <p class="text-muted small mb-3"><?php echo htmlspecialchars($class['description'] ?? 'No description'); ?></p>
                                            
                                            <div class="btn-group btn-group-sm w-100">
                                                <?php if ($class['trainer_id']): ?>
                                                    <a href="message_trainer.php?trainer_id=<?php echo $class['trainer_id']; ?>&class_id=<?php echo $class['class_id']; ?>" class="btn btn-info flex-fill">
                                                        <i class="fas fa-envelope"></i> Message Trainer
                                                    </a>
                                                <?php endif; ?>
                                                <form method="POST" style="display:inline;" class="flex-fill">
                                                    <input type="hidden" name="class_id" value="<?php echo $class['class_id']; ?>">
                                                    <button type="submit" name="unenroll" value="1" class="btn btn-danger btn-sm w-100" onclick="return confirm('Are you sure you want to unenroll from this class?');">
                                                        <i class="fas fa-sign-out-alt"></i> Unenroll
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Schedules for Enrolled Classes -->
                <?php if (!empty($upcoming_schedules)): ?>
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-calendar-check"></i> Upcoming Class Schedules</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Location</th>
                                                <th>Enrolled/Capacity</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($upcoming_schedules as $schedule): ?>
                                            <tr>
                                                <td><?php echo date('D, M d', strtotime($schedule['schedule_date'])); ?></td>
                                                <td><?php echo date('h:i A', strtotime($schedule['start_time'])); ?> - <?php echo date('h:i A', strtotime($schedule['end_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($schedule['location'] ?? 'Main Studio'); ?></td>
                                                <td>
                                                    <span class="badge badge-info"><?php echo $schedule['booked_count']; ?>/<?php echo $schedule['max_capacity']; ?></span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
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
        });

        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }
    </script>
</body>
</html>
