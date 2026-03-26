<?php
// admin/assign_members.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Correct path: from admin/ to root includes
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

Session::requireAdmin();

$error = '';
$success = '';

// Fetch all active trainers
$trainers = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, t.specialization
        FROM users u
        JOIN trainers t ON u.id = t.user_id
        WHERE u.user_type = 'trainer' AND u.status = 'active'
        ORDER BY u.full_name
    ");
    $stmt->execute();
    $trainers = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading trainers: " . $e->getMessage();
}

// Fetch all active members
$members = [];
try {
    $sql = "
        SELECT u.id, u.full_name, u.email,
               m.assigned_trainer_id,
               t.full_name as assigned_trainer_name
        FROM users u
        LEFT JOIN members m ON u.id = m.user_id
        LEFT JOIN users t ON m.assigned_trainer_id = t.id
        WHERE u.user_type = 'member' AND u.status = 'active'
        ORDER BY u.full_name
    ";
    $stmt = $pdo->query($sql);
    $members = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading members: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_members'])) {
    $trainer_id = (int)$_POST['trainer_id'];
    $member_ids = isset($_POST['member_ids']) ? $_POST['member_ids'] : [];

    if ($trainer_id <= 0) {
        $error = "Please select a trainer.";
    } elseif (empty($member_ids)) {
        $error = "Please select at least one member to assign.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE members SET assigned_trainer_id = ? WHERE user_id = ?");
            foreach ($member_ids as $member_id) {
                // Ensure member exists in members table
                $check = $pdo->prepare("SELECT user_id FROM members WHERE user_id = ?");
                $check->execute([$member_id]);
                if ($check->rowCount() == 0) {
                    // Insert member record if missing
                    $ins = $pdo->prepare("INSERT INTO members (user_id, assigned_trainer_id) VALUES (?, ?)");
                    $ins->execute([$member_id, $trainer_id]);
                } else {
                    $stmt->execute([$trainer_id, $member_id]);
                }
            }

            $pdo->commit();
            $success = "Members assigned successfully to the selected trainer.";

            // Refresh the member list
            $stmt = $pdo->query($sql);
            $members = $stmt->fetchAll();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error assigning members: " . $e->getMessage();
        }
    }
}

$user_name = Session::userName();
$page_title = 'Assign Members to Trainer - ' . APP_NAME;
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
        /* Sidebar and main content styles (same as dashboard) */
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.avatar-circle{width:35px;height:35px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;background:linear-gradient(135deg,#667eea,#764ba2);color:white}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}}
    </style>
</head>
<body>
    <div class="loading-spinner" id="loadingSpinner"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>
    <div class="wrapper">
        <!-- Sidebar (same as dashboard) -->
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
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header">
                            <h1><i class="fas fa-user-tag"></i> Assign Members to Trainer</h1>
                        </div>
                    </div>
                </div>

                <!-- Assignment Form -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chalkboard-teacher"></i> Select Trainer and Members</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="trainer_id" class="form-label">Choose Trainer</label>
                                    <select name="trainer_id" id="trainer_id" class="form-control" required>
                                        <option value="">-- Select a Trainer --</option>
                                        <?php foreach ($trainers as $trainer): ?>
                                            <option value="<?php echo $trainer['id']; ?>">
                                                <?php echo htmlspecialchars($trainer['full_name']); ?> (<?php echo htmlspecialchars($trainer['specialization'] ?? 'General'); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Select Members to Assign</label>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="membersTable">
                                        <thead>
                                            <tr>
                                                <th><input type="checkbox" id="selectAll"></th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Current Trainer</th>
                                              </thead>
                                        <tbody>
                                            <?php foreach ($members as $member): ?>
                                             <tr>
                                                <td><input type="checkbox" name="member_ids[]" value="<?php echo $member['id']; ?>" class="member-checkbox"></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-circle mr-2"><?php echo strtoupper(substr($member['full_name'],0,1)); ?></div>
                                                        <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                                <td>
                                                    <?php if ($member['assigned_trainer_id']): ?>
                                                        <span class="badge badge-info"><?php echo htmlspecialchars($member['assigned_trainer_name']); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Not Assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($members)): ?>
                                            <tr><td colspan="4" class="text-center">No active members found.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <button type="submit" name="assign_members" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-check"></i> Assign Selected Members
                            </button>
                            <a href="manage_trainers.php" class="btn btn-secondary btn-lg ml-2">
                                <i class="fas fa-arrow-left"></i> Back to Trainers
                            </a>
                        </form>
                    </div>
                </div>

                <!-- Quick Info Card -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> Assignment Info</h5>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li>Only active trainers are listed.</li>
                            <li>You can assign multiple members at once.</li>
                            <li>Existing assignments will be overwritten.</li>
                            <li>Members without a trainer are marked as "Not Assigned".</li>
                        </ul>
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
    <script>
        $(document).ready(function() {
            $('#sidebarCollapse').on('click', function() {
                $('#sidebar').toggleClass('active');
            });
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);

            // DataTable for members table
            $('#membersTable').DataTable({
                pageLength: 10,
                order: [[1, 'asc']],
                language: {
                    search: "<i class='fas fa-search'></i>",
                    searchPlaceholder: "Search members..."
                },
                columnDefs: [
                    { orderable: false, targets: [0] }
                ]
            });

            // Select/Deselect all checkboxes
            $('#selectAll').on('change', function() {
                $('.member-checkbox').prop('checked', $(this).prop('checked'));
            });
        });

        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }
    </script>
</body>
</html>