<?php
include 'helpers/connection.php';

// Get filter parameters
$cityFilter = isset($_GET['city']) ? $_GET['city'] : null;
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : null;

// Sanitize inputs to prevent SQL injection
if ($cityFilter) {
    $cityFilter = mysqli_real_escape_string($conn, $cityFilter);
}

if ($categoryFilter) {
    $categoryFilter = mysqli_real_escape_string($conn, $categoryFilter);
}

// Build SQL query
$sql = "SELECT * FROM properties JOIN categories ON properties.category_id = categories.category_id WHERE 1=1";

// Add filters if they exist
if ($cityFilter) {
    $sql .= " AND city = '$cityFilter'";
}

if ($categoryFilter) {
    $sql .= " AND categories.category_name = '$categoryFilter'";
}

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => "Failed to retrieve properties: " . mysqli_error($conn),
    ]);
    exit();
}

$properties = mysqli_fetch_all($result, MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'properties' => $properties,
]);
?>