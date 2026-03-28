CREATE DATABASE IF NOT EXISTS fittrack;
USE fittrack;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    user_type ENUM('admin', 'trainer', 'member') NOT NULL,
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active'
);

-- Members additional info
CREATE TABLE members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    membership_type ENUM('basic', 'premium', 'vip') DEFAULT 'basic',
    membership_start DATE,
    membership_end DATE,
    height DECIMAL(5,2),
    weight DECIMAL(5,2),
    fitness_goals TEXT,
    emergency_contact VARCHAR(100),
    emergency_phone VARCHAR(20),
    assigned_trainer_id INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_trainer_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Trainers additional info
CREATE TABLE trainers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    specialization VARCHAR(255),
    experience_years INT,
    qualification TEXT,
    availability VARCHAR(255),
    hourly_rate DECIMAL(10,2),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Workout plans
CREATE TABLE workout_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    trainer_id INT,
    member_id INT,
    plan_name VARCHAR(100),
    description TEXT,
    exercises TEXT,
    duration_weeks INT,
    start_date DATE,
    end_date DATE,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Attendance
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    check_in DATETIME,
    check_out DATETIME,
    date DATE,
    status ENUM('present', 'absent', 'late') DEFAULT 'present',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Payments
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT,
    amount DECIMAL(10,2),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_method ENUM('cash', 'card', 'online') DEFAULT 'cash',
    payment_for VARCHAR(100),
    status ENUM('paid', 'pending', 'failed') DEFAULT 'paid',
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default admin
INSERT INTO users (username, email, password, full_name, user_type) 
VALUES ('admin', 'admin@fittrack.com', '$2y$10$YourHashedPasswordHere', 'System Admin', 'admin');
-- Messages table for member-trainer communication
CREATE TABLE IF NOT EXISTS messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sender (sender_id),
    INDEX idx_recipient (recipient_id),
    INDEX idx_read (is_read)
);

-- Progress tracking table
CREATE TABLE IF NOT EXISTS progress_tracking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,
    recorded_date DATE NOT NULL,
    weight DECIMAL(5,2),
    body_fat DECIMAL(5,2),
    muscle_mass DECIMAL(5,2),
    chest DECIMAL(5,2),
    waist DECIMAL(5,2),
    hips DECIMAL(5,2),
    arms DECIMAL(5,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_member_date (member_id, recorded_date)
);

-- Exercise library
CREATE TABLE IF NOT EXISTS exercises (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    muscle_group VARCHAR(50),
    equipment_needed VARCHAR(100),
    difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    video_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample exercises
INSERT INTO exercises (name, description, muscle_group, equipment_needed, difficulty) VALUES
('Push Up', 'Basic chest exercise', 'Chest', 'None', 'beginner'),
('Pull Up', 'Back and biceps exercise', 'Back', 'Pull up bar', 'intermediate'),
('Squat', 'Leg exercise', 'Legs', 'None', 'beginner'),
('Deadlift', 'Full body exercise', 'Full Body', 'Barbell', 'advanced'),
('Bench Press', 'Chest exercise', 'Chest', 'Barbell, Bench', 'intermediate'),
('Shoulder Press', 'Shoulder exercise', 'Shoulders', 'Dumbbells', 'intermediate');

-- Create trainers table (if not exists)
CREATE TABLE IF NOT EXISTS trainers (
    user_id INT PRIMARY KEY,
    specialization VARCHAR(255),
    experience_years INT,
    hourly_rate DECIMAL(10,2),
    qualification TEXT,
    availability TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create classes table
CREATE TABLE IF NOT EXISTS classes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_name VARCHAR(100) NOT NULL,
    description TEXT,
    trainer_id INT,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    max_capacity INT DEFAULT 20,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create class_bookings table
CREATE TABLE IF NOT EXISTS class_bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT NOT NULL,
    member_id INT NOT NULL,
    booking_date DATE NOT NULL,
    status ENUM('booked', 'attended', 'cancelled', 'no_show') DEFAULT 'booked',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_booking (class_id, member_id, booking_date)
);
-- Notifications master table
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info','success','warning','danger') DEFAULT 'info',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notification recipients (links notifications to users)
CREATE TABLE IF NOT EXISTS notification_recipients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    notification_id INT NOT NULL,
    user_id INT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_notification_user (notification_id, user_id)
);
CREATE TABLE IF NOT EXISTS membership_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration_days INT NOT NULL,
    features TEXT,
    max_classes_per_week INT DEFAULT NULL,
    personal_training_sessions INT DEFAULT 0,
    access_to_equipment BOOLEAN DEFAULT TRUE,
    access_to_classes BOOLEAN DEFAULT FALSE,
    access_to_sauna BOOLEAN DEFAULT FALSE,
    access_to_pool BOOLEAN DEFAULT FALSE,
    guest_passes INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    description TEXT,
    type ENUM('text', 'textarea', 'number', 'email', 'url', 'boolean') DEFAULT 'text',
    category VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT IGNORE INTO settings (setting_key, setting_value, description, type, category) VALUES
('gym_name', 'FitTrack Gym', 'Name of the gym', 'text', 'general'),
('gym_address', '123 Fitness Street, City, State 12345', 'Physical address', 'textarea', 'general'),
('gym_phone', '+1 234 567 890', 'Contact phone number', 'text', 'general'),
('gym_email', 'info@fittrack.com', 'General contact email', 'email', 'general'),
('gym_website', 'https://www.fittrack.com', 'Website URL', 'url', 'general'),
('timezone', 'America/New_York', 'Default timezone', 'text', 'system'),
('date_format', 'M d, Y', 'Date display format', 'text', 'system'),
('time_format', 'h:i A', 'Time display format', 'text', 'system'),
('items_per_page', '20', 'Number of items per page in listings', 'number', 'system'),
('enable_registration', '1', 'Allow new user registration', 'boolean', 'membership'),
('default_membership_days', '30', 'Default membership duration in days', 'number', 'membership'),
('default_membership_price', '29.99', 'Default membership price', 'number', 'membership'),
('smtp_host', '', 'SMTP server host', 'text', 'email'),
('smtp_port', '587', 'SMTP port', 'number', 'email'),
('smtp_secure', 'tls', 'SMTP encryption (tls/ssl)', 'text', 'email'),
('smtp_username', '', 'SMTP username', 'text', 'email'),
('smtp_password', '', 'SMTP password', 'text', 'email'),
('smtp_from_email', 'noreply@fittrack.com', 'Default from email', 'email', 'email'),
('smtp_from_name', 'FitTrack Gym', 'Default from name', 'text', 'email');

CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    payment_method VARCHAR(50),
    payment_for VARCHAR(100),
    transaction_id VARCHAR(100),
    razorpay_order_id VARCHAR(100),
    razorpay_payment_id VARCHAR(100),
    razorpay_signature VARCHAR(255),
    status ENUM('paid','pending','failed') DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS membership_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration_days INT NOT NULL,
    features TEXT,
    sort_order INT DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
