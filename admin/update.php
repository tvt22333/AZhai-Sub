<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/updater.php';

require_admin();

$pageTitle = '系统更新';

$result = null;
$error = '';
$checked = false;
$installed = false;
$installResult = null;

$updateSettingsFile =
    dirname(__DIR__)
    . '/storage/update_settings.json';

$autoCheck = false;

if (is_file($updateSettingsFile)) {
    $settingsJson = @file_get_contents($updateSettingsFile);

    if ($settingsJson !== false) {
        $settings = json_decode($settingsJson, true);

        if (is_array($settings)) {
            $autoCheck = !empty($settings['auto_check']);
        }
    }
}

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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfValid = hash_equals(
        (string) ($_SESSION['csrf_token'] ?? ''),
        (string) ($_POST['csrf_token'] ?? '')
    );

    if (!$csrfValid) {

        $error = '请求验证失败，请刷新页面后重试';

    } else {

        $action = trim(
            (string) (
                $_POST['action'] ?? ''
            )
        );

        if (
            $action === ''
            && isset($_POST['release'])
        ) {
            $action = 'install';
        }

        if ($action === 'save_settings') {

            $autoCheck = !empty($_POST['auto_check']);

            $storageDir = dirname($updateSettingsFile);

            if (
                !is_dir($storageDir)
                && !@mkdir($storageDir, 0755, true)
                && !is_dir($storageDir)
            ) {
                $error = '无法创建升级设置目录';
            } else {
                $settings = [
                    'auto_check' => $autoCheck,
                ];

                $json = json_encode(
                    $settings,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_PRETTY_PRINT
                );

                if (
                    $json === false
                    || @file_put_contents(
                        $updateSettingsFile,
                        $json,
                        LOCK_EX
                    ) === false
                ) {
                    $error = '升级设置保存失败';
                }
            }

        } elseif ($action === 'check') {

            $checked = true;

            $result = update_check();

            if (
                !is_array($result)
                || empty($result['success'])
            ) {
                $error = is_array($result)
                    ? (string) (
                        $result['message']
                        ?? '检查更新失败'
                    )
                    : '检查更新失败';
            }

        } elseif ($action === 'install') {

            $releaseJson = (string) (
                $_POST['release'] ?? ''
            );

            $release = json_decode(
                $releaseJson,
                true
            );

            if (!is_array($release)) {

                $error = '升级信息无效';

            } else {

                $installResult = update_install(
                    $release
                );

                if (
                    is_array($installResult)
                    && !empty($installResult['success'])
                ) {

                    $installed = true;

                    $config = require __DIR__ .
                        '/../config.php';

                } else {

                    $error = is_array($installResult)
                        ? (string) (
                            $installResult['message']
                            ?? '升级失败'
                        )
                        : '升级失败';
                }
            }

        } else {

            $error = '无效的请求';
        }
    }
}

$currentVersion = (string) (
    $config['version'] ?? '1.0.0'
);

$updateConfig = $config['update'] ?? [];

if (!is_array($updateConfig)) {
    $updateConfig = [];
}

$channel = (string) (
    $updateConfig['channel'] ?? 'stable'
);

$updateEnabled = update_is_enabled();

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    && $autoCheck
    && $updateEnabled
) {
    $checked = true;

    $result = update_check();

    if (
        !is_array($result)
        || empty($result['success'])
    ) {
        $error = is_array($result)
            ? (string) (
                $result['message']
                ?? '检查更新失败'
            )
            : '检查更新失败';
    }
}

$release = [];

if (
    !$installed
    && $checked
    && is_array($result)
    && !empty($result['success'])
    && !empty($result['available'])
    && is_array($result['release'] ?? null)
) {
    $release = $result['release'];
}

$hasUpdate = $release !== [];

$latestVersion = $currentVersion;

if (
    $hasUpdate
    && isset($release['version'])
) {
    $latestVersion = (string) $release['version'];
}

require __DIR__ . '/../includes/admin_header.php';

?>

