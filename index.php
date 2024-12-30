<?php
// 加载数据库配置
include 'config.php';

// 从数据库中获取加密内容
$query = "SELECT value FROM settings WHERE key_name = 'encrypted_content' LIMIT 1";
$result = $conn->query($query);
$encryptedContent = $result->num_rows > 0 ? $result->fetch_assoc()['value'] : "暂无加密内容，请联系管理员。";
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>卡密验证</title>
    <style>
        /* 通用样式 */
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f4eb; /* 浅灰背景 */
            margin: 0;
            padding: 0;
        }

        /* 容器样式 */
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background-color: #ffffff; /* 白色背景 */
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); /* 柔和的阴影 */
            position: relative;
        }

        /* 标题样式 */
        h1 {
            font-size: 24px;
            text-align: center;
            color: #333333; /* 深灰色标题 */
            margin-bottom: 20px;
        }

        /* 输入框样式 */
        input[type="text"] {
            width: 100%;
            padding: 12px;
            margin: 15px 0;
            border-radius: 8px;
            border: 1px solid #dddddd; /* 边框灰色 */
            box-sizing: border-box;
            font-size: 16px;
            background-color: #f9f9f9; /* 浅灰背景 */
            transition: border-color 0.3s ease;
        }

        input[type="text"]:focus {
            border-color: #4CAF50; /* 绿色边框 */
            outline: none;
            background-color: #ffffff; /* 聚焦时白色背景 */
        }

        /* 按钮样式 */
        button {
            background-color: #4CAF50; /* 绿色按钮 */
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #45a049; /* 鼠标悬停更深的绿色 */
        }

        /* 错误提示 */
        .error-message {
            color: #dc3545; /* 红色提示 */
            font-size: 16px;
            margin-top: 10px;
            text-align: center;
            display: none;
        }

        /* 加密内容样式 */
        .encrypted-content {
            text-align: center;
            padding: 20px;
            background-color: #e8f5e9; /* 浅绿色背景 */
            border-radius: 10px;
            margin-top: 20px;
            border: 1px solid #c8e6c9; /* 绿色边框 */
            color: #444; /* 深灰内容文字 */
        }

        /* 倒计时样式 */
        #countdown {
            font-size: 16px;
            color: #333333;
            margin-top: 10px;
            text-align: center;
        }

        /* 管理员链接样式 */
        .admin-login-link {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 14px;
            color: #007bff; /* 蓝色链接 */
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        .admin-login-link:hover {
            color: #0056b3; /* 深蓝色悬停效果 */
            text-decoration: underline;
        }
    </style>
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
            <?php echo $encryptedContent; ?>
        </div>
        <p id="countdown" style="display: none;">剩余时间：<span id="time-remaining"></span></p>
    </div>

    <script>
        document.getElementById("submitBtn").addEventListener("click", function () {
            const userCode = document.getElementById("code").value;

            // 禁用输入框和按钮，防止重复提交
            document.getElementById("code").disabled = true;
            document.getElementById("submitBtn").disabled = true;

            // 使用 AJAX 提交表单到 yanzheng.php
            fetch('yanzheng.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `code=${userCode}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById("encrypted-content").style.display = "block";
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

                    // 刷新页面
                    setTimeout(() => {
                        location.reload(); // 倒计时结束后刷新页面
                    }, 2000); // 等待 2 秒后刷新页面
                    return;
                }

                const minutes = Math.floor(diff / 1000 / 60);
                const seconds = Math.floor((diff / 1000) % 60);
                countdownEl.textContent = `${minutes}分${seconds}秒`;
            }

            updateCountdown(); // 初始化时更新一次
            const timer = setInterval(updateCountdown, 1000); // 每秒更新
        }
    </script>
</body>
</html>
