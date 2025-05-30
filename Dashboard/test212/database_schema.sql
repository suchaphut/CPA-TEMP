-- สร้างฐานข้อมูลใหม่ถ้ายังไม่มี
-- esp8266_dashboard: ฐานข้อมูลสำหรับระบบติดตามอุณหภูมิและความชื้นผ่าน ESP8266
CREATE DATABASE IF NOT EXISTS esp8266_dashboard;

-- เลือกใช้ฐานข้อมูล esp8266_dashboard
USE esp8266_dashboard;

-- สร้างตาราง sensor_data สำหรับเก็บข้อมูลเซ็นเซอร์
-- ประกอบด้วย:
-- - id: รหัสอ้างอิงที่เพิ่มขึ้นอัตโนมัติ
-- - temperature: ค่าอุณหภูมิ (องศาเซลเซียส)
-- - humidity: ค่าความชื้นสัมพัทธ์ (เปอร์เซ็นต์)
-- - room: ชื่อห้องที่ติดตั้งเซ็นเซอร์
-- - datetime: วันและเวลาที่บันทึกข้อมูล (บันทึกอัตโนมัติ)
CREATE TABLE IF NOT EXISTS sensor_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    temperature FLOAT NOT NULL,
    humidity FLOAT NOT NULL,
    room VARCHAR(50) NOT NULL,
    datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- สร้างตาราง rooms สำหรับจัดการข้อมูลห้อง
-- ประกอบด้วย:
-- - id: รหัสห้อง (เพิ่มขึ้นอัตโนมัติ)
-- - room_name: ชื่อห้อง (ห้ามซ้ำกัน)
-- - user_id: รหัสผู้ใช้ที่เป็นเจ้าของห้อง (ถ้ามี)
-- - created_at: วันและเวลาที่สร้างห้อง
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_name VARCHAR(50) NOT NULL UNIQUE,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- สร้างตาราง users สำหรับจัดการข้อมูลผู้ใช้งาน
-- ประกอบด้วย:
-- - id: รหัสผู้ใช้ (เพิ่มขึ้นอัตโนมัติ)
-- - username: ชื่อผู้ใช้ (ห้ามซ้ำกัน)
-- - email: อีเมล (ห้ามซ้ำกัน)
-- - password: รหัสผ่านที่เข้ารหัสแล้ว
-- - created_at: วันและเวลาที่สร้างบัญชี
-- - last_login: วันและเวลาที่เข้าสู่ระบบครั้งล่าสุด
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- สร้างตาราง user_settings สำหรับการตั้งค่าของผู้ใช้แต่ละคน
-- ประกอบด้วย:
-- - id: รหัสการตั้งค่า (เพิ่มขึ้นอัตโนมัติ)
-- - user_id: รหัสผู้ใช้ (เชื่อมโยงกับตาราง users)
-- - temp_min: อุณหภูมิต่ำสุดที่ยอมรับได้ (ค่าเริ่มต้น 24°C)
-- - temp_max: อุณหภูมิสูงสุดที่ยอมรับได้ (ค่าเริ่มต้น 25°C)
-- - humidity_min: ความชื้นต่ำสุดที่ยอมรับได้ (ค่าเริ่มต้น 50%)
-- - humidity_max: ความชื้นสูงสุดที่ยอมรับได้ (ค่าเริ่มต้น 60%)
-- - discord_webhook: URL สำหรับส่งการแจ้งเตือนไปยัง Discord (ถ้ามี)
-- - discord_enabled: เปิด/ปิดการแจ้งเตือนผ่าน Discord
-- หมายเหตุ: เมื่อลบผู้ใช้ การตั้งค่าจะถูกลบไปด้วย (ON DELETE CASCADE)
CREATE TABLE IF NOT EXISTS user_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    temp_min FLOAT DEFAULT 24,
    temp_max FLOAT DEFAULT 25,
    humidity_min FLOAT DEFAULT 50,
    humidity_max FLOAT DEFAULT 60,
    discord_webhook VARCHAR(255) NULL,
    discord_enabled BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default rooms
INSERT INTO rooms (room_name) VALUES 
('Room1'),
('Room2'),
('Room3')
ON DUPLICATE KEY UPDATE room_name = VALUES(room_name);

-- Insert sample data for testing
INSERT INTO sensor_data (temperature, humidity, room, datetime)
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
LIMIT 100;
