<?php

$config = $config ?? require __DIR__ . '/../config.php';

require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? $config['site_name'];

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

function user_nav_style(string $path): string
{
    global $currentPath;

    return $currentPath === $path
        ? 'style="color:#ff6fa8 !important;"'
        : '';
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
        <?= e($pageTitle) ?>
        -
        <?= e($config['site_name_cn']) ?>
    </title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >

</head>

<body>

<header class="site-header">

    <div class="container header-inner">

        <a
            href="/"
            class="logo"
        >
            <?= e($config['site_name']) ?>
        </a>

        <nav>

            <a
                href="/"
                <?= user_nav_style('/') ?>
            >
                首页
            </a>

            <?php if (!empty($_SESSION['user_id'])): ?>

                <a
                    href="/dashboard/"
                    <?= user_nav_style('/dashboard/') ?>
                >
                    控制台
                </a>
<a
    href="/dashboard/txt_requests.php"
    <?= user_nav_style('/dashboard/txt_requests.php') ?>
>
    TXT 验证
</a>
                <a
                    href="/dashboard/settings.php"
                    <?= user_nav_style('/dashboard/settings.php') ?>
                >
                    账号设置
                </a>

                <a href="/logout.php">
                    退出
                </a>

            <?php else: ?>

                <a
                    href="/login.php"
                    <?= user_nav_style('/login.php') ?>
                >
                    登录
                </a>

                <?php if ($config['registration_enabled']): ?>

                    <a
                        href="/register.php"
                        <?= user_nav_style('/register.php') ?>
                    >
                        注册
                    </a>

                <?php endif; ?>

            <?php endif; ?>

        </nav>

    </div>

</header>

<main class="container">