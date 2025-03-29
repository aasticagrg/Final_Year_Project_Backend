<?php
include '../helpers/connection.php';

if (isset($_POST['name'], $_POST['email'], $_POST['password'], $_POST['phone_no'], $_POST['user_address'])) {

    $email = $_POST['email'];
    $name = $_POST['name'];
    $password = $_POST['password'];
    $phone_no = $_POST['phone_no'];
    $user_address = $_POST['user_address'];

    // Check if email already exists
    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = mysqli_query($conn, $sql);
    $count = mysqli_num_rows($result);

    if ($count > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Email already exists',
        ]);
        exit();
    }

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user (excluding user_verification)
    $sql = "INSERT INTO users (name, email, password, phone_no, user_address, role, registration_date) 
            VALUES ('$name', '$email', '$hashedPassword', '$phone_no', '$user_address', 'user', CURDATE())";

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to register user',
        ]);
        exit();
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'User registered successfully',
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'All fields are required ',
    ]);
}
?>
