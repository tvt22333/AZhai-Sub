<?php

require_once __DIR__ . '/../includes/functions.php';

$a = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = $_POST['action'] ?? '';

    
    if ($action === 'add') {

        $keyword = trim($_POST['keyword'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        if ($keyword) {

            $stmt = db()->prepare(
                'INSERT IGNORE INTO blacklist(keyword,reason) VALUES(?,?)'
            );

            $stmt->execute([
                $keyword,
                $reason ?: null
            ]);

            
            if ($stmt->rowCount() > 0) {

                log_action(
                    null,
                    (int)($a['id'] ?? current_admin_id()),
                    'add_blacklist',
                    $keyword
                );

                flash(
                    'success',
                    '黑名单已更新。'
                );

            } else {

                flash(
                    'error',
                    '该关键词已经存在。'
                );
            }
        }

    
    } elseif ($action === 'delete') {

        $id = (int)($_POST['id'] ?? 0);

        
        $stmt = db()->prepare(
            'SELECT keyword FROM blacklist WHERE id=? LIMIT 1'
        );

        $stmt->execute([
            $id
        ]);

        $item = $stmt->fetch();

        if ($item) {

            $stmt = db()->prepare(
                'DELETE FROM blacklist WHERE id=?'
            );

            $stmt->execute([
                $id
            ]);

            if ($stmt->rowCount() > 0) {

                log_action(
                    null,
                    (int)($a['id'] ?? current_admin_id()),
                    'delete_blacklist',
                    $item['keyword']
                );

                flash(
                    'success',
                    '关键词已删除。'
                );

            } else {

                flash(
                    'error',
                    '删除失败。'
                );
            }

        } else {

            flash(
                'error',
                '关键词不存在。'
            );
        }
    }

    redirect('blacklist.php');
}

$items = db()->query(
    'SELECT * FROM blacklist ORDER BY id DESC'
)->fetchAll();

$page_title = '黑名单';

require __DIR__ . '/../includes/admin_header.php';

?>

<div class="page-head">

    <div>

        <span class="eyebrow">
            BLACKLIST
        </span>

        <h1>
            黑名单
        </h1>

    </div>

</div>

<div class="card form-card">

    <form
        method="post"
        class="inline-form"
    >

        <input
            type="hidden"
            name="csrf"
            value="<?= e(csrf_token()) ?>"
        >

        <input
            type="hidden"
            name="action"
            value="add"
        >

        <input
            name="keyword"
            placeholder="关键词"
            required
        >

        <input
            name="reason"
            placeholder="原因"
        >

        <button
            class="button primary"
        >
            添加
        </button>

    </form>

</div>

<div class="card table-card">

    <div class="table-wrap">

        <table>

            <thead>

                <tr>
                    <th>关键词</th>
                    <th>原因</th>
                    <th>时间</th>
                    <th></th>
                </tr>

            </thead>

            <tbody>

            <?php foreach ($items as $x): ?>

                <tr>

                    <td>
                        <strong>
                            <?= e($x['keyword']) ?>
                        </strong>
                    </td>

                    <td>
                        <?= e(
                            $x['reason'] ?: '—'
                        ) ?>
                    </td>

                    <td>
                        <?= e(
                            $x['created_at']
                        ) ?>
                    </td>

                    <td>

                        <form method="post">

                            <input
                                type="hidden"
                                name="csrf"
                                value="<?= e(csrf_token()) ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="delete"
                            >

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int)$x['id'] ?>"
                            >

                            <button
                                class="link-button danger"
                                type="submit"
                                onclick="return confirm('确定删除这个关键词吗？')"
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

</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>