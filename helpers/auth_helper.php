<?php
include_once 'connection.php';

function getUserIdFromToken($token) {
    global $conn;
    
    if (empty($token)) {
        return [
            'user_id' => null,
            'vendor_id' => null,
            'role' => null
        ];
    }
    
    try {
        // Use prepared statements to prevent SQL injection
        $sql = "SELECT user_id, vendor_id, role FROM tokens WHERE token = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare statement error: " . $conn->error);
        }
        
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if (!$row) {
            return [
                'user_id' => null,
                'vendor_id' => null,
                'role' => null
            ];
        }
        
        return [
            'user_id' => $row['user_id'] ?? null,
            'vendor_id' => $row['vendor_id'] ?? null,
            'role' => $row['role'] ?? null,
        ];
    } catch (Exception $e) {
        error_log("Token validation error: " . $e->getMessage());
        return [
            'user_id' => null,
            'vendor_id' => null,
            'role' => null
        ];
    }
}

function isAdmin($token) {
    global $conn;
    
    $userData = getUserIdFromToken($token);
    if (!$userData['user_id']) {
        return false;
    }
    
    try {
        // Use prepared statements
        $sql = "SELECT * FROM users WHERE user_id = ? AND role = 'admin'";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare statement error: " . $conn->error);
        }
        
        $stmt->bind_param("i", $userData['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row ? true : false;
    } catch (Exception $e) {
        error_log("Admin check error: " . $e->getMessage());
        return false;
    }
}

function isVendor($token) {
    $userData = getUserIdFromToken($token);
    return $userData['vendor_id'] ? true : false;
}
?>