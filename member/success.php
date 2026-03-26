<?php
// member/success.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Require member login
if (!Session::isMember()) {
    header('Location: ../login.php');
    exit();
}

$member_id = Session::userId();
$payment_id = isset($_GET['payment_id']) ? trim($_GET['payment_id']) : '';

if (empty($payment_id)) {
    header('Location: dashboard.php');
    exit();
}

$page_title = 'Payment Success - ' . APP_NAME;
$user_name = Session::userName(); // for potential display
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 4, Font Awesome, and custom styling (same as admin dashboard) -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Reuse core styles from admin dashboard for consistency */
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header.bg-success{background:linear-gradient(135deg,#28a745 0%,#20c997 100%)!important;border-bottom:none;color:#fff}.card-header.bg-success h3,.card-header.bg-success i{color:#fff}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-info{background:#d1ecf1;color:#0c5460}.btn-primary{background:#667eea;border-color:#667eea}.btn-primary:hover{background:#5a67d8;border-color:#5a67d8}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}.alert-info{background:#d1ecf1;color:#0c5460}.text-muted{color:#6c757d!important}.display-4{font-size:2.5rem;font-weight:300;line-height:1.2}.loading-spinner{display:none}.spinner-border{width:3rem;height:3rem;color:#667eea}.avatar-circle{width:35px;height:35px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold}
    </style>
</head>
<body>
    <div class="loading-spinner" id="loadingSpinner"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-lg border-0 rounded-lg">
                    <div class="card-header bg-success text-white text-center py-4">
                        <i class="fas fa-check-circle fa-4x mb-3"></i>
                        <h3 class="mb-0">Payment Successful!</h3>
                    </div>
                    <div class="card-body p-4 text-center">
                        <p class="lead">Thank you for your payment, <strong><?php echo htmlspecialchars($user_name ?? 'Member'); ?></strong>.</p>
                        <p>Your transaction ID is:</p>
                        <div class="alert alert-info">
                            <strong><?php echo htmlspecialchars($payment_id); ?></strong>
                        </div>
                        <p>A confirmation email has been sent to your registered email address.</p>
                        <hr>
                        <a href="dashboard.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-tachometer-alt mr-2"></i>Go to Dashboard
                        </a>
                    </div>
                    <div class="card-footer text-center text-muted">
                        <small>You will be redirected to dashboard in <span id="countdown">10</span> seconds.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts (jQuery, Bootstrap, and countdown) -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Countdown and redirect
        let seconds = 10;
        const countdownEl = document.getElementById('countdown');
        const interval = setInterval(() => {
            seconds--;
            countdownEl.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(interval);
                window.location.href = 'dashboard.php';
            }
        }, 1000);

        // Optional: fade out any alerts after 5 seconds (if any were added)
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    </script>
</body>
</html>