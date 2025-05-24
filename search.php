<?php
include 'helpers/connection.php';

// Sanitize input
$city = isset($_GET['city']) ? mysqli_real_escape_string($conn, $_GET['city']) : null;
$checkIn = isset($_GET['check_in_date']) ? $_GET['check_in_date'] : null;
$checkOut = isset($_GET['check_out_date']) ? $_GET['check_out_date'] : null;
$price = isset($_GET['price']) ? intval($_GET['price']) : null;

// Base query
$sql = "SELECT properties.*, categories.category_name, 
        AVG(reviews.rating) AS average_rating
        FROM properties
        JOIN categories ON properties.category_id = categories.category_id 
        LEFT JOIN reviews ON properties.property_id = reviews.property_id
        WHERE 1=1";

// City filter
if ($city) {
    $sql .= " AND properties.city = '$city'";
}

// Price filter with 15% buffer
if ($price !== null) {
    $buffer = round($price * 0.15);
    $maxPrice = $price + $buffer;
    $sql .= " AND properties.price_per_night <= $maxPrice";
}

// Date range filter — exclude any property booked for ANY day in the range
if ($checkIn && $checkOut) {
    $checkIn = mysqli_real_escape_string($conn, $checkIn);
    $checkOut = mysqli_real_escape_string($conn, $checkOut);

    $sql .= " AND properties.property_id NOT IN (
        SELECT bp.property_id
        FROM bookings b
        JOIN booking_properties bp ON b.booking_id = bp.booking_id
        WHERE b.booking_status = 'booked'
        AND (
            b.check_in_date < '$checkOut' AND b.check_out_date > '$checkIn'
        )
    )";
}

// Group by property to calculate AVG rating
$sql .= " GROUP BY properties.property_id";

// Sorting: prioritize city + price match
if ($city && $price !== null) {
    $sql .= " ORDER BY
        CASE
            WHEN properties.city = '$city' AND properties.price_per_night <= $price THEN 0
            WHEN properties.city = '$city' THEN 1
            ELSE 2
        END,
        properties.registered_at DESC";
} else {
    $sql .= " ORDER BY properties.registered_at DESC";
}

// Execute query
$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => 'Query failed: ' . mysqli_error($conn)
    ]);
    exit();
}

// Fetch results
$properties = mysqli_fetch_all($result, MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'properties' => $properties
]);
?>
