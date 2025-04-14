<?php
include 'helpers/connection.php';

header('Content-Type: application/json');

if (!isset($_GET['property_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'property_id is required',
    ]);
    exit();
}

$property_id = $_GET['property_id'];

// Fetch property details
$stmt = $conn->prepare("
    SELECT properties.*, categories.*, vendors.vendor_name, vendors.contact_no
    FROM properties
    JOIN categories ON properties.category_id = categories.category_id
    LEFT JOIN vendors ON properties.vendor_id = vendors.vendor_id
    WHERE property_id = ?
");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => "Failed to get property",
    ]);
    exit();
}

$property = $result->fetch_assoc();

if (!$property) {
    echo json_encode([
        'success' => false,
        'message' => "Property not found",
    ]);
    exit();
}

// Fetch only BOOKED date ranges for this property
$bookingStmt = $conn->prepare("
    SELECT b.check_in_date, b.check_out_date
    FROM bookings b
    JOIN booking_properties bp ON b.booking_id = bp.booking_id
    WHERE bp.property_id = ? AND b.booking_status = 'booked'
");
$bookingStmt->bind_param("i", $property_id);
$bookingStmt->execute();
$bookingResult = $bookingStmt->get_result();

$bookedDates = [];

while ($row = $bookingResult->fetch_assoc()) {
    $bookedDates[] = [
        'check_in_date' => $row['check_in_date'],
        'check_out_date' => $row['check_out_date'],
    ];
}

// Return full property + booked date info
echo json_encode([
    'success' => true,
    'property' => $property,
    'booked_dates' => $bookedDates
]);
?>
