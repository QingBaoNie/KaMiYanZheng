<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 获取用户提交的数据
    $hostname = trim($_POST['hostname']);
    $db_username = trim($_POST['db_username']);
    $db_password = trim($_POST['db_password']);
    $dbname = trim($_POST['dbname']);
    $admin_username = trim($_POST['admin_username']);
    $admin_password = md5(trim($_POST['admin_password'])); // 使用 MD5 加密管理员密码

    // 生成数据库配置文件内容
    $config_content = "<?php\n";
    $config_content .= "// 数据库配置文件\n";
    $config_content .= "\$db_servername = \"$hostname\";\n";
    $config_content .= "\$db_username = \"$db_username\";\n";
    $config_content .= "\$db_password = \"$db_password\";\n";
    $config_content .= "\$db_name = \"$dbname\";\n";
    $config_content .= "\n// 创建 MySQLi 连接\n";
    $config_content .= "\$conn = new mysqli(\$db_servername, \$db_username, \$db_password, \$db_name);\n";
    $config_content .= "if (\$conn->connect_error) {\n";
    $config_content .= "    die(\"数据库连接失败: \" . \$conn->connect_error);\n";
    $config_content .= "}\n";
    $config_content .= "\$conn->set_charset(\"utf8mb4\");\n";
    $config_content .= "?>";

    // 写入数据库配置文件
    if (file_put_contents(__DIR__ . '/config.php', $config_content)) {
        // 创建数据库连接
        include __DIR__ . '/config.php';

        // 检查数据库连接是否成功
        if ($conn->connect_error) {
            die("数据库连接失败: " . $conn->connect_error);
        }

        // **清理数据库：删除旧表**
        $conn->query("DROP TABLE IF EXISTS admin");
        $conn->query("DROP TABLE IF EXISTS codes");
        $conn->query("DROP TABLE IF EXISTS settings");

        // **初始化数据库表**
        $create_admin_table = "
            CREATE TABLE IF NOT EXISTS admin (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL
            )
        ";
        $create_codes_table = "
            CREATE TABLE IF NOT EXISTS codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(255) NOT NULL UNIQUE,
                duration INT NOT NULL, -- 卡密有效时长（分钟）
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- 卡密创建时间
                is_used BOOLEAN DEFAULT 0, -- 是否已使用
                used_at DATETIME DEFAULT NULL, -- 卡密使用时间
                user_ip VARCHAR(100) DEFAULT NULL, -- 使用者 IP
                user_location VARCHAR(255) DEFAULT NULL -- 使用者地理位置
            )
        ";
        $create_settings_table = "
            CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                key_name VARCHAR(255) NOT NULL UNIQUE,
                value TEXT NOT NULL
            )
        ";

        // 执行创建表的 SQL
        if ($conn->query($create_admin_table) === TRUE && 
            $conn->query($create_codes_table) === TRUE && 
            $conn->query($create_settings_table) === TRUE) {
            
            // 插入管理员账号
            $insert_admin = $conn->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
            $insert_admin->bind_param("ss", $admin_username, $admin_password);
            $admin_result = $insert_admin->execute();

            // 插入默认加密内容
            $default_encrypted_content = "这是默认的加密内容，您可以在管理员后台更新此内容。";
            $insert_settings = $conn->prepare("INSERT INTO settings (key_name, value) VALUES ('encrypted_content', ?)");
            $insert_settings->bind_param("s", $default_encrypted_content);
            $settings_result = $insert_settings->execute();

            if ($admin_result && $settings_result) {
                // 创建安装锁文件
                file_put_contents(__DIR__ . '/install.lock', '');
                $successMessage = "安装成功！<a href='admin_login.php'>点击进入管理员登录</a>";
            } else {
                $errorMessage = "初始化管理员账号或设置失败，请稍后再试。";
            }
        } else {
            $errorMessage = "初始化数据库表失败：" . $conn->error;
        }
    } else {
        $errorMessage = "配置文件写入失败，请检查文件权限。";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>引导安装</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f4eb;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            max-width: 400px;
            width: 100%;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        h1 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
        }
        label {
            font-size: 14px;
            margin: 10px 0;
            display: block;
            text-align: left;
            color: #555;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ddd;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        button:hover {
            background-color: #45a049;
        }
        .message {
            font-size: 14px;
            margin-top: 20px;
            color: red;
        }
        .message.success {
            color: green;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>安装配置</h1>
        <form method="POST">
            <label for="hostname">数据库主机:</label>
            <input type="text" id="hostname" name="hostname" value="localhost" required>
            
            <label for="db_username">数据库用户名:</label>
            <input type="text" id="db_username" name="db_username" required>
            
            <label for="db_password">数据库密码:</label>
            <input type="password" id="db_password" name="db_password">
            
            <label for="dbname">数据库名称:</label>
            <input type="text" id="dbname" name="dbname" required>

            <hr>

            <label for="admin_username">管理员账号:</label>
            <input type="text" id="admin_username" name="admin_username" required>
            
            <label for="admin_password">管理员密码:</label>
            <input type="password" id="admin_password" name="admin_password" required>
            
            <button type="submit">提交</button>
        </form>

        <!-- 显示成功或错误信息 -->
        <?php if (isset($successMessage)): ?>
            <p class="message success"><?php echo $successMessage; ?></p>
        <?php endif; ?>
        <?php if (isset($errorMessage)): ?>
            <p class="message"><?php echo $errorMessage; ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
