<?php
// member/measurements.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure only logged‑in members can access this page
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'member') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$functions = new Functions();
$error = '';
$success = '';

// Handle adding a measurement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $measurement_date = $_POST['measurement_date'] ?? '';
    $weight = $_POST['weight'] ?? '';
    $body_fat = $_POST['body_fat'] ?? null;
    $muscle_mass = $_POST['muscle_mass'] ?? null;

    if (empty($measurement_date) || empty($weight)) {
        $error = 'Date and weight are required.';
    } elseif (!is_numeric($weight) || $weight <= 0) {
        $error = 'Please enter a valid weight.';
    } elseif (!empty($body_fat) && (!is_numeric($body_fat) || $body_fat < 0 || $body_fat > 100)) {
        $error = 'Body fat must be a number between 0 and 100.';
    } elseif (!empty($muscle_mass) && (!is_numeric($muscle_mass) || $muscle_mass <= 0)) {
        $error = 'Muscle mass must be a positive number.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO measurements (user_id, measurement_date, weight, body_fat, muscle_mass)
                                    VALUES (:user_id, :date, :weight, :body_fat, :muscle_mass)");
            $stmt->execute([
                ':user_id'   => $user_id,
                ':date'      => $measurement_date,
                ':weight'    => $weight,
                ':body_fat'  => $body_fat !== '' ? $body_fat : null,
                ':muscle_mass' => $muscle_mass !== '' ? $muscle_mass : null
            ]);
            $success = 'Measurement added successfully!';
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle deletion of a measurement
if (isset($_GET['delete'])) {
    $measurement_id = (int)$_GET['delete'];
    if ($measurement_id > 0) {
        try {
            // Ensure the measurement belongs to the logged‑in user
            $stmt = $pdo->prepare("DELETE FROM measurements WHERE id = :id AND user_id = :user_id");
            $stmt->execute([':id' => $measurement_id, ':user_id' => $user_id]);
            if ($stmt->rowCount()) {
                $success = 'Measurement deleted successfully.';
            } else {
                $error = 'Measurement not found or you do not have permission to delete it.';
            }
        } catch (PDOException $e) {
            $error = 'Could not delete measurement.';
        }
    } else {
        $error = 'Invalid measurement ID.';
    }
}

// Fetch all measurements for this member, ordered by date descending
$measurements = [];
$weight_data = [];
$date_labels = [];
try {
    $stmt = $pdo->prepare("SELECT id, measurement_date, weight, body_fat, muscle_mass
                           FROM measurements
                           WHERE user_id = :user_id
                           ORDER BY measurement_date DESC");
    $stmt->execute([':user_id' => $user_id]);
    $measurements = $stmt->fetchAll();

    // Prepare data for the weight chart (chronological order)
    $chart_stmt = $pdo->prepare("SELECT measurement_date, weight
                                 FROM measurements
                                 WHERE user_id = :user_id
                                 ORDER BY measurement_date ASC");
    $chart_stmt->execute([':user_id' => $user_id]);
    $chart_data = $chart_stmt->fetchAll();
    foreach ($chart_data as $row) {
        $date_labels[] = date('M j', strtotime($row['measurement_date']));
        $weight_data[] = (float)$row['weight'];
    }
} catch (PDOException $e) {
    $error = 'Could not load measurements.';
}

// Get user name for display
$user_name = '';
try {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch();
    $user_name = $user['full_name'] ?? 'Member';
} catch (PDOException $e) {
    $user_name = 'Member';
}

$page_title = 'My Measurements - ' . APP_NAME;
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
        /* === Same custom styling as the admin dashboard === */
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:15px 20px;transition:.3s}.list-group-item:last-child{border-bottom:none}.list-group-item:hover{background:#f8f9fa;transform:translateX(5px)}.list-group-item i{color:#667eea;margin-right:10px}.chart-container{position:relative;height:300px;margin:20px 0}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}.btn-outline-primary{border-color:#667eea;color:#667eea}.btn-outline-primary:hover{background:#667eea;border-color:#667eea}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}}
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar (Member version) -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Member Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="active"><a href="measurements.php"><i class="fas fa-chart-line"></i> My Measurements</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="membership.php"><i class="fas fa-id-card"></i> Membership</a></li>
                <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li><a href="classes.php"><i class="fas fa-calendar-alt"></i> My Classes</a></li>
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
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header">
                            <h1><i class="fas fa-chart-line"></i> My Measurements <small>Track your fitness progress</small></h1>
                        </div>
                    </div>
                </div>

                <!-- Weight Trend Chart (if data exists) -->
                <?php if (!empty($weight_data)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line"></i> Weight Trend</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="weightChart"></canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Add Measurement Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-plus-circle"></i> Add New Measurement</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Date <span class="text-danger">*</span></label>
                                        <input type="date" name="measurement_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Weight (kg) <span class="text-danger">*</span></label>
                                        <input type="number" step="0.1" name="weight" class="form-control" required placeholder="e.g., 72.5">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Body Fat (%)</label>
                                        <input type="number" step="0.1" name="body_fat" class="form-control" placeholder="Optional">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Muscle Mass (kg)</label>
                                        <input type="number" step="0.1" name="muscle_mass" class="form-control" placeholder="Optional">
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Measurement</button>
                        </form>
                    </div>
                </div>

                <!-- Measurements Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-history"></i> Measurement History</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($measurements)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No measurements recorded yet.</p>
                                <p>Use the form above to add your first measurement.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover" id="measurementsTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Weight (kg)</th>
                                            <th>Body Fat (%)</th>
                                            <th>Muscle Mass (kg)</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($measurements as $m): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($m['measurement_date'])); ?></td>
                                            <td><?php echo number_format($m['weight'], 1); ?></td>
                                            <td><?php echo $m['body_fat'] !== null ? number_format($m['body_fat'], 1) : '—'; ?></td>
                                            <td><?php echo $m['muscle_mass'] !== null ? number_format($m['muscle_mass'], 1) : '—'; ?></td>
                                            <td>
                                                <a href="?delete=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this measurement?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
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
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
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

            // Initialize DataTable if measurements exist
            if ($('#measurementsTable').length && $('#measurementsTable tbody tr').length) {
                $('#measurementsTable').DataTable({
                    pageLength: 10,
                    order: [[0, 'desc']],
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search..."
                    }
                });
            }

            // Weight chart
            <?php if (!empty($weight_data)): ?>
            var ctx = document.getElementById('weightChart').getContext('2d');
            var weightChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($date_labels); ?>,
                    datasets: [{
                        label: 'Weight (kg)',
                        data: <?php echo json_encode($weight_data); ?>,
                        backgroundColor: 'rgba(102, 126, 234, 0.2)',
                        borderColor: '#667eea',
                        borderWidth: 2,
                        pointBackgroundColor: '#764ba2',
                        pointBorderColor: '#fff',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: true,
                        tension: 0.3
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
                                labelString: 'Date'
                            }
                        }]
                    },
                    tooltips: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(tooltipItem, data) {
                                return data.datasets[tooltipItem.datasetIndex].label + ': ' + tooltipItem.yLabel + ' kg';
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        });

        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }
    </script>
</body>
</html>
<?php
ob_end_flush();
?>