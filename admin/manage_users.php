<?php
// admin/manage_users.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::requireAdmin();

$functions = new Functions();
$error = '';
$success = '';

// Handle user status update
if (isset($_POST['update_status'])) {
    try {
        $user_id = (int)$_POST['user_id'];
        $status = $_POST['status'];
        
        if ($user_id == Session::userId()) {
            throw new Exception("You cannot change your own status");
        }
        
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$status, $user_id]);
        
        Session::setFlash('success', 'User status updated successfully');
        header('Location: manage_users.php');
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle user deletion
if (isset($_GET['delete'])) {
    try {
        $user_id = (int)$_GET['delete'];
        
        if ($user_id == Session::userId()) {
            throw new Exception("You cannot delete your own account");
        }
        
        $stmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_type = $stmt->fetchColumn();
        
        if ($user_type === 'admin') {
            throw new Exception("Cannot delete admin users");
        }
        
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $pdo->commit();
        
        Session::setFlash('success', 'User deleted successfully');
        header('Location: manage_users.php');
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error deleting user: " . $e->getMessage();
    }
}

// Get filter parameters
$user_type_filter = $_GET['user_type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search_term = $_GET['search'] ?? '';

// Build query
$sql = "SELECT u.*, 
        CASE 
            WHEN u.user_type = 'member' THEN m.membership_type 
            WHEN u.user_type = 'trainer' THEN t.specialization 
            ELSE 'N/A' 
        END as extra_info,
        CASE 
            WHEN u.user_type = 'member' THEN m.membership_end 
            ELSE NULL 
        END as membership_end
        FROM users u
        LEFT JOIN members m ON u.id = m.user_id
        LEFT JOIN trainers t ON u.id = t.user_id
        WHERE 1=1";

$params = [];

if (!empty($user_type_filter)) {
    $sql .= " AND u.user_type = ?";
    $params[] = $user_type_filter;
}

if (!empty($status_filter)) {
    $sql .= " AND u.status = ?";
    $params[] = $status_filter;
}

if (!empty($search_term)) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY u.created_at DESC";

// Get users
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading users: " . $e->getMessage();
    $users = [];
}

// Get counts for statistics
try {
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_admins = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'admin'")->fetchColumn();
    $total_members = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'member'")->fetchColumn();
    $total_trainers = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'trainer'")->fetchColumn();
    $active_users = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
    $inactive_users = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'inactive'")->fetchColumn();
} catch (Exception $e) {
    $total_users = $total_admins = $total_members = $total_trainers = $active_users = $inactive_users = 0;
}

