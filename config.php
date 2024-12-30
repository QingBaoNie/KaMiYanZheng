<?php
// 数据库配置文件
$db_servername = "localhost";
$db_username = "qpalzm12";
$db_password = "Ar3H7RG2Kya56ecS";
$db_name = "qpalzm12";

// 创建 MySQLi 连接
$conn = new mysqli($db_servername, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>