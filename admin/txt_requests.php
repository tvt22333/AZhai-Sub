<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$pdo = db();

$pageTitle = 'TXT 验证申请';




if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);

    $action = trim(
        (string)($_POST['action'] ?? '')
    );

    $adminReason = trim(
        (string)($_POST['admin_reason'] ?? '')
    );


    if ($id <= 0) {

        flash(
            'error',
            '无效的申请 ID'
        );

        redirect('/admin/txt_requests.php');
    }


    if (!in_array(
        $action,
        [
            'approve',
            'reject',
            'complete',
        ],
        true
    )) {

        flash(
            'error',
            '无效的操作'
        );

        redirect('/admin/txt_requests.php');
    }


    

    $stmt = $pdo->prepare("
        SELECT
            r.*,
            u.username,
            u.email,
            d.full_domain
        FROM txt_verification_requests r

        LEFT JOIN users u
            ON u.id = r.user_id

        LEFT JOIN domains d
            ON d.id = r.domain_id

        WHERE r.id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $id
    ]);

    $request = $stmt->fetch();


    if (!$request) {

        flash(
            'error',
            'TXT 验证申请不存在'
        );

        redirect('/admin/txt_requests.php');
    }


    $adminId = current_admin_id();

    if (!$adminId) {

        flash(
            'error',
            '管理员登录状态已失效'
        );

        redirect('/admin/login.php');
    }


    

    if ($action === 'approve') {

        if ($request['status'] !== 'pending') {

            flash(
                'error',
                '只有待审核申请才能通过'
            );

            redirect('/admin/txt_requests.php');
        }


        $stmt = $pdo->prepare("
            UPDATE txt_verification_requests
            SET
                status = 'approved',
                admin_id = ?,
                admin_reason = ?,
                reviewed_at = NOW()
            WHERE id = ?
              AND status = 'pending'
        ");

        $stmt->execute([
            $adminId,
            $adminReason !== ''
                ? $adminReason
                : null,
            $id
        ]);


        log_action(
            null,
            $adminId,
            'approve_txt_verification',
            $request['full_domain']
        );


        flash(
            'success',
            'TXT 验证申请已通过，请手动在根域 DNS 添加 TXT 记录'
        );

        redirect('/admin/txt_requests.php');
    }


    

    if ($action === 'reject') {

        if ($request['status'] !== 'pending') {

            flash(
                'error',
                '只有待审核申请才能拒绝'
            );

            redirect('/admin/txt_requests.php');
        }


        if ($adminReason === '') {

            flash(
                'error',
                '拒绝申请时必须填写原因'
            );

            redirect('/admin/txt_requests.php');
        }


        if (strlen($adminReason) > 500) {

            flash(
                'error',
                '审核备注不能超过 500 个字符'
            );

            redirect('/admin/txt_requests.php');
        }


        $stmt = $pdo->prepare("
            UPDATE txt_verification_requests
            SET
                status = 'rejected',
                admin_id = ?,
                admin_reason = ?,
                reviewed_at = NOW()
            WHERE id = ?
              AND status = 'pending'
        ");

        $stmt->execute([
            $adminId,
            $adminReason,
            $id
        ]);


        log_action(
            null,
            $adminId,
            'reject_txt_verification',
            $request['full_domain']
        );


        flash(
            'success',
            'TXT 验证申请已拒绝'
        );

        redirect('/admin/txt_requests.php');
    }


    

    if ($action === 'complete') {

        if ($request['status'] !== 'approved') {

            flash(
                'error',
                '只有已通过的申请才能标记为已处理'
            );

            redirect('/admin/txt_requests.php');
        }


        $stmt = $pdo->prepare("
            UPDATE txt_verification_requests
            SET
                status = 'completed',
                admin_id = ?,
                admin_reason = ?,
                completed_at = NOW()
            WHERE id = ?
              AND status = 'approved'
        ");

        $stmt->execute([
            $adminId,
            $adminReason !== ''
                ? $adminReason
                : $request['admin_reason'],
            $id
        ]);


        log_action(
            null,
            $adminId,
            'complete_txt_verification',
            $request['full_domain']
        );


        flash(
            'success',
            'TXT 验证申请已标记为已处理'
        );

        redirect('/admin/txt_requests.php');
    }
}




$statusFilter = trim(
    (string)($_GET['status'] ?? '')
);

$allowedFilters = [
    '',
    'pending',
    'approved',
    'rejected',
    'completed',
];

if (!in_array(
    $statusFilter,
    $allowedFilters,
    true
)) {

    $statusFilter = '';
}




$perPage = 20;

$page = max(
    1,
    (int)($_GET['page'] ?? 1)
);




if ($statusFilter === '') {

    $totalStmt = $pdo->query("
        SELECT COUNT(*)
        FROM txt_verification_requests
    ");

} else {

    $totalStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM txt_verification_requests
        WHERE status = ?
    ");

    $totalStmt->execute([
        $statusFilter
    ]);
}

$total = (int)$totalStmt->fetchColumn();

$totalPages = max(
    1,
    (int)ceil($total / $perPage)
);


if ($page > $totalPages) {
    $page = $totalPages;
}


$offset = ($page - 1) * $perPage;




$sql = "
    SELECT
        r.*,
        u.username,
        u.email,
        d.full_domain
    FROM txt_verification_requests r

    LEFT JOIN users u
        ON u.id = r.user_id

    LEFT JOIN domains d
        ON d.id = r.domain_id
";

$params = [];


if ($statusFilter !== '') {

    $sql .= "
        WHERE r.status = ?
    ";

    $params[] = $statusFilter;
}


$sql .= "
    ORDER BY r.id DESC
    LIMIT :limit
    OFFSET :offset
";


$stmt = $pdo->prepare($sql);


foreach ($params as $index => $value) {

    $stmt->bindValue(
        $index + 1,
        $value,
        PDO::PARAM_STR
    );
}

$stmt->bindValue(
    ':limit',
    $perPage,
    PDO::PARAM_INT
);

$stmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);

$stmt->execute();

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


require __DIR__ . '/../includes/admin_header.php';

?>

<div class="admin-dashboard">

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


    <div class="admin-welcome">

        <h1>
            TXT 验证申请
        </h1>

        <p>
            审核用户提交的根域 TXT 验证申请。
        </p>

    </div>


    <div class="admin-card">

        <div
            style="
                display:flex;
                gap:8px;
                flex-wrap:wrap;
                margin-bottom:20px;
            "
        >

            <a
                href="/admin/txt_requests.php"
                class="button <?= $statusFilter === ''
                    ? ''
                    : 'secondary'
                ?>"
            >
                全部
            </a>

            <a
                href="?status=pending"
                class="button <?= $statusFilter === 'pending'
                    ? ''
                    : 'secondary'
                ?>"
            >
                待审核
            </a>

            <a
                href="?status=approved"
                class="button <?= $statusFilter === 'approved'
                    ? ''
                    : 'secondary'
                ?>"
            >
                已通过
            </a>

            <a
                href="?status=rejected"
                class="button <?= $statusFilter === 'rejected'
                    ? ''
                    : 'secondary'
                ?>"
            >
                已拒绝
            </a>

            <a
                href="?status=completed"
                class="button <?= $statusFilter === 'completed'
                    ? ''
                    : 'secondary'
                ?>"
            >
                已处理
            </a>

        </div>


        <div class="table-wrap">

            <table>

                <thead>

                    <tr>

                        <th>
                            用户
                        </th>

                        <th>
                            域名
                        </th>

                        <th>
                            TXT 记录
                        </th>

                        <th>
                            申请原因
                        </th>

                        <th>
                            状态
                        </th>

                        <th>
                            时间
                        </th>

                        <th>
                            操作
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (!$requests): ?>

                    <tr>

                        <td
                            colspan="7"
                            style="text-align:center"
                        >
                            暂无 TXT 验证申请
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($requests as $request): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= e(
                                        $request['username']
                                        ?: '未知用户'
                                    ) ?>
                                </strong>

                                <?php if (
                                    !empty($request['email'])
                                ): ?>

                                    <small
                                        style="
                                            display:block;
                                            opacity:.6;
                                            margin-top:3px;
                                        "
                                    >
                                        <?= e($request['email']) ?>
                                    </small>

                                <?php endif; ?>

                            </td>


                            <td>

                                <strong>
                                    <?= e(
                                        $request['full_domain']
                                        ?: $request['subdomain']
                                    ) ?>
                                </strong>

                            </td>


                            <td>

                                <strong>
                                    <?= e(
                                        $request['record_name']
                                    ) ?>
                                </strong>

                                <small
                                    style="
                                        display:block;
                                        max-width:260px;
                                        word-break:break-all;
                                        opacity:.65;
                                        margin-top:5px;
                                    "
                                >
                                    <?= e(
                                        $request['record_value']
                                    ) ?>
                                </small>

                            </td>


                            <td>

                                <span
                                    style="
                                        display:block;
                                        max-width:260px;
                                        word-break:break-word;
                                    "
                                >
                                    <?= e(
                                        $request['reason']
                                    ) ?>
                                </span>

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
                                <?= e(
                                    $request['created_at']
                                ) ?>
                            </td>


                            <td>

                                <?php if (
                                    $request['status']
                                    === 'pending'
                                ): ?>

                                    <div
                                        style="
                                            display:flex;
                                            flex-direction:column;
                                            gap:8px;
                                            min-width:180px;
                                        "
                                    >

                                        <form
                                            method="post"
                                        >

                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$request['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="approve"
                                            >

                                            <input
                                                type="text"
                                                name="admin_reason"
                                                maxlength="500"
                                                placeholder="审核备注（可选）"
                                                style="
                                                    width:100%;
                                                    margin-bottom:6px;
                                                "
                                            >

                                            <button
                                                type="submit"
                                                class="button"
                                            >
                                                通过申请
                                            </button>

                                        </form>


                                        <form
                                            method="post"
                                        >

                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$request['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="reject"
                                            >

                                            <input
                                                type="text"
                                                name="admin_reason"
                                                maxlength="500"
                                                placeholder="拒绝原因（必填）"
                                                style="
                                                    width:100%;
                                                    margin-bottom:6px;
                                                "
                                                required
                                            >

                                            <button
                                                type="submit"
                                                class="button secondary"
                                            >
                                                拒绝申请
                                            </button>

                                        </form>

                                    </div>


                                <?php elseif (
                                    $request['status']
                                    === 'approved'
                                ): ?>

                                    <form
                                        method="post"
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$request['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="complete"
                                        >

                                        <input
                                            type="text"
                                            name="admin_reason"
                                            maxlength="500"
                                            placeholder="已添加后的备注（可选）"
                                            style="
                                                width:100%;
                                                margin-bottom:6px;
                                            "
                                        >

                                        <button
                                            type="submit"
                                            class="button"
                                        >
                                            标记为已处理
                                        </button>

                                    </form>


                                <?php else: ?>

                                    <?php if (
                                        !empty(
                                            $request['admin_reason']
                                        )
                                    ): ?>

                                        <small
                                            style="
                                                display:block;
                                                max-width:220px;
                                                word-break:break-word;
                                                opacity:.65;
                                            "
                                        >
                                            <?= e(
                                                $request['admin_reason']
                                            ) ?>
                                        </small>

                                    <?php else: ?>

                                        <span>—</span>

                                    <?php endif; ?>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <?php if ($totalPages > 1): ?>

            <div
                style="
                    display:flex;
                    justify-content:center;
                    align-items:center;
                    gap:6px;
                    flex-wrap:wrap;
                    margin-top:24px;
                "
            >

                <?php if ($page > 1): ?>

                    <a
                        href="?status=<?= urlencode($statusFilter) ?>&page=<?= $page - 1 ?>"
                        class="button secondary"
                    >
                        上一页
                    </a>

                <?php endif; ?>


                <?php

                $startPage = max(
                    1,
                    $page - 2
                );

                $endPage = min(
                    $totalPages,
                    $page + 2
                );

                ?>


                <?php if ($startPage > 1): ?>

                    <a
                        href="?status=<?= urlencode($statusFilter) ?>&page=1"
                        class="button secondary"
                    >
                        1
                    </a>

                    <?php if ($startPage > 2): ?>

                        <span
                            style="
                                padding:0 4px;
                                opacity:.5;
                            "
                        >
                            …
                        </span>

                    <?php endif; ?>

                <?php endif; ?>


                <?php for (
                    $i = $startPage;
                    $i <= $endPage;
                    $i++
                ): ?>

                    <?php if ($i === $page): ?>

                        <span
                            class="button"
                        >
                            <?= $i ?>
                        </span>

                    <?php else: ?>

                        <a
                            href="?status=<?= urlencode($statusFilter) ?>&page=<?= $i ?>"
                            class="button secondary"
                        >
                            <?= $i ?>
                        </a>

                    <?php endif; ?>

                <?php endfor; ?>


                <?php if ($endPage < $totalPages): ?>

                    <?php if ($endPage < $totalPages - 1): ?>

                        <span
                            style="
                                padding:0 4px;
                                opacity:.5;
                            "
                        >
                            …
                        </span>

                    <?php endif; ?>


                    <a
                        href="?status=<?= urlencode($statusFilter) ?>&page=<?= $totalPages ?>"
                        class="button secondary"
                    >
                        <?= $totalPages ?>
                    </a>

                <?php endif; ?>


                <?php if ($page < $totalPages): ?>

                    <a
                        href="?status=<?= urlencode($statusFilter) ?>&page=<?= $page + 1 ?>"
                        class="button secondary"
                    >
                        下一页
                    </a>

                <?php endif; ?>

            </div>


            <div
                style="
                    text-align:center;
                    margin-top:12px;
                    font-size:13px;
                    opacity:.55;
                "
            >
                第 <?= $page ?> / <?= $totalPages ?> 页
                ·
                共 <?= $total ?> 条申请
            </div>

        <?php endif; ?>

    </div>

</div>

<?php

require __DIR__ . '/../includes/admin_footer.php';

?>