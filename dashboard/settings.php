<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/email.php';

require_login();

$pdo = db();
$userId = current_user_id();

$stmt = $pdo->prepare('
    SELECT
        id,
        username,
        email,
        password,
        status,
        email_verified_at,
        oauth_provider,
        created_at
    FROM users
    WHERE id = ?
    LIMIT 1
');

$stmt->execute([$userId]);

$u = $stmt->fetch();

if (!$u) {
    logout_user();
    redirect('/login.php');
}

$error = null;



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = $_POST['action'] ?? 'email';

    

    if ($action === 'resend_verification') {

        if (empty($u['email_verified_at'])) {

            $ok = issue_email_verification(
                (int)$u['id']
            );

            flash(
                $ok ? 'success' : 'error',
                $ok
                    ? '验证邮件已发送，请检查邮箱。'
                    : '发送失败或操作过于频繁，请稍后再试。'
            );
        }

        redirect('/dashboard/settings.php');
    }

    

    elseif ($action === 'email') {

        $email = trim(
            $_POST['email'] ?? ''
        );

        if (!filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )) {

            $error = '请输入正确的邮箱地址';

        } elseif (
            strtolower($email) !==
            strtolower($u['email'])
        ) {

            try {

                $stmt = $pdo->prepare('
                    SELECT id
                    FROM users
                    WHERE email = ?
                      AND id <> ?
                    LIMIT 1
                ');

                $stmt->execute([
                    $email,
                    $u['id']
                ]);

                if ($stmt->fetch()) {

                    $error = '该邮箱已经被其他账号使用';

                } else {

                    $pdo->prepare('
                        UPDATE users
                        SET
                            email = ?,
                            email_verified_at = NULL,
                            updated_at = NOW()
                        WHERE id = ?
                    ')->execute([
                        $email,
                        $u['id']
                    ]);

                    log_action(
                        (int)$u['id'],
                        null,
                        'update_settings',
                        $u['username']
                    );

                    $u['email'] = $email;
                    $u['email_verified_at'] = null;

                    $ok = issue_email_verification(
                        (int)$u['id']
                    );

                    flash(
                        'success',
                        $ok
                            ? '邮箱已更新，请验证新邮箱。'
                            : '邮箱已更新，但验证邮件发送失败，请稍后重新发送。'
                    );

                    redirect('/dashboard/settings.php');
                }

            } catch (Throwable $e) {

                error_log(
                    '[AZhai Sub Settings] ' .
                    $e->getMessage()
                );

                $error = '保存失败，请稍后重试';
            }

        } else {

            $error = '邮箱没有变化';
        }
    }

    

    elseif ($action === 'password') {

        if (!empty($u['oauth_provider'])) {

            $error =
                '阿宅账号用户请在阿宅账号中心修改密码';

        } else {

            $current =
                $_POST['current_password'] ?? '';

            $new =
                $_POST['new_password'] ?? '';

            $confirm =
                $_POST['confirm_password'] ?? '';

            if (!password_verify(
                $current,
                $u['password']
            )) {

                $error = '当前密码错误';

            } elseif (strlen($new) < 8) {

                $error = '新密码至少 8 位';

            } elseif ($new !== $confirm) {

                $error = '两次输入的新密码不一致';

            } elseif (
                password_verify(
                    $new,
                    $u['password']
                )
            ) {

                $error = '新密码不能与旧密码相同';

            } else {

                $pdo->prepare('
                    UPDATE users
                    SET
                        password = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ')->execute([
                    password_hash(
                        $new,
                        PASSWORD_DEFAULT
                    ),
                    $u['id']
                ]);

                session_regenerate_id(true);

                log_action(
                    (int)$u['id'],
                    null,
                    'change_password',
                    $u['username']
                );

                flash(
                    'success',
                    '密码修改成功'
                );

                redirect('/dashboard/settings.php');
            }
        }
    }
}

$pageTitle = '账号设置';

require __DIR__ . '/../includes/header.php';
?>

