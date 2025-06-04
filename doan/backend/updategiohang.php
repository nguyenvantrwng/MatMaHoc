<?php
include "connect.php";
require_once "vendor/autoload.php";
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// --- Hàm lấy JWT từ header Authorization ---
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

// --- Hàm xác thực và lấy user_id từ JWT ---
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

// --- Lấy user_id từ JWT ---
$makh = getUserIdFromJWT();
if (!$makh) {
    echo "Bạn cần đăng nhập!";
    exit();
}

if (isset($_POST['action']) && isset($_POST['mahh'])) {
    $action = $_POST['action'];
    $mahh = intval($_POST['mahh']);

    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM giohang WHERE MaKH = $makh AND MaHH = $mahh"));
    if (!$row) exit("Sản phẩm không tồn tại trong giỏ!");

    $soluong = $row['SoLuong'];

    if ($action === "increase") {
        $soluong++;
        mysqli_query($conn, "UPDATE giohang SET SoLuong = $soluong WHERE MaKH = $makh AND MaHH = $mahh");
    } elseif ($action === "decrease") {
        if ($soluong > 1) {
            $soluong--;
            mysqli_query($conn, "UPDATE giohang SET SoLuong = $soluong WHERE MaKH = $makh AND MaHH = $mahh");
        } else {
            mysqli_query($conn, "DELETE FROM giohang WHERE MaKH = $makh AND MaHH = $mahh");
        }
    } elseif ($action === "delete") {
        mysqli_query($conn, "DELETE FROM giohang WHERE MaKH = $makh AND MaHH = $mahh");
    }
}

$sql_cart = "SELECT h.TenHH, h.GiaTien, g.SoLuong, g.MaHH 
             FROM giohang g JOIN hanghoa h ON g.MaHH = h.MaHH 
             WHERE g.MaKH = $makh";
$cart = mysqli_query($conn, $sql_cart);

$tong = 0;

while ($item = mysqli_fetch_assoc($cart)) {
    $thanhtien = $item['GiaTien'] * $item['SoLuong'];
    $tong += $thanhtien;
?>
  <div class="d-flex justify-content-between align-items-center mb-2 cart-item" data-mahh="<?= $item['MaHH'] ?>">
    <div><?= str_replace("_", " ", $item['TenHH']) ?> - <?= number_format($item['GiaTien'], 0, ',', '.') ?> VND x <span class="item-quantity"><?= $item['SoLuong'] ?></span></div>
    <div>
      <form class="cart-action-form d-inline">
        <input type="hidden" name="action" value="increase">
        <input type="hidden" name="mahh" value="<?= $item['MaHH'] ?>">
        <button class="btn btn-sm btn-primary">+1</button>
      </form>
      <form class="cart-action-form d-inline">
        <input type="hidden" name="action" value="decrease">
        <input type="hidden" name="mahh" value="<?= $item['MaHH'] ?>">
        <button class="btn btn-sm btn-secondary">-1</button>
      </form>
      <form class="cart-action-form d-inline">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="mahh" value="<?= $item['MaHH'] ?>">
        <button class="btn btn-sm btn-danger">Xóa</button>
      </form>
    </div>
  </div>
<?php } ?>
<hr>
<div class="text-end">Tổng: <strong><?= number_format($tong, 0, ',', '.') ?> VND</strong></div>
<div class="text-end mt-2">
  <button id="confirmOrder" class="btn btn-success">Xác nhận đặt hàng & thanh toán</button>
</div>

