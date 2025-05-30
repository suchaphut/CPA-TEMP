<?php
// เปิดการแสดงข้อผิดพลาดทั้งหมดเพื่อช่วยในการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

// กำหนดค่าการเชื่อมต่อฐานข้อมูล MySQL
$servername = "localhost";  // ที่อยู่เซิร์ฟเวอร์ฐานข้อมูล
$username = "root";        // ชื่อผู้ใช้ฐานข้อมูล
$password = "";           // รหัสผ่านฐานข้อมูล (ว่างสำหรับ XAMPP/LARAGON)
$dbname = "esp8266_dashboard";  // ชื่อฐานข้อมูลที่จะใช้

// กำหนด header เพื่อแสดงผลภาษาไทยได้ถูกต้อง
header('Content-Type: text/html; charset=utf-8');

try {
    // สร้างการเชื่อมต่อกับ MySQL Server
    $conn = new mysqli($servername, $username, $password);

    // ตรวจสอบว่าเชื่อมต่อสำเร็จหรือไม่
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>เพิ่มข้อมูลตัวอย่างสำหรับ ESP8266 Dashboard</h2>";
    
    // ตรวจสอบว่ามีฐานข้อมูลอยู่แล้วหรือไม่
    $db_exists = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
    if ($db_exists->num_rows === 0) {
        // สร้างฐานข้อมูลใหม่ถ้ายังไม่มี
        if (!$conn->query("CREATE DATABASE IF NOT EXISTS $dbname")) {
            throw new Exception("Error creating database: " . $conn->error);
        }
        echo "<p>✅ สร้างฐานข้อมูล $dbname สำเร็จ</p>";
    } else {
        echo "<p>✅ ฐานข้อมูล $dbname มีอยู่แล้ว</p>";
    }
    
    // เลือกฐานข้อมูลที่จะทำงานด้วย
    $conn->select_db($dbname);
    
    // สร้างตาราง rooms สำหรับเก็บข้อมูลห้องต่างๆ
    // - id: รหัสห้อง (สร้างอัตโนมัติ)
    // - room_name: ชื่อห้อง (ห้ามซ้ำ)
    // - user_id: รหัสผู้ใช้ที่เป็นเจ้าของห้อง (ถ้ามี)
    // - created_at: วันเวลาที่สร้างห้อง
    $create_rooms = "CREATE TABLE IF NOT EXISTS rooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_name VARCHAR(50) NOT NULL UNIQUE,
        user_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($create_rooms)) {
        echo "<p>✅ สร้างตาราง rooms สำเร็จหรือมีอยู่แล้ว</p>";
    } else {
        throw new Exception("Error creating rooms table: " . $conn->error);
    }
    
    // สร้างตาราง sensor_data สำหรับเก็บข้อมูลจากเซ็นเซอร์
    // - id: รหัสข้อมูล (สร้างอัตโนมัติ)
    // - temperature: อุณหภูมิ (องศาเซลเซียส)
    // - humidity: ความชื้น (เปอร์เซ็นต์)
    // - room: ชื่อห้องที่เก็บข้อมูล
    // - datetime: วันเวลาที่บันทึกข้อมูล
    $create_sensor_data = "CREATE TABLE IF NOT EXISTS sensor_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        temperature FLOAT NOT NULL,
        humidity FLOAT NOT NULL,
        room VARCHAR(50) NOT NULL,
        datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($create_sensor_data)) {
        echo "<p>✅ สร้างตาราง sensor_data สำเร็จหรือมีอยู่แล้ว</p>";
    } else {
        throw new Exception("Error creating sensor_data table: " . $conn->error);
    }
    
    // เพิ่มข้อมูลห้องเริ่มต้น 3 ห้อง
    // ใช้ ON DUPLICATE KEY UPDATE เพื่อป้องกันการเพิ่มข้อมูลซ้ำ
    $insert_rooms = "INSERT INTO rooms (room_name) VALUES 
        ('Room1'),
        ('Room2'),
        ('Room3')
        ON DUPLICATE KEY UPDATE room_name = VALUES(room_name)";
    
    if ($conn->query($insert_rooms)) {
        echo "<p>✅ เพิ่มห้องเริ่มต้นสำเร็จหรือมีอยู่แล้ว</p>";
    } else {
        throw new Exception("Error inserting default rooms: " . $conn->error);
    }
    
    // ตรวจสอบจำนวนข้อมูลในตาราง sensor_data
    $result = $conn->query("SELECT COUNT(*) as count FROM sensor_data");
    $row = $result->fetch_assoc();
    
    // ถ้ามีข้อมูลน้อยกว่า 20 รายการ จะทำการเพิ่มข้อมูลตัวอย่าง
    if ($row['count'] < 20) {
        // ล้างข้อมูลเดิมทั้งหมดก่อน
        $conn->query("TRUNCATE TABLE sensor_data");
        
        // เตรียมข้อมูลสำหรับแต่ละห้อง
        $rooms = ['Room1', 'Room2', 'Room3'];
        $current_time = time();  // เวลาปัจจุบัน
        
        // เตรียม SQL statement สำหรับการเพิ่มข้อมูล
        $insert_stmt = $conn->prepare("INSERT INTO sensor_data (temperature, humidity, room, datetime) VALUES (?, ?, ?, ?)");
        $insert_stmt->bind_param("ddss", $temp, $humidity, $room, $datetime);
        
        $count = 0;
        foreach ($rooms as $room) {
            // สร้างข้อมูลย้อนหลัง 24 ชั่วโมงสำหรับแต่ละห้อง
            for ($i = 0; $i < 24; $i++) {
                $temp = round(rand(230, 270) / 10, 1);     // สุ่มอุณหภูมิระหว่าง 23.0-27.0 °C
                $humidity = round(rand(500, 650) / 10, 1); // สุ่มความชื้นระหว่าง 50.0-65.0 %
                $datetime = date('Y-m-d H:i:s', $current_time - ($i * 3600));  // สร้างเวลาย้อนหลังทีละชั่วโมง
                
                if ($insert_stmt->execute()) {
                    $count++;
                }
            }
        }
        
        $insert_stmt->close();
        
        echo "<p>✅ เพิ่มข้อมูลตัวอย่างสำเร็จ จำนวน $count รายการ</p>";
    } else {
        echo "<p>✅ มีข้อมูลในตาราง sensor_data เพียงพอแล้ว ({$row['count']} รายการ)</p>";
    }
    
    // แสดงตัวอย่างข้อมูล 5 รายการล่าสุดในรูปแบบตาราง
    $sample_data = $conn->query("SELECT * FROM sensor_data ORDER BY datetime DESC LIMIT 5");
    
    if ($sample_data->num_rows > 0) {
        echo "<h3>ข้อมูลตัวอย่าง 5 รายการล่าสุด:</h3>";
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>ID</th><th>Room</th><th>Temperature</th><th>Humidity</th><th>Datetime</th></tr>";
        
        while ($row = $sample_data->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['room']}</td>";
            echo "<td>{$row['temperature']} °C</td>";
            echo "<td>{$row['humidity']} %</td>";
            echo "<td>{$row['datetime']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    echo "<h3>การเพิ่มข้อมูลตัวอย่างเสร็จสมบูรณ์!</h3>";
    echo "<p>คุณสามารถกลับไปที่ <a href='index.html'>หน้าหลัก</a> เพื่อใช้งานแดชบอร์ดได้แล้ว</p>";
    
} catch (Exception $e) {
    // แสดงข้อผิดพลาดที่เกิดขึ้น
    echo "<h3>❌ เกิดข้อผิดพลาด:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>กรุณาตรวจสอบการตั้งค่าฐานข้อมูลของคุณ</p>";
}

// ปิดการเชื่อมต่อฐานข้อมูลเมื่อเสร็จสิ้น
if (isset($conn)) {
    $conn->close();
}
?>
