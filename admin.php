<?php
session_start();

// 验证管理员是否登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
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

$resultMessage = ""; // 用于显示结果的变量

// 处理生成卡密
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    $length = intval($_POST['length']);
    $duration = intval($_POST['duration']);
    
    // 生成卡密
    $code = generateCode($length);

    // 插入数据到数据库
    $stmt = $conn->prepare("INSERT INTO codes (code, duration) VALUES (?, ?)");
    if (!$stmt) {
        die("SQL 查询准备失败: " . $conn->error);
    }
    $stmt->bind_param("si", $code, $duration);
    if ($stmt->execute()) {
        $_SESSION['result_message'] = "<p style='color:green;'>卡密生成成功！卡密为: <strong>" . $code . "</strong></p>";
        header("Location: admin.php"); // 防止重复提交
        exit;
    } else {
        $_SESSION['result_message'] = "<p style='color:red;'>生成卡密失败，请稍后再试。</p>";
        header("Location: admin.php");
        exit;
    }
    $stmt->close();
}

// 显示生成结果
if (isset($_SESSION['result_message'])) {
    $resultMessage = $_SESSION['result_message'];
    unset($_SESSION['result_message']); // 清除会话消息
}

// 处理删除卡密
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (isset($_POST['delete_codes']) && is_array($_POST['delete_codes'])) {
        $delete_ids = $_POST['delete_codes'];
        $placeholders = implode(',', array_fill(0, count($delete_ids), '?'));
        $stmt = $conn->prepare("DELETE FROM codes WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($delete_ids)), ...$delete_ids);
        if ($stmt->execute()) {
            $_SESSION['result_message'] = "<p style='color:green;'>选中卡密已成功删除！</p>";
            header("Location: admin.php");
            exit;
        } else {
            $_SESSION['result_message'] = "<p style='color:red;'>删除失败，请稍后再试。</p>";
            header("Location: admin.php");
            exit;
        }
        $stmt->close();
    } else {
        $_SESSION['result_message'] = "<p style='color:red;'>未选择任何卡密进行删除。</p>";
        header("Location: admin.php");
        exit;
    }
}

// 处理一键清理已使用且过期的卡密
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clean_expired') {
    $stmt = $conn->prepare("DELETE FROM codes WHERE TIMESTAMPDIFF(MINUTE, used_at, NOW()) > duration AND is_used = 1");
    if (!$stmt) {
        die("SQL 查询准备失败: " . $conn->error);
    }
    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows; // 获取被删除的记录数
        if ($affected_rows > 0) {
            $_SESSION['result_message'] = "<p style='color:green;'>成功清理了 {$affected_rows} 条已使用且过期的卡密！</p>";
        } else {
            $_SESSION['result_message'] = "<p style='color:orange;'>没有找到已使用且过期的卡密。</p>";
        }
    } else {
        $_SESSION['result_message'] = "<p style='color:red;'>清理已使用且过期的卡密失败，请稍后再试。</p>";
    }
    $stmt->close();
    header("Location: admin.php");
    exit;
}

// 处理修改密码
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = md5(trim($_POST['current_password']));
    $newPassword = md5(trim($_POST['new_password']));
    $confirmPassword = md5(trim($_POST['confirm_password']));

    // 验证当前密码是否正确
    $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $_SESSION['admin_username'], $currentPassword);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // 当前密码正确，验证新密码是否一致
        if ($newPassword === $confirmPassword) {
            // 更新密码
            $updateStmt = $conn->prepare("UPDATE admin SET password = ? WHERE username = ?");
            $updateStmt->bind_param("ss", $newPassword, $_SESSION['admin_username']);
            if ($updateStmt->execute()) {
                $_SESSION['result_message'] = "<p style='color:green;'>密码修改成功！</p>";
            } else {
                $_SESSION['result_message'] = "<p style='color:red;'>密码修改失败，请稍后再试。</p>";
            }
            $updateStmt->close();
        } else {
            $_SESSION['result_message'] = "<p style='color:red;'>新密码与确认密码不一致！</p>";
        }
    } else {
        $_SESSION['result_message'] = "<p style='color:red;'>当前密码错误！</p>";
    }

    $stmt->close();
    header("Location: admin.php");
    exit;
}

// 获取所有卡密
$query = "SELECT *,
                 IF(is_used = 0, 0, 
                    IF(TIMESTAMPDIFF(MINUTE, used_at, NOW()) > duration, 1, 0)) AS is_expired
          FROM codes 
          ORDER BY created_at DESC";
