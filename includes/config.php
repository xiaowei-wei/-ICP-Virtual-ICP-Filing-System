<?php
session_start();
// 错误报告设置
ini_set('display_errors', 0); // 禁止在浏览器中显示错误
ini_set('display_startup_errors', 0); // 禁止在浏览器中显示启动错误
error_reporting(E_ALL);
// 检查数据库配置文件是否存在且有效，未安装则跳转到安装页面
if (!file_exists(__DIR__ . '/../config/database.php') || filesize(__DIR__ . '/../config/database.php') === 0) {
    header('Location: /install.php');
    exit;
}
// 检查数据库连接和核心表是否存在，未安装则跳转到安装页面
try {
    require_once __DIR__ . '/../config/database.php';
    $db = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    // 检查核心表 system_config 是否存在
    $checkTable = $db->query("SHOW TABLES LIKE 'system_config'");
    if ($checkTable->rowCount() === 0) {
        header('Location: /install.php');
        exit;
    }
} catch (Exception $e) {
    header('Location: /install.php');
    exit;
}
// 系统配置文件

// 开启会话

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 网站基本信息
define('SITE_NAME', '虚拟ICP备案系统');
define('SITE_URL', 'http://localhost/icp_system');
define('ADMIN_EMAIL', 'admin@example.com');

// 引入数据库配置
require_once __DIR__ . '/../config/database.php';

// 系统常量
define('STATUS_PENDING', 'pending');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');

// 获取系统配置
function get_system_config($key, $default = null) {
    try {
        $db = db_connect();
        $stmt = $db->prepare("SELECT config_value FROM system_config WHERE config_key = :key LIMIT 1");
        $stmt->execute(['key' => $key]);
        $result = $stmt->fetch();
        
        return $result ? $result['config_value'] : $default;
    } catch (PDOException $e) {
        error_log('获取系统配置失败: ' . $e->getMessage());
        return $default;
    }
}