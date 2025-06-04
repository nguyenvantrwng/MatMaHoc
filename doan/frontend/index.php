<?php
include("../backend/connect.php");
require __DIR__ . '/../backend/vendor/autoload.php';

// ======= Định nghĩa hàm giải mã dùng AES-256-GCM =======
function getAesKey() {
    $key = getenv('AES_KEY');
    if (!$key || strlen($key) !== 32) {
        die('Khóa AES không đúng hoặc chưa thiết lập!');
    }
    return $key;
}
function decryptField($encStr, $key) {
    list($b64_iv, $b64_tag, $b64_ciphertext) = explode(':', $encStr);
    $iv = base64_decode($b64_iv);
    $tag = base64_decode($b64_tag);
    $ciphertext = base64_decode($b64_ciphertext);
    return openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
}

$key = getAesKey();
$makh = 0;

// Nếu bạn muốn lấy id khách từ JWT (option nâng cao):
if (isset($_COOKIE['jwt_token'])) {
    // Giả sử bạn đã set JWT vào cookie khi đăng nhập (hoặc lấy từ JS localStorage và gán vào request header/backend để lấy user info)
    // TODO: Bổ sung xác thực JWT ở backend cho API nếu cần bảo mật.
}

// Lấy danh sách sản phẩm
$products = mysqli_query($conn, "SELECT * FROM hanghoa");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shop Capybara</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="index.css">
  <script src="https://js.stripe.com/v3/"></script>
  <script>
    // Kiểm tra JWT token trên localStorage, chưa đăng nhập hoặc hết hạn thì chuyển về Login.html
    const token = localStorage.getItem("jwt_token");
    const exp = localStorage.getItem("exp");
    if (!token || (exp && Date.now()/1000 > exp)) {
        localStorage.clear();
        window.location.href = "Login.html";
    }
  </script>
</head>
<body>
<div class="navbar navbar-expand-lg navbar-dark bg-black">
  <a href="#" class="navbar-brand">Shop Capybara</a>
  <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="nav">
    <div class="navbar-nav">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" href="#">Trang Chủ</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown">Tiếng Việt</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#">English</a></li>
            <li><a class="dropdown-item" href="#">Tiếng Việt</a></li>
          </ul>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#">Liên hệ</a>
        </li>
      </ul>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2">
      <button class="btn btn-warning position-relative" data-bs-toggle="modal" data-bs-target="#cartModal">
        🛒 Giỏ hàng <span id="cart-count" class="badge bg-danger ms-1">0</span>
      </button>
      <span id="auth-bar"></span> <!-- Chỉ 1 lần duy nhất -->
    </div>
  </div>
</div>

<div class="button-container">
  <button class="btn btn-outline-primary responsive-btn">✅ <span>Hàng chọn giá hời</span></button>
  <button class="btn btn-outline-warning responsive-btn">💰 <span>Mã giảm giá</span></button>
  <button class="btn btn-outline-success responsive-btn">🚚 <span>Miễn phí ship</span></button>
  <button class="btn btn-outline-info responsive-btn">⚡ <span>Giờ Săn Sale</span></button>
  <button class="btn btn-outline-secondary responsive-btn">🌍 <span>Hàng Quốc Tế</span></button>
  <button class="btn btn-outline-dark responsive-btn">📱 <span>Nạp Thẻ & Dịch Vụ</span></button>
</div>

<div class="container mt-4">
  <div class="row row-cols-1 row-cols-md-4 g-4" id="product-list">
    <?php while ($row = mysqli_fetch_assoc($products)): 
      $mahh = $row['MaHH'] ?? null;
      if (!$mahh) continue;
      $tong = $row['SoLuong'] ?? 0;

      // Lấy tổng số đã đặt mua
 $donhang = mysqli_query($conn, "SELECT SUM(SoLuong) AS dondadat FROM chitiethoadon WHERE MaHH = $mahh");
$tmp_donhang = mysqli_fetch_assoc($donhang);
$daDat = $tmp_donhang && isset($tmp_donhang['dondadat']) ? $tmp_donhang['dondadat'] : 0;
      $trongGio = 0; 

      $conLai = max(0, $tong - $daDat - $trongGio);
      $isOut = $conLai <= 0;
    ?>
      <div class="col product-item" data-mahh="<?= $mahh ?>" data-conlai="<?= $conLai ?>">
     <div class="card h-100">
          <img src="img/<?= htmlspecialchars($row['TenHH']) ?>.jpg" class="card-img-top" alt="<?= htmlspecialchars($row['TenHH']) ?>">
          <div class="card-body">
            <h5 class="card-title"><?= str_replace("_", " ", htmlspecialchars($row['TenHH'])) ?></h5>
            <p class="card-text"><?= number_format($row['GiaTien'], 0, ',', '.') ?> VND</p>
            <p class="<?= $isOut ? 'text-danger' : 'text-success' ?> item-quantity-display">
              <?= $isOut ? 'Hết hàng' : 'Còn lại: ' . $conLai . ' sản phẩm' ?>
            </p>
            <form class="add-to-cart-form" data-mahh="<?= $mahh ?>">
              <button type="submit" class="btn btn-<?= $isOut ? 'secondary' : 'primary' ?>" <?= $isOut ? 'disabled' : '' ?>>Mua ngay</button>
            </form>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
