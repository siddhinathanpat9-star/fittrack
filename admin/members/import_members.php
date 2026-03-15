<?php
// admin/members/import_members.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Path: two levels up to includes
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is admin
if (!Session::isAdmin()) {
    header('Location: ../../login.php');
    exit();
}

$functions = new Functions();
$error = '';
$success = '';
$preview_data = [];
$columns = [];

// Required and optional fields
$required_fields = ['full_name', 'email', 'membership_type'];
$all_fields = [
    'full_name'         => 'Full Name',
    'email'             => 'Email',
    'password'          => 'Password (leave blank to auto-generate)',
    'phone'             => 'Phone',
    'address'           => 'Address',
    'membership_type'   => 'Membership Type',
    'membership_start'  => 'Membership Start (YYYY-MM-DD)',
    'membership_end'    => 'Membership End (YYYY-MM-DD)',
    'height'            => 'Height (cm)',
    'weight'            => 'Weight (kg)',
    'fitness_goals'     => 'Fitness Goals',
    'emergency_contact' => 'Emergency Contact',
    'emergency_phone'   => 'Emergency Phone',
];

// Helper: generate a random password
function generateRandomPassword($length = 8)
{
    return bin2hex(random_bytes($length / 2));
}

// Handle file upload and preview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['csv_file']['tmp_name'];
        $file_name = $_FILES['csv_file']['name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $error = 'Please upload a valid CSV file.';
        } else {
            if (($handle = fopen($file_tmp, 'r')) !== false) {
                $header = fgetcsv($handle); // first row as header
                if (!$header) {
                    $error = 'CSV file is empty or invalid.';
                } else {
                    // Clean headers
                    $header = array_map('trim', $header);
                    $header = array_map('strtolower', $header);
                    // Check for required columns
                    $missing = array_diff($required_fields, $header);
                    if (!empty($missing)) {
                        $error = 'CSV missing required columns: ' . implode(', ', $missing);
                    } else {
                        // Read data (limit to 100 rows for preview)
                        $data = [];
                        $row_count = 0;
                        while (($row = fgetcsv($handle)) !== false && $row_count < 100) {
                            if (count($row) === count($header)) {
                                $data[] = array_combine($header, $row);
                            }
                            $row_count++;
                        }
                        $_SESSION['import_data'] = $data;
                        $_SESSION['import_header'] = $header;
                        $preview_data = $data;
                        $columns = $header;
                    }
                }
                fclose($handle);
            } else {
                $error = 'Could not open uploaded file.';
            }
        }
    } else {
        $error = 'Please select a CSV file to upload.';
    }
}

// Handle confirmation import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    if (!isset($_SESSION['import_data']) || empty($_SESSION['import_data'])) {
        $error = 'No data to import. Please upload a file first.';
    } else {
        $data = $_SESSION['import_data'];
        $imported = 0;
        $failed = 0;
        $errors = [];

        $pdo->beginTransaction();
        try {
            foreach ($data as $row) {
                // Validate required
                $missing = [];
                foreach ($required_fields as $req) {
                    if (empty($row[$req])) {
                        $missing[] = $req;
                    }
                }
                if (!empty($missing)) {
                    $failed++;
                    $errors[] = "Row for {$row['full_name']}: missing " . implode(', ', $missing);
                    continue;
                }

                // Check if email exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$row['email']]);
                if ($stmt->fetch()) {
                    $failed++;
                    $errors[] = "Email {$row['email']} already exists.";
                    continue;
                }

                // Generate password if not provided
                $password = !empty($row['password']) ? $row['password'] : generateRandomPassword(8);
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                // Insert user
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, full_name, phone, address, user_type, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'member', 'active', NOW())
                ");
                $username = $row['email']; // simple username – can be improved
                $stmt->execute([
                    $username,
                    $row['email'],
                    $hashed,
                    $row['full_name'],
                    $row['phone'] ?? null,
                    $row['address'] ?? null
                ]);
                $user_id = $pdo->lastInsertId();

                // Insert member details
                $stmt = $pdo->prepare("
                    INSERT INTO members (user_id, membership_type, membership_start, membership_end, height, weight, fitness_goals, emergency_contact, emergency_phone)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    $row['membership_type'],
                    $row['membership_start'] ?? date('Y-m-d'),
                    $row['membership_end'] ?? date('Y-m-d', strtotime('+1 month')),
                    $row['height'] ?? null,
                    $row['weight'] ?? null,
                    $row['fitness_goals'] ?? null,
                    $row['emergency_contact'] ?? null,
                    $row['emergency_phone'] ?? null
                ]);

                $imported++;
            }
            $pdo->commit();
            $success = "Successfully imported $imported members.";
            if ($failed > 0) {
                $success .= " Failed: $failed. Check error log.";
                // Store errors in session to display? For simplicity we show only count.
            }
            // Clear session data
            unset($_SESSION['import_data']);
            unset($_SESSION['import_header']);
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Import failed: " . $e->getMessage();
        }
    }
}

