<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

include 'helpers/connection.php';
include 'helpers/auth_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['user_id']) || !isset($data['verification_status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

// Extract token
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = '';

if (strpos($authHeader, 'Bearer ') === 0) {
    $token = substr($authHeader, 7);
}

$userData = getUserIdFromToken($token);
if (!$userData['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit();
}

// Check if user is admin
$checkAdmin = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$checkAdmin->bind_param("i", $userData['user_id']);
$checkAdmin->execute();
$result = $checkAdmin->get_result();
$adminRow = $result->fetch_assoc();
$checkAdmin->close();

if (!$adminRow || $adminRow['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Allow only valid enum values
$allowedStatuses = ['verified', 'not verified'];
$status = $data['verification_status'];

if (!in_array($status, $allowedStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid verification status']);
    exit();
}

// Update verification status
$update = $conn->prepare("UPDATE users SET verification_status = ? WHERE user_id = ?");
$update->bind_param("si", $status, $data['user_id']);

if ($update->execute()) {
    // Set dynamic success message based on account status
    if ($status === 'not verified') {
        echo json_encode(['success' => true, 'message' => "User's verfication has  been rejected"]);
    } else {
        echo json_encode(['success' => true, 'message' => "User has been verified"]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update verification status']);
}

$update->close();
$conn->close();
?>