$result = $conn->query($query);
$codes = $result->fetch_all(MYSQLI_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_content') {
    // 获取管理员提交的加密内容
    $updatedContent = $_POST['encrypted_content'];

    // 更新到数据库的 settings 表中
    $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE key_name = 'encrypted_content'");
    $stmt->bind_param("s", $updatedContent);

    if ($stmt->execute()) {
        $resultMessage = "<p style='color:green;'>加密内容已成功更新！</p>";
    } else {
        $resultMessage = "<p style='color:red;'>更新加密内容失败，请稍后重试。</p>";
    }

    $stmt->close();
}

// 从 settings 表中加载当前加密内容
$query = "SELECT value FROM settings WHERE key_name = 'encrypted_content'";
$result = $conn->query($query);
$encryptedContent = $result->fetch_assoc()['value'] ?? '';

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css" rel="stylesheet" />
    <title>管理员后台</title>
    <style>
        /* 通用样式 */
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background-color: #ffffff; /* 首页相同的背景颜色 */
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 20px;
            background-color: #ffffff; /* 卡片白色背景 */
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); /* 柔和阴影 */
            position: relative;
        }

        /* 顶部按钮样式 */
        .buttons {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }

        .buttons button, .buttons form button {
            padding: 8px 15px;
            font-size: 14px;
            font-weight: bold;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .change-password {
            background-color: #007bff;
            color: white;
            transition: all 0.3s ease;
        }

        .change-password:hover {
            background-color: #0056b3;
        }

        .logout {
            background-color: #f44336;
            color: white;
            transition: all 0.3s ease;
        }

        .logout:hover {
            background-color: #d32f2f;
        }

        h1 {
            font-size: 28px;
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        /* 表单样式 */
        form {
            margin-bottom: 20px;
        }

        form h2 {
            font-size: 22px;
            color: #444;
            margin-bottom: 15px;
        }

        form label {
            font-size: 14px;
            font-weight: bold;
            color: #555;
            display: block;
            margin: 10px 0 5px;
        }

        form input[type="number"], 
        form input[type="text"], 
        form input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 14px;
            background-color: #ffffff; /* 与首页背景相似 */
        }

        form button {
            background-color: #28a745; /* 首页绿色按钮 */
            color: white;
            padding: 10px 15px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        form button:hover {
            background-color: #218838;
        }

        /* 表格样式 */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }

        table, th, td {
            border: 1px solid #ddd;
        }

        th {
            background-color: #f4f4f4;
            color: #333;
            text-align: center;
            padding: 12px;
        }

        td {
            padding: 10px;
            text-align: center;
        }

        td:first-child input {
            cursor: pointer;
        }

        .delete-btn {
            background-color: #f44336;
            color: white;
            padding: 8px 15px;
            font-size: 14px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .delete-btn:hover {
            background-color: #d32f2f;
        }

        /* 修改密码弹窗样式 */
        #changePasswordModal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #ffffff;
            padding: 30px;
            width: 400px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }

        #modalOverlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        #changePasswordModal h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #444;
            text-align: center;
        }

        #changePasswordModal form button {
            margin-top: 15px;
        }

        #changePasswordModal form button#closeModal {
            background-color: #6c757d;
            margin-top: 10px;
        }

        #changePasswordModal form button#closeModal:hover {
            background-color: #5a6268;
        }
        form textarea {
    width: 100%; /* 拉满宽度 */
    padding: 12px;
    margin-bottom: 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    box-sizing: border-box;
    font-size: 14px;
    font-family: inherit;
    resize: vertical; /* 允许垂直调整大小 */
    background-color: #ffffff; /* 白色背景 */
}
 pre {
            background-color: #f4f4f4;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            font-size: 14px;
            overflow: auto;
        }
        /* 折叠按钮样式 */
        .toggle-preview {
             background-color: #28a745; /* 首页绿色按钮 */
            color: white;
            padding: 10px 15px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .toggle-preview:hover {
            background-color: #45a049;
        }

        /* 折叠区域样式 */
        .preview-container {
            margin-top: 10px;
            display: none; /* 默认隐藏预览内容 */
        }
    </style>
</head>
<body>
    <div class="container">
    <!-- 顶部按钮 -->
    <div class="buttons">
        <button id="changePasswordBtn" class="change-password">修改密码</button>
        <form method="POST" action="logout.php" style="margin: 0;">
            <button type="submit" class="logout">退出登录</button>
        </form>
    </div>

    <h1>管理员后台 - 卡密管理</h1>

    <!-- 自定义加密内容表单 -->
 <form method="POST">
        <input type="hidden" name="action" value="update_content">
        <label for="encrypted_content">加密内容（支持HTML 和代码高亮）</label>
        <textarea id="encrypted_content" name="encrypted_content" rows="8" placeholder="请输入要加密显示的内容..." required><?php echo htmlspecialchars($encryptedContent); ?></textarea>
        <button type="submit">保存内容</button>
    </form>

    <!-- 可折叠实时预览 -->
    <button  class="toggle-preview" id="togglePreviewBtn">展开预览</button>
    <div class="preview-container" id="previewContainer">
        <label for="preview">实时预览</label>
        <pre><code id="preview" class="language-html"></code></pre>
    </div>





    <!-- 卡密生成表单 -->
    <form method="POST">
        <h2>生成卡密</h2>
        <input type="hidden" name="action" value="generate">
        <label for="length">卡密长度</label>
        <input type="number" id="length" name="length" min="6" max="20" required>
        <label for="duration">卡密时效（分钟）</label>
        <input type="number" id="duration" name="duration" min="1" max="60" required>
        <button type="submit">生成卡密</button>
    </form>

    <div id="result"><?php echo $resultMessage; ?></div>

    <!-- 一键清理过期卡密 -->
    <form method="POST" style="margin-top: 20px;">
        <input type="hidden" name="action" value="clean_expired">
        <button type="submit" class="delete-btn">一键清理已使用且过期的卡密</button>
    </form>

    <!-- 卡密列表 -->
    <form method="POST">
        <h2>卡密列表</h2>
        <input type="hidden" name="action" value="delete">
        <table>
            <thead>
                <tr>
                    <th>选择</th>
                    <th>卡密</th>
                    <th>有效时长 (分钟)</th>
                    <th>是否使用</th>
                    <th>是否过期</th>
                    <th>使用时间</th>
                    <th>使用人 IP</th>
                    <th>地理位置</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($codes as $code): ?>
                    <tr>
                        <td><input type="checkbox" name="delete_codes[]" value="<?= $code['id'] ?>"></td>
                        <td><?= htmlspecialchars($code['code']) ?></td>
                        <td><?= $code['duration'] ?></td>
                        <td><?= $code['is_used'] ? '是' : '否' ?></td>
                        <td><?= $code['is_expired'] ? '是' : '否' ?></td>
                        <td><?= $code['used_at'] ?: '未使用' ?></td>
                        <td><?= $code['user_ip'] ?: 'N/A' ?></td>
                        <td><?= $code['user_location'] ?: 'N/A' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit" class="delete-btn" style="margin-top: 20px;">删除选中</button>
    </form>
    </div>
     <!-- 修改密码弹窗 -->
    <div id="modalOverlay"></div>
    <div id="changePasswordModal">
        <h2>修改密码</h2>
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <label for="current_password">当前密码</label>
            <input type="password" id="current_password" name="current_password" required>
            <label for="new_password">新密码</label>
            <input type="password" id="new_password" name="new_password" required>
            <label for="confirm_password">确认新密码</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
            <button type="submit">保存</button>
            <button type="button" id="closeModal">取消</button>
        </form>
    </div>

    <script>
        // 修改密码弹窗逻辑
        const modal = document.getElementById("changePasswordModal");
        const overlay = document.getElementById("modalOverlay");
        const openModalBtn = document.getElementById("changePasswordBtn");
        const closeModalBtn = document.getElementById("closeModal");

        openModalBtn.addEventListener("click", () => {
            modal.style.display = "block";
            overlay.style.display = "block";
        });

        closeModalBtn.addEventListener("click", () => {
            modal.style.display = "none";
            overlay.style.display = "none";
        });

        overlay.addEventListener("click", () => {
            modal.style.display = "none";
            overlay.style.display = "none";
        });
    </script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-markup.min.js"></script>
<script>
    // 获取相关元素
    const textarea = document.getElementById('encrypted_content');
    const preview = document.getElementById('preview');
    const previewContainer = document.getElementById('previewContainer');
    const togglePreviewBtn = document.getElementById('togglePreviewBtn');

    // 初始化实时预览
    textarea.addEventListener('input', () => {
        const content = textarea.value;
        preview.textContent = content; // 将内容设置为文本
        Prism.highlightElement(preview); // 触发 Prism.js 重新高亮
    });

    // 默认触发一次输入事件，确保加载页面时有高亮
    textarea.dispatchEvent(new Event('input'));

    // 折叠/展开预览逻辑
    togglePreviewBtn.addEventListener('click', () => {
        if (previewContainer.style.display === 'none' || previewContainer.style.display === '') {
            previewContainer.style.display = 'block';
            togglePreviewBtn.textContent = '折叠预览';
        } else {
            previewContainer.style.display = 'none';
            togglePreviewBtn.textContent = '展开预览';
        }
    });
</script>
</body>
</html>