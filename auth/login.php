<?php
include '../helpers/connection.php';

// Define admin credentials in the backend
$admin_email = 'admin@gmail.com';
$admin_password = 'admin'; // Store securely, this is just an example

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['email']) && isset($_POST['password']) && isset($_POST['role'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];
        $role = $_POST['role']; // Expecting 'user', 'vendor', or 'admin'

        // Admin login check
        if ($email === $admin_email && $password === $admin_password && $role === 'admin') {
            $token = bin2hex(random_bytes(32));

            // Store admin token in the database
            $sql = "INSERT INTO tokens (token, role) VALUES ('$token', 'admin')";
            if (!mysqli_query($conn, $sql)) {
                echo json_encode(['success' => false, 'message' => 'Failed to login: ' . mysqli_error($conn)]);
                exit();
            }

            echo json_encode([
                'success' => true,
                'message' => 'Admin logged in successfully',
                'token' => $token,
                'role' => 'admin'
            ]);
            exit();
        }

        if ($role === 'user') {
            $sql = "SELECT * FROM users WHERE email = '$email'";
        } elseif ($role === 'vendor') {
            $sql = "SELECT * FROM vendors WHERE vendor_email = '$email'";
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid role specified!']);
            exit();
        }

        $result = mysqli_query($conn, $sql);
        $user = mysqli_fetch_assoc($result);

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found!']);
            exit();
        }

        $hashedPassword = $user['password'];
        if (!password_verify($password, $hashedPassword)) {
            echo json_encode(['success' => false, 'message' => 'Incorrect password!']);
            exit();
        }

        $token = bin2hex(random_bytes(32));
        $user_id = ($role === 'vendor') ? $user['vendor_id'] : $user['user_id'];

        $sql = ($role === 'vendor') ?
            "INSERT INTO tokens (token, vendor_id, role) VALUES ('$token', '$user_id', '$role')" :
            "INSERT INTO tokens (token, user_id, role) VALUES ('$token', '$user_id', '$role')";

        if (!mysqli_query($conn, $sql)) {
            echo json_encode(['success' => false, 'message' => 'Failed to login: ' . mysqli_error($conn)]);
            exit();
        }

        unset($user['password']);

        if ($role === 'vendor') {
            echo json_encode([
                'success' => true,
                'message' => 'Logged in successfully',
                'token' => $token,
                'role' => $role,
                'vendor' => [
                    'vendor_id' => $user['vendor_id'],
                    'vendor_name' => $user['vendor_name'],
                    'vendor_email' => $user['vendor_email'],
                    'vendor_address' => $user['vendor_address'],
                    'contact_no' => $user['contact_no'],
                    'vendor_verification' => $user['vendor_verification'],
                    'status' => $user['status'] ?? 'active'
                ]
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Logged in successfully',
                'token' => $token,
                'role' => $role
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Email, password, and role are required']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
