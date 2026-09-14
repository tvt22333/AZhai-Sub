-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2026-09-11 22:22:22
-- 服务器版本： 8.4.10
-- PHP 版本： 8.4.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `azhai_sub`
--

-- --------------------------------------------------------

--
-- 表的结构 `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int UNSIGNED NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `status`, `created_at`) VALUES
(1, 'azhai', '$2y$12$.rDJaZB3X0AyGOrhB0nMv.U.2Tu17Oj6zAxWKnM5IX3YuPmw0CW6G', 1, '2026-09-11 22:22:22');

-- --------------------------------------------------------

--
-- 表的结构 `announcements`
--

CREATE TABLE `announcements` (
  `id` int UNSIGNED NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `blacklist`
--

CREATE TABLE `blacklist` (
  `id` int UNSIGNED NOT NULL,
  `keyword` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `blacklist`
--

INSERT INTO `blacklist` (`id`, `keyword`, `reason`, `created_at`) VALUES
(1, 'www', '系统保留', '2026-09-04 22:27:12'),
(2, 'admin', '系统保留', '2026-09-04 22:27:12'),
(3, 'mail', '系统保留', '2026-09-04 22:27:12'),
(4, 'smtp', '系统保留', '2026-09-04 22:27:12'),
(5, 'ftp', '系统保留', '2026-09-04 22:27:12'),
(6, 'api', '系统保留', '2026-09-04 22:27:12'),
(7, 'cdn', '系统保留', '2026-09-04 22:27:12'),
(8, 'panel', '系统保留', '2026-09-04 22:27:12');

-- --------------------------------------------------------

--
-- 表的结构 `dns_providers`
--

CREATE TABLE `dns_providers` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(30) NOT NULL,
  `config` text NOT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `dns_provider_domains`
--

CREATE TABLE `dns_provider_domains` (
  `id` int UNSIGNED NOT NULL,
  `provider_id` int UNSIGNED NOT NULL,
  `domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_zone_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 表的结构 `dns_sync_logs`
--

CREATE TABLE `dns_sync_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `record_id` bigint UNSIGNED NOT NULL,
  `provider_id` int UNSIGNED NOT NULL,
  `action` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL,
  `message` varchar(1000) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `domains`
--

CREATE TABLE `domains` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `provider_domain_id` int UNSIGNED DEFAULT NULL,
  `subdomain` varchar(63) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `full_domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('pending','active','suspended','deleted') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `domain_applications`
--

CREATE TABLE `domain_applications` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `provider_domain_id` int UNSIGNED DEFAULT NULL,
  `subdomain` varchar(63) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `review_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reviewed_by` int UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `domain_records`
--

