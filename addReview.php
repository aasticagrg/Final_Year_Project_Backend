<?php
include_once 'helpers/connection.php';
include_once 'helpers/auth_helper.php';

header("Content-Type: application/json");

// Read the token from Authorization header
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
$token = str_replace('Bearer ', '', $authHeader);

// Validate token
$userData = getUserIdFromToken($token);
$user_id = $userData['user_id'];

// Only users (not vendors or unauthenticated) can submit reviews
if (!$user_id) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

// Read body
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->property_id) || !isset($data->rating) || !isset($data->review_text)) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

$property_id = $data->property_id;
$rating = $data->rating;
$review_text = $data->review_text;

// Check if the user has a 'booked' status for the property
$stmt = $conn->prepare("
    SELECT * 
    FROM bookings b
    JOIN booking_properties bp ON b.booking_id = bp.booking_id
    WHERE b.user_id = ? AND bp.property_id = ? AND b.booking_status = 'booked'
");

$stmt->bind_param("ii", $user_id, $property_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(["success" => false, "message" => "You must book this property before reviewing"]);
    exit;
}

$stmt->close();

// Get vendor_id from the property
$stmt = $conn->prepare("SELECT vendor_id FROM properties WHERE property_id = ?");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(["success" => false, "message" => "Property not found"]);
    exit;
}

$vendor_id = $row['vendor_id'];

// Save the review
$stmt = $conn->prepare("INSERT INTO reviews (user_id, property_id, vendor_id, rating, review_text) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iiiis", $user_id, $property_id, $vendor_id, $rating, $review_text);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Review submitted successfully"]);
} else {
    echo json_encode(["success" => false, "error" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
