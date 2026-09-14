<?php

declare(strict_types=1);

interface DnsProviderInterface
{
    public function testConnection(): array;

    public function createRecord(
        string $fqdn,
        string $type,
        string $content,
        int $ttl,
        bool $proxied = false
    ): array;

    public function updateRecord(
        string $id,
        string $fqdn,
        string $type,
        string $content,
        int $ttl,
        bool $proxied = false
    ): array;

    public function deleteRecord(
        string $id
    ): array;

    public function getDomainInfo(
        string $domain
    ): array;

    public function findRecords(
        string $fqdn
    ): array;

    public function listDomains(): array;
}