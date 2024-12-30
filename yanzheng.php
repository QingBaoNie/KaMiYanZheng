<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'];

    // 获取用户的 IP 地址
    $userIp = $_SERVER['REMOTE_ADDR'];

    // 查询卡密信息
    $stmt = $conn->prepare("SELECT *, TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS elapsed_time FROM codes WHERE code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $codeData = $result->fetch_assoc();

    if ($codeData) {
        $elapsedTime = $codeData['elapsed_time'];
        $duration = $codeData['duration'];
        $isUsed = $codeData['is_used'];
        $usedAt = $codeData['used_at'];
        $storedIp = $codeData['user_ip']; // 数据库中存储的 IP 地址

        if ($isUsed == 0) {
            // 未使用的卡密
            if ($elapsedTime <= $duration) {
                // 调用 ip-api 获取地理位置
                $geoApiUrl = "http://ip-api.com/json/{$userIp}?lang=zh-CN";
                $geoResponse = file_get_contents($geoApiUrl);
                $geoData = json_decode($geoResponse, true);

                // 检查是否成功获取地理位置
                if ($geoData && $geoData['status'] === 'success') {
                    $userLocation = $geoData['country'] . ' ' . $geoData['regionName'] . ' ' . $geoData['city'];
                } else {
                    $userLocation = "未知位置";
                }

                // 更新卡密状态并保存地理位置信息
                $stmtUpdate = $conn->prepare("UPDATE codes SET is_used = 1, used_at = NOW(), user_ip = ?, user_location = ? WHERE id = ?");
                $stmtUpdate->bind_param("ssi", $userIp, $userLocation, $codeData['id']);
                $stmtUpdate->execute();

                echo json_encode([
                    'success' => true,
                    'message' => '卡密验证成功！',
                    'remaining_time' => ($duration - $elapsedTime) * 60, // 剩余时间（秒）
                ]);
            } else {
                // 未使用但过期
                echo json_encode([
                    'success' => false,
                    'message' => '卡密已过期！',
                ]);
            }
        } else {
            // 已使用的卡密
            if ($storedIp === $userIp) {
                // 同一 IP 地址可以继续使用该卡密
                $timeSinceUse = (time() - strtotime($usedAt)) / 60; // 使用后的分钟数
                if ($timeSinceUse <= $duration) {
                    echo json_encode([
                        'success' => true,
                        'message' => '卡密验证成功！',
                        'remaining_time' => ($duration - $timeSinceUse) * 60, // 剩余时间（秒）
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => '卡密已过期！',
                    ]);
                }
            } else {
                // 不同 IP 地址尝试使用已使用的卡密
                echo json_encode([
                    'success' => false,
                    'message' => '卡密已被人使用，请更换一个卡密！',
                ]);
            }
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => '无效的卡密！',
        ]);
    }

    $stmt->close();
}
?>
