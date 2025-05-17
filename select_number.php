<?php
// 启动输出缓冲，确保可以在需要时清除所有输出
ob_start();

require_once 'includes/config.php';
require_once 'includes/functions.php';

// 注意：config.php 已经启动了会话，这里不需要再次启动
// session_start() 已在 config.php 中调用

$page_title = '选择您的备案号';
$error = '';
$success = '';

// 数据库表名
define('TABLE_SELECTABLE_NUMBERS', 'selectable_application_numbers');

// 检查是否有从 apply.php 传递过来的申请数据
if (!isset($_SESSION['application_data'])) {
    // 如果没有申请数据，可能用户直接访问了此页面，重定向回申请页面
    header('Location: apply.php');
    exit;
}

$application_data = $_SESSION['application_data'];

// 获取可用的备案号
$available_numbers = [];
try {
    $db = db_connect();
    $stmt = $db->prepare("SELECT id, application_number FROM " . TABLE_SELECTABLE_NUMBERS . " WHERE is_available = TRUE ORDER BY RAND() LIMIT 20"); // 随机获取最多20个可用号码
    $stmt->execute();
    $available_numbers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = '获取可选备案号失败：' . $e->getMessage();
    // 记录详细错误供调试
    error_log('select_number.php: PDOException while fetching available numbers - ' . $e->getMessage());
}

// 添加调试日志，记录表单提交
error_log('select_number.php: POST请求接收，开始处理表单提交');

