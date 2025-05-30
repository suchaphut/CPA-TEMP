// Chart import removed
// User state
let currentUser = null // กำหนดตัวแปร currentUser เป็น null เพื่อเก็บข้อมูลผู้ใช้ที่เข้าสู่ระบบ
let userSettings = {
  // กำหนดค่าตั้งต้นสำหรับการตั้งค่าผู้ใช้
  temp_min: 24,
  temp_max: 25,
  humidity_min: 50,
  humidity_max: 60,
  discord_webhook: "",
  discord_enabled: false,
}

// Room-specific thresholds
const roomThresholds = {} // กำหนดตัวแปร roomThresholds เป็นอ็อบเจ็กต์ว่างเพื่อเก็บค่าตั้งต้นสำหรับห้องต่างๆ

// Table pagination variables
let tempCurrentPage = 1 // เก็บหน้าปัจจุบันของตาราง
let humidCurrentPage = 1 // ตัวแปรสำหรับเก็บหน้าปัจจุบันของตาราง
const entriesPerPage = 5 // จำนวนรายการที่แสดงในแต่ละหน้า
const maxEntries = 50 //Maximum เลขในตารางที่แสดง

// Store all data to use with pagination
let allTempData = [] // เก็บข้อมูลอุณหภูมิทั้งหมด
let allHumidData = [] // เก็บข้อมูลความชื้นทั้งหมด
let filteredTempData = [] // เก็บข้อมูลอุณหภูมิที่กรองแล้ว
let filteredHumidData = [] // เก็บข้อมูลความชื้นที่กรองแล้ว

// ล็อกอินสถานะ
function checkLoginStatus() {
  const userData = sessionStorage.getItem("user") // ดึงข้อมูลผู้ใช้จาก sessionStorage
  if (userData) {
    // ถ้ามีข้อมูลผู้ใช้
    currentUser = JSON.parse(userData) // แปลงข้อมูล JSON เป็นอ็อบเจ็กต์
    document.getElementById("loginBtn").style.display = "none" // ซ่อนปุ่มล็อกอินเมื่อผู้ใช้ล็อกอินอยู่
    document.getElementById("settingsBtn").style.display = "flex" // แสดงปุ่มตั้งค่าผู้ใช้เมื่อผู้ใช้ล็อกอินอยู่
    document.getElementById("logoutBtn").style.display = "flex" // แสดงปุ่มออกจากระบบเมื่อผู้ใช้ล็อกอินอยู่
    document.getElementById("userInfo").style.display = "block" // แสดงข้อมูลผู้ใช้เมื่อผู้ใช้ล็อกอินอยู่
    document.getElementById("manageRoomsBtn").style.display = "flex" //โชว์ปุ่มจัดการห้องเมื่อผู้ใช้ล็อกอินอยู่
    document.getElementById("usernameDisplay").textContent = currentUser.username //โชว์ชื่อผู้ใช้ที่ล็อกอินอยู่

    //โชว์ข้อมูลห้องที่ผู้ใช้ล็อกอินอยู่
    loadUserSettings()
  } else {
    // ถ้าไม่มีข้อมูลผู้ใช้
    document.getElementById("loginBtn").style.display = "flex" // แสดงปุ่มล็อกอินเมื่อผู้ใช้ยังไม่ล็อกอิน
    document.getElementById("settingsBtn").style.display = "none" // ซ่อนปุ่มตั้งค่าผู้ใช้เมื่อผู้ใช้ยังไม่ล็อกอิน
    document.getElementById("logoutBtn").style.display = "none" // ซ่อนปุ่มออกจากระบบเมื่อผู้ใช้ยังไม่ล็อกอิน
    document.getElementById("userInfo").style.display = "none" // ซ่อนข้อมูลผู้ใช้เมื่อผู้ใช้ยังไม่ล็อกอิน
    document.getElementById("manageRoomsBtn").style.display = "none" // ซ่อนปุ่มจัดการห้องเมื่อผู้ใช้ยังไม่ล็อกอิน
  }
}

function loadUserSettings() {
  // โหลดการตั้งค่าผู้ใช้จากเซิร์ฟเวอร์
  if (!currentUser) return // ถ้าไม่มีข้อมูลผู้ใช้ให้หยุดการทำงาน

  fetch(`get_settings.php?user_id=${currentUser.user_id}`) // ส่งคำขอไปยังเซิร์ฟเวอร์เพื่อดึงการตั้งค่าผู้ใช้
    .then((response) => response.json()) // แปลงข้อมูลที่ได้รับเป็น JSON
    .then((data) => {
      // ถ้าข้อมูลที่ได้รับสำเร็จ
      if (data.success) {
        // ถ้าข้อมูลที่ได้รับสำเร็จ
        userSettings = {
          // อัปเดตการตั้งค่าผู้ใช้
          ...userSettings, // เก็บการตั้งค่าผู้ใช้เดิม
          ...data.settings, // เก็บการตั้งค่าผู้ใช้ใหม่
          discord_webhook: data.settings.discord_webhook || "", // เก็บ URL ของ Discord webhook
          discord_enabled: data.settings.discord_enabled || false, // เก็บสถานะการเปิดใช้งาน Discord webhook
        }

        // Load room thresholds for current room
        loadRoomThresholds(currentRoom) // โหลดค่าตั้งห้องสำหรับห้องปัจจุบัน
      }
    })
    .catch((error) => {
      // ถ้ามีข้อผิดพลาดในการโหลดการตั้งค่าผู้ใช้
      console.error("Error loading settings:", error) // แสดงข้อผิดพลาดในคอนโซล
    })
}

// Load room-specific thresholds
function loadRoomThresholds(roomName) {
  // โหลดค่าตั้งห้องสำหรับห้องที่กำหนด
  if (!currentUser) {
    // ถ้าไม่มีข้อมูลผู้ใช้ให้หยุดการทำงาน
    updateThresholdDisplays() // อัปเดตการแสดงผลค่าตั้งห้อง
    return // อัปเดตการแสดงผลค่าตั้งห้อง
  }

  // First get the room ID from the room name
  fetch(`get_rooms.php?user_id=${currentUser.user_id}`) // ส่งคำขอไปยังเซิร์ฟเวอร์เพื่อดึงข้อมูลห้อง
    .then((response) => response.json()) // แปลงข้อมูลที่ได้รับเป็น JSON
    .then((data) => {
      // ถ้าข้อมูลที่ได้รับสำเร็จ
      if (data.success) {
        // ถ้าข้อมูลที่ได้รับสำเร็จ
        const room = data.rooms.find((r) => r.name.replace(/\s+/g, "") === roomName) // ค้นหาห้องที่มีชื่อที่กำหนด
        if (room) {
          // ถ้าห้องที่มีชื่อที่กำหนดพบ
          fetch(`get_room_thresholds.php?user_id=${currentUser.user_id}&room_id=${room.id}`) // ส่งคำขอไปยังเซิร์ฟเวอร์เพื่อดึงค่าตั้งห้อง
            .then((response) => response.json()) // แปลงข้อมูลที่ได้รับเป็น JSON
            .then((thresholdData) => {
              // ถ้าข้อมูลที่ได้รับสำเร็จ
              if (thresholdData.success) {
                // ถ้าข้อมูลที่ได้รับสำเร็จ
                roomThresholds[roomName] = thresholdData.thresholds // อัปเดตค่าตั้งห้องสำหรับห้องที่กำหนด

                updateThresholdDisplays() // อัปเดตการแสดงผลค่าตั้งห้อง
              }
            })
            .catch((error) => {
              // ถ้ามีข้อผิดพลาดในการโหลดค่าตั้งห้อง
              console.error("Error loading room thresholds:", error) // แสดงข้อผิดพลาดในคอนโซล
            })
        }
      }
    })
    .catch((error) => {
      // ถ้ามีข้อผิดพลาดในการโหลดข้อมูลห้อง
      console.error("Error loading rooms:", error) // แสดงข้อผิดพลาดในคอนโซล
    })
}

// Update threshold displays in the UI
function updateThresholdDisplays() {
  // อัปเดตการแสดงผลค่าตั้งห้องใน UI
  // Use room-specific thresholds if available, otherwise fall back to user settings
  const thresholds = roomThresholds[currentRoom] || userSettings // กำหนดค่าตั้งห้องสำหรับห้องที่กำหนด

  document.getElementById("tempTableTitle").textContent =
    // แสดงชื่อห้องใน UI
    `Temperature Data (${thresholds.temp_min} - ${thresholds.temp_max}°C)` // แสดงชื่อห้องใน UI
  document.getElementById("humidityTableTitle").textContent =
    // แสดงชื่อห้องใน UI
    `Humidity Data (${thresholds.humidity_min} - ${thresholds.humidity_max}%)` // แสดงชื่อห้องใน UI
}

// Send Discord notification
async function sendDiscordNotification(message, type) {
  // ส่งการแจ้งเตือนไปยัง Discord
  if (!userSettings.discord_enabled || !userSettings.discord_webhook) return // ถ้าไม่เปิดใช้งาน Discord webhook หรือไม่มี URL ของ Discord webhook ให้หยุดการทำงาน

  try {
    // ถ้ามีการเปิดใช้งาน Discord webhook
    const color = type === "temperature" ? 16711680 : 3447003 // กำหนดสีของการแจ้งเตือน
    const response = await fetch(userSettings.discord_webhook, {
      // ส่งคำขอไปยัง Discord webhook
      method: "POST", // กำหนดวิธีการส่งคำขอ
      headers: {
        // กำหนดส่วนหัวของคำขอ
        "Content-Type": "application/json", // กำหนดประเภทของข้อมูลที่ส่ง
      }, // กำหนดประเภทของข้อมูลที่ส่ง
      body: JSON.stringify({
        // แปลงข้อมูลที่ส่งเป็น JSON
        username: "CPA-TEMP Alert", // กำหนดชื่อผู้ใช้ที่แสดงใน Discord
        embeds: [
          // กำหนดข้อมูลที่จะแสดงใน Discord
          {
            title: `${currentRoom}`, // กำหนดชื่อหัวข้อการแจ้งเตือน
            description:`**${type === "temperature" ? "🌡️ Temperature" : "💧 Humidity"} Alert**\n${message}`, // กำหนดข้อความการแจ้งเตือน
            color: color, // กำหนดสีของการแจ้งเตือน
            timestamp: new Date().toISOString(), // กำหนดเวลาที่ส่งการแจ้งเตือน
            footer: {
              text: "CPA-TEMP Monitoring", // กำหนดข้อความที่แสดงในส่วนท้ายของการแจ้งเตือน
            },
          },
        ],
      }),
    })

    if (!response.ok) {
      // ถ้ามีข้อผิดพลาดในการส่งการแจ้งเตือน
      console.error("Discord notification failed:", await response.text()) // แสดงข้อผิดพลาดในคอนโซล
    }
  } catch (error) {
    // ถ้ามีข้อผิดพลาดในการส่งการแจ้งเตือน
    console.error("Error sending Discord notification:", error) // แสดงข้อผิดพลาดในคอนโซล
  }
}

// Auth modal functionality
document.getElementById("loginBtn").addEventListener("click", () => {
  // แสดง modal สำหรับล็อกอิน
  document.getElementById("authModal").style.display = "flex" // แสดง modal สำหรับล็อกอิน
})

document.getElementById("authModalClose").addEventListener("click", () => {
  // ปิด modal สำหรับล็อกอิน
  document.getElementById("authModal").style.display = "none" // ปิด modal สำหรับล็อกอิน
})

document.getElementById("loginTab").addEventListener("click", () => {
  //แสดงแท็บล็อกอิน
  document.getElementById("loginTab").classList.add("active") // เพิ่มคลาส active ให้กับแท็บล็อกอิน
  document.getElementById("registerTab").classList.remove("active") // ลบคลาส active ออกจากแท็บลงทะเบียน
  document.getElementById("loginForm").classList.add("active") // เพิ่มคลาส active ให้กับฟอร์มล็อกอิน
  document.getElementById("registerForm").classList.remove("active") // ลบคลาส active ออกจากฟอร์มลงทะเบียน
})

document.getElementById("registerTab").addEventListener("click", () => {
  // แสดงแท็บลงทะเบียน
  document.getElementById("registerTab").classList.add("active") // เพิ่มคลาส active ให้กับแท็บลงทะเบียน
  document.getElementById("loginTab").classList.remove("active") // ลบคลาส active ออกจากแท็บล็อกอิน
  document.getElementById("registerForm").classList.add("active") // เพิ่มคลาส active ให้กับฟอร์มลงทะเบียน
  document.getElementById("loginForm").classList.remove("active") // ลบคลาส active ออกจากฟอร์มล็อกอิน
})

