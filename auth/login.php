<?php
include '../helpers/connection.php';

// Admin email and password (plain text)
$admin_email = 'admin@gmail.com';
$admin_password = 'admin';  // In production, store a securely hashed password

// Hash the admin password once (only if you haven't already hashed it in your database)
$hashed_admin_password = password_hash($admin_password, PASSWORD_DEFAULT);

// Debug: Log the hashed admin password (only for testing purposes)
error_log("Hashed Admin Password: " . $hashed_admin_password);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure the required fields are present in the POST request
    if (!isset($_POST['email']) || !isset($_POST['password']) || !isset($_POST['role'])) {
        echo json_encode(['success' => false, 'message' => 'Email, password, and role are required']);
        exit();
    }

    // Get the posted data
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Debug: Log the received email, password, and role
    error_log("Received Email: " . $email);
    error_log("Received Password: " . $password);
    error_log("Received Role: " . $role);

    // Admin login logic
    if ($email === $admin_email && $role === 'admin') {
        $token = bin2hex(random_bytes(32));

        // Query to check if admin exists in the database
        $adminQuery = "SELECT user_id, password FROM users WHERE email = ? AND role = 'admin'";
        $stmt = $conn->prepare($adminQuery);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $adminResult = $stmt->get_result();
        $adminData = $adminResult->fetch_assoc();
        $stmt->close();

        // Debug: Log fetched admin data
        error_log("Fetched Admin Data: " . print_r($adminData, true));

        // If no admin data was found, return an error
        if (!$adminData) {
            echo json_encode(['success' => false, 'message' => 'Admin account not found']);
            exit();
        }

        // Debug: Check if password matches manually hashed password
        if (!password_verify($password, $hashed_admin_password)) {
            error_log("Password verification failed for admin");
            echo json_encode(['success' => false, 'message' => 'Incorrect password']);
            exit();
        }

        // Admin authenticated, now create a token
        $admin_id = $adminData['user_id'];

        // Insert or update the token in the database
        $insertTokenQuery = "INSERT INTO tokens (token, user_id, role) 
                             VALUES (?, ?, 'admin')
                             ON DUPLICATE KEY UPDATE token = VALUES(token), role = VALUES(role)";
        $stmt = $conn->prepare($insertTokenQuery);
        $stmt->bind_param("si", $token, $admin_id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to login: ' . $stmt->error]);
            exit();
        }
        $stmt->close();

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Admin logged in successfully',
            'token' => $token,
            'role' => 'admin'
        ]);
        exit();
    }

    // If the role is not admin, continue with user/vendor login
    if ($role === 'user') {
        $sql = "SELECT user_id, password, role FROM users WHERE email = ?";
    } elseif ($role === 'vendor') {
        $sql = "SELECT vendor_id AS user_id, password, 'vendor' AS role FROM vendors WHERE vendor_email = ?";
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid role specified!']);
        exit();
    }

    // Query to check if user/vendor exists
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // Debug: Log fetched user/vendor data
    error_log("Fetched User Data: " . print_r($user, true));

    // If no user/vendor data was found, return an error
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found!']);
        exit();
    }

    // Verify password for user/vendor
    if (!password_verify($password, $user['password'])) {
        error_log("Password verification failed for user/vendor");
        echo json_encode(['success' => false, 'message' => 'Incorrect password!']);
        exit();
    }

    // User/vendor authenticated, now create a token
    $token = bin2hex(random_bytes(32));
    $user_id = $user['user_id'];
    $user_role = $user['role'];

    // Insert or update the token in the database
    $insertTokenQuery = "INSERT INTO tokens (token, user_id, role) 
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE token = VALUES(token), role = VALUES(role)";
    $stmt = $conn->prepare($insertTokenQuery);
    $stmt->bind_param("sis", $token, $user_id, $user_role);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to login: ' . $stmt->error]);
        exit();
    }
    $stmt->close();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Logged in successfully',
        'token' => $token,
        'role' => $user_role
    ]);
    exit();
}
?>
