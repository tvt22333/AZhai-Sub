<?php

declare(strict_types=1);

$config =
    require __DIR__ .
    '/../config.php';

require_once __DIR__ .
    '/../includes/database.php';

require_once __DIR__ .
    '/../includes/functions.php';

require_once __DIR__ .
    '/../includes/auth.php';

require_once __DIR__ .
    '/../includes/csrf.php';

require_once __DIR__ .
    '/../includes/dns/DnsManager.php';

require_login();

$pdo = db();

$userId =
    current_user_id();

$domainId =
    (int)($_GET['id'] ?? 0);

if ($domainId <= 0) {

    flash(
        'error',
        '无效的域名'
    );

    redirect(
        '/dashboard/'
    );
}




$stmt = $pdo->prepare("
    SELECT
    id,
    user_id,
    provider_domain_id,
    subdomain,
    full_domain,
    status,
    created_at,
    updated_at
FROM domains
    WHERE id = ?
      AND user_id = ?
      AND status <> 'deleted'
    LIMIT 1
");

$stmt->execute([
    $domainId,
    $userId
]);

$domain =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );

if (!$domain) {

    flash(
        'error',
        '域名不存在，或者你没有权限访问'
    );

    redirect(
        '/dashboard/'
    );
}




$statusLabels = [
    'pending' =>
        '审核中',

    'active' =>
        '正常',

    'suspended' =>
        '已暂停',

    'deleted' =>
        '已删除',
];

$statusClasses = [
    'pending' =>
        'warning',

    'active' =>
        'success',

    'suspended' =>
        'danger',

    'deleted' =>
        'muted',
];

$status =
    $domain['status'];




$allowedTypes = [
    'A',
    'AAAA',
    'CNAME',
    'TXT',
];




$providerName = null;

$providerType = null;

$providerVersionName = null;

$providerVersionCode = null;

$providerMinTtl = 600;

$allowedTtls = [
    600,
    1800,
    3600,
    7200,
    10800,
    21600,
    43200,
    86400,
];

try {

    $dns =
    new DnsManager(
        $pdo,
        (string)$config['app_key']
    );

    $providerInfo =
        $dns->getProviderInfo();

    $providerName =
        $providerInfo['name']
        ?? null;

    $providerType =
        $providerInfo['type']
        ?? null;

    $providerData =
        $providerInfo['info']
        ?? [];

    $providerVersionName =
        $providerData[
            'version_name'
        ]
        ?? null;

    $providerVersionCode =
        $providerData[
            'version_code'
        ]
        ?? null;

    $providerMinTtl =
        (int)(
            $providerData[
                'min_ttl'
            ]
            ?? 600
        );

    if ($providerMinTtl <= 0) {
        $providerMinTtl = 600;
    }

    if (
        !empty(
            $providerData[
                'available_ttls'
            ]
        )
        &&
        is_array(
            $providerData[
                'available_ttls'
            ]
        )
    ) {

        $allowedTtls = [];

        foreach (
            $providerData[
                'available_ttls'
            ]
            as $ttl
        ) {

            $ttl =
                (int)$ttl;

            if (
                $ttl >=
                $providerMinTtl
            ) {

                $allowedTtls[] =
                    $ttl;
            }
        }

        $allowedTtls =
            array_values(
                array_unique(
                    $allowedTtls
                )
            );

        sort(
            $allowedTtls
        );
    }

} catch (Throwable $e) {

    error_log(
        '[AZhai Sub] DNS provider info: ' .
        $e->getMessage()
    );

    $providerName = null;
    $providerType = null;

    $providerVersionName = null;
    $providerVersionCode = null;

    $providerMinTtl = 600;

    $allowedTtls = [
        600,
        1800,
        3600,
        7200,
        10800,
        21600,
        43200,
        86400,
    ];
}




$ttlLabels = [
    1 =>
        '1 秒',

    5 =>
        '5 秒',

    10 =>
        '10 秒',

    30 =>
        '30 秒',

    60 =>
        '1 分钟',

    120 =>
        '2 分钟',

    300 =>
        '5 分钟',

    600 =>
        '10 分钟',

    1800 =>
        '30 分钟',

    3600 =>
        '1 小时',

    7200 =>
        '2 小时',

    10800 =>
        '3 小时',

    21600 =>
        '6 小时',

    43200 =>
        '12 小时',

    86400 =>
        '1 天',
];

$ttlOptions = [];

