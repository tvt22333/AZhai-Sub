<?php

declare(strict_types=1);

/**
 * AZhai Sub
 * 在线升级模块
 */

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/**
 * 格式化文件大小
 */
if (!function_exists('update_format_size')) {
    function update_format_size(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format(
                $bytes / 1024,
                2
            ) . ' KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return number_format(
                $bytes / 1024 / 1024,
                2
            ) . ' MB';
        }

        return number_format(
            $bytes / 1024 / 1024 / 1024,
            2
        ) . ' GB';
    }
}

/**
 * 获取升级配置
 */
function update_config(): array
{
    global $config;

    if (
        !isset($config)
        || !is_array($config)
    ) {
        return [];
    }

    $update =
        $config['update']
        ?? [];

    return is_array($update)
        ? $update
        : [];
}

/**
 * 判断升级功能是否启用
 */
function update_is_enabled(): bool
{
    $update =
        update_config();

    if (
        isset($update['enabled'])
        && !$update['enabled']
    ) {
        return false;
    }

    $token =
        trim(
            (string) (
                $update['token']
                ?? ''
            )
        );

    if (
        $token === ''
        || str_starts_with(
            $token,
            'CHANGE_THIS'
        )
    ) {
        return false;
    }

    return true;
}

/**
 * 获取当前版本
 */
function update_current_version(): string
{
    global $config;

    return trim(
        (string) (
            $config['version']
            ?? ''
        )
    );
}

/**
 * 获取产品标识
 */
function update_product(): string
{
    $update =
        update_config();

    return trim(
        (string) (
            $update['product']
            ?? 'azhai-sub'
        )
    );
}

/**
 * 获取升级频道
 */
function update_channel(): string
{
    $update =
        update_config();

    return trim(
        (string) (
            $update['channel']
            ?? 'stable'
        )
    );
}

/**
 * 获取检查地址
 */
function update_check_url(): string
{
    $update =
        update_config();

    return trim(
        (string) (
            $update['check_url']
            ?? 'https://update.azhai.de/api/check.php'
        )
    );
}

/**
 * 获取升级 Token
 */
function update_token(): string
{
    $update =
        update_config();

    return trim(
        (string) (
            $update['token']
            ?? ''
        )
    );
}

/**
 * JSON 编码
 */
function update_json_encode(
    mixed $data
): string {
    $json =
        json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

    return $json === false
        ? '{}'
        : $json;
}

/**
 * 记录升级器日志
 */
function update_log(
    string $message
): void {
    $root =
        dirname(__DIR__);

    $logDir =
        $root . '/storage';

    if (!is_dir($logDir)) {
        @mkdir(
            $logDir,
            0755,
            true
        );
    }

    $logFile =
        $logDir . '/updater.log';

    $line =
        '['
        . date('Y-m-d H:i:s')
        . '] '
        . $message
        . PHP_EOL;

    @file_put_contents(
        $logFile,
        $line,
        FILE_APPEND | LOCK_EX
    );
}

/**
 * HTTP GET
 */
function update_http_get(
    string $url,
    string $token,
    bool $acceptJson = true
): array {
    if (
        !function_exists('curl_init')
    ) {
        return [
            'success' => false,
            'status' => 0,
            'body' => '',
            'error' =>
                '服务器未安装 cURL'
        ];
    }

    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: '
        . (
            $acceptJson
                ? 'application/json'
                : '*/*'
        ),
        'Connection: close',
        'Cache-Control: no-cache'
    ];

    $ch =
        curl_init();

    if ($ch === false) {
        return [
            'success' => false,
            'status' => 0,
            'body' => '',
            'error' =>
                '无法初始化 cURL'
        ];
    }

    curl_setopt_array(
        $ch,
        [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTP_VERSION =>
                CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT =>
                'AZhai-Sub-Updater/'
                . update_current_version()
        ]
    );

    $body =
        curl_exec($ch);

    $error =
        curl_error($ch);

    $errno =
        curl_errno($ch);

    $status =
        (int) curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

    curl_close($ch);

    if ($body === false) {
        return [
            'success' => false,
            'status' => $status,
            'body' => '',
            'error' =>
                $error !== ''
                    ? $error
                    : 'HTTP 请求失败，错误码 '
                    . $errno
        ];
    }

    if (
        $status < 200
        || $status >= 300
    ) {
        return [
            'success' => false,
            'status' => $status,
            'body' => (string) $body,
            'error' =>
                '服务器返回 HTTP '
                . $status
        ];
    }

    return [
        'success' => true,
        'status' => $status,
        'body' => (string) $body,
        'error' => ''
    ];
}

/**
 * 检查升级
 */
