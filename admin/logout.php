<?php
// 管理员退出登录
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// 检查管理员是否已登录
if (isset($_SESSION['admin_id'])) {
    // 记录退出日志
    log_operation($_SESSION['admin_id'], '管理员退出');
    
    // 清除会话数据
    $_SESSION = array();
    
    // 如果使用了会话cookie，则清除会话cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // 销毁会话
    session_destroy();
}

// 重定向到登录页面
header('Location: index.php');
exit;