<?php
// เพิ่มการแสดงข้อผิดพลาดอย่างละเอียด
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "esp8266_dashboard";

header('Content-Type: application/json');

try {
    // สร้างการเชื่อมต่อ
    $conn = new mysqli($servername, $username, $password);

    // ตรวจสอบการเชื่อมต่อ
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // ตรวจสอบว่าฐานข้อมูลมีอยู่หรือไม่
    $db_exists = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
    $db_exists = $db_exists->num_rows > 0;
    
    if (!$db_exists) {
        // สร้างฐานข้อมูลถ้าไม่มี
        if (!$conn->query("CREATE DATABASE IF NOT EXISTS $dbname")) {
            throw new Exception("Error creating database: " . $conn->error);
        }
        $db_created = true;
    } else {
        $db_created = false;
    }
    
    // เลือกฐานข้อมูล
    $conn->select_db($dbname);
    
    // ตรวจสอบตาราง
    $tables = [];
    $tables_result = $conn->query("SHOW TABLES");
    while ($row = $tables_result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    // ตรวจสอบข้อมูลในตาราง sensor_data
    $data_count = 0;
    if (in_array('sensor_data', $tables)) {
        $result = $conn->query("SELECT COUNT(*) as count FROM sensor_data");
        if ($result) {
            $row = $result->fetch_assoc();
            $data_count = $row['count'];
        }
    }
    
    // ตรวจสอบข้อมูลในตาราง rooms
    $rooms_count = 0;
    if (in_array('rooms', $tables)) {
        $result = $conn->query("SELECT COUNT(*) as count FROM rooms");
        if ($result) {
            $row = $result->fetch_assoc();
            $rooms_count = $row['count'];
        }
    }
    
    // ส่งข้อมูลกลับเป็น JSON
    echo json_encode([
        'success' => true,
        'message' => 'Connection successful',
        'database' => [
            'name' => $dbname,
            'exists' => $db_exists,
            'created' => $db_created
        ],
        'tables' => $tables,
        'data_counts' => [
            'sensor_data' => $data_count,
            'rooms' => $rooms_count
        ],
        'php_version' => phpversion(),
        'server_info' => $conn->server_info,
        'mysql_error' => $conn->error,
        'mysql_errno' => $conn->errno
    ]);
    
} catch (Exception $e) {
    // ส่งข้อผิดพลาดกลับเป็น JSON
    echo json_encode([
        'success' => false,
        'message' => 'Connection failed: ' . $e->getMessage(),
        'php_version' => phpversion(),
        'error_details' => [
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>
