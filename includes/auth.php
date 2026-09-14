<?php

$config = $config ?? require __DIR__ . '/../config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {

    session_name(
        $config['session_name'] ?? 'azhai_sub_session'
    );

    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']),
        'samesite' => 'Lax',
    ]);

    session_start();
}



function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}



function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}



function require_login(): void
{
    if (!is_logged_in()) {

        header('Location: /login.php');

        exit;
    }
}



function require_admin(): void
{
    if (!is_admin_logged_in()) {

        header('Location: /admin/login.php');

        exit;
    }
}



function current_user_id(): ?int
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    return (int)$_SESSION['user_id'];
}



function current_admin_id(): ?int
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }

    return (int)$_SESSION['admin_id'];
}



function current_username(): ?string
{
    if (empty($_SESSION['username'])) {
        return null;
    }

    return (string)$_SESSION['username'];
}



function login_user(
    int $userId,
    string $username
): void {

    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;

    $_SESSION['username'] = $username;
}



function login_admin(
    int $adminId,
    string $username
): void {

    session_regenerate_id(true);

    $_SESSION['admin_id'] = $adminId;

    $_SESSION['admin_username'] = $username;
}



function logout_user(): void
{
    unset(
        $_SESSION['user_id'],
        $_SESSION['username']
    );

    session_regenerate_id(true);
}



function logout_admin(): void
{
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_username']
    );

    session_regenerate_id(true);
}