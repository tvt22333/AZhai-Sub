<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/dns/DnsManager.php';

require_admin();

$pdo = db();

$pageTitle = '域名管理';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = (string)(
        $_POST['action'] ?? ''
    );

    $id = (int)(
        $_POST['id'] ?? 0
    );

    if ($id <= 0) {

        flash(
            'error',
            '无效的域名 ID'
        );

        redirect(
            '/admin/domains.php'
        );
    }

    $stmt = $pdo->prepare("
        SELECT
            d.id,
            d.user_id,
            d.provider_domain_id,
            d.full_domain,
            d.subdomain,
            d.status,
            pd.domain AS provider_domain
        FROM domains d
        LEFT JOIN dns_provider_domains pd
            ON pd.id = d.provider_domain_id
        WHERE d.id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $id
    ]);

    $domain = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$domain) {

        flash(
            'error',
            '域名不存在'
        );

        redirect(
            '/admin/domains.php'
        );
    }

    $oldStatus =
        (string)$domain['status'];

    $fullDomain =
        trim(
            (string)(
                $domain['full_domain']
                ?? ''
            )
        );

    if ($fullDomain === '') {

        $rootDomain =
            trim(
                (string)(
                    $domain['provider_domain']
                    ?? ''
                )
            );

        if ($rootDomain === '') {
            $rootDomain =
                trim(
                    (string)(
                        $config['base_domain']
                        ?? ''
                    )
                );
        }

        $fullDomain =
            trim(
                (string)(
                    $domain['subdomain']
                    ?? ''
                )
            ) .
            '.' .
            $rootDomain;
    }

    $dns =
        new DnsManager(
            $pdo,
            (string)$config['app_key']
        );

    if ($action === 'status') {

        $newStatus = trim(
            (string)(
                $_POST['status'] ?? ''
            )
        );

        $allowedStatuses = [
            'pending',
            'active',
            'suspended',
            'deleted',
        ];

        if (!in_array(
            $newStatus,
            $allowedStatuses,
            true
        )) {

            flash(
                'error',
                '无效的域名状态'
            );

            redirect(
                '/admin/domains.php'
            );
        }

        if ($newStatus === $oldStatus) {

            flash(
                'success',
                '域名状态没有变化'
            );

            redirect(
                '/admin/domains.php'
            );
        }

        try {

            
            if (
                $newStatus === 'suspended'
            ) {

                $result =
                    $dns->suspendDomain(
                        $id
                    );

                if (
                    empty(
                        $result['success']
                    )
                ) {
                    throw new RuntimeException(
                        (string)(
                            $result['message']
                            ?? 'DNS 暂停失败'
                        )
                    );
                }
            }

            
            if (
                $newStatus === 'active' &&
                $oldStatus === 'suspended'
            ) {

                $result =
                    $dns->resumeDomain(
                        $id
                    );

                if (
                    empty(
                        $result['success']
                    )
                ) {
                    throw new RuntimeException(
                        (string)(
                            $result['message']
                            ?? 'DNS 恢复失败'
                        )
                    );
                }
            }

            
            if (
                $newStatus === 'deleted'
            ) {

                $result =
                    $dns->deleteDomain(
                        $id
                    );

                if (
                    empty(
                        $result['success']
                    )
                ) {
                    throw new RuntimeException(
                        (string)(
                            $result['message']
                            ?? 'DNS 删除失败'
                        )
                    );
                }
            }

            
            $stmt = $pdo->prepare("
                UPDATE domains
                SET
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $newStatus,
                $id
            ]);

            log_action(
                null,
                current_admin_id(),
                'update_domain',
                $fullDomain
            );

            $statusNames = [
                'pending' => '待审核',
                'active' => '正常',
                'suspended' => '已暂停',
                'deleted' => '已删除',
            ];

            $statusText =
                $statusNames[
                    $newStatus
                ]
                ?? $newStatus;

            flash(
                'success',
                '域名 ' .
                $fullDomain .
                ' 已修改为：' .
                $statusText .
                '，DNS 已同步'
            );

        } catch (Throwable $e) {

            error_log(
                '[AZhai Sub] DNS 状态同步失败：' .
                $e->getMessage()
            );

            flash(
                'error',
                'DNS 同步失败，域名状态未修改：' .
                $e->getMessage()
            );
        }

        redirect(
            '/admin/domains.php'
        );
    }

    if ($action === 'delete') {

        try {

            $result =
                $dns->deleteDomain(
                    $id
                );

            if (
                empty(
                    $result['success']
                )
            ) {
                throw new RuntimeException(
                    (string)(
                        $result['message']
                        ?? 'DNS 删除失败'
                    )
                );
            }

            $stmt = $pdo->prepare("
                UPDATE domains
                SET
                    status = 'deleted',
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $id
            ]);

            log_action(
                null,
                current_admin_id(),
                'delete_domain',
                $fullDomain
            );

            flash(
                'success',
                '域名已删除，DNS 已同步删除'
            );

        } catch (Throwable $e) {

            error_log(
                '[AZhai Sub] DNS 删除失败：' .
                $e->getMessage()
            );

            flash(
                'error',
                'DNS 删除失败，域名没有删除：' .
                $e->getMessage()
            );
        }

        redirect(
            '/admin/domains.php'
        );
    }
}

