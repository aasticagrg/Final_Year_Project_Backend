<?php
include 'helpers/connection.php';
header('Content-Type: application/json');

// Decode incoming JSON
$data = json_decode(file_get_contents("php://input"), true);

// Check for required fields
if (!isset($data['email'], $data['code'])) {
    echo json_encode(["success" => false, "message" => "Email and code are required"]);
    exit;
}

$email = $data['email'];
$code = $data['code'];

// Check in users table
$sql_user = "SELECT 1 FROM users WHERE email = ? AND reset_code = ?";
$stmt_user = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt_user, "ss", $email, $code);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);

if (mysqli_num_rows($result_user) > 0) {
    echo json_encode(["success" => true]);
    exit;
}

// Check in vendors table
$sql_vendor = "SELECT 1 FROM vendors WHERE vendor_email = ? AND reset_code = ?";
$stmt_vendor = mysqli_prepare($conn, $sql_vendor);
mysqli_stmt_bind_param($stmt_vendor, "ss", $email, $code);
mysqli_stmt_execute($stmt_vendor);
$result_vendor = mysqli_stmt_get_result($stmt_vendor);

if (mysqli_num_rows($result_vendor) > 0) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Invalid code or email"]);
}

// Optional: close statements and connection
mysqli_stmt_close($stmt_user);
mysqli_stmt_close($stmt_vendor);
mysqli_close($conn);
?>
