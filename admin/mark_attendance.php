<?php
// admin/mark_attendance.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Paths
$root_path = dirname(__DIR__);
require_once $root_path . '/includes/config.php';
require_once $root_path . '/includes/session.php';
require_once $root_path . '/includes/functions.php';

// Check if user is admin
if (!Session::isAdmin()) {
    Session::setFlash('danger', 'Access denied. Admin login required.');
    header('Location: ' . $root_path . '/login.php');
    exit();
}

$functions = new Functions();
$error = '';
$success = '';

// Get filter parameters
$search = $_GET['search'] ?? '';
$date = $_GET['date'] ?? date('Y-m-d');

// Fetch members list (active members)
$members = [];
try {
    $sql = "SELECT u.id, u.full_name, u.email, u.phone
            FROM users u
            WHERE u.user_type = 'member' AND u.status = 'active'
            ORDER BY u.full_name";
    if (!empty($search)) {
        $sql = "SELECT u.id, u.full_name, u.email, u.phone
                FROM users u
                WHERE u.user_type = 'member' AND u.status = 'active'
                AND (u.full_name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)
                ORDER BY u.full_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['search' => "%$search%"]);
        $members = $stmt->fetchAll();
    } else {
        $stmt = $pdo->query($sql);
        $members = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $error = "Error loading members: " . $e->getMessage();
}

