<?php
// member/schedule.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure only members can access this page
Session::requireMember();

$functions = new Functions();
$error = '';
$success = '';

$member_id = Session::userId();
$user_name = Session::userName();

// Handle booking actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['book_schedule']) && !empty($_POST['schedule_id'])) {
        $schedule_id = intval($_POST['schedule_id']);
        // Check if already booked
        $check = $pdo->prepare("SELECT id FROM class_bookings WHERE schedule_id = ? AND member_id = ?");
        $check->execute([$schedule_id, $member_id]);
        if ($check->rowCount() > 0) {
            $error = "You have already booked this class.";
        } else {
            // Check capacity
            $stmt = $pdo->prepare("
                SELECT cs.max_capacity, 
                       (SELECT COUNT(*) FROM class_bookings WHERE schedule_id = cs.id) as booked
                FROM class_schedule cs
                WHERE cs.id = ?
            ");
            $stmt->execute([$schedule_id]);
            $schedule = $stmt->fetch();
            if ($schedule && $schedule['booked'] < $schedule['max_capacity']) {
                $insert = $pdo->prepare("INSERT INTO class_bookings (schedule_id, member_id, booking_date, status) VALUES (?, ?, NOW(), 'confirmed')");
                if ($insert->execute([$schedule_id, $member_id])) {
                    $success = "Successfully booked the class!";
                } else {
                    $error = "Failed to book class. Please try again.";
                }
            } else {
                $error = "Sorry, this class is fully booked.";
            }
        }
    } elseif (isset($_POST['cancel_booking']) && !empty($_POST['booking_id'])) {
        $booking_id = intval($_POST['cancel_booking']);
        // Verify ownership
        $check = $pdo->prepare("SELECT id FROM class_bookings WHERE id = ? AND member_id = ?");
        $check->execute([$booking_id, $member_id]);
        if ($check->rowCount() > 0) {
            $delete = $pdo->prepare("DELETE FROM class_bookings WHERE id = ?");
            if ($delete->execute([$booking_id])) {
                $success = "Booking cancelled successfully.";
            } else {
                $error = "Could not cancel booking.";
            }
        } else {
            $error = "Invalid booking.";
        }
    }
}

