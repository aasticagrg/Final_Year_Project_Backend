<?php
include 'helpers/connection.php';

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['code'], $data['email'], $data['newPassword'])) {
    $email = $data['email'];
    $code = $data['code'];
    $newPassword = $data['newPassword'];


    // Check if the email belongs to a user or a vendor
    $sql_user = "SELECT * FROM users WHERE email = '$email' AND reset_code = '$code'";
    $result_user = mysqli_query($conn, $sql_user);

    $sql_vendor = "SELECT * FROM vendors WHERE vendor_email = '$email' AND reset_code = '$code'";
    $result_vendor = mysqli_query($conn, $sql_vendor);

    if (mysqli_num_rows($result_user) === 0 && mysqli_num_rows($result_vendor) === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid code or email',
        ]);
        exit();
    }

    // Hash the new password before storing it
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    if (mysqli_num_rows($result_user) > 0) {
        // Handle user reset
        $user = mysqli_fetch_assoc($result_user);
        $userId = $user['user_id'];
        $sql_update_user = "UPDATE users SET password = '$hashedPassword', reset_code = NULL WHERE user_id = '$userId'";

        if (mysqli_query($conn, $sql_update_user)) {
            echo json_encode([
                'success' => true,
                'message' => 'User password reset successfully',
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to reset user password',
            ]);
        }
    } elseif (mysqli_num_rows($result_vendor) > 0) {
        // Handle vendor reset
        $vendor = mysqli_fetch_assoc($result_vendor);
        $vendorId = $vendor['vendor_id'];
        $sql_update_vendor = "UPDATE vendors SET password = '$hashedPassword', reset_code = NULL WHERE vendor_id = '$vendorId'";

        if (mysqli_query($conn, $sql_update_vendor)) {
            echo json_encode([
                'success' => true,
                'message' => 'Vendor password reset successfully',
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to reset vendor password',
            ]);
        }
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'All fields are required',
    ]);
}
?>
