<?php

require_once '../includes/config.php';
require_once '../includes/session.php';

if(!Session::isMember()){
    header("Location: ../login.php");
    exit();
}

$member_id = Session::userId();

$razorpay_id = $_GET['razorpay_payment_id'] ?? '';
$amount = $_GET['amount'] ?? 0;
$plan = $_GET['plan'] ?? '';
$payment_id = $_GET['payment_id'] ?? '';

if($razorpay_id){

$method = "Razorpay";

// New plan payment
if(!$payment_id){

$stmt = $pdo->prepare("
INSERT INTO payments
(member_id, amount, payment_date, payment_method, payment_for, status)
VALUES (?, ?, NOW(), ?, ?, 'paid')
");

$stmt->execute([
$member_id,
$amount,
$method,
$plan.' Membership'
]);

}

// Paying pending payment
else{

$stmt = $pdo->prepare("
UPDATE payments
SET status='paid',
payment_method=?,
payment_date=NOW()
WHERE id=? AND member_id=?
");

$stmt->execute([
$method,
$payment_id,
$member_id
]);

}

}

header("Location: payments.php");
exit();