<?php
include_once 'helpers/connection.php';
include_once 'helpers/auth_helper.php';

header("Content-Type: application/json");

$headers = getallheaders();
$token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');

// Get user or vendor info from token
$userData = getUserIdFromToken($token);
$user_id = $userData['user_id'] ?? null;
$vendor_id = $userData['vendor_id'] ?? null;

$data = json_decode(file_get_contents("php://input"));
$review_id = $data->review_id ?? null;

if (!$review_id || !is_numeric($review_id)) {
    echo json_encode(["success" => false, "message" => "Invalid review ID"]);
    exit;
}

// Step 1: Fetch review info including property_id and user_id
$reviewQuery = $conn->prepare("SELECT user_id, property_id FROM reviews WHERE review_id = ?");
$reviewQuery->bind_param("i", $review_id);
$reviewQuery->execute();
$reviewResult = $reviewQuery->get_result();

if ($reviewResult->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Review not found"]);
    exit;
}

$review = $reviewResult->fetch_assoc();
$reviewUserId = $review['user_id'];
$propertyId = $review['property_id'];

// Step 2: Check if the logged-in user is the reviewer or the vendor who owns the property
$isReviewer = $user_id && $user_id == $reviewUserId;
$isVendorOwner = false;

if ($vendor_id) {
    $ownerCheck = $conn->prepare("SELECT property_id FROM properties WHERE property_id = ? AND vendor_id = ?");
    $ownerCheck->bind_param("ii", $propertyId, $vendor_id);
    $ownerCheck->execute();
    $ownerResult = $ownerCheck->get_result();
    $isVendorOwner = $ownerResult->num_rows > 0;
}

// Step 3: Authorization check
if (!$isReviewer && !$isVendorOwner) {
    echo json_encode(["success" => false, "message" => "You are not authorized to delete this review"]);
    exit;
}

// Step 4: Delete the review
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
