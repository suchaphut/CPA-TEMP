/*
 * โปรเจค: ระบบตรวจวัดอุณหภูมิและความชื้นด้วย ESP8266 (ปรับปรุงแล้ว)
 * รายละเอียด: อ่านค่าจากเซนเซอร์ DHT22 และส่งข้อมูลไปยังฐานข้อมูล MySQL
 * ผ่าน HTTP GET Request พร้อมกับแสดงผลบนเว็บเซิร์ฟเวอร์
 * 
 * การปรับปรุง:
 * - แก้ไขปัญหา Memory Leak และ Resource Management
 * - ปรับปรุงการจัดการ Error และ Exception Handling
 * - เพิ่มการตรวจสอบ Input Validation
 * - ปรับปรุงการจัดการ Network Connection
 * - เพิ่ม Watchdog Timer เพื่อป้องกันระบบค้าง
 */

// นำเข้าไลบรารี่ที่จำเป็น
#include <ESP8266WiFi.h>
#include <ESP8266WebServer.h>
#include <ESP8266HTTPClient.h>
#include "DHT.h"
#include <WiFiUdp.h>
#include <NTPClient.h>
#include <WiFiManager.h>
#include <EEPROM.h>
#include <Ticker.h>

// การตั้งค่าเซนเซอร์ DHT22
#define DHTPIN 2
#define DHTTYPE DHT22
DHT dht(DHTPIN, DHTTYPE);

// การตั้งค่า GPIO
#define LED_PIN 12
#define RESET_BUTTON_PIN 14

// การตั้งค่า Memory และ Timeout
#define EEPROM_SIZE 512
#define MAX_SERVER_URL_LENGTH 100
#define MAX_RETRY_ATTEMPTS 3
#define HTTP_TIMEOUT 10000
#define SENSOR_READ_TIMEOUT 5000
#define WIFI_CONNECT_TIMEOUT 30000
#define CONFIG_PORTAL_TIMEOUT 300 // 5 นาที

// EEPROM Address Layout
#define ADDR_SERVER_URL 0
#define ADDR_ROOM_ID 100
#define ADDR_CONFIG_VALID 104

// สร้าง Object
ESP8266WebServer server(80);
WiFiUDP ntpUDP;
NTPClient timeClient(ntpUDP, "pool.ntp.org", 25200, 60000);
Ticker wdtTicker; // Watchdog Timer

// ตัวแปรสำหรับเก็บการตั้งค่า
String serverName = "";
int room_id = 1;
bool configValid = false;

// ตัวแปรสำหรับจัดการเวลา
unsigned long lastTime = 0;
const unsigned long timerDelay = 10000; // 10 วินาที
const unsigned long retryDelay = 15000; // 15 วินาที

// ตัวแปรสำหรับติดตามสถานะ
volatile bool wifiConnected = false;
volatile bool mysqlConnected = false;
volatile bool systemHealthy = true;
unsigned long ledBlinkPreviousMillis = 0;
unsigned int ledBlinkInterval = 300;
unsigned long lastWiFiCheck = 0;
const unsigned long wifiCheckInterval = 30000; // ตรวจสอบ WiFi ทุก 30 วินาที

// โครงสร้างข้อมูลสำหรับเก็บข้อมูลเซนเซอร์
struct SensorData {
  float temp;
  float hum;
  unsigned long timestamp;
  int retryCount;
  bool isValid() const {
    return !isnan(temp) && !isnan(hum) && temp > -50 && temp < 80 && hum >= 0 && hum <= 100;
  }
};

// ตัวแปรสำหรับระบบ Retry
SensorData retryData = {NAN, NAN, 0, 0};
bool hasRetryData = false;

// Watchdog Timer Callback
void ICACHE_RAM_ATTR wdtCallback() {
  if (!systemHealthy) {
    Serial.println(F("[WDT] ระบบไม่ตอบสนอง กำลังรีสตาร์ท..."));
    ESP.restart();
  }
  systemHealthy = false; // รีเซ็ตสถานะ ต้องมีการอัพเดทในหลัก loop
}

/*
 * ฟังก์ชัน: saveConfig()
 * วัตถุประสงค์: บันทึกการตั้งค่าลง EEPROM
 */