foreach (
    $allowedTtls
    as $ttl
) {

    $ttl =
        (int)$ttl;

    if (
        $ttl <
        $providerMinTtl
    ) {
        continue;
    }

    $ttlOptions[$ttl] =
        $ttlLabels[$ttl]
        ?? ($ttl . ' 秒');
}

if (empty($ttlOptions)) {

    $ttlOptions = [
        600 =>
            '10 分钟',
    ];
}




$action =
    $_POST['action'] ?? '';

$formType =
    'A';

$formName =
    '@';

$formContent =
    '';

$formTtl =
    (int)array_key_first(
        $ttlOptions
    );

$formProxied =
    0;

$editingRecord =
    null;




if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    &&
    isset($_GET['edit'])
) {

    $editId =
        (int)$_GET['edit'];

    if ($editId > 0) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM domain_records
            WHERE id = ?
              AND domain_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $editId,
            $domainId
        ]);

        $editingRecord =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if ($editingRecord) {

            $formType =
                $editingRecord['type'];

            $formName =
                $editingRecord['name'];

            $formContent =
                $editingRecord['content'];

            $formTtl =
                (int)$editingRecord['ttl'];

            $formProxied =
                (int)$editingRecord['proxied'];

            
            if (
                !isset(
                    $ttlOptions[
                        $formTtl
                    ]
                )
            ) {

                $ttlOptions[
                    $formTtl
                ] =
                    $ttlLabels[
                        $formTtl
                    ]
                    ??
                    (
                        $formTtl .
                        ' 秒'
                    );
            }
        }
    }
}




