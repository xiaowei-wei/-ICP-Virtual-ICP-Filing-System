<?php
// 管理员创建备案申请页面
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
$form_data = [
    'website_name' => '',
    'domain_name' => '',
    'contact_email' => '',
    'website_desc' => '',
    'qq_number' => '',
    'website_type' => ''
];

// 备案类型选项
$website_types = [
    '个人网站' => '个人网站',
    '企业网站' => '企业网站',
    '政府网站' => '政府网站',
    '教育网站' => '教育网站',
    '新闻网站' => '新闻网站',
    '电子商务' => '电子商务',
    '社交网站' => '社交网站',
    '其他' => '其他'
];

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 获取表单数据
    foreach ($form_data as $key => $value) {
        $form_data[$key] = trim($_POST[$key] ?? '');
    }
    // 验证必填字段
    $required_fields = ['website_name', 'domain_name', 'contact_email', 'website_desc', 'qq_number', 'website_type'];
    $missing_fields = [];
    foreach ($required_fields as $field) {
        if (empty($form_data[$field])) {
            $missing_fields[] = $field;
        }
    }
    if (!empty($missing_fields)) {
        $error = '请填写所有必填字段：' . implode(', ', $missing_fields);
    } else {
        // 检查敏感词
        $content_to_check = $form_data['website_name'];
        $sensitive_words = check_sensitive_words($content_to_check);
        if ($sensitive_words) {
            $error = '申请内容包含敏感词：' . implode(', ', $sensitive_words);
        } else {
            try {
                $db = db_connect();
                // 检查域名是否已存在
                $stmt = $db->prepare("SELECT id FROM icp_applications WHERE domain_name = :domain_name LIMIT 1");
                $stmt->execute(['domain_name' => $form_data['domain_name']]);
                $existing = $stmt->fetch();
                if ($existing) {
                    $error = '该域名已存在备案申请记录';
                } else {
                    // 生成申请编号
                    $application_number = generate_application_number();
                    // 插入备案申请
                    $stmt = $db->prepare("INSERT INTO icp_applications (application_number, website_name, domain_name, contact_email, website_desc, qq_number, website_type, status) VALUES (:application_number, :website_name, :domain_name, :contact_email, :website_desc, :qq_number, :website_type, :status)");
                    $stmt->execute([
                        'application_number' => $application_number,
                        'website_name' => $form_data['website_name'],
                        'domain_name' => $form_data['domain_name'],
                        'contact_email' => $form_data['contact_email'],
                        'website_desc' => $form_data['website_desc'],
                        'qq_number' => $form_data['qq_number'],
                        'website_type' => $form_data['website_type'],
                        'status' => 'pending'
                    ]);
                    $success = '备案申请创建成功';
                }
            } catch (PDOException $e) {
                error_log('创建备案申请失败: ' . $e->getMessage());
                $error = '系统错误，请稍后再试';
            }
        }
    }
}

// 页面标题
$page_title = '创建备案申请';
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
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog"></i> 系统设置
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- 主内容区域 -->
            <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">创建备案申请</h1>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">填写备案申请信息</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="" id="applicationForm">
                            <h5 class="mb-3">网站信息</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="website_name">网站名称 <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="website_name" name="website_name" value="<?php echo htmlspecialchars($form_data['website_name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="domain_name">网站域名 <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="domain_name" name="domain_name" value="<?php echo htmlspecialchars($form_data['domain_name']); ?>" placeholder="example.com" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="website_type">备案类型 <span class="text-danger">*</span></label>
                                        <select class="form-control" id="website_type" name="website_type" required>
                                            <option value="" <?php echo empty($form_data['website_type']) ? 'selected' : ''; ?>>请选择备案类型</option>
                                            <?php foreach ($website_types as $key => $value): ?>
                                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $form_data['website_type'] === $key ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($value); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="contact_email">联系邮箱 <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($form_data['contact_email']); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="qq_number">QQ号码 <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="qq_number" name="qq_number" value="<?php echo htmlspecialchars($form_data['qq_number']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <!-- 预留位置，可以添加其他字段 -->
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="website_desc">网站描述 <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="website_desc" name="website_desc" rows="3" required><?php echo htmlspecialchars($form_data['website_desc']); ?></textarea>
                            </div>
                            <div class="form-group form-check">
                                <input type="checkbox" class="form-check-input" id="agreement" name="agreement" required>
                                <label class="form-check-label" for="agreement">我已阅读并同意<a href="#" data-toggle="modal" data-target="#agreementModal">《备案服务协议》</a> <span class="text-danger">*</span></label>
                            </div>
                            <button type="submit" class="btn btn-primary">提交申请</button>
                            <button type="reset" class="btn btn-secondary ml-2">重置表单</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- 协议模态框 -->
    <div class="modal fade" id="agreementModal" tabindex="-1" role="dialog" aria-labelledby="agreementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="agreementModalLabel">备案服务协议</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <h5>一、总则</h5>
                    <p>1.1 本协议是您与本备案系统（以下简称"我们"）之间关于您使用我们提供的网站备案服务所订立的协议。</p>
                    <p>1.2 您在申请备案前，应当认真阅读本协议，充分理解各条款内容，特别是免除或者限制责任的条款。</p>
                    
                    <h5>二、备案服务内容</h5>
                    <p>2.1 我们提供网站备案申请的受理、审核、提交等服务。</p>
                    <p>2.2 您理解并同意，备案最终审核结果取决于相关管理部门，我们不对最终审核结果承担责任。</p>
                    
                    <h5>三、用户义务</h5>
                    <p>3.1 您保证提交的所有备案信息真实、准确、完整，不存在虚假记载、误导性陈述或重大遗漏。</p>
                    <p>3.2 您保证网站内容符合国家法律法规，不含有违法违规信息。</p>
                    <p>3.3 您同意遵守《互联网信息服务管理办法》等相关法律法规的规定。</p>
                    
                    <h5>四、隐私保护</h5>
                    <p>4.1 我们重视对您个人信息的保护，在备案过程中收集的信息仅用于备案目的。</p>
                    <p>4.2 未经您的同意，我们不会向第三方披露您的个人信息，但法律法规另有规定的除外。</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">关闭</button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // 表单验证
        $('#applicationForm').on('submit', function(e) {
            var isValid = true;
            
            // 检查必填字段
            $(this).find('[required]').each(function() {
                if ($(this).val().trim() === '') {
                    isValid = false;
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
            
            // 检查协议是否勾选
            if (!$('#agreement').prop('checked')) {
                isValid = false;
                $('#agreement').addClass('is-invalid');
            } else {
                $('#agreement').removeClass('is-invalid');
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('请填写所有必填字段并同意协议');
            }
        });
        
        // 重置表单时清除验证样式
        $('button[type="reset"]').click(function() {
            $('#applicationForm').find('.is-invalid').removeClass('is-invalid');
        });
    });
    </script>
</body>
</html>