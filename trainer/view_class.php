<?php
// trainer/view_class.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Only trainers can access this page
Session::requireTrainer();

$functions = new Functions();
$error = '';
$class = null;
$enrolled_members = [];
$enrolled_count = 0;

// Get class ID from URL
$class_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($class_id <= 0) {
    $error = "Invalid class ID.";
} else {
    try {
        // Fetch class details along with trainer name
        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name as trainer_name
            FROM classes c
            LEFT JOIN users u ON c.trainer_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$class_id]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$class) {
            $error = "Class not found.";
        } else {
            // (Optional) Check if the logged-in trainer is the assigned trainer
            // Uncomment the following lines if you want to restrict access
            // if ($class['trainer_id'] != Session::userId()) {
            //     $error = "You are not authorized to view this class.";
            // }

            // Get enrolled members with their details
            $stmt = $pdo->prepare("
                SELECT u.id, u.full_name, u.email, u.username, m.membership_type, ce.enrolled_at
                FROM class_enrollments ce
                JOIN users u ON ce.member_id = u.id
                LEFT JOIN members m ON u.id = m.user_id
                WHERE ce.class_id = ?
                ORDER BY ce.enrolled_at DESC
            ");
            $stmt->execute([$class_id]);
            $enrolled_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $enrolled_count = count($enrolled_members);
        }
    } catch (Exception $e) {
        $error = "Error loading class data: " . $e->getMessage();
    }
}

$user_name = Session::userName();
$page_title = 'View Class - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Reuse the same styles from admin dashboard – keep only necessary parts */
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}}
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar (Trainer Version) -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Trainer Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="my_classes.php"><i class="fas fa-calendar-alt"></i> My Classes</a></li>
                <li><a href="schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
                <li><a href="members.php"><i class="fas fa-users"></i> Members</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
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
                        <div class="page-header d-flex justify-content-between align-items-center">
                            <h1><i class="fas fa-chalkboard-teacher"></i> Class Details <small><?php echo $class ? htmlspecialchars($class['name']) : ''; ?></small></h1>
                            <div>
                                <a href="my_classes.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Classes</a>
                                <?php if ($class): ?>
                                <a href="edit_class.php?id=<?php echo $class['id']; ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit Class</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($class): ?>
                <!-- Class Information Card -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> Class Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Trainer:</strong> <?php echo htmlspecialchars($class['trainer_name']); ?></p>
                                <p><strong>Schedule:</strong> <?php echo nl2br(htmlspecialchars($class['schedule'])); ?></p>
                                <p><strong>Capacity:</strong> <?php echo $class['capacity']; ?></p>
                                <p><strong>Enrolled:</strong> <?php echo $enrolled_count; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Description:</strong></p>
                                <p><?php echo nl2br(htmlspecialchars($class['description'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enrolled Members Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-users"></i> Enrolled Members</h5>
                        <button class="btn btn-sm btn-success" data-toggle="modal" data-target="#addMemberModal"><i class="fas fa-user-plus"></i> Add Member</button>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($enrolled_members)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-user-friends fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No members enrolled yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Membership</th>
                                            <th>Enrolled On</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($enrolled_members as $member): ?>
                                        <tr>
                                            <td><?php echo $member['id']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle bg-info text-white mr-2" style="width:35px;height:35px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;">
                                                        <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                                            <td><span class="badge badge-info"><?php echo ucfirst($member['membership_type'] ?? 'basic'); ?></span></td>
                                            <td><?php echo date('M d, Y', strtotime($member['enrolled_at'])); ?></td>
                                            <td>
                                                <a href="remove_member.php?class_id=<?php echo $class['id']; ?>&member_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Remove this member from class?')"><i class="fas fa-trash"></i> Remove</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Member Modal (simple example – you may want to build a proper member search) -->
    <div class="modal fade" id="addMemberModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add Member to Class</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form action="add_member_to_class.php" method="POST">
                    <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Member</label>
                            <select name="member_id" class="form-control" required>
                                <option value="">Select a member...</option>
                                <?php
                                // Fetch all members not yet enrolled (optional)
                                try {
                                    $stmt = $pdo->prepare("
                                        SELECT u.id, u.full_name, u.email
                                        FROM users u
                                        WHERE u.user_type = 'member'
                                        AND u.status = 'active'
                                        AND u.id NOT IN (
                                            SELECT member_id FROM class_enrollments WHERE class_id = ?
                                        )
                                        ORDER BY u.full_name
                                    ");
                                    $stmt->execute([$class['id']]);
                                    $available_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($available_members as $m) {
                                        echo '<option value="'.$m['id'].'">'.htmlspecialchars($m['full_name']).' ('.$m['email'].')</option>';
                                    }
                                } catch (Exception $e) {
                                    echo '<option value="">Error loading members</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times"></i> Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-user-plus"></i> Add Member</button>
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