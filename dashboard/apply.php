<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/dns/DnsManager.php';

require_login();

$pdo = db();

$userId = current_user_id();

$errors = [];

$subdomain = '';
$reason = '';

$providerDomainId = (int)(
    $_POST['provider_domain_id'] ?? 0
);

$maxDomains = (int)(
    $config['max_domains_per_user'] ?? 10
);



$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM domains
    WHERE user_id = ?
      AND status <> 'deleted'
");

$stmt->execute([
    $userId
]);

$domainCount = (int)$stmt->fetchColumn();



$dnsManager = new DnsManager(
    $pdo,
    (string)$config['app_key']
);

$availableDomains = $dnsManager->getAvailableDomains();



$selectedDomain = null;

if ($providerDomainId > 0) {

    foreach ($availableDomains as $item) {

        if (
            (int)$item['id']
            === $providerDomainId
        ) {

            $selectedDomain = $item;

            break;
        }
    }
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();


    
    $subdomain = strtolower(
        trim(
            (string)(
                $_POST['subdomain']
                ?? ''
            )
        )
    );

    $reason = trim(
        (string)(
            $_POST['reason']
            ?? ''
        )
    );

    $providerDomainId = (int)(
        $_POST['provider_domain_id']
        ?? 0
    );


    
    $selectedDomain = null;

    if ($providerDomainId <= 0) {

        $errors[] = '请选择要使用的域名';

    } else {

        foreach ($availableDomains as $item) {

            if (
                (int)$item['id']
                === $providerDomainId
            ) {

                $selectedDomain = $item;

                break;
            }
        }

        if (!$selectedDomain) {

            $errors[] = '选择的域名不可用';
        }
    }


    
    if ($subdomain === '') {

        $errors[] = '请输入二级域名前缀';

    } elseif (strlen($subdomain) > 63) {

        $errors[] =
            '二级域名前缀不能超过 63 个字符';

    } elseif (!is_valid_subdomain($subdomain)) {

        $errors[] =
            '域名前缀格式不正确，只能使用字母、数字和短横线，且不能以短横线开头或结尾';
    }


    
    if ($reason === '') {

        $errors[] = '申请理由必填';

    } elseif (mb_strlen($reason, 'UTF-8') > 500) {

        $errors[] =
            '申请理由不能超过 500 个字符';
    }


    
    if ($domainCount >= $maxDomains) {

        $errors[] =
            '你已经达到最多 '
            . $maxDomains
            . ' 个二级域名的限制';
    }


    
    $reserved = [
        'www',
        'mail',
        'smtp',
        'pop',
        'imap',
        'ftp',
        'api',
        'admin',
        'administrator',
        'dashboard',
        'oauth',
        'login',
        'register',
        'cdn',
        'ns1',
        'ns2',
        'dns',
    ];

    if (
        $subdomain !== ''
        && in_array(
            $subdomain,
            $reserved,
            true
        )
    ) {

        $errors[] =
            '这个域名前缀属于系统保留名称，不能申请';
    }


    
    $fullDomain = '';

    if ($selectedDomain) {

        $rootDomain = strtolower(
            trim(
                (string)$selectedDomain['domain']
            )
        );

        $fullDomain =
            $subdomain
            . '.'
            . $rootDomain;
    }


    
    if (
        empty($errors)
        && $fullDomain !== ''
    ) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM domains
            WHERE full_domain = ?
              AND status <> 'deleted'
            LIMIT 1
        ");

        $stmt->execute([
            $fullDomain
        ]);

        if ($stmt->fetch()) {

            $errors[] =
                '这个二级域名已经被使用';
        }
    }


    
    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM domain_applications
            WHERE provider_domain_id = ?
              AND subdomain = ?
              AND status = 'pending'
            LIMIT 1
        ");

        $stmt->execute([
            $providerDomainId,
            $subdomain
        ]);

        if ($stmt->fetch()) {

            $errors[] =
                '这个二级域名目前正在审核中';
        }
    }


    
    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM domain_applications
            WHERE user_id = ?
              AND provider_domain_id = ?
              AND subdomain = ?
              AND status IN ('pending', 'approved')
            LIMIT 1
        ");

        $stmt->execute([
            $userId,
            $providerDomainId,
            $subdomain
        ]);

        if ($stmt->fetch()) {

            $errors[] =
                '你已经申请过这个二级域名';
        }
    }


    
    if (
        empty($errors)
        && $fullDomain !== ''
    ) {

        try {

            $exists =
    $dnsManager->hasRecordByDomain(
        $fullDomain,
        $providerDomainId
    );

            if ($exists) {

                $errors[] =
                    '这个二级域名已经存在 DNS 解析记录，无法申请';
            }

        } catch (Throwable $e) {

            error_log(
                '[AZhai Sub] DNS availability check failed'
                . ' domain='
                . $fullDomain
                . ' provider_domain_id='
                . $providerDomainId
                . ' error='
                . $e->getMessage()
            );

            $errors[] =
                '暂时无法检查该二级域名的 DNS 状态，请稍后再试';
        }
    }


    
    if (empty($errors)) {

        $stmt = $pdo->prepare(
    'INSERT INTO domain_applications (
        user_id,
        provider_domain_id,
        subdomain,
        reason,
        status,
        created_at
    ) VALUES (?, ?, ?, ?, ?, NOW())'
);

