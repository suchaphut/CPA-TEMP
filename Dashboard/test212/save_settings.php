<?php
/**
 * สคริปต์สำหรับบันทึกการตั้งค่าของผู้ใช้
 * - บันทึกค่าขีดจำกัดอุณหภูมิและความชื้น
 * - รองรับทั้งการเพิ่มใหม่และอัปเดตข้อมูลเดิม
 */

// เชื่อมต่อกับฐานข้อมูล
require_once 'db_connect.php';

// ตั้งค่า header สำหรับส่งข้อมูลกลับเป็น JSON
header('Content-Type: application/json');

// ตรวจสอบว่าเป็นการส่งข้อมูลแบบ POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// รับข้อมูล JSON ที่ส่งมา
$data = json_decode(file_get_contents('php://input'), true);

// ตรวจสอบว่ามีข้อมูลที่จำเป็นครบถ้วน
if (!isset($data['user_id']) || !isset($data['temp_min']) || !isset($data['temp_max']) || 
    !isset($data['humidity_min']) || !isset($data['humidity_max'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

// แปลงค่าที่รับมาเป็นตัวเลข
$user_id = intval($data['user_id']);
$temp_min = floatval($data['temp_min']);
$temp_max = floatval($data['temp_max']);
$humidity_min = floatval($data['humidity_min']);
$humidity_max = floatval($data['humidity_max']);

// ตรวจสอบความถูกต้องของค่าขีดจำกัด
if ($temp_min >= $temp_max) {
    echo json_encode([
        'success' => false,
        'message' => 'Temperature minimum must be less than maximum'
    ]);
    exit;
}

if ($humidity_min >= $humidity_max) {
    echo json_encode([
        'success' => false,
        'message' => 'Humidity minimum must be less than maximum'
    ]);
    exit;
}

// ตรวจสอบว่ามีการตั้งค่าสำหรับผู้ใช้รายนี้อยู่แล้วหรือไม่
$check_stmt = $conn->prepare("SELECT id FROM user_settings WHERE user_id = ?"); // เช็คว่ามีการตั้งค่าอยู่ในฐานข้อมูลหรือไม่
$check_stmt->bind_param("i", $user_id); // bind_param ใช้เพื่อป้องกัน SQL injection
$check_stmt->execute(); // execute คำสั่ง SQL
$check_result = $check_stmt->get_result();  // get_result ใช้เพื่อดึงผลลัพธ์จากการ query

if ($check_result->num_rows === 0) {
    // ถ้ายังไม่มีการตั้งค่า ให้ทำการเพิ่มการตั้งค่าใหม่
    $stmt = $conn->prepare("INSERT INTO user_settings (user_id, temp_min, temp_max, humidity_min, humidity_max) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("idddd", $user_id, $temp_min, $temp_max, $humidity_min, $humidity_max);
} else {
    // ถ้ามีการตั้งค่าอยู่แล้ว ให้ทำการอัปเดตการตั้งค่าเดิม
    $stmt = $conn->prepare("UPDATE user_settings SET temp_min = ?, temp_max = ?, humidity_min = ?, humidity_max = ? WHERE user_id = ?");
    $stmt->bind_param("ddddi", $temp_min, $temp_max, $humidity_min, $humidity_max, $user_id);
}

// บันทึกการตั้งค่าลงฐานข้อมูล
if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Settings saved successfully',
        'settings' => [
            'temp_min' => $temp_min,
            'temp_max' => $temp_max,
            'humidity_min' => $humidity_min,
            'humidity_max' => $humidity_max
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save settings: ' . $stmt->error
    ]);
}

// ปิดการเชื่อมต่อฐานข้อมูล
$check_stmt->close();
$stmt->close();
$conn->close();
?>
