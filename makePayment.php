<?php
// Include database connection and authentication helper
include 'helpers/connection.php';
include 'helpers/auth_helper.php';
include 'helpers/mail_helper.php';

try {
    // Check if token is provided
    if (!isset($_POST['token'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Token is required',
        ]);
        exit();
    }

    // Get the user ID from the token
    $token = $_POST['token'];
    $userId = getUserIdFromToken($token);

    // Check if user ID is valid
    if (!$userId) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid token',
        ]);
        exit();
    }

    // Check if the required fields are provided
    if (isset($_POST['amount'], $_POST['booking_id'], $_POST['details'], $_POST['method'])) {
        $amount = $_POST['amount'];
        $bookingId = $_POST['booking_id'];
        $paymentDetails = $_POST['details']; // Payment gateway response (should be JSON or plain text)
        $method = $_POST['method']; // 'online' or 'on_property'

        // Sanitize method input
        if (!in_array($method, ['online', 'on_property'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid payment method',
            ]);
            exit();
        }

        // Convert paymentDetails array into JSON if needed
        if (is_array($paymentDetails)) {
            $paymentDetails = json_encode($paymentDetails);
        }

        // Set the payment status based on the method
        $paymentStatus = ($method === 'online') ? 'completed' : 'pending';
        $bookingStatus = 'booked'; // Always set to 'booked' if payment recorded successfully

        // Start transaction
        mysqli_begin_transaction($conn);

        // Insert payment details
        $sql = "INSERT INTO payments (user_id, amount, booking_id, details, method, payment_status, payment_date) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iissss", $userId, $amount, $bookingId, $paymentDetails, $method, $paymentStatus);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to record payment details");
        }

        // Update booking status to 'booked' (for both online and on_property)
        $sql = "UPDATE bookings SET booking_status = ? WHERE booking_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $bookingStatus, $bookingId);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to update booking status");
        }

        // Commit transaction
        mysqli_commit($conn);

        // Send confirmation email
        sendBookingEmails($bookingId);

        // Respond based on method
        if ($method === 'on_property') {
            echo json_encode([
                'success' => true,
                'message' => 'Booking created successfully, payment pending at property',
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Payment successful',
            ]);
        }

    } else {
        echo json_encode([
            'success' => false,
            'message' => 'amount, booking_id, details, and method are required',
        ]);
        exit();
    }

} catch (Exception $e) {
    mysqli_rollback($conn);

    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage(),
    ]);
    exit();
}
?>
