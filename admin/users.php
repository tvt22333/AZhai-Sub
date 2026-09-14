<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);

    $action = $_POST['action'] ?? '';

    if ($id <= 0) {

        flash('error', '无效的用户 ID');

        redirect('/admin/users.php');
    }

    
    $stmt = $pdo->prepare("
        SELECT
            id,
            username,
            email,
            status
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $id
    ]);

    $user = $stmt->fetch();

    if (!$user) {

        flash('error', '用户不存在');

        redirect('/admin/users.php');
    }

    $adminId = current_admin_id();

    
    if ($action === 'disable') {

        $stmt = $pdo->prepare("
            UPDATE users
            SET
                status = 0,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([
            $id
        ]);

        log_action(
            null,
            $adminId,
            'disable_user',
            $user['username']
        );

        flash(
            'success',
            '用户已禁用'
        );

    
    } elseif ($action === 'enable') {

        $stmt = $pdo->prepare("
            UPDATE users
            SET
                status = 1,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([
            $id
        ]);

        log_action(
            null,
            $adminId,
            'enable_user',
            $user['username']
        );

        flash(
            'success',
            '用户已启用'
        );

    } else {

        flash(
            'error',
            '无效的操作'
        );
    }

    redirect('/admin/users.php');
}

$users = $pdo->query("
    SELECT
        id,
        username,
        email,
        status,
        created_at
    FROM users
    ORDER BY id DESC
")->fetchAll();

$pageTitle = '用户管理';

require __DIR__ . '/../includes/admin_header.php';

?>

<div class="admin-dashboard">

    <div class="admin-welcome">

        <h1>用户管理</h1>

        <p>
            管理 AZhai Sub 注册用户。
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
            用户列表
            <small>(<?= count($users) ?>)</small>
        </h2>

        <div class="table-wrap">

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>用户名</th>
                        <th>邮箱</th>
                        <th>状态</th>
                        <th>注册时间</th>
                        <th>操作</th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach ($users as $user): ?>

                    <tr>

                        <td>
                            <?= (int)$user['id'] ?>
                        </td>

                        <td>
                            <strong>
                                <?= e($user['username']) ?>
                            </strong>
                        </td>

                        <td>
                            <?= e($user['email']) ?>
                        </td>

                        <td>

                            <?php if ((int)$user['status'] === 1): ?>

                                <span class="badge success">
                                    正常
                                </span>

                            <?php else: ?>

                                <span class="badge danger">
                                    禁用
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= e($user['created_at']) ?>
                        </td>

                        <td>

                            <?php if ((int)$user['status'] === 1): ?>

                                <form
                                    method="post"
                                    style="display:inline"
                                >

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$user['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="disable"
                                    >

                                    <button
                                        type="submit"
                                        class="link-button danger"
                                        onclick="return confirm('确定要禁用这个用户吗？')"
                                    >
                                        禁用
                                    </button>

                                </form>

                            <?php else: ?>

                                <form
                                    method="post"
                                    style="display:inline"
                                >

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$user['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="enable"
                                    >

                                    <button
                                        type="submit"
                                        class="link-button"
                                    >
                                        启用
                                    </button>

                                </form>

                            <?php endif; ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>