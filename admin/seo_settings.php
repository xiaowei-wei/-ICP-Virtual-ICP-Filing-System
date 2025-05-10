<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
// 检查管理员是否已登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$page_title = 'SEO设置';
$success_message = '';
$error_message = '';
// 定义SEO相关的配置键
$seo_config_keys = [
    'site_meta_title' => '网站全局标题 (SEO)',
    'site_meta_description' => '网站全局描述 (SEO)',
    'site_meta_keywords' => '网站全局关键词 (SEO)',
];
// 初始化SEO设置数组
$seo_settings = [];
foreach (array_keys($seo_config_keys) as $key) {
    $seo_settings[$key] = '';
}
// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_seo_settings'])) {
        try {
            $db = db_connect();
            $db->beginTransaction();
            foreach ($seo_config_keys as $key => $label) {
                $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
                // 更新或插入配置项
                $stmt_check = $db->prepare("SELECT config_value FROM system_config WHERE config_key = :config_key");
                $stmt_check->execute([':config_key' => $key]);
                if ($stmt_check->fetch()) {
                    $stmt_update = $db->prepare("UPDATE system_config SET config_value = :config_value WHERE config_key = :config_key");
                    $stmt_update->execute([':config_value' => $value, ':config_key' => $key]);
                } else {
                    $stmt_insert = $db->prepare("INSERT INTO system_config (config_key, config_value) VALUES (:config_key, :config_value)");
                    $stmt_insert->execute([':config_key' => $key, ':config_value' => $value]);
                }
                $seo_settings[$key] = $value; // 更新页面显示的值
            }
            $db->commit();
            $success_message = 'SEO设置已成功更新！';
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('SEO设置更新失败: ' . $e->getMessage());
            $error_message = 'SEO设置更新失败，请稍后再试。错误详情：' . htmlspecialchars($e->getMessage());
        }
    }
}
// 加载当前的SEO设置
try {
    $db = db_connect();
    $placeholders = implode(',', array_fill(0, count($seo_config_keys), '?'));
    $stmt = $db->prepare("SELECT config_key, config_value FROM system_config WHERE config_key IN ($placeholders)");
    $stmt->execute(array_keys($seo_config_keys));
    $current_configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($seo_config_keys as $key => $label) {
        if (isset($current_configs[$key])) {
            $seo_settings[$key] = htmlspecialchars($current_configs[$key]);
        }
    }
} catch (PDOException $e) {
    error_log('加载SEO设置失败: ' . $e->getMessage());
    $error_message = '加载当前SEO设置失败，请稍后再试。';
    // 如果加载失败，表单将显示空值或默认值
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <!-- 自定义样式 -->
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- 侧边栏 -->
            <?php include __DIR__ . '/../includes/sidebar.php'; ?>
            <!-- 主内容区域 -->
            <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $page_title; ?></h1>
                </div>
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                <p class="mb-4">在这里配置网站的全局SEO信息，这些信息将帮助搜索引擎更好地理解和索引您的网站内容。</p>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">SEO设置</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="seo_settings.php">
                            <?php foreach ($seo_config_keys as $key => $label): ?>
                            <div class="mb-3">
                                <label for="<?php echo $key; ?>" class="form-label"><strong><?php echo htmlspecialchars($label); ?></strong></label>
                                <?php if ($key === 'site_meta_description'): ?>
                                    <textarea class="form-control" id="<?php echo $key; ?>" name="<?php echo $key; ?>" rows="3"><?php echo $seo_settings[$key]; ?></textarea>
                                    <small class="form-text text-muted">建议长度在150-160字符之间。</small>
                                <?php elseif ($key === 'site_meta_keywords'): ?>
                                    <input type="text" class="form-control" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo $seo_settings[$key]; ?>">
                                    <small class="form-text text-muted">多个关键词请用英文逗号 (,) 分隔。</small>
                                <?php else: ?>
                                    <input type="text" class="form-control" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo $seo_settings[$key]; ?>">
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <button type="submit" name="save_seo_settings" class="btn btn-primary">保存设置</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <!-- Bootstrap JS 和依赖 -->
    <script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>