void saveConfig() {
  EEPROM.begin(EEPROM_SIZE);
  
  // บันทึก Server URL
  for (int i = 0; i < MAX_SERVER_URL_LENGTH; i++) {
    if (i < serverName.length()) {
      EEPROM.write(ADDR_SERVER_URL + i, serverName[i]);
    } else {
      EEPROM.write(ADDR_SERVER_URL + i, 0);
    }
  }
  
  // บันทึก Room ID
  EEPROM.put(ADDR_ROOM_ID, room_id);
  
  // บันทึกสถานะว่าการตั้งค่าถูกต้อง
  EEPROM.write(ADDR_CONFIG_VALID, 1);
  
  EEPROM.commit();
  EEPROM.end();
}

/*
 * ฟังก์ชัน: loadConfig()
 * วัตถุประสงค์: โหลดการตั้งค่าจาก EEPROM
 */
bool loadConfig() {
  EEPROM.begin(EEPROM_SIZE);
  
  // ตรวจสอบว่ามีการตั้งค่าที่ถูกต้องหรือไม่
  if (EEPROM.read(ADDR_CONFIG_VALID) != 1) {
    EEPROM.end();
    return false;
  }
  
  // โหลด Server URL
  char tempUrl[MAX_SERVER_URL_LENGTH];
  for (int i = 0; i < MAX_SERVER_URL_LENGTH; i++) {
    tempUrl[i] = EEPROM.read(ADDR_SERVER_URL + i);
  }
  tempUrl[MAX_SERVER_URL_LENGTH - 1] = '\0'; // Null terminator
  serverName = String(tempUrl);
  
  // โหลด Room ID
  EEPROM.get(ADDR_ROOM_ID, room_id);
  
  EEPROM.end();
  
  // Validate loaded data
  if (serverName.length() == 0 || room_id < 1 || room_id > 9999) {
    return false;
  }
  
  return true;
}

/*
 * ฟังก์ชัน: clearConfig()
 * วัตถุประสงค์: ล้างการตั้งค่าใน EEPROM
 */
void clearConfig() {
  EEPROM.begin(EEPROM_SIZE);
  EEPROM.write(ADDR_CONFIG_VALID, 0);
  EEPROM.commit();
  EEPROM.end();
}

/*
 * ฟังก์ชัน: connectWIFI()
 * วัตถุประสงค์: จัดการการเชื่อมต่อ WiFi ผ่าน WiFiManager
 */
void connectWIFI() {
  WiFiManager wifiManager;
  
  // ตั้งค่า Timeout
  wifiManager.setConfigPortalTimeout(CONFIG_PORTAL_TIMEOUT);
  wifiManager.setConnectTimeout(WIFI_CONNECT_TIMEOUT / 1000);
  
  // พารามิเตอร์เพิ่มเติม
  WiFiManagerParameter custom_server("server", "MySQL Server URL", serverName.c_str(), MAX_SERVER_URL_LENGTH);
  WiFiManagerParameter custom_room_id("room_id", "Room ID (1-9999)", String(room_id).c_str(), 5);
  
  wifiManager.addParameter(&custom_server);
  wifiManager.addParameter(&custom_room_id);
  
  Serial.println(F("[WiFiManager] กำลังเชื่อมต่อ..."));
  
  // ตั้งค่า LED กระพริบ
  ledBlinkInterval = 200;
  
  // เชื่อมต่อ WiFi
  if (wifiManager.autoConnect("IT-81", "12345678")) {
    Serial.println(F("[WiFiManager] เชื่อมต่อสำเร็จ"));
    
    // อัพเดทการตั้งค่า
    String newServerName = custom_server.getValue();
    int newRoomId = String(custom_room_id.getValue()).toInt();
    
    // Validate input
    if (newServerName.length() > 0 && newServerName.length() < MAX_SERVER_URL_LENGTH && 
        newRoomId >= 1 && newRoomId <= 9999) {
      serverName = newServerName;
      room_id = newRoomId;
      saveConfig();
      configValid = true;
    }
    
    wifiConnected = true;
  } else {
    Serial.println(F("[WiFiManager] หมดเวลารอ กำลังรีสตาร์ท..."));
    ESP.restart();
  }
}

