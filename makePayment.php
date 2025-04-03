<?php
// Include database connection
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
    
    $token = $_POST['token'];
    $userId = getUserIdFromToken($token);
    
    // Check for required fields in the POST request
    if (isset($_POST['amount'], $_POST['booking_id'], $_POST['otherData'], $_POST['method'])) {
        $amount = $_POST['amount'];
        $bookingId = $_POST['booking_id'];
        $otherData = $_POST['otherData'];
        $method = $_POST['method'];  // 'online' or 'on_property'
        
        // Sanitize method input to prevent SQL injection
        if (!in_array($method, ['online', 'on_property'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid payment method',
            ]);
            exit();
        }
        
        // Insert payment details into the payments table
        $sql = "INSERT INTO payments (user_id, amount, booking_id, details, method, payment_status, payment_date) 
                VALUES ('$userId', '$amount', '$bookingId', '$otherData', '$method', 'completed', NOW())";
        $result = mysqli_query($conn, $sql);
        
        if ($result) {
            // Update the booking status to 'paid' in the bookings table
            $sql = "UPDATE bookings SET booking_status = 'paid' WHERE booking_id = '$bookingId'";
            $bookingResult = mysqli_query($conn, $sql);
            
            // Also update the orders table if it exists and is connected to bookings
            $sql = "UPDATE orders SET status = 'paid' WHERE booking_id = '$bookingId'";
            $orderResult = mysqli_query($conn, $sql);
            
            if (!$bookingResult) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update booking status',
                ]);
                exit();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Payment successful',
            ]);
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
            'message' => 'amount, bookingId, otherData, and method are required',
        ]);
        exit();
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage(),
    ]);
    exit();
}
?>