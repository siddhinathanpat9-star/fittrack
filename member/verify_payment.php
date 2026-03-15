<?php

require_once "../includes/config.php";

$payment_id = $_GET['payment_id'];
$razorpay_id = $_GET['razorpay_id'];

$stmt = $pdo->prepare("UPDATE payments SET status='paid', payment_method='razorpay', transaction_id=? WHERE id=?");
$stmt->execute([$razorpay_id,$payment_id]);

header("Location: payments.php?success=1");
exit();

?>