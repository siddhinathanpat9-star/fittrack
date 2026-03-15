<?php
// admin/includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define APP_NAME if not already defined
if (!defined('APP_NAME')) {
    define('APP_NAME', 'FitTrack Gym');
}

// Define BASE_URL if not already defined (fallback)
if (!defined('BASE_URL')) {
    $base = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $base .= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
    define('BASE_URL', $base);
}

// Page title
if (!isset($page_title)) {
    $page_title = APP_NAME . ' - Admin Panel';
}

// Get unread notifications count (if table exists)
$unread_notifications = 0;
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0 OR is_read IS NULL");
        $unread_notifications = (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        // table probably doesn't exist
    }
}

// Get current user's name safely
$user_name = 'Admin';
if (class_exists('Session') && method_exists('Session', 'userName')) {
    $user_name = Session::userName();
} elseif (isset($_SESSION['full_name'])) {
    $user_name = $_SESSION['full_name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.css">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>
    <!-- Preloader -->
    <div class="preloader" id="preloader">
        <div class="loader"></div>
    </div>

    <!-- AJAX Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <div class="wrapper d-flex">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Administrator Panel</p>
            </div>

            <ul class="nav flex-column">
                <?php
                // Determine current file for active state
                $current_page = basename($_SERVER['PHP_SELF']);
                ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/manage_users.php">
                        <i class="fas fa-users"></i> Manage Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'manage_members.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/manage_members.php">
                        <i class="fas fa-user"></i> Members
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'manage_trainers.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/manage_trainers.php">
                        <i class="fas fa-chalkboard-teacher"></i> Trainers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'manage_classes.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/classes/manage_classes.php">
                        <i class="fas fa-calendar-alt"></i> Classes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'manage_payments.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/payments/manage_payments.php">
                        <i class="fas fa-credit-card"></i> Payments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'membership_plans.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/membership/membership_plans.php">
                        <i class="fas fa-id-card"></i> Membership Plans
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'send_notification.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/notifications/send_notification.php">
                        <i class="fas fa-bell"></i> Notifications
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/reports.php">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/settings.php">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/profile.php">
                        <i class="fas fa-user-circle"></i> My Profile
                    </a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <a href="<?php echo BASE_URL; ?>/logout.php" onclick="confirmLogout(event); return false;" class="btn w-100">
                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                </a>
            </div>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg navbar-custom">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-outline-primary">
                        <i class="fas fa-bars"></i> Menu
                    </button>

                    <div class="ms-auto d-flex align-items-center">
                        <!-- Notifications Dropdown -->
                        <div class="dropdown me-3">
                            <button class="btn btn-light position-relative" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-bell"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationBadge">
                                    <?php echo $unread_notifications; ?>
                                </span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" style="width: 300px;" id="notificationDropdown">
                                <div class="dropdown-header bg-light fw-bold">Notifications</div>
                                <div id="notificationList">
                                    <div class="text-center p-3 text-muted">Loading...</div>
                                </div>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-center" href="<?php echo BASE_URL; ?>/admin/notifications/manage_notifications.php">View All</a>
                            </div>
                        </div>

                        <!-- User Dropdown -->
                        <div class="dropdown">
                            <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle fa-lg me-2"></i>
                                <span><?php echo htmlspecialchars($user_name); ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/logout.php" onclick="confirmLogout(event); return false;"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main content area (will be closed in footer) -->
            <div class="container-fluid">