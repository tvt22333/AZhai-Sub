<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['session_name']);

    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']),
        'samesite' => 'Lax',
    ]);

    session_start();
}


function oauth_fail(string $message): never
{
    flash('error', $message);
    redirect('/login.php');
}


function oauth_post(
    string $url,
    array $data,
    array $headers = []
): array {

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,

        CURLOPT_POSTFIELDS => http_build_query(
            $data,
            '',
            '&',
            PHP_QUERY_RFC3986
        ),

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_FOLLOWLOCATION => false,

        
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,

        CURLOPT_CONNECTTIMEOUT => 10,

        CURLOPT_TIMEOUT => 20,

        CURLOPT_HTTPHEADER => array_merge([
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ], $headers),
    ]);

    $body = curl_exec($ch);

    $curlError = curl_error($ch);

    $curlErrno = curl_errno($ch);

    $httpCode = (int) curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if ($body === false) {
        return [
            'success' => false,
            'http_code' => $httpCode,
            'curl_errno' => $curlErrno,
            'curl_error' => $curlError,
            'body' => '',
            'json' => null,
        ];
    }

    $json = json_decode(
        $body,
        true
    );

    return [
        'success' => true,
        'http_code' => $httpCode,
        'curl_errno' => $curlErrno,
        'curl_error' => $curlError,
        'body' => $body,
        'json' => is_array($json) ? $json : null,
    ];
}


$oauth = $config['oauth'] ?? [];

if (
    empty($oauth['enabled']) ||
    empty($oauth['client_id']) ||
    empty($oauth['client_secret']) ||
    empty($oauth['token_url']) ||
    empty($oauth['userinfo_url']) ||
    empty($oauth['redirect_uri'])
) {
    oauth_fail('阿宅 OAuth 尚未正确配置');
}


if (!empty($_GET['error'])) {

    $error = trim(
        (string) $_GET['error']
    );

    $description = trim(
        (string) (
            $_GET['error_description']
            ?? ''
        )
    );

    if ($description === '') {
        $description = $error;
    }

    oauth_fail(
        '阿宅账号授权失败：' . $description
    );
}


$code = trim(
    (string) ($_GET['code'] ?? '')
);

$state = trim(
    (string) ($_GET['state'] ?? '')
);

if ($code === '') {
    oauth_fail('OAuth 授权码缺失');
}


$sessionState = $_SESSION['oauth_state'] ?? '';

if (
    $sessionState === '' ||
    $state === '' ||
    !hash_equals(
        (string) $sessionState,
        $state
    )
) {

    unset(
        $_SESSION['oauth_state'],
        $_SESSION['oauth_code_verifier']
    );

    oauth_fail(
        'OAuth 状态验证失败，请重新登录'
    );
}


$codeVerifier = (string) (
    $_SESSION['oauth_code_verifier']
    ?? ''
);


unset(
    $_SESSION['oauth_state'],
    $_SESSION['oauth_code_verifier']
);


$tokenResult = oauth_post(
    $oauth['token_url'],
    [
        'grant_type' => 'authorization_code',

        'client_id' => $oauth['client_id'],

        'client_secret' => $oauth['client_secret'],

        'redirect_uri' => $oauth['redirect_uri'],

        'code' => $code,

        'code_verifier' => $codeVerifier,
    ]
);

if (!$tokenResult['success']) {

    oauth_fail(
        'Token 请求失败：' .
        (
            $tokenResult['curl_error']
            ?: '网络请求失败'
        )
    );
}

if (
    $tokenResult['http_code'] < 200 ||
    $tokenResult['http_code'] >= 300
) {

    $error = '';

    if (
        is_array($tokenResult['json'])
    ) {

        $error =
            $tokenResult['json']['error_description']
            ??
            $tokenResult['json']['error']
            ??
            '';
    }

    if ($error === '') {
        $error = 'HTTP ' .
            $tokenResult['http_code'];
    }

    oauth_fail(
        'Token 请求失败：' . $error
    );
}

$tokenData = $tokenResult['json'];

if (
    !is_array($tokenData) ||
    empty($tokenData['access_token'])
) {
    oauth_fail(
        '阿宅 OAuth 未返回 Access Token'
    );
}

$accessToken = trim(
    (string) $tokenData['access_token']
);


$userinfoResult = oauth_post(
    $oauth['userinfo_url'],
    [
        'access_token' => $accessToken,
    ],
    [
        'Authorization: Bearer ' . $accessToken,
    ]
);

if (!$userinfoResult['success']) {

    oauth_fail(
        '获取阿宅账号信息失败：' .
        (
            $userinfoResult['curl_error']
            ?: '网络请求失败'
        )
    );
}

if (
    $userinfoResult['http_code'] < 200 ||
    $userinfoResult['http_code'] >= 300
) {

    $error = '';

    if (
        is_array($userinfoResult['json'])
    ) {

        $error =
            $userinfoResult['json']['error_description']
            ??
            $userinfoResult['json']['error']
            ??
            '';
    }

    if ($error === '') {
        $error = 'HTTP ' .
            $userinfoResult['http_code'];
    }

    oauth_fail(
        '获取阿宅账号信息失败：' . $error
    );
}

