<?php
session_start();

// 检测安装锁
if (!file_exists(__DIR__ . '/install.lock')) {
    header('Location: install.php');
    exit;
}

// 验证管理员是否登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

include 'config.php'; // 包含数据库配置文件

// 从 settings 表中加载当前加密内容
$encryptedContent = '';
$query = "SELECT value FROM settings WHERE key_name = 'encrypted_content'";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $encryptedContent = $row['value'];
}

// 获取所有卡密
$query = "SELECT *,
                 IF(is_used = 0, 0, 
                    IF(TIMESTAMPDIFF(MINUTE, used_at, NOW()) > duration, 1, 0)) AS is_expired
          FROM codes 
          ORDER BY created_at DESC";
$result = $conn->query($query);
$codes = $result->fetch_all(MYSQLI_ASSOC);

// 处理表单提交功能
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete': // 删除选中的卡密
                if (isset($_POST['delete_codes']) && is_array($_POST['delete_codes'])) {
                    $ids = implode(",", array_map('intval', $_POST['delete_codes'])); // 防止 SQL 注入
                    $query = "DELETE FROM codes WHERE id IN ($ids)";
                    $result = $conn->query($query);

                    if ($result) {
                        $_SESSION['result_message'] = '已删除选中的卡密！';
                    } else {
                        $_SESSION['result_message'] = '删除卡密失败，请稍后再试。';
                    }
                } else {
                    $_SESSION['result_message'] = '请选择要删除的卡密。';
                }
                break;

            case 'clean_expired': // 清理过期的卡密
                $query = "DELETE FROM codes WHERE is_used = 1 AND TIMESTAMPDIFF(MINUTE, used_at, NOW()) > duration";
                $result = $conn->query($query);

                if ($result) {
                    $_SESSION['result_message'] = '已清理过期卡密！';
                } else {
                    $_SESSION['result_message'] = '清理过期卡密失败，请稍后再试。';
                }
                break;

            case 'update_content': // 更新加密内容
                if (isset($_POST['encrypted_content']) && !empty($_POST['encrypted_content'])) {
                    $newContent = trim($_POST['encrypted_content']);
                    $query = "UPDATE settings SET value = ? WHERE key_name = 'encrypted_content'";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("s", $newContent);
                    $result = $stmt->execute();

                    if ($result) {
                        $_SESSION['result_message'] = '加密内容已更新！';
                    } else {
                        $_SESSION['result_message'] = '更新加密内容失败，请稍后再试。';
                    }
                    $stmt->close();
                } else {
                    $_SESSION['result_message'] = '加密内容不能为空！';
                }
                break;

            case 'generate': // 生成卡密
                if (isset($_POST['length'], $_POST['duration']) && is_numeric($_POST['length']) && is_numeric($_POST['duration'])) {
                    $length = intval($_POST['length']);
                    $duration = intval($_POST['duration']);
                    $encryptedContent = trim($_POST['encrypted_content'] ?? '');
                    $encryptedFile = $_FILES['encrypted_file']['name'] ?? '';

                    if ($length >= 6 && $length <= 20 && $duration >= 1 && $duration <= 60) {
                        // 生成随机卡密
                        $code = bin2hex(random_bytes($length / 2));
                        $query = "INSERT INTO codes (code, duration, encrypted_content, encrypted_file) VALUES (?, ?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("siss", $code, $duration, $encryptedContent, $encryptedFile);
                        $result = $stmt->execute();

                        if ($result) {
                            $_SESSION['result_message'] = '卡密生成成功！';
                        } else {
                            $_SESSION['result_message'] = '生成卡密失败，请稍后再试。';
                        }
                        $stmt->close();
                    } else {
                        $_SESSION['result_message'] = '卡密长度或时效设置无效！';
                    }
                } else {
                    $_SESSION['result_message'] = '请输入有效的卡密长度和时效！';
                }
                break;

            case 'change_password': // 修改密码
                if (isset($_POST['current_password'], $_POST['new_password'], $_POST['confirm_password'])) {
                    $currentPassword = trim($_POST['current_password']);
                    $newPassword = trim($_POST['new_password']);
                    $confirmPassword = trim($_POST['confirm_password']);

                    // 验证当前密码
                    $query = "SELECT password FROM admin WHERE username = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("s", $_SESSION['admin_username']);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows === 1) {
                        $admin = $result->fetch_assoc();
                        if (md5($currentPassword) === $admin['password']) {
                            if ($newPassword === $confirmPassword) {
                                $newPasswordHash = md5($newPassword);
                                $updateQuery = "UPDATE admin SET password = ? WHERE username = ?";
                                $updateStmt = $conn->prepare($updateQuery);
                                $updateStmt->bind_param("ss", $newPasswordHash, $_SESSION['admin_username']);
                                $updateResult = $updateStmt->execute();

                                if ($updateResult) {
                                    $_SESSION['result_message'] = '密码修改成功！';
                                } else {
                                    $_SESSION['result_message'] = '密码修改失败，请稍后再试。';
                                }
                                $updateStmt->close();
                            } else {
                                $_SESSION['result_message'] = '新密码和确认密码不一致！';
                            }
                        } else {
                            $_SESSION['result_message'] = '当前密码不正确！';
                        }
                    }
                    $stmt->close();
                }
                break;
        }

        // 防止表单重复提交，重定向到当前页面
        header('Location: admin.php');
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>管理员后台</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="static/style.css">
   
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

        <!-- 显示生成卡密结果 -->
        <?php if (isset($_SESSION['result_message'])): ?>
            <div><?php echo $_SESSION['result_message']; ?></div>
            <?php unset($_SESSION['result_message']); ?>
        <?php endif; ?>

        <!-- 自定义加密内容表单 -->
        <form method="POST">
            <input type="hidden" name="action" value="update_content">
            <label for="encrypted_content">加密内容（支持HTML 和代码高亮）</label>
            <textarea id="encrypted_content" name="encrypted_content" rows="8" placeholder="请输入要加密显示的内容..." required><?php echo htmlspecialchars($encryptedContent); ?></textarea>
            <button type="submit">保存内容</button>
        </form>

        <!-- 可折叠实时预览 -->
        <button class="toggle-preview" id="togglePreviewBtn">展开预览</button>
        <div class="preview-container" id="previewContainer">
            <label for="preview">实时预览</label>
            <pre><code id="preview" class="language-html"></code></pre>
        </div>

        <!-- 卡密生成表单（通过 AJAX 提交） -->
        <h2>生成卡密</h2>
        <form id="generateForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="generate">
            <label for="length">卡密长度</label>
            <input type="number" id="length" name="length" min="6" max="20" required>
            <label for="duration">卡密时效（分钟）</label>
            <input type="number" id="duration" name="duration" min="1" max="60" required>

            <!-- 加密内容 -->
            <label for="encrypted_content_generate">加密内容</label>
            <textarea id="encrypted_content_generate" name="encrypted_content" rows="8" placeholder="请输入加密内容..."></textarea>

            <!-- 加密文件上传 -->
            <label for="encrypted_file">加密文件</label>
            <input type="file" id="encrypted_file" name="encrypted_file" accept=".txt,.zip,.rar,.pdf,.docx">

            <!-- 进度条 -->
            <div class="progress-bar-container" id="progressBarContainer">
                <div class="progress-bar" id="progressBar">0%</div>
            </div>

            <button type="submit">生成卡密</button>
        </form>

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
                        <th>加密内容</th>
                        <th>加密文件</th>
                        <th>使用时间</th>
                        <th>使用人 IP</th>
                        <th>地理位置</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($codes as $code): ?>
                        <tr>
                            <td><input type="checkbox" name="delete_codes[]" value="<?= htmlspecialchars($code['id']) ?>"></td>
                            <td><?= htmlspecialchars($code['code']) ?></td>
                            <td><?= htmlspecialchars($code['duration']) ?></td>
                            <td><?= $code['is_used'] ? '是' : '否' ?></td>
                            <td><?= $code['is_expired'] ? '是' : '否' ?></td>
                            
                            <!-- 显示加密内容 -->
                            <td><?= htmlspecialchars($code['encrypted_content']) ?: '无' ?></td>
                            
                            <!-- 显示加密文件 -->
                            <td>
                                <?php if ($code['encrypted_file']): ?>
                                    <a href="<?= htmlspecialchars($code['encrypted_file']) ?>" target="_blank">下载文件</a>
                                <?php else: ?>
                                    无
                                <?php endif; ?>
                            </td>
                            
                            <td><?= htmlspecialchars($code['used_at']) ?: '未使用' ?></td>
                            <td><?= htmlspecialchars($code['user_ip']) ?: 'N/A' ?></td>
                            <td><?= htmlspecialchars($code['user_location']) ?: 'N/A' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="delete-btn" style="margin-top: 20px;">删除选中</button>
        </form>
        
    </div>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-markup.min.js"></script>
     <script src="https://cdn.jsdelivr.net/npm/marked@4.0.12/marked.min.js"></script>


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

        // 实时预览逻辑
        const textarea = document.getElementById('encrypted_content');
        const preview = document.getElementById('preview');
        const previewContainer = document.getElementById('previewContainer');
        const togglePreviewBtn = document.getElementById('togglePreviewBtn');

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

        // 生成卡密表单提交逻辑（带进度条）
        const generateForm = document.getElementById('generateForm');
        const progressBarContainer = document.getElementById('progressBarContainer');
        const progressBar = document.getElementById('progressBar');

        generateForm.addEventListener('submit', function(e) {
            e.preventDefault(); // 阻止默认表单提交

            // 创建 FormData 对象
            const formData = new FormData(generateForm);

            // 创建 XMLHttpRequest 对象
            const xhr = new XMLHttpRequest();

            // 配置请求
            xhr.open('POST', 'generate_code.php', true);

            // 显示进度条
            progressBarContainer.style.display = 'block';
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';

            // 监听上传进度
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percentComplete + '%';
                    progressBar.textContent = percentComplete + '%';
                }
            });

            // 监听请求完成
            xhr.onreadystatechange = function() {
                if (xhr.readyState === XMLHttpRequest.DONE) {
                    progressBarContainer.style.display = 'none'; // 隐藏进度条
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                // 移除 alert，直接刷新页面以显示 session 消息
                                window.location.reload();
                            } else {
                                // 显示错误消息，可以使用已有的 result_message 或在此处显示
                                // 这里选择使用 alert 来显示错误消息
                                alert(response.message);
                            }
                        } catch (err) {
                            alert('解析服务器响应失败。');
                        }
                    } else {
                        alert('服务器错误，请稍后再试。');
                    }
                }
            };

            // 发送请求
            xhr.send(formData);
        });
    </script>


</body>
</html>