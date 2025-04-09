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

if (!isset($data['user_id']) || !isset($data['account_status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

// Extract and verify the token
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

// Check if the user is an admin
$checkAdmin = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$checkAdmin->bind_param("i", $userData['user_id']);
$checkAdmin->execute();
$adminResult = $checkAdmin->get_result();
$adminRow = $adminResult->fetch_assoc();
$checkAdmin->close();

if (!$adminRow || $adminRow['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate allowed enum values
$allowedStatuses = ['active', 'deactivated'];
$status = $data['account_status'];

if (!in_array($status, $allowedStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid account status']);
    exit();
}

// Update user account status
$update = $conn->prepare("UPDATE users SET account_status = ? WHERE user_id = ?");
$update->bind_param("si", $status, $data['user_id']);

if ($update->execute()) {
    // Set dynamic success message based on account status
    if ($status === 'deactivated') {
        echo json_encode(['success' => true, 'message' => "User's account is deactivated"]);
    } else {
        echo json_encode(['success' => true, 'message' => "User's account is activated"]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update account status']);
}

$update->close();
$conn->close();
?>
