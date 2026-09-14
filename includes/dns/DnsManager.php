<?php

declare(strict_types=1);

require_once __DIR__ . '/DnsProviderInterface.php';
require_once __DIR__ . '/CloudflareProvider.php';
require_once __DIR__ . '/AliyunProvider.php';

class DnsManager
{
    private PDO $pdo;

    private string $appKey;

    public function __construct(
        PDO $pdo,
        string $appKey
    ) {
        $this->pdo = $pdo;
        $this->appKey = $appKey;
    }

    public function getProviderById(
        int $providerId
    ): ?DnsProviderInterface {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM dns_providers
            WHERE id = ?
              AND status = 1
            LIMIT 1
        ");

        $stmt->execute([
            $providerId
        ]);

        $provider =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$provider) {
            return null;
        }

        return $this->createProvider(
            $provider
        );
    }

    public function getProvider(): ?DnsProviderInterface
    {
        $stmt = $this->pdo->query("
            SELECT *
            FROM dns_providers
            WHERE status = 1
            ORDER BY id ASC
            LIMIT 1
        ");

        $provider =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$provider) {
            return null;
        }

        return $this->createProvider(
            $provider
        );
    }

    private function decodeProviderConfig(
        array $provider
    ): array {
        $config =
            $provider['config']
            ?? '';

        if ($config === '') {
            return [];
        }

        try {

            $decoded =
                json_decode(
                    $config,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

            return is_array($decoded)
                ? $decoded
                : [];

        } catch (Throwable $e) {

            return [];
        }
    }

    private function createProvider(
        array $provider
    ): ?DnsProviderInterface {
        $type =
            strtolower(
                trim(
                    (string)(
                        $provider['type']
                        ?? ''
                    )
                )
            );

        $config =
            $this->decodeProviderConfig(
                $provider
            );

        try {

            switch ($type) {

                case 'cloudflare':

                    return new CloudflareProvider(
                        $config
                    );

                case 'aliyun':
                case 'alicloud':

                    return new AliyunProvider(
                        $config
                    );

                default:

                    return null;
            }

        } catch (Throwable $e) {

            return null;
        }
    }

    public function getProviderDomain(
        int $providerDomainId
    ): ?array {
        $stmt =
            $this->pdo->prepare("
                SELECT
                    pd.*,
                    p.type AS provider_type,
                    p.name AS provider_name,
                    p.status AS provider_status
                FROM dns_provider_domains pd
                INNER JOIN dns_providers p
                    ON p.id = pd.provider_id
                WHERE pd.id = ?
                  AND pd.enabled = 1
                  AND p.status = 1
                LIMIT 1
            ");

        $stmt->execute([
            $providerDomainId
        ]);

        $row =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        return $row ?: null;
    }

    private function getProviderDomainForExistingDomain(
        int $providerDomainId
    ): ?array {
        $stmt =
            $this->pdo->prepare("
                SELECT
                    pd.*,
                    p.type AS provider_type,
                    p.name AS provider_name,
                    p.status AS provider_status
                FROM dns_provider_domains pd
                INNER JOIN dns_providers p
                    ON p.id = pd.provider_id
                WHERE pd.id = ?
                  AND p.status = 1
                LIMIT 1
            ");

        $stmt->execute([
            $providerDomainId
        ]);

        $row =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        return $row ?: null;
    }

    private function createProviderByDomain(
        array $providerDomain
    ): ?DnsProviderInterface {
        $providerId =
            (int)(
                $providerDomain['provider_id']
                ?? 0
            );

        if ($providerId <= 0) {
            return null;
        }

        $stmt =
            $this->pdo->prepare("
                SELECT *
                FROM dns_providers
                WHERE id = ?
                  AND status = 1
                LIMIT 1
            ");

        $stmt->execute([
            $providerId
        ]);

        $provider =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$provider) {
            return null;
        }

        $config =
            $this->decodeProviderConfig(
                $provider
            );

        $type =
            strtolower(
                trim(
                    (string)(
                        $provider['type']
                        ?? ''
                    )
                )
            );

        switch ($type) {

            case 'cloudflare':

                $zoneId =
                    trim(
                        (string)(
                            $providerDomain[
                                'provider_zone_id'
                            ]
                            ?? ''
                        )
                    );

                if ($zoneId === '') {
                    return null;
                }

                $config['zone_id'] =
                    $zoneId;

                $config['domain'] =
                    (string)(
                        $providerDomain['domain']
                    );

                try {

                    return new CloudflareProvider(
                        $config
                    );

                } catch (Throwable $e) {

                    return null;
                }

            case 'aliyun':
            case 'alicloud':

                $config['domain'] =
                    (string)(
                        $providerDomain['domain']
                    );

                if (
                    !empty(
                        $providerDomain[
                            'provider_zone_id'
                        ]
                    )
                ) {

                    $config['domain_id'] =
                        (string)(
                            $providerDomain[
                                'provider_zone_id'
                            ]
                        );
                }

                try {

                    return new AliyunProvider(
                        $config
                    );

                } catch (Throwable $e) {

                    return null;
                }

            default:

                return null;
        }
    }

    public function getAvailableDomains(): array
    {
        $stmt =
            $this->pdo->query("
                SELECT
                    pd.id,
                    pd.provider_id,
                    pd.domain,
                    pd.provider_zone_id,
                    pd.enabled,
                    p.type AS provider_type,
                    p.name AS provider_name
                FROM dns_provider_domains pd
                INNER JOIN dns_providers p
                    ON p.id = pd.provider_id
                WHERE pd.enabled = 1
                  AND p.status = 1
                ORDER BY pd.domain ASC
            ");

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function getProviderInfo(
        int $providerId
    ): array {
        $stmt =
            $this->pdo->prepare("
                SELECT *
                FROM dns_providers
                WHERE id = ?
                  AND status = 1
                LIMIT 1
            ");

        $stmt->execute([
            $providerId
        ]);

        $provider =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$provider) {

            return [
                'success' => false,
                'message' => 'DNS 服务商不存在'
            ];
        }

        $dnsProvider =
            $this->createProvider(
                $provider
            );

        if (!$dnsProvider) {

            return [
                'success' => false,
                'message' => 'DNS 服务商配置无效'
            ];
        }

        try {

            $result =
                $dnsProvider->testConnection();

            if (
                !isset(
                    $result['success']
                )
            ) {

                $result['success'] =
                    false;
            }

            $result['provider_id'] =
                (int)$provider['id'];

            $result['provider_name'] =
                $provider['name']
                ?? '';

            $result['provider_type'] =
                $provider['type']
                ?? '';

            return $result;

        } catch (Throwable $e) {

            return [
                'success' => false,
                'message' =>
                    $e->getMessage()
            ];
        }
    }

    public function getDomainForDns(
        int $domainId
    ): ?array {
        $stmt =
            $this->pdo->prepare("
                SELECT
                    id,
                    user_id,
                    provider_domain_id,
                    subdomain,
                    full_domain,
                    status
                FROM domains
                WHERE id = ?
                LIMIT 1
            ");

        $stmt->execute([
            $domainId
        ]);

        $domain =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$domain) {
            return null;
        }

        $providerDomainId =
            (int)(
                $domain['provider_domain_id']
                ?? 0
            );

        
        if ($providerDomainId > 0) {

            $providerDomain =
                $this->getProviderDomainForExistingDomain(
                    $providerDomainId
                );

            if (!$providerDomain) {
                return null;
            }

            $domain['provider_domain'] =
                $providerDomain;

            return $domain;
        }

        
        $fullDomain =
            strtolower(
                trim(
                    rtrim(
                        (string)$domain[
                            'full_domain'
                        ],
                        '.'
                    )
                )
            );

        if ($fullDomain === '') {
            return null;
        }

        $stmt =
            $this->pdo->query("
                SELECT
                    pd.*,
                    p.type AS provider_type,
                    p.name AS provider_name,
                    p.status AS provider_status
                FROM dns_provider_domains pd
                INNER JOIN dns_providers p
                    ON p.id = pd.provider_id
                WHERE p.status = 1
                ORDER BY
                    CHAR_LENGTH(pd.domain) DESC,
                    pd.id ASC
            ");

        $providerDomains =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

        foreach (
            $providerDomains
            as $providerDomain
        ) {

            $rootDomain =
                strtolower(
                    trim(
                        rtrim(
                            (string)(
                                $providerDomain[
                                    'domain'
                                ]
                            ),
                            '.'
                        )
                    )
                );

            if ($rootDomain === '') {
                continue;
            }

            if (
                $fullDomain ===
                $rootDomain
                ||
                str_ends_with(
                    $fullDomain,
                    '.' . $rootDomain
                )
            ) {

                $domain[
                    'provider_domain'
                ] =
                    $providerDomain;

                $update =
                    $this->pdo->prepare("
                        UPDATE domains
                        SET
                            provider_domain_id = ?,
                            updated_at = NOW()
                        WHERE id = ?
                          AND (
                              provider_domain_id IS NULL
                              OR provider_domain_id = 0
                          )
                    ");

                $update->execute([
                    (int)$providerDomain['id'],
                    $domainId
                ]);

                $domain[
                    'provider_domain_id'
                ] =
                    (int)$providerDomain['id'];

                return $domain;
            }
        }

        return null;
    }

    private function fqdnBelongsToDomain(
        string $fqdn,
        string $rootDomain
    ): bool {
        $fqdn =
            $this->normalizeFqdn(
                $fqdn
            );

        $rootDomain =
            $this->normalizeFqdn(
                $rootDomain
            );

        if (
            $fqdn === ''
            ||
            $rootDomain === ''
        ) {
            return false;
        }

        return
            $fqdn === $rootDomain
            ||
            str_ends_with(
                $fqdn,
                '.' . $rootDomain
            );
    }

    private function normalizeFqdn(
        string $fqdn
    ): string {
        return strtolower(
            trim(
                rtrim(
                    $fqdn,
                    '.'
                )
            )
        );
    }

    public function findRecords(
        string $fqdn
    ): array {
        $fqdn =
            $this->normalizeFqdn(
                $fqdn
            );

        if ($fqdn === '') {

            return [
                'success' => false,
                'message' => '域名不能为空',
                'records' => []
            ];
        }

        $availableDomains =
            $this->getAvailableDomains();

        $matchedDomain =
            null;

        foreach (
            $availableDomains
            as $providerDomain
        ) {

            if (
                $this->fqdnBelongsToDomain(
                    $fqdn,
                    (string)(
                        $providerDomain['domain']
                    )
                )
            ) {

                if (
                    $matchedDomain === null
                    ||
                    strlen(
                        (string)(
                            $providerDomain['domain']
                        )
                    )
                    >
                    strlen(
                        (string)(
                            $matchedDomain['domain']
                        )
                    )
                ) {

                    $matchedDomain =
                        $providerDomain;
                }
            }
        }

        if (!$matchedDomain) {

            return [
                'success' => false,
                'message' =>
                    '未找到对应的 DNS 根域名',
                'records' => []
            ];
        }

        return $this->findRecordsByDomain(
            (int)$matchedDomain['id'],
            $fqdn
        );
    }

    public function findRecordsByDomain(
        int $providerDomainId,
        string $fqdn
    ): array {
        $providerDomain =
            $this->getProviderDomain(
                $providerDomainId
            );

        if (!$providerDomain) {

            return [
                'success' => false,
                'message' =>
                    'DNS 根域名不存在或未启用',
                'records' => []
            ];
        }

        $fqdn =
            $this->normalizeFqdn(
                $fqdn
            );

        if (
            !$this->fqdnBelongsToDomain(
                $fqdn,
                (string)(
                    $providerDomain['domain']
                )
            )
        ) {

            return [
                'success' => false,
                'message' =>
                    '查询域名不属于当前 DNS 根域名',
                'records' => []
            ];
        }

        $provider =
            $this->createProviderByDomain(
                $providerDomain
            );

        if (!$provider) {

            return [
                'success' => false,
                'message' =>
                    'DNS 服务商配置无效',
                'records' => []
            ];
        }

        try {

            return $this->normalizeFindRecordsResult(
                $provider->findRecords(
                    $fqdn
                ),
                (string)$providerDomain['domain']
            );

        } catch (Throwable $e) {

            return [
                'success' => false,
                'message' =>
                    $e->getMessage(),
                'records' => []
            ];
        }
    }

    private function findRecordsForExistingDomain(
        array $providerDomain,
        string $fqdn
    ): array {
        $fqdn =
            $this->normalizeFqdn(
                $fqdn
            );

        if (
            !$this->fqdnBelongsToDomain(
                $fqdn,
                (string)(
                    $providerDomain['domain']
                )
            )
        ) {

            return [
                'success' => false,
                'message' =>
                    '查询域名不属于当前 DNS 根域名',
                'records' => []
            ];
        }

        $provider =
            $this->createProviderByDomain(
                $providerDomain
            );

        if (!$provider) {

            return [
                'success' => false,
                'message' =>
                    'DNS 服务商配置无效',
                'records' => []
            ];
        }

        try {

            return $this->normalizeFindRecordsResult(
                $provider->findRecords(
                    $fqdn
                ),
                (string)$providerDomain['domain']
            );

        } catch (Throwable $e) {

            return [
                'success' => false,
                'message' =>
                    $e->getMessage(),
                'records' => []
            ];
        }
    }

    private function normalizeFindRecordsResult(
        array $result,
        string $rootDomain = ''
    ): array {
        if (
            !empty(
                $result['success']
            )
        ) {

            $records = [];

            if (
                isset(
                    $result['records']
                )
                &&
                is_array(
                    $result['records']
                )
            ) {

                $records =
                    $result['records'];

            } elseif (
                isset(
                    $result['data']['result']
                )
                &&
                is_array(
                    $result['data']['result']
                )
            ) {

                $records =
                    $result['data']['result'];

            } elseif (
                isset(
                    $result['data']['records']
                )
                &&
                is_array(
                    $result['data']['records']
                )
            ) {

                $records =
                    $result['data']['records'];

            } elseif (
                isset(
                    $result['data']
                )
                &&
                is_array(
                    $result['data']
                )
                &&
                $this->looksLikeRecordList(
                    $result['data']
                )
            ) {

                $records =
                    $result['data'];
            }

            $normalized = [];

            foreach (
                $records
                as $record
            ) {

                if (!is_array($record)) {
                    continue;
                }

                $normalized[] =
                    $this->normalizeRecord(
                        $record,
                        $rootDomain
                    );
            }

            $result['records'] =
                $normalized;

            return $result;
        }

        if (
            isset(
                $result['records']
            )
            &&
            is_array(
                $result['records']
            )
        ) {

            foreach (
                $result['records']
                as $index => $record
            ) {

                if (
                    is_array($record)
                ) {

                    $result['records'][$index] =
                        $this->normalizeRecord(
                            $record,
                            $rootDomain
                        );
                }
            }
        }

        return $result;
    }

    private function looksLikeRecordList(
        array $data
    ): bool {
        if (empty($data)) {
            return false;
        }

        $first =
            reset($data);

        if (!is_array($first)) {
            return false;
        }

        return
            isset($first['id'])
            ||
            isset($first['RecordId'])
            ||
            isset($first['name'])
            ||
            isset($first['RR'])
            ||
            isset($first['Type'])
            ||
            isset($first['type']);
    }

    private function normalizeRecord(
        array $record,
        string $rootDomain = ''
    ): array {
        $id =
            $this->getRecordId(
                $record
            );

        $type =
            $this->getRecordType(
                $record
            );

        $content =
            $this->getRecordContent(
                $record
            );

        $name =
            '';

        if (
            isset(
                $record['name']
            )
        ) {

            $name =
                (string)$record['name'];

        } elseif (
            isset(
                $record['Name']
            )
        ) {

            $name =
                (string)$record['Name'];

        } elseif (
            isset(
                $record['RR']
            )
        ) {

            $name =
                (string)$record['RR'];

        } elseif (
            isset(
                $record['host_record']
            )
        ) {

            $name =
                (string)$record['host_record'];
        }

        $name =
            trim($name);

        if (
            $name === ''
            ||
            $name === '@'
        ) {

            $normalizedName =
                '@';

        } else {

            $normalizedName =
                $name;

            if (
                $rootDomain !== ''
                &&
                $this->normalizeFqdn(
                    $name
                ) !==
                $this->normalizeFqdn(
                    $rootDomain
                )
                &&
                str_ends_with(
                    $this->normalizeFqdn(
                        $name
                    ),
                    '.' .
                    $this->normalizeFqdn(
                        $rootDomain
                    )
                )
            ) {

                $normalizedName =
                    substr(
                        $name,
                        0,
                        -(
                            strlen(
                                $rootDomain
                            ) + 1
                        )
                    );
            }
        }

        return [
            'id' =>
                $id,

            'type' =>
                strtoupper($type),

            'name' =>
                $normalizedName,

            'content' =>
                $content,

            'ttl' =>
                (int)(
                    $record['ttl']
                    ??
                    $record['TTL']
                    ??
                    300
                ),

            'proxied' =>
                !empty(
                    $record['proxied']
                    ??
                    $record['Proxied']
                    ??
                    false
                ),

            'raw' =>
                $record
        ];
    }

    private function getRecordId(
        array $record
    ): string {
        foreach (
            [
                'id',
                'record_id',
                'RecordId',
                'recordId',
                'provider_record_id'
            ]
            as $key
        ) {

            if (
                isset(
                    $record[$key]
                )
                &&
                trim(
                    (string)$record[$key]
                ) !== ''
            ) {

                return trim(
                    (string)$record[$key]
                );
            }
        }

        return '';
    }

    private function getRecordType(
        array $record
    ): string {
        foreach (
            [
                'type',
                'Type',
                'record_type',
                'RecordType'
            ]
            as $key
        ) {

            if (
                isset(
                    $record[$key]
                )
            ) {

                return strtoupper(
                    trim(
                        (string)$record[$key]
                    )
                );
            }
        }

        return '';
    }

    private function getRecordContent(
        array $record
    ): string {
        foreach (
            [
                'content',
                'Content',
                'value',
                'Value'
            ]
            as $key
        ) {

            if (
                isset(
                    $record[$key]
                )
            ) {

                return trim(
                    (string)$record[$key]
                );
            }
        }

        return '';
    }

    private function buildRecordFqdn(
        array $record,
        string $rootDomain
    ): string {
        $name =
            trim(
                (string)(
                    $record['name']
                    ?? ''
                )
            );

        $rootDomain =
            $this->normalizeFqdn(
                $rootDomain
            );

        if (
            $name === ''
            ||
            $name === '@'
        ) {

            return $rootDomain;
        }

        $name =
            $this->normalizeFqdn(
                $name
            );

        if (
            $name === $rootDomain
        ) {

            return $rootDomain;
        }

        if (
            str_ends_with(
                $name,
                '.' . $rootDomain
            )
        ) {

            return $name;
        }

        return
            $name .
            '.' .
            $rootDomain;
    }

    private function recordNameEqualsFqdn(
        array $record,
        string $fqdn,
        string $rootDomain
    ): bool {
        return
            $this->buildRecordFqdn(
                $record,
                $rootDomain
            )
            ===
            $this->normalizeFqdn(
                $fqdn
            );
    }

    private function filterRemoteRecordsByFqdn(
        array $records,
        string $fqdn,
        string $rootDomain
    ): array {
        $filtered = [];

        foreach (
            $records
            as $record
        ) {

            if (!is_array($record)) {
                continue;
            }

            if (
                $this->recordNameEqualsFqdn(
                    $record,
                    $fqdn,
                    $rootDomain
                )
            ) {

                $filtered[] =
                    $record;
            }
        }

        return $filtered;
    }

    private function getRemoteRecords(
        string $fqdn,
        array $providerDomain
    ): array {
        return $this->findRecordsForExistingDomain(
            $providerDomain,
            $fqdn
        );
    }

    public function createRecord(
        string $fqdn,
        string $type,
        string $content,
        int $ttl = 300,
        bool $proxied = false
    ): array {
        $fqdn =
            $this->normalizeFqdn(
                $fqdn
            );

        if ($fqdn === '') {

            return [
                'success' => false,
                'message' => '域名不能为空'
            ];
        }

        $availableDomains =
            $this->getAvailableDomains();

        $matchedDomain =
            null;

        foreach (
            $availableDomains
            as $providerDomain
        ) {

            if (
                $this->fqdnBelongsToDomain(
                    $fqdn,
                    (string)(
                        $providerDomain['domain']
                    )
                )
            ) {

                if (
                    $matchedDomain === null
                    ||
                    strlen(
                        (string)(
                            $providerDomain['domain']
                        )
                    )
                    >
                    strlen(
                        (string)(
                            $matchedDomain['domain']
                        )
                    )
                ) {

                    $matchedDomain =
                        $providerDomain;
                }
            }
        }

        if (!$matchedDomain) {

            return [
                'success' => false,
                'message' =>
                    '未找到对应的 DNS 根域名'
            ];
        }

        return $this->createRecordByDomainId(
            (int)$matchedDomain['id'],
            $fqdn,
            $type,
            $content,
            $ttl,
            $proxied
        );
    }

    public function createRecordByDomainId(
    int $providerDomainId,
    string $fqdn,
    string $type,
    string $content,
    int $ttl = 300,
    bool $proxied = false
): array {
    
    $providerDomain =
        $this->getProviderDomain(
            $providerDomainId
        );

    if (!$providerDomain) {

        return [
            'success' => false,
            'message' =>
                'DNS 根域名不存在或未启用'
        ];
    }

    $fqdn =
        $this->normalizeFqdn(
            $fqdn
        );

    if (
        !$this->fqdnBelongsToDomain(
            $fqdn,
            (string)(
                $providerDomain['domain']
            )
        )
    ) {

        return [
            'success' => false,
            'message' =>
                '当前域名不属于指定 DNS 根域名'
        ];
    }

    $provider =
        $this->createProviderByDomain(
            $providerDomain
        );

    if (!$provider) {

        return [
            'success' => false,
            'message' =>
                'DNS 服务商配置无效'
        ];
    }

    try {

        $result =
            $provider->createRecord(
                $fqdn,
                strtoupper($type),
                $content,
                $ttl,
                $proxied
            );

        if (
            empty(
                $result['success']
            )
        ) {

            return $result;
        }

        
        $recordId = trim(
            (string)(
                $result['record_id']
                ??
                $result['id']
                ??
                $result['RecordId']
                ??
                ($result['record']['id'] ?? '')
                ??
                ($result['record']['RecordId'] ?? '')
                ??
                ($result['data']['result']['id'] ?? '')
                ??
                ($result['data']['result']['RecordId'] ?? '')
                ??
                ($result['data']['RecordId'] ?? '')
                ??
                ''
            )
        );

        if ($recordId === '') {

            return [
                'success' => false,
                'message' =>
                    'DNS 记录创建成功, 但未获取到远程记录 ID',
                'data' =>
                    $result['data']
                    ?? $result
            ];
        }

        $result['record_id'] =
            $recordId;

        return $result;

    } catch (Throwable $e) {

        return [
            'success' => false,
            'message' =>
                $e->getMessage()
        ];
    }
}

    public function updateRecord(
        string $id,
        string $fqdn,
        string $type,
        string $content,
        int $ttl = 300,
        bool $proxied = false
    ): array {
        $id =
            trim($id);

        if ($id === '') {

            return [
                'success' => false,
                'message' =>
                    'DNS 记录 ID 不能为空'
            ];
        }

        $stmt =
            $this->pdo->prepare("
                SELECT
                    dr.id,
                    dr.provider_record_id,
                    d.provider_domain_id
                FROM domain_records dr
                INNER JOIN domains d
                    ON d.id = dr.domain_id
                WHERE dr.provider_record_id = ?
                LIMIT 1
            ");

        $stmt->execute([
            $id
        ]);

        $record =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$record) {

            return [
                'success' => false,
                'message' =>
                    '本地 DNS 记录不存在'
            ];
        }

        $providerDomainId =
            (int)(
                $record[
                    'provider_domain_id'
                ]
                ?? 0
            );

        if (
            $providerDomainId <= 0
        ) {

            return [
                'success' => false,
                'message' =>
                    'DNS 记录没有绑定 DNS 根域名'
            ];
        }

        return $this->updateRecordByDomain(
            $providerDomainId,
            $id,
            $fqdn,
            $type,
            $content,
            $ttl,
            $proxied
        );
    }

    public function updateRecordByDomain(
        int $providerDomainId,
        string $id,
        string $fqdn,
        string $type,
        string $content,
        int $ttl = 300,
        bool $proxied = false
    ): array {
        $providerDomain =
            $this->getProviderDomainForExistingDomain(
                $providerDomainId
            );

        if (!$providerDomain) {

            return [
                'success' => false,
                'message' =>
                    'DNS 根域名不存在或服务商已停用'
            ];
        }

        $provider =
            $this->createProviderByDomain(
                $providerDomain
            );

        if (!$provider) {

            return [
                'success' => false,
                'message' =>
                    'DNS 服务商配置无效'
            ];
        }

        try {

            return $provider->updateRecord(
                $id,
                $this->normalizeFqdn(
                    $fqdn
                ),
                strtoupper($type),
                $content,
                $ttl,
                $proxied
            );

        } catch (Throwable $e) {

            return [
                'success' => false,
                'message' =>
                    $e->getMessage()
            ];
        }
    }

    
    public function deleteRecord(
    string $id
): array {
    $id = trim($id);

    if ($id === '' || !ctype_digit($id)) {
        return [
            'success' => false,
            'message' => '无效的 DNS 记录 ID'
        ];
    }

    $localRecordId = (int)$id;

    
    $stmt = $this->pdo->prepare("
        SELECT
            dr.*,
            d.provider_domain_id,
            d.full_domain
        FROM domain_records dr
        INNER JOIN domains d
            ON d.id = dr.domain_id
        WHERE dr.id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $localRecordId
    ]);

    $localRecord = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$localRecord) {
        return [
            'success' => false,
            'message' => 'DNS 记录不存在'
        ];
    }

    $providerDomainId =
        (int)(
            $localRecord['provider_domain_id']
            ?? 0
        );

    if ($providerDomainId <= 0) {
        return [
            'success' => false,
            'message' => 'DNS 记录没有绑定 DNS 根域名'
        ];
    }

    
    $providerDomain =
        $this->getProviderDomainForExistingDomain(
            $providerDomainId
        );

    if (!$providerDomain) {
        return [
            'success' => false,
            'message' => 'DNS 根域名不存在或服务商已停用'
        ];
    }

    $provider =
        $this->createProviderByDomain(
            $providerDomain
        );

    if (!$provider) {
        return [
            'success' => false,
            'message' => 'DNS 服务商配置无效'
        ];
    }

    
    $providerRecordId =
        trim(
            (string)(
                $localRecord['provider_record_id']
                ?? ''
            )
        );

    
    if ($providerRecordId === '') {

        $recordName =
            trim(
                (string)(
                    $localRecord['name']
                    ?? '@'
                )
            );

        $fullDomain =
            trim(
                (string)(
                    $localRecord['full_domain']
                    ?? ''
                )
            );

        if ($fullDomain === '') {
            return [
                'success' => false,
                'message' => 'DNS 记录缺少完整域名'
            ];
        }

        
        if (
            $recordName === ''
            ||
            $recordName === '@'
        ) {
            $fqdn = $fullDomain;
        } else {
            $fqdn =
                $recordName .
                '.' .
                $fullDomain;
        }

        
        try {

            $findResult =
                $provider->findRecords(
                    $fqdn
                );

        } catch (Throwable $e) {

            return [
                'success' => false,
                'message' =>
                    '查询远程 DNS 记录失败：' .
                    $e->getMessage()
            ];
        }

        
        $records =
            $this->normalizeFindRecordsResult(
                $findResult
            );

        
        $localType =
            strtoupper(
                trim(
                    (string)(
                        $localRecord['type']
                        ?? ''
                    )
                )
            );

        $localContent =
            trim(
                (string)(
                    $localRecord['content']
                    ?? ''
                )
            );

        
        foreach ($records as $remoteRecord) {

            $remoteType =
                strtoupper(
                    trim(
                        (string)(
                            $remoteRecord['type']
                            ??
                            $remoteRecord['Type']
                            ??
                            ''
                        )
                    )
                );

            $remoteContent =
                trim(
                    (string)(
                        $remoteRecord['content']
                        ??
                        $remoteRecord['Content']
                        ??
                        $remoteRecord['value']
                        ??
                        $remoteRecord['Value']
                        ??
                        ''
                    )
                );

            
            $remoteId =
                trim(
                    (string)(
                        $remoteRecord['id']
                        ??
                        $remoteRecord['record_id']
                        ??
                        $remoteRecord['RecordId']
                        ??
                        ''
                    )
                );

            
            if (
                $remoteType !== $localType
                ||
                $remoteContent !== $localContent
            ) {
                continue;
            }

            if ($remoteId !== '') {
                $providerRecordId =
                    $remoteId;

                break;
            }
        }

        
        if ($providerRecordId === '') {

            
            if (
                isset($findResult['success'])
                &&
                $findResult['success'] === true
                &&
                count($records) === 0
            ) {

                $update =
                    $this->pdo->prepare("
                        UPDATE domain_records
                        SET
                            provider_record_id = NULL,
                            updated_at = NOW()
                        WHERE id = ?
                    ");

                $update->execute([
                    $localRecordId
                ]);

                return [
                    'success' => true,
                    'message' => 'DNS 记录已不存在'
                ];
            }

            return [
                'success' => false,
                'message' =>
                    '未找到对应的远程 DNS 记录'
            ];
        }
    }

    
    try {

        $result =
            $this->deleteRecordByDomain(
                $providerDomainId,
                $providerRecordId
            );

    } catch (Throwable $e) {

        return [
            'success' => false,
            'message' =>
                '删除远程 DNS 失败：' .
                $e->getMessage()
        ];
    }

    
    if (
        empty(
            $result['success']
        )
    ) {

        $message =
            (string)(
                $result['message']
                ?? ''
            );

        if (
            $this->isRecordMissingMessage(
                $message
            )
        ) {

            $update =
                $this->pdo->prepare("
                    UPDATE domain_records
                    SET
                        provider_record_id = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ");

            $update->execute([
                $localRecordId
            ]);

            return [
                'success' => true,
                'message' => 'DNS 记录已不存在'
            ];
        }

        return $result;
    }

    
    $update =
        $this->pdo->prepare("
            UPDATE domain_records
            SET
                provider_record_id = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");

    $update->execute([
        $localRecordId
    ]);

    return $result;
}

    public function deleteRecordByDomain(
        int $providerDomainId,
        string $id
    ): array {
        $providerDomain =
            $this->getProviderDomainForExistingDomain(
                $providerDomainId
            );

        if (!$providerDomain) {

            return [
                'success' => false,
                'message' =>
                    'DNS 根域名不存在或服务商已停用'
            ];
        }

        $provider =
            $this->createProviderByDomain(
                $providerDomain
            );

        if (!$provider) {

            return [
                'success' => false,
                'message' =>
                    'DNS 服务商配置无效'
            ];
        }

        $id =
            trim($id);

        if ($id === '') {

            return [
                'success' => false,
                'message' =>
                    '远程 DNS 记录 ID 不能为空'
            ];
        }

        try {

            $result =
                $provider->deleteRecord(
                    $id
                );

            if (
                !empty(
                    $result['success']
                )
            ) {

                return $result;
            }

            $message =
                (string)(
                    $result['message']
                    ?? ''
                );

            if (
                $this->isRecordMissingMessage(
                    $message
                )
            ) {

                return [
                    'success' => true,
                    'message' =>
                        'DNS 记录已不存在'
                ];
            }

            return $result;

        } catch (Throwable $e) {

            return [
                'success' => false,
                'message' =>
                    $e->getMessage()
            ];
        }
    }

    private function isRecordMissingMessage(
        string $message
    ): bool {
        $message =
            strtolower(
                trim($message)
            );

        if ($message === '') {
            return false;
        }

        foreach (
            [
                'not found',
                'does not exist',
                'record not found',
                'recordnotfound',
                'recorddoesnotexist',
                '不存在'
            ]
            as $keyword
        ) {

            if (
                str_contains(
                    $message,
                    strtolower($keyword)
                )
            ) {

                return true;
            }
        }

        return false;
    }

    public function suspendDomain(
    int $domainId
): array {
    $domain =
        $this->getDomainForDns(
            $domainId
        );

    if (!$domain) {

        return [
            'success' => false,
            'message' =>
                '域名不存在或 DNS 服务商不可用'
        ];
    }

    $providerDomain =
        $domain['provider_domain']
        ?? null;

    if (
        !is_array(
            $providerDomain
        )
    ) {

        return [
            'success' => false,
            'message' =>
                '域名没有绑定 DNS 根域名'
        ];
    }

    $provider =
        $this->createProviderByDomain(
            $providerDomain
        );

    if (!$provider) {

        return [
            'success' => false,
            'message' =>
                'DNS 服务商配置无效'
        ];
    }

    $fullDomain =
        $this->normalizeFqdn(
            (string)$domain[
                'full_domain'
            ]
        );

    $rootDomain =
        $this->normalizeFqdn(
            (string)$providerDomain[
                'domain'
            ]
        );

    
    $stmt =
        $this->pdo->prepare("
            SELECT *
            FROM domain_records
            WHERE domain_id = ?
            ORDER BY id ASC
        ");

    $stmt->execute([
        $domainId
    ]);

    $localRecords =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    if (!$localRecords) {

        return [
            'success' => true,
            'message' =>
                '该域名没有 DNS 记录',
            'deleted' => 0,
            'already_missing' => 0
        ];
    }

    $deleted = 0;

    $alreadyMissing = 0;

    $errors = [];

    foreach (
        $localRecords
        as $localRecord
    ) {

        $localRecordId =
            (int)(
                $localRecord['id']
                ?? 0
            );

        $type =
            strtoupper(
                trim(
                    (string)(
                        $localRecord['type']
                        ?? ''
                    )
                )
            );

        $content =
            trim(
                (string)(
                    $localRecord['content']
                    ?? ''
                )
            );

        $name =
            trim(
                (string)(
                    $localRecord['name']
                    ?? '@'
                )
            );

        
        if (
            $name === ''
            ||
            $name === '@'
        ) {

            $fqdn =
                $fullDomain;

        } else {

            $fqdn =
                $this->normalizeFqdn(
                    $name .
                    '.' .
                    $fullDomain
                );
        }

        
        $providerRecordId =
            trim(
                (string)(
                    $localRecord[
                        'provider_record_id'
                    ]
                    ?? ''
                )
            );

        
        if (
            $providerRecordId === ''
        ) {

            try {

                $findResult =
                    $provider->findRecords(
                        $fqdn
                    );

            } catch (Throwable $e) {

                $errors[] =
                    $fqdn .
                    '：查询远程 DNS 失败：' .
                    $e->getMessage();

                continue;
            }

            if (
                empty(
                    $findResult['success']
                )
            ) {

                $errors[] =
                    $fqdn .
                    '：' .
                    (string)(
                        $findResult['message']
                        ?? '查询远程 DNS 失败'
                    );

                continue;
            }

            $records =
                $this->normalizeFindRecordsResult(
                    $findResult
                );

            foreach (
                $records
                as $remoteRecord
            ) {

                if (!is_array($remoteRecord)) {
                    continue;
                }

                $remoteType =
                    $this->getRecordType(
                        $remoteRecord
                    );

                $remoteContent =
                    $this->getRecordContent(
                        $remoteRecord
                    );

                if (
                    $remoteType !== $type
                    ||
                    $remoteContent !== $content
                ) {
                    continue;
                }

                $remoteId =
                    $this->getRecordId(
                        $remoteRecord
                    );

                if (
                    $remoteId !== ''
                ) {

                    $providerRecordId =
                        $remoteId;

                    break;
                }
            }
        }

        
        if (
            $providerRecordId === ''
        ) {

            $alreadyMissing++;

            
            if (
                $localRecordId > 0
            ) {

                $update =
                    $this->pdo->prepare("
                        UPDATE domain_records
                        SET
                            provider_record_id = NULL,
                            updated_at = NOW()
                        WHERE id = ?
                    ");

                $update->execute([
                    $localRecordId
                ]);
            }

            continue;
        }

        
        try {

            $result =
                $this->deleteRecordByDomain(
                    (int)(
                        $providerDomain['id']
                    ),
                    $providerRecordId
                );

        } catch (Throwable $e) {

            $errors[] =
                $fqdn .
                '：删除远程 DNS 失败：' .
                $e->getMessage();

            continue;
        }

        if (
            !empty(
                $result['success']
            )
        ) {

            $deleted++;

            if (
                $localRecordId > 0
            ) {

                $update =
                    $this->pdo->prepare("
                        UPDATE domain_records
                        SET
                            provider_record_id = NULL,
                            updated_at = NOW()
                        WHERE id = ?
                    ");

                $update->execute([
                    $localRecordId
                ]);
            }

            continue;
        }

        $message =
            (string)(
                $result['message']
                ?? ''
            );

        
        if (
            $this->isRecordMissingMessage(
                $message
            )
        ) {

            $alreadyMissing++;

            if (
                $localRecordId > 0
            ) {

                $update =
                    $this->pdo->prepare("
                        UPDATE domain_records
                        SET
                            provider_record_id = NULL,
                            updated_at = NOW()
                        WHERE id = ?
                    ");

                $update->execute([
                    $localRecordId
                ]);
            }

            continue;
        }

        $errors[] =
            $fqdn .
            '：' .
            $message;
    }

    if ($errors) {

        return [
            'success' => false,
            'message' =>
                'DNS 暂停失败：' .
                implode(
                    '; ',
                    $errors
                ),
            'deleted' =>
                $deleted,
            'already_missing' =>
                $alreadyMissing
        ];
    }

    return [
        'success' => true,
        'message' =>
            'DNS 记录已暂停',
        'deleted' =>
            $deleted,
        'already_missing' =>
            $alreadyMissing
    ];
}

    public function resumeDomain(
    int $domainId
): array {
    $domain =
        $this->getDomainForDns(
            $domainId
        );

    if (!$domain) {

        return [
            'success' => false,
            'message' =>
                '域名不存在或 DNS 服务商不可用'
        ];
    }

    $providerDomain =
        $domain['provider_domain']
        ?? null;

    if (
        !is_array(
            $providerDomain
        )
    ) {

        return [
            'success' => false,
            'message' =>
                '域名没有绑定 DNS 根域名'
        ];
    }

    $provider =
        $this->createProviderByDomain(
            $providerDomain
        );

    if (!$provider) {

        return [
            'success' => false,
            'message' =>
                'DNS 服务商配置无效'
        ];
    }

    $fullDomain =
        $this->normalizeFqdn(
            (string)$domain[
                'full_domain'
            ]
        );

    $rootDomain =
        $this->normalizeFqdn(
            (string)$providerDomain[
                'domain'
            ]
        );

    
    $stmt =
        $this->pdo->prepare("
            SELECT *
            FROM domain_records
            WHERE domain_id = ?
            ORDER BY id ASC
        ");

    $stmt->execute([
        $domainId
    ]);

    $localRecords =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    if (!$localRecords) {

        return [
            'success' => true,
            'message' =>
                '该域名没有需要恢复的 DNS 记录',
            'created' => 0,
            'skipped' => 0
        ];
    }

    $created = 0;

    $skipped = 0;

    $errors = [];

    foreach (
        $localRecords
        as $localRecord
    ) {

        $localRecordId =
            (int)(
                $localRecord['id']
                ?? 0
            );

        $type =
            strtoupper(
                trim(
                    (string)(
                        $localRecord['type']
                        ?? ''
                    )
                )
            );

        $name =
            trim(
                (string)(
                    $localRecord['name']
                    ?? '@'
                )
            );

        $content =
            trim(
                (string)(
                    $localRecord['content']
                    ?? ''
                )
            );

        $ttl =
            (int)(
                $localRecord['ttl']
                ?? 300
            );

        $proxied =
            !empty(
                $localRecord['proxied']
            );

        
        if (
            $name === ''
            ||
            $name === '@'
        ) {

            $fqdn =
                $fullDomain;

        } else {

            $fqdn =
                $this->normalizeFqdn(
                    $name .
                    '.' .
                    $fullDomain
                );
        }

        
        try {

            $findResult =
                $provider->findRecords(
                    $fqdn
                );

        } catch (Throwable $e) {

            $errors[] =
                $fqdn .
                '：查询远程 DNS 失败：' .
                $e->getMessage();

            continue;
        }

        if (
            empty(
                $findResult['success']
            )
        ) {

            $errors[] =
                $fqdn .
                '：' .
                (string)(
                    $findResult['message']
                    ?? '查询远程 DNS 失败'
                );

            continue;
        }

        $remoteRecords =
            $this->normalizeFindRecordsResult(
                $findResult
            );

        $remoteRecordId = '';

        
        foreach (
            $remoteRecords
            as $remoteRecord
        ) {

            if (!is_array($remoteRecord)) {
                continue;
            }

            $remoteType =
                $this->getRecordType(
                    $remoteRecord
                );

            $remoteContent =
                $this->getRecordContent(
                    $remoteRecord
                );

            if (
                $remoteType !== $type
                ||
                $remoteContent !== $content
            ) {
                continue;
            }

            if (
                !$this->recordNameEqualsFqdn(
                    $remoteRecord,
                    $fqdn,
                    $rootDomain
                )
            ) {
                continue;
            }

            $remoteRecordId =
                $this->getRecordId(
                    $remoteRecord
                );

            if (
                $remoteRecordId !== ''
            ) {
                break;
            }
        }

        
        if (
            $remoteRecordId !== ''
        ) {

            if (
                $localRecordId > 0
            ) {

                $update =
                    $this->pdo->prepare("
                        UPDATE domain_records
                        SET
                            provider_record_id = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");

                $update->execute([
                    $remoteRecordId,
                    $localRecordId
                ]);
            }

            $skipped++;

            continue;
        }

        
        try {

            $result =
                $this->createRecordByDomainId(
                    (int)(
                        $providerDomain['id']
                    ),
                    $fqdn,
                    $type,
                    $content,
                    $ttl,
                    $proxied
                );

        } catch (Throwable $e) {

            $errors[] =
                $fqdn .
                '：' .
                $e->getMessage();

            continue;
        }

        if (
            empty(
                $result['success']
            )
        ) {

            $errors[] =
                $fqdn .
                '：' .
                (string)(
                    $result['message']
                    ?? 'DNS 创建失败'
                );

            continue;
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

            $errors[] =
                $fqdn .
                '：DNS 创建成功, 但未获取到远程记录 ID';

            continue;
        }

        
        if (
            $localRecordId > 0
        ) {

            $update =
                $this->pdo->prepare("
                    UPDATE domain_records
                    SET
                        provider_record_id = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");

            $update->execute([
                $providerRecordId,
                $localRecordId
            ]);
        }

        $created++;
    }

    if ($errors) {

        return [
            'success' => false,
            'message' =>
                'DNS 恢复失败：' .
                implode(
                    '; ',
                    $errors
                ),
            'created' =>
                $created,
            'skipped' =>
                $skipped
        ];
    }

    return [
        'success' => true,
        'message' =>
            'DNS 记录已恢复',
        'created' =>
            $created,
        'skipped' =>
            $skipped
    ];
}

    private function deleteAllRemoteRecords(
        DnsProviderInterface $provider,
        array $records
    ): array {
        $deleted = 0;

        $alreadyMissing = 0;

        $errors = [];

        foreach (
            $records
            as $record
        ) {

            if (!is_array($record)) {
                continue;
            }

            $recordId =
                $this->getRecordId(
                    $record
                );

            if (
                $recordId === ''
            ) {
                continue;
            }

            try {

                $result =
                    $provider->deleteRecord(
                        $recordId
                    );

                if (
                    !empty(
                        $result['success']
                    )
                ) {

                    $deleted++;
                    continue;
                }

                $message =
                    (string)(
                        $result['message']
                        ?? ''
                    );

                if (
                    $this->isRecordMissingMessage(
                        $message
                    )
                ) {

                    $alreadyMissing++;
                    continue;
                }

                $errors[] =
                    '记录 ' .
                    $recordId .
                    '：' .
                    $message;

            } catch (Throwable $e) {

                $errors[] =
                    '记录 ' .
                    $recordId .
                    '：' .
                    $e->getMessage();
            }
        }

        return [
            'success' =>
                empty($errors),

            'deleted' =>
                $deleted,

            'already_missing' =>
                $alreadyMissing,

            'errors' =>
                $errors
        ];
    }

    public function deleteDomain(
        int $domainId
    ): array {
        $domain =
            $this->getDomainForDns(
                $domainId
            );

        if (!$domain) {

            return [
                'success' => false,
                'message' =>
                    '域名不存在或 DNS 服务商不可用'
            ];
        }

        $providerDomain =
            $domain['provider_domain']
            ?? null;

        if (
            !is_array(
                $providerDomain
            )
        ) {

            return [
                'success' => false,
                'message' =>
                    '域名没有绑定 DNS 根域名'
            ];
        }

        $provider =
            $this->createProviderByDomain(
                $providerDomain
            );

        if (!$provider) {

            return [
                'success' => false,
                'message' =>
                    'DNS 服务商配置无效'
            ];
        }

        $fullDomain =
            $this->normalizeFqdn(
                (string)$domain[
                    'full_domain'
                ]
            );

        $rootDomain =
            $this->normalizeFqdn(
                (string)$providerDomain[
                    'domain'
                ]
            );

        $remoteResult =
            $this->getRemoteRecords(
                $fullDomain,
                $providerDomain
            );

        if (
            empty(
                $remoteResult['success']
            )
        ) {

            return [
                'success' => false,
                'message' =>
                    'DNS 删除失败，无法查询远程 DNS：' .
                    (string)(
                        $remoteResult[
                            'message'
                        ]
                        ?? 'DNS 查询失败'
                    )
            ];
        }

        $remoteRecords =
            $this->filterRemoteRecordsByFqdn(
                $remoteResult['records'],
                $fullDomain,
                $rootDomain
            );

        $deleteResult =
            $this->deleteAllRemoteRecords(
                $provider,
                $remoteRecords
            );

        if (
            empty(
                $deleteResult['success']
            )
        ) {

            return [
                'success' => false,
                'message' =>
                    'DNS 删除失败：' .
                    implode(
                        '; ',
                        $deleteResult['errors']
                    ),
                'deleted' =>
                    (int)(
                        $deleteResult['deleted']
                    )
            ];
        }

        $verifyResult =
            $this->getRemoteRecords(
                $fullDomain,
                $providerDomain
            );

        if (
            empty(
                $verifyResult['success']
            )
        ) {

            return [
                'success' => false,
                'message' =>
                    'DNS 删除失败，无法确认远程记录状态：' .
                    (string)(
                        $verifyResult[
                            'message'
                        ]
                        ?? 'DNS 查询失败'
                    ),
                'deleted' =>
                    (int)(
                        $deleteResult['deleted']
                    )
            ];
        }

        $remaining =
            $this->filterRemoteRecordsByFqdn(
                $verifyResult['records'],
                $fullDomain,
                $rootDomain
            );

        if ($remaining) {

            $remainingIds = [];

            foreach (
                $remaining
                as $record
            ) {

                $id =
                    $this->getRecordId(
                        $record
                    );

                if (
                    $id !== ''
                ) {

                    $remainingIds[] =
                        $id;
                }
            }

            return [
                'success' => false,
                'message' =>
                    'DNS 删除失败，远程仍存在当前域名的 DNS 记录' .
                    (
                        $remainingIds
                            ? '：' .
                              implode(
                                  ', ',
                                  $remainingIds
                              )
                            : ''
                    ),
                'deleted' =>
                    (int)(
                        $deleteResult['deleted']
                    )
            ];
        }

        
        $update =
            $this->pdo->prepare("
                UPDATE domain_records
                SET
                    provider_record_id = NULL,
                    updated_at = NOW()
                WHERE domain_id = ?
            ");

        $update->execute([
            $domainId
        ]);

        return [
            'success' => true,
            'message' =>
                '域名 DNS 记录已删除',
            'deleted' =>
                (int)(
                    $deleteResult['deleted']
                )
        ];
    }

    public function hasRecordByDomain(
        string $fqdn,
        int $providerDomainId
    ): bool {
        $providerDomain =
            $this->getProviderDomain(
                $providerDomainId
            );

        if (!$providerDomain) {

            throw new RuntimeException(
                'DNS 根域名不存在或未启用'
            );
        }

        $fqdn =
            $this->normalizeFqdn(
                $fqdn
            );

        if (
            !$this->fqdnBelongsToDomain(
                $fqdn,
                (string)(
                    $providerDomain['domain']
                )
            )
        ) {

            throw new RuntimeException(
                '查询域名不属于当前 DNS 根域名'
            );
        }

        $provider =
            $this->createProviderByDomain(
                $providerDomain
            );

        if (!$provider) {

            throw new RuntimeException(
                '无法创建 DNS 服务商实例'
            );
        }

        $result =
            $this->normalizeFindRecordsResult(
                $provider->findRecords(
                    $fqdn
                ),
                (string)(
                    $providerDomain['domain']
                )
            );

        if (
            !isset(
                $result['success']
            )
        ) {

            throw new RuntimeException(
                'DNS 服务商返回格式无效'
            );
        }

        if (
            empty(
                $result['success']
            )
        ) {

            throw new RuntimeException(
                (string)(
                    $result['message']
                    ?? 'DNS 查询失败'
                )
            );
        }

        $records =
            $result['records']
            ?? [];

        if (
            !is_array(
                $records
            )
        ) {

            return false;
        }

        foreach (
            $records
            as $record
        ) {

            if (!is_array($record)) {
                continue;
            }

            if (
                $this->recordNameEqualsFqdn(
                    $record,
                    $fqdn,
                    (string)(
                        $providerDomain['domain']
                    )
                )
            ) {

                return true;
            }
        }

        return false;
    }

    private function decrypt(
        string $value
    ): string {
        if ($value === '') {
            return '';
        }

        $key =
            hash(
                'sha256',
                $this->appKey,
                true
            );

        $decoded =
            base64_decode(
                $value,
                true
            );

        if (
            $decoded === false
            ||
            strlen($decoded) < 17
        ) {

            return $value;
        }

        $iv =
            substr(
                $decoded,
                0,
                16
            );

        $ciphertext =
            substr(
                $decoded,
                16
            );

        $plain =
            openssl_decrypt(
                $ciphertext,
                'AES-256-CBC',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

        return $plain === false
            ? $value
            : $plain;
    }

    private function encrypt(
        string $value
    ): string {
        $key =
            hash(
                'sha256',
                $this->appKey,
                true
            );

        $iv =
            random_bytes(16);

        $ciphertext =
            openssl_encrypt(
                $value,
                'AES-256-CBC',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

        if ($ciphertext === false) {

            throw new RuntimeException(
                '配置加密失败'
            );
        }

        return base64_encode(
            $iv . $ciphertext
        );
    }
}