<?php

require_once '../../includes/config.php';

$payment_id = $_GET['payment_id'];
$razorpay_id = $_GET['razorpay_id'];

try{

$stmt = $pdo->prepare("
UPDATE payments 
SET 
status='paid',
payment_method='razorpay',
transaction_id=?
WHERE id=?
");

$stmt->execute([$razorpay_id,$payment_id]);

header("Location: manage_payments.php?success=1");

}catch(Exception $e){

echo "Error : ".$e->getMessage();

}

?>