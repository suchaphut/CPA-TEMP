<?php
/**
 * ไฟล์การตั้งค่าสำหรับ ESP8266 Dashboard
 * ทำหน้าที่โหลดค่าตัวแปรสภาพแวดล้อม (Environment Variables) จากไฟล์ .env
 * และกำหนดค่าการตั้งค่าพื้นฐานของระบบ
 */

// ฟังก์ชันสำหรับโหลดตัวแปรสภาพแวดล้อมจากไฟล์ .env
function loadEnv($path = '.env') {  // รับพารามิเตอร์เป็นพาธของไฟล์ .env โดยค่าเริ่มต้นคือ '.env'
    if (!file_exists($path)) {  // ตรวจสอบว่าไฟล์ .env มีอยู่ในระบบหรือไม่
        die("Error: .env file not found. Please create one based on .env.example");   // ถ้าไม่พบไฟล์ให้แสดงข้อความแจ้งเตือนและหยุดการทำงาน
    }

    // อ่านไฟล์ .env แบบบรรทัดต่อบรรทัด โดยข้ามบรรทัดว่างและลบ whitespace
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {    // วนลูปเพื่อประมวลผลแต่ละบรรทัด
        // ข้ามบรรทัดที่เป็นคอมเมนต์ (ขึ้นต้นด้วย #)
        if (strpos(trim($line), '#') === 0) {   // ตรวจสอบว่าบรรทัดเริ่มต้นด้วย # หรือไม่
            continue; // ถ้าเป็นคอมเมนต์ให้ข้ามไปบรรทัดถัดไป
        }

        // แยกชื่อตัวแปรและค่าออกจากกัน โดยใช้เครื่องหมาย =
        list($name, $value) = explode('=', $line, 2); 
        $name = trim($name);  // ลบ whitespace ที่อาจมีอยู่ที่ต้นและท้ายชื่อตัวแปร
        $value = trim($value); // ลบ whitespace ที่อาจมีอยู่ที่ต้นและท้ายค่าตัวแปร
        
        // ลบเครื่องหมายคำพูด (quotes) ออกจากค่าตัวแปร ถ้ามี
        if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {  // ตรวจสอบเครื่องหมาย double quotes
            $value = substr($value, 1, -1);  // ตัดเครื่องหมาย double quotes ออก
        } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {   // ตรวจสอบเครื่องหมาย single quotes
            $value = substr($value, 1, -1);  // ตัดเครื่องหมาย single quotes ออก
        }
        
        // กำหนดค่าตัวแปรสภาพแวดล้อม
        putenv("{$name}={$value}");  // กำหนดค่าในระดับระบบปฏิบัติการ
        $_ENV[$name] = $value; // กำหนดค่าในตัวแปร $_ENV ของ PHP
    }
}

// เรียกใช้ฟังก์ชันเพื่อโหลดตัวแปรสภาพแวดล้อม
loadEnv();  

// การตั้งค่าฐานข้อมูล (Database Configuration)
define('DB_HOST', getenv('DB_HOST') ?: '');  // กำหนดที่อยู่ของเซิร์ฟเวอร์ฐานข้อมูล
define('DB_NAME', getenv('DB_NAME') ?: '');  // กำหนดชื่อฐานข้อมูลที่ใช้งาน
define('DB_USER', getenv('DB_USER') ?: ''); // กำหนดชื่อผู้ใช้สำหรับเข้าถึงฐานข้อมูล
define('DB_PASS', getenv('DB_PASS') ?: '');  // กำหนดรหัสผ่านสำหรับเข้าถึงฐานข้อมูล

// การตั้งค่าแอปพลิเคชัน (Application Settings)
define('APP_DEBUG', getenv('APP_DEBUG') === 'true');  // กำหนดโหมดดีบัก (true = เปิดใช้งาน, false = ปิดใช้งาน)
define('APP_TIMEZONE', getenv('APP_TIMEZONE') ?: 'UTC'); // กำหนดเขตเวลาของแอปพลิเคชัน ค่าเริ่มต้นคือ UTC

// ตั้งค่าเขตเวลาสำหรับ PHP
date_default_timezone_set(APP_TIMEZONE);  // กำหนดเขตเวลาให้กับ PHP ตามค่าที่ตั้งไว้

// กำหนดการแสดงข้อผิดพลาดตามโหมดดีบัก
if (APP_DEBUG) {    // ตรวจสอบว่าอยู่ในโหมดดีบักหรือไม่
    ini_set('display_errors', 1);  // เปิดการแสดงข้อผิดพลาดบนหน้าเว็บ
    error_reporting(E_ALL); // แสดงข้อผิดพลาดทุกประเภท
} else {   // กรณีไม่ได้อยู่ในโหมดดีบัก
    ini_set('display_errors', 0);   // ปิดการแสดงข้อผิดพลาดบนหน้าเว็บ
    error_reporting(E_ERROR | E_PARSE);   // แสดงเฉพาะข้อผิดพลาดร้ายแรงและข้อผิดพลาดในการแปลภาษา
}

// ตั้งค่า URL สำหรับ Discord webhook (ถ้ามีการกำหนดไว้)
define('DISCORD_WEBHOOK_URL', getenv('DISCORD_WEBHOOK_URL') ?: '');    // URL สำหรับส่งการแจ้งเตือนไปยัง Discord
