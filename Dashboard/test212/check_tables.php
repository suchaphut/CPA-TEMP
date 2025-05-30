<?php
// Include database connection
require_once 'db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');   // เช็ท header ให้เป็น JSON

try {  // เช็คการเชื่อมต่อกับฐานข้อมูล
    // Check if required tables exist
    $required_tables = ['users', 'rooms', 'sensor_data', 'user_settings', 'room_thresholds'];   // กำหนดชื่อของตารางที่ต้องการเช็ค
    $missing_tables = [];  // สร้าง array สำหรับเก็บชื่อของตารางที่ไม่พบ
     
    foreach ($required_tables as $table) {   // วนลูปเช็คแต่ละตาราง
        $result = $conn->query("SHOW TABLES LIKE '$table'");  // เช็คว่ามีตารางนี้อยู่ในฐานข้อมูลหรือไม่
        if ($result->num_rows === 0) {   // ถ้าไม่มีตารางนี้อยู่ในฐานข้อมูล
            $missing_tables[] = $table;  // เพิ่มชื่อของตารางที่ไม่พบลงใน array
        }
    }
    
    if (!empty($missing_tables)) {   // ถ้ามีตารางที่ไม่พบ
        // Create missing tables
        if (in_array('users', $missing_tables)) {   // เช็คว่ามีตาราง users หรือไม่
            $conn->query("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL
            )");
        }
        
        if (in_array('rooms', $missing_tables)) {  // เช็คว่ามีตาราง rooms หรือไม่
            $conn->query("CREATE TABLE IF NOT EXISTS rooms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                room_name VARCHAR(50) NOT NULL UNIQUE,
                user_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            // Insert default rooms
            $conn->query("INSERT INTO rooms (room_name) VALUES      
                ('Room1'),
                ('Room2'),
                ('Room3')
                ON DUPLICATE KEY UPDATE room_name = VALUES(room_name)");
        }
        
        if (in_array('sensor_data', $missing_tables)) {
            $conn->query("CREATE TABLE IF NOT EXISTS sensor_data (
                id INT AUTO_INCREMENT PRIMARY KEY,
                temperature FLOAT NOT NULL,
                humidity FLOAT NOT NULL,
                room VARCHAR(50) NOT NULL,
                datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        }
        
        if (in_array('user_settings', $missing_tables)) {
            $conn->query("CREATE TABLE IF NOT EXISTS user_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                temp_min FLOAT DEFAULT 24,
                temp_max FLOAT DEFAULT 25,
                humidity_min FLOAT DEFAULT 50,
                humidity_max FLOAT DEFAULT 60,
                discord_webhook VARCHAR(255) NULL,
                discord_enabled BOOLEAN DEFAULT FALSE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )");
        }
        
        if (in_array('room_thresholds', $missing_tables)) {
            $conn->query("CREATE TABLE IF NOT EXISTS room_thresholds (
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
            )");
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Missing tables created successfully',
            'created_tables' => $missing_tables
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'All required tables exist'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error checking tables: ' . $e->getMessage()
    ]);
    
    // Log error
    error_log("Check tables error: " . $e->getMessage());
}

$conn->close();
?>
