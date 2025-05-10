<?php
// 管理员申请管理页面
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// 检查管理员是否已登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// 初始化变量
$error = '';
$success = '';
$applications = [];
$total_count = 0;

// 分页参数
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 筛选参数
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

// 处理审核操作
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && isset($_POST['application_id'])) {
        $action = $_POST['action'];
        $application_id = (int)$_POST['application_id'];
        
        try {
            $db = db_connect();
            
            // 获取申请信息
            $stmt = $db->prepare("SELECT * FROM icp_applications WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $application_id]);
            $application = $stmt->fetch();
            
            if (!$application) {
                $error = '未找到该备案申请';
            } else {
                if ($action == 'approve') {
                    // 生成备案号
                    $icp_number = generate_icp_number();
                    
                    // 更新状态为已通过
                    $stmt = $db->prepare("UPDATE icp_applications SET status = :status, icp_number = :icp_number, review_admin_id = :admin_id, review_time = NOW() WHERE id = :id");
                    $stmt->execute([
                        'status' => STATUS_APPROVED,
                        'icp_number' => $icp_number,
                        'admin_id' => $_SESSION['admin_id'],
                        'id' => $application_id
                    ]);
                    
                    // 记录操作日志
                    log_operation($_SESSION['admin_id'], '审核通过备案申请', 'application', $application_id, json_encode(['icp_number' => $icp_number]));
                    
                    $success = '备案申请已通过，备案号：' . $icp_number;
                } elseif ($action == 'reject') {
                    $reject_reason = trim($_POST['reject_reason'] ?? '');
                    
                    if (empty($reject_reason)) {
                        $error = '请填写驳回原因';
                    } else {
                        // 更新状态为已驳回
                        $stmt = $db->prepare("UPDATE icp_applications SET status = :status, reject_reason = :reason, review_admin_id = :admin_id, review_time = NOW() WHERE id = :id");
                        $stmt->execute([
                            'status' => STATUS_REJECTED,
                            'reason' => $reject_reason,
                            'admin_id' => $_SESSION['admin_id'],
                            'id' => $application_id
                        ]);
                        
                        // 记录操作日志
                        log_operation($_SESSION['admin_id'], '驳回备案申请', 'application', $application_id, json_encode(['reason' => $reject_reason]));
                        
                        $success = '备案申请已驳回';
                    }
                } elseif ($action == 'delete') {
                    // 删除备案申请
                    $stmt = $db->prepare("DELETE FROM icp_applications WHERE id = :id");
                    $stmt->execute(['id' => $application_id]);
                    // 记录操作日志
                    log_operation($_SESSION['admin_id'], '删除备案申请', 'application', $application_id, '');
                    $success = '备案申请已删除';
                }
            }
        } catch (PDOException $e) {
            error_log('处理备案申请失败: ' . $e->getMessage());
            $error = '系统错误，请稍后再试';
        }
    }
}

