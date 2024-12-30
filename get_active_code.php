<?php
session_start();

// 检查当前是否有活动的卡密
if (isset($_SESSION['active_code']) && isset($_SESSION['active_code_expiration'])) {
    $remaining_time = $_SESSION['active_code_expiration'] - time();

    if ($remaining_time > 0) {
        echo json_encode([
            "success" => true,
            "active_code" => $_SESSION['active_code'],
            "remaining_time" => $remaining_time
        ]);
        exit;
    } else {
        // 如果卡密已过期，清除会话状态
        unset($_SESSION['active_code']);
        unset($_SESSION['active_code_expiration']);
        echo json_encode([
            "success" => false,
            "message" => "卡密已过期！"
        ]);
        exit;
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "没有活动的卡密。"
    ]);
    exit;
}
?>
