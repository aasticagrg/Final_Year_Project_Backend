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
    
    // Get the search query from the request
    $searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
    
    // Prepare the SQL query to fetch users, applying the search filter
    $query = "SELECT user_id, name, email, user_address, phone_no, role, user_verification, account_status, verification_status 
              FROM users WHERE role != 'admin' AND (name LIKE ? OR email LIKE ? OR phone_no LIKE ?)";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    // Bind the search term with wildcards for partial matching
    $searchTerm = "%" . $searchQuery . "%"; // Adding wildcards for LIKE query
    
    // Bind the search parameter
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
    
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];
    
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    // Return the results in JSON format
    echo json_encode(['success' => true, 'users' => $users]);
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>
