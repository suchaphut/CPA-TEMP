<?php
/**
 * สคริปต์สำหรับบันทึกการตั้งค่าขีดจำกัดของแต่ละห้อง
 * - บันทึกค่าขีดจำกัดอุณหภูมิและความชื้นสำหรับแต่ละห้อง
 * - รองรับการตั้งค่า Discord Webhook สำหรับการแจ้งเตือน
 * - สามารถเพิ่มใหม่หรืออัปเดตข้อมูลเดิมได้
 */

// เชื่อมต่อกับฐานข้อมูล
require_once 'db_connect.php';

// ตั้งค่า header สำหรับส่งข้อมูลกลับเป็น JSON
header('Content-Type: application/json');

// ตรวจสอบว่าเป็นการส่งข้อมูลแบบ POST เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// รับและแปลงข้อมูล JSON ที่ส่งมา
$data = json_decode(file_get_contents('php://input'), true);

// ตรวจสอบว่ามีข้อมูลที่จำเป็นครบถ้วน
// - user_id: รหัสผู้ใช้
// - room_id: รหัสห้อง
// - temp_min/max: ค่าอุณหภูมิต่ำสุด/สูงสุด
// - humidity_min/max: ค่าความชื้นต่ำสุด/สูงสุด
if (!isset($data['user_id']) || !isset($data['room_id']) || 
    !isset($data['temp_min']) || !isset($data['temp_max']) || 
    !isset($data['humidity_min']) || !isset($data['humidity_max'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

$user_id = intval($data['user_id']);   // แปลง user_id เป็นจำนวนเต็ม
$room_id = intval($data['room_id']);   // แปลง room_id เป็นจำนวนเต็ม
$temp_min = floatval($data['temp_min']);  // แปลง temp_min เป็นจำนวนทศนิยม
$temp_max = floatval($data['temp_max']); // แปลง temp_max เป็นจำนวนทศนิยม
$humidity_min = floatval($data['humidity_min']);  // แปลง humidity_min เป็นจำนวนทศนิยม
$humidity_max = floatval($data['humidity_max']); // แปลง humidity_max เป็นจำนวนทศนิยม
$discord_webhook = isset($data['discord_webhook']) ? $conn->real_escape_string(trim($data['discord_webhook'])) : '';  // แปลง discord_webhook เป็น string
$discord_enabled = isset($data['discord_enabled']) ? (bool)$data['discord_enabled'] : false;  // แปลง discord_enabled เป็น boolean

// Validate ranges
// ค่าต่ำสุดต้องน้อยกว่าค่าสูงสุดเสมอ
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

// เช็คว่ามีการตั้งค่าขีดจำกัดสำหรับห้องนี้อยู่แล้วหรือไม่
$check_stmt = $conn->prepare("SELECT id FROM room_thresholds WHERE room_id = ? AND user_id = ?");
$check_stmt->bind_param("ii", $room_id, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    // Insert new settings
    $stmt = $conn->prepare("INSERT INTO room_thresholds (room_id, user_id, temp_min, temp_max, humidity_min, humidity_max) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iidddd", $room_id, $user_id, $temp_min, $temp_max, $humidity_min, $humidity_max);
} else {
    // Update existing settings
    $stmt = $conn->prepare("UPDATE room_thresholds SET temp_min = ?, temp_max = ?, humidity_min = ?, humidity_max = ? WHERE room_id = ? AND user_id = ?");
    $stmt->bind_param("ddddii", $temp_min, $temp_max, $humidity_min, $humidity_max, $room_id, $user_id);
}

// เช็คว่ามีการตั้งค่าผู้ใช้ในฐานข้อมูลหรือไม่
// ถ้าไม่มีให้เพิ่มการตั้งค่าใหม่
// ถ้ามีให้ทำการอัปเดตการตั้งค่าเดิม
$user_check_stmt = $conn->prepare("SELECT id FROM user_settings WHERE user_id = ?");
$user_check_stmt->bind_param("i", $user_id);
$user_check_stmt->execute();
$user_check_result = $user_check_stmt->get_result();

if ($user_check_result->num_rows === 0) {
    // Insert new user settings
    $discord_stmt = $conn->prepare("INSERT INTO user_settings (user_id, discord_webhook, discord_enabled) VALUES (?, ?, ?)");
    $discord_enabled_int = $discord_enabled ? 1 : 0;
    $discord_stmt->bind_param("isi", $user_id, $discord_webhook, $discord_enabled_int);
} else {
    // Update existing user settings
    $discord_stmt = $conn->prepare("UPDATE user_settings SET discord_webhook = ?, discord_enabled = ? WHERE user_id = ?");
    $discord_enabled_int = $discord_enabled ? 1 : 0;
    $discord_stmt->bind_param("sii", $discord_webhook, $discord_enabled_int, $user_id);
}
  
// Execute the statement to update or insert user settings
$discord_stmt->execute();
$discord_stmt->close();
$user_check_stmt->close();

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Room thresholds saved successfully',
        'thresholds' => [
            'temp_min' => $temp_min,
            'temp_max' => $temp_max,
            'humidity_min' => $humidity_min,
            'humidity_max' => $humidity_max
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save thresholds: ' . $stmt->error
    ]);
}

$check_stmt->close();
$stmt->close();
$conn->close();
?>
