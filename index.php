<?php

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/updater.php';

require_admin();

$pageTitle = '管理后台';

$pdo = db();



$totalUsers = (int)$pdo->query(
    "SELECT COUNT(*) FROM users"
)->fetchColumn();

$totalDomains = (int)$pdo->query(
    "SELECT COUNT(*) FROM domains"
)->fetchColumn();

$activeDomains = (int)$pdo->query(
    "SELECT COUNT(*) FROM domains WHERE status = 'active'"
)->fetchColumn();

$pendingApplications = (int)$pdo->query(
    "SELECT COUNT(*) FROM domain_applications WHERE status = 'pending'"
)->fetchColumn();

$totalRecords = (int)$pdo->query(
    "SELECT COUNT(*) FROM domain_records"
)->fetchColumn();

$totalProviders = (int)$pdo->query(
    "SELECT COUNT(*) FROM dns_providers"
)->fetchColumn();



$recentUsers = $pdo->query("
    SELECT
        id,
        username,
        email,
        status,
        created_at
    FROM users
    ORDER BY id DESC
    LIMIT 8
")->fetchAll();



$recentApplications = $pdo->query("
    SELECT
        da.id,
        da.user_id,
        da.provider_domain_id,
        da.subdomain,
        da.reason,
        da.status,
        da.created_at,
        u.username,
        dpd.domain AS provider_domain
    FROM domain_applications da
    LEFT JOIN users u
        ON u.id = da.user_id
    LEFT JOIN dns_provider_domains dpd
        ON dpd.id = da.provider_domain_id
    ORDER BY da.id DESC
    LIMIT 8
")->fetchAll();



$updateNotice = null;

$updateSettingsFile =
    __DIR__ .
    '/../storage/update_settings.json';

$autoCheckEnabled = false;

if (is_file($updateSettingsFile)) {

    $settingsJson = @file_get_contents(
        $updateSettingsFile
    );

    if ($settingsJson !== false) {

        $settings = json_decode(
            $settingsJson,
            true
        );

        if (is_array($settings)) {

            $autoCheckEnabled =
                !empty($settings['auto_check']);

        }
    }
}

if ($autoCheckEnabled) {

    try {

        $updateResult = update_check();

        if (
            is_array($updateResult)
            &&
            !empty($updateResult['success'])
            &&
            !empty($updateResult['available'])
        ) {
            $updateNotice = $updateResult;
        }

    } catch (Throwable $e) {

        $updateNotice = null;

    }
}



$latestVersion = '';
$releaseNotes = '';

if (is_array($updateNotice)) {

    $release =
        $updateNotice['release']
        ?? [];

    if (is_array($release)) {

        $latestVersion =
            trim(
                (string)(
                    $release['version']
                    ?? ''
                )
            );

        $releaseNotes =
            trim(
                (string)(
                    $release['release_notes']
                    ?? $release['notes']
                    ?? ''
                )
            );
    }
}

$currentVersion =
    trim(
        (string)(
            $config['version']
            ?? '未知'
        )
    );

require __DIR__ . '/../includes/admin_header.php';

?>

<style>

.admin-update-notice {
    position: relative;
    display: flex;
    align-items: center;
    gap: 18px;
    margin: 0 0 26px;
    padding: 20px 22px;
    border: 1px solid rgba(125, 92, 255, 0.16);
    border-radius: 20px;
    background:
        linear-gradient(
            135deg,
            rgba(255, 255, 255, 0.98),
            rgba(248, 246, 255, 0.98)
        );
    box-shadow:
        0 10px 35px rgba(50, 40, 100, 0.08);
    overflow: hidden;
}

.admin-update-notice::before {
    content: "";
    position: absolute;
    top: -80px;
    right: -50px;
    width: 180px;
    height: 180px;
    border-radius: 50%;
    background: rgba(125, 92, 255, 0.07);
    pointer-events: none;
}

.admin-update-icon {
    position: relative;
    z-index: 1;
    flex: 0 0 52px;
    width: 52px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 16px;
    background:
        linear-gradient(
            135deg,
            #8b6cff,
            #6d4df5
        );
    color: #fff;
    font-size: 23px;
    box-shadow:
        0 8px 20px rgba(109, 77, 245, 0.24);
}

.admin-update-content {
    position: relative;
    z-index: 1;
    flex: 1;
    min-width: 0;
}

.admin-update-title {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0 0 7px;
    font-size: 17px;
    font-weight: 700;
    color: #242033;
}

.admin-update-badge {
    display: inline-flex;
    align-items: center;
    height: 23px;
    padding: 0 9px;
    border-radius: 999px;
    background: rgba(109, 77, 245, 0.1);
    color: #6d4df5;
    font-size: 11px;
    font-weight: 700;
}

.admin-update-version {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 7px;
    color: #777286;
    font-size: 13px;
}

.admin-update-version strong {
    color: #393343;
    font-weight: 600;
}

.admin-update-arrow {
    color: #aaa3b5;
    font-size: 14px;
}

.admin-update-latest {
    color: #6d4df5;
    font-weight: 700;
}

.admin-update-notes {
    margin-top: 7px;
    color: #8b8597;
    font-size: 12px;
    line-height: 1.65;
    max-width: 700px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.admin-update-action {
    position: relative;
    z-index: 1;
    flex: 0 0 auto;
}

.admin-update-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-width: 108px;
    height: 40px;
    padding: 0 17px;
    border-radius: 12px;
    background: #6d4df5;
    color: #fff !important;
    text-decoration: none !important;
    font-size: 13px;
    font-weight: 600;
    box-shadow:
        0 7px 18px rgba(109, 77, 245, 0.2);
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        background 0.2s ease;
}

.admin-update-button:hover {
    background: #5d3fe2;
    transform: translateY(-2px);
    box-shadow:
        0 10px 24px rgba(109, 77, 245, 0.27);
}

.admin-update-button:active {
    transform: translateY(0);
}

.admin-update-button-icon {
    font-size: 14px;
    line-height: 1;
}

@media (max-width: 700px) {

    .admin-update-notice {
        align-items: flex-start;
        gap: 13px;
        padding: 17px;
        border-radius: 17px;
    }

    .admin-update-icon {
        flex-basis: 44px;
        width: 44px;
        height: 44px;
        border-radius: 13px;
        font-size: 19px;
    }

    .admin-update-title {
        flex-wrap: wrap;
        font-size: 15px;
    }

    .admin-update-version {
        font-size: 12px;
    }

    .admin-update-notes {
        font-size: 11px;
    }

    .admin-update-action {
        align-self: center;
    }

    .admin-update-button {
        min-width: auto;
        width: 40px;
        height: 40px;
        padding: 0;
        border-radius: 12px;
    }

    .admin-update-button-text {
        display: none;
    }

}

</style>

<div class="admin-dashboard">

    <?php if ($updateNotice): ?>

        <div class="admin-update-notice">

            <div class="admin-update-icon">
                ↗
            </div>

            <div class="admin-update-content">

                <div class="admin-update-title">

                    <span>发现新版本</span>

                    <span class="admin-update-badge">
                        UPDATE
                    </span>

                </div>

                <div class="admin-update-version">

                    <span>
                        当前
                        <strong>
                            v<?= e($currentVersion) ?>
                        </strong>
                    </span>

                    <span class="admin-update-arrow">
                        →
                    </span>

                    <span class="admin-update-latest">
                        v<?= e(
                            $latestVersion !== ''
                                ? $latestVersion
                                : '最新版本'
                        ) ?>
                    </span>

                </div>

                <?php if ($releaseNotes !== ''): ?>

                    <div class="admin-update-notes">
                        <?= e($releaseNotes) ?>
                    </div>

                <?php endif; ?>

            </div>

            <div class="admin-update-action">

                <a
                    href="/admin/update.php"
                    class="admin-update-button"
                >
                    <span class="admin-update-button-icon">
                        ↗
                    </span>

                    <span class="admin-update-button-text">
                        查看更新
                    </span>
                </a>

            </div>

        </div>

    <?php endif; ?>


    <div class="admin-welcome">

        <h1>AZhai Sub 管理后台</h1>

        <p>
            管理用户、域名、DNS 服务商以及域名申请。
        </p>

    </div>


    <div class="stats-grid">

        <div class="stat-card">

            <div class="stat-title">
                用户数量
            </div>

            <div class="stat-number">
                <?= $totalUsers ?>
            </div>

            <div class="stat-desc">
                注册用户
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                域名数量
            </div>

            <div class="stat-number">
                <?= $totalDomains ?>
            </div>

            <div class="stat-desc">
                已申请域名
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                正常域名
            </div>

            <div class="stat-number">
                <?= $activeDomains ?>
            </div>

            <div class="stat-desc">
                当前正在使用
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                待审核
            </div>

            <div class="stat-number">
                <?= $pendingApplications ?>
            </div>

            <div class="stat-desc">
                等待管理员处理
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                DNS 记录
            </div>

            <div class="stat-number">
                <?= $totalRecords ?>
            </div>

            <div class="stat-desc">
                A / AAAA / CNAME / TXT
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                DNS 服务商
            </div>

            <div class="stat-number">
                <?= $totalProviders ?>
            </div>

            <div class="stat-desc">
                Cloudflare / 阿里云
            </div>

        </div>

    </div>


    <div class="admin-card">

        <h2>
            快捷操作
        </h2>

        <div class="quick-actions">

            <a href="/admin/applications.php">

                <strong>
                    域名审核
                </strong>

                <br>

                <small>
                    处理域名申请
                </small>

            </a>


            <a href="/admin/domains.php">

                <strong>
                    域名管理
                </strong>

                <br>

                <small>
                    管理所有域名
                </small>

            </a>


            <a href="/admin/users.php">

                <strong>
                    用户管理
                </strong>

                <br>

                <small>
                    管理注册用户
                </small>

            </a>


            <a href="/admin/providers.php">

                <strong>
                    DNS 服务商
                </strong>

                <br>

                <small>
                    管理 DNS API
                </small>

            </a>


            <a href="/admin/logs.php">

                <strong>
                    操作日志
                </strong>

                <br>

                <small>
                    查看操作记录
                </small>

            </a>

        </div>

    </div>


    <br>


    <div class="admin-grid">


        <div class="admin-card">

            <h2>
                最新用户
            </h2>

            <?php if (!$recentUsers): ?>

                <p>
                    暂无用户。
                </p>

            <?php else: ?>

                <table class="admin-table">

                    <thead>

                    <tr>

                        <th>
                            用户名
                        </th>

                        <th>
                            邮箱
                        </th>

                        <th>
                            状态
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($recentUsers as $user): ?>

                        <?php

                        $userStatus = [
                            'active' => '正常',
                            'disabled' => '已禁用'
                        ];

                        $status =
                            $user['status'];

                        ?>

                        <tr>

                            <td>
                                <?= e(
                                    $user['username']
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    $user['email']
                                ) ?>
                            </td>

                            <td>

                                <span
                                    class="status status-<?= e(
                                        $status
                                    ) ?>"
                                >
                                    <?= e(
                                        $userStatus[$status]
                                        ?? $status
                                    ) ?>
                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php endif; ?>

        </div>


        <div class="admin-card">

            <h2>
                最新域名申请
            </h2>

            <?php if (!$recentApplications): ?>

                <p>
                    暂无申请。
                </p>

            <?php else: ?>

                <table class="admin-table">

                    <thead>

                    <tr>

                        <th>
                            域名
                        </th>

                        <th>
                            用户
                        </th>

                        <th>
                            状态
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach (
                        $recentApplications
                        as $application
                    ): ?>

                        <?php

                        $applicationStatus = [
                            'pending' => '待审核',
                            'approved' => '已通过',
                            'rejected' => '已拒绝'
                        ];

                        $status =
                            $application['status'];

                        $rootDomain = trim(
                            (string)(
                                $application[
                                    'provider_domain'
                                ]
                                ?? ''
                            )
                        );

                        if ($rootDomain === '') {

                            $rootDomain = trim(
                                (string)(
                                    $config[
                                        'base_domain'
                                    ]
                                    ?? ''
                                )
                            );
                        }

                        $rootDomain =
                            strtolower(
                                rtrim(
                                    $rootDomain,
                                    '.'
                                )
                            );

                        $subdomain = trim(
                            (string)(
                                $application[
                                    'subdomain'
                                ]
                                ?? ''
                            )
                        );

                        if (
                            $subdomain !== ''
                            &&
                            $rootDomain !== ''
                        ) {

                            $displayDomain =
                                $subdomain .
                                '.' .
                                $rootDomain;

                        } else {

                            $displayDomain = '-';

                        }

                        ?>

                        <tr>

                            <td>
                                <?= e(
                                    $displayDomain
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    $application[
                                        'username'
                                    ]
                                    ?? '-'
                                ) ?>
                            </td>

                            <td>

                                <span
                                    class="status status-<?= e(
                                        $status
                                    ) ?>"
                                >
                                    <?= e(
                                        $applicationStatus[
                                            $status
                                        ]
                                        ?? $status
                                    ) ?>
                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php

require __DIR__ .
    '/../includes/admin_footer.php';

?>
