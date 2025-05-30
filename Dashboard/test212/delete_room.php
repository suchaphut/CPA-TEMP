<?php
// Include database connection
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once 'db_connect.php';

// Set headers for JSON response
// กำหนดส่วนหัวของการตอบกลับเป็นรูปแบบ JSON
header('Content-Type: application/json');

// Check if request method is POST
// ตรวจสอบว่าเป็นการเรียกใช้ด้วยเมธอด POST หรือไม่
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get POST data
// รับข้อมูลที่ส่งมาในรูปแบบ JSON และแปลงเป็น PHP array
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
// ตรวจสอบว่ามีการส่ง room_id มาหรือไม่
if (!isset($data['room_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Room ID is required'
    ]);
    exit;
}

// แปลงค่า room_id เป็นตัวเลขและเตรียมค่า user_id (ถ้ามี)
$room_id = intval($data['room_id']);
$user_id = isset($data['user_id']) ? intval($data['user_id']) : null;

// Delete room
// เตรียมคำสั่ง SQL สำหรับลบข้อมูลห้องจากฐานข้อมูล
$stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
$stmt->bind_param("i", $room_id);

// ทำการลบข้อมูลและตรวจสอบผลลัพธ์
if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Room deleted successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete room: ' . $stmt->error
    ]);
}

// ปิดการเชื่อมต่อ statement และฐานข้อมูล
$stmt->close();
$conn->close();
?>