// Login form submission
document.getElementById("loginForm").addEventListener("submit", (e) => {
  // ส่งข้อมูลล็อกอิน
  e.preventDefault() // หยุดการส่งฟอร์มแบบปกติ

  const username = document.getElementById("loginUsername").value // ดึงชื่อผู้ใช้จากฟอร์มล็อกอิน
  const password = document.getElementById("loginPassword").value // ดึงรหัสผ่านจากฟอร์มล็อกอิน

  // Validate inputs
  if (!username || !password) {
    // ถ้าชื่อผู้ใช้หรือรหัสผ่านว่าง
    document.getElementById("loginError").textContent = "Please fill in all fields" // แสดงข้อความผิดพลาด
    document.getElementById("loginError").style.display = "block" // แสดงข้อความผิดพลาด
    return // หยุดการทำงาน
  }

  // Send login request
  fetch("login.php", {
    // ส่งคำขอไปยังเซิร์ฟเวอร์เพื่อทำการล็อกอิน
    method: "POST", // กำหนดวิธีการส่งคำขอ
    headers: {
      // กำหนดส่วนหัวของคำขอ
      "Content-Type": "application/json", // กำหนดประเภทของข้อมูลที่ส่ง
    },
    body: JSON.stringify({
      // แปลงข้อมูลที่ส่งเป็น JSON
      username, // เก็บชื่อผู้ใช้
      password, // เก็บรหัสผ่าน
    }),
  })
    .then((response) => response.json()) // แปลงข้อมูลที่ได้รับเป็น JSON
    .then((data) => {
      // ถ้าข้อมูลที่ได้รับสำเร็จ
      if (data.success) {
        // ถ้าข้อมูลที่ได้รับสำเร็จ
        // Store user data in session storage
        currentUser = {
          // อัปเดตข้อมูลผู้ใช้
          user_id: data.user_id, // เก็บรหัสผู้ใช้
          username: data.username, // เก็บชื่อผู้ใช้
        }
        sessionStorage.setItem("user", JSON.stringify(currentUser)) // เก็บข้อมูลผู้ใช้ใน sessionStorage

        // Store user settings
        userSettings = {
          // อัปเดตการตั้งค่าผู้ใช้
          ...userSettings, // เก็บการตั้งค่าผู้ใช้เดิม
          ...data.settings, // เก็บการตั้งค่าผู้ใช้ใหม่
          discord_webhook: data.settings.discord_webhook || "", // เก็บ URL ของ Discord webhook
          discord_enabled: data.settings.discord_enabled || false, // เก็บสถานะการเปิดใช้งาน Discord webhook
        }

        // Update UI
        checkLoginStatus() // อัปเดตสถานะล็อกอินใน UI
        updateThresholdDisplays() // อัปเดตการแสดงผลค่าตั้งห้องใน UI

        // Close modal
        document.getElementById("authModal").style.display = "none" // ปิด modal สำหรับล็อกอิน

        // Reset form
        document.getElementById("loginForm").reset() // รีเซ็ตฟอร์มล็อกอิน
        document.getElementById("loginError").style.display = "none" // ซ่อนข้อความผิดพลาด

        // Show success message
        showPopup("Login successful!") // แสดงข้อความล็อกอินสำเร็จ
      } else {
        // ถ้าข้อมูลที่ได้รับไม่สำเร็จ
        document.getElementById("loginError").textContent = data.message // แสดงข้อความผิดพลาด
        document.getElementById("loginError").style.display = "block" // แสดงข้อความผิดพลาด
      }
    })
    .catch((error) => {
      // ถ้ามีข้อผิดพลาดในการล็อกอิน
      console.error("Login error:", error) // แสดงข้อผิดพลาดในคอนโซล
      document.getElementById("loginError").textContent = "An error occurred. Please try again." // แสดงข้อความผิดพลาด
      document.getElementById("loginError").style.display = "block" // แสดงข้อความผิดพลาด
    })
})

// Register form submission
document.getElementById("registerForm").addEventListener("submit", (e) => {
  // ส่งข้อมูลลงทะเบียน
  e.preventDefault() // หยุดการส่งฟอร์มแบบปกติ

  const username = document.getElementById("registerUsername").value // ดึงชื่อผู้ใช้จากฟอร์มลงทะเบียน
  const email = document.getElementById("registerEmail").value //ดึงอีเมลจากฟอร์มลงทะเบียน
  const password = document.getElementById("registerPassword").value // ดึงรหัสผ่านจากฟอร์มลงทะเบียน
  const confirmPassword = document.getElementById("registerConfirmPassword").value // ดึงรหัสผ่านยืนยันจากฟอร์มลงทะเบียน

  // Validate inputs
  if (!username || !email || !password || !confirmPassword) {
    // ถ้าชื่อผู้ใช้หรืออีเมลหรือรหัสผ่านหรือรหัสผ่านยืนยันว่าง
    document.getElementById("registerError").textContent = "Please fill in all fields" // แสดงข้อความผิดพลาด
    document.getElementById("registerError").style.display = "block" // แสดงข้อความผิดพลาด
    return // หยุดการทำงาน
  }

  if (password !== confirmPassword) {
    // ถ้ารหัสผ่านและรหัสผ่านยืนยันไม่ตรงกัน
    document.getElementById("registerError").textContent = "Passwords do not match" // แสดงข้อความผิดพลาด
    document.getElementById("registerError").style.display = "block" // แสดงข้อความผิดพลาด
    return // หยุดการทำงาน
  }

  // Send register request
  fetch("register.php", {
    // ส่งคำขอไปยังเซิร์ฟเวอร์เพื่อทำการลงทะเบียน
    method: "POST", // กำหนดวิธีการส่งคำขอ
    headers: {
      "Content-Type": "application/json", // กำหนดส่วนหัวของคำขอ
    },
    body: JSON.stringify({
      // แปลงข้อมูลที่ส่งเป็น JSON
      username, // เก็บชื่อผู้ใช้
      email, // เก็บอีเมล
      password, // เก็บรหัสผ่าน
    }),
  })
    .then((response) => response.json()) // แปลงข้อมูลที่ได้รับเป็น JSON
    .then((data) => {
      // ถ้าข้อมูลที่ได้รับสำเร็จ
      if (data.success) {
        // ถ้าข้อมูลที่ได้รับสำเร็จ
        // Switch to login tab
        document.getElementById("loginTab").click() // เปลี่ยนไปที่แท็บล็อกอิน

        // Reset form
        document.getElementById("registerForm").reset() // รีเซ็ตฟอร์มลงทะเบียน
        document.getElementById("registerError").style.display = "none" // ซ่อนข้อความผิดพลาด

        // Show success message
        showPopup("Registration successful! Please login.") // แสดงข้อความลงทะเบียนสำเร็จ
      } else {
        // ถ้าข้อมูลที่ได้รับไม่สำเร็จ
        document.getElementById("registerError").textContent = data.message // แสดงข้อความผิดพลาด
        document.getElementById("registerError").style.display = "block" // แสดงข้อความผิดพลาด
      }
    })
    .catch((error) => {
      // ถ้ามีข้อผิดพลาดในการลงทะเบียน
      console.error("Registration error:", error) // แสดงข้อผิดพลาดในคอนโซล
      document.getElementById("registerError").textContent = "An error occurred. Please try again." // แสดงข้อความผิดพลาด
      document.getElementById("registerError").style.display = "block" // แสดงข้อความผิดพลาด
    })
})

// Settings modal functionality
document.getElementById("settingsBtn").addEventListener("click", () => {
  // แสดง modal สำหรับตั้งค่าผู้ใช้
  // Load rooms for the dropdown
  loadRoomsForSettings() // โหลดห้องสำหรับ dropdown

  // Populate form with current settings
  document.getElementById("discordWebhook").value = userSettings.discord_webhook || "" // กำหนดค่า Discord webhook
  document.getElementById("discordEnabled").checked = userSettings.discord_enabled || false // กำหนดสถานะการเปิดใช้งาน Discord webhook

  document.getElementById("settingsModal").style.display = "flex" // แสดง modal สำหรับตั้งค่าผู้ใช้
})

// Load rooms for settings dropdown
function loadRoomsForSettings() {
  // โหลดรายชื่อห้องสำหรับ dropdown ใน modal ตั้งค่าผู้ใช้
  if (!currentUser) return // ถ้าไม่มีข้อมูลผู้ใช้ให้หยุดการทำงาน

  fetch(`get_rooms.php?user_id=${currentUser.user_id}`) // ส่งคำขอไปยังเซิร์ฟเวอร์เพื่อดึงข้อมูลห้อง
    .then((response) => response.json()) // แปลงข้อมูลที่ได้รับเป็น JSON
    .then((data) => {
      // ถ้าข้อมูลที่ได้รับสำเร็จ
      if (data.success) {
        // ถ้าข้อมูลที่ได้รับสำเร็จ
        const roomSelect = document.getElementById("roomSelect") // ดึง element dropdown จาก DOM
        roomSelect.innerHTML = "" // ล้างตัวเลือกที่มีอยู่
        let currentRoomOption = null // ตัวแปรสำหรับเก็บ option ที่ตรงกับห้องปัจจุบัน

        // Add room options
        data.rooms.forEach((room) => {
          // วนลูปเพื่อเพิ่มตัวเลือกห้อง
          const option = document.createElement("option") // สร้าง element option
          option.value = room.id // กำหนดค่า value เป็น id ของห้อง
          option.textContent = `${room.name} (ID: ${room.id})` // กำหนดข้อความใน option เป็นชื่อห้องและ ID
          roomSelect.appendChild(option) // เพิ่ม option ใน dropdown

          // If this is the current room, save the option
          if (room.name.replace(/\s+/g, "") === currentRoom) {
            // ถ้าพบตัวเลือกที่ตรงกับห้องปัจจุบัน
            currentRoomOption = option // เก็บ option ที่ตรงกับห้องปัจจุบัน
          }
        })

        // Select current room if found
        if (currentRoomOption) {
          // ถ้าพบตัวเลือกที่ตรงกับห้องปัจจุบัน
          currentRoomOption.selected = true // กำหนดให้ตัวเลือกที่ตรงกับห้องปัจจุบันเป็นตัวเลือกที่ถูกเลือก
          // Load thresholds for the selected room
          loadThresholdsForSelectedRoom(currentRoomOption.value) // โหลดค่าตั้งห้องสำหรับห้องที่เลือก
        }

        // Add change event listener
        roomSelect.addEventListener("change", function () {
          // เพิ่มฟังก์ชันการทำงานเมื่อมีการเปลี่ยนแปลงใน dropdown
          loadThresholdsForSelectedRoom(this.value) // โหลดค่าตั้งห้องสำหรับห้องที่เลือก
        })
      }
    })
    .catch((error) => {
      // ถ้ามีข้อผิดพลาดในการโหลดห้อง
      console.error("Error loading rooms for settings:", error) // แสดงข้อผิดพลาดในคอนโซล
    })
}

// Load thresholds for the selected room in settings
function loadThresholdsForSelectedRoom(roomId) {
  // โหลดค่าตั้งห้องสำหรับห้องที่เลือกใน modal ตั้งค่าผู้ใช้
  if (!currentUser) return // ถ้าไม่มีข้อมูลผู้ใช้ให้หยุดการทำงาน

  fetch(`get_room_thresholds.php?user_id=${currentUser.user_id}&room_id=${roomId}`) // ส่งคำขอไปยังเซิร์ฟเวอร์เพื่อดึงค่าตั้งห้อง
    .then((response) => response.json()) // แปลงข้อมูลที่ได้รับเป็น JSON
    .then((data) => {
      // ถ้าข้อมูลที่ได้รับสำเร็จ
      if (data.success) {
        // ถ้าข้อมูลที่ได้รับสำเร็จ
        // Populate form with room thresholds
        document.getElementById("tempMin").value = data.thresholds.temp_min // กำหนดค่าอุณหภูมิต่ำสุด
        document.getElementById("tempMax").value = data.thresholds.temp_max // กำหนดค่าอุณหภูมิสูงสุด
        document.getElementById("humidityMin").value = data.thresholds.humidity_min // กำหนดค่าความชื้นต่ำสุด
        document.getElementById("humidityMax").value = data.thresholds.humidity_max // กำหนดค่าความชื้นสูงสุด
      }
    })
    .catch((error) => {
      // ถ้ามีข้อผิดพลาดในการโหลดค่าตั้งห้อง
      console.error("Error loading room thresholds for settings:", error) // แสดงข้อผิดพลาดในคอนโซล
    })
}

document.getElementById("settingsModalClose").addEventListener("click", () => {
  // ปิด modal สำหรับตั้งค่าผู้ใช้
  document.getElementById("settingsModal").style.display = "none" // ปิด modal สำหรับตั้งค่าผู้ใช้
})

