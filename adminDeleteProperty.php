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

    $sql = "DELETE FROM properties WHERE property_id = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Error preparing the statement',
        ]);
        exit();
    }

    $stmt->bind_param("i", $property_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Property deleted successfully',
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting property',
        ]);
    }

    $stmt->close();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Property ID is required',
    ]);
}
?>
