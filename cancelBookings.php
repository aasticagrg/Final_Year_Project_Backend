<?php
// CORS headers
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include DB connection
include 'helpers/connection.php';

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

// Get raw input
$input = json_decode(file_get_contents("php://input"), true);
$booking_id = $input['booking_id'] ?? null;

// Validate booking ID
if (!$booking_id || !is_numeric($booking_id)) {
    echo json_encode(["success" => false, "message" => "Valid booking ID is required"]);
    exit;
}

// Prepare and execute query
$query = "UPDATE bookings SET booking_status = 'cancelled' WHERE booking_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $booking_id);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Booking cancelled successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to cancel booking"]);
}

$stmt->close();
$conn->close();
?>
