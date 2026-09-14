<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/dns/DnsProviderInterface.php';
require_once __DIR__ . '/../includes/dns/CloudflareProvider.php';
require_once __DIR__ . '/../includes/dns/AliyunProvider.php';

require_admin();

$pdo = db();

$pageTitle = 'DNS 服务商';


function provider_redirect(): never
{
    redirect('/admin/providers.php');
}


function provider_config_json(array $data): string
{
    $json = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        throw new RuntimeException(
            'DNS 服务商配置保存失败'
        );
    }

    return $json;
}


function create_dns_provider(
    string $type,
    array $config
): DnsProviderInterface {

    switch ($type) {

        case 'cloudflare':

            return new CloudflareProvider(
                $config
            );

        case 'aliyun':

            return new AliyunProvider(
                $config
            );

        default:

            throw new RuntimeException(
                '不支持的 DNS 服务商'
            );
    }
}


function sync_provider_domains(
    PDO $pdo,
    int $providerId,
    string $type,
    array $providerConfig
): int {

    $provider = create_dns_provider(
        $type,
        $providerConfig
    );

    $result = $provider->listDomains();

    if (
        empty($result['success'])
        || !isset($result['domains'])
        || !is_array($result['domains'])
    ) {

        throw new RuntimeException(
            $result['message']
            ?? '无法获取 DNS 域名'
        );
    }

    $count = 0;

    foreach ($result['domains'] as $domainInfo) {

        if (!is_array($domainInfo)) {
            continue;
        }

        $domain = strtolower(
            trim(
                (string)(
                    $domainInfo['domain']
                    ?? ''
                )
            )
        );

        $domain = trim(
            $domain,
            '.'
        );

        $zoneId = trim(
            (string)(
                $domainInfo['provider_zone_id']
                ?? $domainInfo['zone_id']
                ?? $domainInfo['id']
                ?? ''
            )
        );

        if ($domain === '') {
            continue;
        }

        if (
            strlen($domain) > 253
            || !preg_match(
                '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i',
                $domain
            )
        ) {
            continue;
        }

        $stmt = $pdo->prepare("
            SELECT
                id,
                provider_zone_id,
                enabled
            FROM dns_provider_domains
            WHERE provider_id = ?
              AND domain = ?
            LIMIT 1
        ");

        $stmt->execute([
            $providerId,
            $domain
        ]);

        $existing = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if ($existing) {

            $oldZoneId = trim(
                (string)(
                    $existing['provider_zone_id']
                    ?? ''
                )
            );

            if (
                $zoneId !== ''
                && $zoneId !== $oldZoneId
            ) {

                $update = $pdo->prepare("
                    UPDATE dns_provider_domains
                    SET
                        provider_zone_id = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                $update->execute([
                    $zoneId,
                    (int)$existing['id']
                ]);
            }

        } else {

            $insert = $pdo->prepare("
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
            ");

            $insert->execute([
                $providerId,
                $domain,
                $zoneId !== ''
                    ? $zoneId
                    : null
            ]);

            $count++;
        }
    }

    return $count;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = trim(
        (string)(
            $_POST['action']
            ?? ''
        )
    );


    
    if ($action === 'delete') {

        $id = (int)(
            $_POST['id']
            ?? 0
        );

        if ($id <= 0) {

            flash(
                'error',
                '无效的服务商 ID'
            );

            provider_redirect();
        }

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                type
            FROM dns_providers
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $id
        ]);

        $provider = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$provider) {

            flash(
                'error',
                'DNS 服务商不存在'
            );

            provider_redirect();
        }

        $stmt = $pdo->prepare("
            DELETE FROM dns_providers
            WHERE id = ?
        ");

        $stmt->execute([
            $id
        ]);

        log_action(
            null,
            current_admin_id(),
            'delete_provider',
            $provider['name']
        );

        flash(
            'success',
            'DNS 服务商及其 DNS 域名已删除'
        );

        provider_redirect();
    }


    
    if ($action === 'sync_domains') {

        $id = (int)(
            $_POST['id']
            ?? 0
        );

        if ($id <= 0) {

            flash(
                'error',
                '无效的服务商 ID'
            );

            provider_redirect();
        }

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                status
            FROM dns_providers
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $id
        ]);

        $provider = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$provider) {

            flash(
                'error',
                'DNS 服务商不存在'
            );

            provider_redirect();
        }

        if ((int)$provider['status'] !== 1) {

            flash(
                'error',
                'DNS 服务商已停用'
            );

            provider_redirect();
        }

        redirect(
            '/admin/provider_domains.php?provider_id='
            . $id
        );
    }


    
    if ($action === 'add') {

        $name = trim(
            (string)(
                $_POST['name']
                ?? ''
            )
        );

        $type = trim(
            (string)(
                $_POST['type']
                ?? ''
            )
        );

        $configData =
            $_POST['provider_config']
            ?? [];

        if (!is_array($configData)) {
            $configData = [];
        }

        if ($name === '') {

            flash(
                'error',
                '请输入服务商名称'
            );

            provider_redirect();
        }

        if (
            !in_array(
                $type,
                [
                    'cloudflare',
                    'aliyun'
                ],
                true
            )
        ) {

            flash(
                'error',
                '不支持的 DNS 服务商'
            );

            provider_redirect();
        }

        try {

            
            if ($type === 'cloudflare') {

                $email = trim(
                    (string)(
                        $configData['email']
                        ?? ''
                    )
                );

                $apiKey = trim(
                    (string)(
                        $configData['api_key']
                        ?? ''
                    )
                );

                if ($email === '') {

                    throw new RuntimeException(
                        '请输入 Cloudflare 邮箱'
                    );
                }

                if ($apiKey === '') {

                    throw new RuntimeException(
                        '请输入 Cloudflare Global API Key'
                    );
                }

                $configData = [
                    'email' => $email,
                    'api_key' => $apiKey
                ];
            }


            
            if ($type === 'aliyun') {

                $accessKeyId = trim(
                    (string)(
                        $configData['access_key_id']
                        ?? ''
                    )
                );

                $accessKeySecret = trim(
                    (string)(
                        $configData['access_key_secret']
                        ?? ''
                    )
                );

                $endpoint = trim(
                    (string)(
                        $configData['endpoint']
                        ?? 'alidns.cn-hangzhou.aliyuncs.com'
                    )
                );

                if ($accessKeyId === '') {

                    throw new RuntimeException(
                        '请输入阿里云 AccessKey ID'
                    );
                }

                if ($accessKeySecret === '') {

                    throw new RuntimeException(
                        '请输入阿里云 AccessKey Secret'
                    );
                }

                if ($endpoint === '') {

                    $endpoint =
                        'alidns.cn-hangzhou.aliyuncs.com';
                }

                $configData = [
                    'access_key_id' =>
                        $accessKeyId,

                    'access_key_secret' =>
                        $accessKeySecret,

                    'endpoint' =>
                        $endpoint
                ];
            }


            
            $provider = create_dns_provider(
                $type,
                $configData
            );

            $testResult =
                $provider->testConnection();

            if (
                empty(
                    $testResult['success']
                )
            ) {

                throw new RuntimeException(
                    $testResult['message']
                    ?? 'DNS 服务商连接失败'
                );
            }


            
            $domainResult =
                $provider->listDomains();

            if (
                empty(
                    $domainResult['success']
                )
            ) {

                throw new RuntimeException(
                    $domainResult['message']
                    ?? 'DNS 域名获取失败'
                );
            }

            if (
                !isset(
                    $domainResult['domains']
                )
                || !is_array(
                    $domainResult['domains']
                )
                || empty(
                    $domainResult['domains']
                )
            ) {

                throw new RuntimeException(
                    '该 DNS 服务商账号下没有可用域名'
                );
            }


            
            $configJson =
                provider_config_json(
                    $configData
                );

            $stmt = $pdo->prepare("
                INSERT INTO dns_providers
                (
                    name,
                    type,
                    config,
                    status,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    1,
                    NOW(),
                    NOW()
                )
            ");

            $stmt->execute([
                $name,
                $type,
                $configJson
            ]);

            $providerId =
                (int)$pdo->lastInsertId();


            
            $domainCount =
                sync_provider_domains(
                    $pdo,
                    $providerId,
                    $type,
                    $configData
                );


            log_action(
                null,
                current_admin_id(),
                'add_provider',
                $name
            );

            flash(
                'success',
                'DNS 服务商添加成功，已获取 '
                . $domainCount
                . ' 个 DNS 域名'
            );

        } catch (Throwable $e) {

            flash(
                'error',
                $e->getMessage()
            );
        }

        provider_redirect();
    }


    
    if ($action === 'edit') {

        $id = (int)(
            $_POST['id']
            ?? 0
        );

        $name = trim(
            (string)(
                $_POST['name']
                ?? ''
            )
        );

        $type = trim(
            (string)(
                $_POST['type']
                ?? ''
            )
        );

        $configData =
            $_POST['provider_config']
            ?? [];

        if (!is_array($configData)) {
            $configData = [];
        }

        if ($id <= 0) {

            flash(
                'error',
                '无效的服务商 ID'
            );

            provider_redirect();
        }

        if ($name === '') {

            flash(
                'error',
                '请输入服务商名称'
            );

            provider_redirect();
        }

        if (
            !in_array(
                $type,
                [
                    'cloudflare',
                    'aliyun'
                ],
                true
            )
        ) {

            flash(
                'error',
                '不支持的 DNS 服务商'
            );

            provider_redirect();
        }


        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                type,
                config
            FROM dns_providers
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $id
        ]);

        $provider = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$provider) {

            flash(
                'error',
                'DNS 服务商不存在'
            );

            provider_redirect();
        }


        $oldConfig =
            json_decode(
                (string)$provider['config'],
                true
            );

        if (!is_array($oldConfig)) {
            $oldConfig = [];
        }


        try {

            
            if ($type === 'cloudflare') {

                $email = trim(
                    (string)(
                        $configData['email']
                        ?? ''
                    )
                );

                if ($email === '') {

                    $email = trim(
                        (string)(
                            $oldConfig['email']
                            ?? ''
                        )
                    );
                }

                $apiKey = trim(
                    (string)(
                        $configData['api_key']
                        ?? ''
                    )
                );

                if ($apiKey === '') {

                    $apiKey = trim(
                        (string)(
                            $oldConfig['api_key']
                            ?? ''
                        )
                    );
                }

                if ($email === '') {

                    throw new RuntimeException(
                        '请输入 Cloudflare 邮箱'
                    );
                }

                if ($apiKey === '') {

                    throw new RuntimeException(
                        '请输入 Cloudflare Global API Key'
                    );
                }

                $newConfig = [
                    'email' => $email,
                    'api_key' => $apiKey
                ];
            }


            
            else {

                $accessKeyId = trim(
                    (string)(
                        $configData['access_key_id']
                        ?? ''
                    )
                );

                $accessKeySecret = trim(
                    (string)(
                        $configData['access_key_secret']
                        ?? ''
                    )
                );

                $endpoint = trim(
                    (string)(
                        $configData['endpoint']
                        ?? ''
                    )
                );

                if ($accessKeyId === '') {

                    $accessKeyId = trim(
                        (string)(
                            $oldConfig['access_key_id']
                            ?? ''
                        )
                    );
                }

                if ($accessKeySecret === '') {

                    $accessKeySecret = trim(
                        (string)(
                            $oldConfig['access_key_secret']
                            ?? ''
                        )
                    );
                }

                if ($endpoint === '') {

                    $endpoint = trim(
                        (string)(
                            $oldConfig['endpoint']
                            ?? 'alidns.cn-hangzhou.aliyuncs.com'
                        )
                    );
                }

                if ($accessKeyId === '') {

                    throw new RuntimeException(
                        '请输入阿里云 AccessKey ID'
                    );
                }

                if ($accessKeySecret === '') {

                    throw new RuntimeException(
                        '请输入阿里云 AccessKey Secret'
                    );
                }

                if ($endpoint === '') {

                    $endpoint =
                        'alidns.cn-hangzhou.aliyuncs.com';
                }

                $newConfig = [
                    'access_key_id' =>
                        $accessKeyId,

                    'access_key_secret' =>
                        $accessKeySecret,

                    'endpoint' =>
                        $endpoint
                ];
            }


            
            $providerObject =
                create_dns_provider(
                    $type,
                    $newConfig
                );

            $testResult =
                $providerObject->testConnection();

            if (
                empty(
                    $testResult['success']
                )
            ) {

                throw new RuntimeException(
                    $testResult['message']
                    ?? 'DNS 服务商连接失败'
                );
            }


            
            $domainResult =
                $providerObject->listDomains();

            if (
                empty(
                    $domainResult['success']
                )
            ) {

                throw new RuntimeException(
                    $domainResult['message']
                    ?? 'DNS 域名获取失败'
                );
            }

            if (
                !isset(
                    $domainResult['domains']
                )
                || !is_array(
                    $domainResult['domains']
                )
                || empty(
                    $domainResult['domains']
                )
            ) {

                throw new RuntimeException(
                    '该 DNS 服务商账号下没有可用域名'
                );
            }


            
            $configJson =
                provider_config_json(
                    $newConfig
                );

            $stmt = $pdo->prepare("
                UPDATE dns_providers
                SET
                    name = ?,
                    type = ?,
                    config = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $name,
                $type,
                $configJson,
                $id
            ]);


            
            $domainCount =
                sync_provider_domains(
                    $pdo,
                    $id,
                    $type,
                    $newConfig
                );


            log_action(
                null,
                current_admin_id(),
                'update_provider',
                $name
            );

            flash(
                'success',
                'DNS 服务商配置已更新，新增 '
                . $domainCount
                . ' 个 DNS 域名'
            );

        } catch (Throwable $e) {

            flash(
                'error',
                $e->getMessage()
            );
        }

        provider_redirect();
    }
}



