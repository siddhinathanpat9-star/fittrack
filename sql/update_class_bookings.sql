-- Update class_bookings table to use schedule_id instead of class_id
ALTER TABLE class_bookings DROP FOREIGN KEY class_bookings_ibfk_1;
ALTER TABLE class_bookings DROP COLUMN class_id;
ALTER TABLE class_bookings ADD COLUMN schedule_id INT NOT NULL AFTER id;
ALTER TABLE class_bookings ADD CONSTRAINT class_bookings_schedule_fk 
    FOREIGN KEY (schedule_id) REFERENCES class_schedule(id) ON DELETE CASCADE;
ALTER TABLE class_bookings MODIFY COLUMN status ENUM('confirmed','attended','cancelled','no_show') DEFAULT 'confirmed';
