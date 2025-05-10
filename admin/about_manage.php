<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
$error = '';
$success = '';
$db = db_connect();
// 处理删除
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare('DELETE FROM announcements WHERE id = :id AND type = \'about\'');
    $stmt->execute(['id' => $id]);
    $success = '删除成功';
}
// 处理新增或编辑
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($title === '' || $content === '') {
        $error = '标题和内容不能为空';
    } else {
        if ($id > 0) {
            $stmt = $db->prepare('UPDATE announcements SET title = :title, content = :content, updated_at = NOW() WHERE id = :id AND type = \'about\'');
            $stmt->execute(['title' => $title, 'content' => $content, 'id' => $id]);
            $success = '修改成功';
        } else {
            $stmt = $db->prepare('INSERT INTO announcements (title, content, type, admin_id, status) VALUES (:title, :content, \'about\', :admin_id, 1)');
            $stmt->execute(['title' => $title, 'content' => $content, 'admin_id' => $_SESSION['admin_id']]);
            $success = '添加成功';
        }
    }
}
// 获取所有关于备案内容
$stmt = $db->query('SELECT * FROM announcements WHERE type = \'about\' ORDER BY id DESC');
$about_list = $stmt->fetchAll();
// 获取单条用于编辑
$edit_item = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $db->prepare('SELECT * FROM announcements WHERE id = :id AND type = \'about\'');
    $stmt->execute(['id' => $id]);
    $edit_item = $stmt->fetch();
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>关于备案内容管理 - <?php echo SITE_NAME; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
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
                    <h1 class="h2">关于备案内容管理</h1>
                </div>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
                <form method="post" class="mb-4">
                    <input type="hidden" name="id" value="<?php echo $edit_item['id'] ?? 0; ?>">
                    <div class="form-group">
                        <label>标题</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($edit_item['title'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>内容</label>
                        <textarea name="content" class="form-control" rows="5"><?php echo htmlspecialchars($edit_item['content'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><?php echo $edit_item ? '保存修改' : '添加'; ?></button>
                    <?php if ($edit_item): ?>
                        <a href="about_manage.php" class="btn btn-secondary ml-2">取消编辑</a>
                    <?php endif; ?>
                </form>
                <table class="table table-bordered">
                    <thead><tr><th>ID</th><th>标题</th><th>内容</th><th>操作</th></tr></thead>
                    <tbody>
                    <?php foreach ($about_list as $item): ?>
                        <tr>
                            <td><?php echo $item['id']; ?></td>
                            <td><?php echo htmlspecialchars($item['title']); ?></td>
                            <td><?php echo htmlspecialchars(mb_strimwidth(strip_tags($item['content']), 0, 60, '...')); ?></td>
                            <td>
                                <a href="about_manage.php?edit=<?php echo $item['id']; ?>" class="btn btn-sm btn-info">编辑</a>
                                <a href="about_manage.php?delete=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('确定要删除吗？');">删除</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </main>
        </div>
    </div>
    <script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>