</div>

<footer class="bg-dark text-white">
  <div class="container-fluid"> 
    <div class="footer-container">
      <div class="container1">
        <h2>Shop Capybara</h2>
        <p>
          Được thành lập ngày 06/05/2025, Shop Capybara là nơi để tất cả chúng ta
          thỏa mãn niềm đam mê mua sắm của bản thân – điểm đến lý tưởng để bạn tìm
          thấy những món quà độc đáo, đồ dùng sáng tạo và không gian thư giãn, đồng hành
          cùng bạn trong hành trình nuôi dưỡng cảm xúc tích cực mỗi ngày.
        </p>
        <small>© 2025 Shop Capybara - All rights reserved.</small>
      </div>
      <div class="container2">
        <h2>Truy cập nhanh</h2>
        <a href="index.php" class="text-white me-3">Trang chủ</a><br />
        <a href="#" class="text-white me-3">Giỏ Hàng</a><br />
        <a href="#" class="text-white me-3">Giới thiệu</a><br />
        <a href="#" class="text-white me-3">Ưu đãi</a>
      </div>
    </div>
  </div>
</footer>

<div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Giỏ hàng</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="cart-body">
          <div class="text-muted">Đang tải giỏ hàng...</div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Modal VISA -->
<!-- Modal 1: Thông tin người nhận -->
<div class="modal fade" id="receiverModal" tabindex="-1" aria-labelledby="receiverModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="receiverForm">
        <div class="modal-header">
          <h5 class="modal-title" id="receiverModalLabel">Thông tin người nhận hàng</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Họ tên người nhận</label>
            <input type="text" class="form-control" id="receiverName" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Số điện thoại</label>
            <input type="text" class="form-control" id="receiverPhone" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Địa chỉ giao hàng</label>
            <textarea class="form-control" id="receiverAddress" rows="2" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success w-100 mt-2">Tiếp tục</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal 2: Stripe Elements -->
<div class="modal fade" id="stripeCardModal" tabindex="-1" aria-labelledby="stripeCardModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="stripeCardForm">
        <div class="modal-header">
          <h5 class="modal-title" id="stripeCardModalLabel">Thanh toán bằng thẻ Visa/Master</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
        </div>
        <div class="modal-body">
          <div id="card-element"></div>
          <div id="card-errors" class="text-danger mt-2"></div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success w-100">Thanh toán</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Thông báo -->
<div id="toast-container" style="position: fixed; top: 70px; right: 20px; z-index: 9999;"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ------- Khi mở giỏ hàng, load lại giỏ hàng từ server -------
cartModal.addEventListener('show.bs.modal', async () => {
  const jwt = localStorage.getItem("jwt_token");
  const res = await fetch('../backend/updategiohang.php', {
    headers: {
      'Authorization': 'Bearer ' + jwt
    }
  });
  const html = await res.text();
  document.getElementById('cart-body').innerHTML = html;
  attachCartFormListeners();
  updateCartCount();
});


// ------- Lắng nghe nút tăng/giảm/xác nhận trong giỏ hàng -------
function attachCartFormListeners() {
  document.querySelectorAll('.cart-action-form').forEach(form => {
    form.onsubmit = async function (e) {
      e.preventDefault();
      const formData = new FormData(form);
      const action = formData.get('action');
      const mahh = formData.get('mahh');
      // Chặn tăng quá tồn kho
      if (action === 'increase') {
        const itemEl = document.querySelector(`.product-item[data-mahh="${mahh}"]`);
        const conlai = parseInt(itemEl?.dataset.conlai ?? 0);
        const currentQty = parseInt(itemEl?.querySelector('.item-quantity')?.textContent ?? 0);
        if (currentQty >= conlai) {
          showToast('Không thể tăng thêm. Đã đạt số lượng tồn kho!', 'danger');
          return;
        }
      }

      // Lấy JWT token từ localStorage
      const jwt = localStorage.getItem("jwt_token");
      const res = await fetch('../backend/updategiohang.php', {
        method: 'POST',
        body: formData,
        headers: {
          'Authorization': 'Bearer ' + jwt
        }
      });

      const html = await res.text();
      document.getElementById('cart-body').innerHTML = html;
      attachCartFormListeners();
      updateCartCount();
      updateProductStock();
    };
});


  // Khi nhấn xác nhận đặt hàng (confirmOrder): Chuyển qua nhập info người nhận
  const confirmBtn = document.getElementById("confirmOrder");
  if (confirmBtn) {
    confirmBtn.onclick = () => {
      const cartItems = [...document.querySelectorAll("#cart-body .cart-item")];
      if (cartItems.length === 0) {
        showToast("Giỏ hàng đang trống. Không thể đặt hàng!", "danger");
        return;
      }
      window.currentCart = cartItems.map(row => ({
        mahh: row.dataset.mahh,
        soluong: row.querySelector(".item-quantity").textContent.trim()
      }));
      // Hiện modal nhập info người nhận
      setTimeout(() => {
        const receiverModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('receiverModal'));
        receiverModal.show();
      }, 200);
    };
  }
}

