<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/email.php';

if (is_logged_in()) {
    redirect('/dashboard/');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!preg_match('/^[A-Za-z0-9_-]{3,32}$/', $username)) {
        $error = '用户名只能使用 3-32 位字母、数字、下划线或短横线';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '请输入正确的邮箱地址';
    } elseif (strlen($password) < 8) {
        $error = '密码长度至少为 8 位';
    } else {
        try {
            $pdo = db();

            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);

            if ($stmt->fetch()) {
                $error = '用户名已经存在';
            } else {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);

                if ($stmt->fetch()) {
                    $error = '邮箱已经被注册';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO users(username,email,password,status,email_verified_at,created_at,updated_at) VALUES(?,?,?,1,NULL,NOW(),NOW())');
                    $stmt->execute([
                        $username,
                        $email,
                        password_hash($password, PASSWORD_DEFAULT),
                    ]);

                    $id = (int)$pdo->lastInsertId();

                    log_action($id, null, 'register', $username);

                    $sent = issue_email_verification($id);

                    flash(
                        'success',
                        $sent
                            ? '注册成功！验证邮件已经发送到你的邮箱，请完成验证后登录。'
                            : '注册成功，但验证邮件暂时发送失败，请在登录页重新发送验证邮件。'
                    );

                    redirect('/login.php');
                }
            }
        } catch (Throwable $e) {
            error_log('[AZhai Sub Register] ' . $e->getMessage());
            $error = '注册失败，请稍后重试';
        }
    }
}

$pageTitle = '注册';
require __DIR__ . '/includes/header.php';
?>
<div class="card narrow">
    <h1>注册</h1>

    <?php if ($error): ?>
        <div class="alert danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>

        <div class="form-group">
            <label>用户名</label>
            <input type="text" name="username" maxlength="32" value="<?= e($_POST['username'] ?? '') ?>" autocomplete="username" required autofocus>
        </div>

        <div class="form-group">
            <label>邮箱</label>
            <input type="email" name="email" maxlength="255" value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" required>
        </div>

        <div class="form-group">
            <label>密码</label>
            <input type="password" name="password" minlength="8" autocomplete="new-password" required>
        </div>

        <button class="primary">注册</button>
    </form>

    <p style="font-size:12px;color:#aaa;margin-top:14px">注册后需要验证邮箱才能登录。</p>

    <div style="margin-top:18px;text-align:center;color:#aaa1a8;font-size:12px">
        已有账号？
        <a href="/login.php" style="color:#f2769a;font-weight:650">立即登录</a>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
