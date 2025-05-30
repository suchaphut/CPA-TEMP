<?php
// This script fixes the main.js file by removing the import statement
$mainjs_path = 'main.js'; //สถานที่เก็บไฟล์ main.js

if (file_exists($mainjs_path)) { //ตรวจสอบว่าไฟล์ main.js มีอยู่จริงหรือไม่
    $content = file_get_contents($mainjs_path); //อ่านไฟล์ main.js
    
    // Remove the import statement
    $content = preg_replace('/^\s*import\s+\{\s*Chart\s*\}\s*from\s*"@\/components\/ui\/chart"\s*$/m', '// Chart import removed', $content); 
    // //ลบ import statement ที่ไม่ต้องการออก
    
    // Write the fixed content back to the file
    file_put_contents($mainjs_path, $content);//เขียนเนื้อหาที่แก้ไขแล้วกลับไปที่ไฟล์ main.js
    
    echo "JavaScript file fixed successfully!"; //แสดงข้อความเมื่อแก้ไขสำเร็จ
} else { //ถ้าไฟล์ main.js ไม่มีอยู่จริง
    echo "Error: main.js file not found!"; //แสดงข้อความเมื่อไม่พบไฟล์ main.js
}
?>
