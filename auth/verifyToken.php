<?php
include_once __DIR__ . '/../helpers/connection.php';
include_once __DIR__ . '/../helpers/auth_helper.php';

header('Content-Type: application/json');

// Use getallheaders() instead of apache_request_headers()
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (!$authHeader) {
    echo json_encode([
        'success' => false,
        'message' => 'Authorization header missing'
    ]);
    exit;
}

$token = str_replace('Bearer ', '', $authHeader);

// Get user info
$userData = getUserIdFromToken($token);

if ($userData['user_id'] || $userData['vendor_id']) {
    echo json_encode([
        'success' => true,
        'message' => 'Token is valid',
        'data' => [
            'user_id' => $userData['user_id'],
            'vendor_id' => $userData['vendor_id'],
            'role' => $userData['role']
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or expired token'
    ]);
}
?>
