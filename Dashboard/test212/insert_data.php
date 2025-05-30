<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$servername = "172.18.2.2";
$username = "cpatmp";
$password = "Cpa10665";
$dbname = "cpa-tmpdb"; // ชื่อฐานข้อมูลที่ใช้เก็บข้อมูลเซนเซอร์

try {
    // สร้างการเชื่อมต่อ
    $conn = new mysqli($servername, $username, $password, $dbname);  // สร้างการเชื่อมต่อฐานข้อมูล

    // ตรวจสอบการเชื่อมต่อ
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // รับค่าจาก GET parameter
    $temperature = isset($_GET['temperature']) ? floatval($_GET['temperature']) : null;
    $humidity = isset($_GET['humidity']) ? floatval($_GET['humidity']) : null;
    $room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : null;

    // ตรวจสอบข้อมูล
    if ($temperature === null || $humidity === null) {
        throw new Exception("Missing or invalid temperature/humidity parameters.");
    }

    // Validate temperature and humidity ranges
    if ($temperature < -40 || $temperature > 80) {
        throw new Exception("Temperature out of valid range (-40 to 80°C).");
    }

    if ($humidity < 0 || $humidity > 100) {
        throw new Exception("Humidity out of valid range (0 to 100%).");
    }

    // Default to Room1 if no room_id provided
    if ($room_id === null) {
        $room_id = 1; // Assuming Room1 has ID 1
    }

    // ตรวจสอบว่า room_id มีอยู่จริงไหม
    $room_stmt = $conn->prepare("SELECT room_name FROM rooms WHERE id = ?");
    if (!$room_stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $room_stmt->bind_param("i", $room_id);
    $room_stmt->execute();
    $room_result = $room_stmt->get_result();

    if ($room_result->num_rows === 0) {
        // If room doesn't exist, try to get Room1
        $room_stmt->close();
        $room_stmt = $conn->prepare("SELECT room_name FROM rooms WHERE room_name = 'Room1'");
        $room_stmt->execute();
        $room_result = $room_stmt->get_result();
        
        if ($room_result->num_rows === 0) {
            // If Room1 doesn't exist, create it
            $conn->query("INSERT INTO rooms (room_name) VALUES ('Room1')");
            $room_name = "Room1";
        } else {
            $room_row = $room_result->fetch_assoc();
            $room_name = $room_row['room_name'];
        }
    } else {
        $room_row = $room_result->fetch_assoc();
        $room_name = $room_row['room_name'];
    }
    $room_stmt->close();

    // บันทึกข้อมูลเซ็นเซอร์
    $insert_stmt = $conn->prepare("INSERT INTO sensor_data (temperature, humidity, room, datetime) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
    if (!$insert_stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $insert_stmt->bind_param("dds", $temperature, $humidity, $room_name);

    if (!$insert_stmt->execute()) {
        throw new Exception("Insert error: " . $insert_stmt->error);
    }
    
    $insert_stmt->close();
    $conn->close();

    // Return success response
    echo "✅ Data inserted successfully for room: " . htmlspecialchars($room_name);
    
} catch (Exception $e) {
    // Log error
    error_log("insert_data.php error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo "❌ Error: " . $e->getMessage();
}
?>
