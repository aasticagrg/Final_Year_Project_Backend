<?php
include 'helpers/connection.php';

// 🔹 Sanitize input
$city = isset($_GET['city']) ? mysqli_real_escape_string($conn, $_GET['city']) : null;
$checkIn = isset($_GET['check_in_date']) ? $_GET['check_in_date'] : null;
$checkOut = isset($_GET['check_out_date']) ? $_GET['check_out_date'] : null;
$price = isset($_GET['price']) ? intval($_GET['price']) : null;

// 🔹 Base query
$sql = "SELECT properties.*, categories.category_name, 
        AVG(reviews.rating) AS average_rating
        FROM properties
        JOIN categories ON properties.category_id = categories.category_id 
        LEFT JOIN reviews ON properties.property_id = reviews.property_id
        WHERE 1=1";

// 🔹 Apply filters
if ($city) {
    $sql .= " AND properties.city = '$city'";
}

if ($price !== null) {
    $sql .= " AND properties.price_per_night <= $price";
}

// 🔹 Date availability: exclude properties already booked during selected range
if ($checkIn && $checkOut) {
    $sql .= " AND properties.property_id NOT IN (
        SELECT property_id FROM bookings
        WHERE ('$checkIn' < check_out_date AND '$checkOut' > check_in_date)
    )";
}

// 🔹 Group results for proper AVG aggregation
$sql .= " GROUP BY properties.property_id";

// 🔹 Execute query
$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => 'Query failed: ' . mysqli_error($conn)
    ]);
    exit();
}

// 🔹 Return results
$properties = mysqli_fetch_all($result, MYSQLI_ASSOC);
echo json_encode([
    'success' => true,
    'properties' => $properties
]);
?>
