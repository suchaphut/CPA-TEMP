<?php
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once 'db_connect.php';

// กำหนด header เพื่อระบุว่าข้อมูลที่ส่งกลับเป็น JSON
header('Content-Type: application/json');

// ตรวจสอบว่าเป็นการส่งข้อมูลด้วยวิธี POST เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

try {
    // รับข้อมูล JSON ที่ส่งมาจาก client และแปลงเป็น array
    $data = json_decode(file_get_contents('php://input'), true);
    
    // ตรวจสอบว่าข้อมูล JSON ถูกต้องหรือไม่
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }

    // ตรวจสอบว่ามีข้อมูลจำเป็นครบถ้วนหรือไม่ (username และ password)
    if (!isset($data['username']) || !isset($data['password'])) {
        throw new Exception('Missing required fields');
    }

    // ทำความสะอาดข้อมูลและป้องกัน SQL injection
    $username = $conn->real_escape_string(trim($data['username']));
    $password = $data['password'];

    // ค้นหาข้อมูลผู้ใช้จากฐานข้อมูลโดยใช้ username
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    // ตรวจสอบว่าพบผู้ใช้หรือไม่
    if ($result->num_rows === 0) {
        throw new Exception('Invalid username or password');
    }

    // ดึงข้อมูลผู้ใช้
    $user = $result->fetch_assoc();
    $stmt->close();

    // ตรวจสอบรหัสผ่านว่าถูกต้องหรือไม่
    if (!password_verify($password, $user['password'])) {
        throw new Exception('Invalid username or password');
    }

    // ดึงการตั้งค่าของผู้ใช้จากฐานข้อมูล
    $settings_stmt = $conn->prepare("SELECT temp_min, temp_max, humidity_min, humidity_max, discord_webhook, discord_enabled FROM user_settings WHERE user_id = ?");
    if (!$settings_stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $settings_stmt->bind_param("i", $user['id']);
    $settings_stmt->execute();
    $settings_result = $settings_stmt->get_result();
    
    $settings = [];
    if ($settings_result->num_rows > 0) {
        // ถ้ามีการตั้งค่าอยู่แล้ว ให้ดึงข้อมูลมาใช้
        $settings = $settings_result->fetch_assoc();
    } else {
        // ถ้ายังไม่มีการตั้งค่า ให้สร้างค่าเริ่มต้น
        $temp_min = 24;      // อุณหภูมิต่ำสุด (องศาเซลเซียส)
        $temp_max = 25;      // อุณหภูมิสูงสุด (องศาเซลเซียส)
        $humidity_min = 50;  // ความชื้นต่ำสุด (เปอร์เซ็นต์)
        $humidity_max = 60;  // ความชื้นสูงสุด (เปอร์เซ็นต์)
        
        // เพิ่มค่าเริ่มต้นลงในฐานข้อมูล
        $insert_stmt = $conn->prepare("INSERT INTO user_settings (user_id, temp_min, temp_max, humidity_min, humidity_max) VALUES (?, ?, ?, ?, ?)");
        if (!$insert_stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $insert_stmt->bind_param("idddd", $user['id'], $temp_min, $temp_max, $humidity_min, $humidity_max);
        $insert_stmt->execute();
        $insert_stmt->close();
        
        // กำหนดค่าเริ่มต้นสำหรับส่งกลับไปยัง client
        $settings = [
            'temp_min' => $temp_min,
            'temp_max' => $temp_max,
            'humidity_min' => $humidity_min,
            'humidity_max' => $humidity_max,
            'discord_webhook' => null,
            'discord_enabled' => 0
        ];
    }
    $settings_stmt->close();
    
    // อัพเดทเวลาเข้าสู่ระบบล่าสุด
    $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    if (!$update_stmt) {
        // บันทึกข้อผิดพลาดแต่ไม่ยกเลิกการเข้าสู่ระบบ
        error_log("Failed to update last login: " . $conn->error);
    } else {
        $update_stmt->bind_param("i", $user['id']);
        $update_stmt->execute();
        $update_stmt->close();
    }
    
    // ส่งข้อมูลตอบกลับเมื่อเข้าสู่ระบบสำเร็จ
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user_id' => $user['id'],
        'username' => $user['username'],
        'settings' => $settings
    ]);

} catch (Exception $e) {
    // ส่งข้อความผิดพลาดกลับไปหากเกิดข้อผิดพลาด
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    // บันทึกข้อผิดพลาดลงในล็อก
    error_log("Login error: " . $e->getMessage());
}

// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>
