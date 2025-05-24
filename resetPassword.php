<?php
include 'helpers/connection.php';

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['code'], $data['email'], $data['newPassword'], $data['confirmPassword'])) {
    $email = mysqli_real_escape_string($conn, $data['email']);
    $code = mysqli_real_escape_string($conn, $data['code']);
    $newPassword = $data['newPassword'];
    $confirmPassword = $data['confirmPassword'];

    // Check if passwords match
    if ($newPassword !== $confirmPassword) {
        echo json_encode([
            'success' => false,
            'message' => 'Passwords do not match.',
        ]);
        exit;
    }

    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Try resetting for user first
    $sql_user = "SELECT * FROM users WHERE email = '$email' AND reset_code = '$code'";
    $result_user = mysqli_query($conn, $sql_user);

    if (mysqli_num_rows($result_user) > 0) {
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
        exit;
    }

    // Try resetting for vendor
    $sql_vendor = "SELECT * FROM vendors WHERE vendor_email = '$email' AND reset_code = '$code'";
    $result_vendor = mysqli_query($conn, $sql_vendor);

    if (mysqli_num_rows($result_vendor) > 0) {
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
        exit;
    }

    // Neither user nor vendor matched
    echo json_encode([
        'success' => false,
        'message' => 'Invalid reset code or email.',
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'All fields (email, code, newPassword, confirmPassword) are required.',
    ]);
}
?>
