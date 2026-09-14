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

$errors = [];

$domainId = (int)($_POST['domain_id'] ?? 0);
$recordName = trim((string)($_POST['record_name'] ?? ''));
$recordValue = trim((string)($_POST['record_value'] ?? ''));
$reason = trim((string)($_POST['reason'] ?? ''));




if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    

    if ($domainId <= 0) {

        $errors[] = '请选择需要验证的二级域名';

    } else {

        $stmt = $pdo->prepare("
            SELECT
                id,
                subdomain,
                full_domain,
                status
            FROM domains
            WHERE id = ?
              AND user_id = ?
              AND status = 'active'
            LIMIT 1
        ");

        $stmt->execute([
            $domainId,
            $userId
        ]);

        $domain = $stmt->fetch();

        if (!$domain) {
            $errors[] = '选择的域名不存在，或者你没有管理权限';
        }
    }


    

    if ($recordName === '') {

        $errors[] = '请输入 TXT 记录名称';

    } elseif (strlen($recordName) > 255) {

        $errors[] = 'TXT 记录名称不能超过 255 个字符';

    } elseif ($recordName === '@') {

        // 根域名允许使用 @

    } elseif (!preg_match(
        '/^(?!-)(?:[a-zA-Z0-9_](?:[a-zA-Z0-9_-]{0,61}[a-zA-Z0-9_])?\.)*[a-zA-Z0-9_](?:[a-zA-Z0-9_-]{0,61}[a-zA-Z0-9_])?$/',
        $recordName
    )) {

        $errors[] = 'TXT 记录名称格式不正确';
    }


    

    if ($recordValue === '') {

        $errors[] = '请输入 TXT 记录值';

    } elseif (strlen($recordValue) > 4096) {

        $errors[] = 'TXT 记录值不能超过 4096 个字符';
    }


    

    if ($reason === '') {

        $errors[] = '请填写申请原因';

    } elseif (strlen($reason) > 500) {

        $errors[] = '申请原因不能超过 500 个字符';
    }


    

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM txt_verification_requests
            WHERE user_id = ?
              AND domain_id = ?
              AND record_name = ?
              AND record_value = ?
              AND status = 'pending'
            LIMIT 1
        ");

        $stmt->execute([
            $userId,
            $domainId,
            $recordName,
            $recordValue
        ]);

        if ($stmt->fetch()) {

            $errors[] =
                '相同的 TXT 验证申请已经提交，请等待管理员审核';
        }
    }


    

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            INSERT INTO txt_verification_requests (
                user_id,
                domain_id,
                subdomain,
                record_name,
                record_value,
                reason,
                status,
                created_at
            ) VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                'pending',
                NOW()
            )
        ");

        $stmt->execute([
            $userId,
            $domainId,
            $domain['full_domain'],
            $recordName,
            $recordValue,
            $reason
        ]);


        

        log_action(
            $userId,
            null,
            'apply_txt_verification',
            $domain['full_domain']
        );


        flash(
            'success',
            'TXT 根域验证申请提交成功，请等待管理员审核'
        );

        redirect('/dashboard/txt_requests.php');
    }
}