if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    verify_csrf();

    $action =
        (string)(
            $_POST['action']
            ?? ''
        );


    
    if (
        $action === 'create'
        ||
        $action === 'update'
    ) {

        $formType =
            strtoupper(
                trim(
                    (string)(
                        $_POST['type']
                        ?? ''
                    )
                )
            );

        $formName =
            trim(
                (string)(
                    $_POST['name']
                    ?? '@'
                )
            );

        $formContent =
            trim(
                (string)(
                    $_POST['content']
                    ?? ''
                )
            );

        $formTtl =
            (int)(
                $_POST['ttl']
                ??
                array_key_first(
                    $ttlOptions
                )
            );

        $formProxied =
            !empty(
                $_POST['proxied']
            )
                ? 1
                : 0;


        
        if (
            !in_array(
                $formType,
                $allowedTypes,
                true
            )
        ) {

            flash(
                'error',
                '不支持的 DNS 记录类型'
            );

            redirect(
                '/dashboard/domains.php?id=' .
                $domainId
            );
        }


        
        if (
            $formName === ''
        ) {

            $formName =
                '@';
        }


        
        if (
            str_contains(
                $formName,
                '://'
            )
            ||
            str_contains(
                $formName,
                '/'
            )
        ) {

            flash(
                'error',
                '记录名称格式不正确'
            );

            redirect(
                '/dashboard/domains.php?id=' .
                $domainId
            );
        }


        if (
            strlen($formName)
            > 255
        ) {

            flash(
                'error',
                '记录名称不能超过 255 个字符'
            );

            redirect(
                '/dashboard/domains.php?id=' .
                $domainId
            );
        }


        
        if (
            $formContent === ''
        ) {

            flash(
                'error',
                '请输入记录内容'
            );

            redirect(
                '/dashboard/domains.php?id=' .
                $domainId
            );
        }


        if (
            strlen($formContent)
            > 255
        ) {

            flash(
                'error',
                '记录内容不能超过 255 个字符'
            );

            redirect(
                '/dashboard/domains.php?id=' .
                $domainId
            );
        }


        
        if (
            !in_array(
                $formTtl,
                $allowedTtls,
                true
            )
        ) {

            flash(
                'error',
                'TTL 无效，请选择当前 DNS 服务商支持的 TTL'
            );

            redirect(
                '/dashboard/domains.php?id=' .
                $domainId
            );
        }


        
        if (
            $formType === 'A'
        ) {

            if (
                filter_var(
                    $formContent,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4
                ) === false
            ) {

                flash(
                    'error',
                    'A 记录必须填写有效的 IPv4 地址'
                );

                redirect(
                    '/dashboard/domains.php?id=' .
                    $domainId
                );
            }
        }


        
        if (
            $formType === 'AAAA'
        ) {

            if (
                filter_var(
                    $formContent,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV6
                ) === false
            ) {

                flash(
                    'error',
                    'AAAA 记录必须填写有效的 IPv6 地址'
                );

                redirect(
                    '/dashboard/domains.php?id=' .
                    $domainId
                );
            }
        }


        
        if (
            $formType === 'CNAME'
        ) {

            if (
                preg_match(
                    '/^[a-zA-Z0-9.-]+$/',
                    $formContent
                ) !== 1
            ) {

                flash(
                    'error',
                    'CNAME 目标格式不正确'
                );

                redirect(
                    '/dashboard/domains.php?id=' .
                    $domainId
                );
            }
        }


        
        if (
            $formType === 'TXT'
        ) {

            if (
                strlen($formContent)
                > 255
            ) {

                flash(
                    'error',
                    'TXT 记录不能超过 255 个字符'
                );

                redirect(
                    '/dashboard/domains.php?id=' .
                    $domainId
                );
            }
        }


        
        if (
            $providerType !==
            'cloudflare'
        ) {

            $formProxied =
                0;
        }


        
        if (
            $formType === 'TXT'
        ) {

            $formProxied =
                0;
        }


        
        if (
            $formName === '@'
            ||
            $formName === ''
        ) {

            $fqdn =
                $domain['full_domain'];

        } else {

            $fqdn =
                $formName .
                '.' .
                $domain['full_domain'];
        }


        try {

            $dns =
    new DnsManager(
        $pdo,
        (string)$config['app_key']
    );


            
            if (
                $action === 'create'
            ) {

                
                $stmt =
                    $pdo->prepare("
                        SELECT id
                        FROM domain_records
                        WHERE domain_id = ?
                          AND type = ?
                          AND name = ?
                        LIMIT 1
                    ");

                $stmt->execute([
                    $domainId,
                    $formType,
                    $formName
                ]);

                if (
                    $stmt->fetch()
                ) {

                    throw new RuntimeException(
                        '同类型、同名称的 DNS 记录已经存在'
                    );
                }


                
                $providerDomainId =
    (int)(
        $domain['provider_domain_id']
        ?? 0
    );

if ($providerDomainId <= 0) {

    throw new RuntimeException(
        '当前域名没有绑定 DNS 根域名'
    );
}

$result =
    $dns->createRecordByDomainId(
        $providerDomainId,
        $fqdn,
        $formType,
        $formContent,
        $formTtl,
        (bool)$formProxied
    );

if (
    empty($result['success'])
) {

    throw new RuntimeException(
        (string)(
            $result['message']
            ?? 'DNS 记录创建失败'
        )
    );
}
$providerRecordId =
    trim(
        (string)(
            $result['record_id']
            ?? ''
        )
    );

if (
    $providerRecordId === ''
) {

    throw new RuntimeException(
        'DNS 记录创建成功, 但未获取到远程记录 ID'
    );
}
                
                $stmt =
                    $pdo->prepare("
                        INSERT INTO domain_records
                        (
                            domain_id,
                            type,
                            name,
                            content,
                            ttl,
                            proxied,
                            provider_record_id,
                            created_at,
                            updated_at
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            CURRENT_TIMESTAMP,
                            CURRENT_TIMESTAMP
                        )
                    ");

                $stmt->execute([
                    $domainId,
                    $formType,
                    $formName,
                    $formContent,
                    $formTtl,
                    $formProxied,
                    $result['record_id']
                    ?? null,
                ]);


                
                log_action(
                    $userId,
                    null,
                    'add_record',
                    $fqdn
                );


                flash(
                    'success',
                    'DNS 记录添加成功'
                );

            } else {

                
                $editId =
                    (int)(
                        $_POST['record_id']
                        ?? 0
                    );

                if (
                    $editId <= 0
                ) {

                    throw new RuntimeException(
                        '无效的 DNS 记录'
                    );
                }


                $stmt =
                    $pdo->prepare("
                        SELECT *
                        FROM domain_records
                        WHERE id = ?
                          AND domain_id = ?
                        LIMIT 1
                    ");

                $stmt->execute([
                    $editId,
                    $domainId
                ]);

                $record =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );

                if (!$record) {

                    throw new RuntimeException(
                        'DNS 记录不存在'
                    );
                }


                
                $stmt =
                    $pdo->prepare("
                        SELECT id
                        FROM domain_records
                        WHERE domain_id = ?
                          AND type = ?
                          AND name = ?
                          AND id <> ?
                        LIMIT 1
                    ");

                $stmt->execute([
                    $domainId,
                    $formType,
                    $formName,
                    $editId
                ]);

                if (
                    $stmt->fetch()
                ) {

                    throw new RuntimeException(
                        '同类型、同名称的 DNS 记录已经存在'
                    );
                }


                
                $providerDomainId =
    (int)(
        $domain['provider_domain_id']
        ?? 0
    );

if ($providerDomainId <= 0) {

    throw new RuntimeException(
        '当前域名没有绑定 DNS 根域名'
    );
}

$result =
    $dns->updateRecordByDomain(
        $providerDomainId,
        (string)(
            $record['provider_record_id']
            ?? ''
        ),
        $fqdn,
        $formType,
        $formContent,
        $formTtl,
        (bool)$formProxied
    );

if (
    empty($result['success'])
) {

    throw new RuntimeException(
        (string)(
            $result['message']
            ?? 'DNS 记录修改失败'
        )
    );
}


                
                $stmt =
                    $pdo->prepare("
                        UPDATE domain_records
                        SET
                            type = ?,
                            name = ?,
                            content = ?,
                            ttl = ?,
                            proxied = ?,
                            updated_at =
                                CURRENT_TIMESTAMP
                        WHERE id = ?
                          AND domain_id = ?
                    ");

                $stmt->execute([
                    $formType,
                    $formName,
                    $formContent,
                    $formTtl,
                    $formProxied,
                    $editId,
                    $domainId
                ]);


                log_action(
                    $userId,
                    null,
                    'update_record',
                    $fqdn
                );


                flash(
                    'success',
                    'DNS 记录修改成功'
                );
            }

        } catch (Throwable $e) {

            error_log(
                '[AZhai Sub] DNS save: ' .
                $e->getMessage()
            );

            flash(
                'error',
                'DNS 操作失败：' .
                $e->getMessage()
            );
        }

        redirect(
            '/dashboard/domains.php?id=' .
            $domainId
        );
    }


    
    if (
        $action === 'delete'
    ) {

        $recordId =
            (int)(
                $_POST['record_id']
                ?? 0
            );

        if (
            $recordId <= 0
        ) {

            flash(
                'error',
                '无效的 DNS 记录'
            );

            redirect(
                '/dashboard/domains.php?id=' .
                $domainId
            );
        }

        try {

            
            $dns =
                new DnsManager(
                    $pdo,
                    (string)$config['app_key']
                );


            
            $stmt =
                $pdo->prepare("
                    SELECT *
                    FROM domain_records
                    WHERE id = ?
                      AND domain_id = ?
                    LIMIT 1
                ");

            $stmt->execute([
                $recordId,
                $domainId
            ]);

            $record =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!$record) {

                throw new RuntimeException(
                    'DNS 记录不存在'
                );
            }


            
            $deleteResult =
                $dns->deleteRecord(
                    (string)$recordId
                );

            if (
                empty(
                    $deleteResult['success']
                )
            ) {

                throw new RuntimeException(
                    (string)(
                        $deleteResult['message']
                        ?? '远程 DNS 删除失败'
                    )
                );
            }


            
            $stmt =
                $pdo->prepare("
                    DELETE FROM domain_records
                    WHERE id = ?
                      AND domain_id = ?
                    LIMIT 1
                ");

            $stmt->execute([
                $recordId,
                $domainId
            ]);

            if (
                $stmt->rowCount() !== 1
            ) {

                throw new RuntimeException(
                    '本地 DNS 记录删除失败'
                );
            }


            
            $recordName =
                trim(
                    (string)(
                        $record['name']
                        ?? '@'
                    )
                );

            if (
                $recordName === ''
                ||
                $recordName === '@'
            ) {

                $fqdn =
                    (string)$domain[
                        'full_domain'
                    ];

            } else {

                $fqdn =
                    $recordName .
                    '.' .
                    $domain[
                        'full_domain'
                    ];
            }


            
            log_action(
                $userId,
                null,
                'delete_record',
                $fqdn
            );


            flash(
                'success',
                'DNS 记录删除成功'
            );

        } catch (Throwable $e) {

            error_log(
                '[AZhai Sub] DNS delete: ' .
                $e->getMessage()
            );

            flash(
                'error',
                'DNS 删除失败：' .
                $e->getMessage()
            );
        }

        redirect(
            '/dashboard/domains.php?id=' .
            $domainId
        );
    }
}




$stmt =
    $pdo->prepare("
        SELECT *
        FROM domain_records
        WHERE domain_id = ?
        ORDER BY
            type ASC,
            name ASC,
            id ASC
    ");

$stmt->execute([
    $domainId
]);

$records =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );




$title =
    'DNS 管理 - ' .
    $domain['full_domain'];

require __DIR__ .
    '/../includes/header.php';

$success =
    flash('success');

$error =
    flash('error');

?>

<div class="card">

    <div class="page-header">

        <div>

            <h1>
                DNS 管理
            </h1>

            <p class="muted">
                <?= e(
                    $domain['full_domain']
                ) ?>
            </p>

        </div>

        <div>

            <?php
            $statusName =
                $statusLabels[
                    $status
                ]
                ?? $status;

            $statusClass =
                $statusClasses[
                    $status
                ]
                ?? 'muted';
            ?>

            <span
                class="status <?= e(
                    $statusClass
                ) ?>"
            >
                <?= e(
                    $statusName
                ) ?>
            </span>

        </div>

    </div>

    <?php if ($success): ?>

        <div class="alert success">
            <?= e($success) ?>
        </div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="alert danger">
            <?= e($error) ?>
        </div>

    <?php endif; ?>


    <?php if ($status !== 'active'): ?>

        <div class="alert warning">

            当前域名状态为
            <strong>
                <?= e(
                    $statusName
                ) ?>
            </strong>

            ，暂时无法修改 DNS 记录。

        </div>

    <?php else: ?>


        <?php if ($providerType === 'cloudflare'): ?>

            <div class="form-group">

                <label>
                    DNS 服务商
                </label>

                <div class="muted">
                    <?= e(
                        $providerName
                        ?? 'Cloudflare'
                    ) ?>
                </div>

            </div>

        <?php elseif ($providerType === 'aliyun'): ?>

            <div class="form-group">

                <label>
                    DNS 服务商
                </label>

                <div class="muted">

                    <?= e(
                        $providerName
                        ?? '阿里云 DNS'
                    ) ?>

                    <?php if (
                        $providerVersionName
                    ): ?>

                        ·

                        <?= e(
                            $providerVersionName
                        ) ?>

                    <?php endif; ?>

                </div>

            </div>

        <?php endif; ?>


        <form
            method="post"
            class="dns-form"
        >

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="<?= $editingRecord
                    ? 'update'
                    : 'create'
                ?>"
            >

            <?php if (
                $editingRecord
            ): ?>

                <input
                    type="hidden"
                    name="record_id"
                    value="<?= (int)(
                        $editingRecord['id']
                    ) ?>"
                >

            <?php endif; ?>


            <div class="form-group">

                <label>
                    类型
                </label>

                <select
                    name="type"
                    id="dns-type"
                    required
                >

                    <?php foreach (
                        $allowedTypes
                        as $type
                    ): ?>

                        <option
                            value="<?= e(
                                $type
                            ) ?>"
                            <?= $formType === $type
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= e($type) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label>
                    名称
                </label>

                <input
                    type="text"
                    name="name"
                    value="<?= e(
                        $formName
                    ) ?>"
                    placeholder="@"
                    required
                >

                <small>
                    @ 表示当前域名，也可以填写 www、api 等子域名。
                </small>

            </div>


            <div class="form-group">

                <label>
                    内容
                </label>

                <input
                    type="text"
                    name="content"
                    value="<?= e(
                        $formContent
                    ) ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    TTL
                </label>

                <select
                    name="ttl"
                    required
                >

                    <?php foreach (
                        $ttlOptions
                        as $value => $label
                    ): ?>

                        <option
                            value="<?= (int)$value ?>"
                            <?= $formTtl === (int)$value
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= e($label) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <?php if (
                    $providerType === 'aliyun'
                ): ?>

                    <small>
                        当前阿里云 DNS 套餐：
                        <?= e(
                            $providerVersionName
                            ?? '免费版'
                        ) ?>
                    </small>

                <?php endif; ?>

            </div>


            <?php if (
                $providerType === 'cloudflare'
            ): ?>

                <div
                    class="form-group dns-proxy-group"
                    id="dns-proxy-group"
                >

                    <label>
                        DNS 代理
                    </label>

                    <label class="checkbox">

                        <input
                            type="checkbox"
                            name="proxied"
                            value="1"
                            id="dns-proxied"
                            <?= (
                                $formProxied
                                &&
                                $formType !== 'TXT'
                            )
                                ? 'checked'
                                : ''
                            ?>
                        >

                        <span>
                            开启代理
                        </span>

                    </label>

                    <small>
                        仅 A、AAAA、CNAME 记录支持 Cloudflare 代理。
                    </small>

                </div>

            <?php endif; ?>


            <div class="form-actions">

                <button
                    type="submit"
                >
                    <?= $editingRecord
                        ? '保存修改'
                        : '添加记录'
                    ?>
                </button>

                <?php if (
                    $editingRecord
                ): ?>

                    <a
                        href="/dashboard/domains.php?id=<?= $domainId ?>"
                        class="button secondary"
                    >
                        取消编辑
                    </a>

                <?php endif; ?>

            </div>

        </form>

    <?php endif; ?>

