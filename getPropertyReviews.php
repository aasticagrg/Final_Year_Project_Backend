<?php
include_once 'helpers/connection.php';

header('Content-Type: application/json');

$property_id = $_GET['property_id'] ?? null;

if (!$property_id) {
    echo json_encode(['success' => false, 'message' => 'Property ID is required']);
    exit;
}

// Fetch reviews with user name
$sql = "SELECT r.*, u.name AS user_name
        FROM reviews r
        JOIN users u ON r.user_id = u.user_id
        WHERE r.property_id = ?
        ORDER BY r.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $property_id);
$stmt->execute();
$result = $stmt->get_result();

$reviews = [];
while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}

// Calculate average rating
$avgSql = "SELECT AVG(rating) AS average_rating, COUNT(*) AS total_reviews FROM reviews WHERE property_id = ?";
$avgStmt = $conn->prepare($avgSql);
$avgStmt->bind_param("i", $property_id);
$avgStmt->execute();
$avgResult = $avgStmt->get_result();
$avgData = $avgResult->fetch_assoc();

echo json_encode([
    'success' => true,
    'reviews' => $reviews,
    'average_rating' => round($avgData['average_rating'], 1),
    'total_reviews' => $avgData['total_reviews']
]);
