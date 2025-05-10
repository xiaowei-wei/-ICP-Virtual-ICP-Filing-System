<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
$page_title = '关于备案';
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

// 动态获取about内容
$about_content = '';
try {
    $db = db_connect();
    $stmt = $db->prepare("SELECT content FROM announcements WHERE type = 'about' AND status = 1 ORDER BY updated_at DESC, created_at DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && !empty($result['content'])) {
        $about_content = $result['content'];
    } else {
        $about_content = '<div class="alert alert-info">暂无相关内容</div>';
    }
} catch (PDOException $e) {
    $about_content = '<div style="color:red;">关于内容加载失败，请稍后重试。</div>';
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
    font-size: 2.3rem;
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
.icp-content-box {
    width: 100%;
    max-width: 820px;
    background: #fff;
    border-radius: 2.2rem;
    box-shadow: 0 8px 32px 0 rgba(74,124,255,0.13), 0 1.5px 8px 0 rgba(74,124,255,0.06);
    padding: 2.8rem 2.8rem 2.5rem 2.8rem;
    margin-bottom: 2.5rem;
    position: relative;
    overflow: hidden;
    border: 1.5px solid #f0f4fa;
    transition: box-shadow .25s, border-color .25s;
}
.icp-content-box::before {
    content: "";
    position: absolute;
    left: 0; top: 0; right: 0; height: 60px;
    background: linear-gradient(90deg,#e3eaff 0%,#f6f8fa 100%);
    opacity: 0.18;
    z-index: 0;
    border-top-left-radius: 2.2rem;
    border-top-right-radius: 2.2rem;
}
.icp-content-box > * {
    position: relative;
    z-index: 1;
}
@media (max-width: 600px) {
    .brand-bar {padding: 24px 4vw 0 4vw;}
    .icp-content-box {padding: 1.2rem 0.8rem; border-radius: 1.2rem;}
    .icp-content-box::before {height: 36px; border-top-left-radius: 1.2rem; border-top-right-radius: 1.2rem;}
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
    <div class="icp-title">关于ICP备案</div>
    <div class="icp-desc">了解什么是ICP备案及相关流程与常见问题</div>
    <div class="icp-content-box">
        <?php echo $about_content; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>