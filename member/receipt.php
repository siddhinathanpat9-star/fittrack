<?php
// member/receipt.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

$root_path = dirname(__DIR__);
require_once $root_path . '/includes/config.php';
require_once $root_path . '/includes/session.php';
require_once $root_path . '/includes/functions.php';

// Check if user is member
if (!Session::isMember()) {
    Session::setFlash('danger', 'Access denied. Member login required.');
    header('Location: ' . $root_path . '/login.php');
    exit();
}

$member_id = Session::userId();
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($payment_id <= 0) {
    header('Location: payments.php');
    exit();
}

// Fetch payment details, ensuring it belongs to this member
$payment = null;
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name as member_name, u.email as member_email
        FROM payments p
        JOIN users u ON p.member_id = u.id
        WHERE p.id = ? AND p.member_id = ?
    ");
    $stmt->execute([$payment_id, $member_id]);
    $payment = $stmt->fetch();
} catch (Exception $e) {
    // Handle error
    header('Location: payments.php');
    exit();
}

if (!$payment) {
    header('Location: payments.php');
    exit();
}

$page_title = 'Payment Receipt - ' . APP_NAME;
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
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .receipt-card {
            max-width: 800px;
            margin: 30px auto;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .receipt-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border-radius: 15px 15px 0 0;
            padding: 30px;
            text-align: center;
        }
        .receipt-header h2 {
            margin: 10px 0 0;
            font-weight: 600;
        }
        .receipt-body {
            background: #fff;
            padding: 30px;
            border-radius: 0 0 15px 15px;
        }
        .receipt-details {
            margin: 20px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
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
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        .status-paid { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-refunded { background: #e2e3e5; color: #383d41; }
        .print-btn {
            background: #667eea;
            color: #fff;
            border: none;
            padding: 10px 30px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .print-btn:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        @media print {
            .no-print { display: none; }
            .receipt-card { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="receipt-card">
            <div class="receipt-header">
                <i class="fas fa-receipt fa-4x"></i>
                <h2>Payment Receipt</h2>
                <p>Receipt #: <?php echo str_pad($payment['id'], 6, '0', STR_PAD_LEFT); ?></p>
            </div>
            <div class="receipt-body">
                <div class="text-center mb-4 no-print">
                    <button onclick="window.print()" class="print-btn">
                        <i class="fas fa-print mr-2"></i>Print Receipt
                    </button>
                    <a href="payments.php" class="btn btn-outline-secondary ml-2">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Payments
                    </a>
                </div>

                <div class="receipt-details">
                    <div class="detail-row">
                        <span class="detail-label">Member Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($payment['member_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Member Email:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($payment['member_email']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Date:</span>
                        <span class="detail-value"><?php echo date('F j, Y \a\t h:i A', strtotime($payment['payment_date'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Amount:</span>
                        <span class="detail-value"><strong>₹<?php echo number_format($payment['amount'], 2); ?></strong></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Method:</span>
                        <span class="detail-value"><?php echo ucfirst($payment['payment_method']); ?></span>
                    </div>
                    <?php if (!empty($payment['transaction_id'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Transaction ID:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($payment['transaction_id']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <span class="detail-label">Payment For:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($payment['payment_for']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">
                            <span class="status-badge status-<?php echo $payment['status']; ?>">
                                <?php echo ucfirst($payment['status']); ?>
                            </span>
                        </span>
                    </div>
                    <?php if (!empty($payment['notes'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Notes:</span>
                        <span class="detail-value"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="text-center mt-4 text-muted small">
                    <i class="fas fa-dumbbell mr-1"></i> <?php echo APP_NAME; ?> - Official Payment Receipt
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>