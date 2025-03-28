<?php
include 'helpers/connection.php';

$destination = isset($_GET['destination']) ? $_GET['destination'] : '';
$checkInDate = isset($_GET['checkInDate']) ? $_GET['checkInDate'] : '';
$checkOutDate = isset($_GET['checkOutDate']) ? $_GET['checkOutDate'] : '';
$priceRange = isset($_GET['priceRange']) ? $_GET['priceRange'] : '';

// Construct the SQL query without using any sanitization (unsafe approach, but as per request)
$sql = "SELECT * FROM properties WHERE 1=1";

// Replace 'destination' with 'city' since the database column is 'city'
if ($destination) {
    $sql .= " AND city LIKE '%$destination%'";  // Now using 'city' instead of 'destination'
}

if ($priceRange) {
    $sql .= " AND price_per_night <= $priceRange";  // Assuming price_per_night is the correct column for price
}

if ($checkInDate) {
    $sql .= " AND available_date >= '$checkInDate'";  // Assuming available_date is a valid date field
}

if ($checkOutDate) {
    $sql .= " AND available_date <= '$checkOutDate'";  // Assuming available_date is a valid date field
}

$result = $conn->query($sql);

$properties = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $properties[] = $row; // Fetch properties and add to array
    }
}

// Return the result as JSON
header('Content-Type: application/json');
echo json_encode($properties);

$conn->close();
?>
