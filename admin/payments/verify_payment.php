<?php

require_once '../includes/config.php';
require_once '../includes/session.php';

if(!Session::isMember()){
header("Location: ../login.php");
exit();
}

$member_id = Session::userId();

$razorpay_id = $_GET['razorpay_id'] ?? '';
$payment_id = $_GET['payment_id'] ?? '';

if($razorpay_id && $payment_id){

$stmt = $pdo->prepare("
UPDATE payments
SET status='paid',
payment_method='razorpay',
transaction_id=?,
payment_date=NOW()
WHERE id=?
");

$stmt->execute([$razorpay_id,$payment_id]);

}

header("Location: payments.php");
exit();