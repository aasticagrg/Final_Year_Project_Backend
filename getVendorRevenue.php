<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once __DIR__ . '/helpers/connection.php';
include_once __DIR__ . '/helpers/auth_helper.php';

header('Content-Type: application/json');

$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (!$authHeader) {
    echo json_encode(['success' => false, 'message' => 'Authorization header missing']);
    exit;
}

$token = str_replace('Bearer ', '', $authHeader);
$userData = getUserIdFromToken($token);

if (!$userData['vendor_id']) {
    echo json_encode(['success' => false, 'message' => 'Access denied: not a vendor']);
    exit;
}

$vendor_id = $userData['vendor_id'];

// Optional Date Filter
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Condition and Binding
$dateFilter = "";
$params = [$vendor_id];
$types = "i";

if ($startDate && $endDate) {
    $dateFilter = " AND b.created_at BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= "ss";
}

// 1. Summary Query
$summarySql = "
    SELECT 
        SUM(CASE WHEN pm.payment_status = 'completed' THEN bp.total_price ELSE 0 END) AS total_revenue,
        SUM(CASE WHEN pm.payment_status = 'pending' THEN bp.total_price ELSE 0 END) AS pending_revenue,
        COUNT(CASE WHEN b.booking_status = 'booked' THEN 1 END) AS completed_bookings,
        COUNT(CASE WHEN b.booking_status = 'cancelled' THEN 1 END) AS cancelled_bookings
    FROM bookings b
    JOIN booking_properties bp ON b.booking_id = bp.booking_id
    LEFT JOIN payments pm ON b.booking_id = pm.booking_id
    WHERE bp.vendor_id = ? $dateFilter
";




$summaryStmt = $conn->prepare($summarySql);
$summaryStmt->bind_param($types, ...$params);
$summaryStmt->execute();
$summaryResult = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();

// 2. Revenue Table
$tableSql = "
    SELECT 
        b.booking_id,
        p.property_name AS property_name,
        u.name AS guest_name,
        b.check_in_date,
        b.check_out_date,
        bp.total_price AS total_amount,
        pm.payment_status,
        b.booking_status,
        b.created_at
    FROM bookings b
    JOIN booking_properties bp ON b.booking_id = bp.booking_id
    JOIN properties p ON bp.property_id = p.property_id
    JOIN users u ON b.user_id = u.user_id
    LEFT JOIN payments pm ON b.booking_id = pm.booking_id
    WHERE bp.vendor_id = ? $dateFilter
    ORDER BY b.created_at DESC
";




$tableStmt = $conn->prepare($tableSql);
$tableStmt->bind_param($types, ...$params);
$tableStmt->execute();
$tableResult = $tableStmt->get_result();
$revenueData = [];
while ($row = $tableResult->fetch_assoc()) {
    $revenueData[] = $row;
}
$tableStmt->close();

// 3. Chart Data
$chartSql = "
    SELECT 
        DATE_FORMAT(b.created_at, '%Y-%m') AS month,
        SUM(CASE WHEN pm.payment_status = 'completed' THEN bp.total_price ELSE 0 END) AS total
    FROM bookings b
    JOIN booking_properties bp ON b.booking_id = bp.booking_id
    LEFT JOIN payments pm ON b.booking_id = pm.booking_id
    WHERE bp.vendor_id = ? $dateFilter
    GROUP BY month
    ORDER BY month
";




$chartStmt = $conn->prepare($chartSql);
$chartStmt->bind_param($types, ...$params);
$chartStmt->execute();
$chartResult = $chartStmt->get_result();
$chartData = [];
while ($row = $chartResult->fetch_assoc()) {
    $chartData[] = $row;
}
$chartStmt->close();

// Final JSON
echo json_encode([
    'success' => true,
    'summary' => $summaryResult,
    'revenue_data' => $revenueData,
    'chart_data' => $chartData
]);
?>
