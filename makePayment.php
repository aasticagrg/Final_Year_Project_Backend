<?php
include './helpers/connection.php';
include './helpers/authHelper.php';

try {
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
    if (isset($_POST['amount'], $_POST['orderId'], $_POST['otherData'], $_POST['method'])) {
        $amount = $_POST['amount'];
        $orderId = $_POST['orderId'];
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
        $sql = "INSERT INTO payments (user_id, amount, order_id, details, method) VALUES ('$userId', '$amount', '$orderId', '$otherData', '$method')";
        $result = mysqli_query($conn, $sql);

        if ($result) {
            // Update the corresponding order status to 'paid'
            $sql = "UPDATE orders SET status = 'paid' WHERE order_id = '$orderId'";
            $result = mysqli_query($conn, $sql);

            if (!$result) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update order status',
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
            'message' => 'amount, orderId, otherData, and method are required',
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