</div>


<div class="card">

    <div class="page-header">

        <div>

            <h2>
                DNS 记录
            </h2>

            <p class="muted">
                当前共有
                <?= count($records) ?>
                条记录
            </p>

        </div>

    </div>


    <?php if (
        empty($records)
    ): ?>

        <div class="empty">
            暂无 DNS 记录
        </div>

    <?php else: ?>

        <div class="table-wrap">

            <table>

                <thead>

                    <tr>

                        <th>
                            类型
                        </th>

                        <th>
                            名称
                        </th>

                        <th>
                            内容
                        </th>

                        <th>
                            TTL
                        </th>

                        <?php if (
                            $providerType ===
                            'cloudflare'
                        ): ?>

                            <th>
                                代理
                            </th>

                        <?php endif; ?>

                        <th>
                            操作
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach (
                    $records
                    as $record
                ): ?>

                    <?php

                    $recordTtl =
                        (int)$record['ttl'];

                    $recordTtlLabel =
                        $ttlLabels[
                            $recordTtl
                        ]
                        ??
                        (
                            $recordTtl .
                            ' 秒'
                        );

                    ?>

                    <tr>

                        <td>
                            <strong>
                                <?= e(
                                    $record['type']
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= e(
                                $record['name']
                            ) ?>
                        </td>

                        <td>

                            <code>
                                <?= e(
                                    $record['content']
                                ) ?>
                            </code>

                        </td>

                        <td>
                            <?= e(
                                $recordTtlLabel
                            ) ?>
                        </td>

                        <?php if (
                            $providerType ===
                            'cloudflare'
                        ): ?>

                            <td>

                                <?php if (
                                    $record['proxied']
                                ): ?>

                                    <span
                                        class="status success"
                                    >
                                        已代理
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="muted"
                                    >
                                        DNS only
                                    </span>

                                <?php endif; ?>

                            </td>

                        <?php endif; ?>

                        <td>

                            <a
                                href="/dashboard/domains.php?id=<?= $domainId ?>&edit=<?= (int)$record['id'] ?>"
                            >
                                编辑
                            </a>


                            <form
                                method="post"
                                class="inline"
                                onsubmit="return confirm('确定要删除这条 DNS 记录吗？');"
                            >

                                <?= csrf_field() ?>

                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete"
                                >

                                <input
                                    type="hidden"
                                    name="record_id"
                                    value="<?= (int)$record['id'] ?>"
                                >

                                <button
                                    type="submit"
                                    class="link danger"
                                >
                                    删除
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>


<?php if (
    $providerType === 'cloudflare'
): ?>

<script>

(function () {

    const type =
        document.getElementById(
            'dns-type'
        );

    const proxyGroup =
        document.getElementById(
            'dns-proxy-group'
        );

    const proxy =
        document.getElementById(
            'dns-proxied'
        );

    if (
        !type ||
        !proxyGroup ||
        !proxy
    ) {
        return;
    }

    function updateProxy() {

        const value =
            type.value.toUpperCase();

        const supported =
            [
                'A',
                'AAAA',
                'CNAME'
            ].includes(
                value
            );

        if (!supported) {

            proxyGroup.style.display =
                'none';

            proxy.checked =
                false;

        } else {

            proxyGroup.style.display =
                '';

        }
    }

    type.addEventListener(
        'change',
        updateProxy
    );

    updateProxy();

})();

</script>

<?php endif; ?>


<?php require __DIR__ .
    '/../includes/footer.php'; ?>