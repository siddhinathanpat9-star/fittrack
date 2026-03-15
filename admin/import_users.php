<?php
// admin/import_users.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

// Fix the path - includes folder is outside admin folder
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Now use the Session class from the root includes
Session::requireAdmin();

// Initialize functions
$functions = new Functions();
$error = '';
$success = '';
$import_results = [];

// Maximum file size (5MB)
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// Allowed file types
define('ALLOWED_EXTENSIONS', ['csv', 'txt']);

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['import_file'])) {
    $file = $_FILES['import_file'];
    $user_type = $_POST['user_type'] ?? 'member';
    $default_status = $_POST['default_status'] ?? 'active';
    $send_notifications = isset($_POST['send_notifications']);
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = getUploadErrorMessage($file['error']);
    } elseif ($file['size'] > MAX_FILE_SIZE) {
        $error = "File is too large. Maximum size is " . (MAX_FILE_SIZE / 1024 / 1024) . "MB";
    } else {
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ALLOWED_EXTENSIONS)) {
            $error = "Invalid file type. Only CSV files are allowed.";
        } else {
            // Process the CSV file
            $import_results = processImportFile($file['tmp_name'], $user_type, $default_status, $send_notifications, $pdo, $functions);
            
            if ($import_results['success_count'] > 0) {
                $success = "Successfully imported " . $import_results['success_count'] . " users.";
                if ($import_results['error_count'] > 0) {
                    $success .= " " . $import_results['error_count'] . " rows failed.";
                }
            } else {
                $error = "No users were imported. Please check your CSV file format.";
            }
        }
    }
}

/**
 * Get upload error message
 */
function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "File is too large.";
        case UPLOAD_ERR_PARTIAL:
            return "File was only partially uploaded.";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded.";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing temporary folder.";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk.";
        case UPLOAD_ERR_EXTENSION:
            return "File upload stopped by extension.";
        default:
            return "Unknown upload error.";
    }
}

/**
 * Process the import file
 */
function processImportFile($file_path, $user_type, $default_status, $send_notifications, $pdo, $functions) {
    $results = [
        'success_count' => 0,
        'error_count' => 0,
        'errors' => [],
        'successful' => []
    ];
    
    // Open the file
    if (($handle = fopen($file_path, "r")) !== false) {
        $row_number = 0;
        
        // Get headers if first row is headers
        $headers = fgetcsv($handle);
        $row_number++;
        
        // Define expected headers based on user type
        $expected_headers = getExpectedHeaders($user_type);
        
        // Validate headers
        $header_map = validateHeaders($headers, $expected_headers);
        
        if (empty($header_map)) {
            $results['errors'][] = "Invalid CSV format. Expected headers: " . implode(', ', $expected_headers);
            return $results;
        }
        
        // Process each row
        while (($data = fgetcsv($handle)) !== false) {
            $row_number++;
            
            try {
                // Map data to fields
                $user_data = mapDataToFields($data, $header_map, $user_type, $default_status);
                
                // Validate required fields
                $validation_errors = validateUserData($user_data, $user_type, $pdo);
                
                if (!empty($validation_errors)) {
                    throw new Exception("Validation failed: " . implode(', ', $validation_errors));
                }
                
                // Begin transaction
                $pdo->beginTransaction();
                
                // Hash password
                $hashed_password = password_hash($user_data['password'], PASSWORD_DEFAULT);
                
                // Insert user
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, full_name, phone, address, user_type, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $user_data['username'],
                    $user_data['email'],
                    $hashed_password,
                    $user_data['full_name'],
                    $user_data['phone'],
                    $user_data['address'],
                    $user_type,
                    $user_data['status']
                ]);
                
                $user_id = $pdo->lastInsertId();
                
                // Create role-specific records
                if ($user_type === 'member') {
                    createMemberRecord($pdo, $user_id, $user_data);
                } elseif ($user_type === 'trainer') {
                    createTrainerRecord($pdo, $user_id, $user_data);
                }
                
                $pdo->commit();
                
                // Send notification if enabled
                if ($send_notifications) {
                    sendWelcomeNotification($user_data, $user_type, $pdo);
                }
                
                $results['success_count']++;
                $results['successful'][] = [
                    'row' => $row_number,
                    'username' => $user_data['username'],
                    'email' => $user_data['email'],
                    'full_name' => $user_data['full_name']
                ];
                
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                
                $results['error_count']++;
                $results['errors'][] = "Row $row_number: " . $e->getMessage();
            }
        }
        
        fclose($handle);
    } else {
        $results['errors'][] = "Could not open file.";
    }
    
    return $results;
}

