<?php
// Include the database connection file
include 'helpers/connection.php'; // Ensure the path is correct

// Set Content-Type for JSON responses
header('Content-Type: application/json');

// Check if the images directory exists
if (!is_dir('images/')) {
    echo json_encode(['success' => false, 'message' => 'Images directory does not exist']);
    exit();
}

// Ensure all required fields and files are present
if (isset(
    $_POST['property_name'],
    $_POST['city'],
    $_POST['description'],
    $_POST['location'],
    $_POST['p_type'],
    $_POST['bhk'],
    $_POST['bedroom'],
    $_POST['bathroom'],
    $_POST['balcony'],
    $_POST['kitchen'],
    $_POST['availability_status'],
    $_POST['price_per_night'],
    $_POST['vendor_id'],
    $_FILES['pimage1'],
    $_FILES['pimage2'],
    $_FILES['pimage3']
)) {
    // Extract inputs
    $property_name = $_POST['property_name'];
    $city = $_POST['city'];
    $description = $_POST['description'];
    $location = $_POST['location'];
    $p_type = $_POST['p_type'];
    $bhk = $_POST['bhk'];
    $bedroom = $_POST['bedroom'];
    $bathroom = $_POST['bathroom'];
    $balcony = $_POST['balcony'];
    $kitchen = $_POST['kitchen'];
    $availability_status = $_POST['availability_status'];
    $price_per_night = $_POST['price_per_night'];
    $vendor_id = $_POST['vendor_id'];

    function uploadImage($image, $fieldName) {
        $tempPath = $image['tmp_name'];
        $imageSize = $image['size'];
        $imageName = $image['name'];
        $imageExt = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));

        // Validate image type
        if (!in_array($imageExt, ['jpg', 'png', 'jpeg', 'webp'])) {
            echo json_encode(['success' => false, 'message' => "Invalid format for $fieldName"]);
            exit();
        }

        // Validate image size
        if ($imageSize > 5 * 1024 * 1024) { // 5MB limit
            echo json_encode(['success' => false, 'message' => "$fieldName should be < 5MB"]);
            exit();
        }

        $newImageName = uniqid() . '.' . $imageExt;
        $newPath = 'images/' . $newImageName;

        // Move the uploaded file
        if (!move_uploaded_file($tempPath, $newPath)) {
            echo json_encode(['success' => false, 'message' => "Failed to upload $fieldName"]);
            exit();
        }

        return $newPath;
    }

    // Upload images
    $pimage1 = uploadImage($_FILES['pimage1'], 'pimage1');
    $pimage2 = uploadImage($_FILES['pimage2'], 'pimage2');
    $pimage3 = uploadImage($_FILES['pimage3'], 'pimage3');

    // SQL query to insert the property details
    $sql = "INSERT INTO properties (property_name, city, description, location, p_type, bhk, bedroom, bathroom, balcony, kitchen, availability_status, price_per_night, pimage1, pimage2, pimage3, vendor_id) 
            VALUES ('$property_name', '$city', '$description', '$location', '$p_type', '$bhk', '$bedroom', '$bathroom', '$balcony', '$kitchen', '$availability_status', '$price_per_night', '$pimage1', '$pimage2', '$pimage3', '$vendor_id')";

    // Execute query
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Property added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add property']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
}
?>
