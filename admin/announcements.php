<?php
// 管理员公告管理页面
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// 检查管理员是否已登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// 处理获取单个公告详情的AJAX请求
if (isset($_GET['action']) && $_GET['action'] == 'get_announcement' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => '请求失败，请稍后重试。'];
    $announcement_id = (int)$_GET['id'];

    if ($announcement_id > 0) {
        try {
            $db = db_connect();
            $stmt = $db->prepare("SELECT title, content FROM announcements WHERE id = :id");
            $stmt->execute([':id' => $announcement_id]);
            $announcement = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($announcement) {
                $response['success'] = true;
                $response['title'] = htmlspecialchars($announcement['title']);
                // 对于富文本内容，可能需要更复杂的处理，这里暂时直接输出
                // 如果内容是HTML，确保在前端正确渲染而不是作为文本
                $response['content'] = $announcement['content']; 
                $response['message'] = '公告详情获取成功。';
            } else {
                $response['message'] = '未找到指定的公告。';
            }
        } catch (PDOException $e) {
            error_log('获取公告详情失败: ' . $e->getMessage());
            $response['message'] = '数据库查询错误，请稍后再试。';
        }
    } else {
        $response['message'] = '无效的公告ID。';
    }
    echo json_encode($response);
    exit;
}

// 初始化变量
$error = '';
$success = '';
$announcements = [];
$total_count = 0;

// 分页参数
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 处理公告操作
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 添加或编辑公告
    if (isset($_POST['action']) && ($_POST['action'] == 'add' || $_POST['action'] == 'edit')) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $is_top = isset($_POST['is_top']) ? 1 : 0;
        $status = isset($_POST['status']) ? 1 : 0;
        $type = isset($_POST['type']) ? trim($_POST['type']) : (isset($_POST['announcement_id']) ? 'about' : 'normal');
        
        if (empty($title) || empty($content)) {
            $error = '标题和内容不能为空';
        } else {
            try {
                $db = db_connect();
                
                if ($_POST['action'] == 'add') {
                    // 添加新公告
                    $stmt = $db->prepare("INSERT INTO announcements (title, content, is_top, status, admin_id, type) VALUES (:title, :content, :is_top, :status, :admin_id, :type)");
                    $stmt->execute([
                        'title' => $title,
                        'content' => $content,
                        'is_top' => $is_top,
                        'status' => $status,
                        'admin_id' => $_SESSION['admin_id'],
                        'type' => $type
                    ]);
                    
                    $announcement_id = $db->lastInsertId();
                    
                    // 记录操作日志
                    log_operation($_SESSION['admin_id'], '添加公告', 'announcement', $announcement_id, json_encode(['title' => $title]));
                    
                    $success = '公告添加成功';
                } else {
                    // 编辑公告
                    $announcement_id = (int)$_POST['announcement_id'];
                    $stmt = $db->prepare("UPDATE announcements SET title = :title, content = :content, is_top = :is_top, status = :status, type = :type, updated_at = NOW() WHERE id = :id");
                    $stmt->execute([
                        'title' => $title,
                        'content' => $content,
                        'is_top' => $is_top,
                        'status' => $status,
                        'type' => $type,
                        'id' => $announcement_id
                    ]);
                    // 记录操作日志
                    log_operation($_SESSION['admin_id'], '编辑公告', 'announcement', $announcement_id, json_encode(['title' => $title]));
                    $success = '公告更新成功';
                }
            } catch (PDOException $e) {
                error_log('处理公告失败: ' . $e->getMessage());
                $error = '系统错误，请稍后再试';
            }
        }
    }
    
    // 删除公告
    if (isset($_POST['action']) && $_POST['action'] == 'delete' && isset($_POST['announcement_id'])) {
        $announcement_id = (int)$_POST['announcement_id'];
        
        try {
            $db = db_connect();
            
            // 获取公告信息
            $stmt = $db->prepare("SELECT title FROM announcements WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $announcement_id]);
            $announcement = $stmt->fetch();
            
            if (!$announcement) {
                $error = '未找到该公告';
            } else {
                // 删除公告
                $stmt = $db->prepare("DELETE FROM announcements WHERE id = :id");
                $stmt->execute(['id' => $announcement_id]);
                
                // 记录操作日志
                log_operation($_SESSION['admin_id'], '删除公告', 'announcement', $announcement_id, json_encode(['title' => $announcement['title']]));
                
                $success = '公告已删除';
            }
        } catch (PDOException $e) {
            error_log('删除公告失败: ' . $e->getMessage());
            $error = '系统错误，请稍后再试';
        }
    }
}

