<?php
include 'helpers/connection.php';
include 'helpers/auth_helper.php';

// Decode JSON from the request body
$data = json_decode(file_get_contents("php://input"), true);

// Debugging: log the received data
error_log("Received JSON data: " . print_r($data, true));

try {
    // Check if the token exists in the request data
    if (!isset($data['token'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Token is required',
        ]);
        exit();
    }

    // Retrieve the user ID from the token
    $token = $data['token'];
    $userData = getUserIdFromToken($token);

    // Extract the user_id from the returned array
    $userId = $userData['user_id'];

    if (!$userId) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid token',
        ]);
        exit();
    }

    // Validate all required fields in the request
    if (!isset($data['check_in_date'], $data['check_out_date'], $data['properties'], $data['arrival_time'], $data['full_guest_name'])) {
        echo json_encode([
            'success' => false,
            'message' => 'All details are required',
        ]);
        exit();
    }

    // Extract general booking details
    $checkInDate = $data['check_in_date'];
    $checkOutDate = $data['check_out_date'];
    $arrivalTime = $data['arrival_time'];
    $fullGuestName = $data['full_guest_name'];
    
    // Calculate total price (we will calculate this after inserting properties)
    $totalPrice = 0;

    // Start a transaction to ensure both tables are updated
    mysqli_begin_transaction($conn);

    // Insert booking into the bookings table
    $sql = "INSERT INTO bookings (user_id, check_in_date, check_out_date, total_price, arrival_time, full_guest_name) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "issdss", $userId, $checkInDate, $checkOutDate, $totalPrice, $arrivalTime, $fullGuestName);
    $bookingResult = mysqli_stmt_execute($stmt);

    if (!$bookingResult) {
        throw new Exception("Failed to create booking: " . mysqli_error($conn));
    }

    $bookingId = mysqli_insert_id($conn);

    // Loop through the properties data (we assume it's an array of properties)
    foreach ($data['properties'] as $property) {
        if (!isset($property['property_id'], $property['days'])) {
            throw new Exception("Property details missing");
        }

        $propertyId = $property['property_id'];
        $days = $property['days'];  // Previously 'nights', now 'days'

        // Fetch the price per night from the properties table
        $sql = "SELECT price_per_night, vendor_id FROM properties WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $propertyId);
        mysqli_stmt_execute($stmt);
        $propertyResult = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($propertyResult) === 0) {
            throw new Exception("Property not found with ID: " . $propertyId);
        }

        $propertyRow = mysqli_fetch_assoc($propertyResult);
        $pricePerNight = $propertyRow['price_per_night'];
        $vendorId = $propertyRow['vendor_id'];  // Fetch the vendor_id associated with the property
        $propertyTotalPrice = $pricePerNight * $days;

        // Insert property details into the booking_properties table
        $sql = "INSERT INTO booking_properties (booking_id, property_id, days, total_price, vendor_id) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiidi", $bookingId, $propertyId, $days, $propertyTotalPrice, $vendorId);  // bind 'days' and 'total_price'
        $propertyResult = mysqli_stmt_execute($stmt);

        if (!$propertyResult) {
            throw new Exception("Failed to add property to booking: " . mysqli_error($conn));
        }

        // Update the total booking price
        $totalPrice += $propertyTotalPrice;
    }

    // Update the total price in the bookings table
    $sql = "UPDATE bookings SET total_price = ? WHERE booking_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "di", $totalPrice, $bookingId);
    mysqli_stmt_execute($stmt);

    // Commit the transaction
    mysqli_commit($conn);

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Booking created successfully',
        'booking_id' => $bookingId
    ]);

} catch (Exception $e) {
    // Rollback the transaction if there was an error
    mysqli_rollback($conn);

    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage(),
    ]);
}

?>