function update_check(): array
{
    if (!update_is_enabled()) {
        return [
            'success' => false,
            'available' => false,
            'message' =>
                '在线升级未配置'
        ];
    }

    $currentVersion =
        update_current_version();

    $product =
        update_product();

    $channel =
        update_channel();

    $baseUrl =
        update_check_url();

    $query =
        http_build_query(
            [
                'product' => $product,
                'version' => $currentVersion,
                'channel' => $channel
            ]
        );

    $url =
        $baseUrl
        . (
            str_contains(
                $baseUrl,
                '?'
            )
                ? '&'
                : '?'
        )
        . $query;

    $result =
        update_http_get(
            $url,
            update_token(),
            true
        );

    if (
        !$result['success']
    ) {
        update_log(
            '检查更新失败: '
            . $result['error']
        );

        return [
            'success' => false,
            'available' => false,
            'message' =>
                $result['error']
        ];
    }

    $data =
        json_decode(
            $result['body'],
            true
        );

    if (!is_array($data)) {
        return [
            'success' => false,
            'available' => false,
            'message' =>
                '升级中心返回的数据无效'
        ];
    }

    if (
        isset($data['success'])
        && !$data['success']
    ) {
        return [
            'success' => false,
            'available' => false,
            'message' =>
                (string) (
                    $data['message']
                    ?? '检查更新失败'
                ),
            'data' => $data
        ];
    }

    $release = null;

    if (
        isset($data['release'])
        && is_array(
            $data['release']
        )
    ) {
        $release =
            $data['release'];
    } elseif (
        isset($data['data'])
        && is_array(
            $data['data']
        )
        && isset(
            $data['data']['release']
        )
        && is_array(
            $data['data']['release']
        )
    ) {
        $release =
            $data['data']['release'];
    }

    if (
        !is_array($release)
        || empty($release['version'])
    ) {
        return [
            'success' => true,
            'available' => false,
            'message' =>
                '当前已是最新版本',
            'data' => $data
        ];
    }

    $targetVersion =
        trim(
            (string) (
                $release['version']
            )
        );

    if (
        $targetVersion === ''
        || !update_valid_version(
            $targetVersion
        )
    ) {
        return [
            'success' => false,
            'available' => false,
            'message' =>
                '升级中心返回了无效版本号'
        ];
    }

    if (
        update_compare_versions(
            $targetVersion,
            $currentVersion
        ) <= 0
    ) {
        return [
            'success' => true,
            'available' => false,
            'message' =>
                '当前已是最新版本',
            'release' => $release
        ];
    }

    return [
        'success' => true,
        'available' => true,
        'message' =>
            '发现新版本',
        'current_version' =>
            $currentVersion,
        'release' => $release
    ];
}

/**
 * 验证版本号
 */
function update_valid_version(
    string $version
): bool {
    $version =
        trim($version);

    if ($version === '') {
        return false;
    }

    if (strlen($version) > 50) {
        return false;
    }

    return preg_match(
        '/^[0-9]+(?:\.[0-9]+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/',
        $version
    ) === 1;
}

/**
 * 版本比较
 */
function update_compare_versions(
    string $a,
    string $b
): int {
    $a =
        trim($a);

    $b =
        trim($b);

    $aParts =
        preg_split(
            '/[.+-]/',
            $a
        );

    $bParts =
        preg_split(
            '/[.+-]/',
            $b
        );

    $aParts =
        is_array($aParts)
            ? $aParts
            : [];

    $bParts =
        is_array($bParts)
            ? $bParts
            : [];

    $max =
        max(
            count($aParts),
            count($bParts)
        );

    for (
        $i = 0;
        $i < $max;
        $i++
    ) {
        $av =
            isset($aParts[$i])
                ? (int) $aParts[$i]
                : 0;

        $bv =
            isset($bParts[$i])
                ? (int) $bParts[$i]
                : 0;

        if ($av > $bv) {
            return 1;
        }

        if ($av < $bv) {
            return -1;
        }
    }

    return 0;
}

/**
 * 创建目录
 */
function update_ensure_directory(
    string $directory
): bool {
    if (is_dir($directory)) {
        return true;
    }

    return @mkdir(
        $directory,
        0755,
        true
    );
}

/**
 * 下载升级包
 */
function update_download(
    array $release
): array {
    if (!update_is_enabled()) {
        return [
            'success' => false,
            'message' =>
                '在线升级未配置'
        ];
    }

    $url =
        trim(
            (string) (
                $release['download_url']
                ?? $release['url']
                ?? ''
            )
        );

    if ($url === '') {
        return [
            'success' => false,
            'message' =>
                '升级包地址为空'
        ];
    }

    $expectedSha256 =
        strtolower(
            trim(
                (string) (
                    $release['sha256']
                    ?? ''
                )
            )
        );

    if (
        $expectedSha256 !== ''
        && !preg_match(
            '/^[a-f0-9]{64}$/',
            $expectedSha256
        )
    ) {
        return [
            'success' => false,
            'message' =>
                '升级包 SHA-256 无效'
        ];
    }

    $root =
        dirname(__DIR__);

    $tempDir =
        $root
        . '/storage/update';

    if (
        !update_ensure_directory(
            $tempDir
        )
    ) {
        return [
            'success' => false,
            'message' =>
                '无法创建升级临时目录'
        ];
    }

    $targetVersion =
        trim(
            (string) (
                $release['version']
                ?? 'unknown'
            )
        );

    $safeVersion =
        preg_replace(
            '/[^0-9A-Za-z._-]/',
            '_',
            $targetVersion
        );

    if (!is_string($safeVersion)) {
        $safeVersion =
            'unknown';
    }

    $packagePath =
        $tempDir
        . '/azhai-sub-'
        . $safeVersion
        . '.zip';

    $result =
        update_http_get(
            $url,
            update_token(),
            false
        );

    if (
        !$result['success']
    ) {
        return [
            'success' => false,
            'message' =>
                '下载升级包失败: '
                . $result['error']
        ];
    }

    $body =
        (string) $result['body'];

    if ($body === '') {
        return [
            'success' => false,
            'message' =>
                '升级包为空'
        ];
    }

    $maxSize =
        2147483648;

    if (
        strlen($body)
        > $maxSize
    ) {
        return [
            'success' => false,
            'message' =>
                '升级包超过允许大小'
        ];
    }

    if (
        $expectedSha256 !== ''
    ) {
        $actualSha256 =
            hash(
                'sha256',
                $body
            );

        if (
            !hash_equals(
                $expectedSha256,
                $actualSha256
            )
        ) {
            return [
                'success' => false,
                'message' =>
                    '升级包 SHA-256 校验失败'
            ];
        }
    }

    if (
        @file_put_contents(
            $packagePath,
            $body,
            LOCK_EX
        ) === false
    ) {
        return [
            'success' => false,
            'message' =>
                '无法保存升级包'
        ];
    }

    return [
        'success' => true,
        'message' =>
            '升级包下载成功',
        'path' =>
            $packagePath,
        'sha256' =>
            hash(
                'sha256',
                $body
            )
    ];
}

