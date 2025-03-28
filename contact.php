<?php
include 'helpers/connection.php';

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Check if required fields are set
if (isset($_POST['full_name'], $_POST['email'], $_POST['message'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = isset($_POST['phone']) ? $_POST['phone'] : ''; // Phone is optional
    $message = $_POST['message'];

    // Insert into database
    $sql = "INSERT INTO contacts (full_name, email, phone, message) VALUES ('$full_name', '$email', '$phone', '$message')";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Your Message is sent successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Please fill in all required fields.'
    ]);
}
?>
