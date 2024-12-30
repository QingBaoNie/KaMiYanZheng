<?php
// 生成卡密逻辑
$length = $_POST['length'];
$duration = $_POST['duration'];

// 生成随机卡密
$code = bin2hex(random_bytes($length / 2));

// 计算卡密过期时间
$expiration_time = date('Y-m-d H:i:s', strtotime("+$duration minutes"));

// 存储到数据库
$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'card_system';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare("INSERT INTO codes (code, duration, expiration_time) VALUES (?, ?, ?)");
$stmt->bind_param("sis", $code, $duration, $expiration_time);
$stmt->execute();

echo $code;

$stmt->close();
$conn->close();
?>
