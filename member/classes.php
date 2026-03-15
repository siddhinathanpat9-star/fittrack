<?php
// member/classes.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

$root_path = dirname(__DIR__);
require_once $root_path . '/includes/config.php';
require_once $root_path . '/includes/session.php';
require_once $root_path . '/includes/functions.php';

// Check if user is member
if (!Session::isMember()) {
    Session::setFlash('danger', 'Access denied. Member login required.');
    header('Location: ' . $root_path . '/login.php');
    exit();
}

$member_id = Session::userId();
$user_name = Session::userName(); // for display in topbar
$functions = new Functions();
$error = '';
$success = '';

// Handle booking
if (isset($_POST['book_class'])) {
    $class_id = (int)$_POST['class_id'];
    $booking_date = $_POST['booking_date'];

    // Validate date (must be in the future)
    if (strtotime($booking_date) < strtotime(date('Y-m-d'))) {
        $error = "You cannot book classes in the past.";
    } else {
        // Check if class exists and is active
        try {
            $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND status = 'active'");
            $stmt->execute([$class_id]);
            if (!$stmt->fetch()) {
                $error = "Class not found or inactive.";
            } else {
                // Check if already booked
                $stmt = $pdo->prepare("SELECT id FROM class_bookings WHERE class_id = ? AND member_id = ? AND booking_date = ?");
                $stmt->execute([$class_id, $member_id, $booking_date]);
                if ($stmt->fetch()) {
                    $error = "You have already booked this class on that date.";
                } else {
                    // Check capacity
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_bookings WHERE class_id = ? AND booking_date = ?");
                    $stmt->execute([$class_id, $booking_date]);
                    $booked = $stmt->fetchColumn();
                    $stmt = $pdo->prepare("SELECT max_capacity FROM classes WHERE id = ?");
                    $stmt->execute([$class_id]);
                    $max_capacity = $stmt->fetchColumn();
                    if ($booked >= $max_capacity) {
                        $error = "This class is full on that date.";
                    } else {
                        // Insert booking
                        $stmt = $pdo->prepare("INSERT INTO class_bookings (class_id, member_id, booking_date, status) VALUES (?, ?, ?, 'booked')");
                        $stmt->execute([$class_id, $member_id, $booking_date]);
                        $success = "Class booked successfully!";
                    }
                }
            }
        } catch (Exception $e) {
            $error = "Error booking class: " . $e->getMessage();
        }
    }
}

// Handle cancellation
if (isset($_POST['cancel_booking'])) {
    $booking_id = (int)$_POST['booking_id'];
    try {
        // Ensure the booking belongs to this member and is in the future
        $stmt = $pdo->prepare("SELECT cb.id, cb.booking_date FROM class_bookings cb WHERE cb.id = ? AND cb.member_id = ?");
        $stmt->execute([$booking_id, $member_id]);
        $booking = $stmt->fetch();
        if (!$booking) {
            $error = "Booking not found.";
        } elseif (strtotime($booking['booking_date']) < strtotime(date('Y-m-d'))) {
            $error = "Cannot cancel past bookings.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM class_bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            $success = "Booking cancelled successfully.";
        }
    } catch (Exception $e) {
        $error = "Error cancelling booking: " . $e->getMessage();
    }
}

// Get next 7 days for display
$days = [];
for ($i = 1; $i <= 7; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    $day_name = date('l', strtotime($date));
    $days[] = ['date' => $date, 'day_name' => $day_name];
}

