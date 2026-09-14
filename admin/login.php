<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

if (is_admin_logged_in()) {
    header('Location: /admin/');
    exit;
}

$pdo = db();

$error = '';

$passwordChanged = isset($_GET['password_changed'])
    && $_GET['password_changed'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '') {

        $error = '请输入管理员账号';

    } elseif ($password === '') {

        $error = '请输入密码';

    } else {

        $stmt = $pdo->prepare("
            SELECT
                id,
                username,
                password,
                status
            FROM admin_users
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->execute([$username]);

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$admin ||
            (int)$admin['status'] !== 1 ||
            !password_verify($password, $admin['password'])
        ) {

            $error = '管理员账号或密码错误';

        } else {

            login_admin(
                (int)$admin['id'],
                $admin['username']
            );

            if (function_exists('log_action')) {
                log_action(
                    null,
                    (int)$admin['id'],
                    'login',
                    'admin'
                );
            }

            header('Location: /admin/');
            exit;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        管理员登录 - <?= e($config['site_name']) ?>
    </title>

    <meta
        name="theme-color"
        content="#ff69b4"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            min-height: 100%;
        }

        body {
            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 24px;

            background:
                radial-gradient(
                    circle at 10% 10%,
                    rgba(255, 105, 180, .08),
                    transparent 28%
                ),
                radial-gradient(
                    circle at 90% 90%,
                    rgba(255, 182, 193, .08),
                    transparent 30%
                ),
                #fafafa;

            color: #333;

            font-family:
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                "PingFang SC",
                "Hiragino Sans GB",
                "Microsoft YaHei",
                sans-serif;

            -webkit-font-smoothing: antialiased;
        }

        .login-page {
            width: 100%;
            max-width: 430px;
        }

        .brand {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand a {
            display: inline-block;

            color: #222;

            text-decoration: none;

            font-size: 28px;
            font-weight: 800;

            letter-spacing: -1px;
        }

        .brand a span {
            color: #ff69b4;
        }

        .brand small {
            display: block;

            margin-top: 8px;

            color: #aaa;

            font-size: 12px;

            letter-spacing: 1.5px;
        }

        .login-card {
            background: #fff;

            border: 1px solid #eee;

            border-radius: 22px;

            padding: 38px;

            box-shadow:
                0 20px 60px rgba(0, 0, 0, .055);
        }

        .login-title {
            margin-bottom: 28px;
        }

        .login-title h1 {
            margin: 0;

            font-size: 25px;

            color: #222;
        }

        .login-title p {
            margin: 8px 0 0;

            color: #999;

            font-size: 14px;
        }

        .alert {
            margin-bottom: 20px;

            padding: 12px 14px;

            border-radius: 11px;

            font-size: 13px;

            line-height: 1.6;
        }

        .alert.error {
            background: #fff1f1;

            color: #d9534f;

            border: 1px solid #f5d3d3;
        }

        .alert.success {
            background: #f0fff7;

            color: #299b68;

            border: 1px solid #ccefe0;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;

            margin-bottom: 8px;

            color: #555;

            font-size: 13px;

            font-weight: 600;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap input {
            width: 100%;

            height: 48px;

            padding: 0 45px 0 14px;

            border: 1px solid #e6e6e6;

            border-radius: 11px;

            outline: none;

            background: #fff;

            color: #333;

            font-size: 14px;

            transition:
                border-color .2s,
                box-shadow .2s;
        }

        .input-wrap input:focus {
            border-color: #ff9ac9;

            box-shadow:
                0 0 0 4px rgba(255, 105, 180, .09);
        }

        .password-toggle {
            position: absolute;

            right: 13px;
            top: 50%;

            transform: translateY(-50%);

            border: 0;

            padding: 4px;

            background: transparent;

            color: #aaa;

            cursor: pointer;

            font-size: 16px;
        }

        .password-toggle:hover {
            color: #ff69b4;
        }

        .login-button {
            width: 100%;

            height: 48px;

            margin-top: 8px;

            border: 0;

            border-radius: 11px;

            background: #ff69b4;

            color: #fff;

            font-size: 14px;

            font-weight: 650;

            cursor: pointer;

            box-shadow:
                0 8px 22px rgba(255, 105, 180, .22);

            transition:
                transform .2s,
                box-shadow .2s,
                background .2s;
        }

        .login-button:hover {
            background: #ff5bad;

            transform: translateY(-1px);

            box-shadow:
                0 11px 27px rgba(255, 105, 180, .28);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .back-home {
            margin-top: 22px;

            text-align: center;
        }

        .back-home a {
            color: #999;

            text-decoration: none;

            font-size: 13px;
        }

        .back-home a:hover {
            color: #ff69b4;
        }

        .footer {
            margin-top: 25px;

            text-align: center;

            color: #bbb;

            font-size: 11px;

            letter-spacing: .5px;
        }

        @media (max-width: 520px) {

            body {
                padding: 18px;
            }

            .login-card {
                padding: 28px 22px;

                border-radius: 18px;
            }

            .brand {
                margin-bottom: 22px;
            }

            .brand a {
                font-size: 25px;
            }
        }

    </style>

</head>

<body>

<div class="login-page">

    <div class="brand">

        <a href="/">
            AZhai <span>Sub</span>
        </a>

        <small>
            ADMINISTRATION PANEL
        </small>

    </div>


    <div class="login-card">

        <div class="login-title">

            <h1>
                管理员登录
            </h1>

            <p>
                登录 AZhai Sub 管理后台
            </p>

        </div>


        <?php if ($passwordChanged): ?>

            <div class="alert success">
                密码修改成功，请使用新密码重新登录。
            </div>

        <?php endif; ?>


        <?php if ($error !== ''): ?>

            <div class="alert error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <form
            method="post"
            autocomplete="on"
        >

            <?= csrf_field() ?>


            <div class="form-group">

                <label for="username">
                    管理员账号
                </label>

                <div class="input-wrap">

                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="请输入管理员账号"
                        autocomplete="username"
                        value="<?= e($_POST['username'] ?? '') ?>"
                        required
                    >

                </div>

            </div>


            <div class="form-group">

                <label for="password">
                    密码
                </label>

                <div class="input-wrap">

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="请输入密码"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        id="passwordToggle"
                        aria-label="显示密码"
                    >
                        ◉
                    </button>

                </div>

            </div>


            <button
                type="submit"
                class="login-button"
            >
                登录管理后台
            </button>

        </form>

    </div>


    <div class="back-home">

        <a href="/">
            ← 返回 AZhai Sub 首页
        </a>

    </div>


    <div class="footer">
        © <?= date('Y') ?> AZhai Sub · AZhai
    </div>

</div>


<script>

const passwordInput =
    document.getElementById('password');

const passwordToggle =
    document.getElementById('passwordToggle');

passwordToggle.addEventListener('click', function () {

    if (passwordInput.type === 'password') {

        passwordInput.type = 'text';

        passwordToggle.textContent = '◎';

        passwordToggle.setAttribute(
            'aria-label',
            '隐藏密码'
        );

    } else {

        passwordInput.type = 'password';

        passwordToggle.textContent = '◉';

        passwordToggle.setAttribute(
            'aria-label',
            '显示密码'
        );
    }

});

</script>

</body>

</html>