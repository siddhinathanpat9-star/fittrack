<?php
// admin/export_users.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is admin
if (!Session::isAdmin()) {
    Session::setFlash('danger', 'Access denied. Admin login required.');
    header('Location: ../login.php');
    exit();
}

// If export parameter is present, generate CSV download
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=users_' . date('Y-m-d') . '.csv');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add CSV headers (adjust as needed)
    fputcsv($output, [
        'ID',
        'Username',
        'Email',
        'Full Name',
        'Phone',
        'User Type',
        'Status',
        'Created At'
    ]);

    // Fetch users from database
    try {
        $stmt = $pdo->query("
            SELECT id, username, email, full_name, phone, user_type, status, created_at
            FROM users
            ORDER BY id
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
    } catch (Exception $e) {
        // If error, we can't output anything after headers, so just log
        error_log("Export error: " . $e->getMessage());
    }

    fclose($output);
    exit();
}

$page_title = 'Export Users - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 4 CSS (as used in member dashboard) -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f8f9fa;
            overflow-x: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom: none;
            padding: 25px 30px;
        }
        .card-header h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.8rem;
        }
        .card-header i {
            margin-right: 10px;
        }
        .card-body {
            padding: 30px;
            text-align: center;
        }
        .btn-export {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 15px 40px;
            font-size: 1.2rem;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 20px;
        }
        .btn-export:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(102,126,234,0.3);
            color: white;
        }
        .btn-export i {
            margin-right: 10px;
        }
        .info-text {
            color: #6c757d;
            margin-bottom: 20px;
        }
        .footer-note {
            margin-top: 30px;
            font-size: 0.9rem;
            color: #adb5bd;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h4><i class="fas fa-file-export"></i> Export Users</h4>
                    </div>
                    <div class="card-body">
                        <i class="fas fa-users fa-4x text-primary mb-3"></i>
                        <h5>Download All Users as CSV</h5>
                        <p class="info-text">
                            Click the button below to export the complete list of users in CSV format.
                            The file will include user ID, username, email, full name, phone, user type, status, and creation date.
                        </p>
                        <a href="?export=csv" class="btn btn-export">
                            <i class="fas fa-download"></i> Export Now
                        </a>
                        <div class="footer-note">
                            <i class="fas fa-shield-alt"></i> Secure export • Data is current as of <?php echo date('F j, Y'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap and jQuery (for any potential future use) -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>