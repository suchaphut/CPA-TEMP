<?php
// Include database connection
require_once 'db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Validate required parameters
if (!isset($_GET['user_id']) || !isset($_GET['room_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing user_id or room_id parameter'
    ]);
    exit;
}

$user_id = intval($_GET['user_id']);
$room_id = intval($_GET['room_id']);

// Get room thresholds
$stmt = $conn->prepare("SELECT temp_min, temp_max, humidity_min, humidity_max FROM room_thresholds WHERE user_id = ? AND room_id = ?");
$stmt->bind_param("ii", $user_id, $room_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Get user default settings if room-specific settings don't exist
    $user_stmt = $conn->prepare("SELECT temp_min, temp_max, humidity_min, humidity_max FROM user_settings WHERE user_id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    
    if ($user_result->num_rows === 0) {
        // Create default settings if none exist
        $temp_min = 24;
        $temp_max = 25;
        $humidity_min = 50;
        $humidity_max = 60;
        
        // Insert default room thresholds
        $insert_stmt = $conn->prepare("INSERT INTO room_thresholds (room_id, user_id, temp_min, temp_max, humidity_min, humidity_max) VALUES (?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("iidddd", $room_id, $user_id, $temp_min, $temp_max, $humidity_min, $humidity_max);
        $insert_stmt->execute();
        $insert_stmt->close();
    } else {
        // Use user default settings for this room
        $user_settings = $user_result->fetch_assoc();
        $temp_min = $user_settings['temp_min'];
        $temp_max = $user_settings['temp_max'];
        $humidity_min = $user_settings['humidity_min'];
        $humidity_max = $user_settings['humidity_max'];
        
        // Insert room thresholds based on user defaults
        $insert_stmt = $conn->prepare("INSERT INTO room_thresholds (room_id, user_id, temp_min, temp_max, humidity_min, humidity_max) VALUES (?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("iidddd", $room_id, $user_id, $temp_min, $temp_max, $humidity_min, $humidity_max);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    
    if (isset($user_stmt)) {
        $user_stmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'thresholds' => [
            'temp_min' => $temp_min,
            'temp_max' => $temp_max,
            'humidity_min' => $humidity_min,
            'humidity_max' => $humidity_max
        ]
    ]);
} else {
    $thresholds = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'thresholds' => $thresholds
    ]);
}

$stmt->close();
$conn->close();
?>