/**
 * Get expected headers based on user type
 */
function getExpectedHeaders($user_type) {
    $base_headers = [
        'username',
        'email',
        'password',
        'full_name',
        'phone',
        'address'
    ];
    
    if ($user_type === 'member') {
        return array_merge($base_headers, [
            'membership_type',
            'membership_end',
            'assigned_trainer_id',
            'height',
            'weight',
            'fitness_goals',
            'emergency_contact',
            'emergency_phone'
        ]);
    } elseif ($user_type === 'trainer') {
        return array_merge($base_headers, [
            'specialization',
            'experience_years',
            'hourly_rate',
            'qualification',
            'availability'
        ]);
    }
    
    return $base_headers;
}

/**
 * Validate headers and create mapping
 */
function validateHeaders($headers, $expected_headers) {
    $header_map = [];
    $headers = array_map('strtolower', array_map('trim', $headers));
    
    foreach ($expected_headers as $expected) {
        $expected_lower = strtolower($expected);
        $index = array_search($expected_lower, $headers);
        
        if ($index !== false) {
            $header_map[$expected] = $index;
        } elseif (in_array($expected, ['username', 'email', 'password', 'full_name'])) {
            // Required fields must exist
            return [];
        }
    }
    
    return $header_map;
}

/**
 * Map CSV data to fields
 */
function mapDataToFields($data, $header_map, $user_type, $default_status) {
    $user_data = [
        'status' => $default_status
    ];
    
    foreach ($header_map as $field => $index) {
        if (isset($data[$index])) {
            $user_data[$field] = trim($data[$index]);
        } else {
            $user_data[$field] = '';
        }
    }
    
    // Set defaults for missing fields
    if ($user_type === 'member') {
        $user_data['membership_type'] = $user_data['membership_type'] ?? 'basic';
        $user_data['membership_end'] = $user_data['membership_end'] ?? date('Y-m-d', strtotime('+1 month'));
        $user_data['height'] = $user_data['height'] ?? null;
        $user_data['weight'] = $user_data['weight'] ?? null;
    } elseif ($user_type === 'trainer') {
        $user_data['experience_years'] = (int)($user_data['experience_years'] ?? 0);
        $user_data['hourly_rate'] = (float)($user_data['hourly_rate'] ?? 0);
    }
    
    return $user_data;
}

/**
 * Validate user data
 */
