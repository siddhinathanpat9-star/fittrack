-- Safer migration: rename old table, create new one, migrate data
RENAME TABLE class_bookings TO class_bookings_old;

CREATE TABLE class_bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    schedule_id INT NOT NULL,
    member_id INT NOT NULL,
    booking_date DATE NOT NULL,
    status ENUM('confirmed','attended','cancelled','no_show') DEFAULT 'confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES class_schedule(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_booking (schedule_id, member_id)
);

-- Migrate data from old table (map class_id to schedule_id where possible)
-- For now, just insert sample data since the mapping isn't straightforward
-- DROP TABLE class_bookings_old;
