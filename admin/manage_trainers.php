<?php
// admin/manage_trainers.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Fix the path - includes folder is outside admin folder
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Now use the Session class from the root includes
Session::requireAdmin();

// Initialize functions
$functions = new Functions();
$error = '';
$success = '';

// Handle trainer status update
if (isset($_POST['update_status'])) {
    try {
        $trainer_id = $_POST['trainer_id'];
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND user_type = 'trainer'");
        $stmt->execute([$status, $trainer_id]);
        
        Session::setFlash('success', 'Trainer status updated successfully');
        header('Location: manage_trainers.php');
        exit();
    } catch (Exception $e) {
        $error = "Error updating trainer: " . $e->getMessage();
    }
}

// Handle trainer deletion
if (isset($_GET['delete'])) {
    try {
        $trainer_id = (int)$_GET['delete'];
        
        // Check if trainer has assigned members
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE assigned_trainer_id = ?");
        $stmt->execute([$trainer_id]);
        $member_count = $stmt->fetchColumn();
        
        if ($member_count > 0) {
            Session::setFlash('warning', "Cannot delete trainer. They have $member_count assigned members.");
        } else {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Delete trainer record first
            $stmt = $pdo->prepare("DELETE FROM trainers WHERE user_id = ?");
            $stmt->execute([$trainer_id]);
            
            // Delete user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type = 'trainer'");
            $stmt->execute([$trainer_id]);
            
            $pdo->commit();
            
            Session::setFlash('success', 'Trainer deleted successfully');
        }
        header('Location: manage_trainers.php');
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error deleting trainer: " . $e->getMessage();
    }
}

// Get all trainers
try {
    $sql = "SELECT u.*, t.specialization, t.experience_years, t.hourly_rate, t.qualification, t.availability,
            (SELECT COUNT(*) FROM members WHERE assigned_trainer_id = u.id) as member_count,
            (SELECT COUNT(*) FROM classes WHERE trainer_id = u.id AND status = 'active') as class_count
            FROM users u
            LEFT JOIN trainers t ON u.id = t.user_id
            WHERE u.user_type = 'trainer'
            ORDER BY u.created_at DESC";
    
    $trainers = $pdo->query($sql)->fetchAll();
} catch (Exception $e) {
    $error = "Error loading trainers: " . $e->getMessage();
    $trainers = [];
}

$user_name = Session::userName();
$page_title = 'Manage Trainers - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 4, Font Awesome, DataTables -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:250px;max-width:250px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:all 0.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-250px}#sidebar .sidebar-header{padding:20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.5rem;font-weight:600}#sidebar ul.components{padding:15px 0}#sidebar ul li a{padding:12px 20px;font-size:0.95rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:20px;text-align:center}#sidebar ul ul a{padding-left:40px!important;font-size:.85rem!important;background:rgba(0,0,0,0.1)}#sidebar .sidebar-footer{padding:15px;border-top:1px solid rgba(255,255,255,0.1)}#content{width:calc(100% - 250px);padding:20px;min-height:100vh;transition:all 0.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:20px;padding:10px 20px}.page-header{padding-bottom:15px;margin:0 0 25px;border-bottom:3px solid #667eea}.page-header h1{font-size:1.8rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:0.9rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:20px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:20px}.stats-card .card-title{font-size:.8rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:5px}.stats-card h2{font-size:1.8rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:2.5rem;opacity:.3;position:absolute;bottom:10px;right:15px}.stats-card small{opacity:.9}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:25px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:15px 20px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333;font-size:1.2rem}.card-header h5 i{color:#667eea;margin-right:8px}.card-body{padding:20px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.75rem;letter-spacing:.5px;padding:12px 8px}.table tbody td{padding:12px 8px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:5px 8px;border-radius:20px;font-weight:500;font-size:.7rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.badge-secondary{background:#e2e3e5;color:#383d41}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:12px 20px;transition:.3s}.list-group-item:hover{background:#f8f9fa;transform:translateX(5px)}.list-group-item i{color:#667eea;margin-right:10px}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:12px 20px;margin-bottom:20px}.avatar-circle{width:35px;height:35px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:16px}.status-select{width:100px;font-size:0.75rem;padding:0.2rem 0.5rem;height:auto}.btn-group .btn{padding:0.2rem 0.5rem}.btn-sm i{font-size:0.85rem}@media(max-width:992px){#sidebar{min-width:200px;max-width:200px}#content{width:calc(100% - 200px)}}@media(max-width:768px){#sidebar{margin-left:-200px}#sidebar.active{margin-left:0}#content{width:100%;padding:15px}.page-header h1{font-size:1.5rem}.stats-card h2{font-size:1.5rem}.stats-card i{font-size:2rem}}
    </style>
