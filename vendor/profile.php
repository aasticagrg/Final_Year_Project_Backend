<?php
include '../../helpers/connection.php';

// Ensure only GET requests are processed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get the token from the Authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    echo json_encode(['success' => false, 'message' => 'Authorization token required']);
    exit();
}

$token = $matches[1];

// Validate the token and get vendor information with prepared statement
$sql = "SELECT v.* FROM tokens t 
        JOIN vendors v ON t.vendor_id = v.vendor_id 
        WHERE t.token = ? AND t.role = 'vendor'";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $token);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
    exit();
}

$vendor = mysqli_fetch_assoc($result);

// Remove sensitive data
unset($vendor['password']);

// Return complete vendor profile data
echo json_encode([
    'success' => true,
    'message' => 'Vendor profile retrieved successfully',
    'vendor' => $vendor
]);
?>