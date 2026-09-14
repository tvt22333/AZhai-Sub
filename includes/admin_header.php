<?php

$config = $config ?? require __DIR__ . '/../config.php';

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

$pageTitle = $pageTitle ?? '管理后台';

if (empty($_SESSION['admin_id'])) {
    header('Location: /admin/login.php');
    exit;
}

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

function admin_nav_style(string $path): string
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

    <link rel="stylesheet" href="/assets/css/style.css">

    <title>
        <?= e($pageTitle) ?> -
        <?= e($config['site_name']) ?>
    </title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >

</head>

<body>

<header class="site-header">

    <div class="container header-inner">

        <a href="/admin/" class="logo">
            <?= e($config['site_name']) ?>
            <small>管理后台</small>
        </a>

        <nav>

            <a
                href="/admin/"
                <?= admin_nav_style('/admin/') ?>
            >
                首页
            </a>

            <a
                href="/admin/applications.php"
                <?= admin_nav_style('/admin/applications.php') ?>
            >
                域名审核
            </a>

            <a
                href="/admin/domains.php"
                <?= admin_nav_style('/admin/domains.php') ?>
            >
                域名管理
            </a>
<a
    href="/admin/txt_requests.php"
    <?= admin_nav_style('/admin/txt_requests.php') ?>
>
    TXT 验证
</a>
            <a
                href="/admin/users.php"
                <?= admin_nav_style('/admin/users.php') ?>
            >
                用户管理
            </a>

            <a
                href="/admin/providers.php"
                <?= admin_nav_style('/admin/providers.php') ?>
            >
                DNS 服务商
            </a>
<a
    href="/admin/provider_domains.php"
    <?= admin_nav_style('/admin/provider_domains.php') ?>
>
    DNS 域名
</a>
            <a
                href="/admin/email.php"
                <?= admin_nav_style('/admin/email.php') ?>
            >
                邮箱
            </a>

            <a
                href="/admin/logs.php"
                <?= admin_nav_style('/admin/logs.php') ?>
            >
                操作日志
            </a>
<a
    href="/admin/update.php"
    <?= admin_nav_style('/admin/update.php') ?>
>
    系统更新
</a>
            <a
                href="/admin/password.php"
                <?= admin_nav_style('/admin/password.php') ?>
            >
                修改密码
            </a>

            <a href="/admin/logout.php">
                退出
            </a>

        </nav>

    </div>

</header>

<main class="container admin-main">