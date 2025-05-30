<?php
/**
 * Database connection file for ESP8266 Dashboard
 * Uses configuration from config.php
 * 
 * ไฟล์สำหรับเชื่อมต่อฐานข้อมูลสำหรับแดชบอร์ด ESP8266
 * ใช้การตั้งค่าจากไฟล์ config.php
 */

// Include configuration
// เรียกใช้ไฟล์การตั้งค่า
require_once __DIR__ . '/config.php';

// Initialize connection variable
// สร้างตัวแปรสำหรับเก็บการเชื่อมต่อฐานข้อมูล
$conn = null;

try {
    // Create database connection
    // สร้างการเชื่อมต่อกับฐานข้อมูล MySQL โดยใช้ค่าที่กำหนดไว้ในไฟล์ config.php
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Check connection
    // ตรวจสอบการเชื่อมต่อว่าสำเร็จหรือไม่
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Set character set
    // กำหนดชุดอักขระเป็น utf8mb4 เพื่อรองรับการเก็บข้อมูลภาษาไทยและ emoji
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("Error setting charset: " . $conn->error);
    }
    
    // Set wait_timeout to prevent connection timeout
    // กำหนดระยะเวลารอการเชื่อมต่อเป็น 8 ชั่วโมง เพื่อป้องกันการตัดการเชื่อมต่ออัตโนมัติ
    $conn->query("SET SESSION wait_timeout=28800");
    
} catch (Exception $e) {
    // Log error
    // บันทึกข้อผิดพลาดลงในไฟล์ log
    error_log("Database connection error: " . $e->getMessage(), 0);
    
    // Return error as JSON if called via API
    // ส่งข้อผิดพลาดในรูปแบบ JSON กรณีที่เรียกผ่าน API
    if (strpos($_SERVER['REQUEST_URI'], '.php') !== false && 
        !in_array(basename($_SERVER['SCRIPT_NAME']), ['index.php'])) {
        header('Content-Type: application/json');
        die(json_encode([
            'success' => false,
            'message' => APP_DEBUG ? 
                "Database connection error: " . $e->getMessage() : 
                "Database connection error. Please contact administrator."
        ]));
    }
    
    // Show user-friendly message
    // แสดงข้อความแจ้งเตือนที่เป็นมิตรกับผู้ใช้
    // ถ้าเปิดโหมด DEBUG จะแสดงรายละเอียดข้อผิดพลาด แต่ถ้าไม่เปิดจะแสดงเพียงข้อความทั่วไป
    die(APP_DEBUG ? 
        "Database connection error: " . $e->getMessage() . ". Please check your database configuration." : 
        "Database connection error. Please contact administrator."
    );
}
