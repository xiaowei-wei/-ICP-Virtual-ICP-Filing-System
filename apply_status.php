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

$conn = db_connect();
$sql = "SELECT * FROM icp_applications ORDER BY id DESC LIMIT 1";
$result = $conn->query($sql);
$application = $result && $result->rowCount() > 0 ? $result->fetch(PDO::FETCH_ASSOC) : null;
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
.icp-status-box {
    width: 100%;
    max-width: 600px;
    background: #fff;
    border-radius: 1.2rem;
    box-shadow: 0 4px 32px rgba(74,124,255,0.08);
    padding: 2.5rem 2.5rem 2.5rem 2.5rem;
    margin-bottom: 2rem;
}
.icp-status-list {
    list-style: none;
    padding: 0;
    margin: 0 0 1.2rem 0;
}
.icp-status-list li {
    padding: 0.7rem 0.5rem;
    border-bottom: 1px solid #f0f0f0;
    font-size: 1.08rem;
    color: #333;
}
.icp-status-list li:last-child {border-bottom: none;}
.icp-badge {
    display: inline-block;
    padding: 0.2rem 1.1rem;
    border-radius: 1rem;
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
}
.icp-badge-success {background: #28a745;}
.icp-badge-danger {background: #dc3545;}
.icp-badge-warning {background: #b8860b;}
.icp-alert-info {
    background: #f6f8fa;
    color: #4a7cff;
    border-radius: 1rem;
    padding: 1rem 1.5rem;
    margin-bottom: 1.2rem;
    text-align: center;
}
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
@media (max-width: 600px) {
    .brand-bar {padding: 24px 4vw 0 4vw;}
    .icp-status-box {padding: 1.2rem 0.8rem;}
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
    <div class="icp-title">备案申请进度</div>
    <div class="icp-desc">您最近一次的备案申请进度如下</div>
    <div class="icp-status-box">
        <?php if ($application): ?>
            <ul class="icp-status-list">
                <li>网站名称：<?php echo htmlspecialchars($application['website_name']); ?></li>
                <li>域名：<?php echo htmlspecialchars($application['domain_name']); ?></li>
                <li>联系邮箱：<?php echo htmlspecialchars($application['contact_email']); ?></li>
                <li>网站描述：<?php echo htmlspecialchars($application['website_desc']); ?></li>
                <li>QQ号码：<?php echo htmlspecialchars($application['qq_number']); ?></li>
                <li>申请时间：<?php echo htmlspecialchars($application['created_at']); ?></li>
                <li>当前状态：
                    <?php if ($application['status'] === STATUS_APPROVED): ?>
                        <span class="icp-badge icp-badge-success">已通过</span>
                    <?php elseif ($application['status'] === STATUS_REJECTED): ?>
                        <span class="icp-badge icp-badge-danger">驳回</span>
                    <?php else: ?>
                        <span class="icp-badge icp-badge-warning">审核中</span>
                    <?php endif; ?>
                </li>
                <?php if ($application['status'] === STATUS_APPROVED): ?>
                    <?php if (isset($application['record_no']) && !empty($application['record_no'])): ?>
                        <li>备案号：<strong><?php echo htmlspecialchars($application['record_no']); ?></strong></li>
                    <?php endif; ?>
                    <?php if (isset($application['reviewed_at']) && !empty($application['reviewed_at'])): ?>
                        <li>审核时间：<?php echo htmlspecialchars($application['reviewed_at']); ?></li>
                    <?php endif; ?>
                <?php elseif ($application['status'] === STATUS_REJECTED): ?>
                    <li style="color:#dc3545;">驳回原因：<?php echo htmlspecialchars($application['reject_reason']); ?></li>
                <?php endif; ?>
            </ul>
        <?php else: ?>
            <div class="icp-alert-info">暂无申请记录。</div>
        <?php endif; ?>
        <a href="apply.php" class="icp-btn">发起新申请</a>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>