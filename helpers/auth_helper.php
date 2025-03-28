<?php

include '../helpers/connection.php';

function getUserIdFromToken($token) {
    global $conn;
    
    $sql = "SELECT user_id, vendor_id, role FROM tokens WHERE token='$token'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);

    if (!$row) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid token',
        ]);
        exit();
    }

    return [
        'user_id' => $row['user_id'] ?? null,
        'vendor_id' => $row['vendor_id'] ?? null,
        'role' => $row['role'] ?? null
    ];
}

function isAdmin($token) {
    global $conn;

    $userData = getUserIdFromToken($token);
    if (!$userData['user_id']) {
        return false;
    }

    $sql = "SELECT * FROM users WHERE user_id='{$userData['user_id']}' AND role='admin'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);

    return $row ? true : false;
}

function isVendor($token) {
    global $conn;

    $userData = getUserIdFromToken($token);
    return $userData['vendor_id'] ? true : false;
}

?>
