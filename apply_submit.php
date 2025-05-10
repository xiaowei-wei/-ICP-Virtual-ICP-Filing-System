<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_type = trim($_POST['company_type'] ?? '');
    $site_name = trim($_POST['website_name'] ?? '');
    $domain = trim($_POST['domain_name'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $website_desc = trim($_POST['website_desc'] ?? '');
    $qq_number = trim($_POST['qq_number'] ?? '');
    $status = 'pending';
    $created_at = date('Y-m-d H:i:s');
    $application_number = uniqid('ICP');
    if ($site_name && $domain && $contact_email && $website_desc && $qq_number) {
        $conn = db_connect();
        $stmt = $conn->prepare("INSERT INTO icp_applications (application_number, website_name, domain_name, contact_email, website_desc, qq_number, status, created_at) VALUES (:application_number, :website_name, :domain_name, :contact_email, :website_desc, :qq_number, :status, :created_at)");
        $params = [
            'application_number' => $application_number,
            'website_name' => $site_name,
            'domain_name' => $domain,
            'contact_email' => $contact_email,
            'website_desc' => $website_desc,
            'qq_number' => $qq_number,
            'status' => $status,
            'created_at' => $created_at
        ];
        if ($stmt->execute($params)) {
            header('Location: apply_result.php?success=1');
            exit;
        } else {
            $errorMsg = $stmt->error ? $stmt->error : '未知错误';
            header('Location: apply_result.php?success=0&error=' . urlencode($errorMsg));
            exit;
        }
    } else {
        $missing = [];
        if (!$site_name) $missing[] = 'website_name';
        if (!$domain) $missing[] = 'domain_name';
        if (!$contact_email) $missing[] = 'contact_email';
        if (!$website_desc) $missing[] = 'website_desc';
        if (!$qq_number) $missing[] = 'qq_number';
        $errorMsg = '缺少必填字段: ' . implode(', ', $missing);
        header('Location: apply_result.php?success=0&error=' . urlencode($errorMsg));
        exit;
    }
} else {
    header('Location: apply.php');
    exit;
}