</head>
<body>
    <div class="loading-spinner" id="loadingSpinner"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-2x mb-2"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p class="small mb-0">Administrator Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li>
                    <a href="#membersSubmenu" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-users"></i> Members <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="membersSubmenu">
                        <li><a href="members/manage_members.php"><i class="fas fa-list"></i> All Members</a></li>
                        <li><a href="members/add_member.php"><i class="fas fa-user-plus"></i> Add Member</a></li>
                        <li><a href="membership/membership_plans.php"><i class="fas fa-tag"></i> Membership Plans</a></li>
                    </ul>
                </li>
                <li class="active">
                    <a href="#trainersSubmenu" data-toggle="collapse" aria-expanded="true">
                        <i class="fas fa-user-tie"></i> Trainers <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse show list-unstyled" id="trainersSubmenu">
                        <li class="active"><a href="manage_trainers.php"><i class="fas fa-list"></i> All Trainers</a></li>
                        <li><a href="trainers/add_trainer.php"><i class="fas fa-user-plus"></i> Add Trainer</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#classesSubmenu" data-toggle="collapse">
                        <i class="fas fa-calendar-alt"></i> Classes <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="classesSubmenu">
                        <li><a href="classes/manage_classes.php"><i class="fas fa-list"></i> All Classes</a></li>
                        <li><a href="classes/add_class.php"><i class="fas fa-plus-circle"></i> Add Class</a></li>
                        <li><a href="classes/class_schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
                    </ul>
                </li>
                <li><a href="payments/manage_payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li><a href="attendance.php"><i class="fas fa-clock"></i> Attendance</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
            <div class="sidebar-footer">
                <a href="#" onclick="confirmLogout(event)" class="btn btn-danger btn-block btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-custom">
                <button type="button" id="sidebarCollapse" class="btn btn-outline-primary btn-sm"><i class="fas fa-bars"></i> Menu</button>
                <div class="ml-auto d-flex align-items-center">
                    <!-- Notifications dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-light btn-sm dropdown-toggle" data-toggle="dropdown"><i class="fas fa-bell"></i><span class="badge badge-danger badge-pill">3</span></button>
                        <div class="dropdown-menu dropdown-menu-right" style="width:280px">
                            <div class="dropdown-header bg-light">Notifications</div>
                            <a class="dropdown-item" href="#"><strong>New trainer registered</strong><br><small class="text-muted">2 minutes ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Class scheduled</strong><br><small class="text-muted">1 hour ago</small></a>
                            <a class="dropdown-item" href="#"><strong>5 memberships expiring soon</strong><br><small class="text-muted">3 hours ago</small></a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-center" href="#">View all</a>
                        </div>
                    </div>
                    <!-- User dropdown -->
                    <div class="dropdown ml-2 d-inline-block">
                        <button class="btn btn-light btn-sm dropdown-toggle" data-toggle="dropdown"><i class="fas fa-user-circle fa-lg"></i><span class="ml-1 d-none d-sm-inline"><?php echo htmlspecialchars($user_name); ?></span></button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                            <a class="dropdown-item" href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid px-0">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php Session::displayFlash(); ?>

                <!-- Page Header -->
                <div class="page-header">
                    <h1><i class="fas fa-chalkboard-teacher"></i> Manage Trainers <small>View and manage all fitness trainers</small></h1>
                </div>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-primary text-white">
                            <div class="card-body">
                                <div class="card-title">Total Trainers</div>
                                <h2><?php echo count($trainers); ?></h2>
                                <i class="fas fa-chalkboard-teacher"></i>
                                <small>All trainers</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <div class="card-title">Active Trainers</div>
                                <h2><?php 
                                    $active_count = 0;
                                    foreach($trainers as $t) {
                                        if($t['status'] == 'active') $active_count++;
                                    }
                                    echo $active_count;
                                ?></h2>
                                <i class="fas fa-check-circle"></i>
                                <small>Currently active</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <div class="card-title">Total Classes</div>
                                <h2><?php 
                                    $class_total = 0;
                                    foreach($trainers as $t) {
                                        $class_total += $t['class_count'];
                                    }
                                    echo $class_total;
                                ?></h2>
                                <i class="fas fa-calendar-alt"></i>
                                <small>Active classes</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stats-card bg-warning text-white">
                            <div class="card-body">
                                <div class="card-title">Assigned Members</div>
                                <h2><?php 
                                    $member_total = 0;
                                    foreach($trainers as $t) {
                                        $member_total += $t['member_count'];
                                    }
                                    echo $member_total;
                                ?></h2>
                                <i class="fas fa-users"></i>
                                <small>Members under trainers</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trainers Table Card -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list"></i> All Trainers</h5>
                        <a href="trainers/add_trainer.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add New Trainer</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($trainers)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-chalkboard-teacher fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No trainers found</h5>
                                <a href="trainers/add_trainer.php" class="btn btn-primary mt-3"><i class="fas fa-plus"></i> Add First Trainer</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover" id="trainersTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Specialization</th>
                                            <th>Experience</th>
                                            <th>Hourly Rate (₹)</th>
                                            <th>Members</th>
                                            <th>Classes</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($trainers as $trainer): ?>
                                        <tr>
                                            <td><?php echo $trainer['id']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle bg-success text-white mr-2">
                                                        <?php echo strtoupper(substr($trainer['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <strong><?php echo htmlspecialchars($trainer['full_name']); ?></strong>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($trainer['email']); ?></td>
                                            <td><?php echo htmlspecialchars($trainer['phone'] ?? 'N/A'); ?></td>
                                            <td><span class="badge badge-info"><?php echo htmlspecialchars($trainer['specialization'] ?? 'General'); ?></span></td>
                                            <td><?php echo $trainer['experience_years'] ?? 0; ?> yrs</td>
                                            <td>₹<?php echo number_format($trainer['hourly_rate'] ?? 0, 2); ?></td>
                                            <td><span class="badge badge-<?php echo $trainer['member_count'] > 0 ? 'success' : 'secondary'; ?>"><?php echo $trainer['member_count']; ?></span></td>
                                            <td><span class="badge badge-<?php echo $trainer['class_count'] > 0 ? 'success' : 'secondary'; ?>"><?php echo $trainer['class_count']; ?></span></td>
                                            <td>
                                                <?php if ($trainer['id'] != Session::userId()): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="trainer_id" value="<?php echo $trainer['id']; ?>">
                                                    <select name="status" class="form-control form-control-sm status-select" onchange="this.form.submit()">
                                                        <option value="active" <?php echo $trainer['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo $trainer['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                    </select>
                                                    <input type="hidden" name="update_status" value="1">
                                                </form>
                                                <?php else: ?>
                                                <span class="badge badge-<?php echo $trainer['status'] == 'active' ? 'success' : 'danger'; ?>"><?php echo ucfirst($trainer['status']); ?> (You)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="trainers/edit_trainer.php?id=<?php echo $trainer['id']; ?>" class="btn btn-sm btn-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                                    <a href="trainers/view_trainer.php?id=<?php echo $trainer['id']; ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                                                    <?php if ($trainer['id'] != Session::userId()): ?>
                                                    <a href="?delete=<?php echo $trainer['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this trainer? This action cannot be undone.')"><i class="fas fa-trash"></i></a>
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

                <!-- Quick Actions Card -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-6 mb-3">
                                <a href="trainers/add_trainer.php" class="btn btn-outline-primary btn-block py-3">
                                    <i class="fas fa-user-plus fa-2x mb-2"></i><br>Add Trainer
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="classes/add_class.php" class="btn btn-outline-success btn-block py-3">
                                    <i class="fas fa-plus-circle fa-2x mb-2"></i><br>Add Class
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="#" class="btn btn-outline-warning btn-block py-3" onclick="alert('This feature is coming soon!')">
                                    <i class="fas fa-user-tag fa-2x mb-2"></i><br>Assign Members
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <a href="notifications/send_notification.php?to=trainers" class="btn btn-outline-info btn-block py-3">
                                    <i class="fas fa-bell fa-2x mb-2"></i><br>Notify All
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="logoutModalLabel"><i class="fas fa-sign-out-alt"></i> Confirm Logout</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to logout?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times"></i> Cancel</button>
                    <a href="../logout.php" class="btn btn-danger btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>

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

            $('#trainersTable').DataTable({
                pageLength: 10,
                order: [[0, 'desc']],
                language: {
                    search: "<i class='fas fa-search'></i>",
                    searchPlaceholder: "Search trainers...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ trainers",
                    infoEmpty: "Showing 0 to 0 of 0 trainers",
                    infoFiltered: "(filtered from _MAX_ total trainers)"
                },
                columnDefs: [{ orderable: false, targets: [10] }]
            });
        });

        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }
    </script>
</body>
</html>