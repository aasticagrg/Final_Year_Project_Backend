<?php
include 'helpers/connection.php';
include 'helpers/auth_helper.php';

header('Content-Type: application/json');

// Get token from Authorization header
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

// Read JSON input from body
$input = json_decode(file_get_contents("php://input"), true);

if (isset($input['property_id'])) {
    $property_id = $input['property_id'];

    // Begin transaction
    $conn->begin_transaction();

    try {
        // First, delete related records from booking_properties
        $deleteBookings = $conn->prepare("DELETE FROM booking_properties WHERE property_id = ?");
        if (!$deleteBookings) {
            throw new Exception('Error preparing statement for deleting booking properties');
        }
        $deleteBookings->bind_param("i", $property_id);
        $deleteBookings->execute();
        $deleteBookings->close();

        // Then, delete related records from reviews
        $deleteReviews = $conn->prepare("DELETE FROM reviews WHERE property_id = ?");
        if (!$deleteReviews) {
            throw new Exception('Error preparing statement for deleting reviews');
        }
        $deleteReviews->bind_param("i", $property_id);
        $deleteReviews->execute();
        $deleteReviews->close();

        // Finally, delete the property
        $deleteProperty = $conn->prepare("DELETE FROM properties WHERE property_id = ?");
        if (!$deleteProperty) {
            throw new Exception('Error preparing statement for deleting property');
        }
        $deleteProperty->bind_param("i", $property_id);
        $deleteProperty->execute();
        $deleteProperty->close();

        // Commit the transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Property deleted successfully',
        ]);
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting property: ' . $e->getMessage(),
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Property ID is required',
    ]);
}
?>