<div class="card narrow">

    <h1>账号设置</h1>

    <?php if ($error): ?>

        <div class="alert danger">
            <?= e($error) ?>
        </div>

    <?php endif; ?>


    <?php if ($m = flash('success')): ?>

        <div class="alert success">
            <?= e($m) ?>
        </div>

    <?php endif; ?>


    <?php if ($m = flash('error')): ?>

        <div class="alert danger">
            <?= e($m) ?>
        </div>

    <?php endif; ?>


    <!-- =========================================================
         账号信息
    ========================================================== -->

    <div style="margin-bottom:22px;">

        <div class="form-group">

            <label>
                用户名
            </label>

            <input
                type="text"
                value="<?= e($u['username']) ?>"
                disabled
            >

        </div>


        <div class="form-group">

            <label>
                登录邮箱
            </label>

            <input
                type="email"
                value="<?= e($u['email']) ?>"
                disabled
            >

        </div>


        <div class="form-group">

            <label>
                注册时间
            </label>

            <input
                type="text"
                value="<?= e($u['created_at']) ?>"
                disabled
            >

        </div>


        <div class="form-group">

            <label>
                账号状态
            </label>

            <input
                type="text"
                value="<?= (int)$u['status'] === 1
                    ? '正常'
                    : '已禁用' ?>"
                disabled
            >

        </div>


        <div class="form-group">

            <label>
                登录方式
            </label>

            <input
                type="text"
                value="<?= !empty($u['oauth_provider'])
                    ? '阿宅账号 OAuth'
                    : '用户名 / 邮箱 + 密码' ?>"
                disabled
            >

        </div>


        <?php if (empty($u['oauth_provider'])): ?>

            <?php if (empty($u['email_verified_at'])): ?>

                <div
                    class="alert danger"
                    style="margin-top:14px;"
                >

                    邮箱尚未验证。

                    <form
                        method="post"
                        style="display:inline;"
                    >

                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="action"
                            value="resend_verification"
                        >

                        <button
                            type="submit"
                            style="margin-left:8px;"
                        >
                            重新发送
                        </button>

                    </form>

                </div>

            <?php else: ?>

                <div
                    style="
                        margin-top:12px;
                        color:#299b68;
                        font-size:12px;
                    "
                >
                    ✓ 邮箱已验证
                </div>

            <?php endif; ?>

        <?php else: ?>

            <div
                style="
                    margin-top:12px;
                    color:#299b68;
                    font-size:12px;
                "
            >
                ✓ 阿宅账号已验证
            </div>

        <?php endif; ?>

    </div>


    <hr
        style="
            border:0;
            border-top:1px solid #eee;
            margin:22px 0;
        "
    >


    <!-- =========================================================
         修改邮箱
    ========================================================== -->

    <h2>
        修改邮箱
    </h2>

    <p
        style="
            margin:8px 0 18px;
            color:#999;
            font-size:12px;
            line-height:1.7;
        "
    >
        修改邮箱后，需要重新验证新的邮箱地址。
    </p>


    <form method="post">

        <?= csrf_field() ?>

        <input
            type="hidden"
            name="action"
            value="email"
        >

        <div class="form-group">

            <label>
                新邮箱
            </label>

            <input
                type="email"
                name="email"
                value="<?= e($u['email']) ?>"
                autocomplete="email"
                required
            >

        </div>

        <button type="submit">
            保存邮箱
        </button>

    </form>


    <?php if (empty($u['oauth_provider'])): ?>

        <!-- =====================================================
             修改密码
        ====================================================== -->

        <hr
            style="
                border:0;
                border-top:1px solid #eee;
                margin:22px 0;
            "
        >

        <h2>
            修改密码
        </h2>


        <form method="post">

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="password"
            >


            <div class="form-group">

                <label>
                    当前密码
                </label>

                <input
                    type="password"
                    name="current_password"
                    autocomplete="current-password"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    新密码
                </label>

                <input
                    type="password"
                    name="new_password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    确认新密码
                </label>

                <input
                    type="password"
                    name="confirm_password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

            </div>


            <button
                type="submit"
                class="primary"
            >
                修改密码
            </button>

        </form>

    <?php else: ?>

        <!-- =====================================================
             OAuth 密码
        ====================================================== -->

        <hr
            style="
                border:0;
                border-top:1px solid #eee;
                margin:22px 0;
            "
        >

        <h2>
            密码
        </h2>


        <div class="form-group">

            <label>
                密码管理
            </label>

            <input
                type="text"
                value="由阿宅账号中心管理"
                disabled
            >

        </div>


        <div
            class="alert success"
            style="margin-top:12px;"
        >
            此账号使用阿宅账号登录，
            密码由阿宅账号中心管理。
        </div>

    <?php endif; ?>

</div>

<?php

require __DIR__ . '/../includes/footer.php';

?>