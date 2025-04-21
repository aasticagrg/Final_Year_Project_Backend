<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/../vendor/autoload.php'; 

include 'connection.php'; 

function sendBookingEmails($bookingId) {
  global $conn; // Make sure to only use $conn here for executing queries

  // Prepare and execute the SQL query to fetch booking details
  $stmt = $conn->prepare("SELECT 
          u.name AS user_name, u.email AS user_email,
          v.vendor_name, v.vendor_email,
          p.property_name, b.check_in_date, b.check_out_date, b.total_price
      FROM bookings b
      JOIN users u ON b.user_id = u.user_id
      JOIN booking_properties bp ON b.booking_id = bp.booking_id
      JOIN properties p ON bp.property_id = p.property_id
      JOIN vendors v ON p.vendor_id = v.vendor_id
      WHERE b.booking_id = ?");

  $stmt->bind_param("i", $bookingId); // Bind the actual booking ID here
  $stmt->execute();
  $result = $stmt->get_result();

  // Check if data exists
  if (!$result || $result->num_rows == 0) {
      error_log("No booking found for booking ID: $bookingId");
      return;
  }

  // Fetch the result
  $data = $result->fetch_assoc();

  // Ensure all necessary data exists
  if (!isset($data['user_email'], $data['user_name'], $data['vendor_email'], $data['vendor_name'], $data['property_name'], $data['check_in_date'], $data['check_out_date'], $data['total_price'])) {
      error_log("Incomplete booking data for booking ID: $bookingId");
      return;
  }

  // Format the price with 2 decimal places
  $formattedPrice = number_format((float)$data['total_price'], 2);

  // --- Send to user ---
  $mail = new PHPMailer(true);
  try {
      $mail->isSMTP();
      $mail->Host = 'smtp.gmail.com'; 
      $mail->SMTPAuth = true;
      $mail->Username = 'aasticagrg00@gmail.com'; // Replace with your email
      $mail->Password = 'tzkh mvhl cayt oobv'; // Replace with your app password
      $mail->SMTPSecure = 'tls';
      $mail->Port = 587;

      $mail->setFrom('aasticagrg00@gmail.com', 'Easy Rentals');
      $mail->addAddress($data['user_email'], $data['user_name']);
      $mail->isHTML(true);
      $mail->Subject = "Booking Confirmed: {$data['property_name']}";
      $mail->Body = "
          Hi {$data['user_name']},<br><br>
          Your booking at <strong>{$data['property_name']}</strong> has been confirmed.<br>
          <b>Check-in:</b> {$data['check_in_date']}<br>
          <b>Check-out:</b> {$data['check_out_date']}<br>
          <b>Total Price:</b> $formattedPrice<br><br>
          Thank you for booking with us!
      ";
      $mail->send();
  } catch (Exception $e) {
      error_log("User mail error: " . $mail->ErrorInfo);
  }

  // --- Send to vendor ---
  $mail2 = new PHPMailer(true);
  try {
      $mail2->isSMTP();
      $mail2->Host = 'smtp.gmail.com'; // Same SMTP
      $mail2->SMTPAuth = true;
      $mail2->Username = 'aasticagrg00@gmail.com'; // Replace with your email
      $mail2->Password = 'tzkh mvhl cayt oobv'; // Replace with your app password
      $mail2->SMTPSecure = 'tls';
      $mail2->Port = 587;

      $mail2->setFrom('aasticagrg00@gmail.com', 'Easy Rentals');
      $mail2->addAddress($data['vendor_email'], $data['vendor_name']);
      $mail2->isHTML(true);
      $mail2->Subject = "New Booking: {$data['property_name']}";
      $mail2->Body = "
          Hello {$data['vendor_name']},<br><br>
          A new booking has been made for your property <strong>{$data['property_name']}</strong>.<br>
          <b>Guest:</b> {$data['user_name']}<br>
          <b>Check-in:</b> {$data['check_in_date']}<br>
          <b>Check-out:</b> {$data['check_out_date']}<br>
          <b>Total Price:</b> $formattedPrice<br><br>
          Please prepare accordingly.
      ";
      $mail2->send();
  } catch (Exception $e) {
      error_log("Vendor mail error: " . $mail2->ErrorInfo);
  }
}
