<?php
// 管理员数据统计页面
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// 检查管理员是否已登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// 初始化变量
$error = '';
$stats = [];

// 获取统计数据
try {
    $db = db_connect();
    
    // 获取总备案数
    $stmt = $db->query("SELECT COUNT(*) as total FROM icp_applications");
    $stats['total_applications'] = $stmt->fetch()['total'];
    
    // 获取待审核数
    $stmt = $db->query("SELECT COUNT(*) as pending FROM icp_applications WHERE status = 'pending'");
    $stats['pending_applications'] = $stmt->fetch()['pending'];
    
    // 获取已通过数
    $stmt = $db->query("SELECT COUNT(*) as approved FROM icp_applications WHERE status = 'approved'");
    $stats['approved_applications'] = $stmt->fetch()['approved'];
    
    // 获取已驳回数
    $stmt = $db->query("SELECT COUNT(*) as rejected FROM icp_applications WHERE status = 'rejected'");
    $stats['rejected_applications'] = $stmt->fetch()['rejected'];
    
    // 计算通过率
    $processed = $stats['approved_applications'] + $stats['rejected_applications'];
    $stats['approval_rate'] = $processed > 0 ? round(($stats['approved_applications'] / $processed) * 100, 2) : 0;
    
    // 获取最近30天每天的申请数量
    $stmt = $db->query("SELECT DATE(created_at) as date, COUNT(*) as count 
                       FROM icp_applications 
                       WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                       GROUP BY DATE(created_at) 
                       ORDER BY date ASC");
    $daily_applications = $stmt->fetchAll();
    
    // 获取最近6个月每月的申请数量
    $stmt = $db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
                       FROM icp_applications 
                       WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
                       GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
                       ORDER BY month ASC");
    $monthly_applications = $stmt->fetchAll();
    
    // 获取各备案类型网站的数量
    $stmt = $db->query("SELECT website_type, COUNT(*) as count 
                       FROM icp_applications 
                       WHERE website_type IS NOT NULL AND website_type != '' 
                       GROUP BY website_type 
                       ORDER BY count DESC LIMIT 10");
    $content_types = $stmt->fetchAll();
    
    // 获取各主办单位性质的数量 - 注意：由于数据库中暂无company_type字段，使用website_name替代
    $stmt = $db->query("SELECT website_name as company_type, COUNT(*) as count 
                       FROM icp_applications 
                       WHERE website_name IS NOT NULL AND website_name != '' 
                       GROUP BY website_name 
                       ORDER BY count DESC LIMIT 10");
    $company_types = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('获取统计数据失败: ' . $e->getMessage());
    $error = '获取数据失败，请稍后再试';
}

// 页面标题
$page_title = '数据统计';
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
    <!-- Chart.js -->
    <link href="https://cdn.bootcdn.net/ajax/libs/Chart.js/2.9.4/Chart.min.css" rel="stylesheet">
    <!-- 自定义样式 -->
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .stat-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- 侧边栏 -->
            <?php include __DIR__ . '/../includes/sidebar.php'; ?>

            <!-- 主内容区域 -->
            <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">数据统计</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group mr-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="printBtn">
                                <i class="fas fa-print"></i> 打印报表
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="exportBtn">
                                <i class="fas fa-download"></i> 导出数据
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
                            <i class="fas fa-calendar"></i> 本月
                        </button>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- 统计卡片 -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-4">
                        <div class="card stat-card bg-primary text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title">总备案申请</h5>
                                <h2 class="display-4"><?php echo $stats['total_applications']; ?></h2>
                                <p class="card-text">所有备案申请总数</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card stat-card bg-warning text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title">待审核</h5>
                                <h2 class="display-4"><?php echo $stats['pending_applications']; ?></h2>
                                <p class="card-text">等待审核的申请数量</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card stat-card bg-success text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title">已通过</h5>
                                <h2 class="display-4"><?php echo $stats['approved_applications']; ?></h2>
                                <p class="card-text">审核通过的申请数量</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card stat-card bg-danger text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title">已驳回</h5>
                                <h2 class="display-4"><?php echo $stats['rejected_applications']; ?></h2>
                                <p class="card-text">审核驳回的申请数量</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 通过率 -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">备案通过率</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="approvalRateChart"></canvas>
                                </div>
                                <div class="text-center mt-3">
                                    <h3><?php echo $stats['approval_rate']; ?>%</h3>
                                    <p class="text-muted">备案申请通过率</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">申请状态分布</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="statusDistributionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 申请趋势 -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">申请趋势</h5>
                            </div>
                            <div class="card-body">
                                <ul class="nav nav-tabs" id="trendTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link active" id="daily-tab" data-toggle="tab" href="#daily" role="tab" aria-controls="daily" aria-selected="true">每日趋势</a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link" id="monthly-tab" data-toggle="tab" href="#monthly" role="tab" aria-controls="monthly" aria-selected="false">每月趋势</a>
                                    </li>
                                </ul>
                                <div class="tab-content mt-3" id="trendTabsContent">
                                    <div class="tab-pane fade show active" id="daily" role="tabpanel" aria-labelledby="daily-tab">
                                        <div class="chart-container">
                                            <canvas id="dailyTrendChart"></canvas>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="monthly" role="tabpanel" aria-labelledby="monthly-tab">
                                        <div class="chart-container">
                                            <canvas id="monthlyTrendChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 网站类型和主办单位性质 -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">备案类型分布</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="contentTypeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">主办单位性质分布</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="companyTypeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.bootcdn.net/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // 备案通过率图表
        var approvalRateCtx = document.getElementById('approvalRateChart').getContext('2d');
        var approvalRateChart = new Chart(approvalRateCtx, {
            type: 'doughnut',
            data: {
                labels: ['通过', '驳回'],
                datasets: [{
                    data: [<?php echo $stats['approved_applications']; ?>, <?php echo $stats['rejected_applications']; ?>],
                    backgroundColor: ['#28a745', '#dc3545'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    position: 'bottom'
                },
                cutoutPercentage: 70
            }
        });
        
        // 申请状态分布图表
        var statusDistributionCtx = document.getElementById('statusDistributionChart').getContext('2d');
        var statusDistributionChart = new Chart(statusDistributionCtx, {
            type: 'pie',
            data: {
                labels: ['待审核', '已通过', '已驳回'],
                datasets: [{
                    data: [
                        <?php echo $stats['pending_applications']; ?>,
                        <?php echo $stats['approved_applications']; ?>,
                        <?php echo $stats['rejected_applications']; ?>
                    ],
                    backgroundColor: ['#ffc107', '#28a745', '#dc3545'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    position: 'bottom'
                }
            }
        });
        
        // 每日申请趋势图表
        var dailyTrendCtx = document.getElementById('dailyTrendChart').getContext('2d');
        var dailyTrendChart = new Chart(dailyTrendCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    $dates = [];
                    $counts = [];
                    foreach ($daily_applications as $app) {
                        $dates[] = "'" . date('m-d', strtotime($app['date'])) . "'";
                        $counts[] = $app['count'];
                    }
                    echo implode(',', $dates);
                    ?>
                ],
                datasets: [{
                    label: '每日申请数',
                    data: [<?php echo implode(',', $counts); ?>],
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                    pointRadius: 4,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            stepSize: 1
                        }
                    }]
                }
            }
        });
        
        // 每月申请趋势图表
        var monthlyTrendCtx = document.getElementById('monthlyTrendChart').getContext('2d');
        var monthlyTrendChart = new Chart(monthlyTrendCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    $months = [];
                    $monthlyCounts = [];
                    foreach ($monthly_applications as $app) {
                        $months[] = "'" . date('Y年m月', strtotime($app['month'] . '-01')) . "'";
                        $monthlyCounts[] = $app['count'];
                    }
                    echo implode(',', $months);
                    ?>
                ],
                datasets: [{
                    label: '每月申请数',
                    data: [<?php echo implode(',', $monthlyCounts); ?>],
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            stepSize: 1
                        }
                    }]
                }
            }
        });
        
        // 备案类型分布图表
        var contentTypeCtx = document.getElementById('contentTypeChart').getContext('2d');
        var contentTypeChart = new Chart(contentTypeCtx, {
            type: 'horizontalBar',
            data: {
                labels: [
                    <?php 
                    $types = [];
                    $typeCounts = [];
                    foreach ($content_types as $type) {
                        $typeName = $type['website_type'] ?: '未设置';
                        $types[] = "'" . $typeName . "'";
                        $typeCounts[] = $type['count'];
                    }
                    echo implode(',', $types);
                    ?>
                ],
                datasets: [{
                    label: '数量',
                    data: [<?php echo implode(',', $typeCounts); ?>],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                        'rgba(255, 159, 64, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    xAxes: [{
                        ticks: {
                            beginAtZero: true,
                            stepSize: 1
                        }
                    }]
                }
            }
        });
        
        // 主办单位性质分布图表
        var companyTypeCtx = document.getElementById('companyTypeChart').getContext('2d');
        var companyTypeChart = new Chart(companyTypeCtx, {
            type: 'horizontalBar',
            data: {
                labels: [
                    <?php 
                    $companyTypes = [];
                    $companyTypeCounts = [];
                    foreach ($company_types as $type) {
                        $typeName = $type['company_type'] ?: '未设置';
                        $companyTypes[] = "'" . $typeName . "'";
                        $companyTypeCounts[] = $type['count'];
                    }
                    echo implode(',', $companyTypes);
                    ?>
                ],
                datasets: [{
                    label: '数量',
                    data: [<?php echo implode(',', $companyTypeCounts); ?>],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                        'rgba(255, 159, 64, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    xAxes: [{
                        ticks: {
                            beginAtZero: true,
                            stepSize: 1
                        }
                    }]
                }
            }
        });
        
        // 打印报表
        $('#printBtn').click(function() {
            window.print();
        });
        
        // 导出数据（简单示例，实际应该使用AJAX请求后端生成导出文件）
        $('#exportBtn').click(function() {
            alert('导出功能需要在后端实现，此处仅为示例');
        });
    });
    </script>
</body>
</html>