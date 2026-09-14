<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';

$userId = current_user_id();

if ($userId !== null) {
    log_action(
        $userId,
        null,
        'logout',
        'user'
    );
}

logout_user();

header('Location: /');
exit;