/**
 * ZIP 路径安全检查
 */
function update_zip_path_safe(
    string $path
): bool {
    $path =
        str_replace(
            '\\',
            '/',
            $path
        );

    $path =
        ltrim(
            $path,
            '/'
        );

    if ($path === '') {
        return false;
    }

    if (
        str_contains(
            $path,
            "\0"
        )
    ) {
        return false;
    }

    $parts =
        explode(
            '/',
            $path
        );

    foreach ($parts as $part) {
        if (
            $part === '..'
        ) {
            return false;
        }
    }

    if (
        preg_match(
            '/^[A-Za-z]:/',
            $path
        ) === 1
    ) {
        return false;
    }

    return true;
}

/**
 * ZIP 文件安全检查
 */
function update_validate_zip(
    string $zipPath
): array {
    if (
        !class_exists('ZipArchive')
    ) {
        return [
            'success' => false,
            'message' =>
                '服务器未安装 ZipArchive'
        ];
    }

    $zip =
        new ZipArchive();

    $open =
        $zip->open(
            $zipPath
        );

    if ($open !== true) {
        return [
            'success' => false,
            'message' =>
                '无法打开升级包'
        ];
    }

    for (
        $i = 0;
        $i < $zip->numFiles;
        $i++
    ) {
        $stat =
            $zip->statIndex($i);

        if (
            !is_array($stat)
            || !isset($stat['name'])
        ) {
            $zip->close();

            return [
                'success' => false,
                'message' =>
                    '升级包目录结构无效'
            ];
        }

        $name =
            (string) $stat['name'];

        if (
            !update_zip_path_safe(
                $name
            )
        ) {
            $zip->close();

            return [
                'success' => false,
                'message' =>
                    '升级包包含不安全路径'
            ];
        }
    }

    $zip->close();

    return [
        'success' => true,
        'message' =>
            '升级包检查通过'
    ];
}

/**
 * 解压升级包
 */
function update_extract_zip(
    string $zipPath,
    string $destination
): array {
    if (
        !class_exists('ZipArchive')
    ) {
        return [
            'success' => false,
            'message' =>
                '服务器未安装 ZipArchive'
        ];
    }

    if (
        !update_ensure_directory(
            $destination
        )
    ) {
        return [
            'success' => false,
            'message' =>
                '无法创建解压目录'
        ];
    }

    $zip =
        new ZipArchive();

    $open =
        $zip->open(
            $zipPath
        );

    if ($open !== true) {
        return [
            'success' => false,
            'message' =>
                '无法打开升级包'
        ];
    }

    for (
        $i = 0;
        $i < $zip->numFiles;
        $i++
    ) {
        $stat =
            $zip->statIndex($i);

        if (
            !is_array($stat)
            || !isset($stat['name'])
        ) {
            $zip->close();

            return [
                'success' => false,
                'message' =>
                    '升级包结构无效'
            ];
        }

        $name =
            str_replace(
                '\\',
                '/',
                (string) $stat['name']
            );

        if (
            !update_zip_path_safe(
                $name
            )
        ) {
            $zip->close();

            return [
                'success' => false,
                'message' =>
                    '检测到不安全路径'
            ];
        }

        $target =
            $destination
            . '/'
            . ltrim(
                $name,
                '/'
            );

        if (
            str_ends_with(
                $name,
                '/'
            )
        ) {
            if (
                !update_ensure_directory(
                    $target
                )
            ) {
                $zip->close();

                return [
                    'success' => false,
                    'message' =>
                        '无法创建目录'
                ];
            }

            continue;
        }

        $parent =
            dirname($target);

        if (
            !update_ensure_directory(
                $parent
            )
        ) {
            $zip->close();

            return [
                'success' => false,
                'message' =>
                    '无法创建文件目录'
            ];
        }

        $stream =
            $zip->getStream(
                $name
            );

        if ($stream === false) {
            $zip->close();

            return [
                'success' => false,
                'message' =>
                    '无法读取升级包文件'
            ];
        }

        $output =
            @fopen(
                $target,
                'wb'
            );

        if ($output === false) {
            fclose($stream);
            $zip->close();

            return [
                'success' => false,
                'message' =>
                    '无法写入升级文件'
            ];
        }

        while (
            !feof($stream)
        ) {
            $buffer =
                fread(
                    $stream,
                    1048576
                );

            if ($buffer === false) {
                fclose($stream);
                fclose($output);
                $zip->close();

                return [
                    'success' => false,
                    'message' =>
                        '读取升级文件失败'
                ];
            }

            if ($buffer !== '') {
                if (
                    fwrite(
                        $output,
                        $buffer
                    ) === false
                ) {
                    fclose($stream);
                    fclose($output);
                    $zip->close();

                    return [
                        'success' => false,
                        'message' =>
                            '写入升级文件失败'
                    ];
                }
            }
        }

        fclose($stream);
        fclose($output);

        @chmod(
            $target,
            0644
        );
    }

    $zip->close();

    return [
        'success' => true,
        'message' =>
            '升级包解压成功'
    ];
}