// 处理用户选择备案号的提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['selected_number_id'])) {
        // Existing logic for selected_number_id
        $selected_number_id = (int)$_POST['selected_number_id'];
        $selected_application_number = '';

        if (empty($selected_number_id)) {
            $error = '请选择一个备案号。';
        }

        if (empty($error)) {
            try {
                $db = db_connect();
                $db->beginTransaction();

                // 1. 验证所选号码是否仍然可用并获取其号码字符串
                $stmt_check = $db->prepare("SELECT application_number FROM " . TABLE_SELECTABLE_NUMBERS . " WHERE id = :id AND is_available = TRUE FOR UPDATE");
                $stmt_check->execute([':id' => $selected_number_id]);
                $number_row = $stmt_check->fetch(PDO::FETCH_ASSOC);

                if ($number_row) {
                    $selected_application_number = $number_row['application_number'];

                    // 2. 将申请数据插入 icp_applications 表
                    $status = defined('STATUS_PENDING') ? STATUS_PENDING : '审核中';
                    $stmt_insert_app = $db->prepare(
                        "INSERT INTO icp_applications (application_number, website_type, website_name, domain_name, contact_email, website_desc, qq_number, status, user_ip) 
                         VALUES (:application_number, :website_type, :website_name, :domain_name, :contact_email, :website_desc, :qq_number, :status, :user_ip)"
                    );
                    
                    $insert_params = [
                        ':application_number' => $selected_application_number,
                        ':website_type' => $application_data['website_type'],
                        ':website_name' => $application_data['website_name'],
                        ':domain_name' => $application_data['domain_name'],
                        ':contact_email' => $application_data['contact_email'],
                        ':website_desc' => $application_data['website_desc'],
                        ':qq_number' => $application_data['qq_number'],
                        ':status' => $status,
                        ':user_ip' => $application_data['user_ip']
                    ];

                    if ($stmt_insert_app->execute($insert_params)) {
                        // 3. 更新 selectable_application_numbers 表，将所选号码标记为不可用
                        $stmt_update_selectable = $db->prepare("UPDATE " . TABLE_SELECTABLE_NUMBERS . " SET is_available = FALSE WHERE id = :id");
                        if ($stmt_update_selectable->execute([':id' => $selected_number_id])) {
                            $db->commit();
                            // 在重定向前清除会话中的申请数据
                            unset($_SESSION['application_data']); 
                            // 记录成功日志
                            error_log('select_number.php: 成功处理手动选择备案号，准备重定向到apply_result.php，备案号：' . $selected_application_number);
                            // 确保在重定向前没有任何输出
                            ob_end_clean(); // 完全清除所有输出缓冲
                            header("Location: apply_result.php?success=1&app_number=" . urlencode($selected_application_number));
                            exit;
                        } else {
                            $db->rollBack();
                            $error = '更新备案号状态失败。请重试。';
                            error_log('select_number.php: Failed to update selectable number status for ID: ' . $selected_number_id);
                        }
                    } else {
                        $db->rollBack();
                        $error = '提交申请失败，请重试。';
                        error_log('select_number.php: Failed to insert application. Data: ' . print_r($application_data, true) . ' Selected Number: ' . $selected_application_number);
                    }
                } else {
                    $db->rollBack();
                    $error = '您选择的备案号已被占用或无效，请重新选择。';
                    // 刷新可选号码列表
                    $stmt_refresh = $db->prepare("SELECT id, application_number FROM " . TABLE_SELECTABLE_NUMBERS . " WHERE is_available = TRUE ORDER BY RAND() LIMIT 20");
                    $stmt_refresh->execute();
                    $available_numbers = $stmt_refresh->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = '处理您的选择时发生数据库错误：' . $e->getMessage();
                error_log('select_number.php: PDOException during number selection processing - ' . $e->getMessage());
            }
        }
    } elseif (isset($_POST['auto_assign_number']) && $_POST['auto_assign_number'] == '1') {
        // Logic for auto-assigning number
        if (!isset($_SESSION['application_data'])) {
            // This should ideally not happen if session is managed correctly
            // and user followed the flow from apply.php
            error_log('select_number.php: auto_assign_number POST received but no application_data in session.');
            header('Location: apply.php?error=session_expired');
            exit;
        }
        $application_data_auto = $_SESSION['application_data'];
        $generated_icp_number = '';

        try {
            $db = db_connect();
            
            // Attempt to get prefix from system_config, fallback to default
            // Assumes get_system_config function exists in functions.php
            $icp_prefix = '京ICP备'; // Default prefix
            if (function_exists('get_system_config')) {
                 $configured_prefix = get_system_config('auto_icp_prefix');
                 if ($configured_prefix !== null && !empty(trim($configured_prefix))) {
                    $icp_prefix = trim($configured_prefix);
                 }
            } else {
                error_log('select_number.php: get_system_config function not found. Using default ICP prefix.');
            }

            $max_retries = 10;
            $retry_count = 0;

            do {
                $timestamp_part = date('YmdHis');
                // Generate a 6-digit random number part to increase uniqueness
                $random_part = '';
                for ($i = 0; $i < 6; $i++) {
                    $random_part .= random_int(0, 9);
                }
                $generated_icp_number = $icp_prefix . $timestamp_part . $random_part . '号';

                $stmt_check_exists = $db->prepare("SELECT 1 FROM icp_applications WHERE application_number = :app_num");
                $stmt_check_exists->execute([':app_num' => $generated_icp_number]);
                
                if ($stmt_check_exists->fetchColumn()) {
                    $generated_icp_number = ''; // Reset if exists
                    $retry_count++;
                    if ($retry_count >= $max_retries) {
                        $error = '无法生成唯一的备案号，系统繁忙，请稍后重试或选择现有号码。';
                        error_log('select_number.php: Max retries reached for generating unique ICP number.');
                        break;
                    }
                    usleep(100000 * $retry_count); // Wait a bit longer each retry (0.1s, 0.2s, ...)
                } else {
                    break; // Unique number found
                }
            } while ($retry_count < $max_retries);

            if (empty($generated_icp_number) && empty($error)) {
                 $error = '自动分配备案号失败，未能生成唯一号码。请尝试手动选择。';
            }

            if (empty($error)) {
                $db->beginTransaction();
                $status = defined('STATUS_PENDING') ? STATUS_PENDING : '审核中';
                $stmt_insert_app_auto = $db->prepare(
                    "INSERT INTO icp_applications (application_number, website_type, website_name, domain_name, contact_email, website_desc, qq_number, status, user_ip) 
                     VALUES (:application_number, :website_type, :website_name, :domain_name, :contact_email, :website_desc, :qq_number, :status, :user_ip)"
                );
                
                $insert_params_auto = [
                    ':application_number' => $generated_icp_number,
                    ':website_type' => $application_data_auto['website_type'],
                    ':website_name' => $application_data_auto['website_name'],
                    ':domain_name' => $application_data_auto['domain_name'],
                    ':contact_email' => $application_data_auto['contact_email'],
                    ':website_desc' => $application_data_auto['website_desc'],
                    ':qq_number' => $application_data_auto['qq_number'],
                    ':status' => $status,
                    ':user_ip' => $application_data_auto['user_ip']
                ];

                if ($stmt_insert_app_auto->execute($insert_params_auto)) {
                    $db->commit();
                    // 在重定向前清除会话中的申请数据
                    unset($_SESSION['application_data']);
                    // 记录成功日志
                    error_log('select_number.php: 成功处理自动分配备案号，准备重定向到apply_result.php，备案号：' . $generated_icp_number);
                    // 确保在重定向前没有任何输出
                    ob_end_clean(); // 完全清除所有输出缓冲
                    header("Location: apply_result.php?success=1&app_number=" . urlencode($generated_icp_number) . "&auto=1");
                    exit;
                } else {
                    $db->rollBack();
                    $error = '提交申请失败（自动分配），请重试。';
                    error_log('select_number.php: Failed to insert auto-assigned application. Data: ' . print_r($application_data_auto, true) . ' Generated Number: ' . $generated_icp_number . ' DB Error: ' . implode(':', $stmt_insert_app_auto->errorInfo()));
                }
            }
        } catch (PDOException $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $error = '处理自动分配备案号时发生数据库错误：' . $e->getMessage();
            error_log('select_number.php: PDOException during auto-assign processing - ' . $e->getMessage());
        } catch (Exception $e) { // Catch random_int exception if /dev/urandom is not available for example
            $error = '生成随机备案号时发生内部错误：' . $e->getMessage();
            error_log('select_number.php: Exception during random number generation for auto-assign - ' . $e->getMessage());
        }
        // If an error occurred, it will be displayed by the existing error display logic below
    }
}