<div class="admin-card update-page">

    <div class="update-header">

        <div>

            <h1>系统更新</h1>

            <p>
                从阿宅升级中心检查并安装
                AZhai Sub 的最新版本。
            </p>

        </div>

    </div>

    <?php if ($installed): ?>

        <div class="update-message update-success">

            <strong>
                升级成功
            </strong>

            <p>
                AZhai Sub 已成功升级到
                <?= e(
                    (string) (
                        $installResult['version']
                        ?? $currentVersion
                    )
                ) ?>
            </p>

        </div>

    <?php endif; ?>

    <div class="update-info-grid">

        <div class="update-info-item">

            <span>
                当前版本
            </span>

            <strong>
                <?= e($currentVersion) ?>
            </strong>

        </div>

        <div class="update-info-item">

            <span>
                项目
            </span>

            <strong>
                <?= e(
                    (string) (
                        $updateConfig['product']
                        ?? 'azhai-sub'
                    )
                ) ?>
            </strong>

        </div>

        <div class="update-info-item">

            <span>
                更新通道
            </span>

            <strong>
                <?= e($channel) ?>
            </strong>

        </div>

        <div class="update-info-item">

            <span>
                更新中心
            </span>

            <strong>
                update.azhai.de
            </strong>

        </div>

    </div>

    <?php if (!$updateEnabled): ?>

        <div class="update-message update-warning">

            <strong>
                升级中心尚未配置
            </strong>

            <p>
                请先在 config.php 中填写
                AZhai Sub 的升级中心 API Token。
            </p>

        </div>

    <?php endif; ?>

    <?php if ($error !== ''): ?>

        <div class="update-message update-error">

            <strong>
                升级失败
            </strong>

            <p>
                <?= e($error) ?>
            </p>

        </div>

    <?php endif; ?>

    <?php if (
        !$installed
        && $checked
        && is_array($result)
        && !empty($result['success'])
        && $error === ''
    ): ?>

        <?php if (!$hasUpdate): ?>

            <div class="update-message update-success">

                <strong>
                    当前已经是最新版本
                </strong>

                <p>
                    当前版本
                    <?= e($currentVersion) ?>
                    暂无可用更新。
                </p>

            </div>

        <?php else: ?>

            <div class="update-release">

                <div class="update-release-top">

                    <div>

                        <span class="update-badge">
                            有新版本
                        </span>

                        <h2>
                            <?= e($latestVersion) ?>
                        </h2>

                    </div>

                    <div class="update-version-arrow">

                        <?= e($currentVersion) ?>

                        →

                        <?= e($latestVersion) ?>

                    </div>

                </div>

                <div class="update-release-meta">

                    <div>

                        <span>
                            发布渠道
                        </span>

                        <strong>
                            <?= e(
                                (string) (
                                    $release['channel']
                                    ?? $channel
                                )
                            ) ?>
                        </strong>

                    </div>

                    <div>

                        <span>
                            发布时间
                        </span>

                        <strong>
                            <?= e(
                                (string) (
                                    $release['released_at']
                                    ?? '-'
                                )
                            ) ?>
                        </strong>

                    </div>

                    <div>

                        <span>
                            升级包
                        </span>

                        <strong>
                            <?= update_format_size(
                                (int) (
                                    $release['file_size']
                                    ?? 0
                                )
                            ) ?>
                        </strong>

                    </div>

                </div>

                <div class="update-release-notes">

                    <h3>
                        更新内容
                    </h3>

                    <div>
                        <?= nl2br(
                            e(
                                (string) (
                                    $release['release_notes']
                                    ?? '暂无更新说明'
                                )
                            )
                        ) ?>
                    </div>

                </div>

                <div class="update-release-requirements">

                    <?php if (
                        !empty(
                            $release['min_php_version']
                            ?? ''
                        )
                    ): ?>

                        <span>

                            PHP ≥

                            <?= e(
                                (string) (
                                    $release['min_php_version']
                                )
                            ) ?>

                        </span>

                    <?php endif; ?>

                    <?php if (
                        !empty(
                            $release['min_mysql_version']
                            ?? ''
                        )
                    ): ?>

                        <span>

                            MySQL ≥

                            <?= e(
                                (string) (
                                    $release['min_mysql_version']
                                )
                            ) ?>

                        </span>

                    <?php endif; ?>

                    <?php if (
                        !empty(
                            $release['requires_migration']
                            ?? false
                        )
                    ): ?>

                        <span>
                            需要数据库迁移
                        </span>

                    <?php endif; ?>

                    <?php if (
                        !empty(
                            $release['force_update']
                            ?? false
                        )
                    ): ?>

                        <span>
                            强制更新
                        </span>

                    <?php endif; ?>

                </div>

                <div class="update-protection">

                    <span>
                        🔒 config.php 不会被覆盖
                    </span>

                    <span>
                        仅更新服务器现有 config.php 的 version
                    </span>

                </div>

                <div class="update-release-actions">

                    <form
                        method="post"
                        action="/admin/update.php"
                        onsubmit="return confirm('确定要立即安装这个版本吗？升级过程中请不要关闭页面或刷新页面');"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(
                                (string) (
                                    $_SESSION['csrf_token']
                                    ?? ''
                                )
                            ) ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="install"
                        >

                        <input
                            type="hidden"
                            name="release"
                            value="<?= e(
                                json_encode(
                                    $release,
                                    JSON_UNESCAPED_UNICODE
                                    | JSON_UNESCAPED_SLASHES
                                    | JSON_HEX_TAG
                                    | JSON_HEX_AMP
                                    | JSON_HEX_APOS
                                    | JSON_HEX_QUOT
                                )
                            ) ?>"
                        >

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            立即更新
                        </button>

                    </form>

                </div>

            </div>

        <?php endif; ?>

    <?php endif; ?>

    <?php if (!$installed): ?>

        <div class="update-settings">

            <form
                method="post"
                action="/admin/update.php"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(
                        (string) (
                            $_SESSION['csrf_token']
                            ?? ''
                        )
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="save_settings"
                >

                <label class="update-checkbox">

                    <input
                        type="checkbox"
                        name="auto_check"
                        value="1"
                        <?= $autoCheck
                            ? 'checked'
                            : '' ?>
                        onchange="this.form.submit()"
                    >

                    <span>
                        自动检测更新
                    </span>

                </label>

                <p>
                    开启后，进入系统更新页面时会自动检测新版本，但不会自动安装更新。
                </p>

            </form>

        </div>

        <div class="update-actions">

            <form
                method="post"
                action="/admin/update.php"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(
                        (string) (
                            $_SESSION['csrf_token']
                            ?? ''
                        )
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="check"
                >

                <button
                    type="submit"
                    class="btn btn-primary"
                    <?= !$updateEnabled
                        ? 'disabled'
                        : '' ?>
                >
                    检查更新
                </button>

            </form>

        </div>

    <?php endif; ?>

</div>

<style>

.update-page {
    max-width: 1000px;
    margin: 0 auto;
}

.update-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 30px;
}

.update-header h1 {
    margin: 0 0 8px;
}

.update-header p {
    margin: 0;
    color: #999;
}

.update-info-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.update-info-item {
    padding: 18px;
    border: 1px solid #f1e8ed;
    border-radius: 14px;
    background: #fff;
}

.update-info-item span {
    display: block;
    margin-bottom: 7px;
    color: #999;
    font-size: 12px;
}

.update-info-item strong {
    color: #333;
    font-size: 15px;
}

.update-message {
    margin-bottom: 25px;
    padding: 18px 20px;
    border-radius: 14px;
}

.update-message strong {
    display: block;
    margin-bottom: 6px;
}

.update-message p {
    margin: 0;
    color: #777;
}

.update-success {
    background: #f4fff8;
    border: 1px solid #d9f3e3;
}

.update-warning {
    background: #fffaf0;
    border: 1px solid #f6e6bd;
}

.update-error {
    background: #fff5f7;
    border: 1px solid #f4d5dc;
}

.update-release {
    margin-bottom: 25px;
    padding: 25px;
    border: 1px solid #f1e8ed;
    border-radius: 18px;
    background: #fff;
}

.update-release-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #f5edf1;
}

