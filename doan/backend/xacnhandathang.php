<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require 'connect.php';
require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
require_once 'crypto_helper.php';

header('Content-Type: application/json');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Debug: log tất cả dữ liệu nhận được
file_put_contents(__DIR__.'/debug.log', "====\n".date('c')."\n", FILE_APPEND);
file_put_contents(__DIR__.'/debug.log', "POST: ".print_r($_POST, true)."\n", FILE_APPEND);
file_put_contents(__DIR__.'/debug.log', "INPUT: ".file_get_contents('php://input')."\n", FILE_APPEND);

// Hàm lấy JWT từ header Authorization
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

// Lấy user_id từ JWT
function getUserIdFromJWT() {
    $jwt = getBearerToken();
    if (!$jwt) throw new Exception('Không tìm thấy JWT token');
    $publicKey = file_get_contents(__DIR__ . '/public_key.pem');
    if ($publicKey === false) throw new Exception('Không thể đọc file public_key.pem');
    try {
        $decoded = JWT::decode($jwt, new Key($publicKey, 'ES256'));
        return $decoded->user_id ?? null;
    } catch (Exception $e) {
        throw new Exception('Lỗi giải mã JWT: ' . $e->getMessage());
    }
}

try {
    if (!function_exists('getAesKey')) throw new Exception('Hàm getAesKey không được định nghĩa');
    $key = getAesKey();

    $makh = getUserIdFromJWT();
    if (!$makh) {
        http_response_code(401);
        echo json_encode(['error' => 'Vui lòng đăng nhập!']);
        exit();
    }

    // Kiểm tra dữ liệu gửi lên (debug chi tiết nếu thiếu)
    $required = ['cart', 'payment_intent_id', 'receiverName', 'receiverPhone', 'receiverAddress'];
    $missing = [];
    foreach ($required as $field) {
        if (!isset($_POST[$field])) $missing[] = $field;
    }
    if ($missing) {
        file_put_contents(__DIR__.'/debug.log', "Missing fields: ".implode(', ', $missing)."\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['error' => 'Thiếu thông tin!', 'missing' => $missing]);
        exit();
    }

    // Nhận đúng kiểu dữ liệu
    $cart = json_decode($_POST['cart'], true);
    if ($cart === null || !is_array($cart) || count($cart) == 0) {
        file_put_contents(__DIR__.'/debug.log', "Cart decode fail: ".$_POST['cart']."\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['error' => 'Giỏ hàng trống hoặc không hợp lệ!']);
        exit();
    }

    $payment_intent_id = $_POST['payment_intent_id'];
    $receiverName = $_POST['receiverName'];
    $receiverPhone = $_POST['receiverPhone'];
    $receiverAddress = $_POST['receiverAddress'];

    // Kiểm tra thanh toán với Stripe
    $stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;
    if (!$stripeSecretKey) {
        http_response_code(500);
        echo json_encode(['error' => 'Server chưa cấu hình Stripe secret key!']);
        exit();
    }
    \Stripe\Stripe::setApiKey($stripeSecretKey);
    try {
        $intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
        if ($intent->status !== 'succeeded') {
            http_response_code(400);
            echo json_encode(['error' => 'Thanh toán chưa thành công!']);
            exit();
        }
        $stripe_amount = $intent->amount;
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Thanh toán không hợp lệ!', 'message' => $e->getMessage()]);
        exit();
    }

    // Tính tổng tiền giỏ hàng từ CSDL để đối chiếu
    $tongtien = 0;
    foreach ($cart as $item) {
        $mahh = intval($item['mahh']);
        $soluong = intval($item['soluong']);
        $res = mysqli_query($conn, "SELECT GiaTien FROM hanghoa WHERE MaHH = $mahh");
        if (!$res) throw new Exception('Lỗi truy vấn cơ sở dữ liệu: ' . mysqli_error($conn));
        $row = mysqli_fetch_assoc($res);
        if (!$row) throw new Exception("Sản phẩm có mã $mahh không tồn tại");
        $gia = $row['GiaTien'];
        $tongtien += $gia * $soluong;
    }

    // So sánh tổng tiền với Stripe amount (đơn vị cent)
    if (abs($tongtien * 100 - $stripe_amount) > 500) {
        http_response_code(400);
        echo json_encode(['error' => 'Số tiền thanh toán không khớp!']);
        exit();
    }

    // Mã hóa dữ liệu trước khi lưu
    $ngay = date('Y-m-d H:i:s');
    $ngay_enc = encryptField($ngay, $key);
    $tongtien_enc = encryptField((string)$tongtien, $key);
    $payment_intent_id_enc = encryptField($payment_intent_id, $key);
    $receiverName_enc = encryptField($receiverName, $key);
    $receiverPhone_enc = encryptField($receiverPhone, $key);
    $receiverAddress_enc = encryptField($receiverAddress, $key);

    // Thêm đơn hàng vào bảng donhang
    $query = "INSERT INTO donhang (MaKH, NgDH, TongTien, PaymentIntentID, TenNguoiNhan, SDTNguoiNhan, DiaChiNguoiNhan)
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    // MaKH là INT, còn lại là chuỗi
    $stmt->bind_param("issssss", $makh, $ngay_enc, $tongtien_enc, $payment_intent_id_enc, $receiverName_enc, $receiverPhone_enc, $receiverAddress_enc);

    if (!$stmt->execute()) throw new Exception('Lỗi khi tạo đơn hàng: ' . $stmt->error);

    $mahd = $conn->insert_id;

    // Thêm chi tiết đơn hàng vào bảng chitietdonhang
    foreach ($cart as $item) {
        $mahh = intval($item['mahh']);
        $soluong = intval($item['soluong']);
        $res = mysqli_query($conn, "SELECT GiaTien FROM hanghoa WHERE MaHH = $mahh");
        if (!$res) throw new Exception('Lỗi truy vấn cơ sở dữ liệu: ' . mysqli_error($conn));
        $row = mysqli_fetch_assoc($res);
        $gia = $row['GiaTien'];
        $subtotal = $gia * $soluong;

        $query = "INSERT INTO chitiethoadon (MaHD, MaHH, SoLuong, DonGia)
                  VALUES (?, ?, ?, ?)";
        $stmt2 = $conn->prepare($query);
        if (!$stmt2) throw new Exception('Lỗi chuẩn bị truy vấn: ' . $conn->error);
        $stmt2->bind_param("iiii", $mahd, $mahh, $soluong, $subtotal);
        if (!$stmt2->execute()) throw new Exception('Lỗi khi lưu chi tiết đơn hàng: ' . $stmt2->error);
    }

    // Xóa giỏ hàng của khách
    $delete_query = "DELETE FROM giohang WHERE MaKH = ?";
    $stmt3 = $conn->prepare($delete_query);
    if (!$stmt3) throw new Exception('Lỗi chuẩn bị truy vấn xóa giỏ hàng: ' . $conn->error);
    $stmt3->bind_param("i", $makh);
    if (!$stmt3->execute()) throw new Exception('Lỗi khi xóa giỏ hàng: ' . mysqli_error($conn));

    // Thành công
    echo json_encode(['message' => 'Bạn đã đặt hàng và thanh toán thành công!']);

} catch (Exception $e) {
    file_put_contents(__DIR__.'/debug.log', "Catch: ".$e->getMessage()."\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
}
?>
