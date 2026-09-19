<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';


/*
|--------------------------------------------------------------------------
| 网站设置缓存
|--------------------------------------------------------------------------
*/

$GLOBALS['azhai_site_settings'] ??= null;


/*
|--------------------------------------------------------------------------
| 获取全部网站设置
|--------------------------------------------------------------------------
*/

function site_settings(): array
{
    if (is_array($GLOBALS['azhai_site_settings'])) {
        return $GLOBALS['azhai_site_settings'];
    }

    $pdo = db();

    $stmt = $pdo->query("
        SELECT
            setting_key,
            setting_value
        FROM site_settings
        ORDER BY id ASC
    ");

    $settings = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $settings[$row['setting_key']] =
            $row['setting_value'];
    }

    $GLOBALS['azhai_site_settings'] = $settings;

    return $settings;
}


/*
|--------------------------------------------------------------------------
| 获取单个设置
|--------------------------------------------------------------------------
*/

function site_setting(
    string $key,
    ?string $default = null
): ?string {

    $settings = site_settings();

    if (!array_key_exists($key, $settings)) {
        return $default;
    }

    return $settings[$key];
}


/*
|--------------------------------------------------------------------------
| 获取布尔设置
|--------------------------------------------------------------------------
*/

function site_setting_bool(
    string $key,
    bool $default = false
): bool {

    $value = site_setting(
        $key,
        $default ? '1' : '0'
    );

    return in_array(
        strtolower((string) $value),
        [
            '1',
            'true',
            'yes',
            'on'
        ],
        true
    );
}


/*
|--------------------------------------------------------------------------
| 获取整数设置
|--------------------------------------------------------------------------
*/

function site_setting_int(
    string $key,
    int $default = 0
): int {

    $value = site_setting(
        $key,
        (string) $default
    );

    return (int) $value;
}


/*
|--------------------------------------------------------------------------
| 保存单个设置
|--------------------------------------------------------------------------
*/

function update_site_setting(
    string $key,
    ?string $value
): void {

    $pdo = db();

    $stmt = $pdo->prepare("
        INSERT INTO site_settings (
            setting_key,
            setting_value
        )
        VALUES (
            :setting_key,
            :setting_value
        )
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value)
    ");

    $stmt->execute([
        ':setting_key' => $key,
        ':setting_value' => $value
    ]);

    $GLOBALS['azhai_site_settings'] = null;
}


/*
|--------------------------------------------------------------------------
| 批量保存设置
|--------------------------------------------------------------------------
*/

function update_site_settings(
    array $settings
): void {

    $pdo = db();

    $stmt = $pdo->prepare("
        INSERT INTO site_settings (
            setting_key,
            setting_value
        )
        VALUES (
            :setting_key,
            :setting_value
        )
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value)
    ");

    $pdo->beginTransaction();

    try {

        foreach ($settings as $key => $value) {

            $stmt->execute([
                ':setting_key' => (string) $key,
                ':setting_value' => $value === null
                    ? null
                    : (string) $value
            ]);
        }

        $pdo->commit();

        $GLOBALS['azhai_site_settings'] = null;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}


/*
|--------------------------------------------------------------------------
| 网站标题
|--------------------------------------------------------------------------
*/

function site_title(): string
{
    return site_setting(
        'site_name',
        'AZhai Sub'
    ) ?? 'AZhai Sub';
}


/*
|--------------------------------------------------------------------------
| 网站中文名称
|--------------------------------------------------------------------------
*/

function site_title_cn(): string
{
    return site_setting(
        'site_name_cn',
        '阿宅二级域名'
    ) ?? '阿宅二级域名';
}


/*
|--------------------------------------------------------------------------
| 网站 URL
|--------------------------------------------------------------------------
*/

function site_url(): string
{
    return site_setting(
        'site_url',
        'https://sub.azhai.de'
    ) ?? 'https://sub.azhai.de';
}


/*
|--------------------------------------------------------------------------
| 网站描述
|--------------------------------------------------------------------------
*/

function site_description(): string
{
    return site_setting(
        'site_description',
        ''
    ) ?? '';
}


/*
|--------------------------------------------------------------------------
| 网站关键词
|--------------------------------------------------------------------------
*/

function site_keywords(): string
{
    return site_setting(
        'site_keywords',
        ''
    ) ?? '';
}
