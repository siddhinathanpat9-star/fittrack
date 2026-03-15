<?php
// admin/payments/record_payment_handler.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is admin
if (!Session::isAdmin()) {
    header('Location: ../../login.php');
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['record_payment'])) {
    Session::setFlash('danger', 'Invalid request.');
    header('Location: manage_payments.php');
    exit();
}

$functions = new Functions();

// Get and validate input
$member_id = (int)$_POST['member_id'];
$amount = (float)$_POST['amount'];
$payment_date = $_POST['payment_date'] ?: date('Y-m-d H:i:s');
$payment_method = $_POST['payment_method'];
$payment_for = $_POST['payment_for'];
$transaction_id = trim($_POST['transaction_id']) ?: null;
$notes = trim($_POST['notes']) ?: null;
$status = $_POST['status'];

$errors = [];
if ($member_id <= 0) $errors[] = "Please select a member.";
if ($amount <= 0) $errors[] = "Amount must be greater than zero.";

if (!empty($errors)) {
    Session::setFlash('danger', implode('<br>', $errors));
    header('Location: record_payment.php');
    exit();
}

// Insert payment
try {
    $stmt = $pdo->prepare("
        INSERT INTO payments (member_id, amount, payment_date, payment_method, payment_for, transaction_id, notes, status, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$member_id, $amount, $payment_date, $payment_method, $payment_for, $transaction_id, $notes, $status, Session::userId()]);

    Session::setFlash('success', 'Payment recorded successfully.');
} catch (Exception $e) {
    error_log("Payment insert error: " . $e->getMessage());
    Session::setFlash('danger', 'Error recording payment: ' . $e->getMessage());
}

header('Location: manage_payments.php');
exit();