.update-badge {
    display: inline-block;
    margin-bottom: 8px;
    padding: 5px 10px;
    border-radius: 999px;
    background: #fff0f6;
    color: #ff6fa8;
    font-size: 12px;
}

.update-release h2 {
    margin: 0;
    font-size: 30px;
}

.update-version-arrow {
    color: #999;
    font-size: 14px;
}

.update-release-meta {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
    padding: 20px 0;
}

.update-release-meta span {
    display: block;
    margin-bottom: 6px;
    color: #aaa;
    font-size: 12px;
}

.update-release-meta strong {
    color: #444;
    font-size: 14px;
}

.update-release-notes {
    padding: 20px;
    border-radius: 14px;
    background: #faf8f9;
}

.update-release-notes h3 {
    margin: 0 0 12px;
    font-size: 15px;
}

.update-release-notes div {
    color: #666;
    line-height: 1.8;
}

.update-release-requirements {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 18px;
}

.update-release-requirements span {
    padding: 6px 10px;
    border-radius: 8px;
    background: #f6f6f6;
    color: #777;
    font-size: 12px;
}

.update-protection {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 18px;
}

.update-protection span {
    padding: 8px 12px;
    border-radius: 10px;
    background: #fff7fb;
    border: 1px solid #f5dce8;
    color: #b45c83;
    font-size: 12px;
}

.update-release-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 22px;
}

.update-actions {
    display: flex;
    justify-content: flex-start;
    padding-top: 5px;
}

@media (max-width: 800px) {

    .update-info-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .update-release-top {
        align-items: flex-start;
        flex-direction: column;
    }

    .update-release-meta {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 500px) {

    .update-info-grid {
        grid-template-columns: 1fr;
    }

    .update-release {
        padding: 18px;
    }

    .update-release h2 {
        font-size: 25px;
    }

}

        .update-settings {
            margin-top: 20px;
            padding: 18px 20px;
            border: 1px solid rgba(255, 105, 160, 0.16);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.72);
        }

        .update-checkbox {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-weight: 600;
        }

        .update-checkbox input {
            width: 18px;
            height: 18px;
            margin: 0;
            accent-color: #ff6fa8;
            cursor: pointer;
        }

        .update-settings p {
            margin: 8px 0 0;
            color: #888;
            font-size: 13px;
        }

</style>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>