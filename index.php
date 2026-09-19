<?php

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/settings.php';

$title = site_setting('home_title', site_title_cn()) ?? site_title_cn();
$pageTitle = $title;

$pdo = db();

/*
|--------------------------------------------------------------------------
| 首页统计
|--------------------------------------------------------------------------
*/

try {
    $userCount = (int)$pdo->query("
        SELECT COUNT(*)
        FROM users
        WHERE status = 1
    ")->fetchColumn();
} catch (Throwable $e) {
    $userCount = 0;
}

try {
    $domainCount = (int)$pdo->query("
        SELECT COUNT(*)
        FROM domains
        WHERE status = 'active'
    ")->fetchColumn();
} catch (Throwable $e) {
    $domainCount = 0;
}

try {
    $recordCount = (int)$pdo->query("
        SELECT COUNT(*)
        FROM domain_records
    ")->fetchColumn();
} catch (Throwable $e) {
    $recordCount = 0;
}

require __DIR__ . '/includes/header.php';
?>

<style>
/* =========================================================
   AZhai Sub 首页
   ========================================================= */

.home {
    overflow: hidden;
}

/* Hero */

.home-hero {
    position: relative;
    padding: 96px 24px 90px;
    text-align: center;
}

.home-hero::before,
.home-hero::after {
    content: "";
    position: absolute;
    border-radius: 999px;
    pointer-events: none;
    z-index: -1;
}

.home-hero::before {
    width: 360px;
    height: 360px;
    background: rgba(255, 105, 180, .08);
    top: 20px;
    left: -140px;
}

.home-hero::after {
    width: 300px;
    height: 300px;
    background: rgba(255, 182, 193, .08);
    right: -120px;
    bottom: 20px;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 14px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid #eee;
    color: #888;
    font-size: 12px;
    letter-spacing: 1.5px;
    box-shadow: 0 5px 25px rgba(0, 0, 0, .035);
}

.hero-badge span {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #ff69b4;
    box-shadow: 0 0 0 4px rgba(255, 105, 180, .1);
}

.home-hero h1 {
    margin: 25px 0 12px;
    font-size: clamp(46px, 8vw, 82px);
    line-height: 1;
    letter-spacing: -3px;
    font-weight: 800;
    color: #222;
}

.home-hero h1 span {
    color: #ff69b4;
}

.hero-subtitle {
    max-width: 650px;
    margin: 0 auto;
    color: #777;
    font-size: 18px;
    line-height: 1.9;
}

.hero-actions {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 32px;
}

.hero-actions .btn {
    min-width: 130px;
    padding: 13px 25px;
    border-radius: 12px;
    text-decoration: none;
}

.hero-note {
    margin-top: 18px;
    color: #aaa;
    font-size: 13px;
}

/* Stats */

.home-stats {
    max-width: 1050px;
    margin: -10px auto 0;
    padding: 0 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    border: 1px solid #eee;
    border-radius: 20px;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 15px 50px rgba(0, 0, 0, .045);
}

.stat-item {
    padding: 28px 20px;
    text-align: center;
    border-right: 1px solid #eee;
}

.stat-item:last-child {
    border-right: 0;
}

.stat-number {
    display: block;
    font-size: 30px;
    font-weight: 750;
    color: #222;
}

.stat-label {
    display: block;
    margin-top: 5px;
    color: #999;
    font-size: 13px;
}

/* Common section */

.home-section {
    max-width: 1100px;
    margin: 0 auto;
    padding: 95px 20px;
}

.section-head {
    text-align: center;
    margin-bottom: 45px;
}

.section-label {
    display: block;
    margin-bottom: 10px;
    color: #ff69b4;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 2px;
}

.section-head h2 {
    margin: 0;
    font-size: 32px;
    color: #222;
}

.section-head p {
    max-width: 650px;
    margin: 13px auto 0;
    color: #999;
    line-height: 1.8;
}

/* Features */

.features-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
}

.feature-card {
    padding: 30px;
    border: 1px solid #eee;
    border-radius: 18px;
    background: #fff;
    transition: .25s ease;
}

.feature-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 18px 45px rgba(0, 0, 0, .06);
    border-color: #f3d9e6;
}

.feature-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    margin-bottom: 20px;
    border-radius: 14px;
    background: #fff2f8;
    color: #ff69b4;
    font-size: 22px;
}

.feature-card h3 {
    margin: 0 0 10px;
    font-size: 18px;
    color: #333;
}

