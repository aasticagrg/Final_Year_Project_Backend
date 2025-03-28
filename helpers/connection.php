<?php
// Allow requests from any origin
header('Access-Control-Allow-Origin: *');
// Allow specified request methods
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
// Allow specified request headers
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database credentials
$host = "localhost"; // Change if using a remote server
$username = "root";  // Default XAMPP username
$password = "";      // Default XAMPP password (empty)
$database = "easy_rental"; // Your database name


// Create a connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    // Send JSON response if connection fails
    die(json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . mysqli_connect_error()
    ]));
}



?>
