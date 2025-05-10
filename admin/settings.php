<?php
// 管理员系统设置页面
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
$system_configs = [];
$sensitive_words = [];

// 处理系统配置更新
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_config') {
        try {
            $db = db_connect();
            
            // 更新系统配置
            foreach ($_POST['config'] as $key => $value) {
                $trimmed_value = trim($value);

                // 首先检查配置项是否存在
                $stmt_check = $db->prepare("SELECT config_key FROM system_config WHERE config_key = :key");
                $stmt_check->execute(['key' => $key]);
                $existing_config = $stmt_check->fetch();

                if ($existing_config) {
                    // 配置项存在，执行更新操作
                    $stmt_update = $db->prepare("UPDATE system_config SET config_value = :value WHERE config_key = :key");
                    $stmt_update->execute([
                        'value' => $trimmed_value,
                        'key' => $key
                    ]);
                } else {
                    // 配置项不存在，执行插入操作
                    $description = ''; // 默认描述
                    // 为特定的键设置描述
                    if ($key === 'brand_logo_text') {
                        $description = '品牌LOGO显示的文字';
                    } elseif ($key === 'icp_title') {
                        $description = '网站首页显示的标题';
                    } elseif ($key === 'icp_description') {
                        $description = '网站首页的描述信息';
                    }
                    // 可以根据需要为其他新键添加默认描述

                    $stmt_insert = $db->prepare("INSERT INTO system_config (config_key, config_value, description) VALUES (:key, :value, :description)");
                    $stmt_insert->execute([
                        'key' => $key,
                        'value' => $trimmed_value,
                        'description' => $description
                    ]);
                }
            }
            
            // 记录操作日志
            log_operation($_SESSION['admin_id'], '更新系统配置', 'system_config', null, json_encode($_POST['config']));
            
            $success = '系统配置已更新';
        } catch (PDOException $e) {
            error_log('更新系统配置失败: ' . $e->getMessage());
            $error = '系统错误，请稍后再试：' . $e->getMessage();
        }
    } elseif ($_POST['action'] == 'add_sensitive_word') {
        $word = trim($_POST['word'] ?? '');
        $level = $_POST['level'] ?? 'medium';
        
        if (empty($word)) {
            $error = '敏感词不能为空';
        } else {
            try {
                $db = db_connect();
                
                // 检查敏感词是否已存在
                $stmt = $db->prepare("SELECT id FROM sensitive_words WHERE word = :word LIMIT 1");
                $stmt->execute(['word' => $word]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $error = '该敏感词已存在';
                } else {
                    // 添加敏感词
                    $stmt = $db->prepare("INSERT INTO sensitive_words (word, level) VALUES (:word, :level)");
                    $stmt->execute([
                        'word' => $word,
                        'level' => $level
                    ]);
                    
                    // 记录操作日志
                    log_operation($_SESSION['admin_id'], '添加敏感词', 'sensitive_word', $db->lastInsertId(), json_encode(['word' => $word, 'level' => $level]));
                    
                    $success = '敏感词添加成功';
                }
            } catch (PDOException $e) {
                error_log('添加敏感词失败: ' . $e->getMessage());
                $error = '系统错误，请稍后再试：' . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] == 'delete_sensitive_word' && isset($_POST['word_id'])) {
        $word_id = (int)$_POST['word_id'];
        
        try {
            $db = db_connect();
            
            // 获取敏感词信息
            $stmt = $db->prepare("SELECT word FROM sensitive_words WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $word_id]);
            $word_info = $stmt->fetch();
            
            if (!$word_info) {
                $error = '未找到该敏感词';
            } else {
                // 删除敏感词
                $stmt = $db->prepare("DELETE FROM sensitive_words WHERE id = :id");
                $stmt->execute(['id' => $word_id]);
                
                // 记录操作日志
                log_operation($_SESSION['admin_id'], '删除敏感词', 'sensitive_word', $word_id, json_encode(['word' => $word_info['word']]));
                
                $success = '敏感词已删除';
            }
        } catch (PDOException $e) {
            error_log('删除敏感词失败: ' . $e->getMessage());
            $error = '系统错误，请稍后再试：' . $e->getMessage();
        }
    }
}

// 获取系统配置
try {
    $db = db_connect();
    
    $stmt = $db->query("SELECT * FROM system_config ORDER BY id ASC");
    $system_configs = $stmt->fetchAll();

    // Re-index system_configs for easier access by key
    $configs_by_key = [];
    if (is_array($system_configs)) {
        foreach ($system_configs as $config_item) {
            if (isset($config_item['config_key'])) {
                $configs_by_key[$config_item['config_key']] = $config_item;
            }
        }
    }
    
    // 获取敏感词列表
    $stmt = $db->query("SELECT * FROM sensitive_words ORDER BY level DESC, word ASC");
    $sensitive_words = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('获取系统配置失败: ' . $e->getMessage());
    $error = '获取数据失败，请稍后再试：' . $e->getMessage();
}

// 页面标题
$page_title = '系统设置';
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
            <!-- 引入公共侧边栏 -->
            <?php include __DIR__ . '/../includes/sidebar.php'; ?>

            <!-- 主内容区域 -->
            <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">系统设置</h1>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- 设置选项卡 -->
                <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="general-tab" data-toggle="tab" href="#general" role="tab" aria-controls="general" aria-selected="true">
                            <i class="fas fa-cogs"></i> 基本设置
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="sensitive-tab" data-toggle="tab" href="#sensitive" role="tab" aria-controls="sensitive" aria-selected="false">
                            <i class="fas fa-filter"></i> 敏感词管理
                        </a>
                    </li>
                </ul>

                <div class="tab-content mt-4" id="settingsTabsContent">
                    <!-- 基本设置 -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">系统基本设置</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">备案号前缀：</label>
                                        <div class="col-sm-9">
                                            <?php foreach ($system_configs as $config): ?>
                                                <?php if ($config['config_key'] == 'icp_number_prefix'): ?>
                                                    <input type="text" class="form-control" name="config[icp_number_prefix]" value="<?php echo htmlspecialchars($config['config_value']); ?>">
                                                    <small class="form-text text-muted"><?php echo htmlspecialchars($config['description']); ?></small>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">备案号数字位数：</label>
                                        <div class="col-sm-9">
                                            <?php foreach ($system_configs as $config): ?>
                                                <?php if ($config['config_key'] == 'icp_number_digits'): ?>
                                                    <input type="number" class="form-control" name="config[icp_number_digits]" value="<?php echo htmlspecialchars($config['config_value']); ?>" min="1" max="12">
                                                    <small class="form-text text-muted"><?php echo htmlspecialchars($config['description']); ?></small>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">最小审核天数：</label>
                                        <div class="col-sm-9">
                                            <?php foreach ($system_configs as $config): ?>
                                                <?php if ($config['config_key'] == 'review_days_min'): ?>
                                                    <input type="number" class="form-control" name="config[review_days_min]" value="<?php echo htmlspecialchars($config['config_value']); ?>" min="1" max="30">
                                                    <small class="form-text text-muted"><?php echo htmlspecialchars($config['description']); ?></small>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">最大审核天数：</label>
                                        <div class="col-sm-9">
                                            <?php foreach ($system_configs as $config): ?>
                                                <?php if ($config['config_key'] == 'review_days_max'): ?>
                                                    <input type="number" class="form-control" name="config[review_days_max]" value="<?php echo htmlspecialchars($config['config_value']); ?>" min="1" max="30">
                                                    <small class="form-text text-muted"><?php echo htmlspecialchars($config['description']); ?></small>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">品牌LOGO文字：</label>
                                        <div class="col-sm-9">
                                            <input type="text" class="form-control" name="config[brand_logo_text]" value="<?php echo isset($configs_by_key['brand_logo_text']) ? htmlspecialchars($configs_by_key['brand_logo_text']['config_value']) : ''; ?>">
                                            <small class="form-text text-muted"><?php echo isset($configs_by_key['brand_logo_text']) ? htmlspecialchars($configs_by_key['brand_logo_text']['description']) : '品牌LOGO显示的文字'; ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">首页标题：</label>
                                        <div class="col-sm-9">
                                            <input type="text" class="form-control" name="config[icp_title]" value="<?php echo isset($configs_by_key['icp_title']) ? htmlspecialchars($configs_by_key['icp_title']['config_value']) : ''; ?>">
                                            <small class="form-text text-muted"><?php echo isset($configs_by_key['icp_title']) ? htmlspecialchars($configs_by_key['icp_title']['description']) : '网站首页显示的标题'; ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">首页描述：</label>
                                        <div class="col-sm-9">
                                            <textarea class="form-control" name="config[icp_description]" rows="3"><?php echo isset($configs_by_key['icp_description']) ? htmlspecialchars($configs_by_key['icp_description']['config_value']) : ''; ?></textarea>
                                            <small class="form-text text-muted"><?php echo isset($configs_by_key['icp_description']) ? htmlspecialchars($configs_by_key['icp_description']['description']) : '网站首页的描述信息'; ?></small>
                                        </div>
                                    </div>
                                    
                                    <input type="hidden" name="action" value="update_config">
                                    <div class="form-group row">
                                        <div class="col-sm-9 offset-sm-3">
                                            <button type="submit" class="btn btn-primary">保存设置</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 敏感词管理 -->
                    <div class="tab-pane fade" id="sensitive" role="tabpanel" aria-labelledby="sensitive-tab">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">敏感词管理</h5>
                                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addSensitiveWordModal">
                                    <i class="fas fa-plus"></i> 添加敏感词
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th>敏感词</th>
                                                <th>级别</th>
                                                <th>添加时间</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($sensitive_words)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">暂无敏感词</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($sensitive_words as $word): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($word['word']); ?></td>
                                                        <td>
                                                            <?php if ($word['level'] == 'high'): ?>
                                                                <span class="badge badge-danger">高</span>
                                                            <?php elseif ($word['level'] == 'medium'): ?>
                                                                <span class="badge badge-warning">中</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-info">低</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo date('Y-m-d H:i', strtotime($word['created_at'])); ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-danger delete-word-btn" data-id="<?php echo $word['id']; ?>" data-word="<?php echo htmlspecialchars($word['word']); ?>" data-toggle="modal" data-target="#deleteSensitiveWordModal">
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
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- 添加敏感词模态框 -->
    <div class="modal fade" id="addSensitiveWordModal" tabindex="-1" role="dialog" aria-labelledby="addSensitiveWordModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSensitiveWordModalLabel">添加敏感词</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="sensitiveWord">敏感词：</label>
                            <input type="text" class="form-control" id="sensitiveWord" name="word" required>
                        </div>
                        <div class="form-group">
                            <label for="sensitiveLevel">级别：</label>
                            <select class="form-control" id="sensitiveLevel" name="level">
                                <option value="low">低</option>
                                <option value="medium" selected>中</option>
                                <option value="high">高</option>
                            </select>
                        </div>
                        <input type="hidden" name="action" value="add_sensitive_word">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">添加</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 删除敏感词模态框 -->
    <div class="modal fade" id="deleteSensitiveWordModal" tabindex="-1" role="dialog" aria-labelledby="deleteSensitiveWordModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteSensitiveWordModalLabel">删除敏感词</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <p>确认删除敏感词「<span id="deleteWordText"></span>」吗？</p>
                        <input type="hidden" name="action" value="delete_sensitive_word">
                        <input type="hidden" name="word_id" id="deleteWordId" value="">
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
    
    <script>
    $(document).ready(function() {
        // 删除敏感词
        $('.delete-word-btn').click(function() {
            var id = $(this).data('id');
            var word = $(this).data('word');
            
            $('#deleteWordId').val(id);
            $('#deleteWordText').text(word);
        });
    });
    </script>
</body>
</html>