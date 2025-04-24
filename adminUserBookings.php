<?php
include 'helpers/connection.php';
include 'helpers/auth_helper.php';

header('Content-Type: application/json');

// Get token from headers
$headers = apache_request_headers();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

// Check if admin
if (!isAdmin($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get user ID from GET params
$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing user_id']);
    exit;
}
error_log("Checking bookings for user_id: " . $user_id);

$sql = "SELECT 
            b.booking_id, 
            p.property_name, 
            b.check_in_date, 
            b.check_out_date, 
            b.booking_status,
            pay.method AS payment_method,
            pay.amount AS payment_amount,
            pay.payment_status
        FROM bookings b
        JOIN booking_properties bp ON b.booking_id = bp.booking_id
        JOIN properties p ON bp.property_id = p.property_id
        LEFT JOIN payments pay ON b.booking_id = pay.booking_id
        WHERE b.user_id = ?
          AND b.booking_status IN ('booked', 'cancelled')
          AND (pay.payment_status = 'completed' OR pay.payment_status = 'pending')";


$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();



$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

echo json_encode($bookings);
?>
