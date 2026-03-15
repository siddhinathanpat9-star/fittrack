<?php
// admin/profile.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure user is admin
if (!Session::isAdmin()) {
    Session::setFlash('danger', 'Access denied.');
    header('Location: ../login.php');
    exit();
}

$functions = new Functions();
$admin_id = Session::userId();

// Fetch current admin data
$admin = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();
} catch (Exception $e) {
    Session::setFlash('danger', 'Error loading profile: ' . $e->getMessage());
    header('Location: dashboard.php');
    exit();
}

if (!$admin) {
    Session::setFlash('danger', 'User not found.');
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    $errors = [];
    if (empty($full_name)) $errors[] = 'Full name is required.';
    if (empty($email)) $errors[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';

    // Check email uniqueness (excluding current user)
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $admin_id]);
        if ($stmt->fetch()) $errors[] = 'Email already in use by another account.';
    }

    // Password change validation
    if (!empty($new_password)) {
        if (strlen($new_password) < 6) $errors[] = 'New password must be at least 6 characters.';
        if ($new_password !== $confirm_password) $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Update user table
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $phone, $address, $admin_id]);

            // Update password if provided
            if (!empty($new_password)) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed, $admin_id]);
            }

            $pdo->commit();
            $success = 'Profile updated successfully.';

            // Refresh data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$admin_id]);
            $admin = $stmt->fetch();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error updating profile: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

$page_title = 'My Profile - ' . APP_NAME;
include __DIR__ . '/includes/header_clean.php';
?>

<!-- Main content -->
<main class="col-md-10 ms-sm-auto px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-user-circle me-2"></i>My Profile</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <!-- optional buttons -->
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h5>
        </div>
        <div class="card-body">
            <form method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username"
                               value="<?php echo htmlspecialchars($admin['username']); ?>" readonly disabled>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="full_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name"
                               value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="phone" name="phone"
                               value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>">
                    </div>
                    <div class="col-12 mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($admin['address'] ?? ''); ?></textarea>
                    </div>
                </div>

                <hr>
                <h5 class="text-warning"><i class="fas fa-lock me-2"></i>Change Password (leave blank to keep current)</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password">
                        <div class="progress mt-2" style="height: 5px;">
                            <div class="progress-bar" id="passwordStrength" style="width: 0%;"></div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                    </div>
                </div>

                <button type="submit" name="update_profile" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Update Profile
                </button>
            </form>
        </div>
    </div>
</main>

<script>
// Password strength meter
document.getElementById('new_password')?.addEventListener('input', function() {
    let password = this.value;
    let strength = 0;
    if (password.length >= 6) strength += 20;
    if (password.match(/[a-z]+/)) strength += 20;
    if (password.match(/[A-Z]+/)) strength += 20;
    if (password.match(/[0-9]+/)) strength += 20;
    if (password.match(/[$@#&!]+/)) strength += 20;
    let bar = document.getElementById('passwordStrength');
    bar.style.width = strength + '%';
    if (strength <= 20) bar.className = 'progress-bar bg-danger';
    else if (strength <= 40) bar.className = 'progress-bar bg-warning';
    else if (strength <= 60) bar.className = 'progress-bar bg-info';
    else bar.className = 'progress-bar bg-success';
});

// Password match validation
document.getElementById('confirm_password')?.addEventListener('input', function() {
    let pass = document.getElementById('new_password').value;
    if (this.value !== pass) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>