require_once 'includes/header.php'; // 包含通用的头部HTML
?>

<style>
/* 沿用 apply.php 的部分样式，并添加选号页面的特定样式 */
body {
    min-height: 100vh;
    background: url('static/bg.jpg') center center/cover no-repeat fixed, #f6f8fa;
    font-family: 'Arial', sans-serif;
}
.bg-blur {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 0;
    background: rgba(255,255,255,0.6);
    backdrop-filter: blur(2px);
}
.brand-bar { /* 与 apply.php 保持一致 */
    position: relative; z-index: 2; display: flex; align-items: center; justify-content: space-between;
    padding: 32px 8vw 0 8vw;
}
.brand-logo { font-size: 2.2rem; font-weight: bold; color: #4a7cff; letter-spacing: 1px; text-shadow: 0 2px 8px rgba(74,124,255,0.08); }
.brand-nav { display: flex; gap: 2.5rem; font-size: 1.1rem; }
.brand-nav a { color: #333; text-decoration: none; transition: color .2s; }
.brand-nav a:hover { color: #4a7cff; }

.center-content {
    position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; justify-content: center;
    min-height: 70vh; padding: 20px;
}
.select-number-title {
    font-size: 2.1rem; font-weight: 700; color: #4a7cff; margin-bottom: 1rem;
    text-shadow: 0 2px 12px rgba(74,124,255,0.08);
}
.select-number-desc {
    font-size: 1.1rem; color: #444; margin-bottom: 2rem; letter-spacing: 1px; text-align: center;
}
.select-number-box {
    width: 100%; max-width: 600px; background: #fff; border-radius: 1.2rem;
    box-shadow: 0 4px 32px rgba(74,124,255,0.08); padding: 2.5rem;
    margin-bottom: 2rem;
}
.number-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.number-option {
    background: #f6f8fa; border-radius: 0.8rem; padding: 0.8rem;
    text-align: center; font-weight: bold; cursor: pointer;
    transition: background-color 0.2s, color 0.2s, box-shadow 0.2s;
    border: 2px solid transparent;
}
.number-option:hover {
    background-color: #e9efff;
    color: #4a7cff;
}
.number-option.selected {
    background-color: #4a7cff;
    color: #fff;
    border-color: #3a6ae0;
    box-shadow: 0 0 10px rgba(74,124,255,0.3);
}
.icp-btn {
    display: inline-block; width: 100%; padding: 0.9rem 0; border-radius: 2rem; border: none;
    background: linear-gradient(90deg,#4a7cff 0%,#6ec6ff 100%);
    color: #fff; font-size: 1.1rem; font-weight: 600;
    box-shadow: 0 2px 8px rgba(74,124,255,0.08); transition: background .2s; margin-top: 1rem;
}
.icp-btn:hover { background: linear-gradient(90deg,#6ec6ff 0%,#4a7cff 100%); color: #fff; }
.icp-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
}
.alert-message {
    background: #f6f8fa; color: #dc3545; border-radius: 1rem; padding: 1rem 1.5rem;
    margin-bottom: 1.5rem; text-align: center; font-size: 1rem;
}
.alert-message.success {
    color: #28a745;
}
.no-numbers-message {
    text-align: center; padding: 20px; background-color: #fff3cd; color: #856404; border-radius: .5rem; margin-bottom: 1.5rem;
}

@media (max-width: 600px) {
    .brand-bar {padding: 24px 4vw 0 4vw;}
    .select-number-box {padding: 1.5rem 1rem;}
    .number-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); }
}
</style>

<div class="bg-blur"></div>
<div class="brand-bar">
    <div class="brand-logo"><?php echo htmlspecialchars($site_brand_logo); ?></div>
    <nav class="brand-nav">
        <a href="index.php">主页</a>
        <a href="about.php">关于</a>
        <a href="apply.php">重新申请</a>
        <a href="search.php">备案查询</a>
        <a href="public_info.php">公示信息</a>
        <a href="apply_status.php">备案申请进度</a>
    </nav>
</div>

<div class="center-content">
    <div class="select-number-title"><?php echo $page_title; ?></div>
    <div class="select-number-desc">请从下方选择一个您心仪的备案号。</div>

    <div class="select-number-box">
        <?php if ($error): ?>
            <div class="alert-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert-message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (!empty($available_numbers)): ?>
            <form id="selectNumberForm" method="post" action="select_number.php">
                <input type="hidden" name="selected_number_id" id="selected_number_id" value="">
                <div class="number-grid">
                    <?php foreach ($available_numbers as $number): ?>
                        <div class="number-option" data-id="<?php echo $number['id']; ?>" data-number="<?php echo htmlspecialchars($number['application_number']); ?>">
                            <?php echo htmlspecialchars($number['application_number']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="icp-btn" id="submitBtn" disabled>选定此号并提交申请</button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem; margin-bottom: 1rem; color: #555; font-size: 0.9rem;">或</div>
        
        <?php elseif (empty($available_numbers) && empty($error)): // 如果没有预设号码且无其他错误，显示提示 ?>
            <div class="no-numbers-message" style="margin-bottom: 1rem;">
                <p>抱歉，当前没有预设的可选备案号。</p>
                <p>您可以选择由系统自动分配一个备案号并提交申请。</p>
            </div>
        <?php endif; ?>

        <?php // 自动分配表单始终显示 (除非有阻止页面渲染的全局 $error) ?>
        <?php if (empty($error) || !empty($available_numbers)): // 仅当没有全局错误，或者有可选号码时（即使有可选号码错误，也显示自动分配）?>
        <form method="POST" action="select_number.php" id="autoAssignForm" style="<?php if(empty($available_numbers)) echo 'margin-top: 1.5rem;'; ?>">
            <input type="hidden" name="auto_assign_number" value="1">
            <button type="submit" class="icp-btn">由系统自动分配备案号并提交申请</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const numberOptions = document.querySelectorAll('.number-option');
    const selectedNumberInput = document.getElementById('selected_number_id');
    const submitBtn = document.getElementById('submitBtn');

    numberOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove 'selected' class from all options
            numberOptions.forEach(opt => opt.classList.remove('selected'));
            // Add 'selected' class to the clicked option
            this.classList.add('selected');
            // Set the hidden input value
            selectedNumberInput.value = this.dataset.id;
            // Enable submit button
            submitBtn.disabled = false;
        });
    });

    // Optional: Add a confirmation before submitting
    const form = document.getElementById('selectNumberForm');
    if(form){
        form.addEventListener('submit', function(e){
            if(!selectedNumberInput.value){
                alert('请先选择一个备案号。');
                e.preventDefault();
                return;
            }
            if(!confirm('您确定选择此备案号吗？选择后将提交您的备案申请。')){
                e.preventDefault();
            }
        });
    }
});

// Function to get site brand logo (if not already in functions.php)
// This is a placeholder, assuming get_site_brand_logo() exists or will be added to functions.php
<?php if (!function_exists('get_site_brand_logo')): ?>
function get_site_brand_logo() {
    // Placeholder: In a real scenario, this would fetch from DB or config
    // For now, using the same logic as in apply.php if needed, or a default
    global $db_logo;
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
    return $site_brand_logo;
}
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; // 包含通用的页脚HTML ?>