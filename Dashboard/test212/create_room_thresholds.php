<?php
// Include database connection
require_once 'db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');

try {
    // Create room_thresholds table if it doesn't exist
    $create_table_sql = "
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
    )";

    if ($conn->query($create_table_sql) === TRUE) {
        // Check if we need to insert default thresholds
        $check_sql = "SELECT COUNT(*) as count FROM room_thresholds";
        $result = $conn->query($check_sql);
        $row = $result->fetch_assoc();
        
        if ($row['count'] == 0) {
            // Insert default thresholds for existing rooms and users
            $insert_sql = "
            INSERT IGNORE INTO room_thresholds (room_id, user_id, temp_min, temp_max, humidity_min, humidity_max)
            SELECT r.id, u.id, 
                COALESCE(us.temp_min, 24), 
                COALESCE(us.temp_max, 25), 
                COALESCE(us.humidity_min, 50), 
                COALESCE(us.humidity_max, 60)
            FROM rooms r
            CROSS JOIN users u
            LEFT JOIN user_settings us ON u.id = us.user_id
            ";
            
            if ($conn->query($insert_sql) === TRUE) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Room thresholds table created and default values inserted successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Room thresholds table created but failed to insert default values: ' . $conn->error
                ]);
            }
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Room thresholds table already exists with data'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create room thresholds table: ' . $conn->error
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
