<?php
// 检测安装锁
if (!file_exists(__DIR__ . '/install.lock')) {
    header('Location: install.php');
    exit;
}

header('Content-Type: application/json');

// 加载数据库配置
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);

    // 获取用户的 IP 地址
    $userIp = $_SERVER['REMOTE_ADDR'];

    // 查询卡密信息
    $stmt = $conn->prepare("SELECT *, TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS elapsed_time FROM codes WHERE code = ?");
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => '服务器错误，请稍后再试！',
        ]);
        exit;
    }
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
        $content = $codeData['encrypted_content']; // 获取加密内容
        $encryptedFile = $codeData['encrypted_file']; // 获取加密文件

        if ($isUsed == 0) {
            // 未使用的卡密
            if ($elapsedTime <= $duration) {
                // 调用 ip-api 获取地理位置
                $geoApiUrl = "http://ip-api.com/json/{$userIp}?lang=zh-CN";
                $geoResponse = @file_get_contents($geoApiUrl);
                $geoData = json_decode($geoResponse, true);

                // 检查是否成功获取地理位置
                if ($geoData && $geoData['status'] === 'success') {
                    $userLocation = $geoData['country'] . ' ' . $geoData['regionName'] . ' ' . $geoData['city'];
                } else {
                    $userLocation = "未知位置";
                }

                // 更新卡密状态并保存地理位置信息
                $stmtUpdate = $conn->prepare("UPDATE codes SET is_used = 1, used_at = NOW(), user_ip = ?, user_location = ? WHERE id = ?");
                if (!$stmtUpdate) {
                    echo json_encode([
                        'success' => false,
                        'message' => '服务器错误，请稍后再试！',
                    ]);
                    exit;
                }
                $stmtUpdate->bind_param("ssi", $userIp, $userLocation, $codeData['id']);
                $stmtUpdate->execute();
                $stmtUpdate->close();

                // 计算剩余时间（秒）
                $remaining_time = ($duration - $elapsedTime) * 60;

                // 如果卡密没有单独的加密内容或加密文件，则使用默认加密内容
                if (empty($content) && empty($encryptedFile)) {
                    // 获取默认加密内容
                    $stmtDefault = $conn->prepare("SELECT value FROM settings WHERE key_name = 'encrypted_content' LIMIT 1");
                    if ($stmtDefault) {
                        $stmtDefault->execute();
                        $resultDefault = $stmtDefault->get_result();
                        $defaultContent = $resultDefault->num_rows > 0 ? $resultDefault->fetch_assoc()['value'] : "暂无加密内容，请联系管理员。";
                        $stmtDefault->close();
                    } else {
                        $defaultContent = "暂无加密内容，请联系管理员。";
                    }
                    $content = $defaultContent;
                }

                echo json_encode([
                    'success' => true,
                    'message' => '卡密验证成功！',
                    'remaining_time' => $remaining_time, // 剩余时间（秒）
                    'content' => $content,
                    'encrypted_file' => $encryptedFile
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
                    // 计算剩余时间（秒）
                    $remaining_time = ($duration - $timeSinceUse) * 60;

                    // 如果卡密没有单独的加密内容或加密文件，则使用默认加密内容
                    if (empty($content) && empty($encryptedFile)) {
                        // 获取默认加密内容
                        $stmtDefault = $conn->prepare("SELECT value FROM settings WHERE key_name = 'encrypted_content' LIMIT 1");
                        if ($stmtDefault) {
                            $stmtDefault->execute();
                            $resultDefault = $stmtDefault->get_result();
                            $defaultContent = $resultDefault->num_rows > 0 ? $resultDefault->fetch_assoc()['value'] : "暂无加密内容，请联系管理员。";
                            $stmtDefault->close();
                        } else {
                            $defaultContent = "暂无加密内容，请联系管理员。";
                        }
                        $content = $defaultContent;
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => '卡密验证成功！',
                        'remaining_time' => $remaining_time, // 剩余时间（秒）
                        'content' => $content,
                        'encrypted_file' => $encryptedFile
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
    $conn->close();
}
?>
