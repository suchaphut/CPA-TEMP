<?php
// Include database connection
require_once 'db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');  // เช็ท header ให้เป็น JSON

// Check if room_thresholds table exists
$check_table_sql = "SHOW TABLES LIKE 'room_thresholds'";   // เช็คว่า room_thresholds มีอยู่ในฐานจข้อมูลหรือไม่
$result = $conn->query($check_table_sql); // execute คำสั่ง SQL
$table_exists = $result->num_rows > 0; // เช็คว่ามีผลลัพธ์หรือไม่

// Get error logs
$error_log = error_get_last();   // ดึง error log ล่าสุด

// Check database connection
$db_connection_status = $conn->connect_error ? "Error: " . $conn->connect_error : "Connected successfully";   // เช็คการเชื่อมต่อกับฐานข้อมูล

// Check PHP version
$php_version = phpversion();  // ดึงเวอร์ชั่น PHP ปัจจุบัน

// Output diagnostic information
echo json_encode([    // ส่งผลลัพธ์กลับไปยัง client
    'success' => true,  // ส่งผลลัพธ์กลับไปยัง client
    'database_connection' => $db_connection_status,   // ส่งผลลัพธ์กลับไปยัง client
    'room_thresholds_table_exists' => $table_exists, // ส่งผลลัพธ์กลับไปยัง client
    'php_version' => $php_version,  // ส่งผลลัพธ์กลับไปยัง client
    'last_error' => $error_log,  // ส่งผลลัพธ์กลับไปยัง client
    'message' => 'Database diagnostic completed' // ส่งผลลัพธ์กลับไปยัง client
]);

$conn->close(); // ปิดการเชื่อมต่อ
?>
