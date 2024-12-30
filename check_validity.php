<?php
session_start();
include 'config.php'; // 数据库配置

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'];

    // 查询卡密是否有效
    $stmt = $conn->prepare("SELECT *, TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS elapsed_time FROM codes WHERE code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $codeData = $result->fetch_assoc();

    if ($codeData) {
        $elapsedTime = $codeData['elapsed_time'];
        $duration = $codeData['duration'];
        $isUsed = $codeData['is_used'];

        if ($elapsedTime <= $duration && !$isUsed) {
            echo json_encode(['valid' => true]);
        } else {
            echo json_encode(['valid' => false]);
        }
    } else {
        echo json_encode(['valid' => false]);
    }

    $stmt->close();
}
?>
