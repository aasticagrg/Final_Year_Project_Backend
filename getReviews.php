<?php
include 'helpers/connection.php';
include 'helpers/auth_helper.php';

// Check if the user is authenticated (Token validation)
$headers = apache_request_headers();
if (!isset($headers['Authorization'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$token = str_replace('Bearer ', '', $headers['Authorization']);

// Validate token (using helper function)
$userData = getUserIdFromToken($token);

// If token is invalid or user is not authenticated
if (!$userData['user_id'] && !$userData['vendor_id']) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

// Optionally, you can check if the user is an admin or vendor
// and restrict data based on roles, for example:
if (!isAdmin($token) && !isVendor($token)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Fetch reviews data
$query = "SELECT reviews.review_id, reviews.rating, reviews.review_text, reviews.created_at, 
                 users.name AS user_name, properties.property_name 
          FROM reviews
          JOIN users ON reviews.user_id = users.user_id
          JOIN properties ON reviews.property_id = properties.property_id
          ORDER BY reviews.created_at DESC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error preparing query']);
    exit;
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
    echo json_encode(['success' => true, 'reviews' => $reviews]);
} else {
    echo json_encode(['success' => false, 'message' => 'No reviews found']);
}

$stmt->close();
mysqli_close($conn);
?>