/**
 * 获取升级包实际根目录
 */
function update_detect_package_root(
    string $extractRoot
): string {
    $entries =
        @scandir(
            $extractRoot
        );

    if (!is_array($entries)) {
        return $extractRoot;
    }

    $entries =
        array_values(
            array_filter(
                $entries,
                static function (
                    string $item
                ): bool {
                    return $item !== '.'
                        && $item !== '..';
                }
            )
        );

    if (
        count($entries) !== 1
    ) {
        return $extractRoot;
    }

    $single =
        $extractRoot
        . '/'
        . $entries[0];

    if (is_dir($single)) {
        return $single;
    }

    return $extractRoot;
}

/**
 * 删除目录
 */
function update_delete_directory(
    string $directory
): bool {
    if (!is_dir($directory)) {
        return true;
    }

    $items =
        @scandir(
            $directory
        );

    if (!is_array($items)) {
        return false;
    }

    foreach ($items as $item) {
        if (
            $item === '.'
            || $item === '..'
        ) {
            continue;
        }

        $path =
            $directory
            . '/'
            . $item;

        if (is_dir($path)) {
            if (
                !update_delete_directory(
                    $path
                )
            ) {
                return false;
            }

            continue;
        }

        if (
            !@unlink($path)
        ) {
            return false;
        }
    }

    return @rmdir(
        $directory
    );
}

/**
 * 复制目录
 */
function update_copy_directory(
    string $source,
    string $destination
): array {
    if (!is_dir($source)) {
        return [
            'success' => false,
            'message' =>
                '源目录不存在'
        ];
    }

    if (
        !update_ensure_directory(
            $destination
        )
    ) {
        return [
            'success' => false,
            'message' =>
                '无法创建目标目录'
        ];
    }

    $items =
        @scandir(
            $source
        );

    if (!is_array($items)) {
        return [
            'success' => false,
            'message' =>
                '无法读取源目录'
        ];
    }

    foreach ($items as $item) {
        if (
            $item === '.'
            || $item === '..'
        ) {
            continue;
        }

        $sourcePath =
            $source
            . '/'
            . $item;

        $destinationPath =
            $destination
            . '/'
            . $item;

        if (is_dir($sourcePath)) {
            $result =
                update_copy_directory(
                    $sourcePath,
                    $destinationPath
                );

            if (
                !$result['success']
            ) {
                return $result;
            }

            continue;
        }

        if (
            file_exists(
                $destinationPath
            )
        ) {
            if (
                is_dir(
                    $destinationPath
                )
            ) {
                if (
                    !update_delete_directory(
                        $destinationPath
                    )
                ) {
                    return [
                        'success' => false,
                        'message' =>
                            '无法删除冲突目录: '
                            . $item
                    ];
                }
            } elseif (
                !@unlink(
                    $destinationPath
                )
            ) {
                return [
                    'success' => false,
                    'message' =>
                        '无法删除旧文件: '
                        . $item
                ];
            }
        }

        if (
            !@copy(
                $sourcePath,
                $destinationPath
            )
        ) {
            return [
                'success' => false,
                'message' =>
                    '复制文件失败: '
                    . $item
            ];
        }

        @chmod(
            $destinationPath,
            0644
        );
    }

    return [
        'success' => true,
        'message' =>
            '复制完成'
    ];
}

/**
 * 受保护文件
 */
function update_protected_files(): array
{
    return [
        'config.php',
        '.user.ini',
        'admin/update.php',
        'includes/updater.php'
    ];
}

/**
 * 判断文件是否受保护
 */
function update_is_protected_file(
    string $relativePath
): bool {
    $relativePath = str_replace(
        '\\',
        '/',
        $relativePath
    );

    $relativePath = trim(
        $relativePath,
        '/'
    );

    $relativePath = preg_replace(
        '#/+#',
        '/',
        $relativePath
    );

    if (!is_string($relativePath)) {
        return false;
    }

    $protected = update_protected_files();

    if (
        in_array(
            $relativePath,
            $protected,
            true
        )
    ) {
        return true;
    }

    return basename($relativePath) === '.user.ini';
}

/**
 * 安装程序文件
 *
 * 升级包中的:
 *
 * config.php
 * .user.ini
 * admin/update.php
 * includes/updater.php
 *
 * 均不会覆盖服务器现有文件
 */
