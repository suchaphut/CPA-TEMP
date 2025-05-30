<?php
// Include database connection
require_once 'db_connect.php';

// เช็์ท header ให้เป็น JSON
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {  // เช็คว่าเป็น POST หรือไม่
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);  // หมายถึงการแปลง JSON ที่ส่งมาจาก client เป็น array ใน PHP

// Validate required fields
if (!isset($data['room_id']) || !isset($data['room_name'])) {  // เช็คว่ามี room_id และ room_name หรือไม่
    echo json_encode([    // เช็คว่ามี room_id และ room_name หรือไม่
        'success' => false,  // ส่งผลลัพธ์กลับไปยัง client
        'message' => 'Room ID and name are required'  // ส่งผลลัพธ์กลับไปยัง client
    ]);
    exit;  //   ออกจาก script
}

$room_id = intval($data['room_id']);  // หมายถึงการแปลง room_id เป็นจำนวนเต็ม
$room_name = $conn->real_escape_string(trim($data['room_name']));  // หมายถึงการลบช่องว่างที่ไม่จำเป็นออกจาก room_name
$user_id = isset($data['user_id']) ? intval($data['user_id']) : null;  // เช็คว่ามี user_id หรือไม่

// Check if room name already exists (excluding the current room)  
$check_stmt = $conn->prepare("SELECT id FROM rooms WHERE room_name = ? AND id != ?");   // เช็คว่า room_name มีอยู่ในฐานข้อมูลหรือไม่
$check_stmt->bind_param("si", $room_name, $room_id);   // bind_param ใช้เพื่อป้องกัน SQL injection
$check_stmt->execute();  // execute คำสั่ง SQL
$check_result = $check_stmt->get_result();  // get_result ใช้เพื่อดึงผลลัพธ์จาการ query

if ($check_result->num_rows > 0) {   // เช็คว่ามีผลลัพธ์หรือไม่
    echo json_encode([ // เช็คว่ามีผลลัพธ์หรือไม่
        'success' => false,  // ส่งผลลัพธ์กลับไปยัง client
        'message' => 'Room name already exists' // ส่งผลลัพธ์กลับไปยัง client
    ]);
    $check_stmt->close();  // ปิดการเชื่อมต่อ
    exit; //    ออกจาก script
} 
$check_stmt->close();   // ปิดการเชื่อมต่อ

// Update room name
$stmt = $conn->prepare("UPDATE rooms SET room_name = ? WHERE id = ?");   // เช็คว่า room_name มีอยู่ในฐานข้อมูลหรือไม่
$stmt->bind_param("si", $room_name, $room_id);   // bind_param ใช้เพื่อป้องกัน SQL injection

if ($stmt->execute()) {   // execute คำสั่ง SQL
    echo json_encode([   // ส่งผลลัพธ์กลับไปยัง client
        'success' => true,   // ส่งผลลัพธ์กลับไปยัง client
        'message' => 'Room updated successfully',    // ส่งผลลัพธ์กลับไปยัง client
        'room' => [  // ส่งผลลัพธ์กลับไปยัง client
            'id' => $room_id,  // ส่งผลลัพธ์กลับไปยัง client
            'name' => $room_name   //   ส่งผลลัพธ์กลับไปยัง client
        ]
    ]);
} else {   //   เช็คว่ามีผลลัพธ์หรือไม่
    echo json_encode([   // เช็คว่ามีผลลัพธ์หรือไม่
        'success' => false,   // ส่งผลลัพธ์กลับไปยัง client
        'message' => 'Failed to update room: ' . $stmt->error  // ส่งผลลัพธ์กลับไปยัง client
    ]);
}

$stmt->close();   // ปิดการเชื่อมต่อ
$conn->close();   // ปิดการเชื่อมต่อ  
?>
