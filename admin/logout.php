<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

$adminId = current_admin_id();

if ($adminId !== null) {
    log_action(
        null,
        $adminId,
        'logout',
        'admin'
    );
}

logout_admin();

header('Location: /admin/login.php');
exit;