function update_install_files(
    string $packageRoot,
    string $siteRoot
): array {
    if (!is_dir($packageRoot)) {
        return [
            'success' => false,
            'message' =>
                '升级包目录不存在'
        ];
    }

    if (!is_dir($siteRoot)) {
        return [
            'success' => false,
            'message' =>
                '网站根目录不存在'
        ];
    }

    try {
        $iterator =
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $packageRoot,
                    FilesystemIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::SELF_FIRST
            );

        foreach (
            $iterator as $item
        ) {
            $sourcePath =
                $item->getPathname();

            $relativePath =
                substr(
                    $sourcePath,
                    strlen($packageRoot) + 1
                );

            $relativePath =
                str_replace(
                    DIRECTORY_SEPARATOR,
                    '/',
                    $relativePath
                );

            if (
                $relativePath === ''
            ) {
                continue;
            }

            if (
                update_is_protected_file(
                    $relativePath
                )
            ) {
                continue;
            }

            $destinationPath =
                $siteRoot
                . '/'
                . $relativePath;

            if ($item->isDir()) {
                if (
                    file_exists(
                        $destinationPath
                    )
                    && !is_dir(
                        $destinationPath
                    )
                ) {
                    if (
                        !@unlink(
                            $destinationPath
                        )
                    ) {
                        return [
                            'success' => false,
                            'message' =>
                                '无法删除冲突文件: '
                                . $relativePath
                        ];
                    }
                }

                if (
                    !update_ensure_directory(
                        $destinationPath
                    )
                ) {
                    return [
                        'success' => false,
                        'message' =>
                            '无法创建目录: '
                            . $relativePath
                    ];
                }

                continue;
            }

            $parent =
                dirname(
                    $destinationPath
                );

            if (
                !update_ensure_directory(
                    $parent
                )
            ) {
                return [
                    'success' => false,
                    'message' =>
                        '无法创建目录: '
                        . $relativePath
                ];
            }

            if (!is_readable($sourcePath)) {
                return [
                    'success' => false,
                    'message' =>
                        '无法读取升级文件: '
                        . $relativePath
                ];
            }

            if (
                file_exists(
                    $destinationPath
                )
            ) {
                if (
                    is_dir(
                        $destinationPath
                    )
                ) {
                    return [
                        'success' => false,
                        'message' =>
                            '目标位置存在同名目录，无法覆盖文件: '
                            . $relativePath
                    ];
                }

                if (
                    !is_writable(
                        $destinationPath
                    )
                ) {
                    return [
                        'success' => false,
                        'message' =>
                            '无法覆盖文件: '
                            . $relativePath
                            . '，目标文件没有写入权限'
                    ];
                }
            } elseif (
                !is_writable(
                    $parent
                )
            ) {
                return [
                    'success' => false,
                    'message' =>
                        '无法创建文件: '
                        . $relativePath
                        . '，目标目录没有写入权限'
                ];
            }

            if (
                !@copy(
                    $sourcePath,
                    $destinationPath
                )
            ) {
                return [
                    'success' => false,
                    'message' =>
                        '无法安装文件: '
                        . $relativePath
                ];
            }

            @chmod(
                $destinationPath,
                0644
            );
        }
    } catch (
        Throwable $e
    ) {
        return [
            'success' => false,
            'message' =>
                '安装文件时发生异常: '
                . $e->getMessage()
        ];
    }

    return [
        'success' => true,
        'message' =>
            '程序文件安装完成'
    ];
}

/**
 * 创建网站备份
 */
function update_backup_current_site(
    string $siteRoot,
    string $version
): array {
    $backupRoot =
        $siteRoot
        . '/storage/backups';

    if (
        !update_ensure_directory(
            $backupRoot
        )
    ) {
        return [
            'success' => false,
            'message' =>
                '无法创建备份目录'
        ];
    }

    $safeVersion =
        preg_replace(
            '/[^0-9A-Za-z._-]/',
            '_',
            $version
        );

    if (!is_string($safeVersion)) {
        $safeVersion =
            'unknown';
    }

    $backupDir =
        $backupRoot
        . '/'
        . $safeVersion
        . '-'
        . date('Ymd-His');

    if (
        !update_ensure_directory(
            $backupDir
        )
    ) {
        return [
            'success' => false,
            'message' =>
                '无法创建备份目录'
        ];
    }

    $filesDir =
        $backupDir
        . '/files';

    if (
        !update_ensure_directory(
            $filesDir
        )
    ) {
        return [
            'success' => false,
            'message' =>
                '无法创建文件备份目录'
        ];
    }

    $items =
        @scandir(
            $siteRoot
        );

    if (!is_array($items)) {
        return [
            'success' => false,
            'message' =>
                '无法读取网站目录'
        ];
    }

    foreach ($items as $item) {
        if (
            $item === '.'
            || $item === '..'
            || $item === 'storage'
        ) {
            continue;
        }

        if (
            update_is_protected_file(
                $item
            )
        ) {
            continue;
        }

        $source =
            $siteRoot
            . '/'
            . $item;

        $destination =
            $filesDir
            . '/'
            . $item;

        if (is_dir($source)) {
            $result =
                update_copy_directory(
                    $source,
                    $destination
                );
        } else {
            if (
                !@copy(
                    $source,
                    $destination
                )
            ) {
                return [
                    'success' => false,
                    'message' =>
                        '备份文件失败: '
                        . $item
                ];
            }

            @chmod(
                $destination,
                0644
            );

            $result = [
                'success' => true
            ];
        }

        if (
            !$result['success']
        ) {
            return [
                'success' => false,
                'message' =>
                    $result['message']
                    ?? '备份失败'
            ];
        }
    }

    return [
        'success' => true,
        'message' =>
            '当前版本备份完成',
        'path' =>
            $backupDir
    ];
}