.feature-card p {
    margin: 0;
    color: #888;
    line-height: 1.8;
    font-size: 14px;
}

/* DNS */

.dns-box {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 22px;
}

.dns-card {
    padding: 32px;
    border-radius: 20px;
    border: 1px solid #eee;
    background: #fff;
}

.dns-card h3 {
    margin: 0 0 8px;
    font-size: 19px;
}

.dns-card > p {
    margin: 0 0 25px;
    color: #999;
    font-size: 14px;
}

.dns-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
}

.dns-tag {
    padding: 8px 13px;
    border: 1px solid #eee;
    border-radius: 10px;
    color: #555;
    background: #fafafa;
    font-family: monospace;
    font-size: 13px;
}

.dns-tag strong {
    color: #ff69b4;
}

/* Steps */

.steps {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    counter-reset: step;
}

.step {
    position: relative;
    padding: 30px;
    border-radius: 18px;
    background: #fafafa;
}

.step-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #ff69b4;
    color: #fff;
    font-weight: 700;
    margin-bottom: 20px;
}

.step h3 {
    margin: 0 0 9px;
}

.step p {
    margin: 0;
    color: #888;
    font-size: 14px;
    line-height: 1.8;
}

/* Domain example */

.example-box {
    padding: 45px 25px;
    text-align: center;
    border: 1px solid #eee;
    border-radius: 22px;
    background: linear-gradient(
        135deg,
        #fff,
        #fff8fb
    );
}

.example-title {
    color: #999;
    font-size: 13px;
    margin-bottom: 13px;
}

.example-domain {
    font-family: monospace;
    font-size: clamp(24px, 5vw, 42px);
    font-weight: 700;
    color: #222;
    word-break: break-all;
}

.example-domain span {
    color: #ff69b4;
}

/* FAQ */

.faq-list {
    max-width: 800px;
    margin: auto;
}

.faq-item {
    border-bottom: 1px solid #eee;
}

.faq-item summary {
    cursor: pointer;
    padding: 20px 5px;
    list-style: none;
    font-weight: 600;
    color: #333;
}

.faq-item summary::-webkit-details-marker {
    display: none;
}

.faq-item summary::after {
    content: "+";
    float: right;
    color: #bbb;
    font-size: 20px;
}

.faq-item[open] summary::after {
    content: "−";
}

.faq-answer {
    padding: 0 5px 20px;
    color: #888;
    line-height: 1.9;
    font-size: 14px;
}

/* CTA */

.home-cta {
    max-width: 1100px;
    margin: 0 auto;
    padding: 20px 20px 100px;
}

.cta-box {
    position: relative;
    overflow: hidden;
    padding: 65px 30px;
    border-radius: 25px;
    background: #fff5fa;
    border: 1px solid #f7dce9;
    text-align: center;
}

.cta-box::after {
    content: "✦";
    position: absolute;
    right: 7%;
    top: 15%;
    color: rgba(255, 105, 180, .12);
    font-size: 80px;
}

.cta-box h2 {
    margin: 0 0 12px;
    font-size: 32px;
}

.cta-box p {
    margin: 0 auto 25px;
    color: #999;
}

/* Mobile */