// 获取备案申请列表
try {
    $db = db_connect();
    
    // 构建查询条件
    $where_conditions = [];
    $params = [];
    
    if ($status_filter) {
        $where_conditions[] = "status = :status";
        $params['status'] = $status_filter;
    }
    
    if ($search_keyword) {
        $where_conditions[] = "(application_number LIKE :keyword OR domain_name LIKE :keyword OR website_name LIKE :keyword OR contact_email LIKE :keyword OR website_desc LIKE :keyword OR qq_number LIKE :keyword)";
        $params['keyword'] = "%{$search_keyword}%";
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // 获取总记录数
    $count_sql = "SELECT COUNT(*) as total FROM icp_applications {$where_clause}";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_count = $stmt->fetch()['total'];
    
    // 获取分页数据
    $sql = "SELECT a.*, u.username as admin_username 
           FROM icp_applications a 
           LEFT JOIN admin_users u ON a.review_admin_id = u.id 
           {$where_clause} 
           ORDER BY a.created_at DESC 
           LIMIT :offset, :per_page";
    
    $stmt = $db->prepare($sql);
    
    // 绑定分页参数
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    
    // 绑定其他参数
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    
    $stmt->execute();
    $applications = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('获取备案申请列表失败: ' . $e->getMessage());
    $error = '获取数据失败，请稍后再试';
}

// 计算总页数
$total_pages = ceil($total_count / $per_page);

// 页面标题
$page_title = '申请管理';
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
                    <h1 class="h2">申请管理</h1>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- 筛选和搜索 -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" action="" class="form-inline">
                            <div class="form-group mr-3">
                                <label for="status" class="mr-2">状态：</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="" <?php echo $status_filter == '' ? 'selected' : ''; ?>>全部</option>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>待审核</option>
                                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>已通过</option>
                                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>已驳回</option>
                                </select>
                            </div>
                            <div class="form-group mr-3">
                                <input type="text" class="form-control" name="keyword" placeholder="申请号/域名/单位名称" value="<?php echo htmlspecialchars($search_keyword); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">筛选</button>
                            <a href="applications.php" class="btn btn-secondary ml-2">重置</a>
                        </form>
                    </div>
                </div>

                <!-- 备案申请列表 -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">备案申请列表 (共 <?php echo $total_count; ?> 条记录)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>申请编号</th>
                                        <th>网站名称</th>
                                        <th>域名</th>
                                        <th>主办单位</th>
                                        <th>申请时间</th>
                                        <th>状态</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($applications)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">暂无数据</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($applications as $app): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($app['application_number']); ?></td>
                                                <td><?php echo htmlspecialchars($app['website_name']); ?></td>
                                                <td><?php echo htmlspecialchars($app['domain_name']); ?></td>
                                                <td><?php echo htmlspecialchars($app['website_name']); ?></td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($app['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($app['status'] == STATUS_PENDING): ?>
                                                        <span class="badge badge-warning">待审核</span>
                                                    <?php elseif ($app['status'] == STATUS_APPROVED): ?>
                                                        <span class="badge badge-success">已通过</span>
                                                    <?php elseif ($app['status'] == STATUS_REJECTED): ?>
                                                        <span class="badge badge-danger">已驳回</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info view-btn" data-id="<?php echo $app['id']; ?>" data-toggle="modal" data-target="#viewModal">
                                                        <i class="fas fa-eye"></i> 查看
                                                    </button>
                                                    <?php if ($app['status'] == STATUS_PENDING): ?>
                                                        <button type="button" class="btn btn-sm btn-success approve-btn" data-id="<?php echo $app['id']; ?>" data-toggle="modal" data-target="#approveModal">
                                                            <i class="fas fa-check"></i> 通过
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger reject-btn" data-id="<?php echo $app['id']; ?>" data-toggle="modal" data-target="#rejectModal">
                                                            <i class="fas fa-times"></i> 驳回
                                                        </button>
                                                    <?php endif; ?>
                                                    <form method="post" action="" style="display:inline;" onsubmit="return confirm('确定要删除该备案申请吗？');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt"></i> 删除</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 分页 -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&keyword=<?php echo urlencode($search_keyword); ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&keyword=<?php echo urlencode($search_keyword); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&keyword=<?php echo urlencode($search_keyword); ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- 查看详情模态框 -->
    <div class="modal fade" id="viewModal" tabindex="-1" role="dialog" aria-labelledby="viewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalLabel">备案申请详情</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="viewModalBody">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">加载中...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">关闭</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 通过模态框 -->
    <div class="modal fade" id="approveModal" tabindex="-1" role="dialog" aria-labelledby="approveModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approveModalLabel">通过备案申请</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <p>确认通过此备案申请吗？通过后将自动生成备案号。</p>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="application_id" id="approveApplicationId" value="">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-success">确认通过</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 驳回模态框 -->
    <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel">驳回备案申请</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="rejectReason">驳回原因：</label>
                            <textarea class="form-control" id="rejectReason" name="reject_reason" rows="3" required></textarea>
                        </div>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="application_id" id="rejectApplicationId" value="">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-danger">确认驳回</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // 查看详情
        $('.view-btn').click(function() {
            var id = $(this).data('id');
            $('#viewModalBody').html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="sr-only">加载中...</span></div></div>');
            $.get('../view_application.php', {id: id}, function(data) {
                // 只提取详情卡片部分内容
                var match = data.match(/<div class="card">([\s\S]*?)<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/);
                if (match) {
                    $('#viewModalBody').html('<div class="card">'+match[1]+'</div>');
                } else {
                    $('#viewModalBody').html(data);
                }
            }).fail(function() {
                $('#viewModalBody').html('<div class="alert alert-danger">加载详情失败，请稍后再试。</div>');
            });
        });
        
        // 通过申请
        $('.approve-btn').click(function() {
            var id = $(this).data('id');
            $('#approveApplicationId').val(id);
        });
        
        // 驳回申请
        $('.reject-btn').click(function() {
            var id = $(this).data('id');
            $('#rejectApplicationId').val(id);
        });
    });
    </script>
</body>
</html>