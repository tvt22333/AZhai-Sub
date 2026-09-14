<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mailer.php';

function email_settings(): array
{
    static $settings = null;
    if ($settings !== null) return $settings;
    $stmt = db()->query('SELECT * FROM email_settings WHERE id = 1 LIMIT 1');
    $settings = $stmt->fetch() ?: [];
    if (!empty($settings['smtp_password'])) {
        $settings['smtp_password'] = decrypt_secret((string)$settings['smtp_password']);
    } else {
        $settings['smtp_password'] = '';
    }
    return $settings;
}

function email_enabled(): bool
{
    $s = email_settings();
    return !empty($s['enabled']) && !empty($s['smtp_host']) && !empty($s['from_email']);
}

function email_render(string $text, array $vars): string
{
    $replace = [];
    foreach ($vars as $key => $value) {
        $replace['{{' . $key . '}}'] = (string)$value;
    }
    return strtr($text, $replace);
}

function email_log(?int $userId, ?string $templateKey, string $to, string $subject, string $status, ?string $error = null): void
{
    try {
        $stmt = db()->prepare('INSERT INTO email_logs (user_id,template_key,to_email,subject,status,error,sent_at,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
        $stmt->execute([$userId, $templateKey, $to, $subject, $status, $error, $status === 'sent' ? date('Y-m-d H:i:s') : null]);
    } catch (Throwable $e) {
        error_log('[AZhai Sub Email Log] ' . $e->getMessage());
    }
}

function send_email(string $to, string $subject, string $html, ?int $userId = null, ?string $templateKey = null): bool
{
    if (!email_enabled()) {
        email_log($userId, $templateKey, $to, $subject, 'failed', '邮箱系统未启用或 SMTP 配置不完整');
        return false;
    }
    try {
        $mailer = new AzSmtpMailer(email_settings());
        $mailer->send($to, $subject, $html);
        email_log($userId, $templateKey, $to, $subject, 'sent');
        return true;
    } catch (Throwable $e) {
        error_log('[AZhai Sub Email] ' . $e->getMessage());
        email_log($userId, $templateKey, $to, $subject, 'failed', $e->getMessage());
        return false;
    }
}

function send_template_email(string $templateKey, int $userId, array $vars = []): bool
{
    $stmt = db()->prepare('SELECT id,username,email,status FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) return false;

    $stmt = db()->prepare('SELECT * FROM email_templates WHERE template_key = ? AND enabled = 1 LIMIT 1');
    $stmt->execute([$templateKey]);
    $template = $stmt->fetch();
    if (!$template) return false;

    $vars = array_merge([
        'username' => $user['username'],
        'email' => $user['email'],
        'site_name' => $GLOBALS['config']['site_name'] ?? 'AZhai Sub',
        'site_url' => $GLOBALS['config']['site_url'] ?? '',
    ], $vars);

    $subject = email_render((string)$template['subject'], $vars);
    $body = email_render((string)$template['body'], $vars);
    $body = '<!doctype html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:32px;background:#faf7f8;color:#333;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Microsoft YaHei,sans-serif;line-height:1.8"><div style="max-width:620px;margin:auto;background:#fff;border:1px solid #eee;border-radius:16px;padding:30px;box-shadow:0 8px 30px rgba(0,0,0,.04)">' . $body . '<hr style="border:0;border-top:1px solid #eee;margin:28px 0"><div style="font-size:12px;color:#aaa">' . e($vars['site_name']) . '</div></div></body></html>';
    return send_email((string)$user['email'], $subject, $body, $userId, $templateKey);
}

function issue_email_verification(int $userId): bool
{
    $stmt = db()->prepare('SELECT username,email,email_verified_at FROM users WHERE id=? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || !empty($user['email_verified_at'])) return false;

    $stmt = db()->prepare('SELECT created_at FROM email_verifications WHERE user_id=? AND used_at IS NULL ORDER BY id DESC LIMIT 1');
    $stmt->execute([$userId]);
    $last = $stmt->fetch();
    if ($last && strtotime($last['created_at']) > time() - 60) return false;

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expiresMinutes = 30;
    db()->prepare('UPDATE email_verifications SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$userId]);
    db()->prepare('INSERT INTO email_verifications (user_id,email,token_hash,expires_at,created_at) VALUES (?,?,?,?,NOW())')->execute([$userId,$user['email'],$hash,date('Y-m-d H:i:s',time()+$expiresMinutes*60)]);

    $url = rtrim((string)$GLOBALS['config']['site_url'],'/') . '/verify_email.php?token=' . rawurlencode($token);
    return send_template_email('verify_email', $userId, ['verify_url'=>$url,'expires_minutes'=>$expiresMinutes]);
}

function issue_password_reset(int $userId): bool
{
    $stmt = db()->prepare('SELECT username,email,oauth_provider FROM users WHERE id=? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || !empty($user['oauth_provider'])) return false;

    $stmt = db()->prepare('SELECT created_at FROM password_resets WHERE user_id=? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$userId]);
    $last = $stmt->fetch();
    if ($last && strtotime($last['created_at']) > time() - 60) return false;

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expiresMinutes = 30;
    db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$userId]);
    db()->prepare('INSERT INTO password_resets (user_id,token_hash,expires_at,created_at) VALUES (?,?,?,NOW())')->execute([$userId,$hash,date('Y-m-d H:i:s',time()+$expiresMinutes*60)]);

    $url = rtrim((string)$GLOBALS['config']['site_url'],'/') . '/reset_password.php?token=' . rawurlencode($token);
    return send_template_email('password_reset', $userId, ['reset_url'=>$url,'expires_minutes'=>$expiresMinutes]);
}

function send_user_notification(int $userId, string $templateKey, array $vars=[]): bool
{
    return send_template_email($templateKey, $userId, $vars);
}
