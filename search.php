<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
$keyword = '';
$search_result = null;
$error = '';
if (isset($_GET['keyword']) && !empty($_GET['keyword'])) {
    $keyword = trim($_GET['keyword']);
    try {
        $db = db_connect();
        $stmt = $db->prepare("SELECT * FROM icp_applications WHERE domain_name LIKE :keyword1 OR icp_number LIKE :keyword2 LIMIT 1");
        $stmt->execute(['keyword1' => "%{$keyword}%", 'keyword2' => "%{$keyword}%"]);
        $search_result = $stmt->fetch();
        if (!$search_result) {
            $error = '未找到相关备案信息，请确认输入是否正确';
        }
    } catch (PDOException $e) {
        error_log('备案查询失败: ' . $e->getMessage());
        $error = '查询失败，请稍后再试<br>错误信息：' . htmlspecialchars($e->getMessage());
    }
}
$page_title = '备案查询';

// 获取公告
$latest_announcements = [];
try {
    $db_ann = db_connect();
    $stmt_ann = $db_ann->prepare("SELECT title, content, created_at FROM announcements WHERE type = 'normal' AND status = 1 ORDER BY is_top DESC, created_at DESC LIMIT 3");
    $stmt_ann->execute();
    $latest_announcements = $stmt_ann->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('获取公告失败: ' . $e->getMessage());
    // 不中断页面，允许用户继续查询，但记录错误
}

// 获取系统配置项
$site_brand_logo = '维ICP'; // Default
$site_icp_title = '维ICP备案中心'; // Default
$site_icp_description = '安全 · 可靠 · 赋能你的二次元虚拟备案'; // Default

try {
    // 注意：如果 db_connect() 已经在前面被调用过并且 $db 或 $db_ann 仍然可用，
    // 理论上可以复用连接。但为了代码块的独立性和清晰性，这里重新连接。
    // 在实际项目中，应考虑数据库连接的复用策略。
    $db_sys_config = db_connect(); 
    $stmt_sys_config = $db_sys_config->query("SELECT config_key, config_value FROM system_config WHERE config_key IN ('brand_logo_text', 'icp_title', 'icp_description')");
    $fetched_configs = $stmt_sys_config->fetchAll(PDO::FETCH_KEY_PAIR);

    if (!empty($fetched_configs['brand_logo_text'])) {
        $site_brand_logo = htmlspecialchars($fetched_configs['brand_logo_text']);
    }
    if (!empty($fetched_configs['icp_title'])) {
        $site_icp_title = htmlspecialchars($fetched_configs['icp_title']);
    }
    if (!empty($fetched_configs['icp_description'])) {
        $site_icp_description = htmlspecialchars($fetched_configs['icp_description']);
    }
} catch (PDOException $e) {
    error_log('获取系统配置项失败 (search.php): ' . $e->getMessage());
    // 如果获取失败，将使用上面定义的默认值
}

