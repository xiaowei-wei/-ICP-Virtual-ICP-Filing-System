<?php
// 信息公示页面
require_once 'includes/config.php';
require_once 'includes/functions.php';

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = "status = 'approved'";
$params = [];
if (!empty($search)) {
    $where .= " AND (domain_name LIKE :search OR website_name LIKE :search OR icp_number LIKE :search OR contact_email LIKE :search OR website_desc LIKE :search OR qq_number LIKE :search)";
    $params['search'] = "%{$search}%";
}

try {
    $db = db_connect();
    $count_sql = "SELECT COUNT(*) as total FROM icp_applications WHERE {$where}";
    $stmt = $db->prepare($count_sql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $total = $stmt->fetch()['total'];
    $sql = "SELECT * FROM icp_applications WHERE {$where} ORDER BY review_time DESC LIMIT :offset, :limit";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $records = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('获取备案信息失败: ' . $e->getMessage());
    $error = '获取数据失败，请稍后再试';
}
$page_title = '备案信息公示';
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
.icp-table-box {
    width: 100%;
    max-width: 1100px;
    background: #fff;
    border-radius: 1.2rem;
    box-shadow: 0 4px 32px rgba(74,124,255,0.08);
    padding: 2.5rem 2.5rem 2.5rem 2.5rem;
    margin-bottom: 2rem;
}
.icp-table-box table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1.2rem;
}
.icp-table-box th, .icp-table-box td {
    padding: 0.7rem 0.5rem;
    border-bottom: 1px solid #f0f0f0;
    text-align: left;
    font-size: 1.08rem;
}
.icp-table-box th {
    color: #4a7cff;
    font-weight: 600;
    background: #f6f8fa;
}
.icp-table-box tr:last-child td {
    border-bottom: none;
}
.icp-table-box .icp-table-empty {
    text-align: center;
    color: #b8860b;
    padding: 1.2rem 0;
}
.icp-table-box .icp-table-info {
    color: #888;
    font-size: 0.98rem;
    margin-top: 0.8rem;
}
.icp-table-box .icp-table-search {
    margin-bottom: 1.2rem;
    display: flex;
    gap: 0.5rem;
}
.icp-table-box input[type="text"] {
    border: none;
    outline: none;
    font-size: 1.08rem;
    padding: 0.7rem 1.1rem;
    border-radius: 1.2rem;
    background: #f6f8fa;
    box-shadow: none;
    width: 260px;
}
.icp-btn {
    display: inline-block;
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
    .icp-table-box {padding: 1.2rem 0.8rem;}
    .icp-table-box input[type="text"] {width: 100%;}
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
    <div class="icp-title">备案信息公示</div>
    <div class="icp-desc">所有已通过备案的网站信息均在此公示</div>
    <div class="icp-table-box">
        <form action="public_info.php" method="get" class="icp-table-search">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="搜索域名、单位名称或备案号">
            <button class="icp-btn" type="submit">搜索</button>
        </form>
        <?php if (isset($error)): ?>
            <div class="icp-table-empty" style="color:#dc3545;"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (isset($records) && !empty($records)): ?>
            <table>
                <thead>
                    <tr>
                        <th>备案号</th>
                        <th>网站名称</th>
                        <th>网站域名</th>
                        <th>联系邮箱</th>
                        <th>网站描述</th>
                        <th>QQ号码</th>
                        <th>审核时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['icp_number']); ?></td>
                            <td><?php echo htmlspecialchars($record['website_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['domain_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['contact_email']); ?></td>
                            <td><?php echo htmlspecialchars($record['website_desc']); ?></td>
                            <td><?php echo htmlspecialchars($record['qq_number']); ?></td>
                            <td><?php echo format_datetime($record['review_time'], 'Y-m-d'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="icp-table-info">
                <?php 
                $url = 'public_info.php?page={page}';
                if (!empty($search)) {
                    $url .= '&search=' . urlencode($search);
                }
                echo pagination($total, $page, $limit, $url); 
                ?>
                <br>
                <small>共 <?php echo $total; ?> 条记录，当前显示第 <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total); ?> 条</small>
            </div>
        <?php else: ?>
            <div class="icp-table-empty">
                <?php if (!empty($search)): ?>
                    未找到符合条件的备案信息，请尝试其他搜索条件
                <?php else: ?>
                    暂无已备案的网站信息
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="icp-table-info" style="margin-top:1.5rem;">
            <ul style="margin:0;padding-left:1.2rem;color:#888;">
                <li>本页面公示所有已通过备案的网站信息</li>
                <li>信息更新可能存在延迟，请以实际备案查询结果为准</li>
                <li>如需查询特定网站的备案状态，请使用<a href="search.php" style="color:#4a7cff;">备案查询</a>功能</li>
                <li>根据相关规定，部分敏感信息已做隐私保护处理</li>
            </ul>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>