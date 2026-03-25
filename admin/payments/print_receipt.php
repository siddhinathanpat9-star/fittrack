<?php
// admin/payments/print_receipt.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is admin
if (!Session::isAdmin()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

$functions = new Functions();
$error = '';

// Get payment ID from URL
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($payment_id <= 0) {
    $error = 'Invalid payment ID.';
} else {
    // Fetch payment details with member info
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, u.full_name as member_name, u.email as member_email
            FROM payments p
            JOIN users u ON p.member_id = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();

        if (!$payment) {
            $error = 'Payment not found.';
        }
    } catch (Exception $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

$page_title = 'Payment Receipt - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <!-- Bootstrap 4 CSS (local) -->
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <!-- Font Awesome 5 (local) -->
    <link rel="stylesheet" href="../../assets/css/all.min.css">
    <style>
        /* Hide navigation and sidebar when printing */
        @media print {
            .no-print, .btn, .navbar, .sidebar, .wrapper > .sidebar,
            .wrapper > .content, .loading-spinner {
                display: none !important;
            }
            body, .container, .card {
                background: white !important;
                color: black !important;
                box-shadow: none !important;
            }
            .card {
                border: 1px solid #ddd !important;
            }
        }

        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }

        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .receipt-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }

        .receipt-header h2 {
            margin: 10px 0 5px;
            font-weight: 600;
        }

        .receipt-details {
            background: white;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
        }

        .detail-value {
            color: #212529;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 30px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
        }

        .status-paid {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        .status-refunded {
            background: #e2e3e5;
            color: #383d41;
        }

        .print-btn {
            text-align: center;
            margin: 30px 0;
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php elseif (isset($payment)): ?>
            <div class="receipt-header no-print">
                <i class="fas fa-dumbbell fa-4x"></i>
                <h2><?php echo APP_NAME; ?></h2>
                <p>Payment Receipt</p>
            </div>

            <div class="receipt-details">
                <div class="text-center mb-4">
                    <h3>Payment Receipt</h3>
                    <p class="text-muted">Receipt #: <?php echo str_pad($payment['id'], 6, '0', STR_PAD_LEFT); ?></p>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Payment Date:</span>
                    <span class="detail-value"><?php echo date('F j, Y h:i A', strtotime($payment['payment_date'])); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Member Name:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($payment['member_name']); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Member Email:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($payment['member_email']); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Amount:</span>
                    <span class="detail-value"><strong>₹ <?php echo number_format($payment['amount'], 2); ?></strong></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Payment Method:</span>
                    <span class="detail-value"><?php echo ucfirst($payment['payment_method'] ?? ''); ?></span>
                </div>

                <?php if (!empty($payment['transaction_id'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Transaction ID:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($payment['transaction_id']); ?></span>
                </div>
                <?php endif; ?>

                <div class="detail-row">
                    <span class="detail-label">Payment For:</span>
                    <span class="detail-value"><?php echo ucfirst($payment['payment_for'] ?? ''); ?></span>
                </div>

                <?php if (!empty($payment['notes'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Notes:</span>
                    <span class="detail-value"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></span>
                </div>
                <?php endif; ?>

                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <?php
                        $status_class = '';
                        switch ($payment['status']) {
                            case 'paid': $status_class = 'status-paid'; break;
                            case 'pending': $status_class = 'status-pending'; break;
                            case 'failed': $status_class = 'status-failed'; break;
                            case 'refunded': $status_class = 'status-refunded'; break;
                            default: $status_class = 'status-pending';
                        }
                        ?>
                        <span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($payment['status'] ?? ''); ?></span>
                    </span>
                </div>

                <div class="text-center mt-4 text-muted">
                    <small>Thank you for your business!</small>
                </div>
            </div>

            <div class="print-btn no-print">
                <button onclick="window.print()" class="btn btn-primary btn-lg">
                    <i class="fas fa-print mr-2"></i> Print Receipt
                </button>
                <a href="manage_payments.php" class="btn btn-secondary btn-lg ml-2">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Payments
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Optional: small footer with gym details that will print -->
    <div class="no-print text-center text-muted mt-5">
        <small>Receipt generated on <?php echo date('F j, Y'); ?></small>
    </div>

    <!-- Scripts (minimal, only for print button) -->
    <script src="../../assets/js/jquery-3.5.1.min.js"></script>
    <script src="../../assets/js/popper.min.js"></script>
    <script src="../../assets/js/bootstrap.min.js"></script>
</body>
</html>