// Handle check-in/out submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id'])) {
    $member_id = (int)$_POST['member_id'];
    
    // Determine action: either from 'action' field (JS form) or from 'mark_attendance' button (quick form)
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
    } elseif (isset($_POST['mark_attendance']) && in_array($_POST['mark_attendance'], ['check_in', 'check_out'])) {
        $action = $_POST['mark_attendance'];
    } else {
        $error = "Invalid action.";
    }

    if (!isset($action)) {
        // Skip processing if action not set
        $error = "No action specified.";
    } else {
        $selected_date = $_POST['attendance_date'] ?? date('Y-m-d');
        $current_time = date('H:i:s');

        try {
            // Verify member exists and is active
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND user_type = 'member' AND status = 'active'");
            $stmt->execute([$member_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Invalid member selected.");
            }

            if ($action === 'check_in') {
                // Check if already checked in today
                $stmt = $pdo->prepare("SELECT id, check_out FROM attendance WHERE user_id = ? AND date = ?");
                $stmt->execute([$member_id, $selected_date]);
                $existing = $stmt->fetch();

                if ($existing) {
                    if ($existing['check_out']) {
                        throw new Exception("Member already checked in and out for this date.");
                    } else {
                        throw new Exception("Member already checked in for today. Use check-out.");
                    }
                } else {
                    // Insert check-in
                    $stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, check_in, status) VALUES (?, ?, ?, 'present')");
                    $stmt->execute([$member_id, $selected_date, $current_time]);
                    $success = "Check-in recorded successfully.";
                }
            } elseif ($action === 'check_out') {
                // Find today's record with check_in but no check_out
                $stmt = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ? AND check_in IS NOT NULL AND check_out IS NULL");
                $stmt->execute([$member_id, $selected_date]);
                $record = $stmt->fetch();

                if (!$record) {
                    throw new Exception("No check-in record found for today.");
                }

                $stmt = $pdo->prepare("UPDATE attendance SET check_out = ? WHERE id = ?");
                $stmt->execute([$current_time, $record['id']]);
                $success = "Check-out recorded successfully.";
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Fetch recent attendance logs (last 50)
$recent_attendance = [];
try {
    $stmt = $pdo->query("
        SELECT a.*, u.full_name as member_name
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        ORDER BY a.date DESC, a.check_in DESC
        LIMIT 50
    ");
    $recent_attendance = $stmt->fetchAll();
} catch (Exception $e) {
    // ignore
}

$page_title = 'Mark Attendance - ' . APP_NAME;
$user_name = Session::userName();
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; overflow-x: hidden; }
        .wrapper { display: flex; width: 100%; align-items: stretch; min-height: 100vh; }
        #sidebar {
            min-width: 280px; max-width: 280px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff; transition: .3s; box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: relative; z-index: 1000;
        }
        #sidebar.active { margin-left: -280px; }
        #sidebar .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        #sidebar .sidebar-header h3 { font-size: 1.8rem; font-weight: 600; }
        #sidebar ul.components { padding: 20px 0; }
        #sidebar ul li a {
            padding: 15px 25px; font-size: 1rem; display: block; color: #fff;
            text-decoration: none; transition: .3s; border-left: 3px solid transparent;
        }
        #sidebar ul li a:hover { background: rgba(255,255,255,0.1); border-left-color: #fff; }
        #sidebar ul li.active > a { background: rgba(255,255,255,0.15); border-left-color: #fff; font-weight: 600; }
        #sidebar ul li a i { margin-right: 10px; width: 25px; text-align: center; }
        #sidebar ul ul a { padding-left: 50px !important; font-size: .9rem !important; }
        #sidebar .sidebar-footer {
            padding: 20px; position: absolute; bottom: 0; width: 100%;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        #content { width: 100%; padding: 30px; min-height: 100vh; transition: .3s; background: #f8f9fa; }
        .navbar-custom {
            background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-radius: 10px; margin-bottom: 30px; padding: 15px 25px;
        }
        .page-header { padding-bottom: 15px; margin: 0 0 30px; border-bottom: 3px solid #667eea; }
        .page-header h1 { font-size: 2rem; font-weight: 600; color: #333; margin: 0; }
        .page-header h1 i { color: #667eea; margin-right: 10px; }
        .page-header small { font-size: 1rem; color: #6c757d; margin-left: 10px; }
        .card {
            border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .card-header {
            background: #fff; border-bottom: 2px solid #f0f0f0; padding: 20px 25px;
            border-radius: 15px 15px 0 0 !important;
        }
        .card-header h5 { margin: 0; font-weight: 600; color: #333; }
        .card-header h5 i { color: #667eea; margin-right: 10px; }
        .card-body { padding: 25px; }
        .table { margin: 0; }
        .table thead th {
            border-top: none; border-bottom: 2px solid #667eea; color: #555;
            font-weight: 600; text-transform: uppercase; font-size: .8rem;
            letter-spacing: .5px; padding: 15px 10px;
        }
        .table tbody td { padding: 15px 10px; vertical-align: middle; border-bottom: 1px solid #f0f0f0; color: #666; }
        .table tbody tr:hover { background: #f8f9fa; }
        .badge { padding: 6px 10px; border-radius: 20px; font-weight: 500; font-size: .75rem; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-secondary { background: #e2e3e5; color: #383d41; }
        .list-group-item {
            border: none; border-bottom: 1px solid #f0f0f0; padding: 15px 20px;
            transition: .3s;
        }
        .list-group-item:last-child { border-bottom: none; }
        .list-group-item:hover { background: #f8f9fa; transform: translateX(5px); }
        .list-group-item i { color: #667eea; margin-right: 10px; }
        .alert { border: none; border-radius: 10px; padding: 15px 20px; margin-bottom: 30px; }
        .form-control, .form-control:focus { border-radius: 30px; border: 2px solid #e9ecef; box-shadow: none; }
        .form-control:focus { border-color: #667eea; }
        .btn-primary {
            background: #667eea; border: none; border-radius: 30px; padding: 10px 25px;
            transition: .3s;
        }
        .btn-primary:hover { background: #5a67d8; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102,126,234,0.4); }
        .btn-outline-primary {
            border: 2px solid #667eea; color: #667eea; border-radius: 30px;
            transition: .3s;
        }
        .btn-outline-primary:hover { background: #667eea; color: #fff; transform: translateY(-2px); }
        @media (max-width: 768px) {
            #sidebar { margin-left: -280px; }
            #sidebar.active { margin-left: 0; }
            #content { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Administrator Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="manage_members.php"><i class="fas fa-user"></i> Members</a></li>
                <li><a href="manage_trainers.php"><i class="fas fa-chalkboard-teacher"></i> Trainers</a></li>
                <li><a href="classes/manage_classes.php"><i class="fas fa-calendar-alt"></i> Classes</a></li>
                <li><a href="payments/manage_payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li class="active"><a href="mark_attendance.php"><i class="fas fa-clock"></i> Mark Attendance</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
            <div class="sidebar-footer">
                <a href="#" onclick="confirmLogout(event)" class="btn btn-danger btn-block">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-custom">
                <button type="button" id="sidebarCollapse" class="btn btn-outline-primary">
                    <i class="fas fa-bars"></i> Menu
                </button>
                <div class="ml-auto">
                    <div class="dropdown ml-3 d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown">
                            <i class="fas fa-user-circle fa-lg"></i>
                            <span class="ml-2"><?php echo htmlspecialchars($user_name); ?></span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profile</a>
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
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header">
                            <h1><i class="fas fa-clock"></i> Mark Attendance</h1>
                        </div>
                    </div>
                </div>

                <!-- Quick Attendance Form -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-pen"></i> Quick Mark Attendance</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" class="form-inline justify-content-center">
                            <div class="form-group mx-2 mb-2">
                                <label for="member_id" class="sr-only">Member</label>
                                <select name="member_id" id="member_id" class="form-control" style="min-width: 250px;" required>
                                    <option value="">Select Member</option>
                                    <?php foreach ($members as $m): ?>
                                    <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mx-2 mb-2">
                                <label for="attendance_date" class="sr-only">Date</label>
                                <input type="date" name="attendance_date" id="attendance_date" class="form-control" value="<?php echo $date; ?>">
                            </div>
                            <div class="form-group mx-2 mb-2">
                                <button type="submit" name="mark_attendance" value="check_in" class="btn btn-success">
                                    <i class="fas fa-sign-in-alt"></i> Check In
                                </button>
                                <button type="submit" name="mark_attendance" value="check_out" class="btn btn-warning ml-2">
                                    <i class="fas fa-sign-out-alt"></i> Check Out
                                </button>
                            </div>
                        </form>
                        <small class="text-muted d-block text-center">Select member and date, then click Check In or Check Out.</small>
                    </div>
                </div>

                <!-- Member List with Filter -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-users"></i> Members List</h5>
                        <form method="get" class="form-inline">
                            <input type="text" name="search" class="form-control mr-2" placeholder="Search members..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </form>
                    </div>
                    <div class="card-body">
                        <?php if (empty($members)): ?>
                            <p class="text-muted text-center">No members found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($members as $m): ?>
                                        <tr>
                                            <td><?php echo $m['id']; ?></td>
                                            <td><?php echo htmlspecialchars($m['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($m['email']); ?></td>
                                            <td><?php echo htmlspecialchars($m['phone'] ?? 'N/A'); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-success check-in-btn" data-id="<?php echo $m['id']; ?>" data-name="<?php echo htmlspecialchars($m['full_name']); ?>">
                                                    <i class="fas fa-sign-in-alt"></i> Check In
                                                </button>
                                                <button class="btn btn-sm btn-warning check-out-btn" data-id="<?php echo $m['id']; ?>" data-name="<?php echo htmlspecialchars($m['full_name']); ?>">
                                                    <i class="fas fa-sign-out-alt"></i> Check Out
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Attendance Logs -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> Recent Attendance</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_attendance)): ?>
                            <p class="text-muted text-center">No attendance records.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Member</th>
                                            <th>Check In</th>
                                            <th>Check Out</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_attendance as $a): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($a['date'])); ?></td>
                                            <td><?php echo htmlspecialchars($a['member_name']); ?></td>
                                            <td><?php echo $a['check_in'] ? date('h:i A', strtotime($a['check_in'])) : '-'; ?></td>
                                            <td><?php echo $a['check_out'] ? date('h:i A', strtotime($a['check_out'])) : '-'; ?></td>
                                            <td>
                                                <?php
                                                $badge = match($a['status']) {
                                                    'present' => 'success',
                                                    'late'    => 'warning',
                                                    'absent'  => 'danger',
                                                    default   => 'secondary'
                                                };
                                                ?>
                                                <span class="badge badge-<?php echo $badge; ?>"><?php echo ucfirst($a['status']); ?></span>
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
    <script>
        $(document).ready(function() {
            $('#sidebarCollapse').on('click', function() {
                $('#sidebar').toggleClass('active');
            });

            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);

            // Handle check-in/out buttons in member list
            $('.check-in-btn, .check-out-btn').on('click', function() {
                var memberId = $(this).data('id');
                var memberName = $(this).data('name');
                var action = $(this).hasClass('check-in-btn') ? 'check_in' : 'check_out';
                var date = prompt(`Enter date for ${action.replace('_',' ')} of ${memberName} (YYYY-MM-DD):`, '<?php echo date('Y-m-d'); ?>');
                if (!date) return;

                // Submit via a hidden form
                var form = $('<form method="post" style="display:none;">');
                form.append($('<input type="hidden" name="member_id">').val(memberId));
                form.append($('<input type="hidden" name="attendance_date">').val(date));
                form.append($('<input type="hidden" name="action">').val(action));
                form.append($('<input type="hidden" name="mark_attendance">').val('1'));
                $('body').append(form);
                form.submit();
            });
        });

        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }
    </script>
</body>
</html>