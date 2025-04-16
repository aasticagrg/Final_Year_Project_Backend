<?php
include_once 'helpers/connection.php';
include_once 'helpers/auth_helper.php';

header("Content-Type: application/json");
$headers = getallheaders();
$token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
$userData = getUserIdFromToken($token);

$vendor_id = $userData['vendor_id'] ?? null;

if (!$vendor_id) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}


if (!$vendor_id) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$stmt = $conn->prepare("SELECT r.*, u.name AS user_name, p.title AS property_title FROM reviews r JOIN users u ON r.user_id = u.user_id JOIN properties p ON r.property_id = p.property_id WHERE r.vendor_id = ?");
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$result = $stmt->get_result();
$reviews = [];

while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}

echo json_encode(["success" => true, "reviews" => $reviews]);
