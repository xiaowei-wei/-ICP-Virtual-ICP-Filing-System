<?php
// 头部模板文件
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php
// 获取SEO设置
$global_seo_title = SITE_NAME; // Default site name
$global_meta_description = '';
$global_meta_keywords = '';

try {
    // 确保 db_connect 和 SITE_NAME (如果来自config.php) 在此作用域可用
    // 如果 config.php 已经在父文件加载，则无需再次 require_once
    // 但如果 header.php 可能被独立包含，则需要确保依赖可用
    if (!function_exists('db_connect')) {
        // 这个路径假设 header.php 在 includes/ 目录下，而 config.php 在项目根目录的 includes/ 下
        // 如果 header.php 在项目根目录，路径应为 'includes/config.php'
        // 如果 header.php 在 includes/ 目录，config.php 也在 includes/，路径应为 'config.php'
        // 根据实际文件结构调整
        if (file_exists(__DIR__ . '/config.php')) { // 如果config.php和header.php在同一个includes目录
            require_once __DIR__ . '/config.php'; 
        } elseif (file_exists(__DIR__ . '/../includes/config.php')) { // 如果header.php在项目根目录，config.php在includes/
             require_once __DIR__ . '/../includes/config.php';
        } elseif (file_exists(dirname(__DIR__) . '/includes/config.php')) { // 如果header.php在类似 admin/includes/ 目录下
            require_once dirname(__DIR__) . '/includes/config.php';
        }
        // 如果以上都不匹配，可能需要更复杂的路径逻辑或确保config.php总是先被加载
    }
    
    $db_seo = db_connect();
    $seo_keys = ['site_meta_title', 'site_meta_description', 'site_meta_keywords'];
    $placeholders = implode(',', array_fill(0, count($seo_keys), '?'));
    $stmt_seo = $db_seo->prepare("SELECT config_key, config_value FROM system_config WHERE config_key IN ($placeholders)");
    $stmt_seo->execute($seo_keys);
    $fetched_seo_configs = $stmt_seo->fetchAll(PDO::FETCH_KEY_PAIR);

    if (!empty($fetched_seo_configs['site_meta_title'])) {
        $global_seo_title = htmlspecialchars($fetched_seo_configs['site_meta_title']);
    }
    if (!empty($fetched_seo_configs['site_meta_description'])) {
        $global_meta_description = htmlspecialchars($fetched_seo_configs['site_meta_description']);
    }
    if (!empty($fetched_seo_configs['site_meta_keywords'])) {
        $global_meta_keywords = htmlspecialchars($fetched_seo_configs['site_meta_keywords']);
    }
} catch (PDOException $e) {
    error_log('获取全局SEO配置失败 (header.php): ' . $e->getMessage());
    // 如果获取失败，将使用默认值
}

// 决定最终标题
$final_title = isset($page_title) && !empty(trim($page_title)) ? htmlspecialchars(trim($page_title)) . ' - ' . $global_seo_title : $global_seo_title;
?>
    <title><?php echo $final_title; ?></title>
    <?php if (!empty($global_meta_description)): ?>
    <meta name="description" content="<?php echo $global_meta_description; ?>">
    <?php endif; ?>
    <?php if (!empty($global_meta_keywords)): ?>
    <meta name="keywords" content="<?php echo $global_meta_keywords; ?>">
    <?php endif; ?>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <!-- 自定义样式 -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- 导航栏已移除，使用页面自定义导航 -->