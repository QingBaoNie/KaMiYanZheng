<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    unset($_SESSION['active_code']);
    unset($_SESSION['active_code_expiration']);
    echo json_encode(["success" => true, "message" => "活动卡密已清除！"]);
}



if (isset($_SESSION['active_code']) && isset($_SESSION['active_code_expiration'])) {
    $remaining_time = $_SESSION['active_code_expiration'] - time();

    if ($remaining_time > 0) {
        echo json_encode([
            "locked" => true,
            "remaining_time" => $remaining_time
        ]);
    } else {
        // 如果卡密已过期，清除锁定状态
        unset($_SESSION['active_code']);
        unset($_SESSION['active_code_expiration']);
        echo json_encode(["locked" => false]);
    }
} else {
    echo json_encode(["locked" => false]);
}
?>
