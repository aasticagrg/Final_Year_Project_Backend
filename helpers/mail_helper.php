<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/../vendor/autoload.php'; 

include 'connection.php'; 

function sendBookingEmails($bookingId) {
  global $conn; 

  // Prepare and execute the SQL query to fetch booking details
  $stmt = $conn->prepare("SELECT 
            u.name AS user_name, u.email AS user_email, u.phone_no AS user_phone,
            v.vendor_name, v.vendor_email, v.contact_no,
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
    
      $mail->Subject = "Your Booking is Confirmed: {$data['property_name']}";

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; font-size: 15px; color: #333;'>
                <p>Dear {$data['user_name']},</p>

                <p>We are pleased to inform you that your booking at <strong>{$data['property_name']}</strong> has been successfully confirmed.</p>

                <h3 style='color: #2c3e50;'>Booking Details</h3>
                <ul style='line-height: 1.8;'>
                    <li><strong>Property:</strong> {$data['property_name']}</li>
                    <li><strong>Check-in Date:</strong> {$data['check_in_date']}</li>
                    <li><strong>Check-out Date:</strong> {$data['check_out_date']}</li>
                    <li><strong>Total Price:</strong> $formattedPrice</li>
                </ul>

                <p>If you have any questions or need further assistance regarding your stay, please feel free to contact the property owner:</p>

                <ul style='line-height: 1.8;'>
                    <li><strong>Owner:</strong> {$data['vendor_name']}</li>
                    <li><strong>Contact:</strong> {$data['vendor_email']} / {$data['contact_no']}</li>
                </ul>

                <p>Thank you for choosing <strong>Easy Rentals</strong>. We hope you have a pleasant and comfortable stay!</p>

                <br>
                <p style='font-size: 13px; color: #888;'>This is an automated message. Please do not reply directly to this email.</p>
            </div>
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
      $mail2->Username = 'aasticagrg00@gmail.com';
      $mail2->Password = 'tzkh mvhl cayt oobv'; // app password
      $mail2->SMTPSecure = 'tls';
      $mail2->Port = 587;

      $mail2->setFrom('aasticagrg00@gmail.com', 'Easy Rentals');
      $mail2->addAddress($data['vendor_email'], $data['vendor_name']);
      $mail2->isHTML(true);
      $mail2->Subject = "New Booking Received: {$data['property_name']}";

      $mail2->Body = "
        <div style='font-family: Arial, sans-serif; font-size: 15px; color: #333;'>
            <p>Dear {$data['vendor_name']},</p>

            <p>We're pleased to inform you that a new booking has been made for your property <strong>{$data['property_name']}</strong>.</p>

            <h3 style='color: #2c3e50;'>Booking Details</h3>
            <ul style='line-height: 1.8;'>
                <li><strong>Guest Name:</strong> {$data['user_name']}</li>
                <li><strong>Guest Email:</strong> {$data['user_email']}</li>
                <li><strong>Guest Phone:</strong> {$data['user_phone']}</li>
                <li><strong>Check-in Date:</strong> {$data['check_in_date']}</li>
                <li><strong>Check-out Date:</strong> {$data['check_out_date']}</li>
                <li><strong>Total Price:</strong> $formattedPrice</li>
            </ul>

            <p>Please ensure the property is ready for the guest’s arrival and feel free to reach out to them for any clarifications.</p>

            <p>Thank you for partnering with <strong>Easy Rentals</strong>.</p>

            <br>
            <p style='font-size: 13px; color: #888;'>This is an automated message. Please do not reply directly to this email.</p>
        </div>
    ";

      $mail2->send();
  } catch (Exception $e) {
      error_log("Vendor mail error: " . $mail2->ErrorInfo);
  }
}
function sendBookingCancellationEmail($bookingId) {
    global $conn;
  
    $stmt = $conn->prepare(" SELECT 
            u.name AS user_name, 
            u.email AS user_email,
            p.property_name, 
            b.check_in_date, 
            b.check_out_date,
            v.vendor_name,
            v.contact_no
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        JOIN booking_properties bp ON b.booking_id = bp.booking_id
        JOIN properties p ON bp.property_id = p.property_id
        JOIN vendors v ON p.vendor_id = v.vendor_id
        WHERE b.booking_id = ?");
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
  
    if (!$result || $result->num_rows === 0) {
        error_log("No booking found for cancellation email. Booking ID: $bookingId");
        return;
    }
  
    $data = $result->fetch_assoc();
  
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'aasticagrg00@gmail.com';
        $mail->Password = 'tzkh mvhl cayt oobv'; // App password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
  
        $mail->setFrom('aasticagrg00@gmail.com', 'Easy Rentals');
        $mail->addAddress($data['user_email'], $data['user_name']);
        $mail->isHTML(true);
        $mail->Subject = "Booking Cancelled: {$data['property_name']}";

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; font-size: 15px; color: #333;'>
                <p>Dear {$data['user_name']},</p>

                <p>We regret to inform you that the property owner has cancelled your booking for <strong>{$data['property_name']}</strong>.</p>

                <h3 style='color: #2c3e50;'>Booking Details</h3>
                <ul style='line-height: 1.8;'>
                    <li><strong>Property Name:</strong> {$data['property_name']}</li>
                    <li><strong>Check-in:</strong> {$data['check_in_date']}</li>
                    <li><strong>Check-out:</strong> {$data['check_out_date']}</li>
                </ul>

                <h3 style='color: #2c3e50;'>Property Owner Contact</h3>
                <ul style='line-height: 1.8;'>
                    <li><strong>Name:</strong> {$data['vendor_name']}</li>
                    <li><strong>Contact No:</strong> {$data['contact_no']}</li>
                </ul>

                <p>If you have any questions or wish to discuss this cancellation, you may directly contact the property owner using the details above.</p>

                <p>We understand this may cause inconvenience, and we encourage you to explore other available properties on our platform.</p>

                <br>
                <p>Sincerely,</p>
                <p><strong>Easy Rentals Team</strong><br>
                support@easyrentals.com</p>

                <p style='font-size: 13px; color: #888;'>This is an automated email. Please do not reply directly to this message.</p>
            </div>
        ";

        $mail->send();
    } catch (Exception $e) {
        error_log("Cancellation mail error: " . $mail->ErrorInfo);
    }
  }
  