/**
 * 恢复备份
 */
function update_restore_backup(
    string $backupDir,
    string $siteRoot
): array {
    $filesDir =
        $backupDir
        . '/files';

    if (!is_dir($filesDir)) {
        return [
            'success' => false,
            'message' =>
                '备份文件目录不存在'
        ];
    }

    $items =
        @scandir(
            $filesDir
        );

    if (!is_array($items)) {
        return [
            'success' => false,
            'message' =>
                '无法读取备份'
        ];
    }

    foreach ($items as $item) {
        if (
            $item === '.'
            || $item === '..'
        ) {
            continue;
        }

        if (
            update_is_protected_file(
                $item
            )
        ) {
            continue;
        }

        $source =
            $filesDir
            . '/'
            . $item;

        $destination =
            $siteRoot
            . '/'
            . $item;

        if (is_dir($source)) {
            if (
                file_exists(
                    $destination
                )
                && !is_dir(
                    $destination
                )
            ) {
                if (
                    !@unlink(
                        $destination
                    )
                ) {
                    return [
                        'success' => false,
                        'message' =>
                            '无法删除冲突文件: '
                            . $item
                    ];
                }
            }

            $result =
                update_copy_directory(
                    $source,
                    $destination
                );
        } else {
            $parent =
                dirname(
                    $destination
                );

            if (
                !update_ensure_directory(
                    $parent
                )
            ) {
                return [
                    'success' => false,
                    'message' =>
                        '无法创建恢复目录'
                ];
            }

            if (
                file_exists(
                    $destination
                )
            ) {
                if (
                    is_dir(
                        $destination
                    )
                ) {
                    if (
                        !update_delete_directory(
                            $destination
                        )
                    ) {
                        return [
                            'success' => false,
                            'message' =>
                                '无法删除冲突目录: '
                                . $item
                        ];
                    }
                } elseif (
                    !@unlink(
                        $destination
                    )
                ) {
                    return [
                        'success' => false,
                        'message' =>
                            '无法删除旧文件: '
                            . $item
                    ];
                }
            }

            $result = [
                'success' =>
                    @copy(
                        $source,
                        $destination
                    )
            ];

            if (
                $result['success']
            ) {
                @chmod(
                    $destination,
                    0644
                );
            }
        }

        if (
            !$result['success']
        ) {
            return [
                'success' => false,
                'message' =>
                    '恢复文件失败: '
                    . $item
            ];
        }
    }

    return [
        'success' => true,
        'message' =>
            '备份恢复完成'
    ];
}

/**
 * 更新 config.php 中的版本
 */
function update_write_version(
    string $version
): array {
    $configFile =
        dirname(__DIR__)
        . '/config.php';

    if (!is_file($configFile)) {
        return [
            'success' => false,
            'message' => '找不到 config.php'
        ];
    }

    $version = trim($version);

    if (
        $version === ''
        || !update_valid_version($version)
    ) {
        return [
            'success' => false,
            'message' => '版本号无效'
        ];
    }

    $content = @file_get_contents($configFile);

    if ($content === false) {
        return [
            'success' => false,
            'message' => '无法读取 config.php'
        ];
    }

    $pattern =
        "/(['\"]version['\"]\\s*=>\\s*)(['\"])([^'\"]*)(['\"])/";

    if (
        !preg_match(
            $pattern,
            $content,
            $matches
        )
    ) {
        return [
            'success' => false,
            'message' =>
                '无法定位 config.php 中的版本配置'
        ];
    }

    $newContent =
        preg_replace_callback(
            $pattern,
            static function (
                array $matches
            ) use (
                $version
            ): string {
                return
                    $matches[1]
                    . "'"
                    . addslashes($version)
                    . "'";
            },
            $content,
            1
        );

    if (!is_string($newContent)) {
        return [
            'success' => false,
            'message' =>
                '修改 config.php 失败'
        ];
    }

    if ($newContent === $content) {
        return [
            'success' => false,
            'message' =>
                'config.php 版本号没有发生变化'
        ];
    }

    $backupFile =
        $configFile
        . '.version.bak';

    if (
        !is_file($backupFile)
        && !@copy(
            $configFile,
            $backupFile
        )
    ) {
        return [
            'success' => false,
            'message' =>
                '无法创建 config.php 版本备份'
        ];
    }

    $tempFile =
        $configFile
        . '.version.tmp';

    if (
        @file_put_contents(
            $tempFile,
            $newContent,
            LOCK_EX
        ) === false
    ) {
        @unlink($tempFile);

        return [
            'success' => false,
            'message' =>
                '无法生成新的 config.php'
        ];
    }

    if (!@rename($tempFile, $configFile)) {
        @unlink($tempFile);

        if (
            @file_put_contents(
                $configFile,
                $newContent,
                LOCK_EX
            ) === false
        ) {
            return [
                'success' => false,
                'message' =>
                    '无法写入 config.php'
            ];
        }
    }

    clearstatcache(
        true,
        $configFile
    );

    $verifyContent =
        @file_get_contents(
            $configFile
        );

    $verifyPattern =
        "/(['\"]version['\"]\\s*=>\\s*)(['\"])([^'\"]*)(['\"])/";

    if (
        $verifyContent === false
        || !preg_match(
            $verifyPattern,
            $verifyContent,
            $verifyMatches
        )
        || !isset($verifyMatches[3])
        || $verifyMatches[3] !== $version
    ) {
        @copy(
            $backupFile,
            $configFile
        );

        @unlink($tempFile);

        return [
            'success' => false,
            'message' =>
                '版本写入验证失败，已恢复 config.php'
        ];
    }

    $backupContent =
        @file_get_contents(
            $backupFile
        );

    if ($backupContent !== false) {
        $backupPattern =
            "/(['\"]version['\"]\\s*=>\\s*)(['\"])([^'\"]*)(['\"])/";

        if (
            preg_match(
                $backupPattern,
                $backupContent,
                $backupMatches
            )
            && isset($backupMatches[3])
            && $backupMatches[3] !== $version
        ) {
            @unlink($backupFile);
        }
    }

    return [
        'success' => true,
        'message' =>
            '版本更新成功'
    ];
}

