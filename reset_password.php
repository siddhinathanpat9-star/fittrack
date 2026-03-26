<?php
require_once __DIR__ . '/includes/config.php';

$error = '';
$success = '';

$token = $_GET['token'] ?? '';

if (!$token) {
    die("Invalid reset link.");
}

// Check token in database
$stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    die("Reset link is invalid or expired.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {

        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // Update user password
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hashed, $reset['email']]);

        // Delete token
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$reset['email']]);

        $success = "Password reset successfully. You can now login.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Reset Password</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>

<body style="background:#f5f5f5">

<div class="container mt-5">
<div class="row justify-content-center">
<div class="col-md-5">

<div class="card">
<div class="card-header text-center">
<h4>Reset Password</h4>
</div>

<div class="card-body">

<?php if($error): ?>
<div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if($success): ?>
<div class="alert alert-success"><?php echo $success; ?></div>
<a href="login.php" class="btn btn-primary btn-block">Go to Login</a>
<?php else: ?>

<form method="POST">

<div class="form-group">
<label>New Password</label>
<input type="password" name="password" class="form-control" required>
</div>

<div class="form-group">
<label>Confirm Password</label>
<input type="password" name="confirm_password" class="form-control" required>
</div>

<button type="submit" class="btn btn-primary btn-block">
Reset Password
</button>

</form>

<?php endif; ?>

</div>
</div>

</div>
</div>
</div>

</body>
</html>