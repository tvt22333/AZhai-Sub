<?php

declare(strict_types=1);
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if (is_logged_in()) redirect('/dashboard/');
$token=trim($_GET['token']??($_POST['token']??''));
$error=null; $success=null; $row=null;
if (preg_match('/^[a-f0-9]{64}$/',$token)) {
    $hash=hash('sha256',$token);
    $stmt=db()->prepare('SELECT pr.*,u.username,u.email,u.oauth_provider,u.status FROM password_resets pr JOIN users u ON u.id=pr.user_id WHERE pr.token_hash=? AND pr.used_at IS NULL AND pr.expires_at>NOW() LIMIT 1');
    $stmt->execute([$hash]); $row=$stmt->fetch();
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    if (!$row) $error='重置链接无效或已过期，请重新申请。';
    elseif (!empty($row['oauth_provider'])) $error='该账号使用阿宅账号登录，请前往阿宅账号中心修改密码。';
    else {
        $password=$_POST['password']??''; $confirm=$_POST['password_confirm']??'';
        if(strlen($password)<8) $error='新密码至少需要 8 位。';
        elseif($password!==$confirm) $error='两次输入的密码不一致。';
        else {
            $hashPassword=password_hash($password,PASSWORD_DEFAULT);
            db()->beginTransaction();
            try {
                db()->prepare('UPDATE users SET password=?,updated_at=NOW() WHERE id=?')->execute([$hashPassword,(int)$row['user_id']]);
                db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?')->execute([(int)$row['id']]);
                db()->commit();
                log_action((int)$row['user_id'],null,'change_password',$row['username']);
                $success='密码修改成功，请使用新密码登录。'; $row=null;
            } catch(Throwable $e) {
                if(db()->inTransaction()) db()->rollBack();
                error_log('[AZhai Sub Reset Password] '.$e->getMessage()); $error='密码修改失败，请稍后重试。';
            }
        }
    }
}
$pageTitle='重置密码'; require __DIR__.'/includes/header.php';
?>
<div class="card narrow"><h1>重置密码</h1><?php if($error): ?><div class="alert danger"><?=e($error)?></div><?php endif; ?><?php if($success): ?><div class="alert success"><?=e($success)?></div><p style="text-align:center"><a href="/login.php">返回登录</a></p><?php elseif($row): ?><form method="post"><?=csrf_field()?><input type="hidden" name="token" value="<?=e($token)?>"><div class="form-group"><label>新密码</label><input type="password" name="password" minlength="8" autocomplete="new-password" required></div><div class="form-group"><label>确认新密码</label><input type="password" name="password_confirm" minlength="8" autocomplete="new-password" required></div><button class="primary">保存新密码</button></form><?php else: ?><p style="text-align:center"><a href="/forgot_password.php">重新申请密码重置</a></p><?php endif; ?></div>
<?php require __DIR__.'/includes/footer.php'; ?>
