<?php
require 'connect.php';
require_once __DIR__ . '/vendor/autoload.php'; 
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
require_once 'crypto_helper.php';

header('Content-Type: application/json');

$email  = strtolower(trim($_POST['email'] ?? ''));
$user   = trim($_POST['user'] ?? '');
$phone  = trim($_POST['phone'] ?? '');
$pass   = $_POST['pass'] ?? '';
$pass2  = $_POST['pass2'] ?? '';
$otp_input = trim($_POST['otp'] ?? '');

if (!$email || !$user || !$phone || !$pass || !$pass2 || !$otp_input) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin!']);
    exit;
}
if ($pass !== $pass2) {
    echo json_encode(['success' => false, 'message' => 'Mật khẩu không khớp!']);
    exit;
}
if (strlen($pass) < 6) {
    echo json_encode(['success' => false, 'message' => 'Mật khẩu phải ít nhất 6 ký tự!']);
    exit;
}

// Kiểm tra email đã tồn tại
$key = getAesKey();
$stmt = $conn->prepare("SELECT Email FROM khachhang");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $dbEmail = strtolower(trim(decryptField($row['Email'], $key)));
    if ($dbEmail === $email) {
        echo json_encode(['success' => false, 'message' => 'Email đã tồn tại!']);
        $stmt->close();
        exit;
    }
}
$stmt->close();

// Kiểm tra OTP hợp lệ, chỉ cho dùng OTP mới nhất (và chưa used)
$stmt = $conn->prepare("SELECT id, otp_hash FROM otp_codes WHERE email = ? AND used = 0 AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    if (!password_verify($otp_input, $row['otp_hash'])) {
        echo json_encode(['success' => false, 'message' => 'OTP không chính xác!']);
        $stmt->close();
        exit;
    }
    $otp_id = $row['id'];
} else {
    echo json_encode(['success' => false, 'message' => 'OTP đã hết hạn hoặc không hợp lệ.']);
    $stmt->close();
    exit;
}
$stmt->close();

// Mã hóa các trường
$hashedPassword = password_hash($pass, PASSWORD_ARGON2ID);
$encUser  = encryptField($user, $key);
$encEmail = encryptField($email, $key);
$encPhone = encryptField($phone, $key);
$encPass  = encryptField($hashedPassword, $key);

// Lưu tài khoản mới **KHÔNG có otp_secret**
$stmt = $conn->prepare("INSERT INTO khachhang (TenDN, Email, Phone, MK) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $encUser, $encEmail, $encPhone, $encPass);
if ($stmt->execute()) {
    // Đánh dấu used OTP
    $stmt2 = $conn->prepare("UPDATE otp_codes SET used = 1 WHERE id = ?");
    $stmt2->bind_param("i", $otp_id);
    $stmt2->execute();
    $stmt2->close();

    // Xoá các OTP used (tuỳ thích)
    $conn->query("DELETE FROM otp_codes WHERE used = 1");

    echo json_encode(['success' => true, 'message' => 'Đăng ký thành công!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi khi lưu tài khoản!']);
}
$stmt->close();
?>
