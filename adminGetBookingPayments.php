<?php
include 'helpers/connection.php';
include 'helpers/auth_helper.php';

// Ensure the user is an admin
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (!$authHeader) {
    echo json_encode([
        'success' => false,
        'message' => 'Authorization header missing'
    ]);
    exit;
}

$token = str_replace('Bearer ', '', $authHeader);
$userData = getUserIdFromToken($token);

// If not admin, return an error
if (!isAdmin($token)) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Check if search query exists
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

// Query to fetch bookings and payments along with property and vendor details
$sql = "SELECT b.booking_id, pay.payment_id, b.booking_status, b.user_id, u.name, bp.property_id, b.check_in_date, b.check_out_date, b.total_price, 
            p.property_name, p.city, v.contact_no, p.vendor_id, v.vendor_name, 
            pay.payment_id, pay.payment_status, pay.method, pay.amount, pay.created_at
        FROM bookings AS b
        JOIN booking_properties AS bp ON bp.booking_id = b.booking_id
        JOIN properties AS p ON p.property_id = bp.property_id
        JOIN payments AS pay ON b.booking_id = pay.booking_id
        JOIN users AS u ON u.user_id = b.user_id
        LEFT JOIN vendors AS v ON p.vendor_id = v.vendor_id
        WHERE p.property_name LIKE ? OR v.vendor_name LIKE ?";

// Prepare the SQL statement
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Error preparing query: ' . $conn->error
    ]);
    exit;
}

// Bind the search parameter
$searchParam = "%$searchQuery%";
$stmt->bind_param('ss', $searchParam, $searchParam);
$stmt->execute();

// Fetch results
$result = $stmt->get_result();
$bookings = [];

while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

$stmt->close();

// Return the response
echo json_encode([
    'success' => true,
    'data' => $bookings
]);

?>