$stmt = $pdo->prepare("
    SELECT
        id,
        subdomain,
        full_domain
    FROM domains
    WHERE user_id = ?
      AND status = 'active'
    ORDER BY id DESC
");

$stmt->execute([
    $userId
]);

$domains = $stmt->fetchAll();




$stmt = $pdo->prepare("
    SELECT
        r.*,
        d.full_domain
    FROM txt_verification_requests r
    LEFT JOIN domains d
        ON d.id = r.domain_id
    WHERE r.user_id = ?
    ORDER BY r.id DESC
");

$stmt->execute([
    $userId
]);

$requests = $stmt->fetchAll();


$statusLabels = [
    'pending' => '待审核',
    'approved' => '已通过',
    'rejected' => '已拒绝',
    'completed' => '已处理',
];

$statusClasses = [
    'pending' => 'warning',
    'approved' => 'success',
    'rejected' => 'danger',
    'completed' => 'success',
];


$pageTitle = 'TXT 根域验证';

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


    <?php if (!empty($errors)): ?>

        <div class="alert error">

            <?php foreach ($errors as $error): ?>

                <div>
                    <?= e($error) ?>
                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <section class="card">

        <div class="card-header">

            <div>

                <h1>
                    TXT 根域验证
                </h1>

                <p class="muted">
                    如果第三方服务要求在
                    <?= e($config['base_domain']) ?>
                    根域名添加 TXT 记录，可以在这里提交申请。
                </p>

            </div>

        </div>


        <div
            class="alert"
            style="
                margin-bottom:20px;
            "
        >
            <strong>说明：</strong>

            你无法直接修改
            <?= e($config['base_domain']) ?>
            根域 DNS。

            提交申请后，由管理员审核并手动添加 TXT 记录。
        </div>


        <?php if (empty($domains)): ?>

            <div class="empty">

                <div class="empty-icon">
                    🌐
                </div>

                <h3>
                    暂无可用域名
                </h3>

                <p>
                    你需要先拥有一个正常状态的二级域名。
                </p>

            </div>

        <?php else: ?>

            <form method="post">

                <?= csrf_field() ?>


                <div class="form-group">

                    <label>
                        关联域名
                    </label>

                    <select
                        name="domain_id"
                        required
                    >

                        <option value="">
                            请选择域名
                        </option>

                        <?php foreach ($domains as $domain): ?>

                            <option
                                value="<?= (int)$domain['id'] ?>"
                                <?= $domainId === (int)$domain['id']
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= e($domain['full_domain']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        TXT 记录名称
                    </label>

                    <input
                        type="text"
                        name="record_name"
                        value="<?= e($recordName) ?>"
                        placeholder="例如：_acme-challenge.example"
                        maxlength="255"
                        required
                    >

                    <small class="muted">
                        只填写主机记录，不需要填写
                        <?= e($config['base_domain']) ?>
                    </small>

                </div>


                <div class="form-group">

                    <label>
                        TXT 记录值
                    </label>

                    <textarea
                        name="record_value"
                        rows="4"
                        maxlength="4096"
                        placeholder="请输入第三方平台提供的 TXT 验证值"
                        required
                    ><?= e($recordValue) ?></textarea>

                </div>


                <div class="form-group">

                    <label>
                        申请原因
                    </label>

                    <textarea
                        name="reason"
                        rows="4"
                        maxlength="500"
                        placeholder="请说明原因，否则不予通过"
                        required
                    ><?= e($reason) ?></textarea>

                </div>


                <button
                    type="submit"
                    class="button"
                >
                    提交申请
                </button>

            </form>

        <?php endif; ?>

    </section>


    <section class="card">

        <div class="card-header">

            <div>

                <h2>
                    我的申请
                </h2>

                <p class="muted">
                    查看 TXT 根域验证申请状态。
                </p>

            </div>

            <span class="count">
                <?= count($requests) ?>
            </span>

        </div>


        <?php if (empty($requests)): ?>

            <div class="empty small">
                暂无 TXT 验证申请
            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th>
                                域名
                            </th>

                            <th>
                                TXT
                            </th>

                            <th>
                                状态
                            </th>

                            <th>
                                申请时间
                            </th>

                            <th>
                                管理员备注
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($requests as $request): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= e(
                                        $request['full_domain']
                                        ?: $request['subdomain']
                                    ) ?>
                                </strong>

                            </td>


                            <td>

                                <div>
                                    <?= e($request['record_name']) ?>
                                </div>

                                <small
                                    style="
                                        display:block;
                                        max-width:300px;
                                        word-break:break-all;
                                        opacity:.65;
                                        margin-top:5px;
                                    "
                                >
                                    <?= e($request['record_value']) ?>
                                </small>

                            </td>


                            <td>

                                <span class="status <?= e(
                                    $statusClasses[
                                        $request['status']
                                    ] ?? 'muted'
                                ) ?>">

                                    <?= e(
                                        $statusLabels[
                                            $request['status']
                                        ] ?? $request['status']
                                    ) ?>

                                </span>

                            </td>


                            <td>
                                <?= e($request['created_at']) ?>
                            </td>


                            <td>

                                <?php if (
                                    !empty($request['admin_reason'])
                                ): ?>

                                    <?= e(
                                        $request['admin_reason']
                                    ) ?>

                                <?php else: ?>

                                    <span>—</span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</div>

<?php

require __DIR__ . '/../includes/footer.php';

?>