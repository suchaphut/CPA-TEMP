<?php
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once 'db_connect.php';

// กำหนด header เพื่อระบุว่าข้อมูลที่ส่งกลับเป็น JSON
header('Content-Type: application/json');

// ตรวจสอบว่าเป็นการส่งข้อมูลด้วยวิธี POST เท่านั้น
// ถ้าไม่ใช่จะส่งข้อความแจ้งเตือนกลับไป
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

    // ตรวจสอบว่ามีข้อมูลจำเป็นครบถ้วนหรือไม่ (username, password, email)
    if (!isset($data['username']) || !isset($data['password']) || !isset($data['email'])) {
        throw new Exception('Missing required fields');
    }

    // ทำความสะอาดข้อมูลและป้องกัน SQL injection
    $username = $conn->real_escape_string(trim($data['username']));
    $email = $conn->real_escape_string(trim($data['email']));
    $password = $data['password'];

    // ตรวจสอบความยาวของ username (ต้องอยู่ระหว่าง 3-50 ตัวอักษร)
    if (strlen($username) < 3 || strlen($username) > 50) {
        throw new Exception('Username must be between 3 and 50 characters');
    }

    // ตรวจสอบความยาวของรหัสผ่าน (ต้องมีอย่างน้อย 6 ตัวอักษร)
    if (strlen($password) < 6) {
        throw new Exception('Password must be at least 6 characters');
    }

    // ตรวจสอบรูปแบบอีเมลว่าถูกต้องหรือไม่
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // ตรวจสอบว่ามี username นี้ในระบบแล้วหรือไม่
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        throw new Exception('Username already exists');
    }
    $stmt->close();

    // ตรวจสอบว่ามีอีเมลนี้ในระบบแล้วหรือไม่
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        throw new Exception('Email already exists');
    }
    $stmt->close();

    // เข้ารหัสรหัสผ่านด้วย PHP built-in function
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // เพิ่มข้อมูลผู้ใช้ใหม่ลงในฐานข้อมูล
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("sss", $username, $email, $hashed_password);

    if (!$stmt->execute()) {
        throw new Exception('Registration failed: ' . $stmt->error);
    }
    
    // เก็บ ID ของผู้ใช้ที่เพิ่งสร้าง
    $user_id = $stmt->insert_id;
    $stmt->close();
    
    // กำหนดค่าเริ่มต้นสำหรับการตั้งค่าของผู้ใช้ใหม่
    $temp_min = 24;      // อุณหภูมิต่ำสุด (องศาเซลเซียส)
    $temp_max = 25;      // อุณหภูมิสูงสุด (องศาเซลเซียส)
    $humidity_min = 50;  // ความชื้นต่ำสุด (เปอร์เซ็นต์)
    $humidity_max = 60;  // ความชื้นสูงสุด (เปอร์เซ็นต์)
    
    // เพิ่มค่าเริ่มต้นของการตั้งค่าสำหรับผู้ใช้ใหม่
    $settings_stmt = $conn->prepare("INSERT INTO user_settings (user_id, temp_min, temp_max, humidity_min, humidity_max) VALUES (?, ?, ?, ?, ?)");
    if (!$settings_stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $settings_stmt->bind_param("idddd", $user_id, $temp_min, $temp_max, $humidity_min, $humidity_max);
    
    if (!$settings_stmt->execute()) {
        // บันทึกข้อผิดพลาดแต่ไม่ยกเลิกการลงทะเบียน
        error_log("Failed to create default settings: " . $settings_stmt->error);
    }
    $settings_stmt->close();
    
    // ส่งข้อมูลตอบกลับเมื่อลงทะเบียนสำเร็จ
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful',
        'user_id' => $user_id,
        'username' => $username
    ]);

} catch (Exception $e) {
    // ส่งข้อความผิดพลาดกลับไปหากเกิดข้อผิดพลาด
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    // บันทึกข้อผิดพลาดลงในล็อก
    error_log("Registration error: " . $e->getMessage());
}

// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>
