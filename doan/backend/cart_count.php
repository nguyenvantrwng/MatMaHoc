<?php
include "connect.php";
require_once "vendor/autoload.php";
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// === Hàm lấy JWT từ header Authorization ===
function getBearerToken() {
    $headers = [];
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (!isset($headers['Authorization'])) return null;
    if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        return $matches[1];
    }
    return null;
}

// === Lấy user_id từ JWT ===
function getUserIdFromJWT() {
    $jwt = getBearerToken();
    if (!$jwt) return null;
    $publicKey = file_get_contents(__DIR__ . '/public_key.pem');
    try {
        $decoded = JWT::decode($jwt, new Key($publicKey, 'ES256'));
        return $decoded->user_id ?? null;
    } catch (Exception $e) {
        return null;
    }
}

$makh = getUserIdFromJWT();
if (!$makh) {
    echo 0;
    exit;
}

$sql = "SELECT SUM(SoLuong) AS tong FROM giohang WHERE MaKH = $makh";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);

echo $row['tong'] ?? 0;
?>
