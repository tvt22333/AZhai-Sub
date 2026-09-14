<?php

declare(strict_types=1);
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/csrf.php';

if (is_logged_in()) redirect('/dashboard/');
$message = null;
$error = null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) {
        $error='请输入正确的邮箱地址';
    } else {
        $stmt=db()->prepare('SELECT id,email_verified_at,status FROM users WHERE email=? LIMIT 1');
        $stmt->execute([$email]); $user=$stmt->fetch();
        // 无论账号是否存在，都返回相同文案，避免邮箱枚举。
        if ($user && (int)$user['status']===1 && empty($user['email_verified_at'])) issue_email_verification((int)$user['id']);
        $message='如果该邮箱存在且需要验证，我们已经尝试发送验证邮件，请检查收件箱和垃圾邮件。';
    }
}
$pageTitle='重新发送验证邮件'; require __DIR__.'/includes/header.php';
?>
<div class="card narrow"><h1>重新发送验证邮件</h1><?php if($error): ?><div class="alert danger"><?=e($error)?></div><?php endif; ?><?php if($message): ?><div class="alert success"><?=e($message)?></div><?php endif; ?><form method="post"><?=csrf_field()?><div class="form-group"><label>邮箱</label><input type="email" name="email" autocomplete="email" required></div><button class="primary">发送验证邮件</button></form><p style="text-align:center;margin-top:16px"><a href="/login.php">返回登录</a></p></div>
<?php require __DIR__.'/includes/footer.php'; ?>
