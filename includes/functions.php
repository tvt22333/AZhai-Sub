<?php

if (!isset($config)) {
    $config = require __DIR__ . '/../config.php';
}

if (session_status() !== PHP_SESSION_ACTIVE) {

    session_name($config['session_name']);

    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']),
        'samesite' => 'Lax',
    ]);

    session_start();
}


function e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}


function base_domain(): string
{
    global $config;

    return strtolower(
        trim($config['base_domain'])
    );
}


function is_valid_subdomain(string $name): bool
{
    $name = strtolower(trim($name));

    if ($name === '') {
        return false;
    }

    if (!preg_match(
        '/^(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',
        $name
    )) {
        return false;
    }

    return strlen($name) <= 63;
}


function build_fqdn(string $name): string
{
    $name = strtolower(trim($name));

    $domain = base_domain();

    if ($name === '' || $name === '@') {
        return $domain;
    }

    $suffix = '.' . $domain;

    if (
        $name === $domain ||
        str_ends_with($name, $suffix)
    ) {
        return $name;
    }

    return $name . $suffix;
}


function valid_record_type(string $type): bool
{
    return in_array(
        strtoupper($type),
        ['A', 'AAAA', 'CNAME', 'TXT'],
        true
    );
}


function valid_dns_content(
    string $type,
    string $content
): bool {

    $type = strtoupper($type);
    $content = trim($content);

    if ($content === '') {
        return false;
    }

    switch ($type) {

        case 'A':

            return filter_var(
                $content,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4
            ) !== false;

        case 'AAAA':

            return filter_var(
                $content,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV6
            ) !== false;

        case 'CNAME':

            return (bool)preg_match(
                '/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.?$/',
                $content
            );

        case 'TXT':

            return strlen($content) <= 4096;
    }

    return false;
}


function encrypt_secret(string $value): string
{
    global $config;

    $key = hash(
        'sha256',
        $config['app_key'],
        true
    );

    $iv = random_bytes(16);

    $encrypted = openssl_encrypt(
        $value,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($encrypted === false) {
        throw new RuntimeException('加密失败');
    }

    return base64_encode(
        $iv . $encrypted
    );
}


function decrypt_secret(string $value): string
{
    global $config;

    $key = hash(
        'sha256',
        $config['app_key'],
        true
    );

    $raw = base64_decode(
        $value,
        true
    );

    if (
        $raw === false ||
        strlen($raw) < 17
    ) {
        return '';
    }

    $iv = substr($raw, 0, 16);

    $encrypted = substr(
        $raw,
        16
    );

    $result = openssl_decrypt(
        $encrypted,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    return $result === false ? '' : $result;
}
function flash(string $type, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$type] = $message;
        return null;
    }

    if (
        empty($_SESSION['_flash']) ||
        !array_key_exists($type, $_SESSION['_flash'])
    ) {
        return null;
    }

    $message = $_SESSION['_flash'][$type];

    unset($_SESSION['_flash'][$type]);

    return $message;
}
function log_action(
    ?int $userId,
    ?int $adminId,
    string $action,
    ?string $target = null
): void {
    try {
        $pdo = db();

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        if ($ip !== null) {
            $ip = substr($ip, 0, 45);
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        if ($userAgent !== null) {
            $userAgent = substr($userAgent, 0, 65535);
        }

        $stmt = $pdo->prepare("
            INSERT INTO operation_logs (
                user_id,
                admin_id,
                action,
                target,
                ip,
                user_agent,
                created_at
            ) VALUES (
                :user_id,
                :admin_id,
                :action,
                :target,
                :ip,
                :user_agent,
                NOW()
            )
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':admin_id' => $adminId,
            ':action' => $action,
            ':target' => $target,
            ':ip' => $ip,
            ':user_agent' => $userAgent,
        ]);

    } catch (Throwable $e) {

        
        error_log(
            '[AZhai Sub Log] ' . $e->getMessage()
        );
    }
}