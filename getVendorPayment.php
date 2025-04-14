<?php
include 'helpers/connection.php';

header('Content-Type: application/json');

$vendor_id = $_GET['vendor_id'] ?? '';

if (!$vendor_id) {
    echo json_encode(["success" => false, "message" => "Vendor ID is required."]);
    exit;
}

// Updated query to join via booking_properties
$sql = "
    SELECT 
        p.payment_id,
        p.amount,
        p.method,
        p.payment_date,
        p.transaction_id,
        p.payment_status,
        bp.booking_id,
        pr.property_id,
        pr.property_name,
        u.name AS user_name,
        u.email AS user_email,
        u.phone_no AS user_phone
    FROM payments p
    INNER JOIN bookings b ON p.booking_id = b.booking_id
    INNER JOIN booking_properties bp ON bp.booking_id = b.booking_id
    INNER JOIN properties pr ON bp.property_id = pr.property_id
    INNER JOIN users u ON b.user_id = u.user_id
    WHERE pr.vendor_id = ?
    ORDER BY p.payment_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$result = $stmt->get_result();

$payments = [];
while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
}

echo json_encode([
    "success" => true,
    "payments" => $payments
]);
?>
