<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = db();



$perPage = 20;

$page = isset($_GET['page'])
    ? max(1, (int) $_GET['page'])
    : 1;




$totalLogs = (int) $pdo->query("
    SELECT COUNT(*)
    FROM operation_logs
")->fetchColumn();


$totalPages = max(
    1,
    (int) ceil($totalLogs / $perPage)
);




if ($page > $totalPages) {
    $page = $totalPages;
}


$offset = ($page - 1) * $perPage;




$stmt = $pdo->prepare("
    SELECT
        l.id,
        l.user_id,
        l.admin_id,
        l.action,
        l.target,
        l.ip,
        l.user_agent,
        l.created_at,

        u.username AS user_username,

        a.username AS admin_username

    FROM operation_logs l

    LEFT JOIN users u
        ON u.id = l.user_id

    LEFT JOIN admin_users a
        ON a.id = l.admin_id

    ORDER BY l.id DESC

    LIMIT :limit OFFSET :offset
");

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

$logs = $stmt->fetchAll();


$pageTitle = '操作日志';

require __DIR__ . '/../includes/admin_header.php';

?>

<div class="admin-dashboard">

    <div class="admin-welcome">

        <h1>操作日志</h1>

        <p>
            查看系统中的用户与管理员操作记录。
        </p>

    </div>


    <div class="admin-card">

        <h2>
            操作记录
            <small>
                (<?= $totalLogs ?>)
            </small>
        </h2>


        <div class="table-wrap">

            <table>

                <thead>

                    <tr>

                        <th>时间</th>

                        <th>动作</th>

                        <th>目标</th>

                        <th>操作人</th>

                        <th>IP</th>

                    </tr>

                </thead>


                <tbody>

                <?php if (!$logs): ?>

                    <tr>

                        <td
                            colspan="5"
                            style="text-align:center"
                        >
                            暂无操作日志
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($logs as $log): ?>

                        <?php

                        

                        if (
                            !empty($log['admin_username'])
                        ) {

                            $operator =
                                $log['admin_username'];

                            $operatorType = '管理员';

                        } elseif (
                            !empty($log['user_username'])
                        ) {

                            $operator =
                                $log['user_username'];

                            $operatorType = '用户';

                        } else {

                            $operator = '系统';

                            $operatorType = '系统';

                        }

                        ?>

                        <tr>

                            <!-- 时间 -->

                            <td>
                                <?= e($log['created_at']) ?>
                            </td>


                            <!-- 动作 -->

                            <td>

                                <strong>
                                    <?= e($log['action']) ?>
                                </strong>

                            </td>


                            <!-- 目标 -->

                            <td>

                                <?php if (
                                    $log['target'] !== null
                                    && $log['target'] !== ''
                                ): ?>

                                    <?= e($log['target']) ?>

                                <?php else: ?>

                                    <span>—</span>

                                <?php endif; ?>

                            </td>


                            <!-- 操作人 -->

                            <td>

                                <strong>
                                    <?= e($operator) ?>
                                </strong>

                                <small
                                    style="
                                        display:block;
                                        opacity:.6;
                                        margin-top:3px;
                                    "
                                >
                                    <?= e($operatorType) ?>
                                </small>

                            </td>


                            <!-- IP -->

                            <td>

                                <?php if (
                                    $log['ip'] !== null
                                    && $log['ip'] !== ''
                                ): ?>

                                    <?= e($log['ip']) ?>

                                <?php else: ?>

                                    <span>—</span>

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
                        href="?page=<?= $page - 1 ?>"
                        style="
                            display:inline-flex;
                            align-items:center;
                            justify-content:center;
                            min-width:38px;
                            height:38px;
                            padding:0 12px;
                            border-radius:10px;
                            text-decoration:none;
                            color:inherit;
                            background:var(--pink-soft);
                        "
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
                        href="?page=1"
                        style="
                            display:inline-flex;
                            align-items:center;
                            justify-content:center;
                            min-width:38px;
                            height:38px;
                            padding:0 10px;
                            border-radius:10px;
                            text-decoration:none;
                            color:inherit;
                        "
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
                            style="
                                display:inline-flex;
                                align-items:center;
                                justify-content:center;
                                min-width:38px;
                                height:38px;
                                padding:0 10px;
                                border-radius:10px;
                                color:#fff;
                                background:var(--pink-dark);
                                font-weight:600;
                            "
                        >
                            <?= $i ?>
                        </span>

                    <?php else: ?>

                        <a
                            href="?page=<?= $i ?>"
                            style="
                                display:inline-flex;
                                align-items:center;
                                justify-content:center;
                                min-width:38px;
                                height:38px;
                                padding:0 10px;
                                border-radius:10px;
                                text-decoration:none;
                                color:inherit;
                            "
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
                        href="?page=<?= $totalPages ?>"
                        style="
                            display:inline-flex;
                            align-items:center;
                            justify-content:center;
                            min-width:38px;
                            height:38px;
                            padding:0 10px;
                            border-radius:10px;
                            text-decoration:none;
                            color:inherit;
                        "
                    >
                        <?= $totalPages ?>
                    </a>

                <?php endif; ?>


                <?php if ($page < $totalPages): ?>

                    <a
                        href="?page=<?= $page + 1 ?>"
                        style="
                            display:inline-flex;
                            align-items:center;
                            justify-content:center;
                            min-width:38px;
                            height:38px;
                            padding:0 12px;
                            border-radius:10px;
                            text-decoration:none;
                            color:inherit;
                            background:var(--pink-soft);
                        "
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
                共 <?= $totalLogs ?> 条日志
            </div>

        <?php endif; ?>

    </div>

</div>

<?php

require __DIR__ . '/../includes/admin_footer.php';

?>