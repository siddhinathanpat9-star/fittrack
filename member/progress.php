<?php
// member/progress.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure only logged-in members can access this page
if (!Session::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}
if (Session::userType() !== 'member') {
    header('Location: ../index.php');
    exit;
}

$functions = new Functions();
$error = '';
$success = '';
$user_id = Session::userId();
$user_name = Session::userName();

// Fetch member details (membership type, join date)
try {
    $stmt = $pdo->prepare("SELECT membership_type, membership_start FROM members WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $member = $stmt->fetch();
} catch (Exception $e) {
    $error = "Error fetching member details: " . $e->getMessage();
    $member = null;
}

// Fetch progress statistics
try {
    // Latest weight
    $stmt = $pdo->prepare("SELECT weight, recorded_date as recorded_at FROM progress_tracking WHERE member_id = ? ORDER BY recorded_date DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $latest_weight = $stmt->fetch();

    // Weight goal (using default)
    $weight_goal = 70.0; // default fallback

    // Workouts this month
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM workouts WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?");
    $first_day = date('Y-m-01');
    $last_day = date('Y-m-t');
    $stmt->execute([$user_id, $first_day, $last_day]);
    $workouts_this_month = $stmt->fetchColumn();

    // Workout streak (consecutive days with a workout)
    // Simple: calculate by checking if there's a workout every day in the last n days.
    $streak = 0;
    $stmt = $pdo->prepare("SELECT DISTINCT DATE(created_at) as wdate FROM workouts WHERE user_id = ? ORDER BY wdate DESC");
    $stmt->execute([$user_id]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($dates)) {
        $current = new DateTime();
        $today = $current->format('Y-m-d');
        $streak = 0;
        for ($i = 0; $i < count($dates); $i++) {
            $check_date = (new DateTime())->modify("-$i days")->format('Y-m-d');
            if (in_array($check_date, $dates)) {
                $streak++;
            } else {
                break;
            }
        }
    }

    // Progress chart data (last 6 months weight)
    $weight_data = [];
    $weight_labels = [];
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(recorded_date, '%b %Y') as month, AVG(weight) as avg_weight
        FROM progress_tracking
        WHERE member_id = ? AND recorded_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY YEAR(recorded_date), MONTH(recorded_date)
        ORDER BY recorded_date ASC
    ");
    $stmt->execute([$user_id]);
    $weight_chart = $stmt->fetchAll();
    foreach ($weight_chart as $row) {
        $weight_labels[] = $row['month'];
        $weight_data[] = round($row['avg_weight'], 1);
    }

    // If no data, provide sample data
    if (empty($weight_labels)) {
        for ($i = 5; $i >= 0; $i--) {
            $date = new DateTime("-$i months");
            $weight_labels[] = $date->format('M Y');
            $weight_data[] = 70 + rand(-3, 3);
        }
    }

    // Skip recent progress entries (measurement section removed)
    $recent_progress = [];

} catch (Exception $e) {
    $error = "Error loading progress data: " . $e->getMessage();
    $latest_weight = null;
    $weight_goal = 70;
    $workouts_this_month = 0;
    $streak = 0;
    $weight_data = [70, 71, 69.5, 70, 68, 67.5];
    $weight_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
    $recent_progress = [];
}

$page_title = 'My Progress - ' . APP_NAME;
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
        .avatar-circle{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;background:#667eea;color:white;margin-right:12px}
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
                <li class="active"><a href="progress.php"><i class="fas fa-chart-line"></i> My Progress</a></li>
                <li><a href="workouts.php"><i class="fas fa-running"></i> Workouts</a></li>
                <li><a href="measurements.php"><i class="fas fa-ruler"></i> Measurements</a></li>
                <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> Class Schedule</a></li>
                <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
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
                    <!-- Notifications dropdown (static example) -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown"><i class="fas fa-bell"></i><span class="badge badge-danger badge-pill">2</span></button>
                        <div class="dropdown-menu dropdown-menu-right" style="width:300px">
                            <div class="dropdown-header bg-light">Notifications</div>
                            <a class="dropdown-item" href="#"><strong>New class added</strong><br><small class="text-muted">1 day ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Membership renewal reminder</strong><br><small class="text-muted">3 days ago</small></a>
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
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php Session::displayFlash(); ?>

                <!-- Page Header -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header">
                            <h1><i class="fas fa-chart-line"></i> My Progress <small>Track your fitness journey</small></h1>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="card stats-card bg-primary text-white">
                            <div class="card-body">
                                <div class="card-title">Current Weight</div>
                                <h2><?php echo $latest_weight ? number_format($latest_weight['weight'], 1) : '—'; ?> kg</h2>
                                <i class="fas fa-weight-scale"></i>
                                <small>Last recorded: <?php echo $latest_weight ? date('M d, Y', strtotime($latest_weight['recorded_at'])) : 'Never'; ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <div class="card-title">Goal Weight</div>
                                <h2><?php echo number_format($weight_goal, 1); ?> kg</h2>
                                <i class="fas fa-bullseye"></i>
                                <small><?php echo ($latest_weight && $latest_weight['weight'] > $weight_goal) ? 'Remaining: ' . number_format($latest_weight['weight'] - $weight_goal, 1) . ' kg' : 'Achieved!'; ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <div class="card-title">Workouts This Month</div>
                                <h2><?php echo $workouts_this_month; ?></h2>
                                <i class="fas fa-calendar-check"></i>
                                <small>Keep it up!</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="card stats-card bg-warning text-white">
                            <div class="card-body">
                                <div class="card-title">Current Streak</div>
                                <h2><?php echo $streak; ?> days</h2>
                                <i class="fas fa-fire"></i>
                                <small>🔥 Keep burning</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Weight Progress Chart -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line"></i> Weight Trend (Last 6 Months)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="weightChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Progress Modal -->
    <div class="modal fade" id="addProgressModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="process_progress.php">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Log New Progress</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="recorded_at" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Weight (kg)</label>
                            <input type="number" step="0.1" name="weight" class="form-control" placeholder="e.g., 70.5" required>
                        </div>
                        <div class="form-group">
                            <label>Body Fat (%) <small class="text-muted">(optional)</small></label>
                            <input type="number" step="0.1" name="body_fat" class="form-control" placeholder="e.g., 18.5">
                        </div>
                        <div class="form-group">
                            <label>Muscle Mass (kg) <small class="text-muted">(optional)</small></label>
                            <input type="number" step="0.1" name="muscle_mass" class="form-control" placeholder="e.g., 30.2">
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Any notes about this measurement..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Entry</button>
                    </div>
                </form>
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

        // Weight Chart
        var ctx = document.getElementById('weightChart').getContext('2d');
        var weightChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($weight_labels); ?>,
                datasets: [{
                    label: 'Weight (kg)',
                    data: <?php echo json_encode($weight_data); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#764ba2',
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: false
                        },
                        scaleLabel: {
                            display: true,
                            labelString: 'Weight (kg)'
                        }
                    }],
                    xAxes: [{
                        scaleLabel: {
                            display: true,
                            labelString: 'Month'
                        }
                    }]
                },
                tooltips: {
                    mode: 'index',
                    intersect: false
                },
                hover: {
                    mode: 'nearest',
                    intersect: true
                }
            }
        });
    </script>
</body>
</html>
