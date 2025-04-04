<?php
// Include database connection and authentication helper
include 'helpers/connection.php';
include 'helpers/auth_helper.php';

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

        // Sanitize method input to prevent SQL injection
        if (!in_array($method, ['online', 'on_property'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid payment method',
            ]);
            exit();
        }

        // Extract transaction_id if the method is 'online'
        $transactionId = null;
        if ($method === 'online' && isset($paymentDetails['transaction_id'])) {
            $transactionId = $paymentDetails['transaction_id']; // Assuming the response contains a transaction_id
        }

        // Convert paymentDetails array into JSON if it's an array (prevents array to string conversion error)
        if (is_array($paymentDetails)) {
            $paymentDetails = json_encode($paymentDetails);
        }

        // Set the payment status and booking status based on the method
        $paymentStatus = ($method === 'online') ? 'completed' : 'pending';
        $bookingStatus = ($method === 'online') ? 'paid' : 'pending';

        // Prepare the SQL query to insert payment details
        $sql = "INSERT INTO payments (user_id, amount, booking_id, details, method, payment_status, payment_date, transaction_id) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iisssss", $userId, $amount, $bookingId, $paymentDetails, $method, $paymentStatus, $transactionId);

        // Execute the query
        $result = mysqli_stmt_execute($stmt);

        if ($result) {
            // Update the booking status
            $sql = "UPDATE bookings SET booking_status = ? WHERE booking_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "si", $bookingStatus, $bookingId);
            $bookingResult = mysqli_stmt_execute($stmt);

            if (!$bookingResult) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update booking status',
                ]);
                exit();
            }

            // If the payment method is 'on_property', do not show "payment successful"
            if ($method === 'on_property') {
                echo json_encode([
                    'success' => true,
                    'message' => 'Booking created successfully',
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'Payment successful',
                ]);
            }
            exit();
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Payment failed',
            ]);
            exit();
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'amount, booking_id, details, and method are required',
        ]);
        exit();
    }
} catch (Exception $e) {
    // Handle any exceptions that might occur
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage(),
    ]);
    exit();
}
?>
