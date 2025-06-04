<?php
function getAesKey() {
    $key = getenv('AES_KEY');
    if ($key === false || strlen($key) !== 32) {
        die('Chưa cấu hình biến môi trường AES_KEY hoặc key không đủ 32 ký tự (256 bit)!');
    }
    return $key;
}
function encryptField($plaintext, $key) {
    $iv = random_bytes(12); // 12 bytes cho GCM
    $tag = '';
    $ciphertext = openssl_encrypt(
        $plaintext, 
        'aes-256-gcm', 
        $key, 
        OPENSSL_RAW_DATA, 
        $iv, 
        $tag
    );
    return base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($ciphertext);
}

function decryptField($encStr, $key) {
    $parts = explode(':', $encStr);
    if (count($parts) !== 3) return false;
    list($b64_iv, $b64_tag, $b64_ciphertext) = $parts;
    $iv = base64_decode($b64_iv);
    $tag = base64_decode($b64_tag);
    $ciphertext = base64_decode($b64_ciphertext);
    return openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
}
?>