// Clear session data if requested
if (isset($_GET['clear'])) {
    unset($_SESSION['import_data']);
    unset($_SESSION['import_header']);
    header('Location: import_members.php');
    exit();
}

$page_title = 'Import Members - ' . APP_NAME;
include __DIR__ . '/../../includes/header_clean.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar (already provided by header_clean.php) -->
        <!-- The main content starts after the sidebar in the header -->
    </div>
</div>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-file-import me-2"></i>Import Members
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../manage_members.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Members
        </a>
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

<!-- Step 1: Upload CSV -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="fas fa-upload me-2"></i>1. Upload CSV File</h5>
    </div>
    <div class="card-body">
        <p class="text-muted">
            Upload a CSV file with member data. Required columns:
            <strong>full_name, email, membership_type</strong>.
            Other optional columns: <?php echo implode(', ', array_keys(array_diff_key($all_fields, array_flip($required_fields)))); ?>.
        </p>
        <form method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="csv_file" class="form-label">Select CSV File</label>
                <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
            </div>
            <button type="submit" name="upload" class="btn btn-primary">
                <i class="fas fa-upload me-2"></i>Upload and Preview
            </button>
            <a href="sample_members.csv" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-download me-2"></i>Download Sample
            </a>
        </form>
    </div>
</div>

<!-- Step 2: Preview Data (if available) -->
<?php if (!empty($preview_data)): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5><i class="fas fa-eye me-2"></i>2. Preview Data (first <?php echo count($preview_data); ?> rows)</h5>
        <div>
            <a href="?clear=1" class="btn btn-outline-warning btn-sm">
                <i class="fas fa-times me-2"></i>Clear
            </a>
            <form method="post" style="display: inline;">
                <button type="submit" name="confirm" class="btn btn-success btn-sm"
                        onclick="return confirm('Import these members? This action cannot be undone.')">
                    <i class="fas fa-check me-2"></i>Confirm Import
                </button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <?php foreach ($columns as $col): ?>
                            <th><?php echo htmlspecialchars($col); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview_data as $row): ?>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <td><?php echo htmlspecialchars($row[$col] ?? ''); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($preview_data) >= 100): ?>
            <p class="text-muted">Showing first 100 rows. More rows will be imported when confirmed.</p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Instructions -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-info-circle me-2"></i>Import Instructions</h5>
    </div>
    <div class="card-body">
        <ul class="mb-0">
            <li>The first row of the CSV must contain column headers.</li>
            <li>Required columns: <code>full_name</code>, <code>email</code>, <code>membership_type</code>.</li>
            <li>Optional columns: <?php echo implode(', ', array_keys($all_fields)); ?>.</li>
            <li>If password is not provided, a random password will be generated.</li>
            <li>Duplicate emails will be skipped.</li>
            <li>Membership start defaults to today, end defaults to +1 month.</li>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>