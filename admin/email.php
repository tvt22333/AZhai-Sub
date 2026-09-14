<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/email.php';

require_admin();

$pageTitle = '邮箱管理';

$db = db();

$tab = $_GET['tab'] ?? 'settings';

$allowedTabs = [
    'settings',
    'templates',
    'logs',
];

if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'settings';
}



$defaultTemplates = [
    [
        'template_key' => 'verify_email',
        'name' => '注册邮箱验证',
        'subject' => '请验证你的邮箱 - {{site_name}}',
        'body' => '
<h2 style="margin:0 0 18px;">你好，{{username}}</h2>

<p>
感谢注册 {{site_name}}。
</p>

<p>
请点击下面的按钮完成邮箱验证：
</p>

<p style="margin:24px 0;">
<a href="{{verify_url}}" style="display:inline-block;padding:12px 22px;background:#ff6b9d;color:#fff;text-decoration:none;border-radius:10px;">
验证邮箱
</a>
</p>

<p style="font-size:13px;color:#888;">
如果按钮无法点击，可以复制下面的地址到浏览器打开：
</p>

<p style="font-size:13px;word-break:break-all;color:#888;">
{{verify_url}}
</p>

<p style="font-size:13px;color:#999;">
验证链接有效期为 {{expires_minutes}} 分钟。
</p>
',
        'enabled' => 1,
    ],
    [
        'template_key' => 'password_reset',
        'name' => '密码重置',
        'subject' => '密码重置 - {{site_name}}',
        'body' => '
<h2 style="margin:0 0 18px;">你好，{{username}}</h2>

<p>
我们收到了你的密码重置请求。
</p>

<p>
如果这是你本人操作，请点击下面的按钮设置新密码：
</p>

<p style="margin:24px 0;">
<a href="{{reset_url}}" style="display:inline-block;padding:12px 22px;background:#ff6b9d;color:#fff;text-decoration:none;border-radius:10px;">
重置密码
</a>
</p>

<p style="font-size:13px;color:#888;">
如果按钮无法点击，可以复制下面的地址到浏览器打开：
</p>

<p style="font-size:13px;word-break:break-all;color:#888;">
{{reset_url}}
</p>

<p style="font-size:13px;color:#999;">
重置链接有效期为 {{expires_minutes}} 分钟。
</p>

<p style="font-size:13px;color:#999;">
如果不是你本人操作，请忽略这封邮件。
</p>
',
        'enabled' => 1,
    ],
    [
        'template_key' => 'domain_approved',
        'name' => '域名申请通过',
        'subject' => '域名申请已通过 - {{site_name}}',
        'body' => '
<h2 style="margin:0 0 18px;">域名申请已通过</h2>

<p>
你好，{{username}}。
</p>

<p>
你的二级域名申请已经通过审核。
</p>

<div style="margin:20px 0;padding:16px;background:#faf7f8;border-radius:10px;">
<strong>{{domain}}</strong>
</div>

<p>
现在你可以进入控制台管理这个域名以及 DNS 解析记录。
</p>

<p style="margin:24px 0;">
<a href="{{site_url}}/dashboard/" style="display:inline-block;padding:12px 22px;background:#ff6b9d;color:#fff;text-decoration:none;border-radius:10px;">
进入控制台
</a>
</p>
',
        'enabled' => 1,
    ],
    [
        'template_key' => 'domain_rejected',
        'name' => '域名申请拒绝',
        'subject' => '域名申请未通过 - {{site_name}}',
        'body' => '
<h2 style="margin:0 0 18px;">域名申请未通过</h2>

<p>
你好，{{username}}。
</p>

<p>
很抱歉，你提交的二级域名申请未通过审核。
</p>

<div style="margin:20px 0;padding:16px;background:#faf7f8;border-radius:10px;">
<strong>{{domain}}</strong>
</div>

<p>
审核原因：
</p>

<div style="margin:12px 0 20px;padding:16px;background:#fff5f7;border-radius:10px;color:#666;">
{{review_reason}}
</div>

<p style="margin:24px 0;">
<a href="{{site_url}}/dashboard/" style="display:inline-block;padding:12px 22px;background:#ff6b9d;color:#fff;text-decoration:none;border-radius:10px;">
进入控制台
</a>
</p>
',
        'enabled' => 1,
    ],
];



