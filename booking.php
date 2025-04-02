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

    // Verify if user details exist
    $sql = "SELECT * FROM users WHERE user_id='$userId'";
    $userResult = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($userResult) === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found',
        ]);
        exit();
    }

    // Check if the booking details are provided
    if (!isset($_POST['check_in_date'], $_POST['check_out_date'], $_POST['property_id'], $_POST['days'], $_POST['arrival_time'], $_POST['full_guest_name'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Check-in date, check-out date, property ID, days, arrival time, and full guest name are required',
        ]);
        exit();
    }

    $checkInDate = $_POST['check_in_date'];
    $checkOutDate = $_POST['check_out_date'];
    $propertyId = $_POST['property_id'];
    $days = $_POST['days'];
    $arrivalTime = $_POST['arrival_time'];
    $fullGuestName = $_POST['full_guest_name'];

    // // Check for user verification image
    // if (!isset($_POST['user_verification_image'])) {
    //     echo json_encode([
    //         'success' => false,
    //         'message' => 'User verification image is required',
    //     ]);
    //     exit();
    // }

    // Query property details
    $sql = "SELECT * FROM booking_properties WHERE property_id='$propertyId'";
    $propertyResult = mysqli_query($conn, $sql);

    if (mysqli_num_rows($propertyResult) === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Property not found',
        ]);
        exit();
    }

    $propertyRow = mysqli_fetch_assoc($propertyResult);
    $price = $propertyRow['price'];
    $total = $price * $days;

    // Insert booking into bookings table with status set to 'unpaid' - now including arrival_time and full_guest_name
    $sql = "INSERT INTO bookings (check_in_date, check_out_date, booking_status, total, arrival_time, full_guest_name, user_id, property_id) 
            VALUES ('$checkInDate', '$checkOutDate', 'unpaid', '$total', '$arrivalTime', '$fullGuestName', '$userId', '$propertyId')";
    $bookingResult = mysqli_query($conn, $sql);

    if (!$bookingResult) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create booking: ' . mysqli_error($conn),
        ]);
        exit();
    }

    $bookingId = mysqli_insert_id($conn); // Fixed variable from $con to $conn

    echo json_encode([
        'success' => true,
        'message' => 'Booking created successfully',
        'booking_id' => $bookingId
    ]);

} catch (\Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage(),
    ]);
}

// Function to update booking status to 'paid'
function updateBookingStatusToPaid($bookingId) {
    global $conn;
    $sql = "UPDATE bookings SET booking_status='paid' WHERE booking_id='$bookingId'";
    if (mysqli_query($conn, $sql)) {
        return [
            'success' => true,
            'message' => 'Booking status updated to paid',
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to update booking status',
        ];
    }
}