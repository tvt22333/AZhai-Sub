<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/email.php';

require_admin();

$pdo = db();

$title = '域名审核';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $id = isset($_POST['id'])
        ? (int)$_POST['id']
        : 0;

    $act = $_POST['act'] ?? '';

    $reviewReason = trim(
        (string)($_POST['review_reason'] ?? '')
    );

    if (
        $id <= 0 ||
        !in_array($act, ['approve', 'reject'], true)
    ) {
        flash(
            'error',
            '无效的审核请求'
        );

        redirect('/admin/applications.php');
    }

    if (mb_strlen($reviewReason) > 500) {
        flash(
            'error',
            '审核原因不能超过 500 个字符'
        );

        redirect('/admin/applications.php');
    }

    
    $stmt = $pdo->prepare("
        SELECT
            a.*,
            u.username,
            u.email,
            u.status AS user_status,
            dpd.domain AS provider_domain
        FROM domain_applications a
        LEFT JOIN users u
            ON u.id = a.user_id
        LEFT JOIN dns_provider_domains dpd
            ON dpd.id = a.provider_domain_id
        WHERE a.id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $id
    ]);

    $application = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$application) {
        flash(
            'error',
            '审核申请不存在'
        );

        redirect('/admin/applications.php');
    }

    if ($application['status'] !== 'pending') {
        flash(
            'error',
            '该申请已经审核过了'
        );

        redirect('/admin/applications.php');
    }

    $adminId = current_admin_id();

    if (!$adminId) {
        flash(
            'error',
            '管理员登录状态已失效'
        );

        redirect('/admin/login.php');
    }

    
    $subdomain = trim(
        (string)(
            $application['subdomain']
            ?? ''
        )
    );

    $providerDomainId = (int)(
        $application['provider_domain_id']
        ?? 0
    );

    $rootDomain = trim(
        (string)(
            $application['provider_domain']
            ?? ''
        )
    );

    
    if (
        $rootDomain === '' &&
        $providerDomainId > 0
    ) {
        $stmt = $pdo->prepare("
            SELECT domain
            FROM dns_provider_domains
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $providerDomainId
        ]);

        $rootDomain = trim(
            (string)$stmt->fetchColumn()
        );
    }

    
    if ($rootDomain === '') {
        $rootDomain = trim(
            (string)(
                $config['base_domain']
                ?? ''
            )
        );
    }

    if (
        $subdomain === '' ||
        $rootDomain === ''
    ) {
        flash(
            'error',
            '申请域名信息无效'
        );

        redirect('/admin/applications.php');
    }

    $rootDomain = strtolower(
        trim(
            rtrim(
                $rootDomain,
                '.'
            )
        )
    );

    $subdomain = strtolower(
        trim(
            $subdomain,
            '.'
        )
    );

    $fullDomain =
        $subdomain .
        '.' .
        $rootDomain;

    
    if ($act === 'reject') {

        $stmt = $pdo->prepare("
            UPDATE domain_applications
            SET
                status = 'rejected',
                review_reason = ?,
                reviewed_by = ?,
                reviewed_at = NOW()
            WHERE id = ?
              AND status = 'pending'
        ");

        $stmt->execute([
            $reviewReason !== ''
                ? $reviewReason
                : null,
            $adminId,
            $id
        ]);

        log_action(
            null,
            (int)$adminId,
            'reject_domain',
            $fullDomain
        );

        
        if (
            !empty($application['email']) &&
            filter_var(
                $application['email'],
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $mailSent = send_user_notification(
                (int)$application['user_id'],
                'domain_rejected',
                [
                    'domain' => $fullDomain,
                    'full_domain' => $fullDomain,
                    'subdomain' => $subdomain,
                    'review_reason' => $reviewReason !== ''
                        ? $reviewReason
                        : '未填写拒绝原因',
                ]
            );

            if (!$mailSent) {
                error_log(
                    '[AZhai Sub] domain rejected email failed: ' .
                    $fullDomain
                );
            }
        }

        flash(
            'success',
            '域名申请已拒绝'
        );

        redirect('/admin/applications.php');
    }

    
    try {

        $pdo->beginTransaction();

        
        $stmt = $pdo->prepare("
            SELECT id
            FROM domains
            WHERE full_domain = ?
              AND status <> 'deleted'
            LIMIT 1
        ");

        $stmt->execute([
            $fullDomain
        ]);

        if ($stmt->fetch()) {
            throw new RuntimeException(
                '该域名已经存在'
            );
        }

        
        $domainsColumns = [];

        $columnStmt = $pdo->query("
            SHOW COLUMNS FROM domains
        ");

        $domainColumns = $columnStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        foreach ($domainColumns as $column) {
            $domainsColumns[] = $column['Field'];
        }

        if (
            in_array(
                'provider_domain_id',
                $domainsColumns,
                true
            )
        ) {

            $stmt = $pdo->prepare("
                INSERT INTO domains (
                    user_id,
                    provider_domain_id,
                    subdomain,
                    full_domain,
                    status,
                    created_at,
                    updated_at
                ) VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    'active',
                    NOW(),
                    NOW()
                )
            ");

            $stmt->execute([
                $application['user_id'],
                $providerDomainId > 0
                    ? $providerDomainId
                    : null,
                $subdomain,
                $fullDomain
            ]);

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO domains (
                    user_id,
                    subdomain,
                    full_domain,
                    status,
                    created_at,
                    updated_at
                ) VALUES (
                    ?,
                    ?,
                    ?,
                    'active',
                    NOW(),
                    NOW()
                )
            ");

            $stmt->execute([
                $application['user_id'],
                $subdomain,
                $fullDomain
            ]);
        }

        
        $stmt = $pdo->prepare("
            UPDATE domain_applications
            SET
                status = 'approved',
                review_reason = ?,
                reviewed_by = ?,
                reviewed_at = NOW()
            WHERE id = ?
              AND status = 'pending'
        ");

        $stmt->execute([
            $reviewReason !== ''
                ? $reviewReason
                : null,
            $adminId,
            $id
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                '申请状态更新失败'
            );
        }

        $pdo->commit();

        log_action(
            null,
            (int)$adminId,
            'approve_domain',
            $fullDomain
        );

        
        if (
            !empty($application['email']) &&
            filter_var(
                $application['email'],
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $mailSent = send_user_notification(
                (int)$application['user_id'],
                'domain_approved',
                [
                    'domain' => $fullDomain,
                    'full_domain' => $fullDomain,
                    'subdomain' => $subdomain,
                    'review_reason' => $reviewReason !== ''
                        ? $reviewReason
                        : '审核通过',
                ]
            );

            if (!$mailSent) {
                error_log(
                    '[AZhai Sub] domain approved email failed: ' .
                    $fullDomain
                );
            }
        }

        flash(
            'success',
            '域名申请已通过，域名已创建'
        );

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            '[AZhai Sub] approve domain: ' .
            $e->getMessage()
        );

        flash(
            'error',
            '审核失败：' .
            $e->getMessage()
        );
    }

    redirect('/admin/applications.php');
}


$stmt = $pdo->query("
    SELECT
        a.*,
        u.username,
        dpd.domain AS provider_domain
    FROM domain_applications a
    LEFT JOIN users u
        ON u.id = a.user_id
    LEFT JOIN dns_provider_domains dpd
        ON dpd.id = a.provider_domain_id
    ORDER BY a.id DESC
");

$applications = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);

require __DIR__ .
    '/../includes/admin_header.php';

$success = flash('success');
$error = flash('error');

?>

<div class="card">

    <h1>域名审核</h1>

    <?php if ($success): ?>

        <div class="alert success">
            <?= e($success) ?>
        </div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="alert danger">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <?php if (empty($applications)): ?>

        <div class="empty">
            暂无域名申请
        </div>

    <?php else: ?>

        <div class="table-wrap">

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>域名</th>
                        <th>用户</th>
                        <th>说明</th>
                        <th>审核原因</th>
                        <th>状态</th>
                        <th>申请时间</th>
                        <th>操作</th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach ($applications as $application): ?>

                    <?php

                    $subdomain = trim(
                        (string)(
                            $application['subdomain']
                            ?? ''
                        )
                    );

                    $providerDomainId = (int)(
                        $application['provider_domain_id']
                        ?? 0
                    );

                    $rootDomain = trim(
                        (string)(
                            $application['provider_domain']
                            ?? ''
                        )
                    );

                    
                    if (
                        $rootDomain === '' &&
                        $providerDomainId > 0
                    ) {

                        $domainStmt = $pdo->prepare("
                            SELECT domain
                            FROM dns_provider_domains
                            WHERE id = ?
                            LIMIT 1
                        ");

                        $domainStmt->execute([
                            $providerDomainId
                        ]);

                        $rootDomain = trim(
                            (string)$domainStmt->fetchColumn()
                        );
                    }

                    
                    if ($rootDomain === '') {
                        $rootDomain = trim(
                            (string)(
                                $config['base_domain']
                                ?? ''
                            )
                        );
                    }

                    $rootDomain = strtolower(
                        trim(
                            rtrim(
                                $rootDomain,
                                '.'
                            )
                        )
                    );

                    $subdomain = strtolower(
                        trim(
                            $subdomain,
                            '.'
                        )
                    );

                    $fullDomain =
                        $subdomain .
                        '.' .
                        $rootDomain;

                    $statusNames = [
                        'pending' => '待审核',
                        'approved' => '已通过',
                        'rejected' => '已拒绝',
                    ];

                    $statusName =
                        $statusNames[
                            $application['status']
                        ]
                        ??
                        $application['status'];

                    ?>

                    <tr>

                        <td>
                            #<?= (int)$application['id'] ?>
                        </td>

                        <td>
                            <strong>
                                <?= e($fullDomain) ?>
                            </strong>
                        </td>

                        <td>
                            <?= e(
                                $application['username']
                                ?? '未知用户'
                            ) ?>
                        </td>

                        <td>

                            <?php if (!empty($application['reason'])): ?>

                                <?= e(
                                    $application['reason']
                                ) ?>

                            <?php else: ?>

                                <span class="muted">
                                    未填写
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php if (!empty($application['review_reason'])): ?>

                                <?= e(
                                    $application['review_reason']
                                ) ?>

                            <?php elseif ($application['status'] === 'pending'): ?>

                                <span class="muted">
                                    —
                                </span>

                            <?php else: ?>

                                <span class="muted">
                                    未填写原因
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= e($statusName) ?>
                        </td>

                        <td>
                            <?= e(
                                $application['created_at']
                                ?? ''
                            ) ?>
                        </td>

                        <td>

                            <?php if ($application['status'] === 'pending'): ?>

                                <form
                                    class="inline review-form"
                                    method="post"
                                    onsubmit="return reviewApplication(this, 'approve');"
                                >

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$application['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="act"
                                        value="approve"
                                    >

                                    <input
                                        type="hidden"
                                        name="review_reason"
                                        value=""
                                    >

                                    <button
                                        type="submit"
                                    >
                                        通过
                                    </button>

                                </form>

                                <form
                                    class="inline review-form"
                                    method="post"
                                    onsubmit="return reviewApplication(this, 'reject');"
                                >

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$application['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="act"
                                        value="reject"
                                    >

                                    <input
                                        type="hidden"
                                        name="review_reason"
                                        value=""
                                    >

                                    <button
                                        type="submit"
                                        class="danger"
                                    >
                                        拒绝
                                    </button>

                                </form>

                            <?php else: ?>

                                <span class="muted">
                                    <?= e($statusName) ?>
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

<script>

function reviewApplication(form, action) {

    let message = '';

    if (action === 'approve') {

        message =
            '请输入通过该域名申请的审核原因\n\n' +
            '可以留空，直接点击确定即可';

    } else {

        message =
            '请输入拒绝该域名申请的原因\n\n' +
            '可以留空，直接点击确定即可';

    }

    const reason = window.prompt(
        message,
        ''
    );

    if (reason === null) {
        return false;
    }

    if (reason.length > 500) {

        alert(
            '审核原因不能超过 500 个字符'
        );

        return false;
    }

    const input =
        form.querySelector(
            'input[name="review_reason"]'
        );

    if (input) {
        input.value = reason.trim();
    }

    if (action === 'approve') {

        return confirm(
            '确定要通过这个域名申请吗？'
        );

    }

    return confirm(
        '确定要拒绝这个域名申请吗？'
    );
}

</script>

<?php require __DIR__ .
    '/../includes/admin_footer.php'; ?>