$userinfo = $userinfoResult['json'];

if (
    !is_array($userinfo) ||
    empty($userinfo['sub'])
) {

    oauth_fail(
        '阿宅账号信息格式错误'
    );
}


$oauthSub = trim(
    (string) $userinfo['sub']
);

$oauthUsername = trim(
    (string) (
        $userinfo['username']
        ?? ''
    )
);

$oauthName = trim(
    (string) (
        $userinfo['name']
        ?? ''
    )
);

$oauthEmail = trim(
    (string) (
        $userinfo['email']
        ?? ''
    )
);

if ($oauthUsername === '') {

    $oauthUsername =
        'azhai_' . $oauthSub;
}

if ($oauthName === '') {
    $oauthName = $oauthUsername;
}


$pdo = db();


$stmt = $pdo->prepare(
    'SELECT *
     FROM users
     WHERE oauth_provider = :provider
       AND oauth_sub = :oauth_sub
     LIMIT 1'
);

$stmt->execute([
    ':provider' => 'azhai',

    ':oauth_sub' => $oauthSub,
]);

$user = $stmt->fetch(
    PDO::FETCH_ASSOC
);


if ($user) {

    
    if ((int) $user['status'] !== 1) {

        oauth_fail(
            '阿宅账号对应的本地账号已被禁用'
        );
    }

    
    $update = $pdo->prepare(
        'UPDATE users
         SET email = :email,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id
         LIMIT 1'
    );

    $update->execute([
        ':email' =>
            $oauthEmail !== ''
            ? $oauthEmail
            : $user['email'],

        ':id' => $user['id'],
    ]);
}


else {

    
    $checkOAuth = $pdo->prepare(
        'SELECT id
         FROM users
         WHERE oauth_provider = :provider
           AND oauth_sub = :oauth_sub
         LIMIT 1'
    );

    $checkOAuth->execute([
        ':provider' => 'azhai',
        ':oauth_sub' => $oauthSub,
    ]);

    if ($checkOAuth->fetch()) {
        oauth_fail(
            'OAuth 账号绑定状态异常，请重新登录'
        );
    }

    
    $baseUsername = preg_replace(
        '/[^a-zA-Z0-9_\-]/',
        '_',
        $oauthUsername
    );

    $baseUsername = trim(
        (string) $baseUsername,
        '_'
    );

    if ($baseUsername === '') {
        $baseUsername =
            'azhai_' . $oauthSub;
    }

    
    $baseUsername = substr(
        $baseUsername,
        0,
        40
    );

    $localUsername = $baseUsername;

    $checkUsername = $pdo->prepare(
        'SELECT id
         FROM users
         WHERE username = :username
         LIMIT 1'
    );

    $counter = 1;

    while (true) {

        $checkUsername->execute([
            ':username' => $localUsername,
        ]);

        if (!$checkUsername->fetch()) {
            break;
        }

        $counter++;

        $suffix = '_' . $counter;

        $localUsername = substr(
            $baseUsername,
            0,
            50 - strlen($suffix)
        ) . $suffix;
    }

    
    $localEmail = $oauthEmail;

    if ($localEmail === '') {

        $localEmail =
            'oauth_' .
            $oauthSub .
            '@oauth.azhai.de';
    }

    
    $localEmail = substr(
        $localEmail,
        0,
        255
    );

    
    $randomPassword = password_hash(
        bin2hex(random_bytes(32)),
        PASSWORD_DEFAULT
    );

    
    $insert = $pdo->prepare(
        'INSERT INTO users
        (
            username,
            email,
            password,
            status,
            created_at,
            updated_at,
            oauth_provider,
            oauth_sub
        )
        VALUES
        (
            :username,
            :email,
            :password,
            1,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP,
            :oauth_provider,
            :oauth_sub
        )'
    );

    try {

        $insert->execute([
            ':username' => $localUsername,

            ':email' => $localEmail,

            ':password' => $randomPassword,

            ':oauth_provider' => 'azhai',

            ':oauth_sub' => $oauthSub,
        ]);

    } catch (PDOException $e) {

        
        if (
            (int) $e->errorInfo[1] === 1062
        ) {

            oauth_fail(
                '创建本地账号失败：用户名或邮箱已存在，请联系管理员'
            );
        }

        throw $e;
    }

    
    $userId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        'SELECT *
         FROM users
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $userId,
    ]);

    $user = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$user) {

        oauth_fail(
            '本地账号创建成功，但无法读取账号信息'
        );
    }
}


if ((int) $user['status'] !== 1) {

    oauth_fail(
        '账号当前不可用'
    );
}

session_regenerate_id(true);

$_SESSION['user_id'] = (int) $user['id'];


unset(
    $_SESSION['oauth_state'],
    $_SESSION['oauth_code_verifier']
);


if (function_exists('log_action')) {

    log_action(
        (int) $user['id'],
        null,
        'oauth_login',
        'azhai_oauth'
    );
}

flash(
    'success',
    '阿宅账号登录成功，欢迎回来，' .
    ($oauthName ?: $user['username'])
);

redirect('/dashboard/');
