<?php
// Bật hiển thị lỗi trong môi trường phát triển
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'connect.php';
require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once 'crypto_helper.php';
header('Content-Type: application/json');

// Hàm trả về JSON và thoát
function sendResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// --- Lấy thông tin POST
$email = strtolower(trim($_POST['email'] ?? ''));
$user = trim($_POST['user'] ?? '');
$phone = trim($_POST['phone'] ?? '');

// --- Kiểm tra input
if (!$email || !$user || !$phone) {
    sendResponse(false, 'Thiếu thông tin!');
}

// Kiểm tra định dạng email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Email không hợp lệ!');
}

// --- Kiểm tra email đã tồn tại
try {
    $key = getAesKey();
    $stmt = $conn->prepare("SELECT Email FROM khachhang WHERE Email = ?");
    $encryptedEmail = encryptField($email, $key); // Giả sử bạn có hàm encryptField
    $stmt->bind_param("s", $encryptedEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        sendResponse(false, 'Email đã đăng ký!');
    }
    $stmt->close();
} catch (Exception $e) {
    sendResponse(false, 'Lỗi kiểm tra email: ' . $e->getMessage());
}

// --- Tạo OTP và lưu vào DB
try {
    $otp = random_int(100000, 999999);
    $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    $stmt = $conn->prepare("INSERT INTO otp_codes (email, otp_hash, expires_at, used) VALUES (?, ?, ?, 0)");
    $stmt->bind_param("sss", $email, $otp_hash, $expires_at);
    if (!$stmt->execute()) {
        throw new Exception('Lỗi lưu OTP vào cơ sở dữ liệu');
    }
    $stmt->close();
} catch (Exception $e) {
    sendResponse(false, 'Lỗi tạo OTP: ' . $e->getMessage());
}

// --- Gửi OTP qua Google Apps Script
$script_url = 'https://script.google.com/macros/s/AKfycbyp2SMuTD1snn9TZkceKvKnB0pnuU27u1BphO7jUcGPbbdmQ73n22ABq63a6OM-Inav/exec'; // Cập nhật URL thực tế
$subject = 'Mã OTP xác thực đăng ký';
$body = "Mã OTP của bạn là: <b>$otp</b>. Hiệu lực trong 5 phút.";

$data = [
    'to' => $email,
    'subject' => $subject,
    'body' => $body
];

$ch = curl_init($script_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Thêm timeout để tránh treo

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    sendResponse(false, 'Lỗi cURL: ' . $curl_error);
}

if ($http_code === 200 || $http_code === 302) {
    sendResponse(true, 'OTP đã gửi về email.');
} else {
    sendResponse(false, 'Lỗi gửi mail: HTTP code ' . $http_code . ', Response: ' . htmlspecialchars($response));
}
?>