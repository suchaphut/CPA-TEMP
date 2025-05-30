<?php
/**
 * ไฟล์สำหรับดึงข้อมูลจากฐานข้อมูลและส่งกลับในรูปแบบ JSON
 * ใช้สำหรับแสดงผลข้อมูลอุณหภูมิและความชื้นในแต่ละห้อง
 */

# การตั้งค่าการเชื่อมต่อฐานข้อมูล
$servername = "172.18.2.2";
$username = "cpatmp";
$password = "Cpa10665";
$dbname = "cpa-tmpdb"; // ชื่อฐานข้อมูลที่ใช้เก็บข้อมูลเซนเซอร์

// สร้างการเชื่อมต่อกับฐานข้อมูล MySQL
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("การเชื่อมต่อล้มเหลว: " . $conn->connect_error);
}

// รับพารามิเตอร์จาก URL
// timePeriod: ช่วงเวลาที่ต้องการดูข้อมูล (hour, day, week, month, 3months)
// room: ชื่อห้องที่ต้องการดูข้อมูล (เช่น Room1)
$timePeriod = isset($_GET['timePeriod']) ? $_GET['timePeriod'] : 'day';
$room = isset($_GET['room']) ? $_GET['room'] : 'Room1';

// กำหนดเงื่อนไขช่วงเวลาสำหรับการดึงข้อมูล
$timeCondition = "";
if ($timePeriod == 'hour') {
    $timeCondition = "AND datetime >= NOW() - INTERVAL 1 HOUR";  // ดึงข้อมูล 1 ชั่วโมงล่าสุด
} elseif ($timePeriod == 'day') {
    $timeCondition = "AND DATE(datetime) = CURDATE()";          // ดึงข้อมูลวันปัจจุบัน
} elseif ($timePeriod == 'week') {
    $timeCondition = "AND datetime >= NOW() - INTERVAL 1 WEEK";  // ดึงข้อมูล 1 สัปดาห์ล่าสุด
} elseif ($timePeriod == 'month') {
    $timeCondition = "AND DATE(datetime) >= CURDATE() - INTERVAL 1 MONTH";  // ดึงข้อมูล 1 เดือนล่าสุด
} elseif ($timePeriod == '3months') {
    $timeCondition = "AND DATE(datetime) >= CURDATE() - INTERVAL 3 MONTH";  // ดึงข้อมูล 3 เดือนล่าสุด
}

// สร้างคำสั่ง SQL และเตรียมการ query ข้อมูล
$sql = "SELECT * FROM sensor_data WHERE room = ? $timeCondition ORDER BY datetime DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $room);  // กำหนดพารามิเตอร์ชื่อห้อง
$stmt->execute();
$result = $stmt->get_result();

// ตัวแปรสำหรับเก็บข้อมูลต่างๆ
$temperature_data = [];      // อาเรย์เก็บข้อมูลอุณหภูมิ
$humidity_data = [];        // อาเรย์เก็บข้อมูลความชื้น
$datetime_data = [];        // อาเรย์เก็บข้อมูลวันเวลา
$minTemp = null;            // ค่าอุณหภูมิต่ำสุด
$maxTemp = null;            // ค่าอุณหภูมิสูงสุด
$minHumidity = null;        // ค่าความชื้นต่ำสุด
$maxHumidity = null;        // ค่าความชื้นสูงสุด
$minTempDate = '';         // วันที่ที่มีอุณหภูมิต่ำสุด
$maxTempDate = '';         // วันที่ที่มีอุณหภูมิสูงสุด
$minHumidityDate = '';     // วันที่ที่มีความชื้นต่ำสุด
$maxHumidityDate = '';     // วันที่ที่มีความชื้นสูงสุด

// ตัวแปรสำหรับคำนวณค่าเฉลี่ย
$totalTemp = 0;            // ผลรวมของอุณหภูมิ
$totalHumidity = 0;        // ผลรวมของความชื้น
$dataCount = 0;            // จำนวนข้อมูลทั้งหมด