CREATE TABLE `domain_records` (
  `id` int UNSIGNED NOT NULL,
  `domain_id` int UNSIGNED NOT NULL,
  `type` enum('A','AAAA','CNAME','TXT') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `content` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ttl` int UNSIGNED NOT NULL DEFAULT '300',
  `proxied` tinyint NOT NULL DEFAULT '0',
  `provider_record_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `email_logs`
--

CREATE TABLE `email_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `template_key` varchar(64) DEFAULT NULL,
  `to_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('sent','failed') NOT NULL,
  `error` text,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `email_settings`
--

CREATE TABLE `email_settings` (
  `id` tinyint UNSIGNED NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `smtp_host` varchar(255) NOT NULL DEFAULT '',
  `smtp_port` smallint UNSIGNED NOT NULL DEFAULT '465',
  `smtp_encryption` enum('none','ssl','tls') NOT NULL DEFAULT 'ssl',
  `smtp_username` varchar(255) NOT NULL DEFAULT '',
  `smtp_password` text,
  `from_name` varchar(100) NOT NULL DEFAULT 'AZhai Sub',
  `from_email` varchar(255) NOT NULL DEFAULT '',
  `reply_to` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int UNSIGNED NOT NULL,
  `template_key` varchar(64) NOT NULL,
  `name` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` longtext NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- 转存表中的数据 `email_templates`
--

INSERT INTO `email_templates` (`id`, `template_key`, `name`, `subject`, `body`, `enabled`, `updated_at`) VALUES
(1, 'verify_email', '注册邮箱验证', '验证你的 AZhai Sub 邮箱', '<p>你好，{{username}}：</p><p>感谢注册 AZhai Sub。</p><p>请点击下面的按钮验证你的邮箱：</p><p><a href=\"{{verify_url}}\" style=\"display:inline-block;padding:12px 20px;background:#f2769a;color:#fff;text-decoration:none;border-radius:8px;\">验证邮箱</a></p><p>链接有效期：{{expires_minutes}} 分钟。</p><p>如果这不是你的操作，请忽略此邮件。</p>', 1, '2026-09-06 22:36:37'),
(2, 'password_reset', '忘记密码', '重置你的 AZhai Sub 密码', '<p>你好，{{username}}：</p><p>我们收到了密码重置请求。</p><p><a href=\"{{reset_url}}\" style=\"display:inline-block;padding:12px 20px;background:#f2769a;color:#fff;text-decoration:none;border-radius:8px;\">重置密码</a></p><p>链接有效期：{{expires_minutes}} 分钟。</p><p>如果不是你本人操作，请忽略此邮件。</p>', 1, '2026-09-06 22:36:37'),
(3, 'domain_approved', '域名申请通过', '你的域名申请已通过', '<p>你好，{{username}}：</p><p>你的域名 <strong>{{domain}}</strong> 已通过审核。</p><p>现在可以登录 AZhai Sub 控制台管理 DNS 记录。</p>', 1, '2026-09-06 22:36:37'),
(4, 'domain_rejected', '域名申请被拒绝', '你的域名申请未通过', '<p>你好，{{username}}：</p><p>你的域名 <strong>{{domain}}</strong> 未通过审核。</p><p>审核原因：{{review_reason}}</p>', 1, '2026-09-08 21:26:15'),
(5, 'domain_suspended', '域名暂停', '你的域名已被暂停', '<p>你好，{{username}}：</p><p>你的域名 <strong>{{domain}}</strong> 已被管理员暂停。</p>', 1, '2026-09-06 22:36:37'),
(6, 'domain_restored', '域名恢复', '你的域名已恢复', '<p>你好，{{username}}：</p><p>你的域名 <strong>{{domain}}</strong> 已恢复正常。</p>', 1, '2026-09-06 22:36:37'),
(7, 'account_disabled', '账号禁用', '你的 AZhai Sub 账号已被禁用', '<p>你好，{{username}}：</p><p>你的 AZhai Sub 账号已被管理员禁用。</p>', 1, '2026-09-06 22:36:37'),
(8, 'account_enabled', '账号启用', '你的 AZhai Sub 账号已恢复使用', '<p>你好，{{username}}：</p><p>你的 AZhai Sub 账号已恢复使用。</p>', 1, '2026-09-06 22:36:37');

-- --------------------------------------------------------

--
-- 表的结构 `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- 转存表中的数据 `email_verifications`
--

INSERT INTO `email_verifications` (`id`, `user_id`, `email`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(15, 18, '1234qwer@163.com', 'be37aca0edb85fe5dbf9d226f32a819f6b6ddfa23cdf5f7437b301d7e44b91fa', '2026-09-12 11:40:21', NULL, '2026-09-12 11:10:21');

-- --------------------------------------------------------

--
-- 表的结构 `operation_logs`
--

CREATE TABLE `operation_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `admin_id` int UNSIGNED DEFAULT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `target` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `password_resets`
--

CREATE TABLE `password_resets` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- 转存表中的数据 `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 12, 'a0ec5a27b6fcdcb43e664fc6ad468277def0d9240b0ec3df9cc670273ee98270', '2026-09-08 18:20:39', '2026-09-08 17:51:07', '2026-09-08 17:50:39');

-- --------------------------------------------------------

--
-- 表的结构 `settings`
--

CREATE TABLE `settings` (
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `txt_verification_requests`
--

CREATE TABLE `txt_verification_requests` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `domain_id` int UNSIGNED NOT NULL,
  `subdomain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_value` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `admin_id` int UNSIGNED DEFAULT NULL,
  `admin_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 表的结构 `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `oauth_provider` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `oauth_sub` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `user_oauth_accounts`
--

CREATE TABLE `user_oauth_accounts` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `provider` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 转储表的索引
--

--
-- 表的索引 `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- 表的索引 `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `blacklist`
--
ALTER TABLE `blacklist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `keyword` (`keyword`);

--
-- 表的索引 `dns_providers`
--
ALTER TABLE `dns_providers`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `dns_provider_domains`
--
ALTER TABLE `dns_provider_domains`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_provider_domain` (`provider_id`,`domain`),
  ADD KEY `idx_provider_id` (`provider_id`),
  ADD KEY `idx_enabled` (`enabled`);

--
-- 表的索引 `dns_sync_logs`
--
ALTER TABLE `dns_sync_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `record_id` (`record_id`),
  ADD KEY `provider_id` (`provider_id`);

--
-- 表的索引 `domains`
--
ALTER TABLE `domains`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subdomain` (`subdomain`),
  ADD UNIQUE KEY `full_domain` (`full_domain`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_provider_domain_id` (`provider_domain_id`);

--
-- 表的索引 `domain_applications`
--
ALTER TABLE `domain_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_provider_domain_id` (`provider_domain_id`);

--
-- 表的索引 `domain_records`
--
ALTER TABLE `domain_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `domain_id` (`domain_id`);

--
-- 表的索引 `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_logs_user_id` (`user_id`),
  ADD KEY `idx_email_logs_created_at` (`created_at`),
  ADD KEY `idx_email_logs_status` (`status`);

--
-- 表的索引 `email_settings`
--
ALTER TABLE `email_settings`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `template_key` (`template_key`);

--
-- 表的索引 `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_email_verification_token` (`token_hash`),
  ADD KEY `idx_email_verifications_user` (`user_id`),
  ADD KEY `idx_email_verifications_expires` (`expires_at`);

--
-- 表的索引 `operation_logs`
--
ALTER TABLE `operation_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `created_at` (`created_at`);

--
-- 表的索引 `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_password_reset_token` (`token_hash`),
  ADD KEY `idx_password_resets_user` (`user_id`),
  ADD KEY `idx_password_resets_expires` (`expires_at`);

--
-- 表的索引 `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`name`);

--
-- 表的索引 `txt_verification_requests`
--
ALTER TABLE `txt_verification_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_domain_id` (`domain_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_txt_request_admin` (`admin_id`);

--
-- 表的索引 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uk_oauth_account` (`oauth_provider`,`oauth_sub`);

--
-- 表的索引 `user_oauth_accounts`
--
ALTER TABLE `user_oauth_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_provider_subject` (`provider`,`subject`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_provider` (`provider`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `blacklist`
--
ALTER TABLE `blacklist`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- 使用表AUTO_INCREMENT `dns_providers`
--
ALTER TABLE `dns_providers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- 使用表AUTO_INCREMENT `dns_provider_domains`
--
ALTER TABLE `dns_provider_domains`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- 使用表AUTO_INCREMENT `dns_sync_logs`
--
ALTER TABLE `dns_sync_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `domains`
--
ALTER TABLE `domains`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- 使用表AUTO_INCREMENT `domain_applications`
--
ALTER TABLE `domain_applications`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- 使用表AUTO_INCREMENT `domain_records`
--
ALTER TABLE `domain_records`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- 使用表AUTO_INCREMENT `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- 使用表AUTO_INCREMENT `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- 使用表AUTO_INCREMENT `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- 使用表AUTO_INCREMENT `operation_logs`
--
ALTER TABLE `operation_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- 使用表AUTO_INCREMENT `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `txt_verification_requests`
--
ALTER TABLE `txt_verification_requests`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- 使用表AUTO_INCREMENT `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- 使用表AUTO_INCREMENT `user_oauth_accounts`
--
ALTER TABLE `user_oauth_accounts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 限制导出的表
--

--
-- 限制表 `dns_provider_domains`
--
ALTER TABLE `dns_provider_domains`
  ADD CONSTRAINT `fk_provider_domains_provider` FOREIGN KEY (`provider_id`) REFERENCES `dns_providers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- 限制表 `domains`
--
ALTER TABLE `domains`
  ADD CONSTRAINT `fk_domains_provider_domain` FOREIGN KEY (`provider_domain_id`) REFERENCES `dns_provider_domains` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_domains_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 限制表 `domain_applications`
--
ALTER TABLE `domain_applications`
  ADD CONSTRAINT `fk_domain_applications_provider_domain` FOREIGN KEY (`provider_domain_id`) REFERENCES `dns_provider_domains` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- 限制表 `domain_records`
--
ALTER TABLE `domain_records`
  ADD CONSTRAINT `fk_records_domain` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE;

--
-- 限制表 `txt_verification_requests`
--
ALTER TABLE `txt_verification_requests`
  ADD CONSTRAINT `fk_txt_request_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_txt_request_domain` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_txt_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