$providers = $pdo->query("
    SELECT
        id,
        name,
        type,
        config,
        status,
        created_at,
        updated_at
    FROM dns_providers
    ORDER BY id DESC
")->fetchAll(
    PDO::FETCH_ASSOC
);


$editId =
    (int)($_GET['edit'] ?? 0);

$editProvider = null;

if ($editId > 0) {

    foreach ($providers as $provider) {

        if (
            (int)$provider['id']
            === $editId
        ) {

            $provider['config'] =
                json_decode(
                    (string)$provider['config'],
                    true
                );

            if (
                !is_array(
                    $provider['config']
                )
            ) {

                $provider['config'] = [];
            }

            $editProvider = $provider;

            break;
        }
    }
}


require __DIR__ .
    '/../includes/admin_header.php';

?>

<div class="admin-dashboard">

    <div class="admin-welcome">

        <h1>
            DNS 服务商
        </h1>

        <p>
            配置 DNS 服务商 API，并自动获取该账号下的 DNS 域名
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


    <?php if ($editProvider): ?>

        <div class="admin-card">

            <h2>
                编辑 DNS 服务商
            </h2>

            <form method="post">

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="action"
                    value="edit"
                >

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int)$editProvider['id'] ?>"
                >


                <label>
                    服务商名称
                </label>

                <input
                    type="text"
                    name="name"
                    value="<?= e(
                        $editProvider['name']
                    ) ?>"
                    maxlength="100"
                    required
                >


                <label>
                    服务商类型
                </label>

                <select
                    name="type"
                    id="editProviderType"
                >

                    <option
                        value="cloudflare"
                        <?= $editProvider['type'] === 'cloudflare'
                            ? 'selected'
                            : '' ?>
                    >
                        Cloudflare
                    </option>

                    <option
                        value="aliyun"
                        <?= $editProvider['type'] === 'aliyun'
                            ? 'selected'
                            : '' ?>
                    >
                        阿里云 DNS
                    </option>

                </select>


                <div
                    id="editCloudflareFields"
                    style="<?= $editProvider['type'] === 'cloudflare'
                        ? ''
                        : 'display:none' ?>"
                >

                    <label>
                        Cloudflare 邮箱
                    </label>

                    <input
                        type="email"
                        name="provider_config[email]"
                        value="<?= e(
                            (string)(
                                $editProvider['config']['email']
                                ?? ''
                            )
                        ) ?>"
                        autocomplete="off"
                        required
                    >


                    <label>
                        Cloudflare Global API Key
                    </label>

                    <input
                        type="password"
                        name="provider_config[api_key]"
                        placeholder="留空表示保持原 API Key"
                        autocomplete="new-password"
                    >

                    <small>
                        Cloudflare 使用邮箱和 Global API Key 进行 API 认证
                    </small>

                </div>


                <div
                    id="editAliyunFields"
                    style="<?= $editProvider['type'] === 'aliyun'
                        ? ''
                        : 'display:none' ?>"
                >

                    <label>
                        阿里云 AccessKey ID
                    </label>

                    <input
                        type="text"
                        name="provider_config[access_key_id]"
                        value="<?= e(
                            (string)(
                                $editProvider['config']['access_key_id']
                                ?? ''
                            )
                        ) ?>"
                        autocomplete="off"
                    >


                    <label>
                        阿里云 AccessKey Secret
                    </label>

                    <input
                        type="password"
                        name="provider_config[access_key_secret]"
                        placeholder="留空表示保持原 Secret"
                        autocomplete="new-password"
                    >


                    <label>
                        API Endpoint
                    </label>

                    <input
                        type="text"
                        name="provider_config[endpoint]"
                        value="<?= e(
                            (string)(
                                $editProvider['config']['endpoint']
                                ?? 'alidns.cn-hangzhou.aliyuncs.com'
                            )
                        ) ?>"
                        placeholder="alidns.cn-hangzhou.aliyuncs.com"
                    >

                    <small>
                        保存时会自动获取该账号下的 DNS 域名
                    </small>

                </div>


                <div
                    style="
                        display:flex;
                        gap:10px;
                        align-items:center;
                        margin-top:20px;
                    "
                >

                    <button type="submit">
                        保存修改
                    </button>

                    <a
                        href="/admin/providers.php"
                        class="button"
                    >
                        取消
                    </a>

                </div>

            </form>

        </div>

        <br>

    <?php endif; ?>


    <div class="admin-card">

        <h2>
            添加 DNS 服务商
        </h2>

        <form method="post">

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="add"
            >


            <label>
                服务商名称
            </label>

            <input
                type="text"
                name="name"
                placeholder="例如：主 Cloudflare"
                maxlength="100"
                required
            >


            <label>
                服务商类型
            </label>

            <select
                name="type"
                id="providerType"
            >

                <option value="cloudflare">
                    Cloudflare
                </option>

                <option value="aliyun">
                    阿里云 DNS
                </option>

            </select>


            <div
                id="cloudflareFields"
            >

                <label>
                    Cloudflare 邮箱
                </label>

                <input
                    type="email"
                    name="provider_config[email]"
                    placeholder="Cloudflare 登录邮箱"
                    autocomplete="off"
                    required
                >


                <label>
                    Cloudflare Global API Key
                </label>

                <input
                    type="password"
                    name="provider_config[api_key]"
                    placeholder="Cloudflare Global API Key"
                    autocomplete="new-password"
                    required
                >

                <small>
                    添加后系统会自动获取该账号下的 DNS 域名
                </small>

            </div>


            <div
                id="aliyunFields"
                style="display:none"
            >

                <label>
                    阿里云 AccessKey ID
                </label>

                <input
                    type="text"
                    name="provider_config[access_key_id]"
                    placeholder="AccessKey ID"
                    autocomplete="off"
                >


                <label>
                    阿里云 AccessKey Secret
                </label>

                <input
                    type="password"
                    name="provider_config[access_key_secret]"
                    placeholder="AccessKey Secret"
                    autocomplete="new-password"
                >


                <label>
                    API Endpoint
                </label>

                <input
                    type="text"
                    name="provider_config[endpoint]"
                    value="alidns.cn-hangzhou.aliyuncs.com"
                    placeholder="alidns.cn-hangzhou.aliyuncs.com"
                >

                <small>
                    添加后系统会自动获取该账号下的 DNS 域名
                </small>

            </div>


            <button type="submit">
                添加服务商
            </button>

        </form>

    </div>


    <br>


    <div class="admin-card">

        <h2>
            已有 DNS 服务商
        </h2>

        <?php if (!$providers): ?>

            <p>
                暂时没有配置 DNS 服务商
            </p>

        <?php else: ?>

            <div style="overflow-x:auto">

                <table class="admin-table">

                    <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            名称
                        </th>

                        <th>
                            类型
                        </th>

                        <th>
                            DNS 域名
                        </th>

                        <th>
                            状态
                        </th>

                        <th>
                            更新时间
                        </th>

                        <th>
                            操作
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach (
                        $providers
                        as $provider
                    ): ?>

                        <?php

                        $providerStatus =
                            (int)$provider['status'];

                        $domainStmt =
                            $pdo->prepare("
                                SELECT
                                    COUNT(*)
                                FROM dns_provider_domains
                                WHERE provider_id = ?
                            ");

                        $domainStmt->execute([
                            (int)$provider['id']
                        ]);

                        $domainCount =
                            (int)$domainStmt->fetchColumn();

                        ?>

                        <tr>

                            <td>
                                <?= (int)$provider['id'] ?>
                            </td>

                            <td>

                                <strong>
                                    <?= e(
                                        $provider['name']
                                    ) ?>
                                </strong>

                            </td>

                            <td>

                                <?php if (
                                    $provider['type']
                                    === 'cloudflare'
                                ): ?>

                                    Cloudflare

                                <?php elseif (
                                    $provider['type']
                                    === 'aliyun'
                                ): ?>

                                    阿里云 DNS

                                <?php else: ?>

                                    <?= e(
                                        $provider['type']
                                    ) ?>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?= $domainCount ?>

                                个

                            </td>

                            <td>

                                <span
                                    class="status status-<?= $providerStatus ?>"
                                >
                                    <?= $providerStatus === 1
                                        ? '启用'
                                        : '禁用' ?>
                                </span>

                            </td>

                            <td>

                                <?= e(
                                    $provider['updated_at']
                                    ?? $provider['created_at']
                                    ?? ''
                                ) ?>

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
                                        value="sync_domains"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$provider['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                    >
                                        获取域名
                                    </button>

                                </form>


                                <a
                                    href="/admin/provider_test.php?id=<?= (int)$provider['id'] ?>"
                                    style="margin-left:8px"
                                >
                                    测试
                                </a>


                                <a
                                    href="/admin/providers.php?edit=<?= (int)$provider['id'] ?>"
                                    style="margin-left:8px"
                                >
                                    编辑
                                </a>


                                <form
                                    method="post"
                                    style="display:inline"
                                >

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$provider['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        onclick="return confirm('确定删除这个 DNS 服务商吗？删除后其 DNS 域名也会被删除')"
                                    >
                                        删除
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


<script>

const providerType =
    document.getElementById(
        'providerType'
    );

const cloudflareFields =
    document.getElementById(
        'cloudflareFields'
    );

const aliyunFields =
    document.getElementById(
        'aliyunFields'
    );


function updateProviderFields() {

    if (!providerType) {
        return;
    }

    if (
        providerType.value === 'aliyun'
    ) {

        cloudflareFields.style.display =
            'none';

        aliyunFields.style.display =
            'block';

    } else {

        cloudflareFields.style.display =
            'block';

        aliyunFields.style.display =
            'none';
    }
}


if (providerType) {

    providerType.addEventListener(
        'change',
        updateProviderFields
    );

    updateProviderFields();
}


const editProviderType =
    document.getElementById(
        'editProviderType'
    );

const editCloudflareFields =
    document.getElementById(
        'editCloudflareFields'
    );

const editAliyunFields =
    document.getElementById(
        'editAliyunFields'
    );


if (
    editProviderType
    && editCloudflareFields
    && editAliyunFields
) {

    function updateEditProviderFields() {

        if (
            editProviderType.value
            === 'aliyun'
        ) {

            editCloudflareFields.style.display =
                'none';

            editAliyunFields.style.display =
                'block';

        } else {

            editCloudflareFields.style.display =
                'block';

            editAliyunFields.style.display =
                'none';
        }
    }


    editProviderType.addEventListener(
        'change',
        updateEditProviderFields
    );

    updateEditProviderFields();
}

</script>


<?php

require __DIR__ .
    '/../includes/admin_footer.php';

?>