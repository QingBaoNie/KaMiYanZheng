<?php
header('Content-Type: application/json');

// 引入数据库配置
include 'config.php';

$response = [
    'success' => false,
    'content' => ''
];

// 从 settings 表中获取加密内容
$query = "SELECT value FROM settings WHERE key_name = 'encrypted_content'";
$result = $conn->query($query);

if ($result && $row = $result->fetch_assoc()) {
    $response['success'] = true;
    $response['content'] = $row['value']; // 原始HTML直接输出
}

echo json_encode($response);
?>
