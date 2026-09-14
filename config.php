<?php

return [
    
    'site_name' => 'AZhai Sub',
    'site_name_cn' => '阿宅二级域名',
    'site_url' => 'https://sub.azhai.de',

    /*
    |--------------------------------------------------------------------------
    | 当前版本
    |--------------------------------------------------------------------------
    */
'version' => '1.0.8',

    'base_domain' => 'azhai.de',

    /*
    |--------------------------------------------------------------------------
    | MySQL
    |--------------------------------------------------------------------------
    */

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => '数据库',
        'username' => '数据库',
        'password' => '密码',
        'charset' => 'utf8mb4',
    ],

    'app_key' => 'CHANGE_THIS_TO_A_RANDOM_64_CHARACTER_SECRET',

    'session_name' => 'azhai_sub_session',

    'update' => [
        'enabled' => true,

        'product' => 'azhai-sub',

        'channel' => 'stable',

        'check_url' => 'https://update.azhai.de/api/check.php',

        'download_url' => 'https://update.azhai.de/api/download.php',

        'token' => 'a3896c4716bb19974e5f7563fbe1efe3095fe473f77a9ac365fadde289d31dad',
    ],

    'registration_enabled' => true,

    'max_domains_per_user' => 10,

    'oauth' => [
        'enabled' => true,

        'client_id' => '去oauth.azhai.de申请阿宅登录',

        'client_secret' => '去oauth.azhai.de申请阿宅登录',
        
        'authorize_url' => 'https://oauth.azhai.de/authorize.php',

        'token_url' => 'https://oauth.azhai.de/token.php',

        'userinfo_url' => 'https://oauth.azhai.de/userinfo.php',

        'redirect_uri' => 'https://sub.azhai.de/oauth/callback.php',
    ],

];