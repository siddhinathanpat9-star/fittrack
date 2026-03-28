<?php
/**
 * FitTrack Gym Management System - Root Index Page
 * Professional landing page with modern design
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define root path for includes
define('ROOT_PATH', __DIR__);

// Include database connection
require_once ROOT_PATH . '/config/database.php';

// Define application constants
define('APP_NAME', 'FitTrack Gym');
define('APP_VERSION', '2.0');

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_type = $_SESSION['user_type'] ?? '';
$user_name = $_SESSION['full_name'] ?? 'Guest';

// If user is logged in, provide quick access to dashboard
if ($is_logged_in) {
    switch($user_type) {
        case 'admin':
            $dashboard_link = 'admin/dashboard.php';
            $dashboard_name = 'Admin Dashboard';
            break;
        case 'member':
            $dashboard_link = 'member/dashboard.php';
            $dashboard_name = 'Member Dashboard';
            break;
        case 'trainer':
            $dashboard_link = 'trainer/dashboard.php';
            $dashboard_name = 'Trainer Dashboard';
            break;
        default:
            $dashboard_link = 'login.php';
            $dashboard_name = 'Dashboard';
    }
}

// Get gym statistics from database
$stats = [
    'members' => 0,
    'trainers' => 0,
    'classes' => 50,
    'years' => 10
];

try {
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'member'");
        $stats['members'] = $stmt->fetch()['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'trainer'");
        $stats['trainers'] = $stmt->fetch()['total'] ?? 0;
    }
} catch (Exception $e) {
    // Database might not be set up yet
    error_log("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Professional Gym Management System</title>
    
    <!-- Bootstrap 4 CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    
    <!-- Font Awesome 5 (compatible with Bootstrap 4) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4158D0;
            --primary-dark: #2a3bb3;
            --secondary: #C850C0;
            --accent: #FFCC70;
            --dark: #1a1a2e;
            --light: #f8f9fa;
            --success: #4CAF50;
            --warning: #FF9800;
            --danger: #f44336;
            --info: #2196F3;
            --admin: #dc3545;
            --trainer: #28a745;
            --member: #17a2b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: #333;
            overflow-x: hidden;
            background: #fff;
        }

        /* Preloader */
        .preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: opacity 0.5s;
        }

        .preloader.fade-out {
            opacity: 0;
            pointer-events: none;
        }

        .loader {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(255,255,255,0.2);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Navigation */
        .navbar {
            background: transparent;
            padding: 1.2rem 0;
            transition: all 0.3s ease;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .navbar.scrolled {
            background: rgba(26, 26, 46, 0.95);
            backdrop-filter: blur(10px);
            padding: 0.8rem 0;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-size: 1.8rem;
            font-weight: 800;
            color: #fff !important;
            letter-spacing: -0.5px;
        }

        .navbar-brand i {
            margin-right: 10px;
            color: var(--accent);
            font-size: 2rem;
        }

        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            margin: 0 12px;
            transition: all 0.3s;
            font-size: 1rem;
            position: relative;
        }

        .navbar-nav .nav-link:hover {
            color: #fff !important;
        }

        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background: var(--accent);
            transition: width 0.3s;
        }

        .navbar-nav .nav-link:hover::after,
        .navbar-nav .nav-link.active::after {
            width: 30px;
        }

        .navbar-nav .nav-link.btn {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 30px;
            padding: 0.6rem 1.8rem !important;
            border: none;
        }

        .navbar-nav .nav-link.btn::after {
            display: none;
        }

        .navbar-nav .nav-link.btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        /* Dropdown menu */
        .dropdown-menu {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .dropdown-item {
            color: #fff;
            transition: all 0.3s;
        }

        .dropdown-item:hover {
            background: rgba(255,255,255,0.2);
            color: #fff;
            transform: translateX(5px);
        }

        .dropdown-divider {
            border-top-color: rgba(255,255,255,0.2);
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            position: relative;
            display: flex;
            align-items: center;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-position: bottom;
            background-repeat: no-repeat;
            background-size: cover;
            bottom: 0;
        }

        .hero-content {
            color: #fff;
            padding: 100px 0;
            position: relative;
            z-index: 2;
        }

        .hero h1 {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.2;
            text-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.95;
            max-width: 600px;
        }

        .hero-buttons .btn {
            padding: 1rem 2.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            margin-right: 1rem;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .hero-buttons .btn-primary {
            background: #fff;
            color: var(--primary);
            border: none;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .hero-buttons .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.3);
        }

        .hero-buttons .btn-outline-light {
            border: 2px solid #fff;
            background: transparent;
        }

        .hero-buttons .btn-outline-light:hover {
            background: #fff;
            color: var(--primary);
            transform: translateY(-3px);
        }

        .hero-image {
            position: relative;
            z-index: 2;
            animation: float 3s ease-in-out infinite;
        }

        .hero-image img {
            max-width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            border: 5px solid rgba(255,255,255,0.2);
        }

        /* User Status Bar */
        .user-status {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border-radius: 50px;
            padding: 0.8rem 1.5rem;
            display: inline-flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .user-status i {
            font-size: 1.2rem;
            color: var(--accent);
        }

        .user-status span {
            color: #fff;
            font-weight: 500;
        }

        .user-status a {
            color: #fff;
            text-decoration: none;
            padding: 0.5rem 1.5rem;
            border-radius: 30px;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .user-status a:hover {
            background: #fff;
            color: var(--primary);
        }

        /* Section Titles */
        .section-title {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-title h2 {
            font-size: 2.8rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1rem;
            position: relative;
            display: inline-block;
        }

        .section-title h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 2px;
        }

        .section-title p {
            color: #666;
            font-size: 1.1rem;
            max-width: 700px;
            margin: 1.5rem auto 0;
        }

        /* Login Options Cards (styled like dashboard cards) */
        .login-options {
            padding: 80px 0;
            background: #f8f9fa;
        }

        .login-card {
            background: #fff;
            border-radius: 15px;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.4s;
            height: 100%;
            position: relative;
            overflow: hidden;
            border: none;
        }

        .login-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 20px 40px rgba(65, 88, 208, 0.2);
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .login-card.admin-card::before { background: linear-gradient(90deg, var(--admin), #ff6b6b); }
        .login-card.trainer-card::before { background: linear-gradient(90deg, var(--trainer), #51cf66); }
        .login-card.member-card::before { background: linear-gradient(90deg, var(--member), #20c997); }

        .login-icon {
            width: 100px;
            height: 100px;
            line-height: 100px;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
            color: #fff;
            transition: all 0.4s;
        }

        .admin-card .login-icon { background: linear-gradient(135deg, var(--admin), #ff6b6b); }
        .trainer-card .login-icon { background: linear-gradient(135deg, var(--trainer), #51cf66); }
        .member-card .login-icon { background: linear-gradient(135deg, var(--member), #20c997); }

        .login-card:hover .login-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .login-card h4 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .admin-card h4 { color: var(--admin); }
        .trainer-card h4 { color: var(--trainer); }
        .member-card h4 { color: var(--member); }

        .login-card p {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .login-btn {
            display: inline-block;
            padding: 0.8rem 2rem;
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            color: #fff;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .admin-btn { background: linear-gradient(135deg, var(--admin), #ff6b6b); }
        .trainer-btn { background: linear-gradient(135deg, var(--trainer), #51cf66); }
        .member-btn { background: linear-gradient(135deg, var(--member), #20c997); }

        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            color: #fff;
            text-decoration: none;
        }

        .login-btn i {
            margin-left: 8px;
            transition: transform 0.3s;
        }

        .login-btn:hover i {
            transform: translateX(5px);
        }

        .role-badge {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }

        .admin-badge {
            background: rgba(220,53,69,0.1);
            color: var(--admin);
            border: 1px solid rgba(220,53,69,0.3);
        }

        .trainer-badge {
            background: rgba(40,167,69,0.1);
            color: var(--trainer);
            border: 1px solid rgba(40,167,69,0.3);
        }

        .member-badge {
            background: rgba(23,162,184,0.1);
            color: var(--member);
            border: 1px solid rgba(23,162,184,0.3);
        }

        /* Stats Section (using stats-card from dashboard) */
        .stats-section {
            padding: 60px 0;
            background: #fff;
        }

        .stat-card {
            border: none;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s;
            background: #f8f9fa;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(65, 88, 208, 0.1);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            line-height: 70px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            margin: 0 auto 1.2rem;
            color: #fff;
            font-size: 1.8rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Features Section (using feature cards similar to dashboard) */
        .features {
            padding: 80px 0;
            background: #f8f9fa;
        }

        .feature-card {
            background: #fff;
            padding: 2.5rem 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.03);
            transition: all 0.4s;
            height: 100%;
            border: none;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(65, 88, 208, 0.1);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            line-height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            color: #fff;
            font-size: 2rem;
            transition: all 0.4s;
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .feature-card h4 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--dark);
        }

        .feature-card p {
            color: #666;
            line-height: 1.6;
            margin: 0;
        }

        /* About Section */
        .about {
            padding: 80px 0;
            background: #fff;
        }

        .about-image {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .about-image img {
            width: 100%;
            transition: transform 0.5s;
        }

        .about-image:hover img {
            transform: scale(1.05);
        }

        .about-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(65,88,208,0.3), rgba(200,80,192,0.3));
            z-index: 1;
        }

        .about-content {
            padding-left: 3rem;
        }

        .about-content h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--dark);
        }

        .about-content p {
            color: #666;
            margin-bottom: 1.5rem;
            line-height: 1.8;
        }

        .about-stats {
            margin-top: 2rem;
        }

        .about-stats .stat-item {
            text-align: center;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .about-stats .stat-number {
            font-size: 2rem;
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 0.3rem;
        }

        .about-stats .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        /* CTA Section */
        .cta {
            padding: 80px 0;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-position: bottom;
            background-repeat: no-repeat;
            background-size: cover;
            bottom: 0;
        }

        .cta h2 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            position: relative;
        }

        .cta p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.95;
            position: relative;
        }

        .cta .btn {
            padding: 1.2rem 3rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            background: #fff;
            color: var(--primary);
            border: none;
            transition: all 0.3s;
            position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .cta .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }

        /* Footer */
        .footer {
            background: var(--dark);
            color: #fff;
            padding: 60px 0 20px;
        }

        .footer h5 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #fff;
        }

        .footer p {
            color: rgba(255,255,255,0.7);
            line-height: 1.8;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 0.8rem;
        }

        .footer-links a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
        }

        .footer-links a:hover {
            color: var(--accent);
            transform: translateX(5px);
        }

        .footer-links i {
            margin-right: 10px;
            color: var(--accent);
        }

        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            line-height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            color: #fff;
            text-align: center;
            transition: all 0.3s;
        }

        .social-links a:hover {
            background: var(--accent);
            color: var(--dark);
            transform: translateY(-3px);
        }

        .footer-bottom {
            margin-top: 3rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            color: rgba(255,255,255,0.5);
        }

        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        /* Responsive */
        @media (max-width: 991px) {
            .navbar {
                background: rgba(26, 26, 46, 0.95);
            }
            
            .hero h1 {
                font-size: 3rem;
            }
            
            .hero-image {
                margin-top: 3rem;
            }
            
            .about-content {
                padding-left: 0;
                margin-top: 3rem;
            }
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero p {
                font-size: 1rem;
            }
            
            .hero-buttons .btn {
                display: block;
                margin: 1rem 0;
                text-align: center;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
            
            .cta h2 {
                font-size: 2rem;
            }
            
            .user-status {
                flex-direction: column;
                text-align: center;
                gap: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Preloader -->
    <div class="preloader" id="preloader">
        <div class="loader"></div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-dumbbell"></i> <?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Home</a>
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
                    <?php if ($is_logged_in): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $dashboard_link; ?>">
                                <i class="fas fa-tachometer-alt"></i> <?php echo $dashboard_name; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link btn dropdown-toggle" href="#" id="loginDropdown" role="button" data-toggle="dropdown">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="login.php?type=admin">
                                    <i class="fas fa-user-shield" style="color: var(--admin);"></i> Admin Login
                                </a>
                                <a class="dropdown-item" href="login.php?type=trainer">
                                    <i class="fas fa-chalkboard-teacher" style="color: var(--trainer);"></i> Trainer Login
                                </a>
                                <a class="dropdown-item" href="login.php?type=member">
                                    <i class="fas fa-user" style="color: var(--member);"></i> Member Login
                                </a>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <!-- User Status Bar -->
                        <?php if ($is_logged_in): ?>
                        <div class="user-status" data-aos="fade-up">
                            <i class="fas fa-user-circle"></i>
                            <span>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</span>
                            <a href="<?php echo $dashboard_link; ?>">
                                <i class="fas fa-arrow-right"></i> Go to Dashboard
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <h1 data-aos="fade-up" data-aos-delay="100">
                            Transform Your Body,<br>Transform Your Life
                        </h1>
                        <p data-aos="fade-up" data-aos-delay="200">
                            Join <?php echo APP_NAME; ?> today and start your fitness journey with 
                            state-of-the-art equipment, expert trainers, and a supportive community.
                        </p>
                        <div class="hero-buttons" data-aos="fade-up" data-aos-delay="300">
                            <?php if (!$is_logged_in): ?>
                                <a href="#login-options" class="btn btn-primary">Get Started</a>
                                <a href="#features" class="btn btn-outline-light">Learn More</a>
                            <?php else: ?>
                                <a href="<?php echo $dashboard_link; ?>" class="btn btn-primary">
                                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                                </a>
                                <a href="#features" class="btn btn-outline-light">
                                    <i class="fas fa-star"></i> Explore Features
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image" data-aos="fade-left" data-aos-delay="400">
                        <img src="https://images.unsplash.com/photo-1534438327276-14e5300c3a48?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" 
                             alt="Gym" class="img-fluid">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Login Options Section -->
    <section id="login-options" class="login-options">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2>Choose Your Portal</h2>
                <p>Select your role to access the appropriate dashboard and features</p>
            </div>
            
            <div class="row">
                <!-- Admin Login Card -->
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="login-card admin-card">
                        <div class="role-badge admin-badge">
                            <i class="fas fa-crown"></i> Administrator
                        </div>
                        <div class="login-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h4>Admin Portal</h4>
                        <p>Complete system control with user management, financial reports, equipment tracking, and advanced analytics.</p>
                        <a href="login.php?type=admin" class="login-btn admin-btn">
                            Admin Login <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Trainer Login Card -->
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="login-card trainer-card">
                        <div class="role-badge trainer-badge">
                            <i class="fas fa-chalkboard-teacher"></i> Fitness Trainer
                        </div>
                        <div class="login-icon">
                            <i class="fas fa-dumbbell"></i>
                        </div>
                        <h4>Trainer Portal</h4>
                        <p>Manage classes, track member progress, create workout plans, and communicate with your clients.</p>
                        <a href="login.php?type=trainer" class="login-btn trainer-btn">
                            Trainer Login <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Member Login Card -->
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="login-card member-card">
                        <div class="role-badge member-badge">
                            <i class="fas fa-user"></i> Gym Member
                        </div>
                        <div class="login-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h4>Member Portal</h4>
                        <p>View your membership, book classes, track attendance, make payments, and monitor your fitness progress.</p>
                        <a href="login.php?type=member" class="login-btn member-btn">
                            Member Login <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            

        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format(max($stats['members'], 5000)); ?>+</div>
                        <div class="stat-label">Happy Members</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format(max($stats['trainers'], 50)); ?>+</div>
                        <div class="stat-label">Expert Trainers</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['classes']; ?>+</div>
                        <div class="stat-label">Weekly Classes</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['years']; ?>+</div>
                        <div class="stat-label">Years Experience</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2>Why Choose Us?</h2>
                <p>We provide the best fitness experience with modern equipment and expert guidance</p>
            </div>
            
            <div class="row">
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-dumbbell"></i>
                        </div>
                        <h4>Modern Equipment</h4>
                        <p>State-of-the-art fitness equipment from top brands for optimal workout experience.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h4>Expert Trainers</h4>
                        <p>Certified personal trainers to guide you through your fitness journey.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h4>Flexible Schedule</h4>
                        <p>Open 24/7 with various class timings to fit your busy schedule.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h4>Personalized Plans</h4>
                        <p>Customized workout and nutrition plans tailored to your goals.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="500">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4>Group Classes</h4>
                        <p>Join our energetic group classes for motivation and fun.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="600">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shower"></i>
                        </div>
                        <h4>Premium Amenities</h4>
                        <p>Clean locker rooms, showers, and sauna for your comfort.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <div class="about-image">
                        <img src="https://images.unsplash.com/photo-1570829460005-c840387bb1ca?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" 
                             alt="About Us" class="img-fluid">
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="about-content">
                        <h3>About <?php echo APP_NAME; ?></h3>
                        <p>Founded in 2014, <?php echo APP_NAME; ?> has been helping people achieve their fitness goals for over a decade. Our mission is to provide a welcoming environment where everyone can feel comfortable working out, regardless of their fitness level.</p>
                        <p>We believe that fitness is not just about looking good, but about feeling good and living a healthier life. Our team of expert trainers is dedicated to helping you reach your full potential through personalized workout plans and nutritional guidance.</p>
                        
                        <div class="about-stats">
                            <div class="row">
                                <div class="col-4">
                                    <div class="stat-item">
                                        <div class="stat-number">15+</div>
                                        <div class="stat-label">Years</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stat-item">
                                        <div class="stat-number">10k+</div>
                                        <div class="stat-label">Members</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stat-item">
                                        <div class="stat-number">100%</div>
                                        <div class="stat-label">Satisfaction</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <h2 data-aos="fade-up">Ready to Start Your Fitness Journey?</h2>
            <p data-aos="fade-up" data-aos-delay="100">Join <?php echo APP_NAME; ?> today and get 7 days free trial!</p>
            <?php if (!$is_logged_in): ?>
                <a href="#login-options" class="btn" data-aos="fade-up" data-aos-delay="200">
                    <i class="fas fa-rocket mr-2"></i>Get Started Now
                </a>
            <?php else: ?>
                <a href="<?php echo $dashboard_link; ?>" class="btn" data-aos="fade-up" data-aos-delay="200">
                    <i class="fas fa-tachometer-alt mr-2"></i>Go to Dashboard
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="row">
                <div class="col-lg-4">
                    <h5><i class="fas fa-dumbbell"></i> <?php echo APP_NAME; ?></h5>
                    <p>Your trusted partner in fitness. We're committed to helping you achieve your health and fitness goals with state-of-the-art facilities and expert guidance.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6">
                    <h5>Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="#home"><i class="fas fa-chevron-right"></i> Home</a></li>
                        <li><a href="#features"><i class="fas fa-chevron-right"></i> Features</a></li>
                        <li><a href="#about"><i class="fas fa-chevron-right"></i> About</a></li>
                        <li><a href="#contact"><i class="fas fa-chevron-right"></i> Contact</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <h5>Contact Info</h5>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt"></i> K.Tadawale,Dharashiv</li>
                        <li><i class="fas fa-phone"></i> +91 8308759124</li>
                        <li><i class="fas fa-envelope"></i> sidhuanpat07@gmail.com</li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <h5>Business Hours</h5>
                    <ul class="footer-links">
                        <li><i class="fas fa-clock"></i> Mon - Fri: 6am - 11pm</li>
                        <li><i class="fas fa-clock"></i> Saturday: 8am - 10pm</li>
                        <li><i class="fas fa-clock"></i> Sunday: 8am - 8pm</li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> Management System. All rights reserved. | Developed with <i class="fas fa-heart" style="color: #ff4757;"></i> for fitness enthusiasts</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100,
            easing: 'ease-in-out'
        });

        // Preloader
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('preloader').classList.add('fade-out');
            }, 800);
        });

        // Navbar scroll effect
        $(window).scroll(function() {
            if ($(this).scrollTop() > 100) {
                $('#mainNav').addClass('scrolled');
            } else {
                $('#mainNav').removeClass('scrolled');
            }
        });

        // Smooth scrolling for anchor links
        $('a[href*="#"]').on('click', function(e) {
            e.preventDefault();
            
            const target = $(this).attr('href');
            
            if (target !== '#') {
                $('html, body').animate({
                    scrollTop: $(target).offset().top - 70
                }, 800, 'swing');
            }
        });

        // Active nav link on scroll
        $(window).on('scroll', function() {
            const scrollPos = $(this).scrollTop();
            
            $('section').each(function() {
                const top = $(this).offset().top - 100;
                const bottom = top + $(this).outerHeight();
                
                if (scrollPos >= top && scrollPos <= bottom) {
                    const id = $(this).attr('id');
                    $('.nav-link').removeClass('active');
                    $(`.nav-link[href="#${id}"]`).addClass('active');
                }
            });
        });

        // Counter animation
        function animateCounter(element, start, end, duration) {
            let startTimestamp = null;
            
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                element.innerHTML = Math.floor(progress * (end - start) + start);
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            
            window.requestAnimationFrame(step);
        }

        // Trigger counters when they come into view
        const observerOptions = {
            threshold: 0.5,
            rootMargin: '0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const counters = entry.target.querySelectorAll('.stat-number');
                    
                    counters.forEach(counter => {
                        const value = parseInt(counter.innerText.replace(/[^0-9]/g, ''));
                        animateCounter(counter, 0, value, 2000);
                    });
                    
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // Observe stats sections
        document.querySelectorAll('.stats-section, .about-stats').forEach(section => {
            observer.observe(section);
        });

        // Handle login type from URL
        $(document).ready(function() {
            const urlParams = new URLSearchParams(window.location.search);
            const loginType = urlParams.get('login');
            
            if (loginType) {
                $('html, body').animate({
                    scrollTop: $('#login-options').offset().top - 70
                }, 500);
                
                // Highlight the selected card
                $(`.${loginType}-card`).addClass('shadow-lg');
            }
        });

        // Dropdown hover effect (optional, can be removed if not needed)
        $('.dropdown').hover(function() {
            $(this).find('.dropdown-menu').first().stop(true, true).delay(250).slideDown();
        }, function() {
            $(this).find('.dropdown-menu').first().stop(true, true).delay(100).slideUp();
        });
    </script>
</body>
</html>