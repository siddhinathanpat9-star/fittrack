<?php
// member/process_progress.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure only logged-in members can access this page
if (!Session::isLoggedIn()) {
    Session::setFlash('danger', 'You must be logged in to perform this action.');
    header('Location: ../login.php');
    exit;
}
if (Session::userType() !== 'member') {
    Session::setFlash('danger', 'Access denied. Member login required.');
    header('Location: ../index.php');
    exit;
}

$user_id = Session::userId();
$action = $_POST['action'] ?? '';

try {
    if ($action === 'add') {
        // Add new progress entry
        $recorded_date = $_POST['recorded_at'] ?? date('Y-m-d');
        $weight = $_POST['weight'] ?? null;
        $body_fat = $_POST['body_fat'] ?? null;
        $notes = $_POST['notes'] ?? null;

        if (!$weight) {
            Session::setFlash('danger', 'Weight is required.');
            header('Location: progress.php');
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO progress_tracking (member_id, recorded_date, weight, body_fat, notes, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $recorded_date, $weight, $body_fat, $notes]);

        Session::setFlash('success', 'Progress entry added successfully!');
    } 
    elseif ($action === 'delete') {
        // Delete progress entry
        $measurement_id = $_POST['measurement_id'] ?? null;

        if (!$measurement_id) {
            Session::setFlash('danger', 'Invalid measurement ID.');
            header('Location: progress.php');
            exit;
        }

        // Verify that this measurement belongs to the logged-in user
        $stmt = $pdo->prepare("
            SELECT id FROM progress_tracking 
            WHERE id = ? AND member_id = ?
        ");
        $stmt->execute([$measurement_id, $user_id]);
        $measurement = $stmt->fetch();

        if (!$measurement) {
            Session::setFlash('danger', 'Measurement not found or you do not have permission to delete it.');
            header('Location: progress.php');
            exit;
        }

        // Delete the measurement
        $stmt = $pdo->prepare("DELETE FROM progress_tracking WHERE id = ?");
        $stmt->execute([$measurement_id]);

        Session::setFlash('success', 'Measurement deleted successfully!');
    } 
    else {
        Session::setFlash('danger', 'Invalid action.');
    }
} catch (Exception $e) {
    Session::setFlash('danger', 'Error processing request: ' . $e->getMessage());
}

header('Location: progress.php');
exit;
?>
