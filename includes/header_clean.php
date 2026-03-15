<?php
// includes/header_clean.php
// Public header without preloader – used for login, register, forgot password, etc.

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
$page_title = isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME;
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

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav">
        <div class="container">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>/index.php">
                <i class="fas fa-dumbbell me-2"></i><?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php
                        $dashboard_link = '';
                        if ($_SESSION['user_type'] === 'admin') {
                            $dashboard_link = BASE_URL . '/admin/dashboard.php';
                        } elseif ($_SESSION['user_type'] === 'trainer') {
                            $dashboard_link = BASE_URL . '/trainer/dashboard.php';
                        } elseif ($_SESSION['user_type'] === 'member') {
                            $dashboard_link = BASE_URL . '/member/dashboard.php';
                        }
                        ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $dashboard_link; ?>">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn" href="<?php echo BASE_URL; ?>/logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link btn dropdown-toggle" href="#" data-bs-toggle="dropdown">Login</a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>/login.php?type=admin">
                                        <i class="fas fa-user-shield" style="color: var(--admin);"></i> Admin
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>/login.php?type=trainer">
                                        <i class="fas fa-chalkboard-teacher" style="color: var(--trainer);"></i> Trainer
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>/login.php?type=member">
                                        <i class="fas fa-user" style="color: var(--member);"></i> Member
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>/register.php">
                                        <i class="fas fa-user-plus"></i> Register
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main content starts -->
    <main class="main-content">