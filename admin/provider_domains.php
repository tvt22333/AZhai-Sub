<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/dns/DnsManager.php';
require_once __DIR__ . '/../includes/dns/DnsProviderInterface.php';
require_once __DIR__ . '/../includes/dns/CloudflareProvider.php';
require_once __DIR__ . '/../includes/dns/AliyunProvider.php';

require_admin();

$pdo = db();

function provider_domains_redirect(): never
{
    redirect('/admin/provider_domains.php');
}

function create_dns_provider(
    string $type,
    array $config
): DnsProviderInterface {

    if ($type === 'cloudflare') {
        return new CloudflareProvider($config);
    }

    if ($type === 'aliyun') {
        return new AliyunProvider($config);
    }

    throw new RuntimeException(
        '不支持的 DNS 服务商'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = $_POST['action'] ?? '';

    
    if ($action === 'sync') {

        $providerId =
            (int)($_POST['provider_id'] ?? 0);

        if ($providerId <= 0) {
            flash(
                'error',
                '请选择 DNS 服务商'
            );

            provider_domains_redirect();
        }

        try {

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    name,
                    type,
                    config,
                    status
                FROM dns_providers
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $providerId
            ]);

            $provider = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$provider) {
                throw new RuntimeException(
                    'DNS 服务商不存在'
                );
            }

            if ((int)$provider['status'] !== 1) {
                throw new RuntimeException(
                    'DNS 服务商已停用'
                );
            }

            $providerConfig =
                json_decode(
                    (string)$provider['config'],
                    true
                );

            if (!is_array($providerConfig)) {
                $providerConfig = [];
            }

            $dnsProvider =
                create_dns_provider(
                    $provider['type'],
                    $providerConfig
                );

            if (!method_exists(
                $dnsProvider,
                'listDomains'
            )) {
                throw new RuntimeException(
                    '当前 DNS 服务商不支持自动获取域名'
                );
            }

            $remoteDomains =
                $dnsProvider->listDomains();

            $stmt = $pdo->prepare("
                INSERT INTO dns_provider_domains
                (
                    provider_id,
                    domain,
                    provider_zone_id,
                    enabled,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    0,
                    NOW(),
                    NOW()
                )
                ON DUPLICATE KEY UPDATE
                    provider_zone_id =
                        VALUES(provider_zone_id),
                    updated_at = NOW()
            ");

            $count = 0;

            foreach ($remoteDomains as $item) {

                $domain = strtolower(
                    trim(
                        (string)(
                            $item['domain']
                            ?? ''
                        )
                    )
                );

                $zoneId = trim(
                    (string)(
                        $item['zone_id']
                        ?? ''
                    )
                );

                if ($domain === '') {
                    continue;
                }

                $stmt->execute([
                    $providerId,
                    $domain,
                    $zoneId !== ''
                        ? $zoneId
                        : null
                ]);

                $count++;
            }

            log_action(
                null,
                current_admin_id(),
                'sync_provider_domains',
                $provider['name']
            );

            flash(
                'success',
                '域名同步完成，共获取 ' .
                $count .
                ' 个域名'
            );

        } catch (Throwable $e) {

            flash(
                'error',
                $e->getMessage()
            );
        }

        provider_domains_redirect();
    }

    
    if ($action === 'toggle') {

        $domainId =
            (int)($_POST['domain_id'] ?? 0);

        if ($domainId <= 0) {
            flash(
                'error',
                '无效的域名 ID'
            );

            provider_domains_redirect();
        }

        $stmt = $pdo->prepare("
            SELECT
                id,
                domain,
                enabled
            FROM dns_provider_domains
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $domainId
        ]);

        $domain = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$domain) {
            flash(
                'error',
                '域名不存在'
            );

            provider_domains_redirect();
        }

        $newStatus =
            (int)$domain['enabled'] === 1
                ? 0
                : 1;

        $stmt = $pdo->prepare("
            UPDATE dns_provider_domains
            SET
                enabled = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $newStatus,
            $domainId
        ]);

        log_action(
            null,
            current_admin_id(),
            'update_provider_domain',
            $domain['domain']
        );

        flash(
            'success',
            $newStatus === 1
                ? '域名已开放给用户'
                : '域名已停止提供给用户'
        );

        provider_domains_redirect();
    }
}
$selectedProviderId = (int)(
    $_GET['provider_id']
    ?? 0
);
$providers = $pdo->query("
    SELECT
        id,
        name,
        type,
        status
    FROM dns_providers
    ORDER BY id ASC
")->fetchAll(
    PDO::FETCH_ASSOC
);

$domains = $pdo->query("
    SELECT
        d.id,
        d.provider_id,
        d.domain,
        d.provider_zone_id,
        d.enabled,
        d.updated_at,
        p.name AS provider_name,
        p.type AS provider_type,
        p.status AS provider_status
    FROM dns_provider_domains d
    INNER JOIN dns_providers p
        ON p.id = d.provider_id
    ORDER BY
        p.id ASC,
        d.domain ASC
")->fetchAll(
    PDO::FETCH_ASSOC
);

$pageTitle = '可用域名';

require __DIR__ .
    '/../includes/admin_header.php';

?>

<div class="admin-dashboard">

    <div class="admin-welcome">

        <h1>可用域名</h1>

        <p>
            自动获取 DNS 服务商中的域名，并选择允许用户使用的域名。
        </p>

    </div>

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


    <div class="admin-card">

        <h2>
            同步 DNS 域名
        </h2>

        <form method="post">

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="sync"
            >

            <label>
                DNS 服务商
            </label>

            <select
                name="provider_id"
                required
            >

                <option value="">
                    请选择 DNS 服务商
                </option>

                <?php foreach ($providers as $provider): ?>

                    <option
    value="<?= (int)$provider['id'] ?>"
    <?= (int)$provider['status'] !== 1
        ? 'disabled'
        : '' ?>
    <?= (int)$provider['id'] === $selectedProviderId
        ? 'selected'
        : '' ?>
>
                    >
                        <?= e($provider['name']) ?>
                        -
                        <?= $provider['type'] === 'cloudflare'
                            ? 'Cloudflare'
                            : '阿里云 DNS' ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <button type="submit">
                自动获取域名
            </button>

        </form>

    </div>


    <br>


    <div class="admin-card">

        <h2>
            DNS 域名
        </h2>

        <?php if (!$domains): ?>

            <p>
                暂时没有获取到 DNS 域名。
            </p>

        <?php else: ?>

            <div style="overflow-x:auto">

                <table class="admin-table">

                    <thead>

                    <tr>
                        <th>域名</th>
                        <th>服务商</th>
                        <th>类型</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($domains as $domain): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= e($domain['domain']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= e($domain['provider_name']) ?>
                            </td>

                            <td>
                                <?= $domain['provider_type'] === 'cloudflare'
                                    ? 'Cloudflare'
                                    : '阿里云 DNS' ?>
                            </td>

                            <td>

                                <?php if (
                                    (int)$domain['enabled'] === 1
                                ): ?>

                                    <span class="status status-1">
                                        已开放
                                    </span>

                                <?php else: ?>

                                    <span class="status status-0">
                                        未开放
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <form
                                    method="post"
                                    style="display:inline"
                                >

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="toggle"
                                    >

                                    <input
                                        type="hidden"
                                        name="domain_id"
                                        value="<?= (int)$domain['id'] ?>"
                                    >

                                    <button type="submit">

                                        <?= (int)$domain['enabled'] === 1
                                            ? '停止提供'
                                            : '开放给用户' ?>

                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

<?php

require __DIR__ .
    '/../includes/admin_footer.php';

?>