$user_name = Session::userName();
$page_title = 'Manage Users - ' . APP_NAME;
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
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.stats-card small{opacity:.9}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:15px 20px;transition:.3s}.list-group-item:last-child{border-bottom:none}.list-group-item:hover{background:#f8f9fa;transform:translateX(5px)}.list-group-item i{color:#667eea;margin-right:10px}.chart-container{position:relative;height:300px;margin:20px 0}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}.avatar-circle{width:35px;height:35px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:16px}.status-select{width:100px;font-size:0.8rem;padding:0.25rem 0.5rem}.btn-group .btn{padding:0.25rem 0.5rem}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}.stats-card h2{font-size:1.8rem}.status-select{width:100%}}
    </style>
</head>
<body>
    <div class="loading-spinner" id="loadingSpinner"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>
    <div class="wrapper">
        <!-- Sidebar (matching the new design) -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Administrator Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="active"><a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="members/manage_members.php"><i class="fas fa-user"></i> Members</a></li>
                <li><a href="manage_trainers.php"><i class="fas fa-chalkboard-teacher"></i> Trainers</a></li>
                <li><a href="classes/manage_classes.php"><i class="fas fa-calendar-alt"></i> Classes</a></li>
                <li><a href="payments/manage_payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li><a href="membership/membership_plans.php"><i class="fas fa-id-card"></i> Membership Plans</a></li>
                <li><a href="notifications/send_notification.php"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
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
                            <a class="dropdown-item" href="#"><strong>New user registered</strong><br><small class="text-muted">2 minutes ago</small></a>
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
                            <h1><i class="fas fa-users"></i> Manage Users <small>View and manage all system users</small></h1>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards (styled as stats-card) -->
                <div class="row">
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="card stats-card bg-primary text-white">
                            <div class="card-body">
                                <div class="card-title">Total Users</div>
                                <h2><?php echo number_format($total_users); ?></h2>
                                <i class="fas fa-users"></i>
                                <small>All accounts</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <div class="card-title">Active</div>
                                <h2><?php echo number_format($active_users); ?></h2>
                                <i class="fas fa-check-circle"></i>
                                <small>Active accounts</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="card stats-card bg-danger text-white">
                            <div class="card-body">
                                <div class="card-title">Inactive</div>
                                <h2><?php echo number_format($inactive_users); ?></h2>
                                <i class="fas fa-user-slash"></i>
                                <small>Inactive accounts</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <div class="card-title">Members</div>
                                <h2><?php echo number_format($total_members); ?></h2>
                                <i class="fas fa-user"></i>
                                <small>Gym members</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="card stats-card bg-warning text-white">
                            <div class="card-body">
                                <div class="card-title">Trainers</div>
                                <h2><?php echo number_format($total_trainers); ?></h2>
                                <i class="fas fa-chalkboard-teacher"></i>
                                <small>Fitness trainers</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="card stats-card bg-secondary text-white">
                            <div class="card-body">
                                <div class="card-title">Admins</div>
                                <h2><?php echo number_format($total_admins); ?></h2>
                                <i class="fas fa-user-cog"></i>
                                <small>Administrators</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Card -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-filter"></i> Filter Users</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="form-row">
                            <div class="col-md-3 mb-3">
                                <label for="user_type" class="form-label">User Type</label>
                                <select class="form-control" id="user_type" name="user_type">
                                    <option value="">All Types</option>
                                    <option value="admin" <?php echo $user_type_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="trainer" <?php echo $user_type_filter == 'trainer' ? 'selected' : ''; ?>>Trainer</option>
                                    <option value="member" <?php echo $user_type_filter == 'member' ? 'selected' : ''; ?>>Member</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Name, email, username, phone..." 
                                       value="<?php echo htmlspecialchars($search_term); ?>">
                            </div>
                            
                            <div class="col-md-2 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-search"></i> Apply Filters
                                </button>
                            </div>
                            
                            <?php if (!empty($user_type_filter) || !empty($status_filter) || !empty($search_term)): ?>
                            <div class="col-12">
                                <a href="manage_users.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-times"></i> Clear Filters
                                </a>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Users Table Card -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list"></i> All Users</h5>
                        <div>
                            <a href="add_user.php" class="btn btn-primary btn-sm mr-2"><i class="fas fa-user-plus"></i> Add New</a>
                            <a href="import_users.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-import"></i> Import</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($users)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No users found</h5>
                                <?php if (!empty($user_type_filter) || !empty($status_filter) || !empty($search_term)): ?>
                                    <p class="text-muted">Try adjusting your filters</p>
                                    <a href="manage_users.php" class="btn btn-primary">
                                        <i class="fas fa-times"></i> Clear Filters
                                    </a>
                                <?php else: ?>
                                    <a href="add_user.php" class="btn btn-primary mt-3">
                                        <i class="fas fa-user-plus"></i> Add Your First User
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover" id="usersTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Type</th>
                                            <th>Details</th>
                                            <th>Joined</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle bg-<?php 
                                                        echo $user['user_type'] == 'admin' ? 'danger' : 
                                                            ($user['user_type'] == 'trainer' ? 'success' : 'info'); 
                                                    ?> text-white mr-2">
                                                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $user['user_type'] == 'admin' ? 'danger' : 
                                                        ($user['user_type'] == 'trainer' ? 'success' : 'info'); 
                                                ?>">
                                                    <?php echo ucfirst($user['user_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($user['extra_info'])): ?>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($user['extra_info']); ?>
                                                    </small>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($user['membership_end'])): ?>
                                                    <?php 
                                                    $expiry = new DateTime($user['membership_end']);
                                                    $now = new DateTime();
                                                    $days_left = $now->diff($expiry)->days;
                                                    
                                                    if ($expiry < $now) {
                                                        echo '<br><span class="badge badge-danger mt-1">Expired</span>';
                                                    } elseif ($days_left <= 7) {
                                                        echo '<br><span class="badge badge-warning mt-1">' . $days_left . ' days left</span>';
                                                    }
                                                    ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <?php if ($user['id'] != Session::userId()): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <select name="status" class="form-control form-control-sm status-select" 
                                                            onchange="this.form.submit()" 
                                                            data-user-id="<?php echo $user['id']; ?>">
                                                        <option value="active" <?php echo $user['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo $user['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                    </select>
                                                    <input type="hidden" name="update_status" value="1">
                                                </form>
                                                <?php else: ?>
                                                <span class="badge badge-<?php echo $user['status'] == 'active' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($user['status']); ?> (You)
                                                </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-sm btn-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="view_user.php?id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-sm btn-info" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($user['id'] != Session::userId() && $user['user_type'] != 'admin'): ?>
                                                    <a href="?delete=<?php echo $user['id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($user['user_type'] == 'member'): ?>
                                                    <a href="members/renew_membership.php?id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-sm btn-warning" title="Renew Membership">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </a>
                                                    <?php endif; ?>
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

                <!-- Bulk Actions Card -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-tasks"></i> Bulk Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-6 mb-2">
                                <a href="export_users.php?<?php echo http_build_query($_GET); ?>" class="btn btn-outline-success btn-block py-3">
                                    <i class="fas fa-file-excel fa-2x mb-2"></i><br>Export to CSV
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <a href="send_bulk_email.php" class="btn btn-outline-info btn-block py-3">
                                    <i class="fas fa-envelope fa-2x mb-2"></i><br>Send Email
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <a href="notifications/send_notification.php" class="btn btn-outline-warning btn-block py-3">
                                    <i class="fas fa-bell fa-2x mb-2"></i><br>Send Notification
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <a href="print_users.php" target="_blank" class="btn btn-outline-secondary btn-block py-3">
                                    <i class="fas fa-print fa-2x mb-2"></i><br>Print List
                                </a>
                            </div>
                        </div>
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
            $('#usersTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                language: {
                    search: "<i class='fas fa-search'></i>",
                    searchPlaceholder: "Search in table...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ users",
                    infoEmpty: "Showing 0 to 0 of 0 users",
                    infoFiltered: "(filtered from _MAX_ total users)"
                },
                columnDefs: [
                    { orderable: false, targets: [9] }
                ]
            });

            // Confirmation for status changes
            $('.status-select').on('change', function() {
                var userId = $(this).data('user-id');
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