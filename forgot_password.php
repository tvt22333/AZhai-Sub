<?php

declare(strict_types=1);
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/csrf.php';

if (is_logged_in()) redirect('/dashboard/');
$message=null; $error=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $email=trim($_POST['email']??'');
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) $error='请输入正确的邮箱地址';
    else {
        $stmt=db()->prepare('SELECT id,status,oauth_provider FROM users WHERE email=? LIMIT 1');
        $stmt->execute([$email]); $user=$stmt->fetch();
        if ($user && (int)$user['status']===1 && empty($user['oauth_provider'])) issue_password_reset((int)$user['id']);
        $message='如果该邮箱对应可重置的本地账号，我们已经尝试发送密码重置邮件，请检查收件箱和垃圾邮件。';
    }
}
$pageTitle='忘记密码'; require __DIR__.'/includes/header.php';
?>
<div class="card narrow"><h1>忘记密码</h1><?php if($error): ?><div class="alert danger"><?=e($error)?></div><?php endif; ?><?php if($message): ?><div class="alert success"><?=e($message)?></div><?php endif; ?><form method="post"><?=csrf_field()?><div class="form-group"><label>注册邮箱</label><input type="email" name="email" autocomplete="email" required></div><button class="primary">发送重置邮件</button></form><p style="font-size:12px;color:#aaa;margin-top:16px">使用阿宅账号登录的用户请前往阿宅账号中心修改密码。</p><p style="text-align:center"><a href="/login.php">返回登录</a></p></div>
<?php require __DIR__.'/includes/footer.php'; ?>