// ------- Stripe Elements setup -------
const stripe = Stripe('pk_test_51RTJG9Rw4jc6QxzE3xEwOd30g00JCN845hAVMOdatP8bEMhcwOTLtev3wfkQC20ayoocPE7Jdwbu0ltP4BzxIgTF00qi1EZ2Af'); // Đổi thành publishable key Stripe của bạn!
const elements = stripe.elements();
const card = elements.create('card');
card.mount('#card-element');

// Biến toàn cục
let clientSecret = null;
let paymentIntentId = null;
let receiverInfo = {};

// Tạo paymentIntent từ số tiền, gọi backend PHP
async function createPaymentIntent(totalAmount) {
  const jwt = localStorage.getItem("jwt_token");

  const res = await fetch("../backend/create_payment_intent.php", {
    method: "POST",
    headers: {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + jwt
    },
    body: JSON.stringify({ amount: totalAmount })
  });

  if (!res.ok) {
    return null;
  }

  return await res.json();  // Trả về dữ liệu JSON từ server
}

// Nhận thông tin người nhận và gọi backend để sinh clientSecret (Stripe PaymentIntent)
document.getElementById('receiverForm').onsubmit = async function(e) {
  e.preventDefault();
  receiverInfo = {
    receiverName: document.getElementById('receiverName').value.trim(),
    receiverPhone: document.getElementById('receiverPhone').value.trim(),
    receiverAddress: document.getElementById('receiverAddress').value.trim()
  };

  // Lấy cart hiện tại (danh sách sản phẩm và số lượng)
  const cart = window.currentCart || [];
  let totalAmount = 0;
  for (const item of cart) {
    // Lấy giá tiền từ giao diện
    const priceText = document.querySelector(`.product-item[data-mahh="${item.mahh}"] .card-text`).innerText;
    const price = parseInt(priceText.replace(/[^\d]/g, '')); // Lấy số từ "10.000 VND" => 10000
    totalAmount += parseInt(item.soluong) * price;
  }

  // Gọi API tạo PaymentIntent
  const data = await createPaymentIntent(totalAmount);
  if (!data) {
    showToast("Không thể tạo thanh toán Stripe!", "danger");
    return;
  }
  clientSecret = data.clientSecret;
  paymentIntentId = data.paymentIntentId;

  // Đóng modal info, mở modal Stripe nhập thẻ
  bootstrap.Modal.getOrCreateInstance(document.getElementById('receiverModal')).hide();
  bootstrap.Modal.getOrCreateInstance(document.getElementById('cartModal')).hide();
  setTimeout(() => {
    document.getElementById('card-errors').innerText = "";
    bootstrap.Modal.getOrCreateInstance(document.getElementById('stripeCardModal')).show();
  }, 300);
};
// ------- Cập nhật số lượng sản phẩm còn lại, số lượng giỏ hàng, show toast -------
async function updateCartCount() {
  const jwt = localStorage.getItem("jwt_token");
  const res = await fetch("../backend/cart_count.php", {
    headers: {
      'Authorization': 'Bearer ' + jwt
    }
  });
  const count = await res.text();
  document.getElementById("cart-count").textContent = count;
}

