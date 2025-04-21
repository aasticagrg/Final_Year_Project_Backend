<?php
include 'helpers/connection.php';
include 'helpers/auth_helper.php';

// Get headers for token validation
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (!$authHeader) {
    echo json_encode(['success' => false, 'message' => 'Authorization header missing']);
    exit;
}

$token = str_replace('Bearer ', '', $authHeader);
if (!isAdmin($token)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get filters
$fromDate = $_GET['from'] ?? '';
$toDate = $_GET['to'] ?? '';
$bookingStatus = $_GET['booking_status'] ?? '';
$paymentStatus = $_GET['payment_status'] ?? '';
$vendorName = $_GET['vendor_name'] ?? '';
$userName = $_GET['user_name'] ?? '';

// Base SQL query
$sql = "SELECT 
            b.booking_id,
            b.user_id,
            u.name AS user_name,
            p.property_name,
            p.city,
            v.vendor_name,
            b.check_in_date,
            b.check_out_date,
            b.booking_status,
            b.total_price,
            pay.payment_status,
            pay.method,
            pay.amount,
            pay.created_at
        FROM bookings AS b
        JOIN booking_properties AS bp ON bp.booking_id = b.booking_id
        JOIN properties AS p ON p.property_id = bp.property_id
        JOIN vendors AS v ON p.vendor_id = v.vendor_id
        JOIN payments AS pay ON b.booking_id = pay.booking_id
        JOIN users AS u ON b.user_id = u.user_id
        WHERE 1 = 1";

// Filters
$params = [];
$bindValues = [];

if (!empty($fromDate)) {
    $sql .= " AND DATE(pay.created_at) >= ?";
    $params[] = "s";
    $bindValues[] = $fromDate;
}

if (!empty($toDate)) {
    $sql .= " AND DATE(pay.created_at) <= ?";
    $params[] = "s";
    $bindValues[] = $toDate;
}

if (!empty($bookingStatus)) {
    $sql .= " AND b.booking_status = ?";
    $params[] = "s";
    $bindValues[] = $bookingStatus;
}

if (!empty($paymentStatus)) {
    $sql .= " AND pay.payment_status = ?";
    $params[] = "s";
    $bindValues[] = $paymentStatus;
}

if (!empty($vendorName)) {
    $sql .= " AND v.vendor_name LIKE ?";
    $params[] = "s";
    $bindValues[] = "%$vendorName%";
}

if (!empty($userName)) {
    $sql .= " AND u.name LIKE ?";
    $params[] = "s";
    $bindValues[] = "%$userName%";
}

// Prepare and execute
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query error: ' . $conn->error]);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param(implode('', $params), ...$bindValues);
}

$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Error fetching data: ' . $stmt->error]);
    exit;
}

$bookings = [];
$totalCompleted = 0;
$totalCancelled = 0;
$totalRevenue = 0.0;

while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;

    if ($row['booking_status'] === 'booked') {
        $totalCompleted++;
        $totalRevenue += (float)$row['amount'];
    } elseif ($row['booking_status'] === 'cancelled') {
        $totalCancelled++;
    }
}

$stmt->close();

// Get total properties count
$propertyCount = 0;
$propertyQuery = "SELECT COUNT(*) AS total_properties FROM properties";
$propertyResult = $conn->query($propertyQuery);
if ($propertyResult && $propertyRow = $propertyResult->fetch_assoc()) {
    $propertyCount = (int)$propertyRow['total_properties'];
}

// Return result
echo json_encode([
    'success' => true,
    'summary' => [
        'total_completed' => $totalCompleted,
        'total_cancelled' => $totalCancelled,
        'total_revenue' => $totalRevenue,
        'total_bookings' => count($bookings),
        'total_properties' => $propertyCount
    ],
    'data' => $bookings
]);
?>
