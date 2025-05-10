<?php
session_start();

// 防止重复安装
if (file_exists(__DIR__ . '/config/config.php') && filesize(__DIR__ . '/config/config.php') > 0) {
    // 检查config.php是否包含有效的数据库连接信息，如果包含，则认为已安装
    // 这是一个简单的检查，可以根据实际config.php的内容进行调整
    $config_content = file_get_contents(__DIR__ . '/config/config.php');
    if (strpos($config_content, 'DB_HOST') !== false && strpos($config_content, 'DB_NAME') !== false) {
        echo "<p style='color:red; text-align:center; margin-top: 50px;'>系统似乎已安装。如果您确定要重新安装，请先删除 <code>config/config.php</code> 文件。</p>";
        exit;
    }
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success_message = '';

// 默认配置
define('CONFIG_FILE_PATH', __DIR__ . '/config/config.php');
define('DATABASE_CONFIG_FILE_PATH', __DIR__ . '/config/database.php'); // 假设数据库配置分离

// 确保 config 目录存在且可写
if (!is_dir(__DIR__ . '/config')) {
    if (!mkdir(__DIR__ . '/config', 0755, true)) {
        $errors[] = '创建 config 目录失败，请检查权限。';
    }
}
if (is_dir(__DIR__ . '/config') && !is_writable(__DIR__ . '/config')) {
    $errors[] = 'config 目录不可写，请检查权限。';
}


// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) { // 环境检查通过，进入下一步
        // 实际上环境检查是在页面加载时完成的，这里只是确认用户点击了“下一步”
        header('Location: install.php?step=2');
        exit;
    } elseif ($step === 2 && isset($_POST['db_setup'])) { // 数据库配置
        $db_host = trim($_POST['db_host']);
        $db_name = trim($_POST['db_name']);
        $db_user = trim($_POST['db_user']);
        $db_pass = $_POST['db_pass']; // 密码可以为空

        if (empty($db_host)) $errors[] = '数据库主机不能为空。';
        if (empty($db_name)) $errors[] = '数据库名称不能为空。';
        if (empty($db_user)) $errors[] = '数据库用户名不能为空。';

        if (empty($errors)) {
            // 尝试连接数据库
            try {
                $dsn = "mysql:host={$db_host};charset=utf8mb4";
                $pdo = new PDO($dsn, $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                // 尝试创建数据库 (如果不存在)
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$db_name}`");

                $_SESSION['install_db_config'] = [
                    'host' => $db_host,
                    'name' => $db_name,
                    'user' => $db_user,
                    'pass' => $db_pass
                ];
                header('Location: install.php?step=3');
                exit;
            } catch (PDOException $e) {
                $errors[] = '数据库连接失败或创建数据库失败：' . $e->getMessage();
            }
        }
    } elseif ($step === 3 && isset($_POST['admin_setup'])) { // 管理员账户设置
        $admin_user = trim($_POST['admin_user']);
        $admin_pass = $_POST['admin_pass'];
        $admin_pass_confirm = $_POST['admin_pass_confirm'];

        if (empty($admin_user)) $errors[] = '管理员用户名不能为空。';
        if (empty($admin_pass)) $errors[] = '管理员密码不能为空。';
        if ($admin_pass !== $admin_pass_confirm) $errors[] = '两次输入的密码不一致。';
        if (strlen($admin_pass) < 6) $errors[] = '管理员密码长度至少为6位。';

        if (empty($errors) && isset($_SESSION['install_db_config'])) {
            $_SESSION['install_admin_config'] = [
                'username' => $admin_user,
                'password' => $admin_pass
            ];
            header('Location: install.php?step=4');
            exit;
        } elseif (!isset($_SESSION['install_db_config'])) {
            $errors[] = '数据库配置丢失，请返回上一步重新配置。';
            $step = 2; // 跳回数据库配置步骤
        }
    } elseif ($step === 4 && isset($_POST['finalize_install'])) { // 完成安装
        if (!isset($_SESSION['install_db_config']) || !isset($_SESSION['install_admin_config'])) {
            $errors[] = '安装配置信息不完整，请返回之前的步骤检查。';
            $step = 1; // 或者跳回第一步
        }

        if (empty($errors)) {
            $db_config = $_SESSION['install_db_config'];
            $admin_config = $_SESSION['install_admin_config'];

            // 1. 创建 config/database.php (或更新主 config.php)
            $database_php_content = "<?php\n";
            $database_php_content .= "// 数据库配置文件\n";
            $database_php_content .= "define('DB_HOST', '{$db_config['host']}');\n";
            $database_php_content .= "define('DB_NAME', '{$db_config['name']}');\n";
            $database_php_content .= "define('DB_USER', '{$db_config['user']}');\n";
            $database_php_content .= "define('DB_PASS', '{$db_config['pass']}');\n";
            $database_php_content .= "define('DB_CHARSET', 'utf8mb4');\n";

            // 2. 创建主 config.php (如果需要，或者合并)
            // 这里我们创建一个简单的 config.php，实际项目中可能更复杂
            $main_config_content = "<?php\n";
            $main_config_content .= "// 主配置文件\n";
            $main_config_content .= "// 包含数据库配置\n";
            $main_config_content .= "require_once __DIR__ . '/database.php';\n\n";
            $main_config_content .= "// 站点名称\n";
            $main_config_content .= "define('SITE_NAME', '虚拟备案系统');\n\n";
            $main_config_content .= "// 错误报告级别 (生产环境建议设置为0)\n";
            $main_config_content .= "error_reporting(E_ALL);\n";
            $main_config_content .= "ini_set('display_errors', 1);\n\n";
            $main_config_content .= "// 时区设置\n";
            $main_config_content .= "date_default_timezone_set('Asia/Shanghai');\n\n";
            $main_config_content .= "// Session 设置\n";
            $main_config_content .= "if (session_status() == PHP_SESSION_NONE) {\n";
            $main_config_content .= "    session_start();\n";
            $main_config_content .= "}\n\n";
            $main_config_content .= "// 备案状态常量\n";
            $main_config_content .= "define('STATUS_PENDING', 'pending');\n";
            $main_config_content .= "define('STATUS_APPROVED', 'approved');\n";
            $main_config_content .= "define('STATUS_REJECTED', 'rejected');\n";

            try {
                if (file_put_contents(DATABASE_CONFIG_FILE_PATH, $database_php_content) === false) {
                    $errors[] = '创建数据库配置文件 <code>' . basename(DATABASE_CONFIG_FILE_PATH) . '</code> 失败，请检查 <code>config</code> 目录权限。';
                }
                if (file_put_contents(CONFIG_FILE_PATH, $main_config_content) === false) {
                    $errors[] = '创建主配置文件 <code>' . basename(CONFIG_FILE_PATH) . '</code> 失败，请检查 <code>config</code> 目录权限。';
                }

                if (empty($errors)) {
                    // 3. 连接数据库并创建表结构、插入管理员账户
                    $dsn = "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $db_config['user'], $db_config['pass'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);

                    // 仅当用户填写的数据库名不是icp_system时才导入表结构和初始数据
                    if (strtolower($db_config['name']) !== 'icp_system') {
                        $sql_file_path = __DIR__ . '/database/icp_db.sql';
                        if (file_exists($sql_file_path)) {
                            $sql_content = file_get_contents($sql_file_path);
                            // 替换SQL中的icp_system为用户填写的数据库名
                            $sql_content = str_replace(['CREATE DATABASE IF NOT EXISTS icp_system', 'USE icp_system'], [
                                'CREATE DATABASE IF NOT EXISTS ' . $db_config['name'],
                                'USE ' . $db_config['name']
                            ], $sql_content);
                            $sql_commands = preg_split('/;\s*\n/', $sql_content);
                            foreach ($sql_commands as $command) {
                                $command = trim($command);
                                if ($command) {
                                    $pdo->exec($command);
                                }
                            }
                        }
                    }
                    if (empty($errors)) {
                        // 检查admin_users表是否为空，若为空则允许直接插入，否则检查用户名是否已存在
                        $stmt = $pdo->query("SELECT COUNT(*) FROM admin_users");
                        $admin_count = $stmt->fetchColumn();
                        if ($admin_count > 0) {
                            // 检查是否存在默认admin用户
                            $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = 'admin'");
                            $stmt->execute();
                            $default_admin = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($default_admin) {
                                // 允许用户选择覆盖或直接使用已有账户（此处简化为自动覆盖，实际可弹窗交互）
                                $stmt = $pdo->prepare("DELETE FROM admin_users WHERE username = 'admin'");
                                $stmt->execute();
                                $admin_count--;
                            }
                            // 检查用户名是否已存在
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username = :username");
                            $stmt->execute(['username' => $admin_config['username']]);
                            $user_exists = $stmt->fetchColumn();
                            if ($user_exists) {
                                $errors[] = '管理员用户名已存在，请更换用户名或直接使用已有账户登录。';
                            } else {
                                // 插入管理员账户
                                $hashed_password = password_hash($admin_config['password'], PASSWORD_DEFAULT);
                                $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, created_at) VALUES (:username, :password, NOW())");
                                $stmt->execute(['username' => $admin_config['username'], 'password' => $hashed_password]);
                            }
                        } else {
                            // 首次安装，直接插入管理员账户
                            $hashed_password = password_hash($admin_config['password'], PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, created_at) VALUES (:username, :password, NOW())");
                            $stmt->execute(['username' => $admin_config['username'], 'password' => $hashed_password]);
                        }

                        // 清理 session
                        unset($_SESSION['install_db_config']);
                        unset($_SESSION['install_admin_config']);

                        if (empty($errors)) {
                            $success_message = '恭喜！系统安装成功。';
                            // 安装完成后，可以考虑删除install.php或提示用户删除
                            // 为了安全，这里我们重命名安装文件
                            if (rename(__FILE__, __FILE__ . '.completed')) {
                                $success_message .= '<br>安装文件已自动重命名为 <code>install.php.completed</code>。';
                            } else {
                                $success_message .= '<br><strong style="color:orange;">警告：自动重命名安装文件失败，请手动删除或重命名 <code>install.php</code> 以确保安全！</strong>';
                            }
                            $step = 5; // 完成步骤
                        }
                    }
                }
            } catch (PDOException $e) {
                $errors[] = '数据库操作失败：' . $e->getMessage();
            } catch (Exception $e) {
                $errors[] = '发生错误：' . $e->getMessage();
            }
        }
    }
}

// 环境检查函数
function check_environment(&$issues) {
    if (version_compare(PHP_VERSION, '7.2.0', '<')) {
        $issues[] = 'PHP 版本过低，需要 PHP 7.2.0 或更高版本，当前版本：' . PHP_VERSION;
    }
    if (!extension_loaded('pdo_mysql')) {
        $issues[] = '未启用 PDO MySQL 扩展，请在 php.ini 中启用。';
    }
    if (!extension_loaded('session')) {
        $issues[] = '未启用 Session 扩展，请在 php.ini 中启用。';
    }
    if (!is_writable(__DIR__ . '/config')) {
        $issues[] = '<code>config</code> 目录不可写，请检查目录权限 (尝试设置为 755 或 777)。';
    }
    // 检查 config/database.php 是否已存在且有内容，如果安装程序要创建它
    // if (file_exists(DATABASE_CONFIG_FILE_PATH) && filesize(DATABASE_CONFIG_FILE_PATH) > 0) {
    //     $issues[] = '数据库配置文件 <code>config/database.php</code> 已存在。如果需要重新安装，请先删除它。';
    // }
    if (file_exists(CONFIG_FILE_PATH) && filesize(CONFIG_FILE_PATH) > 0) {
         // $issues[] = '主配置文件 <code>config/config.php</code> 已存在。如果需要重新安装，请先删除它。';
         // 这个检查在文件顶部已经有了，这里可以注释掉或细化
    }
}

$environment_issues = [];
if ($step === 1) {
    check_environment($environment_issues);
    // 将环境检查问题也加入到全局错误中，方便显示
    if (!empty($environment_issues)) {
        $errors = array_merge($errors, $environment_issues);
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>虚拟备案系统 - 安装引导</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', 'Arial', sans-serif;
            background: linear-gradient(135deg, #e3f0ff 0%, #b3cfff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .install-container {
            background: rgba(255,255,255,0.95);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.18);
            border-radius: 18px;
            padding: 48px 36px 36px 36px;
            width: 420px;
            max-width: 96vw;
            margin: 32px auto;
            position: relative;
        }
        .step-progress {
            display: flex;
            justify-content: space-between;
            margin-bottom: 36px;
        }
        .step {
            flex: 1;
            text-align: center;
            position: relative;
            color: #b0c4de;
            font-family: 'Orbitron', monospace;
            font-size: 1.1em;
            letter-spacing: 1px;
            transition: color 0.3s;
        }
        .step.active {
            color: #1976d2;
            font-size: 1.25em;
            text-shadow: 0 2px 8px #b3cfff;
        }
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 50%;
            right: -50%;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #b3cfff 0%, #1976d2 100%);
            z-index: 0;
            transform: translateY(-50%);
        }
        .step-icon {
            display: inline-block;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e3f0ff 0%, #b3cfff 100%);
            border: 2px solid #1976d2;
            color: #1976d2;
            font-family: 'Orbitron', monospace;
            font-size: 1.2em;
            line-height: 32px;
            margin-bottom: 6px;
            box-shadow: 0 2px 8px #b3cfff44;
            transition: background 0.3s, color 0.3s;
        }
        .step.active .step-icon {
            background: linear-gradient(135deg, #1976d2 0%, #b3cfff 100%);
            color: #fff;
        }
        .step-label {
            display: block;
            margin-top: 2px;
            font-size: 0.95em;
        }
        .step-content {
            margin-bottom: 28px;
            animation: fadeIn 0.7s cubic-bezier(.4,0,.2,1);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .focus-pointer {
            display: block;
            width: 36px;
            height: 6px;
            background: linear-gradient(90deg, #1976d2 0%, #b3cfff 100%);
            border-radius: 3px;
            margin: 0 auto 18px auto;
            animation: pointerGlow 1.2s infinite alternate;
        }
        @keyframes pointerGlow {
            from { box-shadow: 0 0 0 0 #1976d244; }
            to { box-shadow: 0 0 16px 6px #1976d288; }
        }
        .install-form input, .install-form select {
            width: 100%;
            padding: 12px 10px;
            margin-bottom: 18px;
            border: 1.5px solid #b3cfff;
            border-radius: 8px;
            font-size: 1em;
            background: #f7fbff;
            transition: border-color 0.3s, box-shadow 0.3s;
            outline: none;
        }
        .install-form input:focus {
            border-color: #1976d2;
            box-shadow: 0 0 0 2px #b3cfff88;
        }
        .install-btn {
            width: 100%;
            padding: 13px 0;
            background: linear-gradient(90deg, #1976d2 0%, #b3cfff 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-family: 'Orbitron', monospace;
            font-weight: bold;
            letter-spacing: 1px;
            box-shadow: 0 2px 8px #b3cfff44;
            cursor: pointer;
            transition: background 0.3s, box-shadow 0.3s, transform 0.1s;
        }
        .install-btn:active {
            background: linear-gradient(90deg, #1565c0 0%, #b3cfff 100%);
            transform: scale(0.98);
        }
        .install-btn:focus {
            outline: 2px solid #1976d2;
        }
        .tip-text {
            color: #1976d2;
            font-size: 0.98em;
            margin-bottom: 12px;
            text-align: center;
            letter-spacing: 0.5px;
        }
        .alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 1em;
        }
        .alert-success {
            background: #e3f7e3;
            color: #1b5e20;
        }
        .alert-danger {
            background: #ffeaea;
            color: #c62828;
        }
        .error-list {
            margin: 0 0 12px 0;
            padding: 0;
            list-style: none;
        }
        .error-list li {
            background: #ffeaea;
            color: #c62828;
            border-radius: 6px;
            margin-bottom: 6px;
            padding: 8px 12px;
        }
        .shadow {
            box-shadow: 0 4px 24px 0 #b3cfff33;
        }
    </style>
</head>
<body>
<div class="install-container shadow">
    <div class="step-progress">
        <div class="step<?php echo $step == 1 ? ' active' : ''; ?>">
            <div class="step-icon">1</div>
            <span class="step-label">环境检查</span>
        </div>
        <div class="step<?php echo $step == 2 ? ' active' : ''; ?>">
            <div class="step-icon">2</div>
            <span class="step-label">数据库配置</span>
        </div>
        <div class="step<?php echo $step == 3 ? ' active' : ''; ?>">
            <div class="step-icon">3</div>
            <span class="step-label">管理员设置</span>
        </div>
        <div class="step<?php echo $step == 4 ? ' active' : ''; ?>">
            <div class="step-icon">4</div>
            <span class="step-label">完成安装</span>
        </div>
    </div>
    <span class="focus-pointer"></span>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $err) echo $err . '<br>'; ?>
        </div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if ($step === 1): ?>
        <div class="step-content">
            <h2 style="font-family:'Orbitron',monospace;font-size:1.5em;letter-spacing:2px;color:#1976d2;text-align:center;">步骤 1: 环境检查</h2>
            <?php if (empty($environment_issues)): ?>
                <div class="alert alert-success">您的服务器环境满足所有要求。</div>
                <form method="post" class="install-form" action="install.php?step=1">
                    <div class="tip-text">下一步操作提示：点击“下一步”继续数据库配置</div>
                    <button type="submit" class="install-btn">下一步</button>
                </form>
            <?php else: ?>
                <div class="tip-text">请确保您的服务器环境满足以下要求：</div>
                <ul class="error-list">
                    <?php foreach ($environment_issues as $issue): ?>
                        <li><?php echo $issue; ?></li>
                    <?php endforeach; ?>
                </ul>
                <div class="tip-text">解决上述问题后，请刷新页面重试。</div>
            <?php endif; ?>
        </div>
    <?php elseif ($step === 2): ?>
        <div class="step-content">
            <h2 style="font-family:'Orbitron',monospace;font-size:1.5em;letter-spacing:2px;color:#1976d2;text-align:center;">步骤 2: 数据库配置</h2>
            <form method="post" class="install-form" action="install.php?step=2">
                <input type="hidden" name="db_setup" value="1">
                <input type="text" name="db_host" placeholder="数据库主机 (如localhost)" required autofocus>
                <input type="text" name="db_name" placeholder="数据库名称" required>
                <input type="text" name="db_user" placeholder="数据库用户名" required>
                <input type="password" name="db_pass" placeholder="数据库密码 (可留空)">
                <div class="tip-text">下一步操作提示：填写数据库信息后点击“下一步”</div>
                <button type="submit" class="install-btn">下一步</button>
            </form>
        </div>
    <?php elseif ($step === 3): ?>
        <div class="step-content">
            <h2 style="font-family:'Orbitron',monospace;font-size:1.5em;letter-spacing:2px;color:#1976d2;text-align:center;">步骤 3: 管理员设置</h2>
            <form method="post" class="install-form" action="install.php?step=3">
                <input type="hidden" name="admin_setup" value="1">
                <input type="text" name="admin_user" placeholder="管理员用户名" required autofocus>
                <input type="password" name="admin_pass" placeholder="管理员密码 (至少6位)" required>
                <input type="password" name="admin_pass_confirm" placeholder="确认管理员密码" required>
                <div class="tip-text">下一步操作提示：填写管理员信息后点击“下一步”</div>
                <button type="submit" class="install-btn">下一步</button>
            </form>
        </div>
    <?php elseif ($step === 4): ?>
        <div class="step-content">
            <h2 style="font-family:'Orbitron',monospace;font-size:1.5em;letter-spacing:2px;color:#1976d2;text-align:center;">步骤 4: 确认并完成安装</h2>
            <div class="tip-text">请确认配置信息无误后点击“完成安装”</div>
            <?php 
                $db_info = $_SESSION['install_db_config'] ?? null;
                $admin_info = $_SESSION['install_admin_config'] ?? null;
            ?>
            <div style="margin-bottom:18px;">
                <strong>数据库主机：</strong> <?php echo htmlspecialchars($db_info['host'] ?? 'localhost'); ?><br>
                <strong>数据库名称：</strong> <?php echo htmlspecialchars($db_info['name'] ?? ''); ?><br>
                <strong>数据库用户名：</strong> <?php echo htmlspecialchars($db_info['user'] ?? ''); ?><br>
                <strong>管理员用户名：</strong> <?php echo htmlspecialchars($admin_info['username'] ?? ''); ?><br>
            </div>
            <form method="post" class="install-form" action="install.php?step=4">
                <input type="hidden" name="finalize_install" value="1">
                <button type="submit" class="install-btn">完成安装</button>
            </form>
        </div>
    <?php elseif ($step === 5): ?>
        <div class="step-content">
            <h2 style="font-family:'Orbitron',monospace;font-size:1.5em;letter-spacing:2px;color:#1976d2;text-align:center;">安装完成</h2>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
            <div class="tip-text">请妥善保存管理员信息，并删除本安装文件以确保安全。</div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">无效的安装步骤。</div>
        <a href="install.php?step=1" class="install-btn">重新开始安装</a>
    <?php endif; ?>
</div>
<script>
    // 按钮点击动画反馈
    document.querySelectorAll('.install-btn').forEach(btn => {
        btn.addEventListener('mousedown', function() {
            this.style.transform = 'scale(0.97)';
        });
        btn.addEventListener('mouseup', function() {
            this.style.transform = '';
        });
        btn.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
    // 输入框聚焦动画
    document.querySelectorAll('.install-form input').forEach(input => {
        input.addEventListener('focus', function() {
            this.style.boxShadow = '0 0 0 2px #1976d288';
        });
        input.addEventListener('blur', function() {
            this.style.boxShadow = '';
        });
    });
</script>
</body>
</html>