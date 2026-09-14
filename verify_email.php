<?php

declare(strict_types=1);
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/csrf.php';

$message = '';
$type = 'danger';
$token = trim($_GET['token'] ?? '');

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $message = '验证链接无效。';
} else {
    $hash = hash('sha256', $token);
    $stmt = db()->prepare('SELECT * FROM email_verifications WHERE token_hash=? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if (!$row) {
        $message = '验证链接已失效或已经使用。';
    } else {
        db()->beginTransaction();
        try {
            db()->prepare('UPDATE users SET email_verified_at=NOW(), updated_at=NOW() WHERE id=?')->execute([(int)$row['user_id']]);
            db()->prepare('UPDATE email_verifications SET used_at=NOW() WHERE id=?')->execute([(int)$row['id']]);
            db()->commit();
            log_action((int)$row['user_id'], null, 'verify_email', $row['email']);
            $message = '邮箱验证成功，现在可以登录 AZhai Sub。';
            $type = 'success';
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            error_log('[AZhai Sub Verify Email] '.$e->getMessage());
            $message = '验证失败，请稍后重试。';
        }
    }
}
$pageTitle='邮箱验证';
require __DIR__.'/includes/header.php';
?>
<div class="card narrow"><h1>邮箱验证</h1><div class="alert <?= $type==='success'?'success':'danger' ?>"><?= e($message) ?></div><p style="text-align:center"><a href="/login.php">返回登录</a></p></div>
<?php require __DIR__.'/includes/footer.php'; ?>