require_once 'includes/header.php';
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
.icp-search-box {
    width: 100%;
    max-width: 600px;
    background: #fff;
    border-radius: 1.5rem;
    box-shadow: 0 4px 32px rgba(74,124,255,0.08);
    padding: 2rem 2.5rem 2.5rem 2.5rem;
    margin-bottom: 2rem;
}
.icp-search-form {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.icp-search-form input[type="text"] {
    flex: 1;
    border: none;
    outline: none;
    font-size: 1.15rem;
    padding: 0.9rem 1.2rem;
    border-radius: 2rem;
    background: #f6f8fa;
    box-shadow: none;
}
.icp-search-form button {
    padding: 0.9rem 2.2rem;
    border-radius: 2rem;
    border: none;
    background: linear-gradient(90deg,#4a7cff 0%,#6ec6ff 100%);
    color: #fff;
    font-size: 1.1rem;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(74,124,255,0.08);
    transition: background .2s;
}
.icp-search-form button:hover {
    background: linear-gradient(90deg,#6ec6ff 0%,#4a7cff 100%);
}
.icp-search-tip {
    color: #888;
    font-size: 0.98rem;
    margin-top: 0.5rem;
    margin-left: 0.2rem;
}
.icp-result-box {
    width: 100%;
    max-width: 600px;
    background: #fff;
    border-radius: 1.2rem;
    box-shadow: 0 4px 32px rgba(74,124,255,0.08);
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
}
.icp-result-table th {
    width: 32%;
    background: #f6f8fa;
    font-weight: 500;
}
.icp-result-table td {
    color: #333;
}
.icp-alert {
    margin: 1.5rem 0 0.5rem 0;
    padding: 1rem 1.5rem;
    border-radius: 1rem;
    background: #fffbe6;
    color: #b8860b;
    font-size: 1.05rem;
    box-shadow: 0 2px 8px rgba(255,215,0,0.06);
}
@media (max-width: 600px) {
    .brand-bar {padding: 24px 4vw 0 4vw;}
    .icp-search-box, .icp-result-box {padding: 1.2rem 0.8rem;}
}
</style>
<div class="bg-blur"></div>
<div class="brand-bar">
      <div class="brand-logo"><?php echo $site_brand_logo; ?></div>
    <nav class="brand-nav">
        <a href="index.php">主页</a>
        <a href="about.php">关于</a>
        <a href="apply.php">加入</a>
        <a href="public_info.php">公示</a>
        <a href="apply_status.php">备案申请进度</a>
       
    </nav>
</div>
<div class="center-content">
    <div class="icp-title"><?php echo $site_icp_title; ?></div>
    <div class="icp-desc"><?php echo $site_icp_description; ?></div>
    <div class="icp-search-box">
        <form class="icp-search-form" action="search.php" method="get">
            <input type="text" name="keyword" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="请输入备案号 or 域名" required>
            <button type="submit">立即查询</button>
        </form>
        <div class="icp-search-tip">可输入完整或部分备案号/域名进行查询</div>
        <?php if ($error): ?>
            <div class="icp-alert"><?php echo $error; ?></div>
        <?php endif; ?>
    </div>

    <?php if (!empty($latest_announcements)): ?>
    <div class="icp-announcements-box" style="width: 100%; max-width: 600px; background: #fff; border-radius: 1.2rem; box-shadow: 0 4px 32px rgba(74,124,255,0.08); padding: 1.5rem 2rem; margin-bottom: 2rem;">
        <h5 style="font-weight:600;color:#4a7cff;margin-bottom:1rem;">最新公告</h5>
        <?php foreach ($latest_announcements as $announcement): ?>
            <div class="announcement-item" style="margin-bottom: 1rem; padding-bottom: 0.8rem; border-bottom: 1px solid #eee;">
                <h6 style="font-weight: bold; margin-bottom: 0.3rem;"><?php echo htmlspecialchars($announcement['title']); ?> <small style="color:#888; font-weight:normal;">(<?php echo date('Y-m-d', strtotime($announcement['created_at'])); ?>)</small></h6>
                <div style="font-size:0.95rem; color:#555; line-height:1.6;"><?php echo nl2br(mb_substr($announcement['content'], 0, 150) . (mb_strlen($announcement['content']) > 150 ? '...' : '')); ?></div>
            </div>
        <?php endforeach; ?>
        <style>
        .announcement-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        </style>
    </div>
    <?php endif; ?>

    <?php if ($search_result): ?>
    <div class="icp-result-box">
        <h5 style="font-weight:600;color:#4a7cff;margin-bottom:1.2rem;">查询结果</h5>
        <div class="table-responsive">
            <table class="table icp-result-table">
                <tr>
                    <th>网站名称</th>
                    <td><?php echo isset($search_result['website_name']) ? htmlspecialchars($search_result['website_name']) : '—'; ?></td>
                </tr>
                <tr>
                    <th>网站域名</th>
                    <td><?php echo isset($search_result['domain_name']) ? '<a href="http://' . htmlspecialchars($search_result['domain_name']) . '" target="_blank" style="text-decoration: underline;">' . htmlspecialchars($search_result['domain_name']) . '</a>' : '—'; ?></td>
                </tr>
                <tr>
                    <th>网站描述</th>
                    <td><?php echo isset($search_result['website_desc']) ? htmlspecialchars($search_result['website_desc']) : '—'; ?></td>
                </tr>
                <tr>
                    <th>QQ号码</th>
                    <td><?php echo isset($search_result['qq_number']) ? htmlspecialchars($search_result['qq_number']) : '—'; ?></td>
                </tr>
                <tr>
                    <th>备案号</th>
                    <td>
                        <?php if (isset($search_result['status']) && $search_result['status'] == STATUS_APPROVED && isset($search_result['icp_number']) && $search_result['icp_number']): ?>
                            <span style="color:#28a745;font-weight:600;"><?php echo htmlspecialchars($search_result['icp_number']); ?></span>
                        <?php else: ?>
                            <span style="color:#888;">暂无备案号</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>备案状态</th>
                    <td>
                        <?php if (isset($search_result['status']) && $search_result['status'] == STATUS_PENDING): ?>
                            <span style="color:#b8860b;font-weight:600;">审核中</span>
                        <?php elseif (isset($search_result['status']) && $search_result['status'] == STATUS_APPROVED): ?>
                            <span style="color:#28a745;font-weight:600;">已备案</span>
                        <?php elseif (isset($search_result['status']) && $search_result['status'] == STATUS_REJECTED): ?>
                            <span style="color:#dc3545;font-weight:600;">未通过</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (isset($search_result['status']) && $search_result['status'] == STATUS_APPROVED): ?>
                <tr>
                    <th>审核通过时间</th>
                    <td><?php echo isset($search_result['review_time']) ? format_datetime($search_result['review_time'], 'Y-m-d') : '—'; ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        </div>
        <?php if (isset($search_result['status']) && $search_result['status'] == STATUS_APPROVED && isset($search_result['icp_number']) && !empty($search_result['icp_number'])): ?>
        <div class="icp-hang-code-box" style="margin-top: 1.5rem; padding: 1rem; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 0.5rem; text-align: left;">
            <h6 style="font-weight:600; color:#333; margin-bottom:0.8rem;">备案号悬挂代码：</h6>
            <p style="font-size:0.9rem; color:#555; margin-bottom:0.5rem;">请将以下代码复制并添加到您的网站底部：</p>
            <pre style="background-color: #e9ecef; padding: 0.8rem; border-radius: 0.3rem; font-size:0.95rem; white-space: pre-wrap; word-break: break-all; text-align: left;"><code><?php echo htmlspecialchars('<a href="https://beian.miit.gov.cn/" target="_blank">' . $search_result['icp_number'] . '</a>'); ?></code></pre>
            <button onclick="copyToClipboard(this, '<?php echo htmlspecialchars(addslashes($search_result['icp_number'])); ?>')" style="padding: 0.5rem 1rem; border-radius: 0.3rem; border: none; background: #4a7cff; color: #fff; font-size: 0.9rem; cursor: pointer; margin-top:0.8rem;">复制备案号</button>
        </div>
        <script>
        function copyToClipboard(button, icpNumber) {
            const textToCopy = '<a href="https://beian.miit.gov.cn/" target="_blank">' + icpNumber + '</a>';
            navigator.clipboard.writeText(textToCopy).then(function() {
                button.innerText = '已复制!';
                setTimeout(function() {
                    button.innerText = '复制备案号';
                }, 2000);
            }, function(err) {
                alert('复制失败，请手动复制。');
                console.error('无法复制文本: ', err);
            });
        }
        </script>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php require_once 'includes/footer.php'; ?>