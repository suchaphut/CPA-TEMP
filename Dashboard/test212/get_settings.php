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
if (!isset($_GET['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing user_id parameter'
    ]);
    exit;
}

$user_id = intval($_GET['user_id']);

// Get user settings
$stmt = $conn->prepare("SELECT temp_min, temp_max, humidity_min, humidity_max FROM user_settings WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Create default settings if none exist
    $temp_min = 24;
    $temp_max = 25;
    $humidity_min = 50;
    $humidity_max = 60;
    
    $insert_stmt = $conn->prepare("INSERT INTO user_settings (user_id, temp_min, temp_max, humidity_min, humidity_max) VALUES (?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("idddd", $user_id, $temp_min, $temp_max, $humidity_min, $humidity_max);
    $insert_stmt->execute();
    $insert_stmt->close();
    
    echo json_encode([
        'success' => true,
        'settings' => [
            'temp_min' => $temp_min,
            'temp_max' => $temp_max,
            'humidity_min' => $humidity_min,
            'humidity_max' => $humidity_max
        ]
    ]);
} else {
    $settings = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'settings' => $settings
    ]);
}

$stmt->close();
$conn->close();
?>
