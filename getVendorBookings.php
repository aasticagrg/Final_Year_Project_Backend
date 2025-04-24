<?php
include_once __DIR__ . '/helpers/connection.php';
include_once __DIR__ . '/helpers/auth_helper.php';

header('Content-Type: application/json');

// Get Authorization header
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

// Check if Authorization header is missing
if (!$authHeader) {
    echo json_encode([
        'success' => false,
        'message' => 'Authorization header missing'
    ]);
    exit;
}

// Remove 'Bearer ' from the Authorization header to extract token
$token = str_replace('Bearer ', '', $authHeader);

// Get user data from token (assuming your `getUserIdFromToken` function is working correctly)
$userData = getUserIdFromToken($token);

// Ensure vendor access only (check if the user is a vendor)
if (!$userData['vendor_id']) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Only vendors can view bookings.'
    ]);
    exit;
}

$vendor_id = $userData['vendor_id'];

// Get search query from GET request (default to empty if not set)
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$searchTerm = '%' . $searchQuery . '%'; // Use % for SQL LIKE searches

try {
    // Prepare SQL statement to fetch bookings for the vendor with the search term applied
    $stmt = $conn->prepare("
        SELECT 
            b.booking_id,
            b.check_in_date,
            b.check_out_date,
            b.booking_status,
            b.total_price AS total_price,
            b.arrival_time,
            b.full_guest_name,
            b.created_at AS booking_created,
            bp.property_id,
            bp.days,
            bp.total_price AS total_price,
            p.property_name,
            u.user_id,
            u.name AS name,
            u.email AS email,
            u.user_address,
            u.phone_no,
            u.user_verification,
            u.account_status
        FROM bookings b
        JOIN booking_properties bp ON b.booking_id = bp.booking_id
        JOIN properties p ON bp.property_id = p.property_id
        JOIN users u ON b.user_id = u.user_id
        WHERE bp.vendor_id = ? 
        AND b.booking_status IN ('booked', 'cancelled')
          AND (
              u.name LIKE ? OR 
              u.email LIKE ? OR 
              u.phone_no LIKE ? OR 
              p.property_name LIKE ?
          )
        ORDER BY b.created_at DESC
    ");

    // Bind the parameters for the query
    $stmt->bind_param("sssss", $vendor_id, $searchTerm, $searchTerm, $searchTerm, $searchTerm);

    // Execute the statement
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch all bookings
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }

    // Return success response with bookings data
    echo json_encode([
        'success' => true,
        'bookings' => $bookings
    ]);
} catch (Exception $e) {
    // Catch any errors and return a failure response
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching bookings: ' . $e->getMessage()
    ]);
}
?>
