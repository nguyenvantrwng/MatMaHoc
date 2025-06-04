<?php
include "connect.php";
require_once "vendor/autoload.php";
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Hàm lấy token từ header Authorization
function getBearerToken() {
    $headers = apache_request_headers();
    if (!isset($headers['Authorization'])) return null;
    if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        return $matches[1];
    }
    return null;
}

// Lấy JWT token từ header
$jwt = getBearerToken();
if (!$jwt) {
    echo "Bạn chưa đăng nhập!";
    exit();
}

$publicKey = file_get_contents(__DIR__ . '/public_key.pem');

try {
    $decoded = JWT::decode($jwt, new Key($publicKey, 'ES256'));
    $makh = isset($decoded->user_id) ? $decoded->user_id : null;
    if (!$makh) {
        echo "Không xác định được khách hàng!";
        exit();
    }
} catch (Exception $e) {
    echo "Token không hợp lệ: " . $e->getMessage();
    exit();
}

$mahh = intval($_POST['mahh']);

$check = mysqli_query($conn, "SELECT * FROM giohang WHERE MaKH = $makh AND MaHH = $mahh");
if (mysqli_num_rows($check) > 0) {
    mysqli_query($conn, "UPDATE giohang SET SoLuong = SoLuong + 1 WHERE MaKH = $makh AND MaHH = $mahh");
} else {
    mysqli_query($conn, "INSERT INTO giohang (MaKH, MaHH, SoLuong) VALUES ($makh, $mahh, 1)");
}

echo "Đã thêm vào giỏ!";
?>
