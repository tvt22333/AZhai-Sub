<?php

$config = $config ?? require __DIR__ . '/../config.php';

$db = $config['db'];

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $db['host'],
    $db['port'],
    $db['database'],
    $db['charset']
);

try {

    $pdo = new PDO(
        $dsn,
        $db['username'],
        $db['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

} catch (PDOException $e) {

    http_response_code(500);

    exit('数据库连接失败');

}



function db(): PDO
{
    global $pdo;

    return $pdo;
}
if( !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ) {
$get_HTTP_X_FORWARDED_FOR = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
$_SERVER['REMOTE_ADDR'] = trim($get_HTTP_X_FORWARDED_FOR[0]);
}