<?php
include 'helpers/connection.php';


$sql = "select * from categories";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => "Failed to get categories",
    ]);
    exit();
}

$categories = mysqli_fetch_all($result, MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'categories' => $categories,
]);