async function updateProductStock() {
  const jwt = localStorage.getItem("jwt_token");
  const res = await fetch("../backend/get_soluong_conlai.php", {
    headers: {
      'Authorization': 'Bearer ' + jwt
    }
  });
  const data = await res.json();
  for (const mahh in data) {
    const item = document.querySelector(`.product-item[data-mahh="${mahh}"]`);
    if (!item) continue;
    const conlai = data[mahh];
    const p = item.querySelector(".item-quantity-display");
    const btn = item.querySelector("button");
    item.dataset.conlai = conlai;
    if (conlai <= 0) {
      p.textContent = "Hết hàng";
      p.className = "text-danger item-quantity-display";
      btn.disabled = true;
      btn.classList.remove("btn-primary");
      btn.classList.add("btn-secondary");
    } else {
      p.textContent = `Còn lại: ${conlai} sản phẩm`;
      p.className = "text-success item-quantity-display";
      btn.disabled = false;
      btn.classList.remove("btn-secondary");
      btn.classList.add("btn-primary");
    }
  }
}

// ------- Lắng nghe nút Mua ngay ở mỗi sản phẩm -------
document.querySelectorAll('.add-to-cart-form').forEach(form => {
  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    const mahh = this.dataset.mahh;
    const formData = new FormData();
    formData.append('mahh', mahh);

    // Lấy token từ localStorage
    const jwt = localStorage.getItem("jwt_token");

    // Gửi lên server kèm token ở header
    const res = await fetch('../backend/add_to_cart.php', {
      method: 'POST',
      body: formData,
      headers: {
        'Authorization': 'Bearer ' + jwt
      }
    });
    const result = await res.text();
    showToast(result, 'success');
    updateCartCount();
    updateProductStock();
  });
});

// ------- Show toast helper -------
function showToast(message, type = 'success') {
  const toast = document.createElement('div');
  toast.className = `alert alert-${type}`;
  toast.textContent = message;
  toast.style.minWidth = '200px';
  toast.style.boxShadow = '0 0 10px rgba(0,0,0,0.2)';
  toast.style.transition = 'opacity 0.5s ease';
  document.getElementById('toast-container').appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    setTimeout(() => toast.remove(), 500);
  }, 2500);
}
</script>
<script>
function renderAuthBar() {
    const user = localStorage.getItem("user");
    let html = '';
    if (user) {
        html = `<span class="text-success">Xin chào, ${user}!</span>
                <button class="btn btn-danger ms-2" onclick="logout()">Đăng xuất</button>`;
    } else {
        html = `<a href="Login.html" class="btn btn-success">Đăng nhập</a>`;
    }
    document.getElementById("auth-bar").innerHTML = html;
}
function logout() {
  fetch('/backend/logout.php', { method: 'POST' })
    .finally(() => {
      // Xóa JWT và dữ liệu liên quan ở client
      localStorage.removeItem('jwt_token');
      localStorage.removeItem('user');
      localStorage.removeItem('exp');

      // Chuyển hướng về trang đăng nhập
      window.location.href = 'login.html';

    });
}
renderAuthBar();
</script>
<script>
  document.getElementById('stripeCardForm').onsubmit = async function(e) {
  e.preventDefault();

  // 1. Tạo payment method trên Stripe
  const {paymentMethod, error} = await stripe.createPaymentMethod({
    type: 'card',
    card: card
  });
  if (error) {
    document.getElementById('card-errors').innerText = error.message;
    return;
  }

  // 2. Xác thực thanh toán với PaymentIntent (nếu cần, dùng Stripe confirmCardPayment)
  const {paymentIntent, error: confirmError} = await stripe.confirmCardPayment(clientSecret, {
    payment_method: paymentMethod.id
  });

  if (confirmError) {
    document.getElementById('card-errors').innerText = confirmError.message;
    return;
  }
  if (paymentIntent.status !== "succeeded") {
    document.getElementById('card-errors').innerText = "Thanh toán chưa thành công!";
    return;
  }

  // 3. Gửi dữ liệu đơn hàng sang backend để lưu đơn (cùng paymentIntentId)
  const jwt = localStorage.getItem("jwt_token");
  const cart = window.currentCart || [];
  const formData = new FormData();
  formData.append("cart", JSON.stringify(cart));
  formData.append("payment_intent_id", paymentIntentId);
  formData.append("receiverName", receiverInfo.receiverName);
  formData.append("receiverPhone", receiverInfo.receiverPhone);
  formData.append("receiverAddress", receiverInfo.receiverAddress);

  const res = await fetch("../backend/xacnhandathang.php", {
    method: "POST",
    body: formData,
    headers: {
      "Authorization": "Bearer " + jwt
    }
  });
  const resultJson = await res.json();
  showToast(resultJson.message || 'Thao tác thành công', 'success');

  // Đóng modal Stripe, reset giỏ hàng
  bootstrap.Modal.getOrCreateInstance(document.getElementById('stripeCardModal')).hide();
  document.getElementById("cart-body").innerHTML = `<div class="text-success">${resultJson.message || 'Thao tác thành công'}</div>`;
  updateCartCount();
  updateProductStock();
};

</script>
</body>
</html>

