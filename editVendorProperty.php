<?php
// Allow requests from any origin
header("Access-Control-Allow-Origin: *");
// Allow specific methods
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// Allow specific headers (especially for Authorization)
header("Access-Control-Allow-Headers: Authorization, Content-Type");

// Handle preflight (OPTIONS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Your actual PHP logic continues here...

include_once __DIR__ . '/helpers/connection.php';
include_once __DIR__ . '/helpers/auth_helper.php';

header('Content-Type: application/json');

// Use getallheaders() instead of apache_request_headers()
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

// Verify the token and get vendor_id
$userData = getUserIdFromToken($token);

// Ensure the user is a vendor
if (!$userData['vendor_id']) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Vendor role is required.'
    ]);
    exit;
}

// Extract property data from request body
$inputData = json_decode(file_get_contents('php://input'), true);

// Validate the required data fields
if (!isset($inputData['property_id']) || !isset($inputData['property_name']) || !isset($inputData['city']) || !isset($inputData['location'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Required fields missing: property_id, property_name, city, and location.'
    ]);
    exit;
}

// Assign and sanitize inputs
$propertyId = $inputData['property_id'];
$propertyName = $inputData['property_name'];
$city = $inputData['city'];
$location = $inputData['location'];
$pType = $inputData['p_type'] ?? null;
$bhk = $inputData['bhk'] ?? null;
$bedroom = $inputData['bedroom'] ?? null;
$bathroom = $inputData['bathroom'] ?? null;
$balcony = $inputData['balcony'] ?? null;
$kitchen = $inputData['kitchen'] ?? null;
$wifi = $inputData['wifi'] ?? null;
$utilities = $inputData['utilities'] ?? null;
$parking = $inputData['parking'] ?? null;
$pool = $inputData['pool'] ?? null;
$petFriendly = $inputData['pet_friendly'] ?? null;
$peoples = $inputData['peoples'] ?? null;
$crib = $inputData['crib'] ?? null;
$availabilityStatus = $inputData['availability_status'] ?? null;
$pricePerNight = $inputData['price_per_night'] ?? null;
$checkInTime = $inputData['check_in_time'] ?? null;
$checkOutTime = $inputData['check_out_time'] ?? null;

// Check if property belongs to vendor
$sql = "SELECT vendor_id FROM properties WHERE property_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $propertyId);
$stmt->execute();
$result = $stmt->get_result();
$property = $result->fetch_assoc();

if (!$property || $property['vendor_id'] != $userData['vendor_id']) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. This property does not belong to you.'
    ]);
    exit;
}

// Log the values before the query
error_log("Updating property: " . json_encode($inputData));

// Update the property
$sql = "UPDATE properties 
        SET property_name = ?, city = ?, location = ?, p_type = ?, bhk = ?, bedroom = ?, bathroom = ?, balcony = ?, kitchen = ?, wifi = ?, utilities = ?, parking = ?, pool = ?, pet_friendly = ?, peoples = ?, crib = ?, availability_status = ?, price_per_night = ?, check_in_time = ?, check_out_time = ? 
        WHERE property_id = ? AND vendor_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssiiiiissssssisissii", $propertyName, $city, $location, $pType, $bhk, $bedroom, $bathroom, $balcony, $kitchen, $wifi, $utilities, $parking, $pool, $petFriendly, $peoples, $crib, $availabilityStatus, $pricePerNight, $checkInTime, $checkOutTime, $propertyId, $userData['vendor_id']);

if ($stmt->execute()) {
    $affectedRows = $stmt->affected_rows;
    error_log("Affected Rows: " . $affectedRows);  // Log how many rows were affected
    if ($affectedRows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Property updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No changes made to the property.'
        ]);
    }
} else {
    error_log("Error: " . $stmt->error);  // Log the error if query fails
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update property'
    ]);
}

$stmt->close();
?>
