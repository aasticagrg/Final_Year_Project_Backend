<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'helpers/connection.php';
include 'helpers/auth_helper.php';

// Get token from Authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = '';

if (strpos($authHeader, 'Bearer ') === 0) {
    $token = substr($authHeader, 7);
}

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'Token is required']);
    exit();
}

$userData = getUserIdFromToken($token);
if (!$userData['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit();
}

$userId = $userData['user_id'];

try {
    $name = $_POST['name'] ?? '';
    $phone_no = $_POST['phone_no'] ?? '';
    $user_address = $_POST['user_address'] ?? '';
    
    // Validate required fields
    if (empty($name) || empty($phone_no) || empty($user_address)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        exit();
    }

    // Handle file upload if provided
    $user_verification = null;
    if (isset($_FILES['user_verification']) && $_FILES['user_verification']['error'] === 0) {
        // File details
        $file = $_FILES['user_verification'];
        $fileTmpName = $file['tmp_name'];
        $fileName = basename($file['name']);
        $fileSize = $file['size'];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Allowed file types
        $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];
        
        // Check file size (limit to 5MB)
        if ($fileSize > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File is too large. Maximum size is 5MB.']);
            exit();
        }
        
        // Check file type
        if (!in_array($fileType, $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed types: jpg, jpeg, png, pdf.']);
            exit();
        }
        
        // Generate a unique file name to avoid conflicts
        $newFileName = uniqid() . '.' . $fileType;
        $uploadDir = './images/';  // Same directory as property images
        
        // Ensure upload directory exists
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filePath = $uploadDir . $newFileName;

        // Move the uploaded file to the server's upload directory
        if (move_uploaded_file($fileTmpName, $filePath)) {
            $user_verification = 'images/' . $newFileName; // Relative path to the file
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload the file.']);
            exit();
        }
    }

    // Prepare the query to update the user profile
    $query = "UPDATE users SET name = ?, phone_no = ?, user_address = ? ";
    $params = [$name, $phone_no, $user_address];
    $types = "sss";

    // Add verification document if provided
    if ($user_verification) {
        $query .= ", user_verification = ?";
        $params[] = $user_verification;
        $types .= "s";
    }

    $query .= " WHERE user_id = ?";
    $params[] = $userId;
    $types .= "i";

    // Prepare and execute the query
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        throw new Exception("Failed to update profile: " . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>