/*
 * ฟังก์ชัน: checkWiFi()
 * วัตถุประสงค์: ตรวจสอบและรักษาการเชื่อมต่อ WiFi
 */
void checkWiFi() {
  unsigned long currentMillis = millis();
  
  // ตรวจสอบ WiFi ทุก 30 วินาที หรือเมื่อสูญเสียการเชื่อมต่อ
  if (currentMillis - lastWiFiCheck >= wifiCheckInterval || WiFi.status() != WL_CONNECTED) {
    lastWiFiCheck = currentMillis;
    
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println(F("[WiFi] การเชื่อมต่อขาดหาย กำลังเชื่อมต่อใหม่..."));
      wifiConnected = false;
      
      WiFi.reconnect();
      
      // รอการเชื่อมต่อสูงสุด 10 วินาที
      unsigned long startTime = millis();
      while (WiFi.status() != WL_CONNECTED && millis() - startTime < 10000) {
        delay(500);
        yield(); // ให้ ESP8266 จัดการงานอื่น
      }
    }
    
    wifiConnected = (WiFi.status() == WL_CONNECTED);
    
    if (wifiConnected) {
      Serial.printf("[WiFi] เชื่อมต่อสำเร็จ IP: %s\n", WiFi.localIP().toString().c_str());
    }
  }
}

/*
 * ฟังก์ชัน: readSensorWithTimeout()
 * วัตถุประสงค์: อ่านค่าเซนเซอร์พร้อม Timeout
 */
SensorData readSensorWithTimeout() {
  SensorData data = {NAN, NAN, millis(), 0};
  unsigned long startTime = millis();
  
  // อ่านค่าเซนเซอร์หลายครั้งเพื่อความแม่นยำ
  float tempSum = 0, humSum = 0;
  int validReads = 0;
  
  for (int i = 0; i < 5 && millis() - startTime < SENSOR_READ_TIMEOUT; i++) {
    float t = dht.readTemperature();
    float h = dht.readHumidity();
    
    if (!isnan(t) && !isnan(h) && t > -50 && t < 80 && h >= 0 && h <= 100) {
      tempSum += t;
      humSum += h;
      validReads++;
    }
    
    if (i < 4) delay(100); // รอระหว่างการอ่าน ยกเว้นครั้งสุดท้าย
    yield();
  }
  
  if (validReads > 0) {
    data.temp = tempSum / validReads;
    data.hum = humSum / validReads;
  }
  
  return data;
}

/*
 * ฟังก์ชัน: sendToMySQL()
 * วัตถุประสงค์: ส่งข้อมูลไปยัง MySQL Server
 */
bool sendToMySQL(const SensorData& data) {
  if (!wifiConnected || serverName.length() == 0 || !data.isValid()) {
    return false;
  }
  
  WiFiClient client;
  HTTPClient http;
  
  // ตั้งค่า Timeout
  http.setTimeout(HTTP_TIMEOUT);
  client.setTimeout(HTTP_TIMEOUT / 1000);
  
  // อัพเดทเวลา
  timeClient.update();
  
  if (timeClient.getEpochTime() < 1000000000) {
    Serial.println(F("[NTP] เวลายังไม่ซิงค์"));
    return false;
  }
  
  // สร้าง URL
  String url = serverName;
  if (!url.startsWith("http://") && !url.startsWith("https://")) {
    url = "http://" + url;
  }
  
  url += "?temperature=" + String(data.temp, 2) +
         "&humidity=" + String(data.hum, 2) +
         "&room_id=" + String(room_id);
  
  Serial.printf("[MySQL] ส่งข้อมูล: %.2f°C, %.2f%%\n", data.temp, data.hum);
  
  bool success = false;
  
  if (http.begin(client, url)) {
    int httpCode = http.GET();
    String response = http.getString();
    
    Serial.printf("[MySQL] HTTP Code: %d\n", httpCode);
    
    if (httpCode == HTTP_CODE_OK) {
      success = true;
      mysqlConnected = true;
      Serial.println(F("[MySQL] ส่งสำเร็จ"));
    } else {
      Serial.printf("[MySQL] ส่งล้มเหลว: %s\n", response.c_str());
    }
    
    http.end();
  } else {
    Serial.println(F("[MySQL] ไม่สามารถเชื่อมต่อได้"));
  }
  
  if (!success) {
    mysqlConnected = false;
  }
  
  return success;
}

