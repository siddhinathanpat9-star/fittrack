<?php
// includes/session.php

require_once __DIR__ . '/config.php';

class Session {
    
    /**
     * Start session if not already started
     */
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Set a session value
     */
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Get a session value
     */
    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
    }
    
    /**
     * Check if user is trainer
     */
    public static function isTrainer() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'trainer';
    }
    
    /**
     * Check if user is member
     */
    public static function isMember() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'member';
    }
    
    /**
     * Require admin authentication - redirects to login if not admin
     */
    public static function requireAdmin() {
        self::init();
        if (!self::isAdmin()) {
            self::setFlash('danger', 'Access denied. Admin login required.');
            header('Location: ' . self::getBaseUrl() . '/login.php');
            exit();
        }
    }
    
    /**
     * Require trainer authentication - redirects to login if not trainer
     */
    public static function requireTrainer() {
        self::init();
        if (!self::isTrainer() && !self::isAdmin()) {
            self::setFlash('danger', 'Access denied. Trainer login required.');
            header('Location: ' . self::getBaseUrl() . '/login.php');
            exit();
        }
    }
    
    /**
     * Require member authentication - redirects to login if not member
     */
    public static function requireMember() {
        self::init();
        if (!self::isMember() && !self::isAdmin()) {
            self::setFlash('danger', 'Access denied. Member login required.');
            header('Location: ' . self::getBaseUrl() . '/login.php');
            exit();
        }
    }
    
    /**
     * Require any authenticated user
     */
    public static function requireLogin() {
        self::init();
        if (!self::isLoggedIn()) {
            self::setFlash('warning', 'Please login to continue.');
            header('Location: ' . self::getBaseUrl() . '/login.php');
            exit();
        }
    }
    
    /**
     * Get current user's name
     */
    public static function userName() {
        return $_SESSION['full_name'] ?? 'Guest';
    }
    
    /**
     * Get current user's ID
     */
    public static function userId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current user's type
     */
    public static function userType() {
        return $_SESSION['user_type'] ?? null;
    }
    
    /**
     * Get current user's email
     */
    public static function userEmail() {
        return $_SESSION['email'] ?? null;
    }
    
    /**
     * Get current user's username
     */
    public static function getUsername() {
        return $_SESSION['username'] ?? null;
    }
    
    /**
     * Set a flash message
     */
    public static function setFlash($type, $message) {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message,
            'time' => time()
        ];
    }
    
    /**
     * Display flash message
     */
    public static function displayFlash() {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            $type = $flash['type'];
            $message = $flash['message'];
            
            $alertClass = '';
            $icon = '';
            
            switch($type) {
                case 'success':
                    $alertClass = 'alert-success';
                    $icon = 'fa-check-circle';
                    break;
                case 'error':
                case 'danger':
                    $alertClass = 'alert-danger';
                    $icon = 'fa-exclamation-circle';
                    break;
                case 'warning':
                    $alertClass = 'alert-warning';
                    $icon = 'fa-exclamation-triangle';
                    break;
                case 'info':
                    $alertClass = 'alert-info';
                    $icon = 'fa-info-circle';
                    break;
                default:
                    $alertClass = 'alert-info';
                    $icon = 'fa-info-circle';
            }
            
            echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
            echo '<i class="fas ' . $icon . ' me-2"></i>' . htmlspecialchars($message);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
            
            unset($_SESSION['flash']);
        }
    }
    
    /**
     * Get base URL for redirects
     */
    private static function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $dirName = dirname($scriptName);
        
        // Remove /admin or /trainer or /member from path if present
        $basePath = preg_replace('/(admin|trainer|member)(\/.*)?$/', '', $dirName);
        
        return rtrim($protocol . $host . $basePath, '/');
    }
    
    /**
     * Destroy session (logout)
     */
    public static function destroy() {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    /**
     * Regenerate session ID for security
     */
    public static function regenerate() {
        session_regenerate_id(true);
    }
    
    /**
     * Check if user has specific permission
     */
    public static function hasPermission($permission) {
        // Define permissions for each user type
        $permissions = [
            'admin' => [
                'manage_users',
                'manage_trainers', 
                'manage_members',
                'view_reports',
                'manage_payments',
                'manage_classes',
                'manage_equipment',
                'send_notifications',
                'manage_settings'
            ],
            'trainer' => [
                'view_members',
                'create_workouts',
                'mark_attendance',
                'view_schedule',
                'view_member_progress',
                'send_messages'
            ],
            'member' => [
                'view_workouts',
                'book_classes',
                'view_attendance',
                'make_payments',
                'view_profile',
                'update_profile'
            ]
        ];
        
        $userType = self::userType();
        
        if (!$userType || !isset($permissions[$userType])) {
            return false;
        }
        
        return in_array($permission, $permissions[$userType]);
    }
    
    /**
     * Get user's permissions
     */
    public static function getPermissions() {
        $userType = self::userType();
        
        $permissions = [
            'admin' => [
                'manage_users',
                'manage_trainers', 
                'manage_members',
                'view_reports',
                'manage_payments',
                'manage_classes',
                'manage_equipment',
                'send_notifications',
                'manage_settings'
            ],
            'trainer' => [
                'view_members',
                'create_workouts',
                'mark_attendance',
                'view_schedule',
                'view_member_progress',
                'send_messages'
            ],
            'member' => [
                'view_workouts',
                'book_classes',
                'view_attendance',
                'make_payments',
                'view_profile',
                'update_profile'
            ]
        ];
        
        return $permissions[$userType] ?? [];
    }
    
    /**
     * Check if session has expired
     */
    public static function isExpired($maxLifetime = 3600) {
        $lastActivity = $_SESSION['last_activity'] ?? 0;
        return (time() - $lastActivity) > $maxLifetime;
    }
    
    /**
     * Update last activity time
     */
    public static function updateActivity() {
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Get session data as array
     */
    public static function toArray() {
        return [
            'user_id' => self::userId(),
            'username' => self::getUsername(),
            'full_name' => self::userName(),
            'email' => self::userEmail(),
            'user_type' => self::userType(),
            'is_logged_in' => self::isLoggedIn(),
            'is_admin' => self::isAdmin(),
            'is_trainer' => self::isTrainer(),
            'is_member' => self::isMember()
        ];
    }
}

// For backward compatibility with existing code
function isLoggedIn() {
    return Session::isLoggedIn();
}

function isAdmin() {
    return Session::isAdmin();
}

function isTrainer() {
    return Session::isTrainer();
}

function isMember() {
    return Session::isMember();
}

function redirectIfNotLoggedIn() {
    if (!Session::isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function redirectBasedOnRole() {
    if (Session::isAdmin()) {
        header('Location: admin/dashboard.php');
    } elseif (Session::isTrainer()) {
        header('Location: trainer/dashboard.php');
    } elseif (Session::isMember()) {
        header('Location: member/dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

// Initialize session
Session::init();

// Update activity on each page load
if (Session::isLoggedIn()) {
    Session::updateActivity();
}
?>