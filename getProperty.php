<?php
include 'helpers/connection.php';

$sql = "SELECT * FROM properties"; // Fetch all properties

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
