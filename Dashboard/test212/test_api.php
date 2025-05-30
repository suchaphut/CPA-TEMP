<?php
header('Content-Type: application/json');

// เพิ่มการแสดงข้อผิดพลาดอย่างละเอียด
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ส่งข้อมูลทดสอบกลับเป็น JSON
echo json_encode([
    'success' => true,
    'message' => 'API test successful',
    'timestamp' => date('Y-m-d H:i:s'),
    'test_data' => [
        'temperature' => 25.5,
        'humidity' => 60.2,
        'room' => 'TestRoom'
    ],
    'php_version' => phpversion(),
    'server_info' => $_SERVER['SERVER_SOFTWARE']
]);
?>
