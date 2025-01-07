<?php
// 检测安装锁
if (!file_exists(__DIR__ . '/install.lock')) {
    header('Location: install.php');
    exit;
}

// 加载数据库配置
include 'config.php';

// 从数据库中获取默认加密内容
$query = "SELECT value FROM settings WHERE key_name = 'encrypted_content' LIMIT 1";
$result = $conn->query($query);
$defaultEncryptedContent = $result->num_rows > 0 ? htmlspecialchars($result->fetch_assoc()['value']) : "暂无加密内容，请联系管理员。";
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>卡密验证</title>
    <?php include('header.html'); ?>
</head>
<body>
    <div class="container">
        <!-- 管理员后台登录链接 -->
        <a href="admin_login.php" class="admin-login-link">管理员登录</a>
        
        <h1>卡密验证系统</h1>
        <input type="text" id="code" placeholder="请输入卡密">
        <button id="submitBtn">验证卡密</button>
        <p id="error-message" class="error-message" style="display: none;">卡密错误，请重新输入！</p>
        <div id="encrypted-content" class="encrypted-content" style="display: none;">
            <!-- 输出加密内容 -->
            <?php echo $defaultEncryptedContent; ?>
        </div>
        <div id="download-file" class="download-file" style="display: none;">
            <!-- 输出加密文件下载链接 -->
            <a href="#" id="file-link" download>点击下载加密文件</a>
        </div>
        <p id="countdown" style="display: none;">剩余时间：<span id="time-remaining"></span></p>
    </div>

    <script>
        document.getElementById("submitBtn").addEventListener("click", function () {
            const userCode = document.getElementById("code").value.trim();

            if (userCode === "") {
                document.getElementById("error-message").textContent = "请输入卡密！";
                document.getElementById("error-message").style.display = "block";
                return;
            }

            // 禁用输入框和按钮，防止重复提交
            document.getElementById("code").disabled = true;
            document.getElementById("submitBtn").disabled = true;

            // 使用 AJAX 提交表单到 yanzheng.php
            fetch('yanzheng.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `code=${encodeURIComponent(userCode)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 显示加密内容
                    if (data.content) {
                        document.getElementById("encrypted-content").style.display = "block";
                        document.getElementById("encrypted-content").innerHTML = data.content;
                    } else {
                        document.getElementById("encrypted-content").style.display = "none";
                    }

                    // 显示加密文件下载链接
                    if (data.encrypted_file) {
                        document.getElementById("download-file").style.display = "block";
                        document.getElementById("file-link").href = data.encrypted_file;
                    } else {
                        document.getElementById("download-file").style.display = "none";
                    }

                    // 显示倒计时
                    document.getElementById("countdown").style.display = "block";

                    // 开始倒计时
                    const endTime = Date.now() + data.remaining_time * 1000; // 剩余时间（秒）转为毫秒
                    startCountdown(endTime);
                } else {
                    // 如果验证失败，重新启用输入框和按钮
                    document.getElementById("code").disabled = false;
                    document.getElementById("submitBtn").disabled = false;

                    document.getElementById("error-message").textContent = data.message;
                    document.getElementById("error-message").style.display = "block";
                }
            })
            .catch(err => {
                console.error("请求失败：", err);

                // 请求失败时重新启用输入框和按钮
                document.getElementById("code").disabled = false;
                document.getElementById("submitBtn").disabled = false;

                document.getElementById("error-message").textContent = "网络错误，请稍后重试！";
                document.getElementById("error-message").style.display = "block";
            });
        });

        // 倒计时逻辑
        function startCountdown(endTime) {
            const countdownEl = document.getElementById("countdown");

            function updateCountdown() {
                const now = Date.now();
                const diff = endTime - now;

                if (diff <= 0) {
                    clearInterval(timer);
                    countdownEl.textContent = "卡密已过期！";
                    document.getElementById("encrypted-content").style.display = "none"; // 隐藏内容
                    document.getElementById("download-file").style.display = "none"; // 隐藏文件下载

                    // 刷新页面
                    setTimeout(() => {
                        location.reload(); // 倒计时结束后刷新页面
                    }, 2000); // 等待 2 秒后刷新页面
                    return;
                }

                const minutes = Math.floor(diff / 1000 / 60);
                const seconds = Math.floor((diff / 1000) % 60);
                countdownEl.textContent = `剩余时间：${minutes}分${seconds}秒`;
            }

            updateCountdown(); // 初始化时更新一次
            const timer = setInterval(updateCountdown, 1000); // 每秒更新
        }
    </script>
</body>
</html>