// Settings form submission
document.getElementById("settingsForm").addEventListener("submit", (e) => {
  // ส่งข้อมูลตั้งค่าผู้ใช้
  e.preventDefault() // หยุดการส่งฟอร์มแบบปกติ

  if (!currentUser) {
    // ถ้าไม่มีข้อมูลผู้ใช้ให้หยุดการทำงาน
    document.getElementById("settingsError").textContent = "You must be logged in to save settings" // แสดงข้อความผิดพลาด
    document.getElementById("settingsError").style.display = "block" // แสดงข้อความผิดพลาด
    document.getElementById("settingsSuccess").style.display = "none" // ซ่อนข้อความสำเร็จ
    return // หยุดการทำงาน
  }

  const roomId = document.getElementById("roomSelect").value // ดึงรหัสห้องจาก dropdown
  const tempMin = Number.parseFloat(document.getElementById("tempMin").value) //ดึงค่าอุณหภมูมิต่ำสุดจากฟอร์ม
  const tempMax = Number.parseFloat(document.getElementById("tempMax").value) // ดึงค่าอุณหภูมิสูงสุดจากฟอร์ม
  const humidityMin = Number.parseFloat(document.getElementById("humidityMin").value) // ดึงค่าความชื้นต่ำสุดจากฟอร์ม
  const humidityMax = Number.parseFloat(document.getElementById("humidityMax").value) // ดึงค่าความชื้นสูงสุดจากฟอร์ม
  const discordWebhook = document.getElementById("discordWebhook").value.trim() // ดึง URL ของ Discord webhook จากฟอร์ม
  const discordEnabled = document.getElementById("discordEnabled").checked // ดึงสถานะการเปิดใช้งาน Discord webhook จากฟอร์ม

  // Validate inputs
  if (tempMin >= tempMax) {
    // ถ้าค่าอุณหภูมิต่ำสุดมากกว่าหรือเท่ากับค่าอุณหภูมิสูงสุด
    document.getElementById("settingsError").textContent = "Temperature minimum must be less than maximum" // แสดงข้อความผิดพลาด
    document.getElementById("settingsError").style.display = "block" // แสดงข้อความผิดพลาด
    document.getElementById("settingsSuccess").style.display = "none" // ซ่อนข้อความสำเร็จ
    return // หยุดการทำงาน
  }

  if (humidityMin >= humidityMax) {
    document.getElementById("settingsError").textContent = "Humidity minimum must be less than maximum" // แสดงข้อความผิดพลาด
    document.getElementById("settingsError").style.display = "block" // แสดงข้อความผิดพลาด
    document.getElementById("settingsSuccess").style.display = "none" // ซ่อนข้อความสำเร็จ
    return // หยุดการทำงาน
  }

  if (discordEnabled && !discordWebhook) {
    // ถ้าการเปิดใช้งาน Discord webhook แต่ไม่มี URL ของ Discord webhook
    document.getElementById("settingsError").textContent = //
      "Discord webhook URL is required when notifications are enabled" // แสดงข้อความผิดพลาด
    document.getElementById("settingsError").style.display = "block" // แสดงข้อความผิดพลาด
    document.getElementById("settingsSuccess").style.display = "none" // ซ่อนข้อความสำเร็จ
    return // หยุดการทำงาน
  }

  // Send settings update request
  fetch("save_room_thresholds.php", {
    // ส่งคำขอไปยังเซิร์ฟเวอร์เพื่อบันทึกค่าตั้งห้อง
    method: "POST", // กำหนดวิธีการส่งคำขอ
    headers: {
      // กำหนดส่วนหัวของคำขอ/
      "Content-Type": "application/json", // กำหนดประเภทของข้อมูลที่ส่ง
    }, // กำหนดประเภทของข้อมูลที่ส่ง
    body: JSON.stringify({
      // แปลงข้อมูลที่ส่งเป็น JSON
      user_id: currentUser.user_id, // เก็บรหัสผู้ใช้
      room_id: roomId, // เก็บรหัสห้อง
      temp_min: tempMin, // เก็บค่าอุณหภูมิต่ำสุด
      temp_max: tempMax, //  เก็บค่าอุณหภูมิสูงสุด
      humidity_min: humidityMin, //เก็บค่าความชื้นต่ำสุด
      humidity_max: humidityMax, //เก็บค่าความชื้นสูงสุด
      discord_webhook: discordWebhook, // เก็บ URL ของ Discord webhook
      discord_enabled: discordEnabled, // เก็บสถานะการเปิดใช้งาน Discord webhook
    }),
  })
    .then((response) => response.json()) // แปลงข้อมูลที่ได้รับเป็น JSON
    .then((data) => {
      // ถ้าข้อมูลที่ได้รับสำเร็จ
      if (data.success) {
        // ถ้าข้อมูลที่ได้รับสำเร็จ
        // Update user settings for Discord
        userSettings = {
          // อัปเดตการตั้งค่าผู้ใช้
          ...userSettings, // เก็บการตั้งค่าผู้ใช้เดิม
          discord_webhook: discordWebhook, // เก็บ URL ของ Discord webhook
          discord_enabled: discordEnabled, // เก็บสถานะการเปิดใช้งาน Discord webhook
        }

        // Get the room name from the select element
        const roomSelect = document.getElementById("roomSelect") //
        const roomName = roomSelect.options[roomSelect.selectedIndex].textContent.replace(/\s+/g, "") // ดึงชื่อห้องจาก dropdown

        // Update room thresholds
        roomThresholds[roomName] = {
          // อัปเดตค่าตั้งห้อง
          temp_min: tempMin, // เก็บค่าอุณหภูมิต่ำสุด
          temp_max: tempMax, //  เก็บค่าอุณหภูมิสูงสุด
          humidity_min: humidityMin, //เก็บค่าความชื้นต่ำสุด
          humidity_max: humidityMax, //เก็บค่าความชื้นสูงสุด
        }

        // Update display if this is the current room
        if (roomName === currentRoom) {
          // ถ้าห้องที่เลือกเป็นห้องปัจจุบัน
          updateThresholdDisplays() // อัปเดตการแสดงผลค่าตั้งห้องใน UI
        }

        // Show success message
        document.getElementById("settingsSuccess").textContent = data.message // แสดงข้อความสำเร็จ
        document.getElementById("settingsSuccess").style.display = "block" // แสดงข้อความสำเร็จ
        document.getElementById("settingsError").style.display = "none" // ซ่อนข้อความผิดพลาด

        // Refresh data with new thresholds
        fetchDataAndUpdate() // โหลดข้อมูลใหม่ด้วยค่าตั้งห้องใหม่

        // Auto close after 2 seconds
        setTimeout(() => {
          // ปิด modal หลังจาก 2 วินาที
          document.getElementById("settingsModal").style.display = "none" // ปิด modal สำหรับตั้งค่าผู้ใช้
        }, 2000) // ปิด modal สำหรับตั้งค่าผู้ใช้
      } else {
        // ถ้าข้อมูลที่ได้รับไม่สำเร็จ
        document.getElementById("settingsError").textContent = data.message // แสดงข้อความผิดพลาด
        document.getElementById("settingsError").style.display = "block" // แสดงข้อความผิดพลาด
        document.getElementById("settingsSuccess").style.display = "none" // ซ่อนข้อความสำเร็จ
      }
    })
    .catch((error) => {
      // ถ้ามีข้อผิดพลาดในการบันทึกค่าตั้งห้อง
      console.error("Settings update error:", error) // แสดงข้อผิดพลาดในคอนโซล
      document.getElementById("settingsError").textContent = "An error occurred. Please try again." // แสดงข้อความผิดพลาด
      document.getElementById("settingsError").style.display = "block" // แสดงข้อความผิดพลาด
      document.getElementById("settingsSuccess").style.display = "none" // ซ่อนข้อความสำเร็จ
    })
})

// Logout functionality
document.getElementById("logoutBtn").addEventListener("click", () => {
  // ล็อกเอาท์ผู้ใช้
  // Clear user data
  currentUser = null // ล้างข้อมูลผู้ใช้
  sessionStorage.removeItem("user") // ลบข้อมูลผู้ใช้จาก sessionStorage

  // Reset to default settings
  userSettings = {
    // รีเซ็ตการตั้งค่าผู้ใช้
    temp_min: 24, // กำหนดค่าอุณหภูมิต่ำสุด
    temp_max: 25, // กำหนดค่าอุณหภูมิสูงสุด
    humidity_min: 50, // กำหนดค่าความชื้นต่ำสุด
    humidity_max: 60, // กำหนดค่าความชื้นสูงสุด
    discord_webhook: "", // กำหนด URL ของ Discord webhook
    discord_enabled: false, // กำหนดสถานะการเปิดใช้งาน Discord webhook
  }

  // Update UI
  checkLoginStatus() // อัปเดตสถานะล็อกอินใน UI
  updateThresholdDisplays() // อัปเดตการแสดงผลค่าตั้งห้องใน UI

  // Refresh data with default thresholds
  fetchDataAndUpdate() // โหลดข้อมูลใหม่ด้วยค่าตั้งห้องเริ่มต้น

  // Show success message
  showPopup("Logout successful!") // แสดงข้อความล็อกเอาท์สำเร็จ
})

const prevAlertStatus = {
  // สถานะการแจ้งเตือนก่อนหน้า
  temperature: null, // สถานะการแจ้งเตือนอุณหภูมิ
  humidity: null, // สถานะการแจ้งเตือนความชื้น
}

let currentRoom = "Room1" //  ห้องปัจจุบัน
let showOnlyAbnormal = false // ตัวแปรสำหรับแสดงเฉพาะค่าที่ผิดปกติ
let intervalId // ตัวแปรสำหรับเก็บ ID ของ interval

// เพิ่มตัวแปรเพื่อติดตามสถานะการรีเฟรช
let refreshTimeoutTemp = null
let refreshTimeoutHumid = null
let isRefreshing = false