function update_health_check(): array
{
    $root =
        dirname(__DIR__);

    $required = [
        'config.php',
        'index.php'
    ];

    foreach (
        $required as $file
    ) {
        if (
            !is_file(
                $root
                . '/'
                . $file
            )
        ) {
            return [
                'success' => false,
                'message' =>
                    '健康检查失败，缺少文件: '
                    . $file
            ];
        }
    }

    if (
        !is_readable(
            $root
            . '/config.php'
        )
    ) {
        return [
            'success' => false,
            'message' =>
                '健康检查失败，config.php 不可读取'
        ];
    }

    return [
        'success' => true,
        'message' =>
            '健康检查通过'
    ];
}

/**
 * 向中央升级中心上报升级结果
 *
 * 注意:
 * 上报失败绝不能影响本地升级结果
 */
function update_report_upgrade_log(
    string $fromVersion,
    string $toVersion,
    string $status,
    string $message
): void {
    if (
        $fromVersion === ''
        || $toVersion === ''
    ) {
        update_log(
            '升级日志上报跳过: 版本号为空'
        );

        return;
    }

    if (
        $status !== 'success'
        && $status !== 'failed'
    ) {
        update_log(
            '升级日志上报跳过: 状态无效'
        );

        return;
    }

    if (
        !function_exists('curl_init')
    ) {
        update_log(
            '升级日志上报失败: cURL 不可用'
        );

        return;
    }

    $token =
        update_token();

    if (
        $token === ''
        || str_starts_with(
            $token,
            'CHANGE_THIS'
        )
    ) {
        update_log(
            '升级日志上报跳过: Token 未配置'
        );

        return;
    }

    $payload = [
        'from_version' =>
            $fromVersion,
        'to_version' =>
            $toVersion,
        'status' =>
            $status,
        'message' =>
            mb_substr(
                $message,
                0,
                2000
            )
    ];

    $json =
        update_json_encode(
            $payload
        );

    $url =
        'https://update.azhai.de/api/upgrade_log.php';

    $ch =
        curl_init();

    if ($ch === false) {
        update_log(
            '升级日志上报失败: 无法初始化 cURL'
        );

        return;
    }

    curl_setopt_array(
        $ch,
        [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTP_VERSION =>
                CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
                'Connection: close',
                'Cache-Control: no-cache'
            ],
            CURLOPT_USERAGENT =>
                'AZhai-Sub-Updater/'
                . update_current_version()
        ]
    );

    $response =
        curl_exec($ch);

    $error =
        curl_error($ch);

    $statusCode =
        (int) curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

    curl_close($ch);

    if (
        $response === false
    ) {
        update_log(
            '升级日志上报失败: '
            . (
                $error !== ''
                    ? $error
                    : 'HTTP 请求失败'
            )
        );

        return;
    }

    if (
        $statusCode < 200
        || $statusCode >= 300
    ) {
        update_log(
            '升级日志上报失败: HTTP '
            . $statusCode
        );

        return;
    }

    update_log(
        '升级日志上报成功: '
        . $fromVersion
        . ' -> '
        . $toVersion
        . ' / '
        . $status
    );
}

/**
 * 执行实际安装流程
 *
 * 注意:
 * 这里不负责向中央服务器上报
 */
