<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$pdo = db();

$adminId = current_admin_id();

if (!$adminId) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = '修改密码';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($currentPassword === '') {
        $error = '请输入当前密码';
    } elseif ($newPassword === '') {
        $error = '请输入新密码';
    } elseif (strlen($newPassword) < 8) {
        $error = '新密码至少需要 8 位';
    } elseif ($confirmPassword === '') {
        $error = '请再次输入新密码';
    } elseif ($newPassword !== $confirmPassword) {
        $error = '两次输入的新密码不一致';
    } elseif ($currentPassword === $newPassword) {
        $error = '新密码不能与当前密码相同';
    }

    if ($error === '') {

        $stmt = $pdo->prepare("
            SELECT id, password, status
            FROM admin_users
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$adminId]);

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            $error = '管理员账号不存在';
        } elseif ((int)$admin['status'] !== 1) {
            $error = '管理员账号已被禁用';
        } elseif (!password_verify($currentPassword, $admin['password'])) {
            $error = '当前密码错误';
        }
    }

    if ($error === '') {

        $passwordHash = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

        $stmt = $pdo->prepare("
            UPDATE admin_users
            SET password = ?
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $passwordHash,
            $adminId
        ]);

        if (function_exists('log_action')) {
            log_action(
                null,
                $adminId,
                'change_password',
                'admin'
            );
        }

        
        unset($_SESSION['admin_id']);
        unset($_SESSION['admin_username']);

        session_regenerate_id(true);

        header('Location: /admin/login.php?password_changed=1');
        exit;
    }
}

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="card narrow">

    <h1>修改密码</h1>

    <p style="color:#888;margin-bottom:25px;">
        修改管理员账号的登录密码。
    </p>

    <?php if ($error): ?>

        <div class="alert error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <form method="post">

        <?= csrf_field() ?>

        <div class="form-group">

            <label for="current_password">
                当前密码
            </label>

            <input
                type="password"
                id="current_password"
                name="current_password"
                autocomplete="current-password"
                required
            >

        </div>

        <div class="form-group">

            <label for="new_password">
                新密码
            </label>

            <input
                type="password"
                id="new_password"
                name="new_password"
                minlength="8"
                autocomplete="new-password"
                required
            >

            <small style="color:#999;">
                至少 8 位字符
            </small>

        </div>

        <div class="form-group">

            <label for="confirm_password">
                确认新密码
            </label>

            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="8"
                autocomplete="new-password"
                required
            >

        </div>

        <button
            type="submit"
            class="btn btn-primary"
        >
            修改密码
        </button>

    </form>

</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>