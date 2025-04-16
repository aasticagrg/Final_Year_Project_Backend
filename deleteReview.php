<?php
include_once 'helpers/connection.php';
include_once 'helpers/auth_helper.php';

header("Content-Type: application/json");
$headers = getallheaders();
$token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
$userData = getUserIdFromToken($token);
$user_id = $userData['user_id'] ?? null;

$data = json_decode(file_get_contents("php://input"));
$review_id = $data->review_id ?? null;

if (!$user_id || !$review_id) {
    echo json_encode(["success" => false, "message" => "Unauthorized or missing data"]);
    exit;
}

if (!is_numeric($review_id)) {
  echo json_encode(["success" => false, "message" => "Invalid review ID"]);
  exit;
}

$stmt = $conn->prepare("DELETE FROM reviews WHERE review_id = ? AND user_id = ?");
$stmt->bind_param("ii", $review_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
  echo json_encode(["success" => true, "message" => "Review deleted"]);
} else {
  echo json_encode(["success" => false, "message" => "Delete failed. Either the review doesn't exist or it's not yours."]);
}

$stmt->close();
$conn->close();