// แก้ไขฟังก์ชัน fetchDataAndUpdate เพื่อป้องกันการเรียกซ้ำซ้อน
function fetchDataAndUpdate(keepPagination = false) {
  // ป้องกันการเรียกซ้ำซ้อนถ้ากำลังรีเฟรชอยู่
  if (isRefreshing) return

  isRefreshing = true

  // ยกเลิก timeout ที่อาจกำลังทำงานอยู่
  if (refreshTimeoutTemp) {
    clearTimeout(refreshTimeoutTemp)
    refreshTimeoutTemp = null
  }

  if (refreshTimeoutHumid) {
    clearTimeout(refreshTimeoutHumid)
    refreshTimeoutHumid = null
  }

  const timePeriod = document.getElementById("timePeriod").value
  fetch(`getData.php?timePeriod=${timePeriod}&room=${currentRoom}`)
    .then((res) => {
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`)
      }
      return res.json()
    })
    .then((data) => {
      // Store the latest data for use with pagination
      latestTemperatureData = data
      latestHumidityData = data

      updateStats(data)
      updateTrend(data)
      updateCharts(data)

      if (!keepPagination) {
        // Reset pagination flags when not keeping pagination
        isTempFullyLoaded = false
        isHumidFullyLoaded = false
        // Update tables with default pagination
        updateTemperatureTable(data)
        updateHumidityTable(data)
      } else {
        // If we're keeping pagination, use the current state
        if (isTempFullyLoaded) {
          updateTemperatureTable(latestTemperatureData)
        } else {
          // Always update when showing default entries
          updateTemperatureTable(data)
        }

        if (isHumidFullyLoaded) {
          updateHumidityTable(latestHumidityData)
        } else {
          // Always update when showing default entries
          updateHumidityTable(data)
        }
      }

      // รีเซ็ตสถานะการรีเฟรช
      isRefreshing = false
    })
    .catch((err) => {
      console.error("Data fetch error:", err)
      // Show error message to user
      showPopup("ไม่สามารถโหลดข้อมูลได้ กรุณาลองใหม่อีกครั้ง")

      // รีเซ็ตสถานะการรีเฟรชแม้เกิดข้อผิดพลาด
      isRefreshing = false
    })
}

// แก้ไขฟังก์ชัน startDataFetch เพื่อให้ล้าง interval เดิมก่อนตั้งใหม่

function startDataFetch() {
  // เริ่มการดึงข้อมูล
  if (intervalId) {
    // ถ้ามีการตั้งค่า interval อยู่แล้ว
    clearInterval(intervalId) // ลบการตั้งค่า interval เดิม
  }
  // ยกเลิก timeout ที่อาจกำลังทำงานอยู่
  if (refreshTimeoutTemp) {
    clearTimeout(refreshTimeoutTemp)
    refreshTimeoutTemp = null
  }
  if (refreshTimeoutHumid) {
    clearTimeout(refreshTimeoutHumid)
    refreshTimeoutHumid = null
  }
  fetchDataAndUpdate(false) // โหลดข้อมูลครั้งแรกโดยไม่รีเซ็ตตาราง
  fetchRoomStatus() // Fetch initial room status
  intervalId = setInterval(() => {
    fetchDataAndUpdate(true) // รีเฟรชข้อมูลโดยรักษาสถานะตาราง
    fetchRoomStatus() // โหลดสถานะห้องใหม่
  }, 3000) // เรียกใช้ทุก 3 วินาที
}

startDataFetch() // เริ่มการดึงข้อมูลเมื่อโหลดหน้าเว็บ

function switchRoom(room) {
  // เปลี่ยนห้อง
  currentRoom = room // กำหนดห้องปัจจุบัน
  // Reset pagination
  tempCurrentPage = 1 // รีเซ็ตหน้าปัจจุบันของตารางอุณหภูมิ
  humidCurrentPage = 1 // รีเซ็ตหน้าปัจจุบันของตารางความชื้น

  document.querySelectorAll(".room-btn").forEach((btn) => {
    // ลบคลาส active จากปุ่มห้องทั้งหมด
    btn.classList.remove("active") // ลบคลาส active
  })
  document.getElementById("btn" + room).classList.add("active") // เพิ่มคลาส active ให้กับปุ่มห้องที่เลือก

  // Load room-specific thresholds
  loadRoomThresholds(room) // โหลดค่าตั้งห้องสำหรับห้องที่เลือก

  startDataFetch() // เริ่มการดึงข้อมูลใหม่เมื่อเปลี่ยนห้อง
  fetchDataAndUpdate() // โหลดข้อมูลใหม่
}

function calculateStandardDeviation(data) {
  // คำนวณส่วนเบี่ยงเบนมาตรฐาน
  if (!data || data.length === 0) return 0 // ถ้าไม่มีข้อมูลหรือข้อมูลว่างให้คืนค่า 0
  const mean = data.reduce((sum, value) => sum + value, 0) / data.length // คำนวณค่าเฉลี่ย
  const squaredDiffs = data.map((value) => Math.pow(value - mean, 2)) // คำนวณความแตกต่างยกกำลังสอง
  const avgSquaredDiff = squaredDiffs.reduce((sum, value) => sum + value, 0) / data.length // คำนวณค่าเฉลี่ยของความแตกต่างยกกำลังสอง
  return Math.sqrt(avgSquaredDiff) // คืนค่ารากที่สองของค่าเฉลี่ยของความแตกต่างยกกำลังสอง
}

function getTrend(current, previous) {
  // คำนวณแนวโน้ม
  if (!current || !previous) return "🔄 ไม่มีข้อมูล" // ถ้าไม่มีข้อมูลให้คืนค่า "ไม่มีข้อมูล"
  if (current > previous) return "<span class='trend-up'>📈 เพิ่มขึ้น</span>" // ถ้าค่าปัจจุบันมากกว่าค่าก่อนหน้าให้คืนค่า "เพิ่มขึ้น"
  if (current < previous) return "<span class='trend-down'>📉 ลดลง</span>" // ถ้าค่าปัจจุบันน้อยกว่าค่าก่อนหน้าให้คืนค่า "ลดลง"
  return "<span class='trend-neutral'>🔄 ไม่มีการเปลี่ยนแปลง</span>" // ถ้าค่าปัจจุบันเท่ากับค่าก่อนหน้าให้คืนค่า "ไม่มีการเปลี่ยนแปลง"
}

let isPopupVisible = false // ตัวแปรสำหรับเก็บสถานะการแสดงผลของ popup

function showPopup(message) {
  // แสดง popup
  if (isPopupVisible) return // ถ้า popup แสดงอยู่แล้วให้หยุดการทำงาน

  const popup = document.getElementById("popup") // ดึง popup จาก DOM
  const popupMessage = document.getElementById("popupMessage") // ดึงข้อความใน popup จาก DOM
  popupMessage.textContent = message // กำหนดข้อความใน popup
  popup.style.display = "flex" // แสดง popup
  popup.style.opacity = "0" // กำหนดความโปร่งใสของ popup เป็น 0

  // Trigger reflow for animation
  void popup.offsetWidth // ทำให้ popup รีเฟรชเพื่อให้แอนิเมชันทำงาน

  popup.style.opacity = "1" // กำหนดความโปร่งใสของ popup เป็น 1
  isPopupVisible = true //  กำหนดสถานะการแสดงผลของ popup เป็น true

  // Auto-close after 5 seconds
  setTimeout(() => {
    // ปิด popup หลังจาก 5 วินาที
    closePopup() // เรียกใช้ฟังก์ชันปิด popup
  }, 5000) // ปิด popup หลังจาก 5 วินาที
}

function closePopup() {
  // ปิด popup
  const popup = document.getElementById("popup") // ดึง popup จาก DOM
  popup.style.opacity = "0" // กำหนดความโปร่งใสของ popup เป็น 0
  setTimeout(() => {
    // รอ 300 มิลลิวินาทีเพื่อให้แอนิเมชันเสร็จสิ้น
    popup.style.display = "none" // ซ่อน popup
    isPopupVisible = false // กำหนดสถานะการแสดงผลของ popup เป็น false
  }, 300) //  ซ่อน popup หลังจาก 300 มิลลิวินาที
}

document.getElementById("popupClose").addEventListener("click", closePopup) // เพิ่มฟังก์ชันการทำงานเมื่อคลิกปุ่มปิด popup

const lastAlertTime = {
  // ตัวแปรสำหรับเก็บเวลาที่แจ้งเตือนล่าสุด
  temperature: null, // เวลาที่แจ้งเตือนอุณหภูมิล่าสุด
  humidity: null, // เวลาที่แจ้งเตือนความชื้นล่าสุด
}
const alertCooldown = 5 * 60 * 1000  // 5 นาที (5 minutes * 60 seconds * 1000 milliseconds)

function checkAndShowAlert(value, type, statusObj, statusKey) {
  // ฟังก์ชันสำหรับตรวจสอบและแสดงการแจ้งเตือน
  // Get thresholds for current room
  const thresholds = roomThresholds[currentRoom] || userSettings // โหลดค่าตั้งห้องสำหรับห้องปัจจุบัน
  const low = type === "อุณหภูมิ" ? thresholds.temp_min : thresholds.humidity_min // กำหนดค่าต่ำสุด
  const high = type === "อุณหภูมิ" ? thresholds.temp_max : thresholds.humidity_max // กำหนดค่าสูงสุด

  let status = "normal" // กำหนดสถานะเริ่มต้นเป็นปกติ
  const now = Date.now() // เก็บเวลาปัจจุบัน
  const cooldownKey = `${type}_cooldown` // กำหนดคีย์สำหรับการตั้งค่าการแจ้งเตือน

  if (!statusObj[cooldownKey]) {
    // ถ้าไม่มีการตั้งค่าการแจ้งเตือน
    statusObj[cooldownKey] = 0 // กำหนดการตั้งค่าการแจ้งเตือนเป็น 0
  }

  if (now - statusObj[cooldownKey] < alertCooldown) {
    // ถ้าเวลาที่ผ่านไปน้อยกว่าการตั้งค่าการแจ้งเตือน
    return // หยุดการทำงาน
  }

  // Check for high and low values
  if (value > high) {
    // ถ้าค่ามากกว่าค่าสูงสุด
    status = "high" // กำหนดสถานะเป็นสูง
    const message = `เตือน: ${type} สูงกว่า ${high}${type === "อุณหภูมิ" ? "°C" : "%"}!` // สร้างข้อความแจ้งเตือน
    showPopup(message) // แสดง popup แจ้งเตือน

    // Send Discord notification
    if (userSettings.discord_enabled && userSettings.discord_webhook) {
      // ถ้าเปิดใช้งาน Discord webhook
      sendDiscordNotification(message, type === "อุณหภูมิ" ? "temperature" : "humidity") // ส่งการแจ้งเตือนไปยัง Discord
    }

    statusObj[cooldownKey] = now // Update cooldown timestamp
  } else if (value < low) {
    // ถ้าค่าน้อยกว่าค่าต่ำสุด
    status = "low" // กำหนดสถานะเป็นต่ำ
    const message = `เตือน: ${type} ต่ำกว่า ${low}${type === "อุณหภูมิ" ? "°C" : "%"}!` // สร้างข้อความแจ้งเตือน
    showPopup(message) // แสดง popup แจ้งเตือน

    // Send Discord notification
    if (userSettings.discord_enabled && userSettings.discord_webhook) {
      // ถ้าเปิดใช้งาน Discord webhook
      sendDiscordNotification(message, type === "อุณหภูมิ" ? "temperature" : "humidity") // ส่งการแจ้งเตือนไปยัง Discord
    }

    statusObj[cooldownKey] = now //อัปเดตเวลาที่ตั้งค่าการแจ้งเตือน
  }

  // If status changed from previous state
  if (status !== statusObj[statusKey]) {
    // ถ้าสถานะเปลี่ยนจากสถานะก่อนหน้า
    statusObj[statusKey] = status // อัปเดตสถานะ
  }
}

function updateStats(data) {
  // อัปเดตสถิติ
  if (!data) return

  document.getElementById("minTemp").textContent = data.minTemp // แสดงค่าอุณหภูมิต่ำสุด
  document.getElementById("maxTemp").textContent = data.maxTemp //  แสดงค่าอุณหภูมิสูงสุด
  document.getElementById("avgTemp").textContent = data.averageTemp.toFixed(2) // แสดงค่าอุณหภูมิเฉลี่ย
  document.getElementById("minHumidity").textContent = data.minHumidity // แสดงค่าความชื้นต่ำสุด
  document.getElementById("maxHumidity").textContent = data.maxHumidity // แสดงค่าความชื้นสูงสุด
  document.getElementById("avgHumidity").textContent = data.averageHumidity.toFixed(2) // แสดงค่าความชื้นเฉลี่ย

  document.getElementById("stdDevTemp").textContent = isNaN(calculateStandardDeviation(data.temperature_data_graph)) // ตรวจสอบว่าเป็น NaN หรือไม่
    ? "0" // ถ้าเป็น NaN ให้แสดง 0
    : calculateStandardDeviation(data.temperature_data_graph).toFixed(2) // แสดงส่วนเบี่ยงเบนมาตรฐานของอุณหภูมิ
  document.getElementById("stdDevHumidity").textContent = isNaN(calculateStandardDeviation(data.humidity_data_graph)) // ตรวจสอบว่าเป็น NaN หรือไม่
    ? "0" // ถ้าเป็น NaN ให้แสดง 0
    : calculateStandardDeviation(data.humidity_data_graph).toFixed(2) // แสดงส่วนเบี่ยงเบนมาตรฐานของความชื้น
}

function updateTrend(data) {
  // อัปเดตแนวโน้ม
  if (!data || !data.temperature_data_graph || data.temperature_data_graph.length < 2) {
    // ถ้าไม่มีข้อมูลหรือข้อมูลอุณหภูมิไม่เพียงพอ
    document.getElementById("trendTemp").innerHTML = "🔄 ไม่มีข้อมูล" // แสดงข้อความ "ไม่มีข้อมูล"
    document.getElementById("trendHumidity").innerHTML = "🔄 ไม่มีข้อมูล" // แสดงข้อความ "ไม่มีข้อมูล"
    return // หยุดการทำงาน
  }

  const trendTemp = getTrend(
    // คำนวณแนวโน้มอุณหภูมิ
    data.temperature_data_graph[data.temperature_data_graph.length - 1], // ค่าปัจจุบัน
    data.temperature_data_graph[data.temperature_data_graph.length - 2], // ค่าก่อนหน้า
  )
  const trendHumidity = getTrend(
    // คำนวณแนวโน้มความชื้น
    data.humidity_data_graph[data.humidity_data_graph.length - 1], // ค่าปัจจุบัน
    data.humidity_data_graph[data.humidity_data_graph.length - 2], // ค่าก่อนหน้า
  )

  document.getElementById("trendTemp").innerHTML = trendTemp // แสดงแนวโน้มอุณหภูมิ
  document.getElementById("trendHumidity").innerHTML = trendHumidity // แสดงแนวโน้มความชื้น
}

function updateCharts(data) {
  // อัปเดตกราฟ  
  if (!data) return // ถ้าไม่มีข้อมูลให้หยุดการทำงาน

  // Create arrays for sorting 
  const chartData = data.datetime_data_graph.map((datetime, index) => ({
    datetime: datetime,
    temp: data.temperature_data_graph[index], 
    humidity: data.humidity_data_graph[index],
  }))
  
  // Sort data from newest to oldest
  chartData.sort((a, b) => new Date(b.datetime) - new Date(a.datetime))

  // Update temperature chart with sorted data
  temperatureChart.data.labels = chartData.map((item) => item.datetime)
  temperatureChart.data.datasets[0].data = chartData.map((item) => item.temp)
  
  // Display from right to left (newest on right)
  temperatureChart.options.scales.x.reverse = true 
  temperatureChart.update()

  // Update humidity chart with sorted data  
  humidityChart.data.labels = chartData.map((item) => item.datetime)
  humidityChart.data.datasets[0].data = chartData.map((item) => item.humidity)
  
  // Display from right to left (newest on right)
  humidityChart.options.scales.x.reverse = true
  humidityChart.update()
}

function processTemperatureData(data) {
  // ประมวลผลข้อมูลอุณหภูมิ
  if (!data || !data.temperature_data_graph) return [] // ถ้าไม่มีข้อมูลหรือข้อมูลอุณหภูมิให้คืนค่าเป็นอาร์เรย์ว่าง

  // Get thresholds for current room
  const thresholds = roomThresholds[currentRoom] || userSettings // โหลดค่าตั้งห้องสำหรับห้องปัจจุบัน

  allTempData = [] // สร้างอาร์เรย์สำหรับเก็บข้อมูลอุณหภูมิ
  const today = new Date() // เก็บวันที่ปัจจุบัน
  const todayStr = today.toISOString().split("T")[0] // YYYY-MM-DD  // แปลงวันที่เป็นสตริงในรูปแบบ YYYY-MM-DD

  // Create an array of objects with all the needed information
  data.temperature_data_graph.forEach((temp, i) => {
    // ประมวลผลข้อมูลอุณหภูมิ
    if (!data.datetime_data_graph[i]) return // ถ้าไม่มีข้อมูลวันที่ให้หยุดการทำงาน

    const datetime = data.datetime_data_graph[i] // เก็บวันที่และเวลา
    const entryDateStr = new Date(datetime).toISOString().split("T")[0] // YYYY-MM-DD  // แปลงวันที่เป็นสตริงในรูปแบบ YYYY-MM-DD
    if (entryDateStr !== todayStr) return // ถ้าวันที่ไม่ตรงกับวันที่ปัจจุบันให้หยุดการทำงาน

    const isTempHigh = temp > thresholds.temp_max // ตรวจสอบว่าอุณหภูมิสูงเกินไปหรือไม่
    const isTempLow = temp < thresholds.temp_min // ตรวจสอบว่าอุณหภูมิต่ำเกินไปหรือไม่
    const isAbnormal = isTempHigh || isTempLow // ตรวจสอบว่ามีค่าผิดปกติหรือไม่

    let tempStyle = "", // สร้างตัวแปรสำหรับเก็บสไตล์ของอุณหภูมิ
      tempIcon = "", // สร้างตัวแปรสำหรับเก็บไอคอนของอุณหภูมิ
      statusMessages = [] // สร้างอาร์เรย์สำหรับเก็บข้อความสถานะ

    // Check for alerts
    checkAndShowAlert(temp, "อุณหภูมิ", prevAlertStatus, "temperature") // ตรวจสอบและแสดงการแจ้งเตือน

    if (isTempHigh) {
      // ถ้าอุณหภูมิสูงเกินไป
      tempStyle = "color:var(--danger-color);font-weight:bold;" // กำหนดสไตล์ให้เป็นสีแดง
      tempIcon = " 🔺"
      statusMessages.push("อุณหภูมิสูงเกิน")
    } else if (isTempLow) {
      // ถ้าอุณหภูมิต่ำเกินไป
      tempStyle = "color:var(--primary-color);font-weight:bold;" // กำหนดสไตล์ให้เป็นสีน้ำเงิน
      tempIcon = " 🔻"
      statusMessages.push("อุณหภูมิต่ำเกิน")
    }

    const status = statusMessages.join(", ") || "ปกติ" // สร้างข้อความสถานะ
    const statusClass = //  กำหนดคลาสสถานะ
      statusMessages.length > 1 ? "status-alert" : statusMessages.length === 1 ? "status-warning" : "status-normal" //  กำหนดคลาสตามจำนวนข้อความสถานะ

    allTempData.push({
      // สร้างอ็อบเจ็กต์สำหรับเก็บข้อมูลอุณหภูมิ
      index: i + 1, // เก็บหมายเลขรายการ
      temp: temp, // เก็บค่าอุณหภูมิ
      tempStyle: tempStyle, // เก็บสไตล์ของอุณหภูมิ
      tempIcon: tempIcon, // เก็บไอคอนของอุณหภูมิ
      datetime: datetime, // เก็บวันที่และเวลา
      status: status, // เก็บข้อความสถานะ
      statusClass: statusClass, // เก็บคลาสสถานะ
      isAbnormal: isAbnormal, // เก็บสถานะค่าผิดปกติ
    })
  })

  // Sort by datetime, newest first
  allTempData.sort((a, b) => new Date(b.datetime) - new Date(a.datetime)) // เรียงลำดับข้อมูลตามวันที่และเวลาจากใหม่ไปเก่า

  // Filter data if showOnlyAbnormal is true
  filteredTempData = showOnlyAbnormal ? allTempData.filter((item) => item.isAbnormal) : allTempData // ถ้า showOnlyAbnormal เป็นจริงให้กรองข้อมูลเฉพาะค่าผิดปกติ

  // Update table info
  document.getElementById("tempTableInfo").textContent =
    // อัปเดตข้อมูลในตาราง
    `แสดง ${Math.min(entriesPerPage, filteredTempData.length)} จาก ${filteredTempData.length} รายการ` // แสดงจำนวนรายการในตาราง

  return filteredTempData // คืนค่าข้อมูลอุณหภูมิที่ประมวลผลแล้ว
}

function processHumidityData(data) {
  // ประมวลผลข้อมูลความชื้น
  if (!data || !data.humidity_data_graph) return [] // ถ้าไม่มีข้อมูลหรือข้อมูลความชื้นให้คืนค่าเป็นอาร์เรย์ว่าง

  // Get thresholds for current room
  const thresholds = roomThresholds[currentRoom] || userSettings // โหลดค่าตั้งห้องสำหรับห้องปัจจุบัน

  allHumidData = [] // สร้างอาร์เรย์สำหรับเก็บข้อมูลความชื้น
  const today = new Date() // เก็บวันที่ปัจจุบัน
  const todayStr = today.toISOString().split("T")[0] // YYYY-MM-DD // แปลงวันที่เป็นสตริงในรูปแบบ YYYY-MM-DD

  // Create an array of objects with all the needed information
  data.humidity_data_graph.forEach((humidity, i) => {
    // ประมวลผลข้อมูลความชื้น
    if (!data.datetime_data_graph[i]) return // ถ้าไม่มีข้อมูลวันที่ให้หยุดการทำงาน

    const datetime = data.datetime_data_graph[i] // เก็บวันที่และเวลา
    const entryDateStr = new Date(datetime).toISOString().split("T")[0] // YYYY-MM-DD // แปลงวันที่เป็นสตริงในรูปแบบ YYYY-MM-DD
    if (entryDateStr !== todayStr) return // ถ้าวันที่ไม่ตรงกับวันที่ปัจจุบันให้หยุดการทำงาน

    const isHumHigh = humidity > thresholds.humidity_max // ตรวจสอบว่าความชื้นสูงเกินไปหรือไม่
    const isHumLow = humidity < thresholds.humidity_min // ตรวจสอบว่าความชื้นต่ำเกินไปหรือไม่
    const isAbnormal = isHumHigh || isHumLow // ตรวจสอบว่ามีค่าผิดปกติหรือไม่

    let humidityStyle = "", // สร้างตัวแปรสำหรับเก็บสไตล์ของความชื้น
      humidityIcon = "", // สร้างตัวแปรสำหรับเก็บไอคอนของความชื้น
      statusMessages = [] // สร้างอาร์เรย์สำหรับเก็บข้อความสถานะ

    // Check for alerts
    checkAndShowAlert(humidity, "ความชื้น", prevAlertStatus, "humidity") // ตรวจสอบและแสดงการแจ้งเตือน

    if (isHumHigh) {
      // ถ้าความชื้นสูงเกินไป
      humidityStyle = "color:var(--danger-color);font-weight:bold;"
      humidityIcon = " 🔺"
      statusMessages.push("ความชื้นสูงเกิน")
    } else if (isHumLow) {
      humidityStyle = "color:var(--primary-color);font-weight:bold;"
      humidityIcon = " 🔻"
      statusMessages.push("ความชื้นต่ำเกิน")
    }

    const status = statusMessages.join(", ") || "ปกติ"
    const statusClass =
      statusMessages.length > 1 ? "status-alert" : statusMessages.length === 1 ? "status-warning" : "status-normal" // กำหนดคลาสสถานะ

    allHumidData.push({
      // สร้างอ็อบเจ็กต์สำหรับเก็บข้อมูลความชื้น
      index: i + 1, // เก็บหมายเลขรายการ
      humidity: humidity, // เก็บค่าความชื้น
      humidityStyle: humidityStyle, // เก็บสไตล์ของความชื้น
      humidityIcon: humidityIcon, // เก็บไอคอนของความชื้น
      datetime: datetime, // เก็บวันที่และเวลา
      status: status, // เก็บข้อความสถานะ
      statusClass: statusClass, // เก็บคลาสสถานะ
      isAbnormal: isAbnormal, //  เก็บสถานะค่าผิดปกติ
    })
  })

  // Sort by datetime, newest first
  allHumidData.sort((a, b) => new Date(b.datetime) - new Date(a.datetime)) // เรียงลำดับข้อมูลตามวันที่และเวลาจากใหม่ไปเก่า

  // Filter data if showOnlyAbnormal is true
  filteredHumidData = showOnlyAbnormal ? allHumidData.filter((item) => item.isAbnormal) : allHumidData // ถ้า showOnlyAbnormal เป็นจริงให้กรองข้อมูลเฉพาะค่าผิดปกติ

  // Update table info
  document.getElementById("humidTableInfo").textContent =
    // อัปเดตข้อมูลในตาราง
    `แสดง ${Math.min(entriesPerPage, filteredHumidData.length)} จาก ${filteredHumidData.length} รายการ` // แสดงจำนวนรายการในตาราง

  return filteredHumidData // คืนค่าข้อมูลความชื้นที่ประมวลผลแล้ว
}

let isTempFullyLoaded = false // ตัวแปรสำหรับเก็บสถานะการโหลดข้อมูลอุณหภูมิ
let isHumidFullyLoaded = false // ตัวแปรสำหรับเก็บสถานะการโหลดข้อมูลความชื้น

function updateTemperatureTable(data) {
  // อัปเดตตารางอุณหภูมิ
  const tempData = processTemperatureData(data) // ประมวลผลข้อมูลอุณหภูมิ
  if (!tempData || tempData.length === 0) return // ถ้าไม่มีข้อมูลให้หยุดการทำงาน

  const tbody = document.querySelector("#temperatureTable tbody") // ดึง tbody ของตารางอุณหภูมิจาก DOM
  tbody.innerHTML = "" // ล้างข้อมูลใน tbody

  // Only show first page (latest 5 entries)
  const endIdx = isTempFullyLoaded ? tempData.length : Math.min(entriesPerPage, tempData.length) // กำหนดจำนวนรายการที่จะแสดงในตาราง

  for (let i = 0; i < endIdx; i++) {
    // วนลูปเพื่อสร้างแถวในตาราง
    const item = tempData[i] // ดึงข้อมูลอุณหภูมิ
    const row = tbody.insertRow() // สร้างแถวใหม่ใน tbody
    row.classList.add("fade-in") // เพิ่มคลาส fade-in เพื่อให้มีเอฟเฟกต์การแสดงผล
    row.style.animationDelay = `${i * 0.05}s` // กำหนดเวลาในการแสดงผลของแต่ละแถว

    const cell1 = row.insertCell(0) // สร้างเซลล์ใหม่ในแถว
    const cell2 = row.insertCell(1) // สร้างเซลล์ใหม่ในแถว
    const cell3 = row.insertCell(2) // สร้างเซลล์ใหม่ในแถว
    const cell4 = row.insertCell(3) // สร้างเซลล์ใหม่ในแถว

    cell1.textContent = i + 1 // แสดงหมายเลขรายการ
    cell2.innerHTML = `<span style="${item.tempStyle}">${item.temp}${item.tempIcon}</span>` // แสดงค่าอุณหภูมิ
    cell3.textContent = item.datetime // แสดงวันที่และเวลา
    cell4.innerHTML = `<span class="${item.statusClass}">${item.status}</span>` // แสดงข้อความสถานะ
  }

  // Update load more button visibility
  const loadMoreBtn = document.getElementById("loadMoreTemp") // ดึงปุ่มโหลดเพิ่มเติมจาก DOM
  loadMoreBtn.style.display = tempData.length > entriesPerPage ? "inline-flex" : "none" // แสดงหรือซ่อนปุ่มโหลดเพิ่มเติมตามจำนวนข้อมูล

  // Update table info
  document.getElementById("tempTableInfo").textContent = `แสดง ${endIdx} จาก ${tempData.length} รายการ` // อัปเดตข้อมูลในตาราง

  // Auto refresh when there are exactly 5 entries
  //  if (endIdx === 50) { // ถ้ามีข้อมูล 5 รายการ
  //    setTimeout(() => { // ตั้งเวลาให้รีเฟรชข้อมูล
  //      fetchDataAndUpdate(true) // รีเฟรชข้อมูล
  //    }, 10000) // Refresh after 5 seconds
  //  }
}

function updateHumidityTable(data) {
  const humidData = processHumidityData(data)
  if (!humidData || humidData.length === 0) return

  const tbody = document.querySelector("#humidityTable tbody")
  tbody.innerHTML = ""

  const endIdx = isHumidFullyLoaded ? humidData.length : Math.min(entriesPerPage, humidData.length)

  for (let i = 0; i < endIdx; i++) {
    const item = humidData[i]
    const row = tbody.insertRow()
    row.classList.add("fade-in")
    row.style.animationDelay = `${i * 0.05}s`

    // แก้ไขการแสดงเลขลำดับ - เริ่มจากข้อมูลล่าสุด
    const rowNumber = item.index
 // กำหนดหมายเลขแถวเริ่มต้นจาก 1

    row.innerHTML = `
      <td>${rowNumber}</td>
      <td style="${item.humidityStyle}">${item.humidity}${item.humidityIcon}</td> 
      <td>${item.datetime}</td>
      <td><span class="${item.statusClass}">${item.status}</span></td>
    `
  }

  // อัปเดต UI อื่นๆ ตามเดิม
  const loadMoreBtn = document.getElementById("loadMoreHumid")
  loadMoreBtn.style.display = humidData.length > entriesPerPage ? "inline-flex" : "none"
  document.getElementById("humidTableInfo").textContent = `แสดง ${endIdx} จาก ${humidData.length} รายการ`
}

// Load more temperature data
document.getElementById("loadMoreTemp").addEventListener("click", function (event) {
  // โหลดข้อมูลอุณหภูมิเพิ่มเติม
  event.preventDefault() // ป้องกันการโหลดหน้าใหม่
  isTempFullyLoaded = true // ตั้งค่าสถานะว่าโหลดข้อมูลทั้งหมดแล้ว
  updateTemperatureTable(latestTemperatureData) // Update table with all data // อัปเดตตารางด้วยข้อมูลทั้งหมด

  // Hide button if all data is loaded
  if (filteredTempData.length <= entriesPerPage || tbody.rows.length >= filteredTempData.length) {
    // ถ้าข้อมูลทั้งหมดโหลดแล้ว
    this.style.display = "none" // ซ่อนปุ่มโหลดเพิ่มเติม
  }

  // Update table info
  document.getElementById("tempTableInfo").textContent =
    // อัปเดตข้อมูลในตาราง
    `แสดง ${Math.min(tbody.rows.length, filteredTempData.length)} จาก ${filteredTempData.length} รายการ` // แสดงจำนวนรายการในตาราง
})

// Load more humidity data
document.getElementById("loadMoreHumid").addEventListener("click", function (event) {
  // โหลดข้อมูลความชื้นเพิ่มเติม
  event.preventDefault() // ป้องกันการโหลดหน้าใหม่
  isHumidFullyLoaded = true // ตั้งค่าสถานะว่าโหลดข้อมูลทั้งหมดแล้ว
  updateHumidityTable(latestHumidityData) // Update table with all data // อัปเดตตารางด้วยข้อมูลทั้งหมด
  // Hide button if all data is loaded
  if (filteredHumidData.length <= entriesPerPage || tbody.rows.length >= filteredHumidData.length) {
    // ถ้าข้อมูลทั้งหมดโหลดแล้ว
    this.style.display = "none" // ซ่อนปุ่มโหลดเพิ่มเติม
  }

  // Update table info
  document.getElementById("humidTableInfo").textContent =
    // อัปเดตข้อมูลในตาราง
    `แสดง ${Math.min(tbody.rows.length, filteredHumidData.length)} จาก ${filteredHumidData.length} รายการ` // แสดงจำนวนรายการในตาราง
})

let latestTemperatureData = [] // ตัวแปรสำหรับเก็บข้อมูลอุณหภูมิล่าสุด
let latestHumidityData = [] // ตัวแปรสำหรับเก็บข้อมูลความชื้นล่าสุด

function refreshAllData() {
  // รีเฟรชข้อมูลทั้งหมด
  fetch("/api/data") // ดึงข้อมูลจาก API
    .then((res) => res.json()) // แปลงข้อมูลเป็น JSON
    .then((data) => {
      // ประมวลผลข้อมูล
      latestTemperatureData = data // เก็บข้อมูลอุณหภูมิล่าสุด
      latestHumidityData = data // เก็บข้อมูลความชื้นล่าสุด

      // Update temperature and humidity tables
      updateTemperatureTable(data) // อัปเดตตารางอุณหภูมิ
      updateHumidityTable(data) // อัปเดตตารางความชื้น
    }) // Refresh all data
}

// Toggle filter for abnormal data
document.getElementById("toggleFilter").addEventListener("change", function () {
  // สลับการกรองข้อมูลที่ผิดปกติ
  showOnlyAbnormal = this.checked // ตั้งค่าตัวแปร showOnlyAbnormal ตามสถานะของ checkbox

  // Reset pagination
  tempCurrentPage = 1 // ตั้งค่าหน้าปัจจุบันของตารางอุณหภูมิเป็น 1
  humidCurrentPage = 1 // ตั้งค่าหน้าปัจจุบันของตารางความชื้นเป็น 1

  // Re-filter the data
  filteredTempData = showOnlyAbnormal ? allTempData.filter((item) => item.isAbnormal) : allTempData // กรองข้อมูลอุณหภูมิ
  filteredHumidData = showOnlyAbnormal ? allHumidData.filter((item) => item.isAbnormal) : allHumidData // กรองข้อมูลความชื้น

  // Update tables with filtered data
  const tempTable = document.querySelector("#temperatureTable tbody") // ดึง tbody ของตารางอุณหภูมิจาก DOM
  const humidTable = document.querySelector("#humidityTable tbody") // ดึง tbody ของตารางความชื้นจาก DOM

  tempTable.innerHTML = "" // ล้างข้อมูลใน tbody ของตารางอุณหภูมิ
  humidTable.innerHTML = "" // ล้างข้อมูลใน tbody ของตารางความชื้น

  // Show first page of each table
  const tempEndIdx = Math.min(entriesPerPage, filteredTempData.length) // กำหนดจำนวนรายการที่จะแสดงในตารางอุณหภูมิ
  const humidEndIdx = Math.min(entriesPerPage, filteredHumidData.length) // กำหนดจำนวนรายการที่จะแสดงในตารางความชื้น

  // Update temperature table
  for (let i = 0; i < tempEndIdx; i++) {
    // วนลูปเพื่อสร้างแถวในตารางอุณหภูมิ
    const item = filteredTempData[i] // ดึงข้อมูลอุณหภูมิ
    const row = tempTable.insertRow() // สร้างแถวใหม่ใน tbody
    row.classList.add("fade-in") // เพิ่มคลาส fade-in เพื่อให้มีเอฟเฟกต์การแสดงผล
    row.style.animationDelay = `${i * 0.05}s` // กำหนดเวลาในการแสดงผลของแต่ละแถว

    const cell1 = row.insertCell(0)
    const cell2 = row.insertCell(1)
    const cell3 = row.insertCell(2)
    const cell4 = row.insertCell(3)

    cell1.textContent = i + 1
    cell2.innerHTML = `<span style="${item.tempStyle}">${item.temp}${item.tempIcon}</span>` // แสดงค่าอุณหภูมิ
    cell3.textContent = item.datetime // แสดงวันที่และเวลา
    cell4.innerHTML = `<span class="${item.statusClass}">${item.status}</span>` // แสดงข้อความสถานะ
  }

  // Update humidity table
  for (let i = 0; i < humidEndIdx; i++) {
    // วนลูปเพื่อสร้างแถวในตารางความชื้น
    const item = filteredHumidData[i] // ดึงข้อมูลความชื้น
   
    const row = humidTable.insertRow() // สร้างแถวใหม่ใน tbody
    row.classList.add("fade-in") // เพิ่มคลาส fade-in เพื่อให้มีเอฟเฟกต์การแสดงผล
    row.style.animationDelay = `${i * 0.05}s` //  กำหนดเวลาในการแสดงผลของแต่ละแถว

    row.innerHTML = `
            <td>${i + 1}</td>
            <td style="${item.humidityStyle}">${item.humidity}${item.humidityIcon}</td>
            <td>${item.datetime}</td>
            <td><span class="${item.statusClass}">${item.status}</span></td>
        `
  }

  // Update load more buttons visibility
  document.getElementById("loadMoreTemp").style.display = // แสดงหรือซ่อนปุ่มโหลดเพิ่มเติมตามจำนวนข้อมูล
    filteredTempData.length > entriesPerPage ? "inline-flex" : "none" // แสดงหรือซ่อนปุ่มโหลดเพิ่มเติมตามจำนวนข้อมูล
  document.getElementById("loadMoreHumid").style.display = // แสดงหรือซ่อนปุ่มโหลดเพิ่มเติมตามจำนวนข้อมูล
    filteredHumidData.length > entriesPerPage ? "inline-flex" : "none" // แสดงหรือซ่อนปุ่มโหลดเพิ่มเติมตามจำนวนข้อมูล

  // Update table info
  document.getElementById("tempTableInfo").textContent = `แสดง ${tempEndIdx} จาก ${filteredTempData.length} รายการ` // อัปเดตข้อมูลในตาราง
  document.getElementById("humidTableInfo").textContent = `แสดง ${humidEndIdx} จาก ${filteredHumidData.length} รายการ` // อัปเดตข้อมูลในตาราง
})

document.getElementById("searchBoxTemp").addEventListener("input", function () {
  // ค้นหาข้อมูลอุณหภูมิ
  const searchValue = this.value.toLowerCase() // แปลงค่าที่ค้นหาเป็นตัวพิมพ์เล็ก

  if (searchValue === "") {
    // ถ้าค่าที่ค้นหาเป็นค่าว่าง
    // Reset to showing first page
    updateTemperatureTable({
      // อัปเดตตารางอุณหภูมิ
      temperature_data_graph: allTempData.map((item) => item.temp), // แสดงข้อมูลอุณหภูมิทั้งหมด
      datetime_data_graph: allTempData.map((item) => item.datetime), // แสดงวันที่และเวลา
    })
    return
  }

  // Filter the rows based on search
  const searchResults = allTempData.filter(
    // กรองข้อมูลอุณหภูมิ
    (
      item, //  ตรวจสอบค่าที่ค้นหา
    ) =>
      item.temp
        .toString()
        .includes(searchValue) || // ตรวจสอบค่าที่ค้นหาในอุณหภูมิ
      item.datetime.toLowerCase().includes(searchValue) || // ตรวจสอบค่าที่ค้นหาในวันที่และเวลา
      item.status.toLowerCase().includes(searchValue), // ตรวจสอบค่าที่ค้นหาในสถานะ
  )

  // Update filteredTempData with search results
  filteredTempData = searchResults // อัปเดตข้อมูลอุณหภูมิที่กรองแล้ว

  // Clear the table
  const tbody = document.querySelector("#temperatureTable tbody") // ดึง tbody ของตารางอุณหภูมิจาก DOM
  tbody.innerHTML = "" // ล้างข้อมูลใน tbody

  // Display all search results
  for (let i = 0; i < searchResults.length; i++) {
    // วนลูปเพื่อสร้างแถวในตาราง
    const item = searchResults[i] // ดึงข้อมูลอุณหภูมิ
    const row = tbody.insertRow() // สร้างแถวใหม่ใน tbody

    const cell1 = row.insertCell(0)
    const cell2 = row.insertCell(1)
    const cell3 = row.insertCell(2)
    const cell4 = row.insertCell(3)

    cell1.textContent = i + 1
    cell2.innerHTML = `<span style="${item.tempStyle}">${item.temp}${item.tempIcon}</span>` // แสดงค่าอุณหภูมิ
    cell3.textContent = item.datetime // แสดงวันที่และเวลา
    cell4.innerHTML = `<span class="${item.statusClass}">${item.status}</span>` // แสดงข้อความสถานะ
  }

  // Hide load more button during search
  document.getElementById("loadMoreTemp").style.display = "none" // ซ่อนปุ่มโหลดเพิ่มเติมระหว่างการค้นหา

  // Update table info
  document.getElementById("tempTableInfo").textContent =
    //  อัปเดตข้อมูลในตาราง
    `แสดง ${searchResults.length} จาก ${searchResults.length} รายการ (กำลังค้นหา)` // แสดงจำนวนรายการในตาราง
})

document.getElementById("searchBoxHumidity").addEventListener("input", function () {
  // ค้นหาข้อมูลความชื้น
  const searchValue = this.value.toLowerCase() // แปลงค่าที่ค้นหาเป็นตัวพิมพ์เล็ก

  if (searchValue === "") {
    // ถ้าค่าที่ค้นหาเป็นค่าว่าง
    // Reset to showing first page only
    updateHumidityTable({
      // อัปเดตตารางความชื้น
      humidity_data_graph: allHumidData.map((item) => item.humidity), // แสดงข้อมูลความชื้นทั้งหมด
      datetime_data_graph: allHumidData.map((item) => item.datetime), // แสดงวันที่และเวลา
    })
    return
  }

  // Filter the rows based on search
  const searchResults = allHumidData.filter(
    // กรองข้อมูลความชื้น
    (
      item, // ตรวจสอบค่าที่ค้นหา
    ) =>
      item.humidity
        .toString()
        .includes(searchValue) || // ตรวจสอบค่าที่ค้นหาในความชื้น
      item.datetime.toLowerCase().includes(searchValue) || // ตรวจสอบค่าที่ค้นหาในวันที่และเวลา
      item.status.toLowerCase().includes(searchValue), // ตรวจสอบค่าที่ค้นหาในสถานะ
  )

  // Update filteredHumidData with search results
  filteredHumidData = searchResults // อัปเดตข้อมูลความชื้นที่กรองแล้ว

  // Clear the table
  const tbody = document.querySelector("#humidityTable tbody") // ดึง tbody ของตารางความชื้นจาก DOM
  tbody.innerHTML = "" // ล้างข้อมูลใน tbody

  // Display all search results
  for (let i = 0; i < searchResults.length; i++) {
    // วนลูปเพื่อสร้างแถวในตาราง
    const item = searchResults[i] // ดึงข้อมูลความชื้น
    const row = tbody.insertRow() // สร้างแถวใหม่ใน tbody

    row.innerHTML = `   
            <td>${i + 1}</td>
            <td style="${item.humidityStyle}">${item.humidity}${item.humidityIcon}</td>
            <td>${item.datetime}</td>
            <td><span class="${item.statusClass}">${item.status}</span></td>
        `
  }

  // Hide load more button during search
  document.getElementById("loadMoreHumid").style.display = "none" // ซ่อนปุ่มโหลดเพิ่มเติมระหว่างการค้นหา

  // Update table info
  document.getElementById("humidTableInfo").textContent =
    // อัปเดตข้อมูลในตาราง
    `แสดง ${searchResults.length} จาก ${searchResults.length} รายการ (กำลังค้นหา)` // แสดงจำนวนรายการในตาราง
})

document.getElementById("toggleDarkMode").addEventListener("change", function () {
  // สลับโหมดมืด
  document.body.classList.toggle("dark-mode", this.checked) // สลับคลาส dark-mode ใน body

  // Update chart colors for dark mode
  if (this.checked) {
    // ถ้าเปิดโหมดมืด
    temperatureChart.options.scales.y.grid.color = "rgba(255, 255, 255, 0.1)"
    humidityChart.options.scales.y.grid.color = "rgba(255, 255, 255, 0.1)"
  } else {
    // ถ้าไม่เปิดโหมดมืด
    temperatureChart.options.scales.y.grid.color = "rgba(0, 0, 0, 0.05)"
    humidityChart.options.scales.y.grid.color = "rgba(0, 0, 0, 0.05)"
  }

  temperatureChart.update() // อัปเดตกราฟอุณหภูมิ
  humidityChart.update() // อัปเดตกราฟความชื้น
})

document.getElementById("timePeriod").addEventListener("change", () => {
  // เปลี่ยนช่วงเวลา
  // Reset pagination when changing time period
  tempCurrentPage = 1 // ตั้งค่าหน้าปัจจุบันของตารางอุณหภูมิเป็น 1
  humidCurrentPage = 1 // ตั้งค่าหน้าปัจจุบันของตารางความชื้นเป็น 1
  fetchDataAndUpdate() // รีเฟรชข้อมูล
})

// Check for system dark mode preference
if (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) {
  // ตรวจสอบการตั้งค่าโหมดมืดของระบบ
  document.getElementById("toggleDarkMode").checked = true //   ตั้งค่า checkbox เป็น true
  document.body.classList.add("dark-mode") // เพิ่มคลาส dark-mode ใน body
}

// Chart.js setup
const temperatureChart = new Chart(document.getElementById("temperatureChart").getContext("2d"), {
  type: "line", 
  data: {
    labels: [],
    datasets: [
      {
        label: "Temperature (°C)",
        data: [],
        borderColor: "#f87171",
        backgroundColor: "rgba(239, 68, 68, 0.1)",
        borderWidth: 2,
        tension: 0.3,
        fill: true,
        pointBackgroundColor: "#f87171",
        pointRadius: 3,
        pointHoverRadius: 5,
      },
    ],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: "top",
        labels: {
          font: {
            family: "Poppins",
            size: 12,
          },
        },
      },
      tooltip: {
        mode: "index",
        intersect: false,
        backgroundColor: "rgba(0, 0, 0, 0.7)", 
        titleFont: {
          family: "Poppins",
          size: 14,
        },
        bodyFont: {
          family: "Poppins",
          size: 13,
        },
        padding: 10,
        cornerRadius: 4,
      },
    },
    scales: {
      y: {
        beginAtZero: false,
        grid: {
          color: "rgba(0, 0, 0, 0.05)",
        },
        ticks: {
          font: {
            family: "Poppins",
            size: 11,
          },
        },
      },
      x: {
        reverse: true, // Change to true to show newest data on the right
        display: false,
        grid: {
          display: false,
        },
      },
    },
  },
})

const humidityChart = new Chart(document.getElementById("humidityChart").getContext("2d"), {
  type: "line",
  data: {
    labels: [],
    datasets: [
      {
        label: "Humidity (%)",
        data: [],
        borderColor: "#4cc9f0",
        backgroundColor: "rgba(59, 130, 246, 0.1)",
        borderWidth: 2,
        tension: 0.3,
        fill: true,
        pointBackgroundColor: "#4cc9f0", 
        pointRadius: 3,
        pointHoverRadius: 5,
      },
    ],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: "top",
        labels: {
          font: {
            family: "Poppins",
            size: 12,
          },
        },
      },
      tooltip: {
        mode: "index",
        intersect: false,
        backgroundColor: "rgba(0, 0, 0, 0.7)",
        titleFont: {
          family: "Poppins",
          size: 14,
        },
        bodyFont: {
          family: "Poppins",
          size: 13,
        },
        padding: 10,
        cornerRadius: 4,
      },
    },
    scales: {
      y: {
        beginAtZero: false,
        grid: {
          color: "rgba(0, 0, 0, 0.05)",
        },
        ticks: {
          font: {
            family: "Poppins",
            size: 11,
          },
        },
      },
      x: {
        reverse: true, // Change to true to show newest data on the right
        display: false,
        grid: {
          display: false,
        },
      },
    },
  },
})

document.getElementById("downloadBtn").addEventListener("click", () => {
  // ดาวน์โหลดข้อมูล
  const timePeriod = document.getElementById("timePeriod").value
  fetch(`getData.php?timePeriod=${timePeriod}&room=${currentRoom}`)
    .then((res) => res.json())
    .then((data) => {
      const csv =
        "Datetime,Temperature,Humidity\n" +
        data.datetime_data_graph
          .map((t, i) => `${t},${data.temperature_data_graph[i]},${data.humidity_data_graph[i]}`)
          .join("\n")
      const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" })
      const url = URL.createObjectURL(blob)
      const link = document.createElement("a")
      link.setAttribute("href", url)
      link.setAttribute("download", `sensor_data_${currentRoom}_${new Date().toISOString().split("T")[0]}.csv`)
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
    })
    .catch((err) => {
      console.error("Download error:", err)
      showPopup("ไม่สามารถดาวน์โหลดข้อมูลได้ กรุณาลองใหม่อีกครั้ง")
    })
})

// Check login status on page load
checkLoginStatus() //เช็คสถานะการเข้าสู่ระบบเมื่อโหลดหน้า

// Initial load
loadRooms() //โหลดห้องตอนแรก
fetchRoomStatus() //โหลดสถานะห้องตอนแรก
// Fetch initial data
fetchDataAndUpdate() //โหลดข้อมูลเริ่มต้น

// Room management modal functionality
document.getElementById("manageRoomsBtn").addEventListener("click", () => {
  //เปิดโมดัลการจัดการห้อง
  // Populate the room select dropdown
  loadRooms() //โหลดห้อง
  loadRoomsForDelete() //โหลดห้องสำหรับลบ
  document.getElementById("roomModal").style.display = "flex" // แสดงโมดัลการจัดการห้อง
})

document.getElementById("roomModalClose").addEventListener("click", () => {
  // ปิดโมดัลการจัดการห้อง
  document.getElementById("roomModal").style.display = "none" // ซ่อนโมดัลการจัดการห้อง
})

document.getElementById("addRoomTab").addEventListener("click", () => {
  // สลับไปที่แท็บเพิ่มห้อง
  document.getElementById("addRoomTab").classList.add("active") //  เพิ่มคลาส active
  document.getElementById("editRoomTab").classList.remove("active") // ลบคลาส active
  document.getElementById("deleteRoomTab").classList.remove("active") // ลบคลาส active
  document.getElementById("addRoomForm").classList.add("active") // แสดงฟอร์มเพิ่มห้อง
  document.getElementById("editRoomForm").classList.remove("active") // ซ่อนฟอร์มแก้ไขห้อง
  document.getElementById("deleteRoomForm").classList.remove("active") // ซ่อนฟอร์มลบห้อง
})

document.getElementById("editRoomTab").addEventListener("click", () => {
  // สลับไปที่แท็บแก้ไขห้อง
  document.getElementById("editRoomTab").classList.add("active") // เพิ่มคลาส active
  document.getElementById("addRoomTab").classList.remove("active") // ลบคลาส active
  document.getElementById("deleteRoomTab").classList.remove("active") // ลบคลาส active
  document.getElementById("editRoomForm").classList.add("active") //  แสดงฟอร์มแก้ไขห้อง
  document.getElementById("addRoomForm").classList.remove("active") // ซ่อนฟอร์มเพิ่มห้อง
  document.getElementById("deleteRoomForm").classList.remove("active") // ซ่อนฟอร์มลบห้อง

  // Refresh the room list when switching to edit tab
  loadRooms() //โหลดห้อง
})

document.getElementById("deleteRoomTab").addEventListener("click", () => {
  // สลับไปที่แท็บลบห้อง
  document.getElementById("deleteRoomTab").classList.add("active") // เพิ่มคลาส active
  document.getElementById("addRoomTab").classList.remove("active") // ลบคลาส active
  document.getElementById("editRoomTab").classList.remove("active") // ลบคลาส active
  document.getElementById("addRoomForm").classList.remove("active") // ซ่อนฟอร์มเพิ่มห้อง
  document.getElementById("editRoomForm").classList.remove("active") // ซ่อนฟอร์มแก้ไขห้อง
  document.getElementById("deleteRoomForm").classList.add("active") // แสดงฟอร์มลบห้อง

  // Refresh the room list when switching to delete tab
  loadRoomsForDelete() //โหลดห้องสำหรับลบ
})

// Load rooms for the dropdown
function loadRooms() {
  //โหลดห้อง
  const userId = currentUser ? currentUser.user_id : null // เช็คสถานะผู้ใช้
  const url = userId ? `get_rooms.php?user_id=${userId}` : "get_rooms.php" // กำหนด URL สำหรับดึงข้อมูลห้อง

  fetch(url) // ดึงข้อมูลห้องจากเซิร์ฟเวอร์
    .then((response) => response.json()) // แปลงข้อมูลเป็น JSON
    .then((data) => {
      // ประมวลผลข้อมูลห้อง
      if (data.success) {
        // ถ้าดึงข้อมูลสำเร็จ
        const selectRoom = document.getElementById("selectRoom") // ดึง select element สำหรับห้อง
        selectRoom.innerHTML = "" // ล้างข้อมูลใน select element

        data.rooms.forEach((room) => {
          // วนลูปเพื่อสร้างตัวเลือกห้อง
          const option = document.createElement("option") // สร้างตัวเลือกใหม่
          option.value = room.id // กำหนดค่า id ของห้อง
          option.textContent = room.name // กำหนดชื่อห้อง
          selectRoom.appendChild(option) // เพิ่มตัวเลือกใน select element
        })

        // Also update the room buttons in the sidebar
        updateRoomButtons(data.rooms) // อัปเดตปุ่มห้องในแถบด้านข้าง

        // If this is the first load and no room is selected yet, select the first room
        if (!currentRoom && data.rooms.length > 0) {
          // ถ้าไม่มีห้องที่เลือกและมีห้องในระบบ
          switchRoom(data.rooms[0].name.replace(/\s+/g, "")) // สลับไปที่ห้องแรก
        }
      }
    })
    .catch((error) => {
      // ถ้ามีข้อผิดพลาดในการดึงข้อมูล
      console.error("Error loading rooms:", error)
      // Show error message to user
      showPopup("ไม่สามารถโหลดข้อมูลห้องได้ กรุณาลองใหม่อีกครั้ง")
    })
}
// Load rooms for the delete dropdown
function loadRoomsForDelete() {
  //โหลดห้องสำหรับลบ
  const userId = currentUser ? currentUser.user_id : null // เช็คสถานะผู้ใช้
  const url = userId ? `get_rooms.php?user_id=${userId}` : "get_rooms.php" // กำหนด URL สำหรับดึงข้อมูลห้อง

  fetch(url) // ดึงข้อมูลห้องจากเซิร์ฟเวอร์
    .then((response) => response.json()) //แปลงข้อมูลเป็น JSON
    .then((data) => {
      // ประมวลผลข้อมูลห้อง
      if (data.success) {
        // ถ้าดึงข้อมูลสำเร็จ
        const selectRoom = document.getElementById("deleteRoomSelect") // ดึง select element สำหรับลบห้อง
        selectRoom.innerHTML = "" // ล้างข้อมูลใน select element

        data.rooms.forEach((room) => {
          // วนลูปเพื่อสร้างตัวเลือกห้อง
          // Skip the default rooms (id 1-3)
          if (room.id <= 3) {
            // ถ้าเป็นห้องเริ่มต้น
            return // ข้ามห้องเริ่มต้น
          }

          const option = document.createElement("option") // สร้างตัวเลือกใหม่
          option.value = room.id // กำหนดค่า  id ของห้อง
          option.textContent = room.name // กำหนดชื่อห้อง
          selectRoom.appendChild(option) // เพิ่มตัวเลือกใน select element
        })

        // Show message if no rooms can be deleted
        if (selectRoom.options.length === 0) {
          // ถ้าไม่มีห้องให้ลบ
          const option = document.createElement("option") // สร้างตัวเลือกใหม่
          option.value = "" // กำหนดตัวเลือกเป็นค่าว่าง
          option.textContent = "No custom rooms available to delete" // กำหนดข้อความ
          option.disabled = true //ทำให้ตัวเลือกไม่สามารถเลือกได้
          option.selected = true // ทำให้ตัวเลือกถูกเลือก
          selectRoom.appendChild(option) // เพิ่มตัวเลือกใน select element
        }
      }
    })
    .catch((error) => {
      // ถ้าไม่มีข้อผิดพลาดในการดึงข้อมูล
      console.error("Error loading rooms for deletion:", error) // แสดงข้อผิดพลาดใน console
    })
}

// อัปเดตปุ่มห้องในแถบด้านข้าง
function updateRoomButtons(rooms) {
  //
  const roomButtonsContainer = document.querySelector(".room-buttons") // ดึง container สำหรับปุ่มห้อง
  roomButtonsContainer.innerHTML = "" // ล้างข้อมูลใน container

  rooms.forEach((room) => {
    // วนลูปเพื่อสร้างปุ่มห้อง
    const button = document.createElement("button") // สร้างปุ่มใหม่
    button.id = `btn${room.name.replace(/\s+/g, "")}` // กำหนด id ของปุ่ม
    button.className = "room-btn" // กำหนดคลาสของปุ่ม

    // Create a text node for the room name
    const textNode = document.createTextNode(room.name) // สร้างข้อความสำหรับชื่อห้อง
    button.appendChild(textNode) // เพิ่มข้อความในปุ่ม

    // Add sensor values container
    const sensorValues = document.createElement("div") // สร้าง container สำหรับแสดงค่าของเซ็นเซอร์
    sensorValues.className = "room-sensor-values" // กำหนดคลาสของ container
    sensorValues.innerHTML = "<span>Loading...</span>" // แสดงข้อความกำลังโหลด
    button.appendChild(sensorValues) //เพิ่ม container ในปุ่ม

    button.onclick = () => {
      // เมื่อคลิกปุ่ม
      switchRoom(room.name.replace(/\s+/g, "")) // สลับไปที่ห้องที่เลือก
    }

    if (currentRoom === room.name.replace(/\s+/g, "")) {
      // ถ้าห้องที่เลือกตรงกับห้องปัจจุบัน
      button.classList.add("active") // เพิ่มคลาส active ในปุ่ม
    }

    roomButtonsContainer.appendChild(button) // เพิ่มปุ่มใน container
  })

  // After adding buttons, fetch their status
  fetchRoomStatus() // โหลดสถานะห้อง
}

// Add this function after the loadRooms function
function fetchRoomStatus() {
  // โหลดสถานะห้อง
  const userId = currentUser ? currentUser.user_id : null // เช็คสถานะผู้ใช้
  const url = userId ? `get_room_status.php?user_id=${userId}` : "get_room_status.php" // กำหนด URL สำหรับดึงข้อมูลสถานะห้อง

  fetch(url) // ดึงข้อมูลสถานะห้องจากเซิร์ฟเวอร์
    .then((response) => response.json()) // แปลงข้อมูลเป็น JSON
    .then((data) => {
      // ประมวลผลข้อมูลสถานะห้อง
      if (data.success) {
        // ถ้าดึงข้อมูลสำเร็จ
        updateRoomButtonStatus(data.rooms) // อัปเดตสถานะปุ่มห้อง
      }
    })
    .catch((error) => {
      // ถ้ามีข้อผิดพลาดในการดึงข้อมูล
      console.error("Error loading room status:", error) // แสดงข้อผิดพลาดใน console
    })
}

// Update the updateRoomButtonStatus function to include humidity data and color-coding
function updateRoomButtonStatus(rooms) {
  // อัปเดตสถานะปุ่มห้อง
  rooms.forEach((room) => {
    // วนลูปเพื่ออัปเดตสถานะห้อง
    const buttonId = `btn${room.name.replace(/\s+/g, "")}` // กำหนด id ของปุ่ม
    const button = document.getElementById(buttonId) // ดึงปุ่มจาก DOM

    if (button) {
      // ถ้าปุ่มมีอยู่ใน DOM
      // Remove existing status classes
      button.classList.remove("temp-high", "temp-low", "temp-normal", "humid-high", "humid-low", "humid-normal") // ลบคลาสสถานะที่มีอยู่

      // Add appropriate temperature status class
      if (room.temp_status === "high") {
        // ถ้าสถานะอุณหภูมิสูง
        button.classList.add("temp-high") // เพิ่มคลาสสถานะอุณหภูมิสูง
      } else if (room.temp_status === "low") {
        // ถ้าสถานะอุณหภูมิต่ำ
        button.classList.add("temp-low") // เพิ่มคลาสสถานะอุณหภูมิต่ำ
      } else {
        // ถ้าสถานะอุณหภูมิปกติ
        button.classList.add("temp-normal") // เพิ่มคลาสสถานะอุณหภูมิปกติ
      }

      // Add appropriate humidity status class
      if (room.humid_status === "high") {
        // ถ้าสถานนะความชื้นสูง
        button.classList.add("humid-high") // เพิ่มคลาสสถานะความชื้นสูง
      } else if (room.humid_status === "low") {
        // ถ้าสถานะความชื้นต่ำ
        button.classList.add("humid-low") // เพิ่มคลาสสถานะความชื้นต่ำ
      } else {
        // ถ้าสถานะความชื้นปกติ
        button.classList.add("humid-normal") // เพิ่มคลาสสถานะความชื้นปกติ
      }

      // Update or add sensor values display
      let sensorValues = button.querySelector(".room-sensor-values") // ดึง container สำหรับแสดงค่าของเซ็นเซอร์
      if (!sensorValues) {
        // ถ้าไม่มี container สำหรับแสดงค่าของเซ็นเซอร์
        sensorValues = document.createElement("div") // สร้าง container ใหม้
        sensorValues.className = "room-sensor-values" // กำหนดคลาสของ container
        button.appendChild(sensorValues) // เพิ่ม container ในปุ่ม
      }

      // Clear previous content
      sensorValues.innerHTML = "" // ล้างข้อมูลใน container

      // Add temperature display
      if (room.temperature !== null) {
        // ถ้ามีข้อมูลอุณหภูมิ
        const tempElement = document.createElement("span") // สร้าง element สำหรับแสดงอุณหภูมิ
        tempElement.className = "room-temp" // กำหนดคลาสของ element
        tempElement.textContent = `${room.temperature}°C` // กำหนดข้อความอุณหภูมิ
        sensorValues.appendChild(tempElement) // เพิ่ม element ใน container
      }

      // Add humidity display
      if (room.humidity !== null) {
        // ถ้ามีข้อมูลความชื้น
        const humidElement = document.createElement("span") // สร้าง element สำหรับแสดงความชื้น
        humidElement.className = "room-humidity" // กำหนดคลาสของ element
        humidElement.textContent = `${room.humidity}%` // กำหนดข้อความความชื้น
        sensorValues.appendChild(humidElement) // เพิ่ม element ใน container
      }

      // If no data available
      if (room.temperature === null && room.humidity === null) {
        // ถ้าไม่มีข้อมูล
        const noDataElement = document.createElement("span") // สร้าง element สำหรับแสดงข้อความไม่มีข้อมูล
        noDataElement.textContent = "No data" // กำหนดข้อความไม่มีข้อมูล
        sensorValues.appendChild(noDataElement) // เพิ่ม element ใน container
      }
    }
  })
}

// Add room form submission
document.getElementById("addRoomForm").addEventListener("submit", (e) => {
  // ฟอร์มเพิ่มห้อง
  e.preventDefault() // ป้องกันการส่งฟอร์ม

  const roomName = document.getElementById("newRoomName").value // ดึงชื่อห้องจากฟอร์ม
  const userId = currentUser ? currentUser.user_id : null // เช็คสถานะผู้ใช้

  if (!roomName) {
    // ถ้าไม่มีชื่อห้อง
    document.getElementById("addRoomError").textContent = "Room name is required"
    document.getElementById("addRoomError").style.display = "block"
    document.getElementById("addRoomSuccess").style.display = "none"
    return
  }

  fetch("add_room.php", {
    // ส่งข้อมูลห้องไปยังเซิร์ฟเวอร์
    method: "POST", // กำหนดวิธีการส่งข้อมูล
    headers: {
      // กำหนด header
      "Content-Type": "application/json", // กำหนดประเภทข้อมูลเป็น JSON
    },
    body: JSON.stringify({
      // แปลงข้อมูลเป็น JSON
      room_name: roomName, // กำหนดชื่อห้อง
      user_id: userId, // กำหนด id ของผู้ใช้
    }),
  })
    .then((response) => response.json()) // แปลงข้อมูลเป็น JSON
    .then((data) => {
      // ประมวลผลข้อมูลที่ส่งกลับจากเซิร์ฟเวอร์
      if (data.success) {
        // ถ้าสำเร็จ
        document.getElementById("addRoomSuccess").textContent = data.message // แสดงข้อความสำเร็จ
        document.getElementById("addRoomSuccess").style.display = "block" // แสดงข้อมูลสำเร็จ
        document.getElementById("addRoomError").style.display = "none" // ซ่อนข้อความผิดพลาด
        document.getElementById("newRoomName").value = "" // ล้างข้อมูลในฟอร์ม

        // Reload rooms
        loadRooms() // โหลดห้องใหม่

        // Auto switch to the new room after a delay
        setTimeout(() => {
          // สลับไปที่ห้องใหม่หลังจากดีเลย์
          switchRoom(data.room.name.replace(/\s+/g, "")) // สลับไปที่ห้องใหม่
          document.getElementById("roomModal").style.display = "none" // ซ่อนโมดัลการจัดการห้อง
        }, 1500) // ดีเลย์ 1.5 วินาที
      } else {
        // ถ้าล้มเหลว
        document.getElementById("addRoomError").textContent = data.message
        document.getElementById("addRoomError").style.display = "block"
        document.getElementById("addRoomSuccess").style.display = "none"
      }
    })
    .catch((error) => {
      // ถ้ามีข้อผิดพลาดในการส่งข้อมูล
      console.error("Error adding room:", error)
      document.getElementById("addRoomError").textContent = "An error occurred. Please try again."
      document.getElementById("addRoomError").style.display = "block"
      document.getElementById("addRoomSuccess").style.display = "none"
    })
})

// แก้ไขห้องฟอร์มการส่งข้อมูล
document.getElementById("editRoomForm").addEventListener("submit", (e) => {
  e.preventDefault() // ป้องกันการส่งฟอร์ม

  const roomId = document.getElementById("selectRoom").value // ดึง id  ของห้องจากฟอร์ม
  const roomName = document.getElementById("editRoomName").value // ดึงชื่อห้องจากฟอร์ม
  const userId = currentUser ? currentUser.user_id : null // เช็คสถานะผู้ใช้

  if (!roomId || !roomName) {
    // ถ้าไม่มี id หรือชื่อห้อง
    document.getElementById("editRoomError").textContent = "Please select a room and enter a new name" // แสดงข้อความผิดพลาด
    document.getElementById("editRoomError").style.display = "block" // แสดงข้อมูลผิดพลาด
    document.getElementById("editRoomSuccess").style.display = "none" // ซ่อนข้อมูลสำเร็จ
    return // ออกจากฟังก์ชัน
  }

  fetch("update_room.php", {
    // ส่งข้อมูลห้องไปยังเซิร์ฟเวอร์
    method: "POST", // กำหนดวิธีการส่งข้อมูล
    headers: {
      "Content-Type": "application/json", // กำหนดประเภทข้อมูลเป็น JSON
    },
    body: JSON.stringify({
      // แปลงข้อมูลเป็น  JSON
      room_id: roomId, //  id ของห้อง
      room_name: roomName, // ชื่อห้องใหม่
      user_id: userId, // id ของผู้ใช้
    }),
  })
    .then((response) => response.json()) // แปลงข้อมูลเป็น JSON
    .then((data) => {
      // ประมวลผลข้อมูลที่ส่งกลับจากเซิร์ฟเวอร์
      if (data.success) {
        // ถ้าสำเร็จ
        document.getElementById("editRoomSuccess").textContent = data.message
        document.getElementById("editRoomSuccess").style.display = "block"
        document.getElementById("editRoomError").style.display = "none"
        document.getElementById("editRoomName").value = ""

        // Reload rooms
        loadRooms() // โหลดห้องใหม่

        // Auto close after a delay
        setTimeout(() => {
          // ปิดโมดัลการจัดการห้องหลังจากดีเลย์
          document.getElementById("roomModal").style.display = "none" // ซ่อนโมดัลการจัดการห้อง
        }, 1500) // ดีเลย์ 1.5 วินาที
      } else {
        // ถ้าล้มเหลว
        document.getElementById("editRoomError").textContent = data.message // แสดงข้อความผิดพลาด
        document.getElementById("editRoomError").style.display = "block" // แสดงข้อมูลผิดพลาด
        document.getElementById("editRoomSuccess").style.display = "none"
      }
    })
    .catch((error) => {
      //ถ้ามีข้อผิดพลาดในการส่งข้อมูล
      console.error("Error updating room:", error)
      document.getElementById("editRoomError").textContent = "An error occurred. Please try again."
      document.getElementById("editRoomError").style.display = "block"
      document.getElementById("editRoomSuccess").style.display = "none"
    })
})

//ลบห้องฟอร์มการส่งข้อมูล
document.getElementById("deleteRoomForm").addEventListener("submit", (e) => {
  e.preventDefault() // ป้องกันการส่งฟอร์ม

  const roomId = document.getElementById("deleteRoomSelect").value // ดึง id ของห้องจากฟอร์ม
  const userId = currentUser ? currentUser.user_id : null // เช็คสถานะผู้ใช้

  if (!roomId) {
    // ถ้าไม่มี id ของห้อง
    document.getElementById("deleteRoomError").textContent = "Please select a room to delete" // แสดงข้อความผิดพลาด
    document.getElementById("deleteRoomError").style.display = "block" // แสดงข้อมูลผิดพลาด
    document.getElementById("deleteRoomSuccess").style.display = "none" // ซ่อนข้อมูลสำเร็จ
    return // ออกจากฟังก์ชัน
  }

  // Confirm deletion
  if (!confirm("Are you s ure you want to delete this room? This action cannot be undone!")) {
    // ถ้าไม่มีการยืนยันการลบ
    return // ออกจากฟังก์ชัน
  }

  fetch("delete_room.php", {
    // ส่งข้อมูลห้องไปยังเซิร์ฟเวอร์
    method: "POST", // กำหนดวิธีการส่งข้อมูล
    headers: {
      // กำหนด header
      "Content-Type": "application/json", // กำหนดประเภทข้อมูลเป็น JSON
    },
    body: JSON.stringify({
      // แปลงข้อมูลเป็น JSON
      room_id: roomId, // id ของห้อง
      user_id: userId, // id ของผู้ใช้
    }),
  })
    .then((response) => response.json()) // แปลงข้อมูลเป็น JSON
    .then((data) => {
      // ประมวลผลข้อมูลที่ส่งกลับจากเซิร์ฟเวอร์
      if (data.success) {
        // ถ้าสำเร็จ
        document.getElementById("deleteRoomSuccess").textContent = data.message // แสดงข้อความสำเร็จ
        document.getElementById("deleteRoomSuccess").style.display = "block" // แสดงข้อมูลสำเร็จ
        document.getElementById("deleteRoomError").style.display = "none" // ซ่อนข้อมูลผิดพลาด

        // Reload rooms
        loadRooms() // โหลดห้องใหม่
        loadRoomsForDelete() // โหลดห้องสำหรับลบใหม่

        // Switch to first room if needed
        switchRoom("Room1") // สลับไปที่ห้องแรก

        // Auto close after a delay
        setTimeout(() => {
          // ปิดโมดัลการจัดการห้องหลังจากดีเลย์
          document.getElementById("roomModal").style.display = "none" // ซ่อนโมดัลการจัดการห้อง
        }, 1500) // ดีเลย์ 1.5 วินาที
      } else {
        // ถ้าล้มเหลว
        document.getElementById("deleteRoomError").textContent = data.message // แสดงข้อความผิดพลาด
        document.getElementById("deleteRoomError").style.display = "block" // แสดงข้อมูลผิดพลาด
        document.getElementById("deleteRoomSuccess").style.display = "none" // ซ่อนข้อมูลสำเร็จ
      }
    })
    .catch((error) => {
      // ถ้ามีข้อผิดพลาดในการส่งข้อมูล
      console.error("Error deleting room:", error) // แสดงข้อผิดพลาดใน console
      document.getElementById("deleteRoomError").textContent = "An error occurred. Please try again." // แสดงข้อความผิดพลาด
      document.getElementById("deleteRoomError").style.display = "block" // แสดงข้อมูลผิดพลาด
      document.getElementById("deleteRoomSuccess").style.display = "none" // ซ่อนข้อมูลสำเร็จ
    })
})
