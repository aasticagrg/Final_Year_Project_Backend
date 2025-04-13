<?php
include 'helpers/connection.php';
include 'helpers/auth_helper.php';

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

if (isset($_POST['property_id'])) {
    $property_id = $_POST['property_id'];

    // Prepare the query to check if the property belongs to the current vendor
    $sql = "SELECT * FROM properties WHERE property_id = ? AND vendor_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Error preparing the statement',
        ]);
        exit();
    }

    $stmt->bind_param("ii", $property_id, $vendor_id); // Bind as integers
    $stmt->execute();
    $result = $stmt->get_result();

    if (mysqli_num_rows($result) > 0) {
        // Proceed with deletion if the property belongs to the vendor
        $deleteSql = "DELETE FROM properties WHERE property_id = ? AND vendor_id = ?";
        $deleteStmt = $conn->prepare($deleteSql);

        if (!$deleteStmt) {
            echo json_encode([
                'success' => false,
                'message' => 'Error preparing the delete statement',
            ]);
            exit();
        }

        $deleteStmt->bind_param("ii", $property_id, $vendor_id); // Bind as integers
        if ($deleteStmt->execute()) {
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

        $deleteStmt->close();
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Property not found or you do not have permission to delete it',
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
