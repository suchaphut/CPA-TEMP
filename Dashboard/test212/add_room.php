<?php
// Include database connection
require_once 'db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');  // กำหนด header ให้เป็น JSON

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {   // เช็คว่าเป็น POST หรือไม่
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true); // Decode JSON input

// Validate required fields
if (!isset($data['room_name'])) {   // เช็คว่ามี room_name หรือไม่
    echo json_encode([   
        'success' => false,
        'message' => 'Room name is required'
    ]);
    exit;
}

$room_name = $conn->real_escape_string(trim($data['room_name']));  // หมายถึงการลบช่องว่างที่ไม่จำเป็นออกจาก room_name
$user_id = isset($data['user_id']) ? intval($data['user_id']) : null;  // เช็คว่ามี user_id  หรือไม่

// Check if room name already exists
$check_stmt = $conn->prepare("SELECT id FROM rooms WHERE room_name = ?");  // เช็คว่า room_name มีอยู่ในฐานข้อมูลหรือไม่
$check_stmt->bind_param("s", $room_name);   // bind_param ใช้เพื่อป้องกัน SQL injection
$check_stmt->execute();  // execute คำสั่ง SQL
$check_result = $check_stmt->get_result(); // get_result ใช้เพื่อดึงผลลัพธ์จาการ query

if ($check_result->num_rows > 0) {   // เช็คว่ามีผลลัพธ์หรือไม่
    echo json_encode([    // เช็คว่ามีผลลัพธ์หรือไม่
        'success' => false,
        'message' => 'Room name already exists'
    ]);
    $check_stmt->close();  // ปิดการเชื่อมต่อ
    exit;
}
$check_stmt->close();   // ปิดการเชื่อมต่อ

// Insert new room
if ($user_id) {   // เช็คว่ามี user_id หรือไม่
    $stmt = $conn->prepare("INSERT INTO rooms (room_name, user_id, created_at) VALUES (?, ?, NOW())");  // ถ้ามี user_id ให้เพิ่ม user_id ลงในฐานข้อมูล
    $stmt->bind_param("si", $room_name, $user_id);  // bind_param ใช้เพื่อป้องกัน SQL injection
} else {  // ถ้าไม่มี user_id ให้เพิ่ม room_name ลงในฐานข้อมูล
    $stmt = $conn->prepare("INSERT INTO rooms (room_name, created_at) VALUES (?, NOW())");  // ถ้าไม่มี user_id ให้เพิ่ม room_name ลงในฐานข้อมูล
    $stmt->bind_param("s", $room_name); // bind_param ใช้เพื่อป้องกัน SQL injection
}

if ($stmt->execute()) {   // execute คำสั่ง SQL
    $room_id = $stmt->insert_id;   // ดึง id ของห้องที่เพิ่งเพิ่มเข้าไปในฐานข้อมูล
    echo json_encode([  // ส่งผลลัพธ์กลับไปยัง client
        'success' => true,  // ส่งผลลัพธ์กลับไปยัง client
        'message' => 'Room added successfully', // ส่งผลลัพธ์กลับไปยัง client
        'room' => [  // ส่งผลลัพธ์กลับไปยัง client
            'id' => $room_id,   //  ส่ง id ของห้องที่เพิ่งเพิ่มเข้าไปในฐานข้อมูล
            'name' => $room_name // ส่ง room_name ที่เพิ่งเพิ่มเข้าไปในฐานข้อมูล
        ]
    ]);
} else {     // ถ้าไม่สามารถเพิ่มห้องได้
    echo json_encode([   // ส่งผลลัพธ์กลับไปยัง client
        'success' => false,  // ส่งผลลัพธ์กลับไปยัง client
        'message' => 'Failed to add room: ' . $stmt->error  // ส่งผลลัพธ์กลับไปยัง client
    ]);
}

$stmt->close();   // ปิดการเชื่อมต่อ
$conn->close();   // ปิดการเชื่อมต่อ
// End of script
?>
