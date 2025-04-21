<?php
// CORS headers
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'helpers/connection.php';
include 'helpers/auth_helper.php';

// Auth check
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!$authHeader) {
    echo json_encode(['success' => false, 'message' => 'Authorization header missing']);
    exit;
}
$token = str_replace('Bearer ', '', $authHeader);
if (!isAdmin($token)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Filters
$startDate = $_GET['startDate'] ?? null;
$endDate = $_GET['endDate'] ?? null;
$vendorName = $_GET['vendorName'] ?? '';

// Build WHERE conditions
$conditions = [];
$params = [];
$types = '';

if ($startDate && $endDate) {
    $conditions[] = "b.created_at BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= 'ss';
}
if (!empty($vendorName)) {
    $conditions[] = "v.vendor_name LIKE ?";
    $params[] = '%' . $vendorName . '%';
    $types .= 's';
}

$whereClause = count($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// === 1. Totals ===
$totalsQuery = "
    SELECT 
        SUM(CASE WHEN b.booking_status = 'booked' THEN 1 ELSE 0 END) AS total_completed,
        SUM(CASE WHEN b.booking_status = 'cancelled' THEN 1 ELSE 0 END) AS total_cancelled,
        SUM(CASE WHEN b.booking_status = 'booked' THEN pay.amount ELSE 0 END) AS total_revenue
    FROM bookings b
    JOIN booking_properties bp ON bp.booking_id = b.booking_id
    JOIN properties p ON p.property_id = bp.property_id
    JOIN vendors v ON p.vendor_id = v.vendor_id
    JOIN payments pay ON pay.booking_id = b.booking_id
    $whereClause
";
$stmt = $conn->prepare($totalsQuery);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();
$stmt->close();

// === Fixed: Total Properties (Independent count with vendor filter) ===
$propertyTotalQuery = "
    SELECT COUNT(*) AS total_properties 
    FROM properties p
    JOIN vendors v ON p.vendor_id = v.vendor_id
    $whereClause
";
$stmt = $conn->prepare($propertyTotalQuery);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$propertyTotalResult = $stmt->get_result()->fetch_assoc();
$totals['total_properties'] = $propertyTotalResult['total_properties'];
$stmt->close();

// === 2. Monthly Revenue Line Chart ===
$monthlyQuery = "
    SELECT DATE_FORMAT(b.created_at, '%Y-%m') AS month, SUM(pay.amount) AS revenue
    FROM bookings b
    JOIN booking_properties bp ON bp.booking_id = b.booking_id
    JOIN properties p ON p.property_id = bp.property_id
    JOIN vendors v ON p.vendor_id = v.vendor_id
    JOIN payments pay ON pay.booking_id = b.booking_id
    $whereClause
    AND b.booking_status = 'booked'
    GROUP BY month
    ORDER BY month
";
$stmt = $conn->prepare($monthlyQuery);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$monthlyResult = $stmt->get_result();
$monthlyRevenue = [];
while ($row = $monthlyResult->fetch_assoc()) {
    $monthlyRevenue[] = $row;
}
$stmt->close();

// === 3. Weekly Revenue Line Chart ===
$weeklyQuery = "
    SELECT DATE_FORMAT(b.created_at, '%Y-%u') AS week, SUM(pay.amount) AS revenue
    FROM bookings b
    JOIN booking_properties bp ON bp.booking_id = b.booking_id
    JOIN properties p ON p.property_id = bp.property_id
    JOIN vendors v ON p.vendor_id = v.vendor_id
    JOIN payments pay ON pay.booking_id = b.booking_id
    $whereClause
    AND b.booking_status = 'booked'
    GROUP BY week
    ORDER BY week
";
$stmt = $conn->prepare($weeklyQuery);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$weeklyResult = $stmt->get_result();
$weeklyRevenue = [];
while ($row = $weeklyResult->fetch_assoc()) {
    $weeklyRevenue[] = $row;
}
$stmt->close();

// === 4. Payment Method Pie Chart ===
$paymentMethodQuery = "
    SELECT pay.method, SUM(pay.amount) AS total
    FROM bookings b
    JOIN booking_properties bp ON bp.booking_id = b.booking_id
    JOIN properties p ON p.property_id = bp.property_id
    JOIN vendors v ON p.vendor_id = v.vendor_id
    JOIN payments pay ON pay.booking_id = b.booking_id
    $whereClause
    AND b.booking_status = 'booked'
    GROUP BY pay.method
";
$stmt = $conn->prepare($paymentMethodQuery);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$methodResult = $stmt->get_result();
$paymentMethods = [];
while ($row = $methodResult->fetch_assoc()) {
    $paymentMethods[] = $row;
}
$stmt->close();

// === 5. Top Earning Properties ===
$topPropertiesQuery = "
    SELECT p.property_name, SUM(pay.amount) AS total_earned
    FROM bookings b
    JOIN booking_properties bp ON bp.booking_id = b.booking_id
    JOIN properties p ON p.property_id = bp.property_id
    JOIN vendors v ON p.vendor_id = v.vendor_id
    JOIN payments pay ON pay.booking_id = b.booking_id
    $whereClause
    AND b.booking_status = 'booked'
    GROUP BY p.property_id
    ORDER BY total_earned DESC
    LIMIT 5
";
$stmt = $conn->prepare($topPropertiesQuery);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$topResult = $stmt->get_result();
$topProperties = [];
while ($row = $topResult->fetch_assoc()) {
    $topProperties[] = $row;
}
$stmt->close();

// === 6. Property Category Pie Chart ===
$categoryQuery = "
    SELECT c.category_name AS category, COUNT(*) AS count
    FROM properties p
    JOIN categories c ON p.category_id = c.category_id
    GROUP BY c.category_name
";
$categoryResult = $conn->query($categoryQuery);
$categoryCounts = [];
while ($row = $categoryResult->fetch_assoc()) {
    $categoryCounts[] = $row;
}

// === Final Response ===
echo json_encode([
    'success' => true,
    'totals' => $totals,
    'monthlyRevenue' => $monthlyRevenue,
    'weeklyRevenue' => $weeklyRevenue,
    'paymentMethods' => $paymentMethods,
    'topProperties' => $topProperties,
    'categoryCounts' => $categoryCounts
]);
?>
