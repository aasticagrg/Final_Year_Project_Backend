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
include_once __DIR__ . '/helpers/connection.php';
include_once __DIR__ . '/helpers/auth_helper.php';

header('Content-Type: application/json');

// Get Authorization token
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
$userData = getUserIdFromToken($token);

if (!$userData['user_id']) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

$user_id = $userData['user_id'];

$sql = "SELECT 
            bp.property_id,
            p.property_name,
            p.city,
            b.booking_id,
            b.check_in_date,
            b.check_out_date,
            b.booking_status,
            pay.payment_status
        FROM bookings b
        INNER JOIN booking_properties bp ON b.booking_id = bp.booking_id
        INNER JOIN properties p ON bp.property_id = p.property_id
        LEFT JOIN payments pay ON pay.booking_id = b.booking_id
        WHERE b.user_id = ?
        ORDER BY b.check_in_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];

while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

echo json_encode([
    'success' => true,
    'data' => $bookings
]);
?>
