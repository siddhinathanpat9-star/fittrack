<?php
// forgot_password.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start(); // Start output buffering to prevent header issues

require_once __DIR__ . '/includes/config.php'; // This already includes vendor/autoload and defines helpers
require_once __DIR__ . '/includes/session.php';

$error = '';
$success = '';

// Determine role type for display (admin/trainer/member)
$type = isset($_GET['type']) && in_array($_GET['type'], ['admin', 'trainer', 'member']) ? $_GET['type'] : 'member';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $email = trim($_POST['email']);

    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if user exists (without revealing existence)
        try {
            $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Always show the same message to prevent email enumeration
            $success = 'If your email is registered, you will receive a reset link shortly.';

            if ($user) {
                // Begin transaction to ensure token consistency
                $pdo->beginTransaction();

                // Generate a secure token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Delete any existing tokens for this email
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmt->execute([$email]);

                // Insert new token
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expires]);

                // Commit transaction before sending email
                $pdo->commit();

                // Send email using the helper function from config.php
                if (sendPasswordResetEmail($email, $token, $type)) {
                    error_log("Password reset email sent successfully to: $email");
                } else {
                    // Email failed – rollback the token
                    try {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
                        $stmt->execute([$token]);
                        $pdo->commit();
                    } catch (Exception $rollbackEx) {
                        error_log("Rollback error: " . $rollbackEx->getMessage());
                    }

                    // Set error message for user
                    $error = 'Failed to send email. Please try again later or contact support.';
                    $success = ''; // Override the generic success message
                }
            }
        } catch (Exception $e) {
            // Database or other errors
            error_log("Forgot password error: " . $e->getMessage());
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'An unexpected error occurred. Please try again later.';
        }
    }
}

$page_title = 'Forgot Password - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 4, Font Awesome -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Google Fonts (optional) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
            margin: 0 auto;
            background: #fff;
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 0;
            padding: 35px 20px;
            text-align: center;
            color: #fff;
            border: none;
        }
        .card-header i {
            font-size: 3.5rem;
            margin-bottom: 15px;
            background: rgba(255, 255, 255, 0.2);
            width: 80px;
            height: 80px;
            line-height: 80px;
            border-radius: 50%;
            display: inline-block;
        }
        .card-header h4 {
            font-weight: 600;
            font-size: 1.8rem;
            margin: 0;
            letter-spacing: -0.5px;
        }
        .card-body {
            padding: 2.5rem 2rem;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .input-group {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border-radius: 50px;
            overflow: hidden;
        }
        .input-group-text {
            background: #fff;
            border: 1px solid #e1e5eb;
            border-right: none;
            color: #667eea;
            font-size: 1.1rem;
            padding: 0.75rem 1rem;
        }
        .form-control {
            border: 1px solid #e1e5eb;
            border-left: none;
            border-radius: 0 50px 50px 0;
            height: auto;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: none;
            outline: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 50px;
            padding: 14px 25px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            width: 100%;
            margin-top: 10px;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        .btn-primary i {
            margin-right: 8px;
        }
        .alert {
            border: none;
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 25px;
            font-size: 0.95rem;
        }
        .alert-danger {
            background: #fee;
            color: #c33;
        }
        .alert-success {
            background: #e3f9e5;
            color: #1a7d3a;
        }
        hr {
            border-top: 2px solid #e9ecef;
            margin: 1.8rem 0;
        }
        .text-muted a {
            color: #667eea;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.3s;
        }
        .text-muted a:hover {
            color: #5a67d8;
            text-decoration: underline;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .back-link i {
            font-size: 0.9rem;
        }
        .text-center p {
            margin-bottom: 0;
        }
        @media (max-width: 576px) {
            .card-header {
                padding: 25px 15px;
            }
            .card-header i {
                font-size: 3rem;
                width: 70px;
                height: 70px;
                line-height: 70px;
            }
            .card-header h4 {
                font-size: 1.5rem;
            }
            .card-body {
                padding: 1.8rem 1.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-key"></i>
                        <h4>Forgot Password</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php endif; ?>

                        <p class="text-muted text-center mb-4" style="font-size: 0.95rem;">
                            Enter your email address and we'll send you a link to reset your password.
                        </p>

                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    </div>
                                    <input type="email" class="form-control" id="email" name="email"
                                           placeholder="Enter your email" required
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                            </div>

                            <button type="submit" name="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Reset Link
                            </button>
                        </form>

                        <hr class="my-4">

                        <p class="text-center text-muted mb-0">
                            <a href="login.php<?php echo $type ? '?type=' . urlencode($type) : ''; ?>" class="back-link">
                                <i class="fas fa-arrow-left"></i> Back to Login
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    </script>
</body>
</html>