function update_install_execute(
    array $release
): array {
    $siteRoot =
        dirname(__DIR__);

    $currentVersion =
        update_current_version();

    $targetVersion =
        trim(
            (string) (
                $release['version']
                ?? ''
            )
        );

    if (
        $currentVersion === ''
    ) {
        return [
            'success' => false,
            'message' =>
                '当前版本号为空'
        ];
    }

    if (
        $targetVersion === ''
    ) {
        return [
            'success' => false,
            'message' =>
                '目标版本号为空'
        ];
    }

    if (
        !update_valid_version(
            $targetVersion
        )
    ) {
        return [
            'success' => false,
            'message' =>
                '目标版本号无效'
        ];
    }

    if (
        update_compare_versions(
            $targetVersion,
            $currentVersion
        ) <= 0
    ) {
        return [
            'success' => false,
            'message' =>
                '目标版本不高于当前版本'
        ];
    }

    update_log(
        '开始升级: '
        . $currentVersion
        . ' -> '
        . $targetVersion
    );

    $packageResult =
        update_download(
            $release
        );

    if (
        !$packageResult['success']
    ) {
        return [
            'success' => false,
            'message' =>
                $packageResult['message']
                ?? '升级包下载失败'
        ];
    }

    $packagePath =
        (string) (
            $packageResult['path']
            ?? ''
        );

    if (
        $packagePath === ''
        || !is_file(
            $packagePath
        )
    ) {
        return [
            'success' => false,
            'message' =>
                '升级包文件不存在'
        ];
    }

    update_log(
        '升级包下载完成'
    );

    $zipCheck =
        update_validate_zip(
            $packagePath
        );

    if (
        !$zipCheck['success']
    ) {
        return [
            'success' => false,
            'message' =>
                $zipCheck['message']
                ?? '升级包检查失败'
        ];
    }

    update_log(
        '升级包安全检查通过'
    );

    $backup =
        update_backup_current_site(
            $siteRoot,
            $currentVersion
        );

    if (
        !$backup['success']
    ) {
        return [
            'success' => false,
            'message' =>
                $backup['message']
                ?? '备份失败'
        ];
    }

    $backupPath =
        (string) (
            $backup['path']
            ?? ''
        );

    update_log(
        '当前版本备份完成'
    );

    $tempRoot =
        $siteRoot
        . '/storage/update/extract-'
        . date('Ymd-His')
        . '-'
        . bin2hex(
            random_bytes(4)
        );

    if (
        !update_ensure_directory(
            $tempRoot
        )
    ) {
        return [
            'success' => false,
            'message' =>
                '无法创建解压目录'
        ];
    }

    try {
        $extract =
            update_extract_zip(
                $packagePath,
                $tempRoot
            );

        if (
            !$extract['success']
        ) {
            throw new RuntimeException(
                (string) (
                    $extract['message']
                    ?? '解压失败'
                )
            );
        }

        update_log(
            '升级包解压完成'
        );

        $packageRoot =
            update_detect_package_root(
                $tempRoot
            );

        $install =
            update_install_files(
                $packageRoot,
                $siteRoot
            );

        if (
            !$install['success']
        ) {
            throw new RuntimeException(
                (string) (
                    $install['message']
                    ?? '文件安装失败'
                )
            );
        }

        update_log(
            '程序文件安装完成'
        );

        $health =
            update_health_check();

        if (
            !$health['success']
        ) {
            throw new RuntimeException(
                (string) (
                    $health['message']
                    ?? '健康检查失败'
                )
            );
        }

        update_log(
            '健康检查通过'
        );

        $versionResult =
            update_write_version(
                $targetVersion
            );

        if (
            !$versionResult['success']
        ) {
            throw new RuntimeException(
                (string) (
                    $versionResult['message']
                    ?? '版本号更新失败'
                )
            );
        }

        update_log(
            '当前版本已更新为 '
            . $targetVersion
        );

        return [
            'success' => true,
            'message' =>
                '升级成功',
            'from_version' =>
                $currentVersion,
            'to_version' =>
                $targetVersion,
            'version' =>
                $targetVersion,
            'backup_path' =>
                $backupPath
        ];
    } catch (
        Throwable $e
    ) {
        update_log(
            '升级失败: '
            . $e->getMessage()
        );

        if (
            $backupPath !== ''
            && is_dir($backupPath)
        ) {
            update_log(
                '开始恢复升级前备份'
            );

            $restore =
                update_restore_backup(
                    $backupPath,
                    $siteRoot
                );

            if (
                $restore['success']
            ) {
                update_log(
                    '升级前备份恢复成功'
                );
            } else {
                update_log(
                    '升级前备份恢复失败: '
                    . (
                        $restore['message']
                        ?? '未知错误'
                    )
                );
            }
        }

        return [
            'success' => false,
            'message' =>
                $e->getMessage(),
            'from_version' =>
                $currentVersion,
            'to_version' =>
                $targetVersion,
            'backup_path' =>
                $backupPath
        ];
    } finally {
        if (
            is_dir($tempRoot)
        ) {
            update_delete_directory(
                $tempRoot
            );
        }

        if (
            is_file($packagePath)
        ) {
            @unlink(
                $packagePath
            );
        }
    }
}

/**
 * 执行升级
 *
 * 这里统一负责向中央升级中心上报
 * 每次调用只会上报一次
 */
function update_install(
    array $release
): array {
    $currentVersion =
        update_current_version();

    $targetVersion =
        trim(
            (string) (
                $release['version']
                ?? ''
            )
        );

    try {
        $result =
            update_install_execute(
                $release
            );

        $success =
            !empty(
                $result['success']
            );

        $status =
            $success
                ? 'success'
                : 'failed';

        $message =
            trim(
                (string) (
                    $result['message']
                    ?? (
                        $success
                            ? '升级成功'
                            : '升级失败'
                    )
                )
            );

        update_report_upgrade_log(
            $currentVersion,
            $targetVersion,
            $status,
            $message
        );

        return $result;
    } catch (
        Throwable $e
    ) {
        $message =
            $e->getMessage();

        update_log(
            '升级流程异常: '
            . $message
        );

        update_report_upgrade_log(
            $currentVersion,
            $targetVersion,
            'failed',
            $message
        );

        return [
            'success' => false,
            'message' =>
                $message,
            'from_version' =>
                $currentVersion,
            'to_version' =>
                $targetVersion
        ];
    }
}
