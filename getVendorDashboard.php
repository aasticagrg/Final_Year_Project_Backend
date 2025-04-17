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

// Get token from Authorization header
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (!$authHeader) {
    echo json_encode([
        'success' => false,
        'message' => 'Authorization header missing'
    ]);
    exit;
}

$token = str_replace('Bearer ', '', $authHeader);
$userData = getUserIdFromToken($token);

if (!$userData['vendor_id']) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

$vendor_id = $userData['vendor_id'];
$response = [];

try {
    // Total Properties
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM properties WHERE vendor_id = ?");
    mysqli_stmt_bind_param($stmt, "s", $vendor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $response['total_properties'] = mysqli_fetch_assoc($result)['total'];

    // Total Bookings
    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(DISTINCT b.booking_id) AS total
        FROM bookings b
        JOIN booking_properties bp ON b.booking_id = bp.booking_id
        WHERE bp.vendor_id = ?
    ");
    mysqli_stmt_bind_param($stmt, "s", $vendor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $response['total_bookings'] = mysqli_fetch_assoc($result)['total'];

    // Total Earnings
    $stmt = mysqli_prepare($conn, "
        SELECT SUM(p.amount) AS total
        FROM payments p
        JOIN bookings b ON p.booking_id = b.booking_id
        JOIN booking_properties bp ON b.booking_id = bp.booking_id
        WHERE bp.vendor_id = ? AND p.payment_status = 'completed'
    ");
    mysqli_stmt_bind_param($stmt, "s", $vendor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $earnings = mysqli_fetch_assoc($result);
    $response['total_earnings'] = $earnings['total'] ?? 0;

    // Average Rating
    $stmt = mysqli_prepare($conn, "
        SELECT AVG(r.rating) AS average 
        FROM reviews r 
        WHERE r.vendor_id = ?
    ");
    mysqli_stmt_bind_param($stmt, "s", $vendor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ratings = mysqli_fetch_assoc($result);
    $response['average_rating'] = round($ratings['average'] ?? 0, 1);

    // Monthly Earnings Chart
    $stmt = mysqli_prepare($conn, "
        SELECT 
            MONTHNAME(p.payment_date) AS month, 
            SUM(p.amount) AS total
        FROM payments p
        JOIN bookings b ON p.booking_id = b.booking_id
        JOIN booking_properties bp ON b.booking_id = bp.booking_id
        WHERE bp.vendor_id = ? AND p.payment_status = 'completed'
        GROUP BY MONTH(p.payment_date)
        ORDER BY MONTH(p.payment_date)
    ");
    mysqli_stmt_bind_param($stmt, "s", $vendor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $monthly_earnings = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $monthly_earnings[] = $row;
    }
    $response['monthly_earnings'] = $monthly_earnings;

    // Monthly Bookings
    $stmt = mysqli_prepare($conn, "
        SELECT 
            MONTHNAME(b.created_at) AS month, 
            COUNT(*) AS total
        FROM bookings b
        JOIN booking_properties bp ON b.booking_id = bp.booking_id
        WHERE bp.vendor_id = ?
        GROUP BY MONTH(b.created_at)
        ORDER BY MONTH(b.created_at)
    ");
    mysqli_stmt_bind_param($stmt, "s", $vendor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $monthly_bookings = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $monthly_bookings[] = $row;
    }
    $response['monthly_bookings'] = $monthly_bookings;

    // Rating Distribution
    $stmt = mysqli_prepare($conn, "
        SELECT 
            r.rating,
            COUNT(*) as count
        FROM reviews r
        WHERE r.vendor_id = ?
        GROUP BY r.rating
        ORDER BY r.rating
    ");
    mysqli_stmt_bind_param($stmt, "s", $vendor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rating_distribution = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rating_distribution[] = $row;
    }
    $response['rating_distribution'] = $rating_distribution;

    // Recent Bookings
    $stmt = mysqli_prepare($conn, "
        SELECT 
            b.booking_id,
            b.check_in_date,
            b.check_out_date,
            pay.payment_status,
            b.booking_status,
            p.property_name, 
            u.name AS user_name
        FROM bookings b
        JOIN booking_properties bp ON b.booking_id = bp.booking_id
        JOIN properties p ON bp.property_id = p.property_id
        JOIN users u ON b.user_id = u.user_id
        LEFT JOIN payments pay ON b.booking_id = pay.booking_id
        WHERE bp.vendor_id = ?
        ORDER BY b.booking_id DESC
        LIMIT 5

    ");
    mysqli_stmt_bind_param($stmt, "s", $vendor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $recent_bookings = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_bookings[] = $row;
    }
    $response['recent_bookings'] = $recent_bookings;

    // Recent Reviews
    $stmt = mysqli_prepare($conn, "
        SELECT 
            r.review_id,
            r.rating,
            r.review_text,
            r.created_at,
            p.property_name, 
            u.name AS user_name
        FROM reviews r
        JOIN properties p ON r.property_id = p.property_id
        JOIN users u ON r.user_id = u.user_id
        WHERE r.vendor_id = ?
        ORDER BY r.review_id DESC
        LIMIT 5
    ");
    mysqli_stmt_bind_param($stmt, "s", $vendor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $recent_reviews = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_reviews[] = $row;
    }
    $response['recent_reviews'] = $recent_reviews;

    

    // Weekly Bookings
    $stmt = mysqli_prepare($conn, "
    SELECT 
        WEEK(b.created_at, 1) AS week, 
        COUNT(*) AS total
    FROM bookings b
    JOIN booking_properties bp ON b.booking_id = bp.booking_id
    WHERE bp.vendor_id = ?
    GROUP BY WEEK(b.created_at, 1)
    ORDER BY WEEK(b.created_at, 1)
    ");
    mysqli_stmt_bind_param($stmt, "s", $vendor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $weekly_bookings = [];
    while ($row = mysqli_fetch_assoc($result)) {
    $weekly_bookings[] = $row;
    }
    $response['weekly_bookings'] = $weekly_bookings;

    // Weekly Earnings
    $stmt = mysqli_prepare($conn, "
    SELECT 
        WEEK(p.payment_date, 1) AS week, 
        SUM(p.amount) AS total
    FROM payments p
    JOIN bookings b ON p.booking_id = b.booking_id
    JOIN booking_properties bp ON b.booking_id = bp.booking_id
    WHERE bp.vendor_id = ? AND p.payment_status = 'completed'
    GROUP BY WEEK(p.payment_date, 1)
    ORDER BY WEEK(p.payment_date, 1)
    ");
    mysqli_stmt_bind_param($stmt, "s", $vendor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $weekly_earnings = [];
    while ($row = mysqli_fetch_assoc($result)) {
    $weekly_earnings[] = $row;
    }
    $response['weekly_earnings'] = $weekly_earnings;
    
    echo json_encode([
      'success' => true,
      'data' => $response
    ]);


} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching dashboard data',
        'error' => $e->getMessage()
    ]);
}
?>
