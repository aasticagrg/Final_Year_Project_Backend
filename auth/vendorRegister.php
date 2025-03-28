<?php
include '../helpers/connection.php';

// Check if all required fields are set
if (isset($_POST['vendor_name'], $_POST['vendor_email'], $_POST['vendor_address'], $_POST['contact_no'], $_POST['password'], $_FILES['vendor_verification'])) {
    $name = $_POST['vendor_name'];
    $email = $_POST['vendor_email'];
    $address = $_POST['vendor_address'];
    $phone_no = $_POST['contact_no'];
    $password = $_POST['password'];
    $image = $_FILES['vendor_verification'];

    // Check if email already exists
    $sql = "SELECT * FROM vendors WHERE vendor_email = '$email'";
    $result = mysqli_query($conn, $sql);
    $count = mysqli_num_rows($result);

    if ($count > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'The email you provided already exists'
        ]);
        exit();
    }

    // Hash password for security
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Image validation and upload
    $tempPath = $image['tmp_name'];
    $image_size = $image['size'];
    $imageName = $image['name'];
    $imageExt = pathinfo($imageName, PATHINFO_EXTENSION);

    if ($imageExt != 'jpg' && $imageExt != 'png' && $imageExt != 'jpeg' && $imageExt != 'webp') {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid image format',
        ]);
        exit();
    }

    if ($image_size > 1024 * 1024 * 5) {
        echo json_encode([
            'success' => false,
            'message' => 'Image should be less than 5MB',
        ]);
        exit();
    }

    $newImageName = uniqid() . '.' . $imageExt;
    $newPath = '../images/' . $newImageName;
    $actualPath = 'images/' . $newImageName;

    $moved = move_uploaded_file($tempPath, $newPath);

    if (!$moved) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to upload image',
        ]);
        exit();
    }

    // Insert into database
    $sql = "INSERT INTO vendors (vendor_name, vendor_email, vendor_address, contact_no, password, vendor_verification) VALUES ('$name', '$email', '$address', '$phone_no', '$hashedPassword', '$actualPath')";

    $result = mysqli_query($conn, $sql);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Vendor registered successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to register vendor'
        ]);
        exit();
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'All fields are required'
    ]);
}
?>
