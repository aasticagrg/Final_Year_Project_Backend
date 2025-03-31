<?php
include './helpers/connection.php';

if (!isset($_GET['property_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'property_id is required',
    ]);
    exit();
}

$property_id = $_GET['property_id'];

// Use prepared statements to prevent SQL injection
$stmt = $conn->prepare("
    SELECT properties.*, categories.*
    FROM properties
    JOIN categories ON properties.category_id = categories.category_id
    WHERE property_id = ?
");
$stmt->bind_param("i", $property_id);
$stmt->execute();

$result = $stmt->get_result();

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => "Failed to get property",
    ]);
    exit();
}

$property = $result->fetch_assoc();

if (!$property) {
    echo json_encode([
        'success' => false,
        'message' => "Property not found",
    ]);
    exit();
}

echo json_encode([
    'success' => true,
    'property' => $property
]);