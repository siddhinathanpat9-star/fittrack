<?php
// admin/members/manage_members.php
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

// Handle member update
if (isset($_POST['update_membership'])) {
    try {
        $user_id = $_POST['user_id'];
        $membership_type = $_POST['membership_type'];
        $membership_end = $_POST['membership_end'];
        $assigned_trainer = !empty($_POST['assigned_trainer']) ? $_POST['assigned_trainer'] : null;
        
        $stmt = $pdo->prepare("UPDATE members SET membership_type = ?, membership_end = ?, assigned_trainer_id = ? WHERE user_id = ?");
        $stmt->execute([$membership_type, $membership_end, $assigned_trainer, $user_id]);
        
        Session::setFlash('success', 'Member updated successfully');
        header('Location: manage_members.php');
        exit();
    } catch (Exception $e) {
        $error = "Error updating member: " . $e->getMessage();
    }
}

// Handle member status update
if (isset($_POST['update_status'])) {
    try {
        $user_id = $_POST['user_id'];
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$status, $user_id]);
        
        Session::setFlash('success', 'Member status updated successfully');
        header('Location: manage_members.php');
        exit();
    } catch (Exception $e) {
        $error = "Error updating status: " . $e->getMessage();
    }
}

// Handle member deletion
if (isset($_POST['delete_member'])) {
    try {
        $user_id = $_POST['user_id'];
        
        // Begin transaction to ensure data integrity
        $pdo->beginTransaction();
        
        // Delete related records (adjust according to your database schema)
        // Payments
        $stmt = $pdo->prepare("DELETE FROM payments WHERE member_id = ?");
        $stmt->execute([$user_id]);
        
        // Attendance
        $stmt = $pdo->prepare("DELETE FROM attendance_records WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Workout plans (if table exists)
        $stmt = $pdo->prepare("DELETE FROM workout_plans WHERE member_id = ?");
        $stmt->execute([$user_id]);
        
        // Membership details
        $stmt = $pdo->prepare("DELETE FROM members WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Finally, delete the user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type = 'member'");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        
        Session::setFlash('success', 'Member deleted successfully');
        header('Location: manage_members.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error deleting member: " . $e->getMessage();
    }
}

// Get filter parameters
$membership_filter = $_GET['membership'] ?? '';
$status_filter = $_GET['status'] ?? '';
$trainer_filter = $_GET['trainer'] ?? '';
$search_term = $_GET['search'] ?? '';

// Build query for members
$sql = "SELECT u.*, m.membership_type, m.membership_start, m.membership_end, 
               m.height, m.weight, m.fitness_goals, m.emergency_contact, m.emergency_phone,
               t.full_name as trainer_name, t.id as trainer_id,
               (SELECT COUNT(*) FROM payments WHERE member_id = u.id) as payment_count,
               (SELECT COUNT(*) FROM attendance WHERE user_id = u.id) as attendance_count,
               (SELECT COUNT(*) FROM workout_plans WHERE member_id = u.id) as workout_count
        FROM users u
        JOIN members m ON u.id = m.user_id
        LEFT JOIN users t ON m.assigned_trainer_id = t.id
        WHERE u.user_type = 'member'";

$params = [];

if (!empty($membership_filter)) {
    $sql .= " AND m.membership_type = ?";
    $params[] = $membership_filter;
}

if (!empty($status_filter)) {
    $sql .= " AND u.status = ?";
    $params[] = $status_filter;
}

if (!empty($trainer_filter)) {
    if ($trainer_filter == 'unassigned') {
        $sql .= " AND m.assigned_trainer_id IS NULL";
    } else {
        $sql .= " AND m.assigned_trainer_id = ?";
        $params[] = $trainer_filter;
    }
}

if (!empty($search_term)) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.username LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY u.created_at DESC";

// Get all members
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $members = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading members: " . $e->getMessage();
    $members = [];
}

// Get all trainers for assignment
$trainers = [];
try {
    $stmt = $pdo->query("SELECT u.id, u.full_name FROM users u JOIN trainers t ON u.id = t.user_id WHERE u.status = 'active' ORDER BY u.full_name");
    $trainers = $stmt->fetchAll();
} catch (Exception $e) {
    // Trainers table might not exist
}

// Get statistics
try {
    $total_members = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'member'")->fetchColumn();
    $active_members = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'member' AND status = 'active'")->fetchColumn();
    $expiring_soon = $pdo->query("SELECT COUNT(*) FROM members WHERE membership_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
    $expired = $pdo->query("SELECT COUNT(*) FROM members WHERE membership_end < CURDATE()")->fetchColumn();
    
    $basic_count = $pdo->query("SELECT COUNT(*) FROM members WHERE membership_type = 'basic'")->fetchColumn();
    $premium_count = $pdo->query("SELECT COUNT(*) FROM members WHERE membership_type = 'premium'")->fetchColumn();
    $vip_count = $pdo->query("SELECT COUNT(*) FROM members WHERE membership_type = 'vip'")->fetchColumn();
    
    $new_members = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'member' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
    
} catch (Exception $e) {
    $total_members = $active_members = $expiring_soon = $expired = $basic_count = $premium_count = $vip_count = $new_members = 0;
}

$user_name = Session::userName();
$page_title = 'Manage Members - ' . APP_NAME;
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
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.stats-card small{opacity:.9}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:15px 20px;transition:.3s}.list-group-item:last-child{border-bottom:none}.list-group-item:hover{background:#f8f9fa;transform:translateX(5px)}.list-group-item i{color:#667eea;margin-right:10px}.chart-container{position:relative;height:300px;margin:20px 0}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}.avatar-circle{width:35px;height:35px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:16px}.status-select{width:100px;font-size:0.8rem;padding:0.25rem 0.5rem}.btn-group .btn{padding:0.25rem 0.5rem}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}.stats-card h2{font-size:1.8rem}}
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
                <li class="active">
                    <a href="#membersSubmenu" data-toggle="collapse" aria-expanded="true">
                        <i class="fas fa-users"></i> Members <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse show list-unstyled" id="membersSubmenu">
                        <li class="active"><a href="manage_members.php"><i class="fas fa-list"></i> All Members</a></li>
                        <li><a href="add_member.php"><i class="fas fa-user-plus"></i> Add Member</a></li>
                        <li><a href="../membership/membership_plans.php"><i class="fas fa-tag"></i> Membership Plans</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#trainersSubmenu" data-toggle="collapse">
                        <i class="fas fa-user-tie"></i> Trainers <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="trainersSubmenu">
                        <li><a href="../manage_trainers.php"><i class="fas fa-list"></i> All Trainers</a></li>
                        <li><a href="../add_trainer.php"><i class="fas fa-user-plus"></i> Add Trainer</a></li>
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
                    <!-- Notifications dropdown (placeholder) -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown"><i class="fas fa-bell"></i><span class="badge badge-danger badge-pill">3</span></button>
                        <div class="dropdown-menu dropdown-menu-right" style="width:300px">
                            <div class="dropdown-header bg-light">Notifications</div>
                            <a class="dropdown-item" href="#"><strong>New member registered</strong><br><small class="text-muted">2 minutes ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Payment received</strong><br><small class="text-muted">1 hour ago</small></a>
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
                            <h1><i class="fas fa-user-friends"></i> Manage Members <small>View and manage all gym members</small></h1>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards (stats-card style) -->
                <div class="row">
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="card stats-card bg-primary text-white">
                            <div class="card-body">
                                <div class="card-title">Total Members</div>
                                <h2><?php echo $total_members; ?></h2>
                                <i class="fas fa-users"></i>
                                <small>All members</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <div class="card-title">Active</div>
                                <h2><?php echo $active_members; ?></h2>
                                <i class="fas fa-check-circle"></i>
                                <small>Active members</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <div class="card-title">New This Month</div>
                                <h2><?php echo $new_members; ?></h2>
                                <i class="fas fa-calendar-plus"></i>
                                <small>Joined this month</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="card stats-card bg-warning text-white">
                            <div class="card-body">
                                <div class="card-title">Expiring Soon</div>
                                <h2><?php echo $expiring_soon; ?></h2>
                                <i class="fas fa-clock"></i>
                                <small>Next 7 days</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="card stats-card bg-danger text-white">
                            <div class="card-body">
                                <div class="card-title">Expired</div>
                                <h2><?php echo $expired; ?></h2>
                                <i class="fas fa-exclamation-circle"></i>
                                <small>Memberships expired</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="card stats-card bg-secondary text-white">
                            <div class="card-body">
                                <div class="card-title">Membership Split</div>
                                <h2><?php echo "B:$basic_count P:$premium_count V:$vip_count"; ?></h2>
                                <i class="fas fa-chart-pie"></i>
                                <small>Basic / Premium / VIP</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Card -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-filter"></i> Filter Members</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="form-row">
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Membership</label>
                                <select name="membership" class="form-control">
                                    <option value="">All Types</option>
                                    <option value="basic" <?php echo $membership_filter == 'basic' ? 'selected' : ''; ?>>Basic</option>
                                    <option value="premium" <?php echo $membership_filter == 'premium' ? 'selected' : ''; ?>>Premium</option>
                                    <option value="vip" <?php echo $membership_filter == 'vip' ? 'selected' : ''; ?>>VIP</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Trainer</label>
                                <select name="trainer" class="form-control">
                                    <option value="">All Trainers</option>
                                    <option value="unassigned" <?php echo $trainer_filter == 'unassigned' ? 'selected' : ''; ?>>Unassigned</option>
                                    <?php foreach ($trainers as $trainer): ?>
                                        <option value="<?php echo $trainer['id']; ?>" <?php echo $trainer_filter == $trainer['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($trainer['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Name, email, phone..." 
                                       value="<?php echo htmlspecialchars($search_term); ?>">
                            </div>
                            
                            <div class="col-md-2 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-search"></i> Apply
                                </button>
                            </div>
                            
                            <?php if (!empty($membership_filter) || !empty($status_filter) || !empty($trainer_filter) || !empty($search_term)): ?>
                            <div class="col-12">
                                <a href="manage_members.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-times"></i> Clear Filters
                                </a>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Members Table Card -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list"></i> All Members</h5>
                        <div>
                            <a href="add_member.php" class="btn btn-primary btn-sm mr-2"><i class="fas fa-user-plus"></i> Add New Member</a>
                            <a href="import_members.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-import"></i> Import</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($members)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No members found</h5>
                                <?php if (!empty($membership_filter) || !empty($status_filter) || !empty($trainer_filter) || !empty($search_term)): ?>
                                    <p class="text-muted">Try adjusting your filters</p>
                                    <a href="manage_members.php" class="btn btn-primary">
                                        <i class="fas fa-times"></i> Clear Filters
                                    </a>
                                <?php else: ?>
                                    <a href="add_member.php" class="btn btn-primary mt-3">
                                        <i class="fas fa-user-plus"></i> Add Your First Member
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover" id="membersTable">
                                    <thead>
                                        32
                                            <th>ID</th>
                                            <th>Member</th>
                                            <th>Contact</th>
                                            <th>Membership</th>
                                            <th>Valid Until</th>
                                            <th>Trainer</th>
                                            <th>Activity</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($members as $member): ?>
                                        <tr>
                                            <td><?php echo $member['id']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle bg-<?php 
                                                        echo $member['membership_type'] == 'premium' ? 'success' : 
                                                            ($member['membership_type'] == 'vip' ? 'warning' : 'info'); 
                                                    ?> text-white mr-2">
                                                        <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>
                                                        <br><small class="text-muted">@<?php echo htmlspecialchars($member['username']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <i class="fas fa-envelope fa-fw text-muted"></i> <?php echo htmlspecialchars($member['email']); ?><br>
                                                <i class="fas fa-phone fa-fw text-muted"></i> <?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $member['membership_type'] == 'premium' ? 'success' : 
                                                        ($member['membership_type'] == 'vip' ? 'warning' : 'info'); 
                                                ?> p-2">
                                                    <?php echo ucfirst($member['membership_type'] ?? 'basic'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                if($member['membership_end']) {
                                                    $expiry_date = new DateTime($member['membership_end']);
                                                    $now = new DateTime();
                                                    $days_left = $now->diff($expiry_date)->days;
                                                    
                                                    if ($expiry_date < $now) {
                                                        echo '<span class="badge badge-danger">Expired</span>';
                                                    } elseif ($days_left <= 7) {
                                                        echo '<span class="badge badge-warning">' . $days_left . ' days</span>';
                                                    } else {
                                                        echo '<span class="badge badge-success">' . $expiry_date->format('M d, Y') . '</span>';
                                                    }
                                                } else {
                                                    echo '<span class="badge badge-secondary">N/A</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if($member['trainer_name']): ?>
                                                    <span class="badge badge-info">
                                                        <?php echo htmlspecialchars($member['trainer_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Not Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info" title="Payments">P:<?php echo $member['payment_count']; ?></span>
                                                <span class="badge badge-secondary" title="Attendance">A:<?php echo $member['attendance_count']; ?></span>
                                                <span class="badge badge-success" title="Workouts">W:<?php echo $member['workout_count']; ?></span>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $member['id']; ?>">
                                                    <select name="status" class="form-control form-control-sm status-select" 
                                                            onchange="this.form.submit()"
                                                            style="width: 85px;">
                                                        <option value="active" <?php echo $member['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo $member['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                    </select>
                                                    <input type="hidden" name="update_status" value="1">
                                                </form>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-primary" 
                                                            data-toggle="modal" 
                                                            data-target="#editModal<?php echo $member['id']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="view_member.php?id=<?php echo $member['id']; ?>" 
                                                       class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-danger" 
                                                            data-toggle="modal" 
                                                            data-target="#deleteModal<?php echo $member['id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
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

    <!-- Edit Modals (Bootstrap 4 version) -->
    <?php foreach($members as $member): ?>
    <div class="modal fade" id="editModal<?php echo $member['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editModalLabel<?php echo $member['id']; ?>" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="editModalLabel<?php echo $member['id']; ?>">
                            <i class="fas fa-edit mr-2"></i>
                            Edit Member: <?php echo htmlspecialchars($member['full_name']); ?>
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="user_id" value="<?php echo $member['id']; ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Membership Type</label>
                            <select name="membership_type" class="form-control">
                                <option value="basic" <?php echo $member['membership_type'] == 'basic' ? 'selected' : ''; ?>>Basic</option>
                                <option value="premium" <?php echo $member['membership_type'] == 'premium' ? 'selected' : ''; ?>>Premium</option>
                                <option value="vip" <?php echo $member['membership_type'] == 'vip' ? 'selected' : ''; ?>>VIP</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Membership End Date</label>
                            <input type="date" name="membership_end" class="form-control" 
                                   value="<?php echo $member['membership_end']; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Assign Trainer</label>
                            <select name="assigned_trainer" class="form-control">
                                <option value="">None</option>
                                <?php foreach ($trainers as $trainer): ?>
                                <option value="<?php echo $trainer['id']; ?>" 
                                    <?php echo ($member['trainer_id'] ?? '') == $trainer['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($trainer['full_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_membership" class="btn btn-primary">
                            <i class="fas fa-save mr-2"></i>Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal<?php echo $member['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel<?php echo $member['id']; ?>" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="user_id" value="<?php echo $member['id']; ?>">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteModalLabel<?php echo $member['id']; ?>">
                            <i class="fas fa-exclamation-triangle"></i> Confirm Deletion
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>?</p>
                        <p class="text-danger">This action cannot be undone and will remove all associated data (payments, attendance, workout plans, etc.).</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_member" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Member
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

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

            // Initialize DataTable
            $('#membersTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                language: {
                    search: "<i class='fas fa-search'></i>",
                    searchPlaceholder: "Search members..."
                },
                columnDefs: [
                    { orderable: false, targets: [8] }
                ]
            });

            // Confirmation for status changes (optional - we already have a modal for delete)
            $('.status-select').on('change', function() {
                var newStatus = $(this).val();
                var userName = $(this).closest('tr').find('td:eq(1)').text().trim();
                return confirm('Are you sure you want to change status for ' + userName + ' to ' + newStatus + '?');
            });
        });

        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }
    </script>
</body>
</html>