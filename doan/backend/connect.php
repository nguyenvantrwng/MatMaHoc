<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Thông tin Railway (copy từ connection string)
$server = 'shortline.proxy.rlwy.net';
$user   = 'root';
$pass   = 'DjNzpArryGnNSpmXOTYAHHaSOEDYqDnq';
$db     = 'railway';
$port   = 48592;

// Kết nối MySQL
$conn = new mysqli($server, $user, $pass, $db, $port);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>
