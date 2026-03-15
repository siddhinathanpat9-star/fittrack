<?php

require('vendor/razorpay/razorpay/Razorpay.php');

use Razorpay\Api\Api;

$keyId = "YOUR_KEY_ID";
$keySecret = "YOUR_KEY_SECRET";

$api = new Api($keyId, $keySecret);

$orderData = [
    'receipt'         => 'order_rcptid_11',
    'amount'          => 50000, // ₹500 (amount in paise)
    'currency'        => 'INR'
];

$order = $api->order->create($orderData);

echo $order['id'];

?>