function validateUserData($user_data, $user_type, $pdo) {
    $errors = [];
    
    // Required fields
    if (empty($user_data['username'])) {
        $errors[] = "Username is required";
    } elseif (strlen($user_data['username']) < 3) {
        $errors[] = "Username must be at least 3 characters";
    }
    
    if (empty($user_data['email'])) {
        $errors[] = "Email is required";
    } elseif (!filter_var($user_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($user_data['password'])) {
        $errors[] = "Password is required";
    } elseif (strlen($user_data['password']) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if (empty($user_data['full_name'])) {
        $errors[] = "Full name is required";
    }
    
    // Check for existing username/email
    if (!empty($user_data['username']) && !empty($user_data['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$user_data['username'], $user_data['email']]);
        
        if ($stmt->rowCount() > 0) {
            $existing = $stmt->fetch();
            $errors[] = "Username or email already exists";
        }
    }
    
    // Member-specific validation
    if ($user_type === 'member') {
        if (!empty($user_data['membership_end']) && !strtotime($user_data['membership_end'])) {
            $errors[] = "Invalid membership end date format";
        }
        
        if (!empty($user_data['height']) && !is_numeric($user_data['height'])) {
            $errors[] = "Height must be a number";
        }
        
        if (!empty($user_data['weight']) && !is_numeric($user_data['weight'])) {
            $errors[] = "Weight must be a number";
        }
    }
    
    // Trainer-specific validation
    if ($user_type === 'trainer') {
        if (!empty($user_data['experience_years']) && !is_numeric($user_data['experience_years'])) {
            $errors[] = "Experience years must be a number";
        }
        
        if (!empty($user_data['hourly_rate']) && !is_numeric($user_data['hourly_rate'])) {
            $errors[] = "Hourly rate must be a number";
        }
    }
    
    return $errors;
}

/**
 * Create member record
 */
function createMemberRecord($pdo, $user_id, $user_data) {
    $stmt = $pdo->prepare("
        INSERT INTO members (
            user_id, 
            membership_type, 
            membership_start, 
            membership_end, 
            assigned_trainer_id,
            height, 
            weight, 
            fitness_goals, 
            emergency_contact, 
            emergency_phone
        ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $user_id,
        $user_data['membership_type'] ?? 'basic',
        $user_data['membership_end'] ?? date('Y-m-d', strtotime('+1 month')),
        !empty($user_data['assigned_trainer_id']) ? $user_data['assigned_trainer_id'] : null,
        !empty($user_data['height']) ? $user_data['height'] : null,
        !empty($user_data['weight']) ? $user_data['weight'] : null,
        $user_data['fitness_goals'] ?? null,
        $user_data['emergency_contact'] ?? null,
        $user_data['emergency_phone'] ?? null
    ]);
}

/**
 * Create trainer record
 */
function createTrainerRecord($pdo, $user_id, $user_data) {
    $stmt = $pdo->prepare("
        INSERT INTO trainers (
            user_id, 
            specialization, 
            experience_years, 
            hourly_rate, 
            qualification, 
            availability
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $user_id,
        $user_data['specialization'] ?? null,
        $user_data['experience_years'] ?? 0,
        $user_data['hourly_rate'] ?? 0,
        $user_data['qualification'] ?? null,
        $user_data['availability'] ?? null
    ]);
}

/**
 * Send welcome notification
 */
function sendWelcomeNotification($user_data, $user_type, $pdo) {
    try {
        // Check if notifications table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'notifications'");
        if ($stmt->rowCount() == 0) {
            return;
        }
        
        $message = "Welcome to " . APP_NAME . "! Your account has been created successfully.";
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, created_at) 
            VALUES (?, ?, ?, 'welcome', NOW())
        ");
        
        // We don't have user_id yet, so we'll store for admin to see
        $stmt->execute([0, "New User Created", "User: " . $user_data['full_name'] . " (" . $user_type . ")"]);
        
    } catch (Exception $e) {
        // Silently fail - notifications are optional
    }
}

/**
 * Download sample CSV
 */
if (isset($_GET['download_sample'])) {
    $user_type = $_GET['user_type'] ?? 'member';
    
    // Set headers for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sample_' . $user_type . '_import.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add headers
    $headers = getExpectedHeaders($user_type);
    fputcsv($output, $headers);
    
    // Add sample data
    $sample_data = getSampleData($user_type);
    fputcsv($output, $sample_data);
    
    // Add second sample
    $sample_data2 = getSampleData2($user_type);
    fputcsv($output, $sample_data2);
    
    fclose($output);
    exit();
}

/**
 * Get sample data based on user type
 */
function getSampleData($user_type) {
    if ($user_type === 'member') {
        return [
            'john_doe',
            'john@example.com',
            'password123',
            'John Doe',
            '555-123-4567',
            '123 Main St, City',
            'premium',
            date('Y-m-d', strtotime('+1 month')),
            '',
            '175',
            '70',
            'Lose weight, build muscle',
            'Jane Doe',
            '555-987-6543'
        ];
    } elseif ($user_type === 'trainer') {
        return [
            'jane_trainer',
            'jane@example.com',
            'password123',
            'Jane Smith',
            '555-222-3333',
            '456 Oak Ave, City',
            'Yoga, Pilates',
            '5',
            '45.00',
            'Certified Yoga Instructor',
            'Weekdays 9am-5pm'
        ];
    } else {
        return [
            'admin_user',
            'admin@example.com',
            'password123',
            'Admin User',
            '555-444-5555',
            '789 Pine St, City'
        ];
    }
}

/**
 * Get second sample data
 */
function getSampleData2($user_type) {
    if ($user_type === 'member') {
        return [
            'jane_member',
            'jane.member@example.com',
            'password123',
            'Jane Member',
            '555-777-8888',
            '321 Elm St, City',
            'basic',
            date('Y-m-d', strtotime('+2 months')),
            '1',
            '165',
            '65',
            'Maintain fitness',
            'Bob Member',
            '555-999-0000'
        ];
    } elseif ($user_type === 'trainer') {
        return [
            'mike_trainer',
            'mike@example.com',
            'password123',
            'Mike Johnson',
            '555-666-7777',
            '654 Maple Dr, City',
            'Weight Training, CrossFit',
            '8',
            '60.00',
            'CrossFit Level 2 Trainer',
            'Weekends only'
        ];
    } else {
        return [
            'admin2',
            'admin2@example.com',
            'password123',
            'Admin Two',
            '555-888-9999',
            '987 Cedar Ln, City'
        ];
    }
}

$user_name = Session::userName();
$page_title = 'Import Users - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 4, Font Awesome, DataTables (optional) -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <style>
        /* Dashboard styles from reference */
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#f8f9fa;overflow-x:hidden}.wrapper{display:flex;width:100%;align-items:stretch;min-height:100vh}#sidebar{min-width:280px;max-width:280px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;transition:.3s;box-shadow:2px 0 10px rgba(0,0,0,0.1);position:relative;z-index:1000}#sidebar.active{margin-left:-280px}#sidebar .sidebar-header{padding:30px 20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1)}#sidebar .sidebar-header h3{font-size:1.8rem;font-weight:600}#sidebar ul.components{padding:20px 0}#sidebar ul li a{padding:15px 25px;font-size:1rem;display:block;color:#fff;text-decoration:none;transition:.3s;border-left:3px solid transparent}#sidebar ul li a:hover{background:rgba(255,255,255,0.1);border-left-color:#fff}#sidebar ul li.active>a{background:rgba(255,255,255,0.15);border-left-color:#fff;font-weight:600}#sidebar ul li a i{margin-right:10px;width:25px;text-align:center}#sidebar ul ul a{padding-left:50px!important;font-size:.9rem!important}#sidebar .sidebar-footer{padding:20px;position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1)}#content{width:100%;padding:30px;min-height:100vh;transition:.3s;background:#f8f9fa}.navbar-custom{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.05);border-radius:10px;margin-bottom:30px;padding:15px 25px}.page-header{padding-bottom:15px;margin:0 0 30px;border-bottom:3px solid #667eea}.page-header h1{font-size:2rem;font-weight:600;color:#333;margin:0}.page-header h1 i{color:#667eea;margin-right:10px}.page-header small{font-size:1rem;color:#6c757d;margin-left:10px}.stats-card{border:none;border-radius:15px;margin-bottom:25px;transition:.3s;overflow:hidden;position:relative}.stats-card:hover{transform:translateY(-5px);box-shadow:0 10px 30px rgba(0,0,0,0.15)}.stats-card .card-body{padding:25px}.stats-card .card-title{font-size:.9rem;text-transform:uppercase;letter-spacing:1px;opacity:.9;margin-bottom:10px}.stats-card h2{font-size:2.2rem;font-weight:700;margin:0 0 5px}.stats-card i{font-size:3rem;opacity:.3;position:absolute;bottom:15px;right:15px}.card{border:none;border-radius:15px;box-shadow:0 5px 20px rgba(0,0,0,0.05);margin-bottom:30px}.card-header{background:#fff;border-bottom:2px solid #f0f0f0;padding:20px 25px;border-radius:15px 15px 0 0!important}.card-header h5{margin:0;font-weight:600;color:#333}.card-header h5 i{color:#667eea;margin-right:10px}.card-body{padding:25px}.table{margin:0}.table thead th{border-top:none;border-bottom:2px solid #667eea;color:#555;font-weight:600;text-transform:uppercase;font-size:.8rem;letter-spacing:.5px;padding:15px 10px}.table tbody td{padding:15px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0;color:#666}.table tbody tr:hover{background:#f8f9fa}.badge{padding:6px 10px;border-radius:20px;font-weight:500;font-size:.75rem}.badge-success{background:#d4edda;color:#155724}.badge-warning{background:#fff3cd;color:#856404}.badge-danger{background:#f8d7da;color:#721c24}.badge-info{background:#d1ecf1;color:#0c5460}.loading-spinner{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999}.loading-spinner.active{display:block}.spinner-border{width:3rem;height:3rem;color:#667eea}.alert{border:none;border-radius:10px;padding:15px 20px;margin-bottom:30px}@media(max-width:768px){#sidebar{margin-left:-280px}#sidebar.active{margin-left:0}#content{padding:20px}}
        /* Additional styles for pre blocks and tabs */
        pre{background:#f8f9fa;padding:15px;border-radius:5px;font-size:0.85rem;overflow-x:auto;white-space:pre-wrap;word-wrap:break-word}
        .nav-tabs .nav-link.active{background-color:#667eea;color:#fff;border-color:#667eea}
        .nav-tabs .nav-link{color:#667eea;font-weight:500}
        .nav-tabs .nav-link:hover{background-color:#f0f0f0}
    </style>
</head>
<body>
    <!-- Loading Spinner (optional) -->
    <div class="loading-spinner" id="loadingSpinner"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>

    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-dumbbell fa-3x mb-3"></i>
                <h3><?php echo APP_NAME; ?></h3>
                <p>Administrator Panel</p>
            </div>
            <ul class="list-unstyled components">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li>
                    <a href="#membersSubmenu" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-users"></i> Members <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="membersSubmenu">
                        <li><a href="members/manage_members.php"><i class="fas fa-list"></i> All Members</a></li>
                        <li><a href="members/add_member.php"><i class="fas fa-user-plus"></i> Add Member</a></li>
                        <li><a href="membership/membership_plans.php"><i class="fas fa-tag"></i> Membership Plans</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#trainersSubmenu" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-user-tie"></i> Trainers <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="trainersSubmenu">
                        <li><a href="manage_trainers.php"><i class="fas fa-list"></i> All Trainers</a></li>
                        <li><a href="trainers/add_trainer.php"><i class="fas fa-user-plus"></i> Add Trainer</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#classesSubmenu" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-calendar-alt"></i> Classes <i class="fas fa-chevron-down float-right"></i>
                    </a>
                    <ul class="collapse list-unstyled" id="classesSubmenu">
                        <li><a href="classes/manage_classes.php"><i class="fas fa-list"></i> All Classes</a></li>
                        <li><a href="classes/add_class.php"><i class="fas fa-plus-circle"></i> Add Class</a></li>
                        <li><a href="classes/class_schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
                    </ul>
                </li>
                <li><a href="payments/manage_payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
                <li><a href="attendance.php"><i class="fas fa-clock"></i> Attendance</a></li>
                <li><a href="equipment.php"><i class="fas fa-dumbbell"></i> Equipment</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
            <div class="sidebar-footer">
                <a href="#" onclick="confirmLogout(event)" class="btn btn-danger btn-block"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg navbar-custom">
                <button type="button" id="sidebarCollapse" class="btn btn-outline-primary">
                    <i class="fas fa-bars"></i> Menu
                </button>
                <div class="ml-auto">
                    <!-- Notifications dropdown (optional) -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown">
                            <i class="fas fa-bell"></i><span class="badge badge-danger badge-pill">3</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" style="width:300px">
                            <div class="dropdown-header bg-light">Notifications</div>
                            <a class="dropdown-item" href="#"><strong>New member registered</strong><br><small class="text-muted">2 minutes ago</small></a>
                            <a class="dropdown-item" href="#"><strong>Payment received</strong><br><small class="text-muted">1 hour ago</small></a>
                            <a class="dropdown-item" href="#"><strong>5 memberships expiring soon</strong><br><small class="text-muted">3 hours ago</small></a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-center" href="#">View all</a>
                        </div>
                    </div>
                    <div class="dropdown ml-3 d-inline-block">
                        <button class="btn btn-light dropdown-toggle" data-toggle="dropdown">
                            <i class="fas fa-user-circle fa-lg"></i>
                            <span class="ml-2"><?php echo htmlspecialchars($user_name); ?></span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                            <a class="dropdown-item" href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid">
                <!-- Flash messages from session -->
                <?php Session::displayFlash(); ?>

                <!-- Error alert from form processing -->
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="page-header d-flex justify-content-between align-items-center">
                    <h1>
                        <i class="fas fa-file-import"></i> Import Users
                        <small>Bulk import from CSV</small>
                    </h1>
                    <a href="manage_users.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Users
                    </a>
                </div>

                <!-- Import Results (if any) -->
                <?php if (!empty($import_results)): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-<?php echo $import_results['error_count'] > 0 ? 'warning' : 'success'; ?> text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-bar mr-2"></i>Import Results
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="card stats-card bg-success text-white">
                                        <div class="card-body text-center">
                                            <h3><?php echo $import_results['success_count']; ?></h3>
                                            <p class="mb-0">Successful</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card stats-card bg-danger text-white">
                                        <div class="card-body text-center">
                                            <h3><?php echo $import_results['error_count']; ?></h3>
                                            <p class="mb-0">Failed</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card stats-card bg-info text-white">
                                        <div class="card-body text-center">
                                            <h3><?php echo $import_results['success_count'] + $import_results['error_count']; ?></h3>
                                            <p class="mb-0">Total Rows</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($import_results['successful'])): ?>
                                <h6>Successfully Imported Users:</h6>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Row</th>
                                                <th>Username</th>
                                                <th>Name</th>
                                                <th>Email</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($import_results['successful'] as $item): ?>
                                            <tr>
                                                <td><?php echo $item['row']; ?></td>
                                                <td><?php echo htmlspecialchars($item['username']); ?></td>
                                                <td><?php echo htmlspecialchars($item['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['email']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($import_results['errors'])): ?>
                                <h6 class="text-danger">Errors:</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Error</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($import_results['errors'] as $error_msg): ?>
                                            <tr>
                                                <td class="text-danger"><?php echo htmlspecialchars($error_msg); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Import Form Card -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-upload mr-2"></i>Upload CSV File</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Instructions:</strong> Upload a CSV file with the required columns.
                            <a href="#sample-format" data-toggle="collapse">View sample format</a>
                        </div>

                        <form method="POST" enctype="multipart/form-data" class="form-row">
                            <div class="form-group col-md-4">
                                <label for="user_type" class="form-label">User Type *</label>
                                <select class="form-control" id="user_type" name="user_type" required>
                                    <option value="">Select Type</option>
                                    <option value="member" <?php echo isset($_POST['user_type']) && $_POST['user_type'] == 'member' ? 'selected' : ''; ?>>Member</option>
                                    <option value="trainer" <?php echo isset($_POST['user_type']) && $_POST['user_type'] == 'trainer' ? 'selected' : ''; ?>>Trainer</option>
                                    <option value="admin" <?php echo isset($_POST['user_type']) && $_POST['user_type'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="default_status" class="form-label">Default Status</label>
                                <select class="form-control" id="default_status" name="default_status">
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="import_file" class="form-label">CSV File *</label>
                                <input type="file" class="form-control-file" id="import_file" name="import_file"
                                       accept=".csv,.txt" required>
                                <small class="form-text text-muted">Maximum file size: 5MB</small>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="send_notifications" name="send_notifications">
                                    <label class="form-check-label" for="send_notifications">
                                        Send welcome notifications to imported users
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload mr-2"></i>Import Users
                                </button>
                                <button type="reset" class="btn btn-secondary">
                                    <i class="fas fa-undo mr-2"></i>Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sample Format Card (collapsible) -->
                <div class="card mt-4 collapse" id="sample-format">
                    <div class="card-header">
                        <h5><i class="fas fa-table mr-2"></i>Sample CSV Format</h5>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="sampleTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="member-tab" data-toggle="tab" href="#member" role="tab">Member</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="trainer-tab" data-toggle="tab" href="#trainer" role="tab">Trainer</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="admin-tab" data-toggle="tab" href="#admin" role="tab">Admin</a>
                            </li>
                        </ul>

                        <div class="tab-content mt-3">
                            <div class="tab-pane fade show active" id="member" role="tabpanel">
                                <h6>Member CSV Headers:</h6>
                                <pre>username,email,password,full_name,phone,address,membership_type,membership_end,assigned_trainer_id,height,weight,fitness_goals,emergency_contact,emergency_phone</pre>

                                <h6 class="mt-3">Sample Data:</h6>
                                <pre>john_doe,john@example.com,password123,John Doe,555-123-4567,"123 Main St, City",premium,<?php echo date('Y-m-d', strtotime('+1 month')); ?>,1,175,70,"Lose weight, build muscle",Jane Doe,555-987-6543</pre>

                                <div class="mt-3">
                                    <a href="?download_sample&user_type=member" class="btn btn-sm btn-success">
                                        <i class="fas fa-download mr-2"></i>Download Sample CSV
                                    </a>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="trainer" role="tabpanel">
                                <h6>Trainer CSV Headers:</h6>
                                <pre>username,email,password,full_name,phone,address,specialization,experience_years,hourly_rate,qualification,availability</pre>

                                <h6 class="mt-3">Sample Data:</h6>
                                <pre>jane_trainer,jane@example.com,password123,Jane Smith,555-222-3333,"456 Oak Ave, City","Yoga, Pilates",5,45.00,"Certified Yoga Instructor","Weekdays 9am-5pm"</pre>

                                <div class="mt-3">
                                    <a href="?download_sample&user_type=trainer" class="btn btn-sm btn-success">
                                        <i class="fas fa-download mr-2"></i>Download Sample CSV
                                    </a>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="admin" role="tabpanel">
                                <h6>Admin CSV Headers:</h6>
                                <pre>username,email,password,full_name,phone,address</pre>

                                <h6 class="mt-3">Sample Data:</h6>
                                <pre>admin_user,admin@example.com,password123,Admin User,555-444-5555,"789 Pine St, City"</pre>

                                <div class="mt-3">
                                    <a href="?download_sample&user_type=admin" class="btn btn-sm btn-success">
                                        <i class="fas fa-download mr-2"></i>Download Sample CSV
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>Note:</strong> The first row must contain the headers. Password must be at least 6 characters.
                            All dates should be in YYYY-MM-DD format.
                        </div>
                    </div>
                </div>

                <!-- Tips Card -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-lightbulb mr-2"></i>Import Tips</h5>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li>Make sure your CSV file uses commas as separators</li>
                            <li>The first row must contain the column headers</li>
                            <li>Required fields: username, email, password, full_name</li>
                            <li>Username and email must be unique in the system</li>
                            <li>Passwords will be securely hashed before storage</li>
                            <li>For members, membership_end defaults to 1 month from now if not specified</li>
                            <li>You can leave optional fields empty</li>
                            <li>Maximum 1000 rows per import for best performance</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-sign-out-alt"></i> Confirm Logout</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to logout?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times"></i> Cancel</button>
                    <a href="../logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // Sidebar toggle
            $('#sidebarCollapse').on('click', function() {
                $('#sidebar').toggleClass('active');
            });

            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);

            // File size validation
            $('#import_file').on('change', function() {
                var fileSize = this.files[0].size;
                var maxSize = <?php echo MAX_FILE_SIZE; ?>;

                if (fileSize > maxSize) {
                    alert('File is too large. Maximum size is ' + (maxSize / 1024 / 1024) + 'MB');
                    $(this).val('');
                }
            });
        });

        function confirmLogout(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        }
    </script>
</body>
</html>