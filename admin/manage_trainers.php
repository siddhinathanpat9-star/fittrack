<?php
// admin/manage_trainers.php
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

// Handle trainer deletion (via POST for modal confirmation)
if (isset($_POST['delete_trainer'])) {
    try {
        $trainer_id = (int)$_POST['trainer_id'];

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
        if (isset($pdo) && $pdo->inTransaction()) {
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

// Calculate statistics
$total_trainers = count($trainers);
$active_trainers = 0;
$inactive_trainers = 0;
$total_classes = 0;
$total_assigned_members = 0;
foreach ($trainers as $t) {
    if ($t['status'] == 'active') $active_trainers++;
    else $inactive_trainers++;
    $total_classes += $t['class_count'];
    $total_assigned_members += $t['member_count'];
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
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.stats-card small{opacity:.9}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:15px 20px;transition:.3s}.list-group-item:last-child{border-bottom:none}.list-group-item:hover{background:#f8f9fa;transform:translateX(5px)}.list-group-item i{color:#667eea;margin-right:10px}.chart-container{position:relative;height:300px;margin:20px 0}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}.stats-card h2{font-size:1.8rem}}
    </style>
</head>
<body>
    <div class="loading-spinner" id="loadingSpinner"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>
    <div class="wrapper">
        <!-- Sidebar (matching the dashboard) -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Administrator Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="members/manage_members.php"><i class="fas fa-user"></i> Members</a></li>
                <li class="active"><a href="manage_trainers.php"><i class="fas fa-chalkboard-teacher"></i> Trainers</a></li>
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
                    <!-- Notifications dropdown (static example) -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown"><i class="fas fa-bell"></i><span class="badge badge-danger badge-pill">3</span></button>
                        <div class="dropdown-menu dropdown-menu-right" style="width:300px">
                            <div class="dropdown-header bg-light">Notifications</div>
                            <a class="dropdown-item" href="#"><strong>New trainer registered</strong><br><small class="text-muted">2 minutes ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Class scheduled</strong><br><small class="text-muted">1 hour ago</small></a>
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
                            <h1><i class="fas fa-chalkboard-teacher"></i> Manage Trainers <small>View and manage all fitness trainers</small></h1>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards (6 cards, matching dashboard) -->
                <div class="row">
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="card stats-card bg-primary text-white">
                            <div class="card-body">
                                <div class="card-title">Total Trainers</div>
                                <h2><?php echo $total_trainers; ?></h2>
                                <i class="fas fa-chalkboard-teacher"></i>
                                <small>All trainers</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <div class="card-title">Active</div>
                                <h2><?php echo $active_trainers; ?></h2>
                                <i class="fas fa-check-circle"></i>
                                <small>Currently active</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <div class="card-title">Active Classes</div>
                                <h2><?php echo $total_classes; ?></h2>
                                <i class="fas fa-calendar-alt"></i>
                                <small>Classes scheduled</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="card stats-card bg-warning text-white">
                            <div class="card-body">
                                <div class="card-title">Assigned Members</div>
                                <h2><?php echo $total_assigned_members; ?></h2>
                                <i class="fas fa-users"></i>
                                <small>Members under trainers</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="card stats-card bg-secondary text-white">
                            <div class="card-body">
                                <div class="card-title">Inactive</div>
                                <h2><?php echo $inactive_trainers; ?></h2>
                                <i class="fas fa-user-slash"></i>
                                <small>Inactive trainers</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="card stats-card bg-dark text-white">
                            <div class="card-body">
                                <div class="card-title">System</div>
                                <h2>Online</h2>
                                <i class="fas fa-server"></i>
                                <small>Status</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trainers Table Card -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list"></i> All Trainers</h5>
                        <div>
                            <a href="trainers/add_trainer.php" class="btn btn-primary btn-sm"><i class="fas fa-user-plus"></i> Add New Trainer</a>
                            <a href="trainer/assign_members.php" class="btn btn-outline-info btn-sm ml-2"><i class="fas fa-user-tag"></i> Assign Members</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($trainers)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-chalkboard-teacher fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No trainers found</h5>
                                <a href="trainers/add_trainer.php" class="btn btn-primary mt-3">
                                    <i class="fas fa-user-plus"></i> Add First Trainer
                                </a>
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
                                         '</
                                    </thead>
                                    <tbody>
                                        <?php foreach ($trainers as $trainer): ?>
                                        <tr>
                                            <td><?php echo $trainer['id']; ?></td>
                                             <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle bg-success text-white mr-2" style="width:35px;height:35px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;">
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
                                                        <button class="btn btn-sm btn-danger" title="Delete" data-toggle="modal" data-target="#deleteModal<?php echo $trainer['id']; ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
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

                <!-- Quick Actions Card (optional) -->
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
                                <a href="trainer/assign_members.php" class="btn btn-outline-warning btn-block py-3">
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

    <!-- Delete Confirmation Modals (for each trainer) -->
    <?php foreach ($trainers as $trainer): ?>
        <?php if ($trainer['id'] != Session::userId()): ?>
        <div class="modal fade" id="deleteModal<?php echo $trainer['id']; ?>" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="trainer_id" value="<?php echo $trainer['id']; ?>">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h5>
                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($trainer['full_name']); ?></strong>?</p>
                            <?php if ($trainer['member_count'] > 0): ?>
                                <p class="text-danger">This trainer currently has <?php echo $trainer['member_count']; ?> assigned members. They must be reassigned before deletion.</p>
                            <?php else: ?>
                                <p class="text-danger">This action cannot be undone.</p>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" name="delete_trainer" class="btn btn-danger" <?php echo $trainer['member_count'] > 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-trash"></i> Delete Trainer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>

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
            $('#trainersTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                language: {
                    search: "<i class='fas fa-search'></i>",
                    searchPlaceholder: "Search trainers..."
                },
                columnDefs: [
                    { orderable: false, targets: [10] } // actions column
                ]
            });
            // Confirm status change
            $('.status-select').on('change', function() {
                var newStatus = $(this).val();
                var trainerName = $(this).closest('tr').find('td:eq(1)').text().trim();
                return confirm('Are you sure you want to change status for ' + trainerName + ' to ' + newStatus + '?');
            });
        });
        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }
    </script>
</body>
</html>