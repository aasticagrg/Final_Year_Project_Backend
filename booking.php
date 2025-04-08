<?php
include 'helpers/connection.php';
include 'helpers/auth_helper.php';

try {
    // Decode JSON from the request body
    $data = json_decode(file_get_contents("php://input"), true);

    // Check if token exists in the request data
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

    if (!$userData || !isset($userData['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid token',
        ]);
        exit();
    }

    // Extract and validate necessary fields from the request
    if (!isset($data['check_in_date'], $data['check_out_date'], $data['properties'], $data['arrival_time'], $data['full_guest_name'])) {
        echo json_encode([
            'success' => false,
            'message' => 'All details are required',
        ]);
        exit();
    }

    // Extract booking data from request
    $checkInDate = $data['check_in_date'];
    $checkOutDate = $data['check_out_date'];
    $arrivalTime = $data['arrival_time'];
    $fullGuestName = $data['full_guest_name'];
    $properties = $data['properties'];

    // Start a database transaction
    mysqli_begin_transaction($conn);

    // Insert a new booking into the bookings table
    $sql = "INSERT INTO bookings (user_id, check_in_date, check_out_date, total_price, arrival_time, full_guest_name) 
            VALUES (?, ?, ?, ?, ?, ?)";

    $total = 0;
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "issdss", $userData['user_id'], $checkInDate, $checkOutDate, $total, $arrivalTime, $fullGuestName);
    $bookingResult = mysqli_stmt_execute($stmt);

    if (!$bookingResult) {
        throw new Exception("Failed to create booking: " . mysqli_error($conn));
    }

    $bookingId = mysqli_insert_id($conn);
    $totalPrice = 0;

    // Loop through properties and insert them into the booking_properties table
    foreach ($properties as $property) {
        if (!isset($property['property_id'], $property['days'])) {
            throw new Exception("Property details missing");
        }

        $propertyId = $property['property_id'];
        $days = $property['days'];

        // Fetch property details
        $sql = "SELECT price_per_night, vendor_id FROM properties WHERE property_id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $propertyId);
        mysqli_stmt_execute($stmt);
        $propertyResult = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($propertyResult) === 0) {
            throw new Exception("Property not found with ID: " . $propertyId);
        }

        $propertyRow = mysqli_fetch_assoc($propertyResult);
        $pricePerNight = $propertyRow['price_per_night'];
        $vendorId = $propertyRow['vendor_id'];
        $propertyTotalPrice = $pricePerNight * $days;

        // Insert property into booking_properties table
        $sql = "INSERT INTO booking_properties (booking_id, property_id, days, total_price, vendor_id) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiidi", $bookingId, $propertyId, $days, $propertyTotalPrice, $vendorId);
        $propertyResult = mysqli_stmt_execute($stmt);

        if (!$propertyResult) {
            throw new Exception("Failed to add property to booking: " . mysqli_error($conn));
        }

        // Add to total
        $totalPrice += $propertyTotalPrice;
    }

    // Update total price in bookings table
    $sql = "UPDATE bookings SET total_price = ? WHERE booking_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "di", $totalPrice, $bookingId);
    mysqli_stmt_execute($stmt);

    // Commit the transaction
    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Booking created successfully',
        'booking_id' => $bookingId
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);

    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage(),
    ]);
}
?>
