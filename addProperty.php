<?php
include 'helpers/connection.php';
include 'helpers/auth_helper.php';

if (!isset($_POST['token'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Token is required',
    ]);
    exit();
}

$token = $_POST['token'];
$isVendor = isVendor($token);

if (!$isVendor) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access',
    ]);
    exit();
}

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
    $_POST['wifi'],
    $_POST['utilities'],
    $_POST['parking'],
    $_POST['pool'],
    $_POST['pet_friendly'],
    $_POST['peoples'],
    $_POST['crib'],
    $_POST['availability_status'],
    $_FILES['pimage1'],
    $_FILES['pimage2'],
    $_FILES['pimage3'],
    $_FILES['pimage4'],
    $_FILES['pimage5'],
    $_POST['price_per_night'],
    $_POST['check_in_time'],
    $_POST['check_out_time'],
    $_POST['category_id']
)) {
    $property_name = $_POST['property_name'];
    $city = $_POST['city'];
    $description = mysqli_escape_string($conn, $_POST['description']);
    $location = $_POST['location'];
    $p_type = $_POST['p_type'];
    $bhk = $_POST['bhk'];
    $bedroom = $_POST['bedroom'];
    $bathroom = $_POST['bathroom'];
    $balcony = $_POST['balcony'];
    $kitchen = $_POST['kitchen'];
    $wifi = $_POST['wifi'];
    $utilities = $_POST['utilities'];
    $parking = $_POST['parking'];
    $pool = $_POST['pool'];
    $pet_friendly = $_POST['pet_friendly'];
    $peoples = $_POST['peoples'];
    $crib = $_POST['crib'];
    $availability_status = $_POST['availability_status'];
    $price_per_night = $_POST['price_per_night'];
    $check_in_time = $_POST['check_in_time'];
    $check_out_time = $_POST['check_out_time'];
    $category_id = $_POST['category_id'];

    // Token-related vendor_id retrieval
    $vendorData = getUserIdFromToken($token);
    $vendor_id = $vendorData['vendor_id'];

    $images = ['pimage1', 'pimage2', 'pimage3', 'pimage4', 'pimage5'];
    $imagePaths = [];

    foreach ($images as $imageField) {
        $image = $_FILES[$imageField];
        $tempPath = $image['tmp_name'];
        $image_size = $image['size'];
        $imageName = $image['name'];
        $imageExt = pathinfo($imageName, PATHINFO_EXTENSION);

        if ($imageExt != 'jpg' && $imageExt != 'png' && $imageExt != 'jpeg' && $imageExt != 'webp') {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid image format for ' . $imageField,
            ]);
            exit();
        }

        if ($image_size > 1024 * 1024 * 5) {
            echo json_encode([
                'success' => false,
                'message' => 'Image size must be less than 5MB for ' . $imageField,
            ]);
            exit();
        }

        $newImageName = uniqid() . '.' . $imageExt;
        $newPath = './images/' . $newImageName;
        $actualPath = 'images/' . $newImageName;

        if (!move_uploaded_file($tempPath, $newPath)) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to upload ' . $imageField,
            ]);
            exit();
        }

        $imagePaths[] = $actualPath;
    }

    $sql = "INSERT INTO properties (
        property_name, city, description, location, p_type, bhk, bedroom, bathroom, balcony, kitchen, wifi,
        utilities, parking, pool, pet_friendly, peoples, crib, availability_status, pimage1, pimage2, pimage3,
        pimage4, pimage5, price_per_night, check_in_time, check_out_time, vendor_id, category_id
    ) VALUES (
        '$property_name', '$city', '$description', '$location', '$p_type', '$bhk', '$bedroom', '$bathroom', '$balcony',
        '$kitchen', '$wifi', '$utilities', '$parking', '$pool', '$pet_friendly', '$peoples', '$crib', 
        '$availability_status', '{$imagePaths[0]}', '{$imagePaths[1]}', '{$imagePaths[2]}', '{$imagePaths[3]}', 
        '{$imagePaths[4]}', '$price_per_night', '$check_in_time', '$check_out_time', '$vendor_id', '$category_id'
    )";

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to add property',
        ]);
        exit();
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Property added successfully',
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'All required fields must be filled',
    ]);
}
?>