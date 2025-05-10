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


// 域名状态提示逻辑
$domain_status_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['domain_name'])) {
    $domain_name = trim($_POST['domain_name']);
    try {
        $db = db_connect();
        $stmt = $db->prepare("SELECT status FROM icp_applications WHERE domain_name = :domain_name ORDER BY id DESC LIMIT 1");
        $stmt->execute(['domain_name' => $domain_name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if ($row['status'] == STATUS_APPROVED) {
                $domain_status_msg = '<div class="icp-alert-info" style="color:#28a745;">该域名已通过审核</div>';
            } else {
                $domain_status_msg = '<div class="icp-alert-info" style="color:#b8860b;">审核中</div>';
            }
        }
    } catch (PDOException $e) {
        $domain_status_msg = '<div class="icp-alert-info" style="color:#dc3545;">状态查询失败，请稍后再试</div>';
    }
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
.icp-form-box {
    width: 100%;
    max-width: 500px;
    background: #fff;
    border-radius: 1.2rem;
    box-shadow: 0 4px 32px rgba(74,124,255,0.08);
    padding: 2.5rem 2.5rem 2.5rem 2.5rem;
    margin-bottom: 2rem;
}
.icp-form-box label {
    font-weight: 500;
    color: #4a7cff;
    margin-bottom: 0.3rem;
}
.icp-form-box input, .icp-form-box select, .icp-form-box textarea {
    width: 100%;
    border: none;
    outline: none;
    font-size: 1.08rem;
    padding: 0.8rem 1.1rem;
    border-radius: 1.2rem;
    background: #f6f8fa;
    margin-bottom: 1.1rem;
    box-shadow: none;
}
.icp-form-box textarea {resize: vertical;}
.icp-btn {
    display: inline-block;
    width: 100%;
    padding: 0.9rem 0;
    border-radius: 2rem;
    border: none;
    background: linear-gradient(90deg,#4a7cff 0%,#6ec6ff 100%);
    color: #fff;
    font-size: 1.1rem;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(74,124,255,0.08);
    transition: background .2s;
    margin-top: 0.5rem;
}
.icp-btn:hover {
    background: linear-gradient(90deg,#6ec6ff 0%,#4a7cff 100%);
    color: #fff;
}
.icp-alert-info {
    background: #f6f8fa;
    color: #4a7cff;
    border-radius: 1rem;
    padding: 1rem 1.5rem;
    margin-top: 1.2rem;
    text-align: center;
    font-size: 1rem;
}
@media (max-width: 600px) {
    .brand-bar {padding: 24px 4vw 0 4vw;}
    .icp-form-box {padding: 1.2rem 0.8rem;}
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
    <div class="icp-title">备案申请流程</div>
    <div class="icp-desc">请填写以下信息，提交您的备案申请</div>
    <?php if (!empty($domain_status_msg)) echo $domain_status_msg; ?>
    <div class="icp-form-box">
        <form id="applyForm" method="post" action="apply.php">
            <label>备案类型</label>
            <select name="company_type" required>
                <option value="">请选择</option>
                <option value="个人">个人备案</option>
                <option value="企业">企业备案</option>
            </select>
            <label>网站名称</label>
            <input type="text" name="website_name" placeholder="请输入网站名称" required>
            <label>域名</label>
            <input type="text" name="domain_name" placeholder="请输入域名" required>
            <label>联系邮箱</label>
            <input type="email" name="contact_email" placeholder="请输入联系邮箱" required>
            <label>网站描述</label>
            <textarea name="website_desc" rows="3" placeholder="请输入网站描述" required></textarea>
            <label>QQ号码</label>
            <input type="text" name="qq_number" placeholder="请输入QQ号码" required>
            <button type="submit" class="icp-btn">提交申请</button>
        </form>
        <div class="icp-alert-info">
            提交后请耐心等待审核，您可在“查看申请进度”中查询结果。
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>