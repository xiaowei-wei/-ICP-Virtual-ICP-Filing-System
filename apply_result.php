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

$success = isset($_GET['success']) && $_GET['success'] == '1';
$app_number = isset($_GET['app_number']) ? htmlspecialchars($_GET['app_number']) : '';
$is_auto = isset($_GET['auto']) && $_GET['auto'] == '1';
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
.icp-result-box {
    width: 100%;
    max-width: 500px;
    background: #fff;
    border-radius: 1.2rem;
    box-shadow: 0 4px 32px rgba(74,124,255,0.08);
    padding: 2.5rem 2.5rem 2.5rem 2.5rem;
    margin-bottom: 2rem;
    text-align: center;
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
.icp-alert-success {
    background: #e6fff2;
    color: #28a745;
    border-radius: 1rem;
    padding: 1rem 1.5rem;
    margin-bottom: 1.2rem;
}
.icp-alert-danger {
    background: #fff0f0;
    color: #dc3545;
    border-radius: 1rem;
    padding: 1rem 1.5rem;
    margin-bottom: 1.2rem;
}
@media (max-width: 600px) {
    .brand-bar {padding: 24px 4vw 0 4vw;}
    .icp-result-box {padding: 1.2rem 0.8rem;}
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
    <div class="icp-title">备案申请结果</div>
    <div class="icp-desc">感谢您的提交，以下是申请反馈</div>
    <div class="icp-result-box">
        <?php if ($success): ?>
            <div class="icp-alert-success">您的备案申请已提交成功，请耐心等待审核！</div>
            <?php if(!empty($app_number)): ?>
            <div style="margin-top: 1rem; padding: 1rem; background-color: #f0f8ff; border-radius: 0.5rem; color: #4a7cff; font-weight: bold;">
                您的备案号：<?php echo $app_number; ?>
                <?php if($is_auto): ?><br><small>(系统自动分配)</small><?php endif; ?>
            </div>
            <?php endif; ?>
            <a href="apply_status.php" class="icp-btn">查看申请进度</a>
        <?php else:
            $error_message = "备案申请提交失败，请检查信息后重试。"; // 默认消息
            if (isset($_GET['error'])) {
                switch ($_GET['error']) {
                    case 'invalid_input':
                        $error_message = "输入信息无效，请检查所有字段并重试。";
                        break;
                    case 'db_insert_failed':
                        $error_message = "数据库操作失败，无法保存您的申请，请稍后重试。";
                        break;
                    case 'db_exception':
                        $error_message = "数据库连接或查询时发生错误，请联系管理员或稍后重试。";
                        break;
                }
            }
        ?>
            <div class="icp-alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <a href="apply.php" class="icp-btn" style="background:#f6f8fa;color:#4a7cff;">返回重新申请</a>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>