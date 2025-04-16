<?php
include_once 'helpers/connection.php';
include_once 'helpers/auth_helper.php';

header("Content-Type: application/json");

$headers = getallheaders();
$token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
$userData = getUserIdFromToken($token);
$vendor_id = $userData['vendor_id'] ?? null;
$user_id = $userData['user_id'] ?? null;

$data = json_decode(file_get_contents("php://input"));
$review_id = $data->review_id ?? null;

if (!$review_id || !is_numeric($review_id)) {
    echo json_encode(["success" => false, "message" => "Invalid review ID"]);
    exit;
}

// Step 1: Get the property_id from the review
$reviewQuery = $conn->prepare("SELECT property_id FROM reviews WHERE review_id = ?");
$reviewQuery->bind_param("i", $review_id);
$reviewQuery->execute();
$reviewResult = $reviewQuery->get_result();

if ($reviewResult->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Review not found"]);
    exit;
}

$property_id = $reviewResult->fetch_assoc()['property_id'];

// Step 2: Check if the vendor owns the property
$ownershipCheck = $conn->prepare("SELECT * FROM properties WHERE property_id = ? AND vendor_id = ?");
$ownershipCheck->bind_param("ii", $property_id, $vendor_id);
$ownershipCheck->execute();
$ownerResult = $ownershipCheck->get_result();

if ($ownerResult->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "You do not own this property or are not authorized to delete this review"]);
    exit;
}

// Step 3: Delete the review
$deleteStmt = $conn->prepare("DELETE FROM reviews WHERE review_id = ?");
$deleteStmt->bind_param("i", $review_id);
$deleteStmt->execute();

if ($deleteStmt->affected_rows > 0) {
    echo json_encode(["success" => true, "message" => "Review deleted"]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to delete review"]);
}

$conn->close();
?>
