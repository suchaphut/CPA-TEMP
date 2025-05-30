-- Create room_thresholds table to store room-specific threshold settings
CREATE TABLE IF NOT EXISTS room_thresholds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    temp_min FLOAT DEFAULT 24,
    temp_max FLOAT DEFAULT 25,
    humidity_min FLOAT DEFAULT 50,
    humidity_max FLOAT DEFAULT 60,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY room_user_unique (room_id, user_id)
);

-- Insert default thresholds for existing rooms and users
INSERT INTO room_thresholds (room_id, user_id, temp_min, temp_max, humidity_min, humidity_max)
SELECT r.id, u.id, us.temp_min, us.temp_max, us.humidity_min, us.humidity_max
FROM rooms r
CROSS JOIN users u
LEFT JOIN user_settings us ON u.id = us.user_id
ON DUPLICATE KEY UPDATE room_id = room_id;