// ประมวลผลข้อมูลที่ได้จากฐานข้อมูล
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // เก็บข้อมูลในอาเรย์
        $temperature_data[] = $row['temperature'];
        $humidity_data[] = $row['humidity'];
        $datetime_data[] = date("j M Y H:i:s", strtotime($row['datetime']));

        // คำนวณหาค่าต่ำสุดและสูงสุดของอุณหภูมิและความชื้น
        $current_temp = $row['temperature'];
        $current_humidity = $row['humidity'];

        // อัพเดทค่าต่ำสุด-สูงสุดของอุณหภูมิ
        if ($minTemp === null || $current_temp < $minTemp) {
            $minTemp = $current_temp;
        }
        if ($maxTemp === null || $current_temp > $maxTemp) {
            $maxTemp = $current_temp;
        }
        // อัพเดทค่าต่ำสุด-สูงสุดของความชื้น
        if ($minHumidity === null || $current_humidity < $minHumidity) {
            $minHumidity = $current_humidity;
        }
        if ($maxHumidity === null || $current_humidity > $maxHumidity) {
            $maxHumidity = $current_humidity;
        }

        // สะสมค่าสำหรับการคำนวณค่าเฉลี่ย
        $totalTemp += $current_temp;
        $totalHumidity += $current_humidity;
        $dataCount++;
    }
}

// คำนวณค่าเฉลี่ยของอุณหภูมิและความชื้น
$averageTemp = $dataCount > 0 ? $totalTemp / $dataCount : 0;
$averageHumidity = $dataCount > 0 ? $totalHumidity / $dataCount : 0;

// ดึงข้อมูลสำหรับกราฟ (จำกัดที่ 50 รายการล่าสุด)
$sql_graph = "SELECT * FROM sensor_data WHERE room = ? $timeCondition ORDER BY datetime DESC LIMIT 50";
$stmt_graph = $conn->prepare($sql_graph);
$stmt_graph->bind_param("s", $room);
$stmt_graph->execute();
$result_graph = $stmt_graph->get_result();

// อาเรย์สำหรับเก็บข้อมูลที่จะแสดงในกราฟ
$temperature_data_graph = [];    // ข้อมูลอุณหภูมิสำหรับกราฟ
$humidity_data_graph = [];       // ข้อมูลความชื้นสำหรับกราฟ
$datetime_data_graph = [];       // ข้อมูลวันเวลาสำหรับกราฟ

// ประมวลผลข้อมูลสำหรับกราฟ
if ($result_graph->num_rows > 0) {
    while($row = $result_graph->fetch_assoc()) {
        $temperature_data_graph[] = $row['temperature'];
        $humidity_data_graph[] = $row['humidity'];
        $datetime_data_graph[] = date("j M Y H:i:s", strtotime($row['datetime']));
    }
}

// ปิดการเชื่อมต่อฐานข้อมูล
$stmt->close();
$stmt_graph->close();
$conn->close();

// ส่งข้อมูลกลับในรูปแบบ JSON
echo json_encode([
    'temperature_data_graph' => $temperature_data_graph,    // ข้อมูลอุณหภูมิสำหรับกราฟ
    'humidity_data_graph' => $humidity_data_graph,         // ข้อมูลความชื้นสำหรับกราฟ
    'datetime_data_graph' => $datetime_data_graph,         // ข้อมูลวันเวลาสำหรับกราฟ
    'minTemp' => $minTemp,                                // ค่าอุณหภูมิต่ำสุด
    'maxTemp' => $maxTemp,                                // ค่าอุณหภูมิสูงสุด
    'minHumidity' => $minHumidity,                       // ค่าความชื้นต่ำสุด
    'maxHumidity' => $maxHumidity,                       // ค่าความชื้นสูงสุด
    'averageTemp' => $averageTemp,                       // ค่าเฉลี่ยอุณหภูมิ
    'averageHumidity' => $averageHumidity                // ค่าเฉลี่ยความชื้น
]);
?>
