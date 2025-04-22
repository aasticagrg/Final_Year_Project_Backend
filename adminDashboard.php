<?php
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'helpers/connection.php';
include 'helpers/auth_helper.php';

// Auth
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

// 1. Totals
$totals = [
    'total_users' => 0,
    'total_vendors' => 0,
    'total_bookings' => 0,
    'total_revenue' => 0,
    'total_properties' => 0
];

$totalsQuery = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'user') AS total_users,
        (SELECT COUNT(*) FROM vendors WHERE role = 'vendor') AS total_vendors,
        (SELECT COUNT(*) FROM bookings) AS total_bookings,
        (SELECT IFNULL(SUM(amount), 0) FROM payments) AS total_revenue,
        (SELECT COUNT(*) FROM properties) AS total_properties
";
$totalsResult = $conn->query($totalsQuery);
if ($totalsResult) {
    $totals = $totalsResult->fetch_assoc();
}

// 2. Booking Trend Chart
$weekly = [];
$weeklyQuery = "
    SELECT DATE_FORMAT(created_at, '%Y-%u') AS week, COUNT(*) AS count
    FROM bookings
    GROUP BY week
    ORDER BY week DESC
    LIMIT 8
";
$result = $conn->query($weeklyQuery);
while ($row = $result->fetch_assoc()) {
    $weekly[] = $row;
}

$monthly = [];
$monthlyQuery = "
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS count
    FROM bookings
    GROUP BY month
    ORDER BY month DESC
    LIMIT 6
";
$result = $conn->query($monthlyQuery);
while ($row = $result->fetch_assoc()) {
    $monthly[] = $row;
}

// 3. Booking Status Pie Chart
$bookingStatus = [];
$statusQuery = "
    SELECT booking_status, COUNT(*) AS count
    FROM bookings
    GROUP BY booking_status
";
$result = $conn->query($statusQuery);
while ($row = $result->fetch_assoc()) {
    $bookingStatus[] = $row;
}

// 4. New Registrations This Month
$registrations = [
  'users' => 0,
  'vendors' => 0
];

$userRegQuery = "
  SELECT COUNT(*) AS count
  FROM users
  WHERE MONTH(registration_date) = MONTH(CURRENT_DATE())
    AND YEAR(registration_date) = YEAR(CURRENT_DATE())
";
$userResult = $conn->query($userRegQuery);
if ($userResult) {
  $registrations['users'] = $userResult->fetch_assoc()['count'];
}

$vendorRegQuery = "
  SELECT COUNT(*) AS count
  FROM vendors
  WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
";
$vendorResult = $conn->query($vendorRegQuery);
if ($vendorResult) {
  $registrations['vendors'] = $vendorResult->fetch_assoc()['count'];
}

// 5. Top Performing Vendors (by revenue)
$topVendors = [];
$topQuery = "
    SELECT v.vendor_name , SUM(p.amount) AS revenue
    FROM vendors v
    JOIN properties prop ON prop.vendor_id = v.vendor_id
    JOIN booking_properties bp ON bp.property_id = prop.property_id
    JOIN payments p ON p.booking_id = bp.booking_id
    GROUP BY v.vendor_id
    ORDER BY revenue DESC
    LIMIT 5
";
$result = $conn->query($topQuery);
while ($row = $result->fetch_assoc()) {
    $topVendors[] = $row;
}

// 6. Recent Bookings
$recentBookings = [];
$bookingQuery = "
    SELECT b.booking_id, IFNULL(b.booking_status, 'unknown') AS booking_status, u.name, p.property_name, b.created_at
    FROM bookings b
    JOIN booking_properties bp ON bp.booking_id = b.booking_id
    JOIN properties p ON p.property_id = bp.property_id
    JOIN users u ON u.user_id = b.user_id
    ORDER BY b.created_at DESC
    LIMIT 5
";
$result = $conn->query($bookingQuery);
while ($row = $result->fetch_assoc()) {
    $recentBookings[] = $row;
}

// 7. Recent Reviews
$recentReviews = [];
$reviewQuery = "
    SELECT r.review_id, r.rating, r.review_text, r.created_at, u.name, p.property_name
    FROM reviews r
    JOIN users u ON u.user_id = r.user_id
    JOIN properties p ON p.property_id = r.property_id
    ORDER BY r.created_at DESC
    LIMIT 5
";
$result = $conn->query($reviewQuery);
while ($row = $result->fetch_assoc()) {
    $recentReviews[] = $row;
}

// 8. Rating Distribution Pie Chart
$ratingDist = [];
$ratingQuery = "
    SELECT rating, COUNT(*) AS count
    FROM reviews
    GROUP BY rating
    ORDER BY rating DESC
";
$result = $conn->query($ratingQuery);
while ($row = $result->fetch_assoc()) {
    $ratingDist[] = $row;
}

// 9. Property Count by Category
$propertyCategory = [];
$categoryQuery = "
    SELECT p_type AS category, COUNT(*) AS count
    FROM properties
    GROUP BY p_type
";
$result = $conn->query($categoryQuery);
while ($row = $result->fetch_assoc()) {
    $propertyCategory[] = $row;
}

// Final Output
echo json_encode([
    'success' => true,
    'totals' => $totals,
    'weeklyTrend' => array_reverse($weekly),
    'monthlyTrend' => array_reverse($monthly),
    'bookingStatus' => $bookingStatus,
    'registrations' => $registrations,
    'topVendors' => $topVendors,
    'recentBookings' => $recentBookings,
    'recentReviews' => $recentReviews,
    'ratingDistribution' => $ratingDist,
    'propertyCategory' => $propertyCategory
]);
?>