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

// Get token from headers
$headers = getallheaders();
$token = null;

if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
    if (strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
    }
}

if (!$token || !isAdmin($token)) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access',
    ]);
    exit();
}

// Optional search filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$sql = "SELECT 
            p.property_id, 
            p.property_name, 
            p.city, 
            p.location, 
            p.price_per_night, 
            p.p_type, 
            p.bhk, 
            p.pimage1, 
            p.pimage2,
            v.vendor_name, 
            v.contact_no
        FROM properties p
        JOIN vendors v ON p.vendor_id = v.vendor_id
        WHERE p.property_name LIKE '%$search%' OR v.vendor_name LIKE '%$search%'
        ORDER BY p.property_id DESC";

$result = mysqli_query($conn, $sql);

$properties = [];
while ($row = mysqli_fetch_assoc($result)) {
    $properties[] = $row;
}

echo json_encode([
    'success' => true,
    'data' => $properties
]);
?>
