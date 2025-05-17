<?php
// 公共函数文件

/**
 * 生成随机字符串
 * @param int $length 字符串长度
 * @return string 随机字符串
 */
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * 生成备案申请编号
 * @return string 备案申请编号
 */
function generate_application_number() {
    $prefix = 'ICP';
    $date = date('Ymd');
    $random = generate_random_string(6);
    return $prefix . $date . $random;
}

/**
 * 生成ICP备案号
 * @return string ICP备案号
 */
function generate_icp_number() {
    $prefix = get_system_config('icp_number_prefix', '京ICP备');
    $digits = get_system_config('icp_number_digits', 8);
    $number = '';
    for ($i = 0; $i < $digits; $i++) {
        $number .= rand(0, 9);
    }
    return $prefix . $number . '号';
}

/**
 * 检查字符串是否包含敏感词
 * @param string $content 要检查的内容
 * @return array|false 包含的敏感词数组，如果没有则返回false
 */
/**
 * 数据库连接函数
 * @return PDO 数据库连接对象
 */
function db_connect() {
    error_log('db_connect() called.'); // 新增日志
    static $pdo = null;
    if ($pdo === null) {
        // require_once __DIR__ . '/config.php'; // 移除此行
        error_log('Attempting to establish PDO connection...'); // 新增日志
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            error_log('PDO connection established successfully.'); // 新增日志
        } catch (PDOException $e) {
            error_log('PDO connection failed in db_connect(): ' . $e->getMessage() . ' (Code: ' . $e->getCode() . ')'); // 修改日志
            throw $e;
        }
    }
    return $pdo;
}

function check_sensitive_words($content) {
    try {
        $db = db_connect();
        $stmt = $db->query("SELECT word FROM sensitive_words");
        $words = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $found = [];
        foreach ($words as $word) {
            if (stripos($content, $word) !== false) {
                $found[] = $word;
            }
        }
        
        return !empty($found) ? $found : false;
    } catch (PDOException $e) {
        error_log('检查敏感词失败: ' . $e->getMessage());
        return false;
    }
}

/**
 * 记录操作日志
 * @param int $admin_id 管理员ID
 * @param string $action 操作动作
 * @param string $target_type 目标类型
 * @param int $target_id 目标ID
 * @param string $details 详细信息
 * @return bool 是否成功
 */
function log_operation($admin_id, $action, $target_type = null, $target_id = null, $details = null) {
    try {
        $db = db_connect();
        $stmt = $db->prepare("INSERT INTO operation_logs (admin_id, action, target_type, target_id, details, ip_address) VALUES (:admin_id, :action, :target_type, :target_id, :details, :ip_address)");
        
        return $stmt->execute([
            'admin_id' => $admin_id,
            'action' => $action,
            'target_type' => $target_type,
            'target_id' => $target_id,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (PDOException $e) {
        error_log('记录操作日志失败: ' . $e->getMessage());
        return false;
    }
}

/**
 * 获取备案申请状态文本
 * @param string $status 状态代码
 * @return string 状态文本
 */
function get_status_text($status) {
    $status_map = [
        STATUS_PENDING => '审核中',
        STATUS_APPROVED => '已通过',
        STATUS_REJECTED => '已驳回'
    ];
    
    return $status_map[$status] ?? '未知状态';
}

/**
 * 格式化日期时间
 * @param string $datetime 日期时间字符串
 * @param string $format 格式化模式
 * @return string 格式化后的日期时间
 */
function format_datetime($datetime, $format = 'Y-m-d H:i:s') {
    $dt = new DateTime($datetime);
    return $dt->format($format);
}

/**
 * 隐藏敏感信息
 * @param string $str 原始字符串
 * @param int $start 开始保留的字符数
 * @param int $end 结尾保留的字符数
 * @param string $mask 掩码字符
 * @return string 处理后的字符串
 */
function mask_string($str, $start = 3, $end = 4, $mask = '*') {
    $length = mb_strlen($str);
    if ($length <= $start + $end) {
        return $str;
    }
    
    $masked_length = $length - $start - $end;
    $masked_part = str_repeat($mask, $masked_length);
    
    return mb_substr($str, 0, $start) . $masked_part . mb_substr($str, -$end);
}

/**
 * 分页函数
 * @param int $total 总记录数
 * @param int $page 当前页码
 * @param int $limit 每页记录数
 * @param string $url 链接URL模板
 * @return string 分页HTML
 */
function pagination($total, $page, $limit, $url = '?page={page}') {
    $total_pages = ceil($total / $limit);
    if ($total_pages <= 1) {
        return '';
    }
    
    $page = max(1, min($page, $total_pages));
    
    $html = '<nav aria-label="分页导航"><ul class="pagination justify-content-center">';
    
    // 上一页
    if ($page > 1) {
        $prev_url = str_replace('{page}', $page - 1, $url);
        $html .= '<li class="page-item"><a class="page-link" href="' . $prev_url . '">&laquo; 上一页</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">&laquo; 上一页</a></li>';
    }
    
    // 页码
    $start = max(1, $page - 2);
    $end = min($total_pages, $page + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        $page_url = str_replace('{page}', $i, $url);
        if ($i == $page) {
            $html .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $page_url . '">' . $i . '</a></li>';
        }
    }
    
    // 下一页
    if ($page < $total_pages) {
        $next_url = str_replace('{page}', $page + 1, $url);
        $html .= '<li class="page-item"><a class="page-link" href="' . $next_url . '">下一页 &raquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">下一页 &raquo;</a></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * 获取网站品牌LOGO
 * @return string LOGO图片路径或空字符串
 */
function get_site_brand_logo() {
    // 临时返回空字符串，后续可以从配置或数据库中获取
    return ''; 
}