$stmt->execute([
    $userId,
    $providerDomainId,
    $subdomain,
    $reason,
    'pending'
]);


        
        if (
            function_exists('log_action')
        ) {

            log_action(
                $userId,
                null,
                'apply_domain',
                $fullDomain
            );
        }


        flash(
            'success',
            '域名申请提交成功，请等待管理员审核'
        );

        redirect(
            '/dashboard/'
        );
    }
}


$title = '申请二级域名';

require __DIR__ . '/../includes/header.php';

?>

<div class="dashboard">

    <div class="card">

        <div class="page-heading">

            <span class="eyebrow">
                AZHAI SUB
            </span>

            <h1>
                申请二级域名
            </h1>

            <p class="muted">
                免费申请属于你的二级域名
            </p>

        </div>


        <?php if (!empty($errors)): ?>

            <div class="alert error">

                <ul>

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= e($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <div class="domain-preview">

            <span>
                你的域名
            </span>

            <strong>

                <span id="preview-subdomain">
                    <?= e(
                        $subdomain !== ''
                            ? $subdomain
                            : 'your-name'
                    ) ?>
                </span>

                <?php if ($selectedDomain): ?>

                    <span id="preview-domain">
                        .<?= e(
                            $selectedDomain['domain']
                        ) ?>
                    </span>

                <?php else: ?>

                    <span
                        id="preview-domain"
                        style="display:none"
                    ></span>

                <?php endif; ?>

            </strong>

        </div>


        <form
            method="post"
            autocomplete="off"
        >

            <?= csrf_field() ?>


            <div class="form-group">

                <label for="provider_domain_id">
                    选择域名
                </label>

                <?php if (empty($availableDomains)): ?>

                    <div class="alert error">
                        当前没有开放可申请的域名
                    </div>

                <?php else: ?>

                    <select
                        id="provider_domain_id"
                        name="provider_domain_id"
                        required
                    >

                        <option
                            value=""
                            <?= $providerDomainId <= 0
                                ? 'selected'
                                : '' ?>
                        >
                            请选择域名
                        </option>

                        <?php foreach (
                            $availableDomains
                            as $item
                        ): ?>

                            <option
                                value="<?= (int)$item['id'] ?>"
                                data-domain="<?= e($item['domain']) ?>"
                                <?= $providerDomainId ===
                                    (int)$item['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                .<?= e(
                                    $item['domain']
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <small>
                        请选择你希望使用的二级域名后缀
                    </small>

                <?php endif; ?>

            </div>


            <div class="form-group">

                <label for="subdomain">
                    二级域名前缀
                </label>

                <div class="domain-input">

                    <input
                        type="text"
                        id="subdomain"
                        name="subdomain"
                        value="<?= e($subdomain) ?>"
                        placeholder="例如 blog"
                        maxlength="63"
                        required
                        autofocus
                    >

                    <span
                        id="domain-suffix"
                        <?= $selectedDomain
                            ? ''
                            : 'style="display:none"' ?>
                    >
                        <?php if ($selectedDomain): ?>

                            .<?= e(
                                $selectedDomain['domain']
                            ) ?>

                        <?php endif; ?>
                    </span>

                </div>

                <small>
                    只能使用字母、数字和短横线，不能以短横线开头或结尾
                </small>

            </div>


            <div class="form-group">

                <label for="reason">

                    申请理由

                    <span class="required">
                        必填
                    </span>

                </label>

                <textarea
                    id="reason"
                    name="reason"
                    maxlength="500"
                    rows="5"
                    placeholder="例如：用于个人博客、作品展示、个人主页等"
                    required
                ><?= e($reason) ?></textarea>

                <small>
                    最多 500 个字符
                </small>

            </div>


            <button
                type="submit"
                class="button full"
                <?= empty($availableDomains)
                    ? 'disabled'
                    : '' ?>
            >
                提交申请
            </button>

        </form>


        <div class="form-footer">

            <a href="/dashboard/">
                ← 返回控制台
            </a>

        </div>

    </div>

</div>


<script>

const subdomainInput =
    document.getElementById(
        'subdomain'
    );

const preview =
    document.getElementById(
        'preview-subdomain'
    );

const domainSelect =
    document.getElementById(
        'provider_domain_id'
    );

const previewDomain =
    document.getElementById(
        'preview-domain'
    );

const domainSuffix =
    document.getElementById(
        'domain-suffix'
    );

function updateDomainPreview() {

    if (
        subdomainInput
        && preview
    ) {

        preview.textContent =
            subdomainInput.value
                .toLowerCase()
                .trim()
            || 'your-name';
    }


    if (
        domainSelect
        && previewDomain
        && domainSuffix
    ) {

        const option =
            domainSelect.options[
                domainSelect.selectedIndex
            ];

        const domain =
            option
                ? option.dataset.domain || ''
                : '';

        if (domain !== '') {

            previewDomain.textContent =
                '.' + domain;

            previewDomain.style.display =
                '';

            domainSuffix.textContent =
                '.' + domain;

            domainSuffix.style.display =
                '';

        } else {

            previewDomain.textContent =
                '';

            previewDomain.style.display =
                'none';

            domainSuffix.textContent =
                '';

            domainSuffix.style.display =
                'none';
        }
    }
}


if (subdomainInput) {

    subdomainInput.addEventListener(
        'input',
        updateDomainPreview
    );
}


if (domainSelect) {

    domainSelect.addEventListener(
        'change',
        updateDomainPreview
    );
}


updateDomainPreview();

</script>

<?php

require __DIR__ . '/../includes/footer.php';

?>