<?php
// login.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once 'includes/config.php';
require_once 'includes/session.php';

// Redirect if already logged in
if (Session::isLoggedIn()) {
    if (Session::isAdmin()) {
        header('Location: admin/dashboard.php');
    } elseif (Session::isTrainer()) {
        header('Location: trainer/dashboard.php');
    } elseif (Session::isMember()) {
        header('Location: member/dashboard.php');
    }
    exit();
}

$login_type = isset($_GET['type']) && in_array($_GET['type'], ['admin','trainer','member']) ? $_GET['type'] : 'member';

// Role-specific configuration
$role_config = [
    'admin' => [
        'title' => 'Admin Login',
        'icon' => 'fas fa-user-shield',
        'color' => '#dc3545',
        'badge' => 'danger'
    ],
    'trainer' => [
        'title' => 'Trainer Login',
        'icon' => 'fas fa-chalkboard-teacher',
        'color' => '#28a745',
        'badge' => 'success'
    ],
    'member' => [
        'title' => 'Member Login',
        'icon' => 'fas fa-user',
        'color' => '#17a2b8',
        'badge' => 'info'
    ]
];
$config = $role_config[$login_type];

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $submitted_type = $_POST['login_type'] ?? 'member';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter username/email and password';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'active') {
                    if ($user['user_type'] !== $submitted_type) {
                        $error = "Invalid login portal. Please use the correct login page for your account type.";
                    } else {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['user_type'] = $user['user_type'];
                        
                        error_log("User logged in: " . $user['username'] . " (" . $user['user_type'] . ")");
                        
                        switch($user['user_type']) {
                            case 'admin':
                                header('Location: admin/dashboard.php');
                                break;
                            case 'trainer':
                                header('Location: trainer/dashboard.php');
                                break;
                            default:
                                header('Location: member/dashboard.php');
                        }
                        exit();
                    }
                } else {
                    $error = "Your account is inactive. Please contact admin.";
                }
            } else {
                $error = "Invalid username/email or password";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$page_title = $config['title'] . ' - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 4 CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
        }
        .login-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
            margin: 0 auto;
        }
        .login-header {
            padding: 30px;
            text-align: center;
            background: linear-gradient(135deg, <?php echo $config['color']; ?> 0%, <?php echo $config['color']; ?>dd 100%);
            color: white;
        }
        .login-header i {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .login-header h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
        }
        .login-body {
            padding: 30px;
            background: #fff;
        }
        .role-badge {
            text-align: center;
            margin-bottom: 20px;
        }
        .role-badge .badge {
            padding: 8px 20px;
            font-size: 0.9rem;
            font-weight: 500;
            border-radius: 30px;
        }
        .input-group-text {
            background: #fff;
            border-right: none;
            color: <?php echo $config['color']; ?>;
        }
        .form-control {
            border-left: none;
            border-radius: 0 30px 30px 0;
            height: 45px;
        }
        .form-control:focus {
            border-color: <?php echo $config['color']; ?>;
            box-shadow: 0 0 0 0.2rem <?php echo $config['color']; ?>33;
        }
        .input-group-prepend .input-group-text {
            border-radius: 30px 0 0 30px;
        }
        .input-group-append .btn {
            border-radius: 0 30px 30px 0;
            border-left: none;
        }
        .btn-login {
            background: linear-gradient(135deg, <?php echo $config['color']; ?> 0%, <?php echo $config['color']; ?>dd 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 12px;
            border-radius: 30px;
            width: 100%;
            transition: all 0.3s;
            margin-top: 15px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px <?php echo $config['color']; ?>80;
            color: white;
        }
        .btn-login i {
            margin-left: 8px;
            transition: transform 0.3s;
        }
        .btn-login:hover i {
            transform: translateX(5px);
        }
        .role-switch {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 20px 0;
        }
        .role-switch .btn {
            border-radius: 30px;
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #495057;
        }
        .role-switch .btn:hover {
            transform: translateY(-2px);
        }
        .role-switch .btn.active {
            background: <?php echo $config['color']; ?>;
            color: white;
            border-color: <?php echo $config['color']; ?>;
        }
        .forgot-link {
            color: <?php echo $config['color']; ?>;
            font-size: 0.9rem;
        }
        .forgot-link:hover {
            text-decoration: underline;
        }
        .register-link {
            color: <?php echo $config['color']; ?>;
            font-weight: 600;
        }
        .register-link:hover {
            text-decoration: underline;
        }
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 20px;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        @media (max-width: 576px) {
            .login-header {
                padding: 20px;
            }
            .login-header h2 {
                font-size: 1.5rem;
            }
            .login-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="<?php echo $config['icon']; ?>"></i>
            <h2><?php echo $config['title']; ?></h2>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>

            <?php Session::displayFlash(); ?>

            <div class="role-badge">
                <span class="badge badge-<?php echo $config['badge']; ?>">
                    <i class="<?php echo $config['icon']; ?> mr-1"></i> <?php echo ucfirst($login_type); ?> Access
                </span>
            </div>

            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="login_type" value="<?php echo $login_type; ?>">

                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control" id="username" name="username"
                               placeholder="Enter your username or email"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                               required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        </div>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Enter your password" required>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label" for="remember">Remember Me</label>
                    </div>
                    <a href="forgot_password.php?type=<?php echo $login_type; ?>" class="forgot-link">
                        <i class="fas fa-key mr-1"></i>Forgot Password?
                    </a>
                </div>

                <button type="submit" class="btn-login">
                    Login <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="role-switch">
                <a href="?type=admin" class="btn <?php echo $login_type == 'admin' ? 'active' : ''; ?>">
                    <i class="fas fa-user-shield"></i> Admin
                </a>
                <a href="?type=trainer" class="btn <?php echo $login_type == 'trainer' ? 'active' : ''; ?>">
                    <i class="fas fa-chalkboard-teacher"></i> Trainer
                </a>
                <a href="?type=member" class="btn <?php echo $login_type == 'member' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i> Member
                </a>
            </div>

            <hr class="my-4">
            <p class="text-center mb-0">
                <a href="index.php" class="text-muted"><i class="fas fa-home mr-1"></i>Back to Home</a>
            </p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Password visibility toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Disable button on submit to prevent double submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
        });
    </script>
</body>
</html>