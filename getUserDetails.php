<?php
include 'helpers/connection.php';
include 'helpers/auth_helper.php';

// Check if token is set in GET request
if (!isset($_GET['token'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Token is required',
    ]);
    exit();
}

$token = $_GET['token'];

// Function to get userId from token (you can implement your logic here)
$userId = getUserIdFromToken($token);

// SQL query to fetch user details
$sql = "SELECT user_id, email, role, name, phone_no, user_address FROM users WHERE user_id = '$userId'";

// Execute the query
$result = mysqli_query($conn, $sql);

// Check if the query was successful
if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => "Failed to get user details",
    ]);
    exit();
}

// Fetch the user data
$user = mysqli_fetch_assoc($result);

// If no user is found, return an error
if (!$user) {
    echo json_encode([
        'success' => false,
        'message' => "User not found",
    ]);
    exit();
}

// Return user details as JSON response
echo json_encode([
    'success' => true,
    'user' => $user
]);
?>