$domains = $pdo->query("
    SELECT
        d.id,
        d.user_id,
        d.provider_domain_id,
        d.subdomain,
        d.full_domain,
        d.status,
        d.created_at,
        d.updated_at,
        u.username,
        pd.domain AS provider_domain
    FROM domains d
    LEFT JOIN users u
        ON u.id = d.user_id
    LEFT JOIN dns_provider_domains pd
        ON pd.id = d.provider_domain_id
    ORDER BY d.id DESC
")->fetchAll(
    PDO::FETCH_ASSOC
);

$statusNames = [
    'pending' => '待审核',
    'active' => '正常',
    'suspended' => '已暂停',
    'deleted' => '已删除',
];

require __DIR__ .
    '/../includes/admin_header.php';

?>

<div class="admin-dashboard">

    <div class="admin-welcome">

        <h1>域名管理</h1>

        <p>
            管理平台上的所有二级域名。
            域名状态变化会同步处理 DNS 记录。
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

        <h2>域名列表</h2>

        <?php if (!$domains): ?>

            <p>
                暂时没有域名。
            </p>

        <?php else: ?>

            <div style="overflow-x:auto">

                <table class="admin-table">

                    <thead>

                    <tr>

                        <th>ID</th>

                        <th>域名</th>

                        <th>用户</th>

                        <th>状态</th>

                        <th>创建时间</th>

                        <th>更新时间</th>

                        <th>操作</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($domains as $domain): ?>

                        <?php

                        $status =
                            (string)$domain['status'];

                        $statusText =
                            $statusNames[$status]
                            ?? $status;

                        $displayDomain =
                            trim(
                                (string)(
                                    $domain['full_domain']
                                    ?? ''
                                )
                            );

                        if ($displayDomain === '') {

                            $rootDomain =
                                trim(
                                    (string)(
                                        $domain[
                                            'provider_domain'
                                        ]
                                        ?? ''
                                    )
                                );

                            if ($rootDomain === '') {
                                $rootDomain =
                                    trim(
                                        (string)(
                                            $config[
                                                'base_domain'
                                            ]
                                            ?? ''
                                        )
                                    );
                            }

                            $displayDomain =
                                trim(
                                    (string)(
                                        $domain[
                                            'subdomain'
                                        ]
                                        ?? ''
                                    )
                                ) .
                                '.' .
                                $rootDomain;
                        }

                        ?>

                        <tr>

                            <td>
                                <?= (int)$domain['id'] ?>
                            </td>

                            <td>

                                <strong>
                                    <?= e(
                                        $displayDomain
                                    ) ?>
                                </strong>

                            </td>

                            <td>

                                <?= e(
                                    $domain['username']
                                    ?? '未知用户'
                                ) ?>

                            </td>

                            <td>

                                <span
                                    class="status status-<?= e($status) ?>"
                                >
                                    <?= e($statusText) ?>
                                </span>

                            </td>

                            <td>

                                <?= e(
                                    $domain['created_at']
                                    ?? ''
                                ) ?>

                            </td>

                            <td>

                                <?= e(
                                    $domain['updated_at']
                                    ?? ''
                                ) ?>

                            </td>

                            <td>

                                <?php if ($status !== 'deleted'): ?>

                                    <form
                                        method="post"
                                        style="display:inline"
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="status"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$domain['id'] ?>"
                                        >

                                        <select
                                            name="status"
                                            onchange="return this.form.submit()"
                                        >

                                            <option
                                                value="pending"
                                                <?= $status === 'pending'
                                                    ? 'selected'
                                                    : ''
                                                ?>
                                            >
                                                待审核
                                            </option>

                                            <option
                                                value="active"
                                                <?= $status === 'active'
                                                    ? 'selected'
                                                    : ''
                                                ?>
                                            >
                                                正常
                                            </option>

                                            <option
                                                value="suspended"
                                                <?= $status === 'suspended'
                                                    ? 'selected'
                                                    : ''
                                                ?>
                                            >
                                                已暂停
                                            </option>

                                            <option
                                                value="deleted"
                                                <?= $status === 'deleted'
                                                    ? 'selected'
                                                    : ''
                                                ?>
                                            >
                                                已删除
                                            </option>

                                        </select>

                                    </form>

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
                                            value="<?= (int)$domain['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            onclick="return confirm('确定删除这个域名吗？删除后 DNS 记录也会同步删除')"
                                        >
                                            删除
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span>
                                        已删除
                                    </span>

                                <?php endif; ?>

                               

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