$templateCount = (int)$db->query("
    SELECT COUNT(*)
    FROM email_templates
")->fetchColumn();

if ($templateCount === 0) {

    $insertTemplate = $db->prepare("
        INSERT INTO email_templates (
            template_key,
            name,
            subject,
            body,
            enabled,
            updated_at
        ) VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            NOW()
        )
    ");

    foreach ($defaultTemplates as $template) {

        $insertTemplate->execute([
            $template['template_key'],
            $template['name'],
            $template['subject'],
            $template['body'],
            $template['enabled'],
        ]);
    }
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = $_POST['action'] ?? '';

    

    if ($action === 'save_settings') {

        $host = trim($_POST['smtp_host'] ?? '');
        $port = (int)($_POST['smtp_port'] ?? 587);
        $encryption = trim($_POST['smtp_encryption'] ?? 'tls');
        $username = trim($_POST['smtp_username'] ?? '');
        $password = (string)($_POST['smtp_password'] ?? '');

        $fromName = trim($_POST['from_name'] ?? '');
        $fromEmail = trim($_POST['from_email'] ?? '');
        $replyTo = trim($_POST['reply_to'] ?? '');

        $enabled = isset($_POST['enabled']) ? 1 : 0;

        $errors = [];

        if ($host === '') {
            $errors[] = 'SMTP 服务器不能为空';
        }

        if ($port < 1 || $port > 65535) {
            $errors[] = 'SMTP 端口无效';
        }

        if (!in_array($encryption, ['none', 'ssl', 'tls'], true)) {
            $errors[] = 'SMTP 加密方式无效';
        }

        if (
            $fromEmail === ''
            || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)
        ) {
            $errors[] = '发件人邮箱格式不正确';
        }

        if (
            $replyTo !== ''
            && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)
        ) {
            $errors[] = '回复邮箱格式不正确';
        }

        if (!$errors) {

            $currentStmt = $db->query("
                SELECT *
                FROM email_settings
                WHERE id = 1
                LIMIT 1
            ");

            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

            if ($password === '' && $current) {
                $password = decrypt_secret(
                    (string)($current['smtp_password'] ?? '')
                );
            }

            $encryptedPassword = encrypt_secret($password);

            if ($current) {

                $stmt = $db->prepare("
                    UPDATE email_settings
                    SET
                        smtp_host = ?,
                        smtp_port = ?,
                        smtp_encryption = ?,
                        smtp_username = ?,
                        smtp_password = ?,
                        from_name = ?,
                        from_email = ?,
                        reply_to = ?,
                        enabled = ?,
                        updated_at = NOW()
                    WHERE id = 1
                ");

                $stmt->execute([
                    $host,
                    $port,
                    $encryption,
                    $username,
                    $encryptedPassword,
                    $fromName,
                    $fromEmail,
                    $replyTo !== '' ? $replyTo : null,
                    $enabled,
                ]);

            } else {

                $stmt = $db->prepare("
                    INSERT INTO email_settings (
                        id,
                        smtp_host,
                        smtp_port,
                        smtp_encryption,
                        smtp_username,
                        smtp_password,
                        from_name,
                        from_email,
                        reply_to,
                        enabled,
                        created_at,
                        updated_at
                    ) VALUES (
                        1,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        NOW(),
                        NOW()
                    )
                ");

                $stmt->execute([
                    $host,
                    $port,
                    $encryption,
                    $username,
                    $encryptedPassword,
                    $fromName,
                    $fromEmail,
                    $replyTo !== '' ? $replyTo : null,
                    $enabled,
                ]);
            }

            log_action(
                null,
                current_admin_id(),
                'update_settings',
                'email_settings'
            );

            flash('success', '邮箱 SMTP 配置已保存');

            redirect('/admin/email.php?tab=settings');
        }

        flash('error', implode(' ', $errors));

        redirect('/admin/email.php?tab=settings');
    }

    

    if ($action === 'test_email') {

        $to = trim($_POST['test_email'] ?? '');

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {

            flash('error', '请输入正确的测试邮箱地址');

            redirect('/admin/email.php?tab=settings');
        }

        $stmt = $db->query("
            SELECT *
            FROM email_settings
            WHERE id = 1
            LIMIT 1
        ");

        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$settings) {

            flash('error', '请先保存 SMTP 配置');

            redirect('/admin/email.php?tab=settings');
        }

        if ((int)$settings['enabled'] !== 1) {

            flash('error', '邮箱系统当前处于关闭状态，请先启用');

            redirect('/admin/email.php?tab=settings');
        }

        try {

            $subject = 'AZhai Sub 邮件系统测试';

            $html = '
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:32px;background:#faf7f8;color:#333;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Microsoft YaHei,sans-serif;line-height:1.8;">
<div style="max-width:620px;margin:auto;background:#fff;border:1px solid #eee;border-radius:16px;padding:30px;">
<h2 style="margin:0 0 16px;">邮件系统测试成功</h2>
<p>这是一封来自 AZhai Sub 的测试邮件。</p>
<p>如果你能正常收到这封邮件，说明 SMTP 配置基本正常。</p>
<hr style="border:0;border-top:1px solid #eee;margin:24px 0;">
<p style="font-size:13px;color:#999;">AZhai Sub · 邮件系统</p>
</div>
</body>
</html>
';

            $result = send_email(
                $to,
                $subject,
                $html
            );

            if ($result) {

                log_action(
                    null,
                    current_admin_id(),
                    'test_email',
                    $to
                );

                flash(
                    'success',
                    '测试邮件发送成功，请检查收件箱'
                );

            } else {

                flash(
                    'error',
                    '测试邮件发送失败，请检查 SMTP 配置及邮件日志'
                );
            }

        } catch (Throwable $e) {

            error_log(
                '[AZhai Sub] test email error: '
                . $e->getMessage()
            );

            flash(
                'error',
                '测试邮件发送失败：' . $e->getMessage()
            );
        }

        redirect('/admin/email.php?tab=settings');
    }

    

    if ($action === 'save_template') {

        $id = (int)($_POST['id'] ?? 0);

        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $enabled = isset($_POST['enabled']) ? 1 : 0;

        if ($id <= 0) {

            flash('error', '无效的邮件模板');

            redirect('/admin/email.php?tab=templates');
        }

        if ($subject === '') {

            flash('error', '邮件主题不能为空');

            redirect('/admin/email.php?tab=templates');
        }

        if ($body === '') {

            flash('error', '邮件正文不能为空');

            redirect('/admin/email.php?tab=templates');
        }

        $stmt = $db->prepare("
            UPDATE email_templates
            SET
                subject = ?,
                body = ?,
                enabled = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $subject,
            $body,
            $enabled,
            $id,
        ]);

        log_action(
            null,
            current_admin_id(),
            'update_settings',
            'email_template:' . $id
        );

        flash('success', '邮件模板已保存');

        redirect('/admin/email.php?tab=templates');
    }

    

    if ($action === 'reset_template') {

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {

            flash('error', '无效的邮件模板');

            redirect('/admin/email.php?tab=templates');
        }

        $stmt = $db->prepare("
            SELECT template_key
            FROM email_templates
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {

            flash('error', '邮件模板不存在');

            redirect('/admin/email.php?tab=templates');
        }

        $default = null;

        foreach ($defaultTemplates as $item) {

            if ($item['template_key'] === $template['template_key']) {
                $default = $item;
                break;
            }
        }

        if (!$default) {

            flash('error', '该模板没有可恢复的默认内容');

            redirect('/admin/email.php?tab=templates');
        }

        $stmt = $db->prepare("
            UPDATE email_templates
            SET
                name = ?,
                subject = ?,
                body = ?,
                enabled = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $default['name'],
            $default['subject'],
            $default['body'],
            $default['enabled'],
            $id,
        ]);

        log_action(
            null,
            current_admin_id(),
            'update_settings',
            'email_template_reset:' . $id
        );

        flash('success', '邮件模板已恢复默认内容');

        redirect('/admin/email.php?tab=templates');
    }
}



$stmt = $db->query("
    SELECT *
    FROM email_settings
    WHERE id = 1
    LIMIT 1
");

$emailSettings = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emailSettings) {

    $emailSettings = [
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'smtp_username' => '',
        'smtp_password' => '',
        'from_name' => $config['site_name'] ?? 'AZhai Sub',
        'from_email' => '',
        'reply_to' => '',
        'enabled' => 0,
    ];
}



$templateStmt = $db->query("
    SELECT
        id,
        template_key,
        name,
        subject,
        body,
        enabled,
        updated_at
    FROM email_templates
    ORDER BY id ASC
");

$emailTemplates = $templateStmt->fetchAll(PDO::FETCH_ASSOC);



$logPage = max(
    1,
    (int)($_GET['page'] ?? 1)
);

$logPerPage = 20;

$totalLogs = (int)$db->query("
    SELECT COUNT(*)
    FROM email_logs
")->fetchColumn();

$totalLogPages = max(
    1,
    (int)ceil($totalLogs / $logPerPage)
);

if ($logPage > $totalLogPages) {
    $logPage = $totalLogPages;
}

$logOffset = ($logPage - 1) * $logPerPage;

$logStmt = $db->prepare("
    SELECT *
    FROM email_logs
    ORDER BY id DESC
    LIMIT ? OFFSET ?
");

$logStmt->bindValue(
    1,
    $logPerPage,
    PDO::PARAM_INT
);

$logStmt->bindValue(
    2,
    $logOffset,
    PDO::PARAM_INT
);

$logStmt->execute();

$emailLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

$flashSuccess = flash('success');
$flashError = flash('error');

require __DIR__ . '/../includes/admin_header.php';

?>

<style>

.email-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin: 0 0 24px;
}

.email-tabs a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 16px;
    border-radius: 10px;
    text-decoration: none;
    color: #666;
    background: #f5f5f5;
    transition:
        color .2s ease,
        background .2s ease,
        transform .2s ease;
}

.email-tabs a:hover {
    color: var(--pink-dark);
    background: var(--pink-soft);
    transform: translateY(-1px);
}

.email-tabs a.active {
    color: #fff;
    background: var(--pink-dark);
}

.email-section {
    margin-bottom: 24px;
}

.email-section h2 {
    margin-top: 0;
    margin-bottom: 8px;
}

.email-section-description {
    margin: 0 0 20px;
    color: #888;
    font-size: 14px;
    line-height: 1.7;
}

.email-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.email-form-grid .full {
    grid-column: 1 / -1;
}

.email-field label {
    display: block;
    margin-bottom: 7px;
    font-weight: 600;
}

.email-field input,
.email-field select,
.email-field textarea {
    width: 100%;
}

.email-field textarea {
    min-height: 300px;
    resize: vertical;
    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;
    line-height: 1.7;
}

.email-help {
    margin-top: 6px;
    color: #999;
    font-size: 12px;
    line-height: 1.7;
}

.email-toggle {
    display: flex !important;
    align-items: center;
    gap: 9px;
    cursor: pointer;
}

.email-toggle input {
    width: auto !important;
    margin: 0;
}

.email-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.email-template {
    margin-bottom: 18px;
}

.email-template summary {
    cursor: pointer;
    list-style: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.email-template summary::-webkit-details-marker {
    display: none;
}

.email-template summary::after {
    content: "＋";
    color: #999;
    font-size: 18px;
}

.email-template[open] summary::after {
    content: "−";
}

.email-template-name {
    font-weight: 700;
}

.email-template-key {
    display: inline-block;
    margin-left: 8px;
    padding: 3px 7px;
    border-radius: 6px;
    background: #f5f5f5;
    color: #999;
    font-size: 12px;
    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;
}

.email-template-body {
    margin-top: 20px;
}

.email-template-info {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.email-template-status {
    display: inline-flex;
    align-items: center;
    padding: 4px 9px;
    border-radius: 999px;
    font-size: 12px;
}

.email-template-status.enabled {
    color: #16803c;
    background: #ecfdf3;
}

.email-template-status.disabled {
    color: #888;
    background: #f5f5f5;
}

.email-variables {
    margin-top: 12px;
    padding: 14px 16px;
    background: #fafafa;
    border-radius: 10px;
    font-size: 13px;
    color: #777;
    line-height: 1.8;
}

.email-variables code {
    padding: 2px 5px;
    border-radius: 5px;
    background: #f1f1f1;
    color: #666;
}

.email-log-table {
    width: 100%;
    border-collapse: collapse;
}

.email-log-table th,
.email-log-table td {
    padding: 12px 10px;
    border-bottom: 1px solid #eee;
    text-align: left;
    vertical-align: top;
}

.email-log-table th {
    color: #777;
    font-size: 13px;
}

.email-log-status {
    display: inline-flex;
    align-items: center;
    padding: 4px 9px;
    border-radius: 999px;
    font-size: 12px;
}

.email-log-status.success {
    color: #16803c;
    background: #ecfdf3;
}

.email-log-status.failed {
    color: #c24141;
    background: #fff1f2;
}

.email-log-error {
    max-width: 420px;
    color: #c24141;
    font-size: 12px;
    word-break: break-word;
}

.email-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.email-pagination a,
.email-pagination span {
    display: inline-flex;
    min-width: 36px;
    height: 36px;
    align-items: center;
    justify-content: center;
    padding: 0 10px;
    border-radius: 8px;
    text-decoration: none;
}

.email-pagination a {
    color: #666;
    background: #f5f5f5;
}

.email-pagination a:hover {
    color: var(--pink-dark);
    background: var(--pink-soft);
}

.email-pagination .current {
    color: #fff;
    background: var(--pink-dark);
}

.email-empty {
    padding: 35px 20px;
    text-align: center;
    color: #999;
}

.email-template-reset {
    background: #f5f5f5 !important;
    color: #666 !important;
}

.email-template-reset:hover {
    background: #eee !important;
}

@media (max-width: 760px) {

    .email-form-grid {
        grid-template-columns: 1fr;
    }

    .email-form-grid .full {
        grid-column: auto;
    }

    .email-template-key {
        display: block;
        width: fit-content;
        margin: 6px 0 0;
    }

    .email-log-wrap {
        overflow-x: auto;
    }

    .email-log-table {
        min-width: 760px;
    }

}

</style>

<div class="page-title">

    <h1>
        邮箱管理
    </h1>

    <p>
        配置 SMTP、管理邮件模板以及查看邮件发送记录
    </p>

</div>

<?php if ($flashSuccess): ?>

    <div class="alert alert-success">
        <?= e($flashSuccess) ?>
    </div>

<?php endif; ?>

<?php if ($flashError): ?>

    <div class="alert alert-error">
        <?= e($flashError) ?>
    </div>

<?php endif; ?>

<div class="email-tabs">

    <a
        href="/admin/email.php?tab=settings"
        class="<?= $tab === 'settings' ? 'active' : '' ?>"
    >
        邮箱配置
    </a>

    <a
        href="/admin/email.php?tab=templates"
        class="<?= $tab === 'templates' ? 'active' : '' ?>"
    >
        邮件模板
    </a>

    <a
        href="/admin/email.php?tab=logs"
        class="<?= $tab === 'logs' ? 'active' : '' ?>"
    >
        邮件日志
    </a>

</div>

<?php if ($tab === 'settings'): ?>

    <section class="card email-section">

        <h2>
            SMTP 邮箱配置
        </h2>

        <p class="email-section-description">
            邮箱用于注册验证、找回密码以及系统通知
        </p>

        <form method="post">

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="save_settings"
            >

            <div class="email-form-grid">

                <div class="email-field full">

                    <label class="email-toggle">

                        <input
                            type="checkbox"
                            name="enabled"
                            value="1"
                            <?= !empty($emailSettings['enabled']) ? 'checked' : '' ?>
                        >

                        <span>
                            启用邮件系统
                        </span>

                    </label>

                    <div class="email-help">
                        关闭后系统不会主动发送注册验证、密码重置以及通知邮件
                    </div>

                </div>

                <div class="email-field">

                    <label for="smtp_host">
                        SMTP 服务器
                    </label>

                    <input
                        type="text"
                        id="smtp_host"
                        name="smtp_host"
                        value="<?= e($emailSettings['smtp_host'] ?? '') ?>"
                        placeholder="例如 smtp.example.com"
                        required
                    >

                </div>

                <div class="email-field">

                    <label for="smtp_port">
                        SMTP 端口
                    </label>

                    <input
                        type="number"
                        id="smtp_port"
                        name="smtp_port"
                        value="<?= e($emailSettings['smtp_port'] ?? 587) ?>"
                        min="1"
                        max="65535"
                        required
                    >

                </div>

                <div class="email-field">

                    <label for="smtp_encryption">
                        加密方式
                    </label>

                    <select
                        id="smtp_encryption"
                        name="smtp_encryption"
                    >

                        <option
                            value="tls"
                            <?= ($emailSettings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>
                        >
                            STARTTLS
                        </option>

                        <option
                            value="ssl"
                            <?= ($emailSettings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>
                        >
                            SSL
                        </option>

                        <option
                            value="none"
                            <?= ($emailSettings['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>
                        >
                            无加密
                        </option>

                    </select>

                </div>

                <div class="email-field">

                    <label for="smtp_username">
                        SMTP 用户名
                    </label>

                    <input
                        type="text"
                        id="smtp_username"
                        name="smtp_username"
                        value="<?= e($emailSettings['smtp_username'] ?? '') ?>"
                        autocomplete="username"
                    >

                </div>

                <div class="email-field full">

                    <label for="smtp_password">
                        SMTP 密码
                    </label>

                    <input
                        type="password"
                        id="smtp_password"
                        name="smtp_password"
                        value=""
                        placeholder="留空则保持原密码不变"
                        autocomplete="new-password"
                    >

                    <div class="email-help">
                        SMTP 密码不会直接显示在后台，留空保存时继续使用原密码
                    </div>

                </div>

                <div class="email-field">

                    <label for="from_name">
                        发件人名称
                    </label>

                    <input
                        type="text"
                        id="from_name"
                        name="from_name"
                        value="<?= e($emailSettings['from_name'] ?? '') ?>"
                        placeholder="AZhai Sub"
                    >

                </div>

                <div class="email-field">

                    <label for="from_email">
                        发件人邮箱
                    </label>

                    <input
                        type="email"
                        id="from_email"
                        name="from_email"
                        value="<?= e($emailSettings['from_email'] ?? '') ?>"
                        placeholder="noreply@example.com"
                        required
                    >

                </div>

                <div class="email-field full">

                    <label for="reply_to">
                        回复邮箱
                    </label>

                    <input
                        type="email"
                        id="reply_to"
                        name="reply_to"
                        value="<?= e($emailSettings['reply_to'] ?? '') ?>"
                        placeholder="可选"
                    >

                </div>

            </div>

            <div class="email-actions">

                <button type="submit">
                    保存邮箱配置
                </button>

            </div>

        </form>

    </section>

    <section class="card email-section">

        <h2>
            测试邮件
        </h2>

        <p class="email-section-description">
            使用已经保存的 SMTP 配置发送一封测试邮件
        </p>

        <form method="post">

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="test_email"
            >

            <div class="email-form-grid">

                <div class="email-field">

                    <label for="test_email">
                        测试邮箱
                    </label>

                    <input
                        type="email"
                        id="test_email"
                        name="test_email"
                        placeholder="请输入收件邮箱"
                        required
                    >

                </div>

            </div>

            <div class="email-actions">

                <button type="submit">
                    发送测试邮件
                </button>

            </div>

        </form>

    </section>

<?php elseif ($tab === 'templates'): ?>

    <section class="card email-section">

        <h2>
            邮件模板
        </h2>

        <p class="email-section-description">
            修改系统发送邮件时使用的主题和 HTML 正文
        </p>

        <?php if (empty($emailTemplates)): ?>

            <div class="email-empty">
                暂无邮件模板
            </div>

        <?php else: ?>

            <?php foreach ($emailTemplates as $template): ?>

                <details
                    class="email-template"
                    <?= isset($_GET['edit']) && (int)$_GET['edit'] === (int)$template['id'] ? 'open' : '' ?>
                >

                    <summary>

                        <div>

                            <span class="email-template-name">
                                <?= e($template['name']) ?>
                            </span>

                            <span class="email-template-key">
                                <?= e($template['template_key']) ?>
                            </span>

                        </div>

                    </summary>

                    <div class="email-template-body">

                        <div class="email-template-info">

                            <?php if ((int)$template['enabled'] === 1): ?>

                                <span class="email-template-status enabled">
                                    已启用
                                </span>

                            <?php else: ?>

                                <span class="email-template-status disabled">
                                    已停用
                                </span>

                            <?php endif; ?>

                            <?php if (!empty($template['updated_at'])): ?>

                                <span class="email-help">
                                    最后修改：<?= e($template['updated_at']) ?>
                                </span>

                            <?php endif; ?>

                        </div>

                        <form method="post">

                            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="action"
                                value="save_template"
                            >

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int)$template['id'] ?>"
                            >

                            <div class="email-form-grid">

                                <div class="email-field full">

                                    <label>
                                        模板名称
                                    </label>

                                    <input
                                        type="text"
                                        value="<?= e($template['name']) ?>"
                                        disabled
                                    >

                                </div>

                                <div class="email-field full">

                                    <label>
                                        模板 Key
                                    </label>

                                    <input
                                        type="text"
                                        value="<?= e($template['template_key']) ?>"
                                        disabled
                                    >

                                </div>

                                <div class="email-field full">

                                    <label for="subject_<?= (int)$template['id'] ?>">
                                        邮件主题
                                    </label>

                                    <input
                                        type="text"
                                        id="subject_<?= (int)$template['id'] ?>"
                                        name="subject"
                                        value="<?= e($template['subject']) ?>"
                                        maxlength="255"
                                        required
                                    >

                                </div>

                                <div class="email-field full">

                                    <label for="body_<?= (int)$template['id'] ?>">
                                        邮件正文
                                    </label>

                                    <textarea
                                        id="body_<?= (int)$template['id'] ?>"
                                        name="body"
                                        required
                                    ><?= e($template['body']) ?></textarea>

                                    <div class="email-help">
                                        支持 HTML
                                    </div>

                                </div>

                                <div class="email-field full">

                                    <label class="email-toggle">

                                        <input
                                            type="checkbox"
                                            name="enabled"
                                            value="1"
                                            <?= (int)$template['enabled'] === 1 ? 'checked' : '' ?>
                                        >

                                        <span>
                                            启用此模板
                                        </span>

                                    </label>

                                </div>

                            </div>

                            <div class="email-variables">

                                <strong>
                                    可用变量
                                </strong>

                                <br>

                                <code>{{username}}</code>
                                用户名

                                <code>{{email}}</code>
                                邮箱

                                <code>{{site_name}}</code>
                                网站名称

                                <code>{{site_url}}</code>
                                网站地址

                                <?php if ($template['template_key'] === 'verify_email'): ?>

                                    <br>

                                    <code>{{verify_url}}</code>
                                    验证地址

                                    <code>{{expires_minutes}}</code>
                                    有效期

                                <?php elseif ($template['template_key'] === 'password_reset'): ?>

                                    <br>

                                    <code>{{reset_url}}</code>
                                    重置地址

                                    <code>{{expires_minutes}}</code>
                                    有效期

                                <?php elseif ($template['template_key'] === 'domain_approved'): ?>

                                    <br>

                                    <code>{{domain}}</code>
                                    域名

                                <?php elseif ($template['template_key'] === 'domain_rejected'): ?>

                                    <br>

                                    <code>{{domain}}</code>
                                    域名

                                    <code>{{review_reason}}</code>
                                    审核原因

                                <?php endif; ?>

                            </div>

                            <div class="email-actions">

                                <button type="submit">
                                    保存模板
                                </button>

                        </form>

                                <form
                                    method="post"
                                    onsubmit="return confirm('确定恢复这个模板的默认内容吗？');"
                                >

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="reset_template"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$template['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="email-template-reset"
                                    >
                                        恢复默认
                                    </button>

                                </form>

                            </div>

                    </div>

                </details>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

<?php elseif ($tab === 'logs'): ?>

    <section class="card email-section">

        <h2>
            邮件日志
        </h2>

        <p class="email-section-description">
            共 <?= number_format($totalLogs) ?> 条邮件发送记录
        </p>

        <?php if (empty($emailLogs)): ?>

            <div class="email-empty">
                暂无邮件发送记录
            </div>

        <?php else: ?>

            <div class="email-log-wrap">

                <table class="email-log-table">

                    <thead>

                        <tr>
                            <th>ID</th>
                            <th>收件邮箱</th>
                            <th>模板</th>
                            <th>主题</th>
                            <th>状态</th>
                            <th>错误信息</th>
                            <th>发送时间</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($emailLogs as $log): ?>

                        <tr>

                            <td>
                                #<?= (int)$log['id'] ?>
                            </td>

                            <td>
                                <?= e($log['to_email'] ?? '') ?>
                            </td>

                            <td>
                                <?= e($log['template_key'] ?? '-') ?>
                            </td>

                            <td>
                                <?= e($log['subject'] ?? '') ?>
                            </td>

                            <td>

                                <?php
                                $status = (string)($log['status'] ?? '');
                                ?>

                                <?php if ($status === 'sent'): ?>

                                    <span class="email-log-status success">
                                        发送成功
                                    </span>

                                <?php else: ?>

                                    <span class="email-log-status failed">
                                        发送失败
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if (!empty($log['error'])): ?>

                                    <div class="email-log-error">
                                        <?= e($log['error']) ?>
                                    </div>

                                <?php else: ?>

                                    <span class="muted">
                                        -
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= e(
                                    $log['sent_at']
                                    ?? $log['created_at']
                                    ?? ''
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <?php if ($totalLogPages > 1): ?>

                <div class="email-pagination">

                    <?php if ($logPage > 1): ?>

                        <a
                            href="/admin/email.php?tab=logs&page=<?= $logPage - 1 ?>"
                        >
                            上一页
                        </a>

                    <?php endif; ?>

                    <?php

                    $startPage = max(1, $logPage - 2);
                    $endPage = min($totalLogPages, $logPage + 2);

                    if ($startPage > 1):
                    ?>

                        <a
                            href="/admin/email.php?tab=logs&page=1"
                        >
                            1
                        </a>

                        <?php if ($startPage > 2): ?>

                            <span>
                                ...
                            </span>

                        <?php endif; ?>

                    <?php endif; ?>

                    <?php for ($page = $startPage; $page <= $endPage; $page++): ?>

                        <?php if ($page === $logPage): ?>

                            <span class="current">
                                <?= $page ?>
                            </span>

                        <?php else: ?>

                            <a
                                href="/admin/email.php?tab=logs&page=<?= $page ?>"
                            >
                                <?= $page ?>
                            </a>

                        <?php endif; ?>

                    <?php endfor; ?>

                    <?php if ($endPage < $totalLogPages): ?>

                        <?php if ($endPage < $totalLogPages - 1): ?>

                            <span>
                                ...
                            </span>

                        <?php endif; ?>

                        <a
                            href="/admin/email.php?tab=logs&page=<?= $totalLogPages ?>"
                        >
                            <?= $totalLogPages ?>
                        </a>

                    <?php endif; ?>

                    <?php if ($logPage < $totalLogPages): ?>

                        <a
                            href="/admin/email.php?tab=logs&page=<?= $logPage + 1 ?>"
                        >
                            下一页
                        </a>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        <?php endif; ?>

    </section>

<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>