// Get all active classes
$classes = [];
try {
    $stmt = $pdo->query("
        SELECT c.*, u.full_name as trainer_name
        FROM classes c
        LEFT JOIN users u ON c.trainer_id = u.id
        WHERE c.status = 'active'
        ORDER BY FIELD(c.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), c.start_time
    ");
    $classes = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading classes: " . $e->getMessage();
}

// Group classes by day of week for quick lookup
$classes_by_day = [];
foreach ($classes as $c) {
    $classes_by_day[$c['day_of_week']][] = $c;
}

// Get member's upcoming bookings (next 7 days)
$my_bookings = [];
try {
    $stmt = $pdo->prepare("
        SELECT cb.*, c.class_name, c.start_time, c.end_time, c.day_of_week
        FROM class_bookings cb
        JOIN classes c ON cb.class_id = c.id
        WHERE cb.member_id = ? AND cb.booking_date >= CURDATE()
        ORDER BY cb.booking_date, c.start_time
        LIMIT 20
    ");
    $stmt->execute([$member_id]);
    $my_bookings = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading your bookings: " . $e->getMessage();
}

$page_title = 'Book Classes - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 4, Font Awesome, DataTables (optional) -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.badge-secondary{background:#e9ecef;color:#6c757d}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:15px 20px;transition:.3s}.list-group-item:hover{background:#f8f9fa;transform:translateX(5px)}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}.stats-card h2{font-size:1.8rem}}
    </style>
</head>
<body>
    <!-- Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>

    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Member Area</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="attendance.php"><i class="fas fa-clock"></i> My Attendance</a></li>
                <li><a href="workouts.php"><i class="fas fa-dumbbell"></i> Workout Plans</a></li>
                <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li class="active"><a href="classes.php"><i class="fas fa-calendar-alt"></i> Book Classes</a></li>
            </ul>
            <div class="sidebar-footer">
                <a href="#" onclick="confirmLogout(event)" class="btn btn-danger btn-block"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg navbar-custom">
                <button type="button" id="sidebarCollapse" class="btn btn-outline-primary">
                    <i class="fas fa-bars"></i> Menu
                </button>
                <div class="ml-auto">
                    <!-- User dropdown -->
                    <div class="dropdown ml-3 d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown">
                            <i class="fas fa-user-circle fa-lg"></i>
                            <span class="ml-2"><?php echo htmlspecialchars($user_name); ?></span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid">
                <!-- Error / Success messages -->
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="page-header">
                    <h1><i class="fas fa-calendar-alt"></i> Book Classes <small>Schedule your fitness sessions</small></h1>
                </div>

                <!-- Upcoming Bookings Section -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-calendar-check"></i> My Upcoming Bookings</h5>
                        <?php if (!empty($my_bookings)): ?>
                            <span class="badge badge-info"><?php echo count($my_bookings); ?> bookings</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($my_bookings)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <p class="text-muted">You have no upcoming bookings.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Class</th>
                                            <th>Time</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($my_bookings as $b): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($b['booking_date'])) . ' (' . $b['day_of_week'] . ')'; ?></td>
                                            <td><?php echo htmlspecialchars($b['class_name']); ?></td>
                                            <td><?php echo date('h:i A', strtotime($b['start_time'])) . ' - ' . date('h:i A', strtotime($b['end_time'])); ?></td>
                                            <td>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                    <button type="submit" name="cancel_booking" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this booking?')">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Class Schedule for Next 7 Days -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-week"></i> Classes Available (Next 7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($days as $day): ?>
                            <?php $day_of_week = $day['day_name']; ?>
                            <div class="mb-4">
                                <h6 class="border-bottom pb-2 text-primary">
                                    <i class="fas fa-calendar-day"></i> <?php echo $day_of_week . ', ' . date('M d', strtotime($day['date'])); ?>
                                </h6>
                                <?php if (isset($classes_by_day[$day_of_week])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Class</th>
                                                    <th>Time</th>
                                                    <th>Trainer</th>
                                                    <th>Duration</th>
                                                    <th>Capacity</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($classes_by_day[$day_of_week] as $class): 
                                                    // Check if already booked
                                                    $already_booked = false;
                                                    foreach ($my_bookings as $b) {
                                                        if ($b['class_id'] == $class['id'] && $b['booking_date'] == $day['date']) {
                                                            $already_booked = true;
                                                            break;
                                                        }
                                                    }
                                                    // Check capacity
                                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_bookings WHERE class_id = ? AND booking_date = ?");
                                                    $stmt->execute([$class['id'], $day['date']]);
                                                    $booked_count = $stmt->fetchColumn();
                                                    $available = $booked_count < $class['max_capacity'];
                                                ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                                                    <td><?php echo date('h:i A', strtotime($class['start_time'])) . ' - ' . date('h:i A', strtotime($class['end_time'])); ?></td>
                                                    <td><?php echo htmlspecialchars($class['trainer_name'] ?: 'TBA'); ?></td>
                                                    <td>
                                                        <?php
                                                        $start = new DateTime($class['start_time']);
                                                        $end = new DateTime($class['end_time']);
                                                        $diff = $start->diff($end);
                                                        echo $diff->h . ' hr ' . $diff->i . ' min';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-<?php echo $available ? 'success' : 'danger'; ?>">
                                                            <?php echo $booked_count; ?>/<?php echo $class['max_capacity']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($already_booked): ?>
                                                            <span class="badge badge-secondary"><i class="fas fa-check"></i> Booked</span>
                                                        <?php elseif ($available): ?>
                                                            <form method="post">
                                                                <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                                                <input type="hidden" name="booking_date" value="<?php echo $day['date']; ?>">
                                                                <button type="submit" name="book_class" class="btn btn-sm btn-primary">
                                                                    <i class="fas fa-plus"></i> Book
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger">Full</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No classes scheduled on this day.</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
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
            // Sidebar toggle
            $('#sidebarCollapse').on('click', function() {
                $('#sidebar').toggleClass('active');
            });

            // Auto-hide alerts after 5 seconds
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