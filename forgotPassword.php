<?php
include 'helpers/connection.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['email'])) {
    $email = $data['email'];
    $code = rand(111111, 999999); // 6-digit reset code
    $expiry = date('Y-m-d H:i:s', strtotime('+1 minute'));

    $userId = null;
    $accountType = null;

    // Check in users table
    $sql = "SELECT user_id AS id FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user) {
        $userId = $user['id'];
        $accountType = 'user';
    } else {
        // Check in vendors table
        $sql = "SELECT vendor_id AS id FROM vendors WHERE vendor_email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $vendor = mysqli_fetch_assoc($result);

        if ($vendor) {
            $userId = $vendor['id'];
            $accountType = 'vendor';
        } else {
            echo json_encode(['success' => false, 'message' => 'Email not registered']);
            exit();
        }
    }

    // Update reset code and expiration
    if ($accountType === 'user') {
        $updateSql = "UPDATE users SET reset_code = ?, reset_code_expires_at = ? WHERE user_id = ?";
    } else {
        $updateSql = "UPDATE vendors SET reset_code = ?, reset_code_expires_at = ? WHERE vendor_id = ?";
    }

    $stmt = mysqli_prepare($conn, $updateSql);
    mysqli_stmt_bind_param($stmt, "ssi", $code, $expiry, $userId);
    $result = mysqli_stmt_execute($stmt);

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Failed to update reset code']);
        exit();
    }

    // Send email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'aasticagrg00@gmail.com';
        $mail->Password = 'tzkh mvhl cayt oobv'; // Use Gmail App Password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

        $mail->setFrom('aasticagrg00@gmail.com', 'Easy Rentals');
        $mail->addAddress($email);
        $mail->Subject = 'Reset Password Code';
        $mail->Body = "Your password reset code is: $code (expires in 1 minute)";

        $mail->send();

        echo json_encode(['success' => true, 'message' => 'Code sent to your email']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Email send failed: ' . $mail->ErrorInfo]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
}
