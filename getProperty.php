<?php
include 'helpers/connection.php';

// Fetch properties with category name instead of category_id
$sql = "SELECT * FROM properties 
        JOIN categories ON properties.category_id = categories.category_id";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => "Failed to retrieve properties",
    ]);
    exit();
}

$properties = mysqli_fetch_all($result, MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'properties' => $properties,
]);
?>
