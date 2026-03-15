<?php
// admin/settings.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

$root_path = dirname(__DIR__);
require_once $root_path . '/includes/config.php';
require_once $root_path . '/includes/session.php';
require_once $root_path . '/includes/functions.php';

if (!Session::isAdmin()) {
    header('Location: ' . $root_path . '/login.php');
    exit();
}

$functions = new Functions();
$error = '';
$success = '';

// Check if settings table exists
$table_exists = false;
try {
    $pdo->query("SELECT 1 FROM settings LIMIT 1");
    $table_exists = true;
} catch (Exception $e) {
    // table doesn't exist
}

// If table doesn't exist and user clicked "Create Table"
if (!$table_exists && isset($_POST['create_table'])) {
    try {
        // SQL to create settings table with default data
        $sql = "
        CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT,
            description TEXT,
            type ENUM('text','textarea','number','email','url','boolean') DEFAULT 'text',
            category VARCHAR(50) DEFAULT 'general',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
        
        INSERT IGNORE INTO settings (setting_key, setting_value, description, type, category) VALUES
        ('gym_name', 'FitTrack Gym', 'Name of the gym', 'text', 'general'),
        ('gym_address', '123 Fitness Street, City, State 12345', 'Physical address', 'textarea', 'general'),
        ('gym_phone', '+1 234 567 890', 'Contact phone number', 'text', 'general'),
        ('gym_email', 'info@fittrack.com', 'General contact email', 'email', 'general'),
        ('gym_website', 'https://www.fittrack.com', 'Website URL', 'url', 'general'),
        ('timezone', 'America/New_York', 'Default timezone', 'text', 'system'),
        ('date_format', 'M d, Y', 'Date display format', 'text', 'system'),
        ('time_format', 'h:i A', 'Time display format', 'text', 'system'),
        ('items_per_page', '20', 'Number of items per page in listings', 'number', 'system'),
        ('enable_registration', '1', 'Allow new user registration', 'boolean', 'membership'),
        ('default_membership_days', '30', 'Default membership duration in days', 'number', 'membership'),
        ('default_membership_price', '29.99', 'Default membership price', 'number', 'membership'),
        ('smtp_host', '', 'SMTP server host', 'text', 'email'),
        ('smtp_port', '587', 'SMTP port', 'number', 'email'),
        ('smtp_secure', 'tls', 'SMTP encryption (tls/ssl)', 'text', 'email'),
        ('smtp_username', '', 'SMTP username', 'text', 'email'),
        ('smtp_password', '', 'SMTP password', 'text', 'email'),
        ('smtp_from_email', 'noreply@fittrack.com', 'Default from email', 'email', 'email'),
        ('smtp_from_name', 'FitTrack Gym', 'Default from name', 'text', 'email');
        ";
        
        $pdo->exec($sql);
        $success = "Settings table created successfully. You can now configure your settings.";
        $table_exists = true;
    } catch (Exception $e) {
        $error = "Failed to create settings table: " . $e->getMessage();
    }
}

// Fetch all settings (if table exists) – we need them for types and to handle checkboxes
$settings = [];
$categories = [];
$boolean_keys = []; // remember which keys are boolean

if ($table_exists) {
    try {
        $stmt = $pdo->query("SELECT * FROM settings ORDER BY category, setting_key");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row;
            if (!in_array($row['category'], $categories)) {
                $categories[] = $row['category'];
            }
            if ($row['type'] == 'boolean') {
                $boolean_keys[] = $row['setting_key'];
            }
        }
    } catch (Exception $e) {
        $error = "Error loading settings: " . $e->getMessage();
    }
}

// Handle saving settings
if ($table_exists && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $pdo->beginTransaction();

        // First, collect all posted setting values (prefixed with 'setting_')
        $posted = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'setting_') === 0) {
                $setting_key = substr($key, 8);
                // Checkbox values come as 'on' – convert to 1
                if ($value === 'on') $value = 1;
                $posted[$setting_key] = $value;
            }
        }

        // For each boolean setting, if not present in POST, set to 0
        foreach ($boolean_keys as $bool_key) {
            if (!isset($posted[$bool_key])) {
                $posted[$bool_key] = 0;
            }
        }

        // Now update/insert each setting using INSERT ... ON DUPLICATE KEY UPDATE
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (:key, :value)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");

        foreach ($posted as $key => $value) {
            $stmt->execute([':key' => $key, ':value' => $value]);
        }

        $pdo->commit();
        $success = "Settings saved successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error saving settings: " . $e->getMessage();
    }
}

