<?php
include 'helpers/connection.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['email'])) {
    $email = $data['email'];
    $code = rand(111111, 999999);  // Generate random reset code

    $userId = null;
    $accountType = null;

    // First check in users table
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
        // Then check in vendors table
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
            echo json_encode([
                'success' => false,
                'message' => 'Email not registered',
            ]);
            exit();
        }
    }

    // Update reset code based on account type
    if ($accountType === 'user') {
        $updateSql = "UPDATE users SET reset_code = ? WHERE user_id = ?";
    } else {
        $updateSql = "UPDATE vendors SET reset_code = ? WHERE vendor_id = ?";
    }

    $stmt = mysqli_prepare($conn, $updateSql);
    mysqli_stmt_bind_param($stmt, "si", $code, $userId);
    $result = mysqli_stmt_execute($stmt);

    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update reset code',
        ]);
        exit();
    }

    // Send the reset code via email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'aasticagrg00@gmail.com';
        $mail->Password = 'tzkh mvhl cayt oobv';  // Use App Passwords, not your main password!
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // Optional for self-signed certs
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ),
        );

        $mail->setFrom('aasticagrg00@gmail.com', 'Your Booking Platform');
        $mail->addAddress($email);
        $mail->Subject = 'Reset Password Code';
        $mail->Body = "Your password reset code is: $code";

        $mail->send();

        echo json_encode([
            'success' => true,
            'message' => 'Code sent to your email',
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send email. Error: ' . $mail->ErrorInfo,
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Email is required',
    ]);
}
?>
