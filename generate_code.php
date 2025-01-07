<?php
session_start();
header('Content-Type: application/json');

// 临时启用错误报告（仅开发阶段使用）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 检测安装锁
if (!file_exists(__DIR__ . '/install.lock')) {
    echo json_encode([
        'success' => false,
        'message' => '系统尚未安装，请先安装。'
    ]);
    exit;
}

// 验证管理员是否登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode([
        'success' => false,
        'message' => '未登录或会话过期，请重新登录。'
    ]);
    exit;
}

include 'config.php'; // 包含数据库配置文件

// 生成随机卡密的函数
function generateCode($length) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $charactersLength = strlen($characters);
    $randomCode = '';
    for ($i = 0; $i < $length; $i++) {
        $randomCode .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomCode;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取表单数据
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 60;
    $encryptedContent = isset($_POST['encrypted_content']) ? trim($_POST['encrypted_content']) : null;
    $encryptedFile = null;

    // 处理文件上传
    if (isset($_FILES['encrypted_file']) && $_FILES['encrypted_file']['error'] === UPLOAD_ERR_OK) {
        // 验证文件类型
        $allowedMimeTypes = [
            'text/plain',
            'application/zip',
            'application/x-rar-compressed',
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['encrypted_file']['tmp_name']);
        if (!in_array($mimeType, $allowedMimeTypes)) {
            $_SESSION['result_message'] = "<p style='color:red;'>不支持的文件类型。</p>";
            echo json_encode([
                'success' => false,
                'message' => '不支持的文件类型。'
            ]);
            exit;
        }

        $uploadDir = 'uploads/';  // 文件存储目录
        // 确保上传目录存在
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileName = basename($_FILES['encrypted_file']['name']);
        $fileName = preg_replace("/[^A-Za-z0-9.\-_]/", '', $fileName); // 简单的文件名清理
        $uploadFile = $uploadDir . time() . '_' . $fileName;

        // 移动文件到目标目录
        if (move_uploaded_file($_FILES['encrypted_file']['tmp_name'], $uploadFile)) {
            $encryptedFile = $uploadFile;
        } else {
            $_SESSION['result_message'] = "<p style='color:red;'>文件上传失败，请重试。</p>";
            echo json_encode([
                'success' => false,
                'message' => '文件上传失败，请重试。'
            ]);
            exit;
        }
    }

    // 生成卡密
    $code = generateCode($length);

    // 插入数据到数据库
    $stmt = $conn->prepare("INSERT INTO codes (code, duration, encrypted_content, encrypted_file) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        $_SESSION['result_message'] = "<p style='color:red;'>服务器错误，请稍后再试。</p>";
        echo json_encode([
            'success' => false,
            'message' => '服务器错误，请稍后再试。'
        ]);
        exit;
    }
    $stmt->bind_param("siss", $code, $duration, $encryptedContent, $encryptedFile);
    if ($stmt->execute()) {
        $_SESSION['result_message'] = "<p style='color:green;'>卡密生成成功！卡密为: <strong>" . htmlspecialchars($code) . "</strong></p>";
        echo json_encode([
            'success' => true,
            'message' => '卡密生成成功！'
        ]);
    } else {
        $_SESSION['result_message'] = "<p style='color:red;'>生成卡密失败，请稍后再试。</p>";
        echo json_encode([
            'success' => false,
            'message' => '生成卡密失败，请稍后再试。'
        ]);
    }
    $stmt->close();
    $conn->close();
} else {
    echo json_encode([
        'success' => false,
        'message' => '无效的请求方式。'
    ]);
}
?>