/*
 * ฟังก์ชัน: handleRoot()
 * วัตถุประสงค์: จัดการหน้าแรกของเว็บเซิร์ฟเวอร์
 */
void handleRoot() {
  SensorData data = readSensorWithTimeout();
  
  // สร้างหน้าเว็บ
  String html = "<!DOCTYPE html><html><head>";
  html += "<title>ESP8266 Sensor Monitor</title>";
  html += "<meta charset='UTF-8'>";
  html += "<meta name='viewport' content='width=device-width, initial-scale=1'>";
  html += "<style>body{font-family:Arial;margin:20px}button{padding:10px 20px;margin:5px;font-size:16px}</style>";
  html += "</head><body>";
  
  html += "<h1>🌡️ ESP8266 Sensor Monitor</h1>";
  
  // ข้อมูลเซนเซอร์
  html += "<h2>📊 ข้อมูลเซนเซอร์</h2>";
  if (data.isValid()) {
    html += "<p>🌡️ อุณหภูมิ: <strong>" + String(data.temp, 1) + " °C</strong></p>";
    html += "<p>💧 ความชื้น: <strong>" + String(data.hum, 1) + " %</strong></p>";
  } else {
    html += "<p style='color:red'>❌ ไม่สามารถอ่านข้อมูลเซนเซอร์ได้</p>";
  }
  
  // สถานะระบบ
  html += "<h2>📡 สถานะระบบ</h2>";
  html += "<p>WiFi: " + String(wifiConnected ? "🟢 เชื่อมต่อ" : "🔴 ไม่เชื่อมต่อ") + "</p>";
  html += "<p>MySQL: " + String(mysqlConnected ? "🟢 เชื่อมต่อ" : "🔴 ไม่เชื่อมต่อ") + "</p>";
  html += "<p>IP Address: " + WiFi.localIP().toString() + "</p>";
  html += "<p>เวลาทำงาน: " + String(millis() / 1000) + " วินาที</p>";
  
  // ควบคุม LED
  html += "<h2>💡 ควบคุม LED</h2>";
  String ledStatus = digitalRead(LED_PIN) ? "🟡 เปิด" : "⚫ ปิด";
  html += "<p>สถานะ: " + ledStatus + "</p>";
  html += "<a href='/led/on'><button>เปิด LED</button></a>";
  html += "<a href='/led/off'><button>ปิด LED</button></a>";
  
  // ข้อมูลการตั้งค่า
  html += "<h2>⚙️ การตั้งค่า</h2>";
  html += "<p>Room ID: " + String(room_id) + "</p>";
  html += "<p>Server: " + (serverName.length() > 0 ? serverName : "ไม่ได้ตั้งค่า") + "</p>";
  
  html += "<p><em>กดปุ่มรีเซ็ต (D5) เพื่อตั้งค่าใหม่</em></p>";
  html += "</body></html>";
  
  server.send(200, "text/html", html);
}

/*
 * ฟังก์ชัน: handleLEDOn() และ handleLEDOff()
 */
void handleLEDOn() {
  digitalWrite(LED_PIN, HIGH);
  server.sendHeader("Location", "/");
  server.send(303);
}

void handleLEDOff() {
  digitalWrite(LED_PIN, LOW);
  server.sendHeader("Location", "/");
  server.send(303);
}

/*
 * ฟังก์ชัน: handleNotFound()
 * วัตถุประสงค์: จัดการ URL ที่ไม่พบ
 */
void handleNotFound() {
  server.send(404, "text/plain", "404 - Page Not Found");
}

/*
 * ฟังก์ชัน: updateLEDStatus()
 * วัตถุประสงค์: แสดงสถานะผ่าน LED
 */