// 获取公告列表
try {
    $db = db_connect();
    
    // 获取总记录数
    $stmt = $db->query("SELECT COUNT(*) as total FROM announcements WHERE type = 'normal'");
    $total_count = $stmt->fetch()['total'];
    
    // 获取分页数据
    $sql = "SELECT a.*, u.username as admin_username 
           FROM announcements a 
           LEFT JOIN admin_users u ON a.admin_id = u.id 
           WHERE a.type = 'normal'
           ORDER BY a.is_top DESC, a.created_at DESC 
           LIMIT :offset, :per_page";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $announcements = $stmt->fetchAll();
    // 获取about类型内容
    $about_stmt = $db->prepare("SELECT * FROM announcements WHERE type = 'about' ORDER BY updated_at DESC, created_at DESC LIMIT 1");
    $about_stmt->execute();
    $about_announcement = $about_stmt->fetch();
} catch (PDOException $e) {
    error_log('获取公告列表失败: ' . $e->getMessage());
    $error = '获取数据失败，请稍后再试';
}

// 计算总页数
$total_pages = ceil($total_count / $per_page);

// 页面标题
$page_title = '公告管理';
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
    <!-- Summernote 富文本编辑器 -->
    <link href="https://cdn.bootcdn.net/ajax/libs/summernote/0.8.18/summernote-bs4.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- 侧边栏 -->
            <?php include __DIR__ . '/../includes/sidebar.php'; ?>


            <!-- 主内容区域 -->
            <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">公告管理</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addAnnouncementModal">
                            <i class="fas fa-plus"></i> 添加公告
                        </button>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- 公告列表 -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">公告列表 (共 <?php echo $total_count; ?> 条记录)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th width="40%">标题</th>
                                        <th>发布人</th>
                                        <th>发布时间</th>
                                        <th>置顶</th>
                                        <th>状态</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($announcements)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">暂无数据</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($announcements as $announcement): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                                                <td><?php echo htmlspecialchars($announcement['admin_username']); ?></td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($announcement['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($announcement['is_top']): ?>
                                                        <span class="badge badge-primary">是</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">否</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($announcement['status']): ?>
                                                        <span class="badge badge-success">显示</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">隐藏</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info view-btn" data-id="<?php echo $announcement['id']; ?>" data-toggle="modal" data-target="#viewAnnouncementModal">
                                                        <i class="fas fa-eye"></i> 查看
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-warning edit-btn" data-id="<?php echo $announcement['id']; ?>" data-toggle="modal" data-target="#editAnnouncementModal">
                                                        <i class="fas fa-edit"></i> 编辑
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger delete-btn" data-id="<?php echo $announcement['id']; ?>" data-title="<?php echo htmlspecialchars($announcement['title']); ?>" data-toggle="modal" data-target="#deleteAnnouncementModal">
                                                        <i class="fas fa-trash"></i> 删除
                                                    </button>
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
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
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

    <!-- 添加公告模态框 -->
    <div class="modal fade" id="addAnnouncementModal" tabindex="-1" role="dialog" aria-labelledby="addAnnouncementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAnnouncementModalLabel">添加公告</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="addTitle">公告标题：</label>
                            <input type="text" class="form-control" id="addTitle" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="addContent">公告内容：</label>
                            <textarea class="form-control summernote" id="addContent" name="content" rows="10" required></textarea>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="addIsTop" name="is_top">
                                <label class="custom-control-label" for="addIsTop">置顶公告</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="addStatus" name="status" checked>
                                <label class="custom-control-label" for="addStatus">显示公告</label>
                            </div>
                        </div>
                        <input type="hidden" name="action" value="add">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">保存</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 编辑公告模态框 -->
    <div class="modal fade" id="editAnnouncementModal" tabindex="-1" role="dialog" aria-labelledby="editAnnouncementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAnnouncementModalLabel">编辑公告</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="editTitle">公告标题：</label>
                            <input type="text" class="form-control" id="editTitle" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="editContent">公告内容：</label>
                            <textarea class="form-control summernote" id="editContent" name="content" rows="10" required></textarea>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="editIsTop" name="is_top">
                                <label class="custom-control-label" for="editIsTop">置顶公告</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="editStatus" name="status">
                                <label class="custom-control-label" for="editStatus">显示公告</label>
                            </div>
                        </div>
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="announcement_id" id="editAnnouncementId" value="">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">保存</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 查看公告模态框 -->
    <div class="modal fade" id="viewAnnouncementModal" tabindex="-1" role="dialog" aria-labelledby="viewAnnouncementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewAnnouncementModalLabel">查看公告</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <h4 id="viewAnnouncementTitle"></h4>
                    <hr>
                    <div id="viewAnnouncementContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">关闭</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 删除公告模态框 -->
    <div class="modal fade" id="deleteAnnouncementModal" tabindex="-1" role="dialog" aria-labelledby="deleteAnnouncementModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAnnouncementModalLabel">删除公告</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <p>确认删除公告「<span id="deleteTitle"></span>」吗？此操作不可恢复。</p>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="announcement_id" id="deleteAnnouncementId" value="">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-danger">确认删除</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    <!-- Summernote JS -->
    <script src="https://cdn.bootcdn.net/ajax/libs/summernote/0.8.18/summernote-bs4.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/summernote/0.8.18/lang/summernote-zh-CN.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // 初始化 Summernote 编辑器
        $('.summernote').summernote({
            height: 300,
            placeholder: '请输入公告内容...',
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['fontname', ['fontname']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['height', ['height']],
                ['table', ['table']],
                ['insert', ['link', 'picture', 'video']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });

        // 查看公告按钮点击事件
        $('.view-btn').click(function() {
            var announcementId = $(this).data('id');
            $('#viewAnnouncementTitle').text('加载中...');
            $('#viewAnnouncementContent').html('<p>正在加载内容...</p>');
            
            $.ajax({
                url: 'announcements.php?action=get_announcement&id=' + announcementId,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#viewAnnouncementTitle').text(response.title);
                        $('#viewAnnouncementContent').html(response.content); // 直接渲染HTML内容
                    } else {
                        $('#viewAnnouncementTitle').text('错误');
                        $('#viewAnnouncementContent').html('<p>' + response.message + '</p>');
                    }
                    $('#viewAnnouncementModal').modal('show'); // AJAX成功或失败后都显示模态框
                },
                error: function() {
                    $('#viewAnnouncementTitle').text('请求失败');
                    $('#viewAnnouncementContent').html('<p>无法连接到服务器或服务器发生错误，请稍后再试。</p>');
                    $('#viewAnnouncementModal').modal('show');
                }
            });
        });

        // 编辑公告按钮点击事件
        $('.edit-btn').click(function() {
            var id = $(this).data('id');
            $('#editAnnouncementId').val(id);
            
            // 这里应该使用AJAX加载详情，但为简化示例，我们直接在页面中构建详情
            // 实际项目中应该使用AJAX从服务器获取数据
            var title = $(this).closest('tr').find('td:first').text();
            $('#editTitle').val(title);
            
            // 设置置顶和状态
            var isTop = $(this).closest('tr').find('td:nth-child(4) .badge-primary').length > 0;
            var isShow = $(this).closest('tr').find('td:nth-child(5) .badge-success').length > 0;
            
            $('#editIsTop').prop('checked', isTop);
            $('#editStatus').prop('checked', isShow);
            
            // 设置内容（实际应该通过AJAX获取）
            $('#editContent').summernote('code', '<p>请通过AJAX加载实际内容</p>');
        });
        
        // 删除公告
        $('.delete-btn').click(function() {
            var id = $(this).data('id');
            var title = $(this).data('title');
            
            $('#deleteAnnouncementId').val(id);
            $('#deleteTitle').text(title);
        });
    });
    </script>
</body>
</html>