$user_name = Session::userName();
$page_title = 'Settings - ' . APP_NAME;
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
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.stats-card small{opacity:.9}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.list-group-item{border:none;border-bottom:1px solid #f0f0f0;padding:15px 20px;transition:.3s}.list-group-item:last-child{border-bottom:none}.list-group-item:hover{background:#f8f9fa;transform:translateX(5px)}.list-group-item i{color:#667eea;margin-right:10px}.chart-container{position:relative;height:300px;margin:20px 0}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}.stats-card h2{font-size:1.8rem}}
        /* Additional styles for form */
        .form-label{font-weight:600;color:#495057;margin-bottom:8px}
        .form-control{border-radius:8px;border:1px solid #e1e5eb;padding:10px 15px;height:auto}
        .form-control:focus{border-color:#667eea;box-shadow:0 0 0 0.2rem rgba(102,126,234,0.25)}
        .nav-tabs .nav-link.active{background-color:#667eea;color:#fff;border-color:#667eea}
        .nav-tabs .nav-link{color:#667eea;font-weight:500}
        .nav-tabs .nav-link:hover{background-color:#f0f0f0}
    </style>
</head>
<body>
    <!-- Loading Spinner (optional) -->
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
                <li>
                    <a href="#trainersSubmenu" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-user-tie"></i> Trainers <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="trainersSubmenu">
                        <li><a href="manage_trainers.php"><i class="fas fa-list"></i> All Trainers</a></li>
                        <li><a href="trainers/add_trainer.php"><i class="fas fa-user-plus"></i> Add Trainer</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#classesSubmenu" data-toggle="collapse" aria-expanded="false">
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
                <li><a href="equipment.php"><i class="fas fa-dumbbell"></i> Equipment</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li class="active"><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
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
                    <!-- Notifications dropdown (optional) -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown">
                            <i class="fas fa-bell"></i><span class="badge badge-danger badge-pill">3</span>
                        </button>
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
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown">
                            <i class="fas fa-user-circle fa-lg"></i>
                            <span class="ml-2"><?php echo htmlspecialchars($user_name); ?></span>
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
                <!-- Flash messages from session -->
                <?php Session::displayFlash(); ?>

                <!-- Error alert from form processing -->
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="page-header">
                    <h1><i class="fas fa-cog"></i> System Settings <small>Configure your application</small></h1>
                </div>

                <?php if (!$table_exists): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-database fa-4x text-muted mb-3"></i>
                            <h3>Settings Table Not Found</h3>
                            <p class="text-muted">The settings table needs to be created before you can configure your system.</p>
                            <form method="post">
                                <button type="submit" name="create_table" class="btn btn-primary btn-lg">
                                    <i class="fas fa-database mr-2"></i>Create Settings Table Now
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="post" action="">
                        <!-- Tabs navigation (Bootstrap 4) -->
                        <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                            <?php foreach ($categories as $index => $cat): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $index === 0 ? 'active' : ''; ?>" 
                                       id="<?php echo $cat; ?>-tab" 
                                       data-toggle="tab" 
                                       href="#<?php echo $cat; ?>" 
                                       role="tab">
                                        <?php echo ucfirst($cat); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="tab-content">
                            <?php foreach ($categories as $index => $cat): ?>
                                <div class="tab-pane fade <?php echo $index === 0 ? 'show active' : ''; ?>" 
                                     id="<?php echo $cat; ?>" 
                                     role="tabpanel">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <h5><?php echo ucfirst($cat); ?> Settings</h5>
                                        </div>
                                        <div class="card-body">
                                            <?php foreach ($settings as $key => $setting): ?>
                                                <?php if ($setting['category'] == $cat): ?>
                                                    <div class="form-group row">
                                                        <label for="setting_<?php echo $key; ?>" class="col-sm-3 col-form-label">
                                                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $key))); ?>
                                                            <?php if (!empty($setting['description'])): ?>
                                                                <i class="fas fa-info-circle text-muted ml-1" 
                                                                   data-toggle="tooltip" 
                                                                   title="<?php echo htmlspecialchars($setting['description']); ?>"></i>
                                                            <?php endif; ?>
                                                        </label>
                                                        <div class="col-sm-9">
                                                            <?php if ($setting['type'] == 'textarea'): ?>
                                                                <textarea class="form-control" 
                                                                          id="setting_<?php echo $key; ?>" 
                                                                          name="setting_<?php echo $key; ?>" 
                                                                          rows="3"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                                            <?php elseif ($setting['type'] == 'boolean'): ?>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" 
                                                                           type="checkbox" 
                                                                           id="setting_<?php echo $key; ?>" 
                                                                           name="setting_<?php echo $key; ?>" 
                                                                           <?php echo $setting['setting_value'] ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="setting_<?php echo $key; ?>">
                                                                        Enabled
                                                                    </label>
                                                                </div>
                                                            <?php else: ?>
                                                                <input type="<?php echo $setting['type'] == 'number' ? 'number' : ($setting['type'] == 'email' ? 'email' : ($setting['type'] == 'url' ? 'url' : 'text')); ?>" 
                                                                       class="form-control" 
                                                                       id="setting_<?php echo $key; ?>" 
                                                                       name="setting_<?php echo $key; ?>" 
                                                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                                       <?php echo $setting['type'] == 'number' ? 'step="any"' : ''; ?>>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-4">
                            <button type="submit" name="save_settings" class="btn btn-primary btn-lg">
                                <i class="fas fa-save mr-2"></i>Save All Settings
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
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

            // Initialize Bootstrap 4 tooltips
            $('[data-toggle="tooltip"]').tooltip();
        });

        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }
    </script>
</body>
</html>