void updateLEDStatus() {
  static bool ledState = false;
  unsigned long currentMillis = millis();
  
  if (wifiConnected && mysqlConnected) {
    digitalWrite(LED_PIN, HIGH); // เปิดค้าง
    return;
  }
  
  // กระพริบตามสถานะ
  unsigned int interval = 500; // ค่าเริ่มต้น
  if (!wifiConnected) interval = 100;  // กระพริบเร็วถ้า WiFi ขาด
  else if (!mysqlConnected) interval = 300; // กระพริบปานกลางถ้า MySQL ขาด
  
  if (currentMillis - ledBlinkPreviousMillis >= interval) {
    ledBlinkPreviousMillis = currentMillis;
    ledState = !ledState;
    digitalWrite(LED_PIN, ledState);
  }
}

/*
 * ฟังก์ชัน: setup()
 */
void setup() {
  Serial.begin(115200);
  Serial.println(F("\n🚀 ESP8266 Sensor Monitor เริ่มทำงาน"));
  
  // ตั้งค่า GPIO
  pinMode(LED_PIN, OUTPUT);
  pinMode(RESET_BUTTON_PIN, INPUT_PULLUP);
  digitalWrite(LED_PIN, LOW);
  
  // เริ่มต้นเซนเซอร์
  dht.begin();
  
  // โหลดการตั้งค่า
  configValid = loadConfig();
  
  // เชื่อมต่อ WiFi
  connectWIFI();
  
  // เริ่มต้น NTP
  timeClient.begin();
  
  // ตั้งค่า Web Server
  server.on("/", handleRoot);
  server.on("/led/on", handleLEDOn);
  server.on("/led/off", handleLEDOff);
  server.onNotFound(handleNotFound);
  server.begin();
  
  // เริ่มต้น Watchdog Timer
  wdtTicker.attach(30, wdtCallback); // ตรวจสอบทุก 30 วินาที
  
  Serial.println(F("✅ ระบบพร้อมใช้งาน"));
  Serial.printf("🌐 เข้าถึงได้ที่: http://%s\n", WiFi.localIP().toString().c_str());
}

/*
 * ฟังก์ชัน: loop()
 */
void loop() {
  systemHealthy = true; // อัพเดท Watchdog
  
  server.handleClient();
  yield();
  
  checkWiFi();
  
  // Reset button check
  static unsigned long buttonPressTime = 0;
  if (digitalRead(RESET_BUTTON_PIN) == LOW) {
    if (buttonPressTime == 0) {
      buttonPressTime = millis();
    } else if (millis() - buttonPressTime > 3000) { // กดค้าง 3 วินาที
      Serial.println(F("[Reset] ล้างการตั้งค่าและรีสตาร์ท"));
      clearConfig();
      WiFi.disconnect(true);
      delay(1000);
      ESP.restart();
    }
  } else {
    buttonPressTime = 0;
  }
  
  // ส่งข้อมูลเซนเซอร์
  unsigned long currentMillis = millis();
  if (currentMillis - lastTime >= timerDelay || currentMillis < lastTime) {
    if (wifiConnected && configValid) {
      SensorData data = readSensorWithTimeout();
      
      if (data.isValid()) {
        if (!sendToMySQL(data)) {
          // เก็บไว้ส่งซ้ำ
          if (!hasRetryData || retryData.retryCount >= MAX_RETRY_ATTEMPTS) {
            retryData = data;
            retryData.retryCount = 1;
            hasRetryData = true;
          }
        }
      }
    }
    lastTime = currentMillis;
  }
  
  // ระบบ Retry
  if (hasRetryData && currentMillis - retryData.timestamp >= retryDelay) {
    if (retryData.retryCount <= MAX_RETRY_ATTEMPTS) {
      Serial.printf("[Retry] ครั้งที่ %d\n", retryData.retryCount);
      
      if (sendToMySQL(retryData)) {
        hasRetryData = false;
      } else {
        retryData.retryCount++;
        retryData.timestamp = currentMillis;
        
        if (retryData.retryCount > MAX_RETRY_ATTEMPTS) {
          Serial.println(F("[Retry] เกินจำนวนครั้งที่กำหนด ยกเลิก"));
          hasRetryData = false;
        }
      }
    }
  }
  
  updateLEDStatus();
  delay(100); // ป้องกัน Watchdog timeout
}