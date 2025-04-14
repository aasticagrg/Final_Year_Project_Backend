<?php
include 'helpers/connection.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$payment_id = $data['payment_id'] ?? '';
$payment_status = $data['payment_status'] ?? '';

if (!$payment_id || !in_array($payment_status, ['pending', 'completed'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$sql = "UPDATE payments SET payment_status = ?, updated_at = CURRENT_TIMESTAMP WHERE payment_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $payment_status, $payment_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}
?>
