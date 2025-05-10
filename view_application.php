<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// 获取品牌LOGO文字
$site_brand_logo = '默认LOGO'; // 默认值
try {
    $db_logo = db_connect();
    $stmt_logo = $db_logo->prepare("SELECT config_value FROM system_config WHERE config_key = 'brand_logo_text' LIMIT 1");
    $stmt_logo->execute();
    $logo_result = $stmt_logo->fetch(PDO::FETCH_ASSOC);
    if ($logo_result && !empty($logo_result['config_value'])) {
        $site_brand_logo = $logo_result['config_value'];
    }
} catch (PDOException $e_logo) {
    // 日志记录错误，但继续使用默认LOGO
    error_log('Failed to fetch brand_logo_text: ' . $e_logo->getMessage());
}


$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$application = null;
$error = '';

if ($id > 0) {
    try {
        $db = db_connect();
        $stmt = $db->prepare("SELECT * FROM icp_applications WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$application) {
            $error = '未找到对应的备案申请。';
        }
    } catch (PDOException $e) {
        error_log('加载备案申请详情失败: ' . $e->getMessage());
        $error = '加载详情失败，请稍后再试。';
    }
} else {
    $error = '参数错误，缺少申请ID。';
}
?>
<style>
body {
    min-height: 100vh;
    background: url('static/bg.jpg') center center/cover no-repeat fixed, #f6f8fa;
}
.bg-blur {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    z-index: 0;
    background: rgba(255,255,255,0.6);
    backdrop-filter: blur(2px);
}
.brand-bar {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 32px 8vw 0 8vw;
}
.brand-logo {
    font-size: 2.2rem;
    font-weight: bold;
    color: #4a7cff;
    letter-spacing: 1px;
    text-shadow: 0 2px 8px rgba(74,124,255,0.08);
}
.brand-nav {
    display: flex;
    gap: 2.5rem;
    font-size: 1.1rem;
}
.brand-nav a {
    color: #333;
    text-decoration: none;
    transition: color .2s;
}
.brand-nav a:hover {
    color: #4a7cff;
}
.center-content {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 70vh;
}
.icp-title {
    font-size: 2.1rem;
    font-weight: 700;
    color: #4a7cff;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 12px rgba(74,124,255,0.08);
}
.icp-desc {
    font-size: 1.1rem;
    color: #444;
    margin-bottom: 2.5rem;
    letter-spacing: 1px;
}
.icp-detail-box {
    width: 100%;
    max-width: 600px;
    background: #fff;
    border-radius: 1.2rem;
    box-shadow: 0 4px 32px rgba(74,124,255,0.08);
    padding: 2.5rem 2.5rem 2.5rem 2.5rem;
    margin-bottom: 2rem;
}
.icp-detail-list {
    list-style: none;
    padding: 0;
    margin: 0 0 1.2rem 0;
}
.icp-detail-list li {
    padding: 0.7rem 0.5rem;
    border-bottom: 1px solid #f0f0f0;
    font-size: 1.08rem;
    color: #333;
}
.icp-detail-list li:last-child {border-bottom: none;}
.icp-btn {
    display: inline-block;
    margin: 1.2rem 0.5rem 0 0.5rem;
    padding: 0.7rem 2.2rem;
    border-radius: 2rem;
    border: none;
    background: linear-gradient(90deg,#4a7cff 0%,#6ec6ff 100%);
    color: #fff;
    font-size: 1.1rem;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(74,124,255,0.08);
    transition: background .2s;
    text-decoration: none;
}
.icp-btn:hover {
    background: linear-gradient(90deg,#6ec6ff 0%,#4a7cff 100%);
    color: #fff;
}
.icp-alert-danger {
    background: #fff0f0;
    color: #dc3545;
    border-radius: 1rem;
    padding: 1rem 1.5rem;
    margin-bottom: 1.2rem;
    text-align: center;
}
@media (max-width: 600px) {
    .brand-bar {padding: 24px 4vw 0 4vw;}
    .icp-detail-box {padding: 1.2rem 0.8rem;}
}
</style>
<div class="bg-blur"></div>
<div class="brand-bar">
    <div class="brand-logo"><?php echo htmlspecialchars($site_brand_logo); ?></div>
    <nav class="brand-nav">
        <a href="index.php">主页</a>
        <a href="about.php">关于</a>
        <a href="apply.php">加入</a>
        <a href="public_info.php">公示</a>
        <a href="apply_status.php">备案申请进度</a>
        
    </nav>
</div>
<div class="center-content">
    <div class="icp-title">备案申请详情</div>
    <div class="icp-desc">查看备案申请的详细信息</div>
    <div class="icp-detail-box">
        <?php if ($error): ?>
            <div class="icp-alert-danger"><?php echo $error; ?></div>
        <?php elseif ($application): ?>
            <ul class="icp-detail-list">
                <li><strong>申请编号：</strong><?php echo htmlspecialchars($application['application_number']); ?></li>
                <li><strong>网站名称：</strong><?php echo htmlspecialchars($application['website_name']); ?></li>
                <li><strong>域名：</strong><?php echo htmlspecialchars($application['domain_name']); ?></li>
                <li><strong>申请时间：</strong><?php echo htmlspecialchars($application['created_at']); ?></li>
                <li><strong>状态：</strong><?php echo htmlspecialchars($application['status']); ?></li>
                <li><strong>描述：</strong><?php echo htmlspecialchars($application['website_desc']); ?></li>
            </ul>
            <a href="admin/applications.php" class="icp-btn" style="background:#f6f8fa;color:#4a7cff;">返回列表</a>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>