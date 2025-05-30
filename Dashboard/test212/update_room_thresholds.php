<?php
// Include database connection
require_once 'db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {   // เช็คว่าเป็น POST หรือไม่
    echo json_encode([   
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }

    // Validate required fields
    if (!isset($data['user_id']) || !isset($data['room_id']) ||     // เช็คว่ามี user_id และ room_id หรือไม่
        !isset($data['temp_min']) || !isset($data['temp_max']) ||   //  เช็คว่ามี temp_min และ temp_max หรือไม่
        !isset($data['humidity_min']) || !isset($data['humidity_max'])) {   // เช็คว่ามี humidity_min และ humidity_max หรือไม่
        throw new Exception('Missing required fields');  //  เช็คว่ามี field ที่จำเป็นหรือไม่
    }

    $user_id = intval($data['user_id']);    // หมายถึงการแปลง user_id เป็นจำนวนเต็ม
    $room_id = intval($data['room_id']);   // หมายถึงการแปลง room_id เป็นจำนวนเต็ม
    $temp_min = floatval($data['temp_min']);  // หมายถึงการแปลง temp_min เป็นจำนวนทศนิยม
    $temp_max = floatval($data['temp_max']);  // หมายถึงการแปลง temp_max เป็นจำนวนทศนิยม
    $humidity_min = floatval($data['humidity_min']); // หมายถึงการแปลง humidity_min เป็นจำนวนทศนิยม
    $humidity_max = floatval($data['humidity_max']); // หมายถึงการแปลง humidity_max เป็นจำนวนทศนิยม

    // Validate ranges
    if ($temp_min >= $temp_max) {     // เช็คว่า temp_min น้อยกว่า temp_max หรือไม่
        throw new Exception('Temperature minimum must be less than maximum');  //
    }

    if ($humidity_min >= $humidity_max) {   // เช็คว่า humidity_min น้อยกว่า humidity_max หรือไม่
        throw new Exception('Humidity minimum must be less than maximum');
    }

    // Check if room exists
    $room_check = $conn->prepare("SELECT id FROM rooms WHERE id = ?");   // เช็คว่า room_id มีอยู่ในฐานข้อมูลหรือไม่
    if (!$room_check) {  // เช็คว่าเตรียมคำสั่ง SQL สำเร็จหรือไม่
        throw new Exception('Database error: ' . $conn->error);   // ถ้าไม่สำเร็จให้แสดง error
    }
    
    $room_check->bind_param("i", $room_id);  // bind_param ใช้เพื่อป้องกัน SQL injection
    $room_check->execute();  // execute คำสั่ง SQL
    $room_result = $room_check->get_result();  // get_result ใช้เพื่อดึงผลลัพธ์จาการ query
    
    if ($room_result->num_rows === 0) {   // เช็คว่ามีผลลัพธ์หรือไม่
        throw new Exception('Room does not exist');  // ถ้าไม่มีผลลัพธ์ให้แสดง error
    } 
    $room_check->close();  // ปิดการเชื่อมต่อ

    // Check if user exists
    $user_check = $conn->prepare("SELECT id FROM users WHERE id = ?");    // เช็คว่า user_id มีอยู่ในฐานข้อมูลหรือไม่
    if (!$user_check) {   // เช็คว่าเตรียมคำสั่ง SQL สำเร็จหรือไม่
        throw new Exception('Database error: ' . $conn->error);    // ถ้าไม่สำเร็จให้แสดง error
    }
    
    $user_check->bind_param("i", $user_id);   // bind_param ใช้เพื่อป้องกัน SQL injection
    $user_check->execute();  // execute คำสั่ง SQL
    $user_result = $user_check->get_result();   // get_result ใช้เพื่อดึงผลลัพธ์จาการ query
    
    if ($user_result->num_rows === 0) {  // เช็คว่ามีผลลัพธ์หรือไม่
        throw new Exception('User does not exist');   // ถ้าไม่มีผลลัพธ์ให้แสดง error
    }
    $user_check->close();   // ปิดการเชื่อมต่อ

    // Check if room_thresholds table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'room_thresholds'");   // เช็คว่า room_thresholds มีอยู่ในฐานจข้อมูลหรือไม่
    if ($table_check->num_rows === 0) {   // ถ้าไม่มีตาราง room_thresholds ให้สร้างตารางใหม่
        // Create the table if it doesn't exist
        $create_table = "CREATE TABLE IF NOT EXISTS room_thresholds (    
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
        
        if (!$conn->query($create_table)) {     // เช็คว่าการสร้างตารางสำเร็จหรือไม่
            throw new Exception('Failed to create room_thresholds table: ' . $conn->error);  // ถ้าไม่สำเร็จให้แสดง error
        }
    }

    // Check if settings exist for this room and user
    $check_stmt = $conn->prepare("SELECT id FROM room_thresholds WHERE room_id = ? AND user_id = ?");  // เช็คว่า room_id และ user_id มีอยู่ในฐานข้อมูลหรือไม่
    if (!$check_stmt) {     // เช็คว่า้ตรียมคำสั่ง SQL สำเร็จหรือไม่
        throw new Exception('Database error: ' . $conn->error);  // ถ้าไม่สำเร็จให้แสดง error
    }
    
    $check_stmt->bind_param("ii", $room_id, $user_id);  // bind_param ใช้เพื่อป้องกัน SQL injections
    $check_stmt->execute(); // execute คำสั่ง SQL
    $check_result = $check_stmt->get_result(); // get_result ใช้เพื่อดึงผลลัพธ์จาการ query
    $check_stmt->close();  // ปิดการเชื่อมต่อ

    if ($check_result->num_rows === 0) {   // เช็คว่ามีผลลัพธ์หรือไม่
        // Insert new settings
        $stmt = $conn->prepare("INSERT INTO room_thresholds (room_id, user_id, temp_min, temp_max, humidity_min, humidity_max) VALUES (?, ?, ?, ?, ?, ?)");  // เช็คว่า room_id และ user_id มีอยู่ในฐานข้อมูลหรือไม่
        if (!$stmt) {  // เช็คว่าเตรียมคำสั่ง SQL สำเร็จหรือไม่
            throw new Exception('Database error: ' . $conn->error);  // ถ้าไม่สำเร็จให้แสดง error
        }
        
        $stmt->bind_param("iidddd", $room_id, $user_id, $temp_min, $temp_max, $humidity_min, $humidity_max);  // bind_param ใช้เพื่อป้องกัน SQL injections
    } else {  // ถ้ามีผลลัพธ์ให้ทำการอัพเดทข้อมูล
        // Update existing settings
        $stmt = $conn->prepare("UPDATE room_thresholds SET temp_min = ?, temp_max = ?, humidity_min = ?, humidity_max = ?, updated_at = NOW() WHERE room_id = ? AND user_id = ?");  // เช็คว่า room_id และ user_id มีอยู่ในฐานข้อมูลหรือไม่
        if (!$stmt) {   // เช็คว่าเตรียมคำสั่ง SQL สำเร็จหรือไม่
            throw new Exception('Database error: ' . $conn->error);  // ถ้าไม่สำเร็จให้แสดง error
        }
        
        $stmt->bind_param("ddddii", $temp_min, $temp_max, $humidity_min, $humidity_max, $room_id, $user_id);  // bind_param ใช้เพื่อป้องกัน SQL injections
    }

    if (!$stmt->execute()) {  // execute คำสั่ง SQL
        throw new Exception('Failed to save thresholds: ' . $stmt->error); // ถ้าไม่สำเร็จให้แสดง error
    }
    $stmt->close();  // ปิดการเชื่อมต่อ

    // Update discord settings if provided
    if (isset($data['discord_webhook']) || isset($data['discord_enabled'])) {   // เช็คว่ามี discord_webhook หรือ discord_enabled หรือไม่
        $discord_webhook = isset($data['discord_webhook']) ? $conn->real_escape_string(trim($data['discord_webhook'])) : '';  // หมายถึงการลบช่องว่างที่ไม่จำเป็นออกจาก discord_webhook
        $discord_enabled = isset($data['discord_enabled']) ? (bool)$data['discord_enabled'] : false;  // หมายถึงการแปลง discord_enabled เป็น boolean
        $discord_enabled_int = $discord_enabled ? 1 : 0;  // หมายถึงการแปลง discord_enabled เป็นจำนวนเต็ม
        
        // Check if user_settings table has a record for this user
        $user_check_stmt = $conn->prepare("SELECT id FROM user_settings WHERE user_id = ?");   // เช็คว่า user_id มีอยู่ในฐานข้อมูลหรือไม่
        if (!$user_check_stmt) {   // เช็คว่าเตรียมคำสั่ง SQL สำเร็จหรือไม่
            throw new Exception('Database error: ' . $conn->error);   // ถ้าไม่สำเร็จให้แสดง error
        }
        
        $user_check_stmt->bind_param("i", $user_id);    // bind_param ใช้เพื่อป้องกัน SQL injections
        $user_check_stmt->execute();  // execute คำสั่ง SQL
        $user_check_result = $user_check_stmt->get_result();   // get_result ใช้เพื่อดึงผลลัพธ์จาการ query
        $user_check_stmt->close();  // ปิดการเชื่อมต่อ

        if ($user_check_result->num_rows === 0) {   // เช็คว่ามีผลลัพธ์หรือไม่
            // No existing settings, insert new
            // Insert new user settings
            $discord_stmt = $conn->prepare("INSERT INTO user_settings (user_id, discord_webhook, discord_enabled) VALUES (?, ?, ?)"); // เช็คว่า user_id มีอยู่ในฐานข้อมูลหรือไม่
            if (!$discord_stmt) {  // เช็คว่าเตรียมคำสั่ง SQL สำเร็จหรือไม่
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $discord_stmt->bind_param("isi", $user_id, $discord_webhook, $discord_enabled_int);  // bind_param ใช้เพื่อป้องกัน SQL injections
        } else {
            // Update existing user settings
            $discord_stmt = $conn->prepare("UPDATE user_settings SET discord_webhook = ?, discord_enabled = ? WHERE user_id = ?");  // เช็คว่า user_id มีอยู่ในฐานข้อมูลหรือไม่
            if (!$discord_stmt) {  // เช็คว่าเตรียมคำสั่ง SQL สำเร็จหรือไม่
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $discord_stmt->bind_param("sii", $discord_webhook, $discord_enabled_int, $user_id);   // bind_param ใช้เพื่อป้องกัน SQL injections
        }

        if (!$discord_stmt->execute()) {   // execute คำสั่ง SQL
            // Log error but don't fail the whole operation
            error_log("Failed to update Discord settings: " . $discord_stmt->error);  // ถ้าไม่สำเร็จให้แสดง error
        }
        $discord_stmt->close();  // ปิดการเชื่อมต่อ
    }

    echo json_encode([   // ส่งผลลัพธ์กลับไปยัง client
        'success' => true,
        'message' => 'Room thresholds saved successfully',
        'thresholds' => [
            'temp_min' => $temp_min,
            'temp_max' => $temp_max,
            'humidity_min' => $humidity_min,
            'humidity_max' => $humidity_max
        ]
    ]);

} catch (Exception $e) {   // ถ้าเกิดข้อผิดพลาดให้แสดง error
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    // Log error
    error_log("Update room thresholds error: " . $e->getMessage());  // ถ้าไม่สำเร็จให้แสดง error
}

$conn->close();  // ปิดการเชื่อมต่อ
?>