// Fetch upcoming classes (next 7 days)
try {
    $upcoming_classes = $pdo->prepare("
       SELECT cs.id AS schedule_id,
       cs.schedule_date,
       cs.start_time,
       cs.end_time,
       cs.location,
       cs.max_capacity,
       c.id AS class_id,
       c.class_name AS class_name,
       c.description,
       TIMESTAMPDIFF(MINUTE, cs.start_time, cs.end_time) AS duration_minutes,
       u.full_name AS trainer_name,
       (SELECT COUNT(*) FROM class_bookings cb WHERE cb.schedule_id = cs.id) AS booked_count
        FROM class_schedule cs
        INNER JOIN classes c ON cs.class_id = c.id
        LEFT JOIN trainers t ON c.trainer_id = t.user_id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE cs.schedule_date >= CURDATE()
          AND cs.schedule_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY cs.schedule_date ASC, cs.start_time ASC
    ");
    $upcoming_classes->execute();
    $upcoming_classes = $upcoming_classes->fetchAll();
} catch (Exception $e) {
    $error = "Error loading schedule: " . $e->getMessage();
    $upcoming_classes = [];
}

// Fetch member's current bookings
try {
    $my_bookings = $pdo->prepare("
        SELECT cb.id AS booking_id,
               cs.schedule_date,
               cs.start_time,
               cs.end_time,
               cs.location,
               c.class_name,
               u.full_name AS trainer_name
        FROM class_bookings cb
        INNER JOIN class_schedule cs ON cb.schedule_id = cs.id
        INNER JOIN classes c ON cs.class_id = c.id
        LEFT JOIN trainers t ON c.trainer_id = t.user_id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE cb.member_id = ?
          AND cs.schedule_date >= CURDATE()
        ORDER BY cs.schedule_date ASC, cs.start_time ASC
    ");
    $my_bookings->execute([$member_id]);
    $my_bookings = $my_bookings->fetchAll();
} catch (Exception $e) {
    $error = "Error loading your bookings: " . $e->getMessage();
    $my_bookings = [];
}

$page_title = 'Class Schedule - ' . APP_NAME;
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
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',sans-serif; background:#f8f9fa; overflow-x:hidden; }
        .wrapper { display:flex; width:100%; align-items:stretch; min-height:100vh; }
        #sidebar { min-width:280px; max-width:280px; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; transition:.3s; box-shadow:2px 0 10px rgba(0,0,0,0.1); position:relative; z-index:1000; }
        #sidebar.active { margin-left:-280px; }
        #sidebar .sidebar-header { padding:30px 20px; text-align:center; border-bottom:1px solid rgba(255,255,255,0.1); }
        #sidebar .sidebar-header h3 { font-size:1.8rem; font-weight:600; }
        #sidebar ul.components { padding:20px 0; }
        #sidebar ul li a { padding:15px 25px; font-size:1rem; display:block; color:#fff; text-decoration:none; transition:.3s; border-left:3px solid transparent; }
        #sidebar ul li a:hover { background:rgba(255,255,255,0.1); border-left-color:#fff; }
        #sidebar ul li.active > a { background:rgba(255,255,255,0.15); border-left-color:#fff; font-weight:600; }
        #sidebar ul li a i { margin-right:10px; width:25px; text-align:center; }
        #sidebar .sidebar-footer { padding:20px; position:absolute; bottom:0; width:100%; border-top:1px solid rgba(255,255,255,0.1); }
        #content { width:100%; padding:30px; min-height:100vh; transition:.3s; background:#f8f9fa; }
        .navbar-custom { background:#fff; box-shadow:0 2px 10px rgba(0,0,0,0.05); border-radius:10px; margin-bottom:30px; padding:15px 25px; }
        .page-header { padding-bottom:15px; margin:0 0 30px; border-bottom:3px solid #667eea; }
        .page-header h1 { font-size:2rem; font-weight:600; color:#333; margin:0; }
        .page-header h1 i { color:#667eea; margin-right:10px; }
        .card { border:none; border-radius:15px; box-shadow:0 5px 20px rgba(0,0,0,0.05); margin-bottom:30px; }
        .card-header { background:#fff; border-bottom:2px solid #f0f0f0; padding:20px 25px; border-radius:15px 15px 0 0!important; }
        .card-header h5 { margin:0; font-weight:600; color:#333; }
        .card-header h5 i { color:#667eea; margin-right:10px; }
        .card-body { padding:25px; }
        .table { margin:0; }
        .table thead th { border-top:none; border-bottom:2px solid #667eea; color:#555; font-weight:600; text-transform:uppercase; font-size:.8rem; letter-spacing:.5px; padding:15px 10px; }
        .table tbody td { padding:15px 10px; vertical-align:middle; border-bottom:1px solid #f0f0f0; color:#666; }
        .badge { padding:6px 10px; border-radius:20px; font-weight:500; font-size:.75rem; }
        .badge-success { background:#d4edda; color:#155724; }
        .badge-warning { background:#fff3cd; color:#856404; }
        .badge-danger { background:#f8d7da; color:#721c24; }
        .badge-info { background:#d1ecf1; color:#0c5460; }
        .btn-sm { padding:.25rem .5rem; font-size:.75rem; }
        .btn-primary { background:#667eea; border-color:#667eea; }
        .btn-primary:hover { background:#5a67d8; border-color:#5a67d8; }
        .btn-outline-primary { color:#667eea; border-color:#667eea; }
        .btn-outline-primary:hover { background:#667eea; border-color:#667eea; }
        @media(max-width:768px) {
            #sidebar { margin-left:-280px; }
            #sidebar.active { margin-left:0; }
            #content { padding:20px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar for members -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Member Area</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="active"><a href="schedule.php"><i class="fas fa-calendar-alt"></i> Class Schedule</a></li>
                <li><a href="my_classes.php"><i class="fas fa-user-check"></i> My Classes</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li><a href="membership.php"><i class="fas fa-id-card"></i> Membership</a></li>
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
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown">
                            <i class="fas fa-user-circle fa-lg"></i><span class="ml-2"><?php echo htmlspecialchars($user_name); ?></span>
                        </button>
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
                <?php Session::displayFlash(); ?>

                <!-- Page Header -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="page-header">
                            <h1><i class="fas fa-calendar-alt"></i> Class Schedule <small>Book your next workout</small></h1>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Classes Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-chalkboard-teacher"></i> Upcoming Classes (Next 7 Days)</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($upcoming_classes)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No classes scheduled for the next 7 days.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Class</th>
                                            <th>Trainer</th>
                                            <th>Location</th>
                                            <th>Availability</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($upcoming_classes as $class): 
                                            $available = $class['max_capacity'] - $class['booked_count'];
                                            $booked = $class['booked_count'];
                                            $disabled = ($available <= 0);
                                        ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($class['schedule_date'])); ?></td>
                                            <td><?php echo date('g:i A', strtotime($class['start_time'])) . ' - ' . date('g:i A', strtotime($class['end_time'])); ?></td>
                                            <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong><br><small><?php echo htmlspecialchars($class['description']); ?></small></td>
                                            <td><?php echo htmlspecialchars($class['trainer_name'] ?: 'TBA'); ?></td>
                                            <td><?php echo htmlspecialchars($class['location']); ?></td>
                                            <td><?php echo $available; ?>/<?php echo $class['max_capacity']; ?> spots left</td>
                                            <td>
                                                <?php if ($disabled): ?>
                                                    <button class="btn btn-secondary btn-sm" disabled>Full</button>
                                                <?php else: ?>
                                                    <form method="POST" action="" style="display:inline;">
                                                        <input type="hidden" name="schedule_id" value="<?php echo $class['schedule_id']; ?>">
                                                        <button type="submit" name="book_schedule" class="btn btn-primary btn-sm">Book</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- My Bookings -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-calendar-check"></i> My Upcoming Bookings</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($my_bookings)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                                <p class="text-muted">You have no upcoming classes booked.</p>
                                <a href="schedule.php" class="btn btn-primary">Browse Schedule</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Class</th>
                                            <th>Trainer</th>
                                            <th>Location</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($my_bookings as $booking): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($booking['schedule_date'])); ?></td>
                                            <td><?php echo date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time'])); ?></td>
                                            <td><strong><?php echo htmlspecialchars($booking['class_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($booking['trainer_name'] ?: 'TBA'); ?></td>
                                            <td><?php echo htmlspecialchars($booking['location']); ?></td>
                                            <td>
                                                <form method="POST" action="" onsubmit="return confirm('Cancel this booking?');">
                                                    <input type="hidden" name="cancel_booking" value="<?php echo $booking['booking_id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
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
