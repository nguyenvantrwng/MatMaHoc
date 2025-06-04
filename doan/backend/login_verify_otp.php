<?php
require 'connect.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once 'crypto_helper.php';

use Dotenv\Dotenv;
use OTPHP\TOTP;
use Firebase\JWT\JWT;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

header('Content-Type: application/json');

// Lấy input
$username = trim($_POST['username'] ?? '');
$otp_input = trim($_POST['otp'] ?? '');

if (!$username || !$otp_input) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập tên tài khoản và mã OTP!']);
    exit;
}

$key = getAesKey();

// Tìm user (giải mã từng user trong bảng)
$sql = "SELECT * FROM khachhang";
$result = $conn->query($sql);
$user = null;
$user_id = null;
while ($row = $result->fetch_assoc()) {
    $decryptedUsername = decryptField($row['TenDN'], $key);
    if ($decryptedUsername === $username) {
        $user = $row;
        $user_id = $row['id'];
        break;
    }
}
if (!$user) {
    error_log("No user found for username: " . $username);
    echo json_encode(['success' => false, 'message' => 'Tài khoản không tồn tại!']);
    exit;
}

// Lấy secret OTP
$encOtpSecret = $user['otp_secret'];
if (!$encOtpSecret) {
    echo json_encode(['success' => false, 'message' => 'Tài khoản chưa thiết lập xác thực OTP!']);
    exit;
}

$otp_secret = decryptField($encOtpSecret, $key);
if (!$otp_secret) {
    // Ghi log secret giải mã ra để kiểm tra
    error_log("Failed to decrypt otp_secret! encOtpSecret: $encOtpSecret | Username: $username | Key: $key");
    echo json_encode(['success' => false, 'message' => 'Không thể giải mã OTP secret! Vui lòng đăng nhập lại để thiết lập OTP mới.']);
    exit;
}

// In secret ra log để debug (KHÔNG dùng trên môi trường production)
error_log("OTP_SECRET for $username: $otp_secret");

try {
    $totp = TOTP::create($otp_secret);
    $otp_now = $totp->now();
    error_log("DEBUG | $username | OTP_SECRET: $otp_secret | OTP_BACKEND: $otp_now | OTP_CLIENT: $otp_input");


    // Kiểm tra mã OTP, có thể kiểm tra chấp nhận lệch 1 khoảng thời gian (ví dụ 30s trước/sau)
    $isValid = $totp->verify($otp_input, null, 3); // Cho phép lệch 1 step (30s)
} catch (Throwable $e) {
    error_log("TOTP Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Không thể tạo TOTP để xác thực!']);
    exit;
}

if (!$isValid) {
    error_log("Sai mã OTP. Input: $otp_input | Secret: $otp_secret | OTP_NOW: $otp_now");
    echo json_encode([
        'success' => false,
        'message' => 'Sai mã OTP!',
        'otp_now' => $otp_now,       // mã OTP mà backend sinh ra tại thời điểm hiện tại
        'otp_secret' => $otp_secret, // secret dùng để sinh OTP, chỉ để debug!
        'time_server' => date('Y-m-d H:i:s'), // giờ server, để so sánh lệch giờ
    ]);
    exit;
}


// Đúng OTP, trả JWT
$privateKeyPath = __DIR__ . '/private_key.pem';
if (!file_exists($privateKeyPath)) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy private key!']);
    exit;
}
$privateKey = file_get_contents($privateKeyPath);
if (!$privateKey) {
    echo json_encode(['success' => false, 'message' => 'Không thể đọc private key!']);
    exit;
}

$issuedAt = time();
$expire = $issuedAt + (20 * 60);

$payload = [
    'user' => $username,
    'user_id' => $user_id,
    'iat' => $issuedAt,
    'exp' => $expire
];
$jwt = JWT::encode($payload, $privateKey, 'ES256');

echo json_encode([
    'success' => true,
    'message' => 'Đăng nhập thành công!',
    'username' => $username,
    'user_id' => $user_id,
    'token' => $jwt,
    'exp' => $expire
]);
exit;
?>
