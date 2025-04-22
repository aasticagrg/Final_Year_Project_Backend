<?php
include 'helpers/connection.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['email'])) {
    $email = $data['email'];

    // Check both users and vendors for the email
    $sql = "SELECT * FROM users WHERE email = ? UNION SELECT * FROM vendors WHERE vendor_email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $email, $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Email not registered',
        ]);
        exit();
    }

    $user = mysqli_fetch_assoc($result);
    $code = rand(111111, 999999);  // Generate random code

    // Check if it's a user or a vendor and update accordingly
    if (isset($user['user_id'])) {
        // It's a user
        $userId = $user['user_id'];
        $sql = "UPDATE users SET reset_code = ? WHERE user_id = ?";
    } else {
        // It's a vendor
        $userId = $user['vendor_id'];
        $sql = "UPDATE vendors SET reset_code = ? WHERE vendor_id = ?";
    }

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $code, $userId);
    $result = mysqli_stmt_execute($stmt);

    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update code',
        ]);
        exit();
    }

    // Send the reset code via email
    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'aasticagrg00@gmail.com';
    $mail->Password = 'tzkh mvhl cayt oobv';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    $mail->setFrom('aasticagrg00@gmail.com.com');
    $mail->addAddress($email);
    $mail->Subject = 'Reset Password Code for Your Account';
    $mail->Body = "Your reset password code is $code";

    $isMailSent = $mail->send();

    if ($isMailSent) {
        echo json_encode([
            'success' => true,
            'message' => 'Code sent to your email',
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send code',
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Email is required',
    ]);
}
?>
