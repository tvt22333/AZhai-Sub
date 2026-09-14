<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (empty($config['oauth']['enabled'])) {
    http_response_code(503);
    exit('阿宅 OAuth 登录暂未启用');
}

$oauth = $config['oauth'];

if (
    empty($oauth['client_id']) ||
    empty($oauth['authorize_url']) ||
    empty($oauth['redirect_uri'])
) {
    http_response_code(500);
    exit('OAuth 配置不完整');
}



$state = bin2hex(random_bytes(32));



$codeVerifier = rtrim(
    strtr(
        base64_encode(random_bytes(64)),
        '+/',
        '-_'
    ),
    '='
);



$codeChallenge = rtrim(
    strtr(
        base64_encode(
            hash(
                'sha256',
                $codeVerifier,
                true
            )
        ),
        '+/',
        '-_'
    ),
    '='
);



$_SESSION['oauth_state'] = $state;

$_SESSION['oauth_code_verifier'] = $codeVerifier;



$returnTo = $_GET['return_to'] ?? '/dashboard/';

if (
    !is_string($returnTo) ||
    $returnTo === '' ||
    $returnTo[0] !== '/' ||
    str_starts_with($returnTo, '//')
) {
    $returnTo = '/dashboard/';
}

$_SESSION['oauth_return_to'] = $returnTo;



$params = [
    'client_id' => $oauth['client_id'],

    'redirect_uri' => $oauth['redirect_uri'],

    'response_type' => 'code',

    'scope' => 'openid profile email',

    'state' => $state,

    'code_challenge' => $codeChallenge,

    'code_challenge_method' => 'S256',
];



$url =
    $oauth['authorize_url']
    . '?'
    . http_build_query(
        $params,
        '',
        '&',
        PHP_QUERY_RFC3986
    );

header('Cache-Control: no-store');
header('Pragma: no-cache');

header(
    'Location: ' . $url
);

exit;
