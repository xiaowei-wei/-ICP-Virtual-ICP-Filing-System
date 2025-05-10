<?php
// 管理员仪表盘页面
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'];
try {
    $db = db_connect();
    $stmt = $db->query("SELECT COUNT(*) as total FROM icp_applications");
    $total_applications = $stmt->fetch()['total'];
    $stmt = $db->query("SELECT COUNT(*) as pending FROM icp_applications WHERE status = 'pending'");
    $pending_applications = $stmt->fetch()['pending'];
    $stmt = $db->query("SELECT COUNT(*) as approved FROM icp_applications WHERE status = 'approved'");
    $approved_applications = $stmt->fetch()['approved'];
    $stmt = $db->query("SELECT COUNT(*) as rejected FROM icp_applications WHERE status = 'rejected'");
    $rejected_applications = $stmt->fetch()['rejected'];
    $stmt = $db->query("SELECT * FROM icp_applications ORDER BY created_at DESC LIMIT 5");
    $recent_applications = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('获取统计数据失败: ' . $e->getMessage());
    $error = '获取数据失败，请稍后再试';
}
$page_title = '管理仪表盘';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        body.dashboard-minimal {
            background: linear-gradient(135deg, #fff 0%, #f5f6fa 100%);
        }
        .glass-card {
            background: rgba(255,255,255,0.82);
            box-shadow: 0 4px 32px 0 rgba(31,38,135,0.10), 0 1.5px 0.5px 0 #e0e0e0;
            border-radius: 18px;
            backdrop-filter: blur(14px) saturate(1.1);
            border: 1px solid rgba(200,200,200,0.13);
            margin-bottom: 24px;
        }
        .dashboard-divider {
            border-top: 1px solid #ececec;
            box-shadow: 0 0.5px 0 #e0e0e0;
            margin: 32px 0 24px 0;
        }
        .stat-number {
            font-size: 2.4rem;
            font-weight: bold;
            color: #23272f;
            font-family: 'Noto Sans SC', 'Microsoft YaHei', Arial, sans-serif;
            letter-spacing: 1px;
        }
        .stat-title {
            color: #888;
            font-size: 1.08rem;
            margin-top: 0.5rem;
        }
        .stat-highlight {
            color: #ff9800;
            background: rgba(255,152,0,0.08);
            border-radius: 8px;
            padding: 0.2em 0.7em;
            font-weight: 700;
        }
        .dashboard-params-panel {
            position: fixed;
            left: 32px;
            top: 80px;
            width: 220px;
            background: rgba(255,255,255,0.72);
            box-shadow: 0 8px 32px 0 rgba(31,38,135,0.10);
            border-radius: 18px;
            backdrop-filter: blur(12px) saturate(1.1);
            z-index: 200;
            padding: 24px 18px 18px 18px;
            border: 1px solid rgba(200,200,200,0.13);
        }
        .dashboard-params-panel h6 {
            font-weight: 700;
            color: #444;
            margin-bottom: 1.2em;
        }
        .dashboard-params-panel label {
            font-size: 0.98rem;
            color: #666;
        }
        .dashboard-params-panel input[type=range] {
            width: 100%;
        }
        .dashboard-main {
            margin-left: 260px;
            padding-top: 32px;
        }
        .dashboard-charts {
            display: flex;
            gap: 32px;
            margin-bottom: 32px;
        }
        .dashboard-chart-block {
            flex: 1;
            background: rgba(255,255,255,0.92);
            border-radius: 16px;
            box-shadow: 0 2px 12px 0 rgba(31,38,135,0.07);
            padding: 24px 18px 18px 18px;
            position: relative;
            transition: box-shadow 0.2s;
        }
        .dashboard-chart-block:hover {
            box-shadow: 0 4px 24px 0 #b3e5fc55, 0 2px 8px 0 #ffd18055;
        }
        .dashboard-btn-group {
            display: flex;
            justify-content: flex-end;
            gap: 18px;
            background: rgba(245,245,245,0.82);
            border-radius: 12px;
            box-shadow: 0 1.5px 8px 0 rgba(31,38,135,0.06);
            padding: 12px 24px;
            margin-top: 32px;
            margin-bottom: 12px;
        }
        .dashboard-btn-group .btn {
            background: rgba(255,255,255,0.7);
            border: 1px solid #e0e0e0;
            color: #444;
            font-weight: 600;
            border-radius: 8px;
            transition: background 0.2s, box-shadow 0.2s;
        }
        .dashboard-btn-group .btn:hover {
            background: #e3f2fd;
            color: #1976d2;
            box-shadow: 0 0 8px #90caf9;
        }
        @media (max-width: 991px) {
            .dashboard-main { margin-left: 0; }
            .dashboard-params-panel { display: none; }
            .dashboard-charts { flex-direction: column; gap: 18px; }
        }
    </style>
</head>
<body class="dashboard-minimal">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="dashboard-main">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <h2 class="mb-4" style="font-weight:700;color:#23272f;">数据可视化仪表盘</h2>
                </div>
            </div>
            <div class="dashboard-charts">
                <div class="dashboard-chart-block">
                    <h5 style="font-weight:600;">备案趋势折线图</h5>
                    <canvas id="lineChart"></canvas>
                </div>
                <div class="dashboard-chart-block">
                    <h5 style="font-weight:600;">状态分布饼图</h5>
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
            <div class="dashboard-divider"></div>
            <div class="row">
                <div class="col-md-3">
                    <div class="glass-card text-center py-4">
                        <div class="stat-number stat-highlight"><?php echo $total_applications ?? 0; ?></div>
                        <div class="stat-title">总备案数</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-card text-center py-4">
                        <div class="stat-number" style="color:#ff9800;"><?php echo $pending_applications ?? 0; ?></div>
                        <div class="stat-title">待审核</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-card text-center py-4">
                        <div class="stat-number" style="color:#388e3c;"><?php echo $approved_applications ?? 0; ?></div>
                        <div class="stat-title">已通过</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-card text-center py-4">
                        <div class="stat-number" style="color:#d32f2f;"><?php echo $rejected_applications ?? 0; ?></div>
                        <div class="stat-title">已驳回</div>
                    </div>
                </div>
            </div>
            <div class="dashboard-divider"></div>
            <div class="glass-card">
                <h5 class="mb-3" style="font-weight:600;">最近备案申请</h5>
                <?php if (isset($recent_applications) && !empty($recent_applications)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-borderless mb-0">
                            <thead style="background:rgba(245,245,245,0.82);">
                                <tr>
                                    <th>申请编号</th>
                                    <th>网站名称</th>
                                    <th>域名</th>
                                    <th>申请时间</th>
                                    <th>状态</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_applications as $app): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($app['application_number']); ?></td>
                                        <td><?php echo htmlspecialchars($app['website_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['domain_name']); ?></td>
                                        <td><?php echo format_datetime($app['created_at'], 'Y-m-d H:i'); ?></td>
                                        <td>
                                            <?php if ($app['status'] == STATUS_PENDING): ?>
                                                <span class="badge badge-warning">审核中</span>
                                            <?php elseif ($app['status'] == STATUS_APPROVED): ?>
                                                <span class="badge badge-success">已通过</span>
                                            <?php elseif ($app['status'] == STATUS_REJECTED): ?>
                                                <span class="badge badge-danger">已驳回</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="../view_application.php?id=<?php echo $app['id']; ?>" class="btn btn-sm btn-info">查看</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">暂无备案申请记录</div>
                <?php endif; ?>
            </div>
            <div class="dashboard-btn-group">
                <a href="create_application.php" class="btn">创建备案申请</a>
                <a href="applications.php" class="btn">查看全部</a>
            </div>
        </div>
    </main>
    <script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
    <script>
        // 折线图数据
        var lineChart = new Chart(document.getElementById('lineChart'), {
            type: 'line',
            data: {
                labels: ['1月','2月','3月','4月','5月','6月','7月'],
                datasets: [{
                    label: '备案数',
                    data: [12, 19, 8, 15, 22, 17, 25],
                    backgroundColor: 'rgba(33,150,243,0.08)',
                    borderColor: '#2196f3',
                    borderWidth: 2,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#2196f3',
                    pointHoverBackgroundColor: '#2196f3',
                    pointHoverBorderColor: '#fff',
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    fill: true
                }]
            },
            options: {
                legend: { display: false },
                scales: {
                    yAxes: [{ ticks: { beginAtZero: true } }]
                },
                tooltips: { backgroundColor: '#e3f2fd', titleFontColor: '#1976d2', bodyFontColor: '#23272f' }
            }
        });
        // 饼图数据
        var pieChart = new Chart(document.getElementById('pieChart'), {
            type: 'pie',
            data: {
                labels: ['待审核', '已通过', '已驳回'],
                datasets: [{
                    data: [<?php echo $pending_applications ?? 0; ?>, <?php echo $approved_applications ?? 0; ?>, <?php echo $rejected_applications ?? 0; ?>],
                    backgroundColor: ['#ffb300','#43a047','#e53935'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                legend: { position: 'bottom', labels: { fontColor: '#444', fontSize: 14 } },
                tooltips: { backgroundColor: '#e3f2fd', titleFontColor: '#1976d2', bodyFontColor: '#23272f' }
            }
        });
    </script>
</body>
</html>