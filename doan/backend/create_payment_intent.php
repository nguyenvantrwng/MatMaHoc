<?php
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Stripe\PaymentIntent;

// Load file .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Định nghĩa các header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: POST');

// Hàm lấy token từ header
function getBearerToken() {
    $headers = apache_request_headers() ?: [];
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
    }
    return isset($headers['Authorization']) ? preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches) ? $matches[1] : null : null;
}

// Hàm lấy user_id từ JWT
function getUserIdFromJWT($publicKeyPath) {
    $jwt = getBearerToken();
    if (!$jwt || !file_exists($publicKeyPath)) {
        error_log('Invalid token or public key file not found: ' . $publicKeyPath);
        return null;
    }

    $publicKey = file_get_contents($publicKeyPath);
    try {
        $decoded = JWT::decode($jwt, new Key($publicKey, 'ES256'));
        return $decoded->user_id ?? null;
    } catch (Exception $e) {
        error_log('JWT decode error: ' . $e->getMessage());
        return null;
    }
}

// Lấy user_id
$publicKeyPath = __DIR__ . '/public_key.pem';
$userId = getUserIdFromJWT($publicKeyPath);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Invalid or missing token']);
    exit;
}

// Nhận dữ liệu JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

$amountVND = $input['amount'] ?? 0;
if (!is_numeric($amountVND) || $amountVND <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Số tiền không hợp lệ!']);
    exit;
}

// Chuyển đổi amount sang đơn vị nhỏ nhất của Stripe
$amount = (int)($amountVND * 100);

// Lấy Stripe secret key từ biến môi trường
$stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;
if (!$stripeSecretKey) {
    error_log('Stripe secret key not set in .env');
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Stripe secret key not found']);
    exit;
}

// Cấu hình Stripe
\Stripe\Stripe::setApiKey($stripeSecretKey);

try {
    $paymentIntent = PaymentIntent::create([
        'amount' => $amount,
        'currency' => 'vnd',
        'metadata' => ['user_id' => $userId]
    ]);

    echo json_encode([
        'clientSecret' => $paymentIntent->client_secret,
        'paymentIntentId' => $paymentIntent->id
    ]);
} catch (Exception $e) {
    error_log('Stripe error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Đã xảy ra lỗi khi xử lý thanh toán. Vui lòng thử lại.', 'message' => $e->getMessage()]);
}
?>