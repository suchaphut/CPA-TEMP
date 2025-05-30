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

// Get user_id parameter if provided (for user-specific rooms)
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Query to get all rooms
$query = "SELECT id, room_name FROM rooms";
if ($user_id) {
    $query .= " WHERE user_id = ? OR user_id IS NULL";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
} else {
    $stmt = $conn->prepare($query);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    $rooms = [];
    while ($row = $result->fetch_assoc()) {
        $rooms[] = [
            'id' => $row['id'],
            'name' => $row['room_name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'rooms' => $rooms
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch rooms: ' . $conn->error
    ]);
}

$stmt->close();
$conn->close();
?>
