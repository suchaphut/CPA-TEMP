<?php
/**
 * สคริปต์สำหรับตั้งค่าฐานข้อมูลระบบ ESP8266 Dashboard
 * - สร้างฐานข้อมูลถ้ายังไม่มี
 * - สร้างตารางที่จำเป็นทั้งหมด
 * - เพิ่มข้อมูลเริ่มต้นสำหรับการทดสอบ
 */

// นำเข้าไฟล์การตั้งค่า
require_once __DIR__ . '/config.php';

try {
    // สร้างการเชื่อมต่อกับ MySQL โดยยังไม่ระบุฐานข้อมูล
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

    // ตรวจสอบการเชื่อมต่อ
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    echo "<h2>การตรวจสอบและสร้างฐานข้อมูล ESP8266 Dashboard</h2>";
    
    // สร้างฐานข้อมูลถ้ายังไม่มี
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    if ($conn->query($sql) === TRUE) {
        echo "<p>✅ สร้างฐานข้อมูล " . DB_NAME . " สำเร็จหรือมีอยู่แล้ว</p>";
    } else {
        throw new Exception("Error creating database: " . $conn->error);
    }

    // เลือกฐานข้อมูล
    $conn->select_db(DB_NAME);

    // สร้างตารางต่างๆ ในระบบ
    $tables = [
        // ตาราง sensor_data: เก็บข้อมูลจากเซ็นเซอร์
        // - temperature: อุณหภูมิที่วัดได้
        // - humidity: ความชื้นที่วัดได้
        // - room: ชื่อห้องที่ติดตั้งเซ็นเซอร์
        // - datetime: เวลาที่บันทึกข้อมูล
        "CREATE TABLE IF NOT EXISTS sensor_data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            temperature FLOAT NOT NULL,
            humidity FLOAT NOT NULL,
            room VARCHAR(50) NOT NULL,
            datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        // ตาราง rooms: เก็บข้อมูลห้องต่างๆ ในระบบ
        // - room_name: ชื่อห้อง (ห้ามซ้ำ)
        // - user_id: รหัสผู้ใช้ที่เป็นเจ้าของห้อง (ถ้ามี)
        "CREATE TABLE IF NOT EXISTS rooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_name VARCHAR(50) NOT NULL UNIQUE,
            user_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        // ตาราง users: เก็บข้อมูลผู้ใช้งานระบบ
        // - username: ชื่อผู้ใช้ (ห้ามซ้ำ)
        // - email: อีเมล (ห้ามซ้ำ)
        // - password: รหัสผ่านที่เข้ารหัสแล้ว
        // - last_login: เวลาที่เข้าสู่ระบบครั้งล่าสุด
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL
        )",
        
        // ตาราง user_settings: เก็บการตั้งค่าของผู้ใช้แต่ละคน
        // - ค่าขีดจำกัดเริ่มต้นสำหรับแจ้งเตือน
        // - การตั้งค่า webhook Discord สำหรับการแจ้งเตือน
        "CREATE TABLE IF NOT EXISTS user_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            temp_min FLOAT DEFAULT 24,
            temp_max FLOAT DEFAULT 25,
            humidity_min FLOAT DEFAULT 50,
            humidity_max FLOAT DEFAULT 60,
            discord_webhook VARCHAR(255) NULL,
            discord_enabled BOOLEAN DEFAULT FALSE
        )",
        
        // ตาราง room_thresholds: เก็บค่าขีดจำกัดสำหรับแต่ละห้อง
        // - กำหนดค่าอุณหภูมิและความชื้นต่ำสุด-สูงสุดที่ยอมรับได้
        // - แยกตามห้องและผู้ใช้ (ห้ามซ้ำ)
        "CREATE TABLE IF NOT EXISTS room_thresholds (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id INT NOT NULL,
            user_id INT NOT NULL,
            temp_min FLOAT DEFAULT 24,
            temp_max FLOAT DEFAULT 25,
            humidity_min FLOAT DEFAULT 50,
            humidity_max FLOAT DEFAULT 60,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY room_user_unique (room_id, user_id)
        )"
    ];
    
    // ประมวลผลการสร้างตารางต่างๆ
    foreach ($tables as $sql) {
        if ($conn->query($sql) === TRUE) {
            $table_name = preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $matches) ? $matches[1] : 'unknown';
            echo "<p>✅ สร้างตาราง $table_name สำเร็จหรือมีอยู่แล้ว</p>";
        } else {
            throw new Exception("Error creating table: " . $conn->error);
        }
    }
    
    // เพิ่มห้องเริ่มต้นถ้ายังไม่มี
    $sql = "INSERT INTO rooms (room_name) VALUES 
        ('Room1'),
        ('Room2'),
        ('Room3')
        ON DUPLICATE KEY UPDATE room_name = VALUES(room_name)";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p>✅ เพิ่มห้องเริ่มต้นสำเร็จหรือมีอยู่แล้ว</p>";
    } else {
        throw new Exception("Error inserting default rooms: " . $conn->error);
    }
    
    // ตรวจสอบว่าควรเพิ่มข้อมูลตัวอย่างหรือไม่
    $result = $conn->query("SELECT COUNT(*) as count FROM sensor_data");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // เพิ่มข้อมูลตัวอย่าง
        $sql = "INSERT INTO sensor_data (temperature, humidity, room, datetime)
        SELECT 
            ROUND(RAND() * 5 + 23, 1) AS temperature, 
            ROUND(RAND() * 20 + 45, 1) AS humidity,
            CASE FLOOR(RAND() * 3)
                WHEN 0 THEN 'Room1'
                WHEN 1 THEN 'Room2'
                ELSE 'Room3'
            END AS room,
            DATE_SUB(NOW(), INTERVAL ROUND(RAND() * 24) HOUR) AS datetime
        FROM 
            (SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) t1,
            (SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) t2
        LIMIT 100";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p>✅ เพิ่มข้อมูลตัวอย่างสำเร็จ</p>";
        } else {
            throw new Exception("Error inserting sample data: " . $conn->error);
        }
    } else {
        echo "<p>✅ มีข้อมูลในตาราง sensor_data อยู่แล้ว</p>";
    }
    
    echo "<h3>การตั้งค่าฐานข้อมูลเสร็จสมบูรณ์!</h3>";
    echo "<p>คุณสามารถกลับไปที่ <a href='index.html'>หน้าหลัก</a> เพื่อใช้งานแดชบอร์ดได้แล้ว</p>";
    
} catch (Exception $e) {
    echo "<h3>❌ เกิดข้อผิดพลาด:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>กรุณาตรวจสอบการตั้งค่าฐานข้อมูลของคุณ</p>";
    
    if (APP_DEBUG) {
        echo "<h4>รายละเอียดข้อผิดพลาด:</h4>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
}

if (isset($conn)) {
    $conn->close();
}
?>
