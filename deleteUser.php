<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'helpers/connection.php';
include 'helpers/auth_helper.php';

// Get token from Authorization header
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
$token = '';

if (strpos($authHeader, 'Bearer ') === 0) {
    $token = substr($authHeader, 7);
}

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'Token is required']);
    exit();
}

$userData = getUserIdFromToken($token);
if (!$userData['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit();
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
$targetUserId = $data['user_id'] ?? 0;

if (!$targetUserId) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

// Check if the user has admin role
try {
    $checkAdminQuery = "SELECT role FROM users WHERE user_id = ?";
    $checkStmt = $conn->prepare($checkAdminQuery);
    
    if (!$checkStmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $checkStmt->bind_param("i", $userData['user_id']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $adminData = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    if (!$adminData || $adminData['role'] != 'admin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    }
    
    // Delete related tokens first
    $deleteTokensQuery = "DELETE FROM tokens WHERE user_id = ?";
    $tokenStmt = $conn->prepare($deleteTokensQuery);
    
    if (!$tokenStmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $tokenStmt->bind_param("i", $targetUserId);
    $tokenStmt->execute();
    $tokenStmt->close();

    // Now delete the user
    $query = "DELETE FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $targetUserId);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'User removed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove user or user does not exist']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>