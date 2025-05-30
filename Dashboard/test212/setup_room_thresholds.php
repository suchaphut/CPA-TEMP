<?php
// เชื่อมต่อกับฐานข้อมูล
require_once 'db_connect.php';

// ตั้งค่า header สำหรับส่งข้อมูลกลับเป็น JSON
header('Content-Type: application/json');

// สร้างตาราง room_thresholds ถ้ายังไม่มี
// ตารางนี้ใช้เก็บค่าขีดจำกัดอุณหภูมิและความชื้นสำหรับแต่ละห้องของแต่ละผู้ใช้
// - temp_min/max: ค่าต่ำสุด/สูงสุดของอุณหภูมิ (ค่าเริ่มต้น 24-25 องศา)
// - humidity_min/max: ค่าต่ำสุด/สูงสุดของความชื้น (ค่าเริ่มต้น 50-60 %)
$create_table_sql = "
CREATE TABLE IF NOT EXISTS room_thresholds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    temp_min FLOAT DEFAULT 24,
    temp_max FLOAT DEFAULT 25,
    humidity_min FLOAT DEFAULT 50,
    humidity_max FLOAT DEFAULT 60,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY room_user_unique (room_id, user_id)
)";

if ($conn->query($create_table_sql) === TRUE) {
    // ตรวจสอบว่าต้องใส่ค่าเริ่มต้นหรือไม่ โดยนับจำนวนข้อมูลในตาราง
    $check_sql = "SELECT COUNT(*) as count FROM room_thresholds";
    $result = $conn->query($check_sql);
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // ถ้ายังไม่มีข้อมูล ให้ใส่ค่าเริ่มต้นสำหรับทุกห้องและทุกผู้ใช้
        // โดยดึงค่าจากตาราง user_settings ถ้ามี ถ้าไม่มีให้ใช้ค่าเริ่มต้น
        // COALESCE จะเลือกค่าแรกที่ไม่เป็น NULL ถ้าไม่มีจะใช้ค่าที่กำหนด
        $insert_sql = "
        INSERT INTO room_thresholds (room_id, user_id, temp_min, temp_max, humidity_min, humidity_max)
        SELECT r.id, u.id, 
               COALESCE(us.temp_min, 24), 
               COALESCE(us.temp_max, 25), 
               COALESCE(us.humidity_min, 50), 
               COALESCE(us.humidity_max, 60)
        FROM rooms r
        CROSS JOIN users u
        LEFT JOIN user_settings us ON u.id = us.user_id
        ";
        
        if ($conn->query($insert_sql) === TRUE) {
            // แจ้งผลว่าสร้างตารางและใส่ข้อมูลสำเร็จ
            echo json_encode([
                'success' => true,
                'message' => 'Room thresholds table created and default values inserted successfully'
            ]);
        } else {
            // แจ้งข้อผิดพลาดกรณีใส่ข้อมูลไม่สำเร็จ
            echo json_encode([
                'success' => false,
                'message' => 'Room thresholds table created but failed to insert default values: ' . $conn->error
            ]);
        }
    } else {
        // แจ้งว่ามีข้อมูลในตารางอยู่แล้ว
        echo json_encode([
            'success' => true,
            'message' => 'Room thresholds table already exists with data'
        ]);
    }
} else {
    // แจ้งข้อผิดพลาดกรณีสร้างตารางไม่สำเร็จ
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create room thresholds table: ' . $conn->error
    ]);
}

// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>
