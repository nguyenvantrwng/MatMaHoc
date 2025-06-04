<?php
require 'connect.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once 'crypto_helper.php';

use Dotenv\Dotenv;
use OTPHP\TOTP;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

header('Content-Type: application/json');

// Lấy input
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (!$username || !$password) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ tài khoản và mật khẩu!']);
    exit;
}

$key = getAesKey();

// Tìm user (giải mã từng user trong bảng)
$sql = "SELECT * FROM khachhang";
$result = $conn->query($sql);
$user = null;
while ($row = $result->fetch_assoc()) {
    $tmp = decryptField($row['TenDN'], $key);
    if ($tmp === $username) {
        $user = $row;
        break;
    }
}
if (!$user || !isset($user['MK'])) {
    echo json_encode(['success' => false, 'message' => 'Sai tài khoản hoặc mật khẩu!']);
    exit;
}

// Kiểm tra password
$hash_from_db = decryptField($user['MK'], $key);
if (!password_verify($password, $hash_from_db)) {
    echo json_encode(['success' => false, 'message' => 'Sai tài khoản hoặc mật khẩu!']);
    exit;
}

// Lấy hoặc tạo mới secret OTP
$encOtpSecret = $user['otp_secret'] ?? null;
$otp_secret = $encOtpSecret ? decryptField($encOtpSecret, $key) : null;
$show_qr = false;

if (!$otp_secret) {
    // Sinh secret OTP mới nếu user chưa có
    $totp = TOTP::create();
    $otp_secret = $totp->getSecret();
    $encOtpSecret = encryptField($otp_secret, $key);
    $stmt = $conn->prepare("UPDATE khachhang SET otp_secret = ? WHERE id = ?");
    $stmt->bind_param("si", $encOtpSecret, $user['id']);
    $stmt->execute();
    $stmt->close();
    $show_qr = true;
}

// Luôn tạo TOTP object từ secret
$totp = TOTP::create($otp_secret);
$totp->setLabel($username);
$totp->setIssuer('CapybaraShop');
$otp_url = $totp->getProvisioningUri();

$qr_base64 = null;
if ($show_qr) {
    $qrCode = new QrCode($otp_url); // Khởi tạo QR code với dữ liệu
    $writer = new PngWriter();
    $result = $writer->write($qrCode); // Tạo hình ảnh QR code
    $qr_base64 = base64_encode($result->getString());
}

// Trả về cho frontend
echo json_encode([
    'success' => true,
    'require_otp' => true,
    'message' => 'Vui lòng quét QR bằng Google Authenticator nếu lần đầu hoặc nhập mã OTP 6 số.',
    'qr_base64' => $qr_base64 // null nếu không cần show QR lại
]);
exit;
?>