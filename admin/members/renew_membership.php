<?php
/**
 * admin/members/renew_membership.php - Renew a member's subscription
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

Session::requireAdmin();

$error = '';
$success = '';

$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($member_id <= 0) {
    Session::setFlash('error', 'Invalid member ID.');
    header('Location: manage_members.php');
    exit;
}

$member = null;
$plans = [];

try {
    // Fetch member details – match your actual table columns
    $stmt = $pdo->prepare("
        SELECT u.id AS user_id, u.full_name, u.email, u.username, u.status,
               m.id AS membership_record_id, m.membership_type, m.start_date, m.end_date
        FROM users u
        LEFT JOIN members m ON u.id = m.user_id
        WHERE u.id = ? AND u.user_type = 'member'
    ");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        throw new Exception('Member not found.');
    }

    // Fetch active membership plans
    $plans_stmt = $pdo->query("
        SELECT id,name AS plan_name , duration_days, price, description 
        FROM membership_plans 
        WHERE status = 'active' 
        ORDER BY duration_days
    ");
    $plans = $plans_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error in renew_membership.php: " . $e->getMessage());
    $error = "Failed to load member data. " . $e->getMessage();
    $member = null;
    $plans = [];
}

// Process renewal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $member) {
    $plan_id = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : 0;
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'cash';
    $amount_paid = isset($_POST['amount_paid']) ? floatval($_POST['amount_paid']) : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if ($plan_id <= 0) {
        $error = 'Please select a membership plan.';
    } elseif ($amount_paid <= 0) {
        $error = 'Please enter a valid amount.';
    } else {
        try {
            $pdo->beginTransaction();

            // Get plan details
            $plan_stmt = $pdo->prepare("SELECT name AS plan_name, duration_days, price FROM membership_plans WHERE id = ?");
            $plan_stmt->execute([$plan_id]);
            $plan = $plan_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$plan) {
                throw new Exception('Selected plan not found.');
            }

            // Calculate new expiry date
            $current_end = $member['end_date'] ?? null;
            $today = new DateTime();
            if ($current_end && new DateTime($current_end) > $today) {
                // Not expired: extend from current end date
                $new_end_date = (new DateTime($current_end))->modify("+{$plan['duration_days']} days")->format('Y-m-d');
                $start_date = $member['start_date'];
            } else {
                // Expired or no membership: start from today
                $new_end_date = $today->modify("+{$plan['duration_days']} days")->format('Y-m-d');
                $start_date = date('Y-m-d');
            }

            // Update member record (no plan_id column, just membership_type)
            if ($member['membership_record_id']) {
                $update_stmt = $pdo->prepare("
                 UPDATE members 
                SET membership_type = ?, start_date = ?, end_date = ?
                WHERE user_id = ?
                ");
                $update_stmt->execute([$plan['plan_name'], $start_date, $new_end_date, $member['user_id']]);
            } else {
                $insert_stmt = $pdo->prepare("
                   INSERT INTO members (user_id, membership_type, start_date, end_date) 
                 VALUES (?, ?, ?, ?)
                ");
                $insert_stmt->execute([$member['user_id'], $plan['plan_name'], $start_date, $new_end_date]);
            }

            // Ensure user status is active
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$member['user_id']]);

            // Record payment
            $receipt_number = 'RCP' . date('Ymd') . rand(1000, 9999);
            $payment_stmt = $pdo->prepare("INSERT INTO payments (member_id, amount, payment_date, payment_method, notes, recorded_by) VALUES (?, ?, NOW(), ?, ?, ?)");
            $payment_stmt->execute([
                $member['user_id'],
                $amount_paid,
                $payment_method,
                $notes,
                $_SESSION['user_id']
            ]);

            // Optional: log activity (skip if table missing)
            try {
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) 
                    VALUES (?, 'renew_membership', ?, ?, NOW())
                ");
                $log_stmt->execute([
                    $_SESSION['user_id'],
                    "Renewed membership for member ID {$member['user_id']} (Plan: {$plan['plan_name']})",
                    $_SERVER['REMOTE_ADDR']
                ]);
            } catch (Exception $log_e) {
                error_log("Failed to log activity: " . $log_e->getMessage());
            }

            $pdo->commit();

            Session::setFlash('success', "Membership renewed successfully! New expiry date: " . date('d M Y', strtotime($new_end_date)));
            header("Location: manage_members.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Renewal error: " . $e->getMessage());
            $error = "Error processing renewal: " . $e->getMessage();
        }
    }
}

$user_name = Session::userName();
$page_title = 'Renew Membership - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Same styles as dashboard (keep exactly as in original) */
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border:none;border-radius:30px;padding:10px 25px;transition:.3s}.btn-primary:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(102,126,234,0.4)}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}.form-group label{font-weight:500;color:#555}.form-control{border-radius:10px;border:1px solid #e1e5eb;padding:10px 15px;transition:.2s}.form-control:focus{border-color:#667eea;box-shadow:0 0 0 0.2rem rgba(102,126,234,0.25)}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-info{background:#d1ecf1;color:#0c5460}.badge-danger{background:#f8d7da;color:#721c24}.member-info{border-left:4px solid #667eea;background:#f8f9fa;padding:15px;border-radius:10px;margin-bottom:20px}.member-info p{margin:5px 0}.member-info strong{color:#667eea}
    </style>
</head>
<body>
    <div class="wrapper">
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo htmlspecialchars(APP_NAME); ?></h3>
                <p>Administrator Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="../manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li class="active"><a href="manage_members.php"><i class="fas fa-user"></i> Members</a></li>
                <li><a href="../manage_trainers.php"><i class="fas fa-chalkboard-teacher"></i> Trainers</a></li>
                <li><a href="../classes/manage_classes.php"><i class="fas fa-calendar-alt"></i> Classes</a></li>
                <li><a href="../payments/manage_payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li><a href="../membership/membership_plans.php"><i class="fas fa-id-card"></i> Membership Plans</a></li>
                <li><a href="../notifications/send_notification.php"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="../reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="../settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
            <div class="sidebar-footer">
                <a href="#" onclick="confirmLogout(event)" class="btn btn-danger btn-block"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </nav>

        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-custom">
                <button type="button" id="sidebarCollapse" class="btn btn-outline-primary"><i class="fas fa-bars"></i> Menu</button>
                <div class="ml-auto">
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
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php Session::displayFlash(); ?>

                <div class="page-header">
                    <h1><i class="fas fa-sync-alt"></i> Renew Membership <small>Extend member's subscription</small></h1>
                </div>

                <?php if ($member): ?>
                <div class="row">
                    <div class="col-lg-6 offset-lg-3">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-user"></i> Member Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="member-info">
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($member['full_name']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($member['email']); ?></p>
                                    <p><strong>Current Plan:</strong> 
                                        <?php if ($member['membership_type']): ?>
                                            <span class="badge badge-info"><?php echo htmlspecialchars($member['membership_type']); ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">No active plan</span>
                                        <?php endif; ?>
                                    </p>
                                    <p><strong>Expiry Date:</strong> 
                                        <?php if ($member['end_date']): ?>
                                            <span class="badge badge-<?php echo (strtotime($member['end_date']) > time()) ? 'success' : 'danger'; ?>">
                                                <?php echo date('d M Y', strtotime($member['end_date'])); ?>
                                                <?php if (strtotime($member['end_date']) < time()): ?> (Expired)<?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Not set</span>
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label for="plan_id">Select New Plan <span class="text-danger">*</span></label>
                                        <select name="plan_id" id="plan_id" class="form-control" required>
                                            <option value="">-- Choose a plan --</option>
                                            <?php foreach ($plans as $plan): ?>
                                                <option value="<?php echo $plan['id']; ?>" data-price="<?php echo $plan['price']; ?>">
                                                    <?php echo htmlspecialchars($plan['plan_name']); ?> 
                                                    (<?php echo $plan['duration_days']; ?> days - ₹<?php echo number_format($plan['price'], 2); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="amount_paid">Amount Paid (₹) <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" name="amount_paid" id="amount_paid" class="form-control" required>
                                        <small class="form-text text-muted">Enter the amount received from member.</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="payment_method">Payment Method</label>
                                        <select name="payment_method" id="payment_method" class="form-control">
                                            <option value="cash">Cash</option>
                                            <option value="card">Card</option>
                                            <option value="online">Online Transfer</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="notes">Notes (Optional)</label>
                                        <textarea name="notes" id="notes" rows="3" class="form-control" placeholder="Any remarks about this renewal"></textarea>
                                    </div>

                                    <div class="form-group text-center mt-4">
                                        <button type="submit" class="btn btn-primary btn-lg px-5"><i class="fas fa-check-circle"></i> Renew Membership</button>
                                        <a href="manage_members.php" class="btn btn-secondary btn-lg px-4"><i class="fas fa-arrow-left"></i> Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-danger text-center">
                    <i class="fas fa-exclamation-triangle"></i> Member not found. <a href="manage_members.php">Go back to members list</a>
                </div>
                <?php endif; ?>
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
                    <a href="../../logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
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

            $('#plan_id').on('change', function() {
                var price = $(this).find(':selected').data('price');
                if (price) {
                    $('#amount_paid').val(price);
                } else {
                    $('#amount_paid').val('');
                }
            });
        });

        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }
    </script>
</body>
</html>