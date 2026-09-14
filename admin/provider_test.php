<?php

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/dns/DnsProviderInterface.php';
require_once __DIR__ . '/../includes/dns/CloudflareProvider.php';
require_once __DIR__ . '/../includes/dns/AliyunProvider.php';
require_once __DIR__ . '/../includes/dns/DnsManager.php';

require_admin();

$pdo = db();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    exit('无效的 DNS 服务商 ID');
}

$result = [
    'success' => false,
    'message' => '未知错误',
];

$provider = null;

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

    $stmt->execute([$id]);

    $provider = $stmt->fetch();

    if (!$provider) {

        throw new RuntimeException(
            'DNS 服务商不存在'
        );
    }


    
    if ((int)$provider['status'] !== 1) {

        throw new RuntimeException(
            '该 DNS 服务商当前已禁用'
        );
    }


    
    $providerConfig = json_decode(
        $provider['config'],
        true
    );

    if (!is_array($providerConfig)) {

        throw new RuntimeException(
            'DNS 服务商配置格式错误'
        );
    }


    
    switch ($provider['type']) {

        case 'cloudflare':

            $dnsProvider = new CloudflareProvider(
                $providerConfig
            );

            break;


        case 'aliyun':

            $dnsProvider = new AliyunProvider(
                $providerConfig
            );

            break;


        default:

            throw new RuntimeException(
                '不支持的 DNS 服务商类型：' .
                $provider['type']
            );
    }


    
    $result = $dnsProvider->testConnection();

} catch (Throwable $e) {

    $result = [
        'success' => false,
        'message' => $e->getMessage(),
    ];
}




if (!empty($provider)) {

    log_action(
        null,
        current_admin_id(),
        'test_provider',
        $provider['name']
    );
}


$pageTitle = 'DNS 测试';

require __DIR__ . '/../includes/admin_header.php';

?>

<div class="admin-dashboard">

    <div class="admin-welcome">

        <h1>DNS 连接测试</h1>

        <?php if (!empty($provider)): ?>

            <p>
                正在测试：
                <strong>
                    <?= e($provider['name']) ?>
                </strong>
            </p>

        <?php endif; ?>

    </div>


    <?php if (!empty($result['success'])): ?>

        <div class="alert success">

            <?= e(
                $result['message']
                ?? 'DNS 连接成功'
            ) ?>

        </div>

    <?php else: ?>

        <div class="alert error">

            <?= e(
                $result['message']
                ?? 'DNS 连接失败'
            ) ?>

        </div>

    <?php endif; ?>


    <div class="admin-card">

        <h2>测试结果</h2>

        <pre style="
            white-space:pre-wrap;
            word-break:break-word;
            overflow:auto;
        "><?= e(
            json_encode(
                $result,
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            )
        ) ?></pre>

    </div>


    <p style="margin-top:20px">

        <a href="/admin/providers.php">
            ← 返回 DNS 服务商
        </a>

    </p>

</div>

<?php

require __DIR__ . '/../includes/admin_footer.php';

?>