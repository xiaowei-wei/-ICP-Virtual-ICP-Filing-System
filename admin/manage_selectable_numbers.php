<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// 检查管理员是否已登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$page_title = '管理可选备案号';
$error = '';
$success = '';

// 数据库表名
define('TABLE_SELECTABLE_NUMBERS', 'selectable_application_numbers');

// 检查表是否存在，如果不存在则创建
try {
    $db = db_connect();
    $db->exec("CREATE TABLE IF NOT EXISTS " . TABLE_SELECTABLE_NUMBERS . " (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_number VARCHAR(255) NOT NULL UNIQUE,
        is_available BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");
} catch (PDOException $e) {
    $error = '数据库错误：无法创建可选号码表。 ' . $e->getMessage();
}


// 处理生成号码请求
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_numbers'])) {
    $count = isset($_POST['number_count']) ? (int)$_POST['number_count'] : 10;
    if ($count > 0 && $count <= 100) { // 限制一次生成的数量
        try {
            $db = db_connect();

            // Fetch icp_number_digits from system_config
            $stmt_config_digits = $db->prepare("SELECT config_value FROM system_config WHERE config_key = 'icp_number_digits'");
            $stmt_config_digits->execute();
            $config_row_digits = $stmt_config_digits->fetch(PDO::FETCH_ASSOC);
            $number_digits = 8; // Default value if not set or invalid (e.g., for 京ICP备12345678号)
            if ($config_row_digits && !empty($config_row_digits['config_value']) && is_numeric($config_row_digits['config_value'])) {
                $current_digits_val = (int)$config_row_digits['config_value'];
                if ($current_digits_val > 0 && $current_digits_val <= 12) { // Ensure positive and within reasonable limits for numeric part
                    $number_digits = $current_digits_val;
                }
            }

            // Fetch icp_number_prefix from system_config
            $stmt_config_prefix = $db->prepare("SELECT config_value FROM system_config WHERE config_key = 'icp_number_prefix'");
            $stmt_config_prefix->execute();
            $config_row_prefix = $stmt_config_prefix->fetch(PDO::FETCH_ASSOC);
            $icp_prefix = ($config_row_prefix && isset($config_row_prefix['config_value'])) ? $config_row_prefix['config_value'] : '京ICP备'; // Default prefix

            // Fetch icp_number_suffix from system_config
            $stmt_config_suffix = $db->prepare("SELECT config_value FROM system_config WHERE config_key = 'icp_number_suffix'");
            $stmt_config_suffix->execute();
            $config_row_suffix = $stmt_config_suffix->fetch(PDO::FETCH_ASSOC);
            $icp_suffix = ($config_row_suffix && isset($config_row_suffix['config_value'])) ? $config_row_suffix['config_value'] : '号'; // Default suffix

            $generated_count = 0;
            for ($i = 0; $i < $count; $i++) {
                // 生成符合ICP备案号格式的号码
                $max_attempts = 10; // Maximum attempts to generate a unique number for each requested number
                $attempt = 0;
                $successfully_generated_this_number = false;

                do {
                    $numeric_part = '';
                    for ($j = 0; $j < $number_digits; $j++) {
                        $numeric_part .= random_int(0, 9);
                    }
                    $new_number = $icp_prefix . $numeric_part . $icp_suffix;
                    
                    $stmt_check = $db->prepare("SELECT id FROM " . TABLE_SELECTABLE_NUMBERS . " WHERE application_number = :number");
                    $stmt_check->execute([':number' => $new_number]);
                    
                    if (!$stmt_check->fetch()) {
                        $successfully_generated_this_number = true;
                        break; // Unique number found
                    }
                    $attempt++;
                } while ($attempt < $max_attempts);

                if (!$successfully_generated_this_number) {
                    // Could not generate a unique number after several attempts for this specific number, skip it.
                    // You might want to log this event or inform the admin more specifically.
                    continue; 
                }
                
                $stmt = $db->prepare("INSERT INTO " . TABLE_SELECTABLE_NUMBERS . " (application_number, is_available) VALUES (:number, TRUE)");
                if ($stmt->execute([':number' => $new_number])) {
                    $generated_count++;
                }
            }
            if ($generated_count > 0) {
                $success = "成功生成了 {$generated_count} 个可选备案号。";
            } else {
                $error = '未能生成任何新的备案号，可能存在重复或数据库错误。';
            }
        } catch (PDOException $e) {
            $error = '生成号码时发生数据库错误：' . $e->getMessage();
        }
    } else {
        $error = '请输入有效的生成数量 (1-100)。';
    }
}

