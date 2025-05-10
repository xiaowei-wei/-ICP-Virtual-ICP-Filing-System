<?php
// 主配置文件
// 包含数据库配置
require_once __DIR__ . '/database.php';

// 站点名称
define('SITE_NAME', '虚拟备案系统');

// 错误报告级别 (生产环境建议设置为0)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// Session 设置
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 备案状态常量
define('STATUS_PENDING', 'pending');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');
