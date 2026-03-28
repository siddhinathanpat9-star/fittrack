<?php
/**
 * trainer/progress.php
 * Member Progress Tracking - Trainer View
 * Displays and manages member progress measurements with Bootstrap styling
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Check trainer authentication
if (!Session::isLoggedIn() || Session::userType() !== 'trainer') {
    header('Location: ../login.php');
    exit;
}

$trainer_id = Session::userId();
$trainer_name = Session::userName();
$functions = new Functions();
$error = '';
$success = '';
$member = null;
$progress_entries = [];
$latest_progress = null;
$weight_chart_data = [];
$date_chart_labels = [];

// Get member_id from URL
$member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;

if (!$member_id) {
    $error = "No member specified. Please select a member from your list.";
} else {
    try {
        // Fetch member details without trainer_id (since it may not exist)
        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name, u.email, u.username, u.status, u.created_at,
                   m.membership_type
            FROM users u
            LEFT JOIN members m ON u.id = m.user_id
            WHERE u.id = :member_id AND u.user_type = 'member'
        ");
        $stmt->execute(['member_id' => $member_id]);
        $member = $stmt->fetch();

        if (!$member) {
            $error = "Member not found.";
        }
        // Removed authorization check because trainer_id doesn't exist
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle delete progress entry
if (isset($_GET['delete_id']) && $member && !$error) {
    $delete_id = (int)$_GET['delete_id'];
    $confirm = isset($_GET['confirm']) && $_GET['confirm'] == 'yes';
    
    if ($confirm) {
        try {
            // Verify the progress entry belongs to this member
            $stmt = $pdo->prepare("SELECT id FROM progress_tracking WHERE id = :id AND member_id = :member_id");
            $stmt->execute(['id' => $delete_id, 'member_id' => $member_id]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("DELETE FROM progress_tracking WHERE id = :id");
                $stmt->execute(['id' => $delete_id]);
                Session::setFlash('success', 'Progress entry deleted successfully.');
            } else {
                Session::setFlash('danger', 'Progress entry not found or does not belong to this member.');
            }
        } catch (Exception $e) {
            Session::setFlash('danger', 'Failed to delete: ' . $e->getMessage());
        }
        header("Location: progress.php?member_id=" . $member_id);
        exit;
    } else {
        // Show confirmation message inline
        $delete_pending = $delete_id;
    }
}

// Handle add new progress entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_progress' && $member && !$error) {
    $measurement_date = $_POST['measurement_date'] ?? date('Y-m-d');
    $weight = isset($_POST['weight']) ? (float)$_POST['weight'] : null;
    $body_fat = isset($_POST['body_fat']) ? (float)$_POST['body_fat'] : null;
    $muscle_mass = isset($_POST['muscle_mass']) ? (float)$_POST['muscle_mass'] : null;
    $chest = isset($_POST['chest']) ? (float)$_POST['chest'] : null;
    $waist = isset($_POST['waist']) ? (float)$_POST['waist'] : null;
    $hips = isset($_POST['hips']) ? (float)$_POST['hips'] : null;
    $notes = trim($_POST['notes'] ?? '');
    
    $errors = [];
    if ($weight <= 0 && $weight !== null) $errors[] = "Weight must be a positive number.";
    if ($body_fat !== null && ($body_fat < 0 || $body_fat > 100)) $errors[] = "Body fat must be between 0 and 100.";
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO progress_tracking (member_id, recorded_date, weight, body_fat, 
                muscle_mass, chest, waist, hips, notes, created_at)
                VALUES (:member_id, :date, :weight, :body_fat, :muscle_mass, :chest, :waist, :hips, :notes, NOW())
            ");
            $stmt->execute([
                'member_id' => $member_id,
                'date' => $measurement_date,
                'weight' => $weight,
                'body_fat' => $body_fat,
                'muscle_mass' => $muscle_mass,
                'chest' => $chest,
                'waist' => $waist,
                'hips' => $hips,
                'notes' => $notes
            ]);
            Session::setFlash('success', 'Progress entry added successfully.');
            header("Location: progress.php?member_id=" . $member_id);
            exit;
        } catch (Exception $e) {
            $error = "Failed to add progress: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Fetch progress entries if member is valid
if ($member && !$error) {
    try {
        // Get all progress entries for this member
        $stmt = $pdo->prepare("
            SELECT * FROM progress_tracking 
            WHERE member_id = :member_id 
            ORDER BY recorded_date DESC, created_at DESC
        ");
        $stmt->execute(['member_id' => $member_id]);
        $progress_entries = $stmt->fetchAll();
        
        // Get latest progress for stats cards
        if (!empty($progress_entries)) {
            $latest_progress = $progress_entries[0];
        }
        
        // Prepare data for weight chart (last 6 months or all entries)
        $chart_entries = array_reverse($progress_entries);
        foreach ($chart_entries as $entry) {
            if ($entry['weight'] !== null) {
                $date_chart_labels[] = date('M d', strtotime($entry['recorded_date']));
                $weight_chart_data[] = (float)$entry['weight'];
            }
        }
        // Limit to last 10 entries for readability
        if (count($date_chart_labels) > 10) {
            $date_chart_labels = array_slice($date_chart_labels, -10);
            $weight_chart_data = array_slice($weight_chart_data, -10);
        }
    } catch (Exception $e) {
        $error = "Error fetching progress data: " . $e->getMessage();
    }
}

// Calculate initial weight and change for stats
$initial_weight = null;
$weight_change = null;
if (!empty($progress_entries)) {
    $oldest = end($progress_entries);
    $initial_weight = $oldest['weight'];
    $current_weight = $progress_entries[0]['weight'];
    if ($initial_weight && $current_weight) {
        $weight_change = $current_weight - $initial_weight;
    }
}

$page_title = 'Member Progress - ' . htmlspecialchars($member ? $member['full_name'] : 'Trainer') . ' - ' . APP_NAME;
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
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.stats-card small{opacity:.9}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:15px 20px;transition:.3s}.list-group-item:last-child{border-bottom:none}.list-group-item:hover{background:#f8f9fa;transform:translateX(5px)}.list-group-item i{color:#667eea;margin-right:10px}.chart-container{position:relative;height:300px;margin:20px 0}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}.avatar-circle{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:1.2rem}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}.stats-card h2{font-size:1.8rem}}
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
                <li><a href="members.php"><i class="fas fa-users"></i> My Members</a></li>
                <li class="active"><a href="#"><i class="fas fa-chart-line"></i> Member Progress</a></li>
                <li><a href="workout_plans.php"><i class="fas fa-dumbbell"></i> Workout Plans</a></li>
                <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> Schedule</a></li>
                <li><a href="assessments.php"><i class="fas fa-clipboard-list"></i> Assessments</a></li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
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
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown"><i class="fas fa-bell"></i><span class="badge badge-danger badge-pill">2</span></button>
                        <div class="dropdown-menu dropdown-menu-right" style="width:300px">
                            <div class="dropdown-header bg-light">Notifications</div>
                            <a class="dropdown-item" href="#"><strong>Member progress updated</strong><br><small class="text-muted">New measurement recorded</small></a>
                            <a class="dropdown-item" href="#"><strong>Upcoming session</strong><br><small class="text-muted">Training session in 2 hours</small></a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-center" href="#">View all</a>
                        </div>
                    </div>
                    <div class="dropdown ml-3 d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown"><i class="fas fa-user-circle fa-lg"></i><span class="ml-2"><?php echo htmlspecialchars($trainer_name); ?></span></button>
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
                <?php Session::displayFlash(); ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if (isset($delete_pending)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Are you sure you want to delete this progress entry?
                        <a href="?member_id=<?php echo $member_id; ?>&delete_id=<?php echo $delete_pending; ?>&confirm=yes" class="btn btn-sm btn-danger ml-3">Yes, Delete</a>
                        <a href="?member_id=<?php echo $member_id; ?>" class="btn btn-sm btn-secondary ml-2">Cancel</a>
                    </div>
                <?php endif; ?>

                <!-- Page Header with Back Link -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header d-flex justify-content-between align-items-center">
                            <div>
                                <h1><i class="fas fa-chart-line"></i> Member Progress 
                                    <small>
                                        <?php if ($member): ?>
                                            <a href="members.php" class="text-muted"><i class="fas fa-arrow-left"></i> Back to Members</a>
                                        <?php endif; ?>
                                    </small>
                                </h1>
                            </div>
                            <?php if ($member && !$error): ?>
                                <button class="btn btn-primary" data-toggle="modal" data-target="#addProgressModal">
                                    <i class="fas fa-plus-circle"></i> Record New Progress
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($member && !$error): ?>
                    <!-- Member Profile Card (trainer assignment removed) -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-user-circle"></i> Member Profile</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle bg-primary text-white mr-3" style="width:60px;height:60px;font-size:1.8rem;">
                                            <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h3 class="mb-1"><?php echo htmlspecialchars($member['full_name']); ?></h3>
                                            <p class="text-muted mb-0">
                                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($member['email']); ?>
                                            </p>
                                            <p class="text-muted mb-0">
                                                <i class="fas fa-id-card"></i> Membership: <span class="badge badge-info"><?php echo ucfirst($member['membership_type'] ?? 'Standard'); ?></span>
                                                | <i class="fas fa-calendar-alt"></i> Joined: <?php echo date('M d, Y', strtotime($member['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 text-md-right">
                                    <span class="badge badge-<?php echo $member['status'] == 'active' ? 'success' : 'danger'; ?> p-2">
                                        <i class="fas fa-circle"></i> <?php echo ucfirst($member['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stats-card bg-primary text-white">
                                <div class="card-body">
                                    <div class="card-title">Current Weight</div>
                                    <h2><?php echo $latest_progress && $latest_progress['weight'] ? number_format($latest_progress['weight'], 1) : '--'; ?> kg</h2>
                                    <i class="fas fa-weight-scale"></i>
                                    <small>Latest measurement</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stats-card bg-success text-white">
                                <div class="card-body">
                                    <div class="card-title">Weight Change</div>
                                    <h2><?php 
                                        if ($weight_change !== null) {
                                            $change = $weight_change;
                                            $symbol = $change >= 0 ? '+' : '';
                                            echo $symbol . number_format($change, 1) . ' kg';
                                        } else { echo '--'; }
                                    ?></h2>
                                    <i class="fas fa-chart-line"></i>
                                    <small>From first record</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stats-card bg-info text-white">
                                <div class="card-body">
                                    <div class="card-title">Body Fat %</div>
                                    <h2><?php echo $latest_progress && $latest_progress['body_fat'] ? number_format($latest_progress['body_fat'], 1) . '%' : '--'; ?></h2>
                                    <i class="fas fa-percent"></i>
                                    <small>Latest reading</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stats-card bg-warning text-white">
                                <div class="card-body">
                                    <div class="card-title">Total Entries</div>
                                    <h2><?php echo count($progress_entries); ?></h2>
                                    <i class="fas fa-database"></i>
                                    <small>Progress records</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Weight Chart -->
                    <?php if (!empty($weight_chart_data)): ?>
                    <div class="card">
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

                    <!-- Progress History Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-history"></i> Measurement History</h5>
                            <span class="text-muted">Total: <?php echo count($progress_entries); ?> records</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($progress_entries)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No progress records found for this member.</p>
                                    <button class="btn btn-primary" data-toggle="modal" data-target="#addProgressModal">
                                        <i class="fas fa-plus-circle"></i> Add First Measurement
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="progressTable">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Weight (kg)</th>
                                                <th>Body Fat %</th>
                                                <th>Muscle Mass (kg)</th>
                                                <th>Chest (cm)</th>
                                                <th>Waist (cm)</th>
                                                <th>Hips (cm)</th>
                                                <th>Notes</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($progress_entries as $entry): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($entry['recorded_date'])); ?></td>
                                                <td><strong><?php echo $entry['weight'] ? number_format($entry['weight'], 1) : '-'; ?></strong></td>
                                                <td><?php echo $entry['body_fat'] ? number_format($entry['body_fat'], 1) . '%' : '-'; ?></td>
                                                <td><?php echo $entry['muscle_mass'] ? number_format($entry['muscle_mass'], 1) : '-'; ?></td>
                                                <td><?php echo $entry['chest'] ? number_format($entry['chest'], 1) : '-'; ?></td>
                                                <td><?php echo $entry['waist'] ? number_format($entry['waist'], 1) : '-'; ?></td>
                                                <td><?php echo $entry['hips'] ? number_format($entry['hips'], 1) : '-'; ?></td>
                                                <td><?php echo htmlspecialchars(substr($entry['notes'], 0, 50) ?? '-'); ?></td>
                                                <td>
                                                    <a href="?member_id=<?php echo $member_id; ?>&delete_id=<?php echo $entry['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('Are you sure you want to delete this entry?');">
                                                        <i class="fas fa-trash-alt"></i>
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
                <?php elseif (!$error): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> No member selected. Please go to <a href="members.php" class="alert-link">My Members</a> and select a member to view progress.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Progress Modal -->
    <div class="modal fade" id="addProgressModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_progress">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Record New Progress Measurement</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-calendar"></i> Measurement Date</label>
                                    <input type="date" name="measurement_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-weight-scale"></i> Weight (kg)</label>
                                    <input type="number" step="0.1" name="weight" class="form-control" placeholder="e.g., 75.5">
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-percent"></i> Body Fat %</label>
                                    <input type="number" step="0.1" name="body_fat" class="form-control" placeholder="e.g., 18.5">
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-muscle"></i> Muscle Mass (kg)</label>
                                    <input type="number" step="0.1" name="muscle_mass" class="form-control" placeholder="e.g., 32.0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-tshirt"></i> Chest (cm)</label>
                                    <input type="number" step="0.1" name="chest" class="form-control" placeholder="e.g., 98.5">
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-belt"></i> Waist (cm)</label>
                                    <input type="number" step="0.1" name="waist" class="form-control" placeholder="e.g., 82.0">
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-arrows-alt"></i> Hips (cm)</label>
                                    <input type="number" step="0.1" name="hips" class="form-control" placeholder="e.g., 94.0">
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-sticky-note"></i> Notes</label>
                                    <textarea name="notes" class="form-control" rows="3" placeholder="Additional comments..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Measurement</button>
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
            
            // Initialize DataTable
            if ($('#progressTable').length) {
                $('#progressTable').DataTable({
                    pageLength: 10,
                    lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "All"]],
                    order: [[0, 'desc']]
                });
            }

            // Weight Chart
            <?php if (!empty($weight_chart_data)): ?>
            var ctx = document.getElementById('weightChart').getContext('2d');
            var weightChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($date_chart_labels); ?>,
                    datasets: [{
                        label: 'Weight (kg)',
                        data: <?php echo json_encode($weight_chart_data); ?>,
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderColor: '#667eea',
                        borderWidth: 3,
                        pointBackgroundColor: '#764ba2',
                        pointBorderColor: '#fff',
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        tension: 0.2,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        yAxes: [{
                            ticks: {
                                beginAtZero: false,
                                callback: function(value) { return value + ' kg'; }
                            },
                            gridLines: { color: '#e9ecef' }
                        }],
                        xAxes: [{
                            gridLines: { display: false }
                        }]
                    },
                    tooltips: {
                        callbacks: {
                            label: function(tooltipItem, data) {
                                return data.datasets[0].label + ': ' + tooltipItem.yLabel + ' kg';
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