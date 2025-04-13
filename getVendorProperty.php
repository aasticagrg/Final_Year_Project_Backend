<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'helpers/connection.php';
include 'auth/verifyToken.php';
include_once 'helpers/auth_helper.php';

// Get token from the headers
$headers = getallheaders();
$token = null;

if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
    if (strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
    }
}

if (!$token) {
    echo json_encode([
        'success' => false,
        'message' => 'Token is required',
    ]);
    exit();
}

// Verify if the user is a vendor
$isVendor = isVendor($token);
if (!$isVendor) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access',
    ]);
    exit();
}

$vendorData = getUserIdFromToken($token);
$vendor_id = $vendorData['vendor_id'];

// Debugging: Log the vendor_id being used
error_log("Vendor ID: " . $vendor_id); // Log the vendor_id

if (!$vendor_id) {
    error_log("Vendor ID is null or missing for token: $token");
    echo json_encode([
        'success' => false,
        'message' => 'Vendor ID not found from token',
    ]);
    exit();
}

// Fetch properties from the database for the specific vendor
$sql = "SELECT * FROM properties WHERE vendor_id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Error preparing SQL statement',
    ]);
    exit();
}

$stmt->bind_param("i", $vendor_id); // Bind the vendor_id as an integer
$stmt->execute();
$result = $stmt->get_result();

$properties = [];
if ($result->num_rows > 0) {
    $properties = $result->fetch_all(MYSQLI_ASSOC);
}

echo json_encode([
    'success' => true,
    'token_valid' => true, // Indicates the token is valid
    'vendor_id' => $vendor_id,
    'properties' => $properties,
]);

$stmt->close();
?>
