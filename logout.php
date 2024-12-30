<?php
session_start();

// 清除会话数据并退出登录
session_unset();
session_destroy();

header("Location: admin_login.php");
exit;
?>
