<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_login();

$pdo = db();

$userId = current_user_id();

$stmt = $pdo->prepare("
    SELECT
        id,
        username,
        email,
        status,
        oauth_provider,
        oauth_sub,
        created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    logout_user();
    redirect('/login.php');
}


$stmt = $pdo->prepare("
    SELECT
        id,
        subdomain,
        full_domain,
        status,
        created_at,
        updated_at
    FROM domains
    WHERE user_id = ?
      AND status <> 'deleted'
    ORDER BY id DESC
");

$stmt->execute([$userId]);

$domains = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $pdo->prepare("
    SELECT
        da.id,
        da.subdomain,
        da.provider_domain_id,
        da.reason,
        da.status,
        da.review_reason,
        da.created_at,
        da.reviewed_at,
        dpd.domain AS root_domain
    FROM domain_applications da
    LEFT JOIN dns_provider_domains dpd
        ON dpd.id = da.provider_domain_id
    WHERE da.user_id = ?
    ORDER BY da.id DESC
    LIMIT 10
");

$stmt->execute([$userId]);

$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusLabels = [
    'pending' => '审核中',
    'active' => '正常',
    'suspended' => '已暂停',
    'deleted' => '已删除',
];

$statusClasses = [
    'pending' => 'warning',
    'active' => 'success',
    'suspended' => 'danger',
    'deleted' => 'muted',
];

$applicationLabels = [
    'pending' => '审核中',
    'approved' => '已通过',
    'rejected' => '已拒绝',
];

$applicationClasses = [
    'pending' => 'warning',
    'approved' => 'success',
    'rejected' => 'danger',
];

$title = '控制台';

require __DIR__ . '/../includes/header.php';
?>

<div class="dashboard">

    <?php if ($message = flash('success')): ?>
        <div class="alert success">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($message = flash('error')): ?>
        <div class="alert error">
            <?= e($message) ?>
        </div>
    <?php endif; ?>


    <!-- 欢迎 -->
    <section class="card dashboard-welcome">

        <div>
            <span class="eyebrow">AZHAI SUB</span>

            <h1>
                欢迎回来，
                <?= e($user['username']) ?>
            </h1>

            <p class="muted">
                <?= $user['oauth_provider'] === 'azhai'
                    ? '已通过阿宅账号登录'
                    : '欢迎使用阿宅二级域名服务'
                ?>
            </p>
        </div>

        <div class="welcome-actions">
            <a href="/dashboard/apply.php" class="button">
                + 申请二级域名
            </a>

            <a href="/logout.php" class="button secondary">
                退出登录
            </a>
        </div>

    </section>


    <!-- 我的域名 -->
    <section class="card">

        <div class="card-header">

            <div>
                <h2>我的域名</h2>

                <p class="muted">
                    管理你已经获得的二级域名
                </p>
            </div>

            <span class="count">
                <?= count($domains) ?>
            </span>

        </div>


        <?php if (empty($domains)): ?>

            <div class="empty">

                <div class="empty-icon">🌐</div>

                <h3>还没有二级域名</h3>

                <p>
                    申请一个属于你的
                    <?= e($config['base_domain']) ?>
                    二级域名。
                </p>

                <a href="/dashboard/apply.php" class="button">
                    立即申请
                </a>

            </div>

        <?php else: ?>

            <div class="domain-grid">

                <?php foreach ($domains as $domain): ?>

                    <?php
                    $status = $domain['status'];
                    ?>

                    <a href="/dashboard/domains.php?id=<?= (int) $domain['id'] ?>">

                        <div class="domain-card-top">

                            <span class="domain-icon">
                                🌐
                            </span>

                            <span class="status <?= e(
                                $statusClasses[$status] ?? 'muted'
                            ) ?>">
                                <?= e(
                                    $statusLabels[$status] ?? $status
                                ) ?>
                            </span>

                        </div>

                        <strong class="domain-name">
                            <?= e($domain['full_domain']) ?>
                        </strong>

                        <span class="domain-arrow">
                            →
                        </span>

                    </a>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>


    <!-- 申请记录 -->
    <section class="card">

        <div class="card-header">

            <div>
                <h2>申请记录</h2>

                <p class="muted">
                    最近的二级域名申请
                </p>
            </div>

            <a href="/dashboard/apply.php" class="text-link">
                申请域名 →
            </a>

        </div>


        <?php if (empty($applications)): ?>

            <div class="empty small">
                暂无申请记录
            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>域名</th>
                            <th>状态</th>
                            <th>申请时间</th>
                            <th>审核结果</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($applications as $application): ?>

                        <?php
                        $status = $application['status'];

                        $subdomain = trim((string) $application['subdomain']);
                        $rootDomain = trim((string) ($application['root_domain'] ?? ''));

                        if ($rootDomain !== '') {
                            $fullDomain = $subdomain . '.' . $rootDomain;
                        } else {
                            $fullDomain = $subdomain;
                        }
                        ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= e($fullDomain) ?>
                                </strong>
                            </td>

                            <td>
                                <span class="status <?= e(
                                    $applicationClasses[$status] ?? 'muted'
                                ) ?>">
                                    <?= e(
                                        $applicationLabels[$status] ?? $status
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= e($application['created_at']) ?>
                            </td>

                            <td>

                                <?php if ($status === 'rejected'): ?>

                                    <?= e(
                                        $application['review_reason']
                                        ?: '未填写原因'
                                    ) ?>

                                <?php elseif ($status === 'approved'): ?>

                                    已通过

                                <?php else: ?>

                                    等待管理员审核

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>


    <!-- 账号信息 -->
    <section class="card">

        <div class="card-header">

            <div>
                <h2>账号信息</h2>
                <p class="muted">
                    当前登录账号
                </p>
            </div>

        </div>

        <div class="info-grid">

            <div class="info-item">
                <span>用户名</span>
                <strong>
                    <?= e($user['username']) ?>
                </strong>
            </div>

            <div class="info-item">
                <span>邮箱</span>
                <strong>
                    <?= e($user['email']) ?>
                </strong>
            </div>

            <div class="info-item">
                <span>登录方式</span>
                <strong>
                    <?= $user['oauth_provider'] === 'azhai'
                        ? '阿宅账号 OAuth'
                        : '账号密码'
                    ?>
                </strong>
            </div>

            <div class="info-item">
                <span>注册时间</span>
                <strong>
                    <?= e($user['created_at']) ?>
                </strong>
            </div>

        </div>

    </section>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>