@media (max-width: 800px) {

    .home-hero {
        padding: 70px 20px 65px;
    }

    .home-hero h1 {
        letter-spacing: -2px;
    }

    .hero-subtitle {
        font-size: 15px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .stat-item {
        border-right: 0;
        border-bottom: 1px solid #eee;
    }

    .stat-item:last-child {
        border-bottom: 0;
    }

    .features-grid,
    .dns-box,
    .steps {
        grid-template-columns: 1fr;
    }

    .home-section {
        padding: 70px 20px;
    }

    .section-head h2 {
        font-size: 27px;
    }

    .cta-box {
        padding: 50px 22px;
    }

    .cta-box h2 {
        font-size: 27px;
    }
}
</style>

<div class="home">

    <!-- =====================================================
         Hero
         ====================================================== -->

    <section class="home-hero">

        <div class="hero-badge">
            <span></span>
            FREE SUBDOMAIN PLATFORM
        </div>

        <h1>
            <?= e(site_title()) ?>
        </h1>

        <p class="hero-subtitle">
            <?= e(site_setting('home_description', '免费、简单、可控的二级域名平台')) ?>
        </p>

        <?php if (site_setting_bool('home_notice_enabled', false) && site_setting('home_notice', '') !== ''): ?>
            <div style="max-width:760px;margin:28px auto 0;padding:14px 18px;border:1px solid #f1dce5;background:#fff7fa;border-radius:16px;color:#888;line-height:1.7;text-align:left;">
                <?= nl2br(e(site_setting('home_notice', ''))) ?>
            </div>
        <?php endif; ?>

        <div class="hero-actions">

            <?php if (is_logged_in()): ?>

                <a
                    href="/dashboard/apply.php"
                    class="btn btn-primary"
                >
                    立即申请
                </a>

                <a
                    href="/dashboard/"
                    class="btn"
                >
                    进入控制台
                </a>

            <?php else: ?>

                <a
                    href="/register.php"
                    class="btn btn-primary"
                >
                    免费注册
                </a>

                <a
                    href="/login.php"
                    class="btn"
                >
                    登录账号
                </a>

            <?php endif; ?>

        </div>

        <?php if (is_logged_in()): ?>

            <div class="hero-note">
                欢迎回来，开始申请你的专属二级域名
            </div>

        <?php else: ?>

            <div class="hero-note">
                注册账号 · 提交申请 · 管理 DNS
            </div>

        <?php endif; ?>

    </section>
    <!-- =====================================================
         Features
         ====================================================== -->

    <section class="home-section">

        <div class="section-head">

            <span class="section-label">
                WHY AZHAI SUB
            </span>

            <h2>
                简单，但不简陋
            </h2>

            <p>
                不需要复杂的配置流程，从申请域名开始，
                到 DNS 记录管理，都尽可能保持简单
            </p>

        </div>

        <div class="features-grid">

            <div class="feature-card">

                <div class="feature-icon">
                    ✦
                </div>

                <h3>
                    免费使用
                </h3>

                <p>
                    提供免费的二级域名申请服务，
                    适合个人博客、作品集、开源项目以及各种个人项目
                </p>

            </div>


            <div class="feature-card">

                <div class="feature-icon">
                    ◇
                </div>

                <h3>
                    自主管理
                </h3>

                <p>
                    域名通过审核后，可以在控制台查看自己的域名，
                    并直接管理 DNS 解析记录
                </p>

            </div>


            <div class="feature-card">

                <div class="feature-icon">
                    ♢
                </div>

                <h3>
                    多 DNS 支持
                </h3>

                <p>
                    平台设计支持多种 DNS 服务商，
                    当前可以接入 Cloudflare 与阿里云 DNS
                </p>

            </div>


            <div class="feature-card">

                <div class="feature-icon">
                    #
                </div>

                <h3>
                    多种记录
                </h3>

                <p>
                    支持 A、AAAA、CNAME、TXT 等常用 DNS 记录类型，
                    满足网站、验证和各种项目的基本需求
                </p>

            </div>


            <div class="feature-card">

                <div class="feature-icon">
                    ✓
                </div>

                <h3>
                    安全控制
                </h3>

                <p>
                    用户只能管理属于自己的域名，
                    管理操作经过权限验证与 CSRF 防护
                </p>

            </div>


            <div class="feature-card">

                <div class="feature-icon">
                    ♡
                </div>

                <h3>
                    为个人而生
                </h3>

                <p>
                    不追求复杂的企业级功能，
                    只希望让一个人拥有一个好用、简单的域名
                </p>

            </div>

        </div>

    </section>


    <!-- =====================================================
         Domain Example
         ====================================================== -->

    <section class="home-section">

        <div class="section-head">

            <span class="section-label">
                YOUR DOMAIN
            </span>

            <h2>
                一个属于你的地址
            </h2>

            <p>
                选择一个喜欢的名称，让它成为你在互联网中的一个小小角落
            </p>

        </div>

        <div class="example-box">

            <div class="example-title">
                例如
            </div>

            <div class="example-domain">
                <span>blog</span>.<?= e($config['base_domain']) ?>
            </div>

        </div>

    </section>


    <!-- =====================================================
         DNS
         ====================================================== -->

    <section class="home-section">

        <div class="section-head">

            <span class="section-label">
                DNS MANAGEMENT
            </span>

            <h2>
                DNS，交给你自己管理
            </h2>

            <p>
                域名审核通过后，你可以直接在控制台维护 DNS 解析
            </p>

        </div>

        <div class="dns-box">

            <div class="dns-card">

                <h3>
                    支持的 DNS 服务商
                </h3>

                <p>
                    平台采用服务商抽象设计，可以逐步扩展更多 DNS 平台
                </p>

                <div class="dns-tags">

                    <div class="dns-tag">
                        <strong>CF</strong>
                        Cloudflare
                    </div>

                    <div class="dns-tag">
                        <strong>ALI</strong>
                        阿里云 DNS
                    </div>

                </div>

            </div>


            <div class="dns-card">

                <h3>
                    支持的记录类型
                </h3>

                <p>
                    覆盖个人网站最常使用的 DNS 记录
                </p>

                <div class="dns-tags">

                    <div class="dns-tag">
                        <strong>A</strong>
                        IPv4
                    </div>

                    <div class="dns-tag">
                        <strong>AAAA</strong>
                        IPv6
                    </div>

                    <div class="dns-tag">
                        <strong>CNAME</strong>
                        别名
                    </div>

                    <div class="dns-tag">
                        <strong>TXT</strong>
                        文本
                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- =====================================================
         Steps
         ====================================================== -->

    <section class="home-section">

        <div class="section-head">

            <span class="section-label">
                HOW IT WORKS
            </span>

            <h2>
                三步开始使用
            </h2>

            <p>
                没有复杂的流程，申请你的二级域名只需要几步
            </p>

        </div>

        <div class="steps">

            <div class="step">

                <div class="step-number">
                    1
                </div>

                <h3>
                    创建账号
                </h3>

                <p>
                    注册一个阿宅账号并登录 AZhai Sub
                    如果已经拥有阿宅账号，可以直接登录
                </p>

            </div>


            <div class="step">

                <div class="step-number">
                    2
                </div>

                <h3>
                    提交申请
                </h3>

                <p>
                    输入你希望使用的二级域名前缀，
                    简单说明用途并提交审核
                </p>

            </div>


            <div class="step">

                <div class="step-number">
                    3
                </div>

                <h3>
                    配置 DNS
                </h3>

                <p>
                    审核通过后进入控制台，
                    添加 A、AAAA、CNAME 或 TXT 等 DNS 记录
                </p>

            </div>

        </div>

    </section>


    <!-- =====================================================
         FAQ
         ====================================================== -->

    <section class="home-section">

        <div class="section-head">

            <span class="section-label">
                FAQ
            </span>

            <h2>
                常见问题
            </h2>

        </div>

        <div class="faq-list">

            <details class="faq-item">

                <summary>
                    AZhai Sub 是什么？
                </summary>

                <div class="faq-answer">
                    AZhai Sub 是一个免费的二级域名平台，
                    用户可以申请 <?= e($config['base_domain']) ?> 下的二级域名，
                    并在审核通过后管理对应的 DNS 记录
                </div>

            </details>


            <details class="faq-item">

                <summary>
                    可以申请什么样的域名？
                </summary>

                <div class="faq-answer">
                    你可以申请一个由字母、数字和连字符组成的域名前缀
                    部分系统保留名称无法申请，具体以平台审核结果为准
                </div>

            </details>


            <details class="faq-item">

                <summary>
                    审核通过以后可以做什么？
                </summary>

                <div class="faq-answer">
                    审核通过后，你可以在控制台看到自己的域名，
                    并管理 A、AAAA、CNAME、TXT 等 DNS 记录
                </div>

            </details>


            <details class="faq-item">

                <summary>
                    DNS TTL 最低是多少？
                </summary>

                <div class="faq-answer">
                    为兼容当前 DNS 服务商，AZhai Sub 的 TTL
                    最低设置为 600 秒，即 10 分钟
                </div>

            </details>


            <details class="faq-item">

                <summary>
                    可以申请多个域名吗？
                </summary>

                <div class="faq-answer">
                    可以平台会根据账号的申请数量限制进行控制，
                    当前默认每个账号最多拥有 10 个域名
                </div>

            </details>

        </div>

    </section>


    <!-- =====================================================
         CTA
         ====================================================== -->

    <section class="home-cta">

        <div class="cta-box">

            <?php if (is_logged_in()): ?>

                <h2>
                    准备好了吗？
                </h2>

                <p>
                    现在就申请一个属于你的二级域名
                </p>

                <a
                    href="/dashboard/apply.php"
                    class="btn btn-primary"
                >
                    立即申请
                </a>

            <?php else: ?>

                <h2>
                    从一个域名开始
                </h2>

                <p>
                    创建账号，申请属于你的 AZhai Sub 二级域名
                </p>

                <a
                    href="/register.php"
                    class="btn btn-primary"
                >
                    免费注册
                </a>

            <?php endif; ?>

        </div>

    </section>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
