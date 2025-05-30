<?php
// เชื่อมต่อกับฐานข้อมูล MySQL
require_once 'db_connect.php';

// กำหนด header ให้ส่งข้อมูลกลับเป็น JSON
header('Content-Type: application/json');

// เปิดการแสดงข้อผิดพลาดทั้งหมดเพื่อช่วยในการดีบัก
// แสดงข้อผิดพลาดทุกประเภท (E_ALL) และเปิดการแสดงผลข้อผิดพลาด (display_errors)
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // ตรวจสอบว่าเป็นการเรียกใช้งานด้วยเมธอด GET หรือไม่
    // หากไม่ใช่จะแสดงข้อผิดพลาด 'Invalid request method'
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }

    // รับค่า user_id จาก URL parameter (ถ้ามี)
    // ใช้สำหรับดึงค่าขีดจำกัด (thresholds) เฉพาะของผู้ใช้
    // ถ้าไม่มีการส่ง user_id มา จะกำหนดให้เป็น null
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

    // ตรวจสอบว่ามีตาราง rooms ในฐานข้อมูลหรือไม่
    // ใช้คำสั่ง SHOW TABLES เพื่อค้นหาตารางที่มีชื่อ 'rooms'
    $table_check = $conn->query("SHOW TABLES LIKE 'rooms'");
    if ($table_check->num_rows === 0) {
        // หากไม่มีตาราง rooms จะทำการสร้างตารางใหม่
        // โครงสร้างตารางประกอบด้วย:
        // - id: รหัสห้อง (auto increment)
        // - room_name: ชื่อห้อง (ห้ามซ้ำ)
        // - user_id: รหัสผู้ใช้ที่เป็นเจ้าของห้อง (อาจเป็น null ได้)
        // - created_at: วันเวลาที่สร้างห้อง
        $create_table = "CREATE TABLE IF NOT EXISTS rooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_name VARCHAR(50) NOT NULL UNIQUE,
            user_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        if (!$conn->query($create_table)) {
            throw new Exception("Failed to create rooms table: " . $conn->error);
        }
        
        // Insert default rooms
        $default_rooms = "INSERT INTO rooms (room_name) VALUES 
            ('Room1'),
            ('Room2'),
            ('Room3')
            ON DUPLICATE KEY UPDATE room_name = VALUES(room_name)";
        
        if (!$conn->query($default_rooms)) {
            throw new Exception("Failed to insert default rooms: " . $conn->error);
        }
    }

    // Get all rooms
    $rooms_stmt = $conn->prepare("SELECT id, room_name FROM rooms");
    if (!$rooms_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $rooms_stmt->execute();
    $rooms_result = $rooms_stmt->get_result();

    $room_status = [];

    if ($rooms_result) {
        while ($room = $rooms_result->fetch_assoc()) {
            $room_name = $room['room_name'];
            $room_id = $room['id'];
            
            // กำหนดค่าเริ่มต้นสำหรับขีดจำกัดอุณหภูมิและความชื้น
            // ค่าเหล่านี้จะถูกใช้เมื่อไม่มีการตั้งค่าเฉพาะสำหรับผู้ใช้
            $temp_min = 24;      // อุณหภูมิต่ำสุดที่ยอมรับได้ (องศาเซลเซียส)
            $temp_max = 25;      // อุณหภูมิสูงสุดที่ยอมรับได้ (องศาเซลเซียส)
            $humidity_min = 50;  // ความชื้นต่ำสุดที่ยอมรับได้ (เปอร์เซ็นต์)
            $humidity_max = 60;  // ความชื้นสูงสุดที่ยอมรับได้ (เปอร์เซ็นต์)
            
            if ($user_id) {
                // ตรวจสอบว่ามีตาราง room_thresholds หรือไม่
                // ตารางนี้ใช้เก็บค่าขีดจำกัดที่กำหนดโดยผู้ใช้สำหรับแต่ละห้อง
                $threshold_table_check = $conn->query("SHOW TABLES LIKE 'room_thresholds'");
                if ($threshold_table_check->num_rows === 0) {
                    // Create room_thresholds table if it doesn't exist
                    $create_threshold_table = "CREATE TABLE IF NOT EXISTS room_thresholds (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        room_id INT NOT NULL,
                        user_id INT NOT NULL,
                        temp_min FLOAT DEFAULT 24,
                        temp_max FLOAT DEFAULT 25,
                        humidity_min FLOAT DEFAULT 50,
                        humidity_max FLOAT DEFAULT 60,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY room_user_unique (room_id, user_id)
                    )";
                    
                    if (!$conn->query($create_threshold_table)) {
                        throw new Exception("Failed to create room_thresholds table: " . $conn->error);
                    }
                }
                
                // Try to get room-specific thresholds
                $thresholds_stmt = $conn->prepare("SELECT temp_min, temp_max, humidity_min, humidity_max FROM room_thresholds WHERE room_id = ? AND user_id = ?");
                if (!$thresholds_stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $thresholds_stmt->bind_param("ii", $room_id, $user_id);
                $thresholds_stmt->execute();
                $thresholds_result = $thresholds_stmt->get_result();
                
                if ($thresholds_result->num_rows > 0) {
                    $thresholds = $thresholds_result->fetch_assoc();
                    $temp_min = $thresholds['temp_min'];
                    $temp_max = $thresholds['temp_max'];
                    $humidity_min = $thresholds['humidity_min'];
                    $humidity_max = $thresholds['humidity_max'];
                } else {
                    // Check if user_settings table exists
                    $settings_table_check = $conn->query("SHOW TABLES LIKE 'user_settings'");
                    if ($settings_table_check->num_rows === 0) {
                        // Create user_settings table if it doesn't exist
                        $create_settings_table = "CREATE TABLE IF NOT EXISTS user_settings (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT NOT NULL,
                            temp_min FLOAT DEFAULT 24,
                            temp_max FLOAT DEFAULT 25,
                            humidity_min FLOAT DEFAULT 50,
                            humidity_max FLOAT DEFAULT 60,
                            discord_webhook VARCHAR(255) NULL,
                            discord_enabled BOOLEAN DEFAULT FALSE
                        )";
                        
                        if (!$conn->query($create_settings_table)) {
                            throw new Exception("Failed to create user_settings table: " . $conn->error);
                        }
                    }
                    
                    // Fall back to user settings
                    $settings_stmt = $conn->prepare("SELECT temp_min, temp_max, humidity_min, humidity_max FROM user_settings WHERE user_id = ?");
                    if (!$settings_stmt) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }
                    
                    $settings_stmt->bind_param("i", $user_id);
                    $settings_stmt->execute();
                    $settings_result = $settings_stmt->get_result();
                    
                    if ($settings_result->num_rows > 0) {
                        $settings = $settings_result->fetch_assoc();
                        $temp_min = $settings['temp_min'];
                        $temp_max = $settings['temp_max'];
                        $humidity_min = $settings['humidity_min'];
                        $humidity_max = $settings['humidity_max'];
                    }
                    $settings_stmt->close();
                }
                $thresholds_stmt->close();
            }
            
            // ตรวจสอบว่ามีตาราง sensor_data สำหรับเก็บข้อมูลจากเซ็นเซอร์หรือไม่
            $sensor_table_check = $conn->query("SHOW TABLES LIKE 'sensor_data'");
            if ($sensor_table_check->num_rows === 0) {
                // สร้างตาราง sensor_data หากยังไม่มี
                // โครงสร้างตารางประกอบด้วย:
                // - id: รหัสข้อมูล (auto increment)
                // - temperature: ค่าอุณหภูมิที่วัดได้ (องศาเซลเซียส)
                // - humidity: ค่าความชื้นที่วัดได้ (เปอร์เซ็นต์)
                // - room: ชื่อห้องที่ทำการวัด
                // - datetime: วันและเวลาที่บันทึกข้อมูล
                $create_sensor_table = "CREATE TABLE IF NOT EXISTS sensor_data (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    temperature FLOAT NOT NULL,
                    humidity FLOAT NOT NULL,
                    room VARCHAR(50) NOT NULL,
                    datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                
                if (!$conn->query($create_sensor_table)) {
                    throw new Exception("Failed to create sensor_data table: " . $conn->error);
                }
                // ไม่ต้องสร้างข้อมูลตัวอย่างเมื่อสร้างตารางใหม่
            }
            
            // ดึงข้อมูลล่าสุดของวันนี้สำหรับห้องนี้
            $sensor_stmt = $conn->prepare("SELECT temperature, humidity FROM sensor_data 
                WHERE room = ? 
                AND DATE(datetime) = CURDATE()
                ORDER BY datetime DESC 
                LIMIT 1");
            if (!$sensor_stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $sensor_stmt->bind_param("s", $room_name);
            $sensor_stmt->execute();
            $sensor_result = $sensor_stmt->get_result();
            
            // กำหนดค่าเริ่มต้นสำหรับข้อมูลอุณหภูมิและความชื้น
            $temperature = null;       // ค่าอุณหภูมิ
            $humidity = null;          // ค่าความชื้น
            $temp_status = 'normal';   // สถานะอุณหภูมิ (normal, high, low)
            $humidity_status = 'normal';// สถานะความชื้น (normal, high, low)
            
            if ($sensor_result->num_rows > 0) {
                // ดึงข้อมูลล่าสุดจากเซ็นเซอร์
                $sensor_data = $sensor_result->fetch_assoc();
                $temperature = $sensor_data['temperature'];
                $humidity = $sensor_data['humidity'];
                
                // ตรวจสอบสถานะของอุณหภูมิเทียบกับค่าขีดจำกัด
                // - ถ้าอุณหภูมิสูงกว่าค่าสูงสุด -> สถานะ 'high'
                // - ถ้าอุณหภูมิต่ำกว่าค่าต่ำสุด -> สถานะ 'low'
                // - ถ้าอยู่ระหว่างค่าต่ำสุดและสูงสุด -> สถานะ 'normal'
                if ($temperature > $temp_max) {
                    $temp_status = 'high';
                } else if ($temperature < $temp_min) {
                    $temp_status = 'low';
                }
                
                // ตรวจสอบสถานะของความชื้นเทียบกับค่าขีดจำกัด
                // - ถ้าความชื้นสูงกว่าค่าสูงสุด -> สถานะ 'high'
                // - ถ้าความชื้นต่ำกว่าค่าต่ำสุด -> สถานะ 'low'
                // - ถ้าอยู่ระหว่างค่าต่ำสุดและสูงสุด -> สถานะ 'normal'
                if ($humidity > $humidity_max) {
                    $humidity_status = 'high';
                } else if ($humidity < $humidity_min) {
                    $humidity_status = 'low';
                }
            } else {
                // ถ้าไม่มีข้อมูลสำหรับห้องนี้ในวันนี้
                $temperature = 'No data';
                $humidity = 'No data';
                $temp_status = 'no_data';
                $humidity_status = 'no_data';
            }
            
            // เตรียมข้อมูลสถานะห้องสำหรับส่งกลับเป็น JSON
            // ประกอบด้วยข้อมูล:
            // - id: รหัสห้อง
            // - name: ชื่อห้อง
            // - temperature: ค่าอุณหภูมิที่วัดได้ล่าสุด
            // - humidity: ค่าความชื้นที่วัดได้ล่าสุด
            // - temp_status: สถานะอุณหภูมิ (normal/high/low)
            // - humidity_status: สถานะความชื้น (normal/high/low)
            // - thresholds: ค่าขีดจำกัดที่กำหนดไว้สำหรับห้องนี้
            $room_status[] = [
                'id' => $room['id'],
                'name' => $room_name,
                'temperature' => $temperature,
                'humidity' => $humidity,
                'temp_status' => $temp_status,
                'humidity_status' => $humidity_status,
                'thresholds' => [
                    'temp_min' => $temp_min,
                    'temp_max' => $temp_max,
                    'humidity_min' => $humidity_min,
                    'humidity_max' => $humidity_max
                ]
            ];
            
            $sensor_stmt->close();
        }
    }

    $rooms_stmt->close();
    
    // ส่งข้อมูลกลับเป็น JSON format
    // - success: true แสดงว่าการทำงานสำเร็จ
    // - rooms: array ของข้อมูลสถานะแต่ละห้อง
    echo json_encode([
        'success' => true,
        'rooms' => $room_status
    ]);

} catch (Exception $e) {
    // บันทึกข้อผิดพลาดลงใน error log ของระบบ
    // ใช้สำหรับการตรวจสอบปัญหาในภายหลัง
    error_log("get_room_status.php error: " . $e->getMessage());
    
    // ส่งข้อความแสดงข้อผิดพลาดกลับไปยังผู้ใช้เป็น JSON
    // - success: false แสดงว่าเกิดข้อผิดพลาด
    // - message: ข้อความอธิบายข้อผิดพลาดที่เกิดขึ้น
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