// 处理删除号码请求
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_number'])) {
    $number_id_to_delete = isset($_POST['number_id']) ? (int)$_POST['number_id'] : 0;
    if ($number_id_to_delete > 0) {
        try {
            $db = db_connect();
            $stmt = $db->prepare("DELETE FROM " . TABLE_SELECTABLE_NUMBERS . " WHERE id = :id"); // 允许删除所有状态的号码
            if ($stmt->execute([':id' => $number_id_to_delete])) {
                if ($stmt->rowCount() > 0) {
                    $success = '成功删除备案号。';
                } else {
                    $error = '无法删除该备案号，可能已被选用或不存在。';
                }
            } else {
                $error = '删除号码时发生数据库错误。';
            }
        } catch (PDOException $e) {
            $error = '删除号码时发生数据库错误：' . $e->getMessage();
        }
    }
}


// 获取可选号码列表
$selectable_numbers = [];
$available_numbers_count = 0;
$taken_numbers_count = 0;

try {
    $db = db_connect();
    $stmt_all = $db->query("SELECT * FROM " . TABLE_SELECTABLE_NUMBERS . " ORDER BY created_at DESC");
    $selectable_numbers = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

    $stmt_available_count = $db->query("SELECT COUNT(*) as count FROM " . TABLE_SELECTABLE_NUMBERS . " WHERE is_available = TRUE");
    $available_numbers_count = $stmt_available_count->fetchColumn();

    $stmt_taken_count = $db->query("SELECT COUNT(*) as count FROM " . TABLE_SELECTABLE_NUMBERS . " WHERE is_available = FALSE");
    $taken_numbers_count = $stmt_taken_count->fetchColumn();

} catch (PDOException $e) {
    $error = '获取可选号码列表时发生数据库错误：' . $e->getMessage();
}

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
                    <h1 class="h2"><?php echo $page_title; ?></h1>
                </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">生成新的可选备案号</div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="number_count">生成数量 (1-100):</label>
                            <input type="number" name="number_count" id="number_count" class="form-control" value="10" min="1" max="100" required>
                        </div>
                        <button type="submit" name="generate_numbers" class="btn btn-primary mt-2">生成号码</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">号码池统计</div>
                <div class="card-body">
                    <p>可用号码数量: <strong><?php echo $available_numbers_count; ?></strong></p>
                    <p>已选号码数量: <strong><?php echo $taken_numbers_count; ?></strong></p>
                    <p>总号码数量: <strong><?php echo count($selectable_numbers); ?></strong></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table mr-1"></i>
            可选备案号列表
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>备案号</th>
                            <th>状态</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($selectable_numbers)): ?>
                            <?php foreach ($selectable_numbers as $number): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($number['id']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($number['application_number']); ?></strong></td>
                                    <td>
                                        <?php if ($number['is_available']): ?>
                                            <span class="badge bg-success">可用</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">已选用</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($number['created_at']); ?></td>
                                    <td>
                                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('确定要删除这个号码吗？此操作不可恢复。');">
                                            <input type="hidden" name="number_id" value="<?php echo $number['id']; ?>">
                                            <button type="submit" name="delete_number" class="btn btn-danger btn-sm">删除</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">当前没有可选的备案号。</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
            </main>
        </div>
    </div>
    <!-- Bootstrap JS 和依赖 -->
    <script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
<?php
require_once __DIR__ . '/../includes/footer.php'; 
?>

<script>
// 如果使用 DataTables 等JS库，可以在这里初始化
// $(document).ready(function() {
// $('#dataTable').DataTable();
// });
</script>