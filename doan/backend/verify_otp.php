<?php
require 'connect.php';

header('Content-Type: application/json');

$email = strtolower(trim($_POST['email'] ?? ''));
$otp_input = trim($_POST['otp'] ?? '');

if (!$email || !$otp_input) {
    echo json_encode(['success' => false, 'message' => 'Thiếu email hoặc OTP']);
    exit;
}

// Chỉ kiểm tra hợp lệ, không update used ở đây
$stmt = $conn->prepare("SELECT otp_hash FROM otp_codes WHERE email = ? AND used = 0 AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if (!password_verify($otp_input, $row['otp_hash'])) {
        echo json_encode(['success' => false, 'message' => 'OTP không chính xác.']);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'OTP hợp lệ!']);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy OTP phù hợp với email hoặc OTP đã hết hạn/đã dùng.']);
    exit;
}
?>
