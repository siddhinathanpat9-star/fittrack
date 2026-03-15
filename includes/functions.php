<?php
// admin/includes/functions.php

require_once __DIR__ . '/config.php';

class Functions {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * Get dashboard statistics
     */
    public function getDashboardStats() {
        $stats = [];
        
        try {
            // Total members
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'member'");
            $stats['total_members'] = $stmt->fetchColumn();
            
            // Active members
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'member' AND status = 'active'");
            $stats['active_members'] = $stmt->fetchColumn();
            
            // Today's attendance
            $stmt = $this->pdo->query("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE date = CURDATE()");
            $stats['today_attendance'] = $stmt->fetchColumn();
            
            // Today's revenue
            $stmt = $this->pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(payment_date) = CURDATE() AND status = 'paid'");
            $stats['today_revenue'] = $stmt->fetchColumn();
            
            // Monthly revenue
            $stmt = $this->pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE()) AND status = 'paid'");
            $stats['monthly_revenue'] = $stmt->fetchColumn();
            
            // Total trainers
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'trainer'");
            $stats['total_trainers'] = $stmt->fetchColumn();
            
            // Total classes
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM classes WHERE status = 'active'");
            $stats['total_classes'] = $stmt->fetchColumn();
            
            // Total payments this month
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())");
            $stats['total_payments'] = $stmt->fetchColumn();
            
            // Pending tasks (can be customized)
            $stats['pending_tasks'] = 5;
            
        } catch (Exception $e) {
            error_log("Error getting dashboard stats: " . $e->getMessage());
            $stats = [
                'total_members' => 0,
                'active_members' => 0,
                'today_attendance' => 0,
                'today_revenue' => 0,
                'monthly_revenue' => 0,
                'total_trainers' => 0,
                'total_classes' => 0,
                'total_payments' => 0,
                'pending_tasks' => 0
            ];
        }
        
        return $stats;
    }
    
    /**
     * Get all members with optional filter
     */
    public function getAllMembers($status = null, $limit = null) {
        try {
            $sql = "SELECT u.id as member_id, u.full_name, u.email, u.phone, u.status,
                    m.membership_type, m.membership_end as expiry_date, 
                    m.membership_start, m.assigned_trainer_id
                    FROM users u 
                    LEFT JOIN members m ON u.id = m.user_id 
                    WHERE u.user_type = 'member'";
            
            if ($status) {
                $sql .= " AND u.status = :status";
            }
            
            $sql .= " ORDER BY u.created_at DESC";
            
            if ($limit) {
                $sql .= " LIMIT :limit";
            }
            
            $stmt = $this->pdo->prepare($sql);
            
            if ($status) {
                $stmt->bindValue(':status', $status);
            }
            
            if ($limit) {
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Error getting members: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get class schedule
     */
    public function getClassSchedule($limit = null) {
        try {
            $sql = "SELECT c.*, u.full_name as trainer_name,
                    (SELECT COUNT(*) FROM class_bookings cb WHERE cb.class_id = c.id AND cb.booking_date = CURDATE()) as current_bookings
                    FROM classes c
                    LEFT JOIN users u ON c.trainer_id = u.id
                    WHERE c.status = 'active'
                    ORDER BY FIELD(c.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                    c.start_time";
            
            if ($limit) {
                $sql .= " LIMIT :limit";
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $stmt = $this->pdo->query($sql);
            }
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting class schedule: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recent payments
     */
    public function getRecentPayments($limit = 10) {
        try {
            $sql = "SELECT p.*, u.full_name as member_name
                    FROM payments p
                    LEFT JOIN users u ON p.member_id = u.id
                    ORDER BY p.payment_date DESC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting recent payments: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get expiring memberships
     */
    public function getExpiringMemberships($days = 7) {
        try {
            $sql = "SELECT u.id as member_id, u.full_name, u.email, u.phone,
                    m.membership_type, m.membership_end as expiry_date
                    FROM users u
                    JOIN members m ON u.id = m.user_id
                    WHERE u.user_type = 'member'
                    AND u.status = 'active'
                    AND m.membership_end IS NOT NULL
                    AND m.membership_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
                    ORDER BY m.membership_end ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting expiring memberships: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get equipment statistics
     */
    public function getEquipmentStats() {
        try {
            // Check if equipment table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'equipment'");
            if ($stmt->rowCount() == 0) {
                return ['total' => 0, 'available' => 0, 'maintenance' => 0];
            }
            
            $stmt = $this->pdo->query("SELECT 
                    COUNT(*) as total, 
                    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance
                    FROM equipment");
            
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Error getting equipment stats: " . $e->getMessage());
            return ['total' => 0, 'available' => 0, 'maintenance' => 0];
        }
    }
    
    /**
     * Get recent notifications
     */
    public function getRecentNotifications($limit = 5) {
        try {
            // Check if notifications table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'notifications'");
            if ($stmt->rowCount() == 0) {
                return [];
            }
            
            $sql = "SELECT n.*, u.full_name as sender_name
                    FROM notifications n
                    LEFT JOIN users u ON n.sender_id = u.id
                    WHERE n.recipient_id IS NULL OR n.recipient_id = 0
                    ORDER BY n.created_at DESC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get membership distribution
     */
    public function getMembershipDistribution() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COALESCE(membership_type, 'basic') as type,
                    COUNT(*) as count
                FROM members
                GROUP BY membership_type
            ");
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting membership distribution: " . $e->getMessage());
            return [
                ['type' => 'basic', 'count' => 0],
                ['type' => 'premium', 'count' => 0],
                ['type' => 'vip', 'count' => 0]
            ];
        }
    }
    
    /**
     * Get revenue for last 6 months
     */
    public function getRevenueLast6Months() {
        $months = [];
        $revenues = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $month = date('M', strtotime("-$i months"));
            $months[] = $month;
            
            $start = date('Y-m-01', strtotime("-$i months"));
            $end = date('Y-m-t', strtotime("-$i months"));
            
            try {
                $stmt = $this->pdo->prepare("
                    SELECT COALESCE(SUM(amount), 0) as total
                    FROM payments
                    WHERE DATE(payment_date) BETWEEN ? AND ?
                    AND status = 'paid'
                ");
                $stmt->execute([$start, $end]);
                $revenues[] = (float)$stmt->fetchColumn();
            } catch (Exception $e) {
                $revenues[] = 0;
            }
        }
        
        return ['months' => $months, 'revenues' => $revenues];
    }
    
    /**
     * Format currency
     */
    public function formatCurrency($amount) {
        return '$' . number_format($amount, 2);
    }
    
    /**
     * Format date
     */
    public function formatDate($date, $format = null) {
        if (empty($date)) return 'N/A';
        if (!$format) $format = DATE_FORMAT;
        return date($format, strtotime($date));
    }
    
    /**
     * Get time ago string
     */
    public function timeAgo($datetime) {
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return $diff . ' seconds ago';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 2592000) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date(DATE_FORMAT, $time);
        }
    }
    
    /**
     * Generate random string
     */
    public function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    
    /**
     * Validate email
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Sanitize input
     */
    public function sanitize($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Error getting user: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Log activity
     */
    public function logActivity($user_id, $action, $details = null) {
        try {
            // Check if activity_log table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'activity_log'");
            if ($stmt->rowCount() == 0) {
                return false;
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_log (user_id, action, details, ip_address, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            
            return $stmt->execute([$user_id, $action, $details, $ip]);
        } catch (Exception $e) {
            error_log("Error logging activity: " . $e->getMessage());
            return false;
        }
    }
}
?>