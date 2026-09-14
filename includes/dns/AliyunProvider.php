<?php

declare(strict_types=1);

require_once __DIR__ . '/DnsProviderInterface.php';

class AliyunProvider implements DnsProviderInterface
{
    private array $config;

    private string $defaultEndpoint =
        'alidns.cn-hangzhou.aliyuncs.com';

    public function __construct(
        array $config
    ) {
        $this->config = $config;
    }

    public function testConnection(): array
    {
        $accessKeyId =
            trim(
                $this->config['access_key_id']
                ?? ''
            );

        $accessKeySecret =
            trim(
                $this->config['access_key_secret']
                ?? ''
            );

        $domain =
            trim(
                $this->config['domain']
                ?? ''
            );

        if ($accessKeyId === '') {

            return [
                'success' => false,
                'message' =>
                    '阿里云 AccessKey ID 未配置',
            ];
        }

        if ($accessKeySecret === '') {

            return [
                'success' => false,
                'message' =>
                    '阿里云 AccessKey Secret 未配置',
            ];
        }

        if ($domain === '') {

            return [
                'success' => false,
                'message' =>
                    '阿里云 DNS 域名未配置',
            ];
        }

        $result =
            $this->request(
                'DescribeDomainInfo',
                [
                    'DomainName' =>
                        $domain,

                    'NeedDetailAttributes' =>
                        'true',
                ]
            );

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,

            'message' =>
                '阿里云 DNS 连接成功',

            'data' =>
                $result['data']
                ?? null,
        ];
    }
public function listDomains(): array
{
    $accessKeyId =
        trim(
            $this->config['access_key_id']
            ?? ''
        );

    $accessKeySecret =
        trim(
            $this->config['access_key_secret']
            ?? ''
        );

    if ($accessKeyId === '') {

        throw new RuntimeException(
            '阿里云 AccessKey ID 未配置'
        );
    }

    if ($accessKeySecret === '') {

        throw new RuntimeException(
            '阿里云 AccessKey Secret 未配置'
        );
    }

    $pageNumber = 1;
    $pageSize = 100;
    $domains = [];

    do {

        $result =
            $this->request(
                'DescribeDomains',
                [
                    'PageNumber' =>
                        $pageNumber,

                    'PageSize' =>
                        $pageSize,
                ]
            );

        if (
            empty($result['success'])
        ) {

            throw new RuntimeException(
                $result['message']
                ?? '阿里云获取域名失败'
            );
        }

        $data =
            $result['data']
            ?? [];

        if (!is_array($data)) {
            $data = [];
        }

        $items =
            $data['Domains']['Domain']
            ?? [];

        if (!is_array($items)) {
            $items = [];
        }

        foreach ($items as $item) {

            $domain =
                strtolower(
                    trim(
                        (string)(
                            $item['DomainName']
                            ?? ''
                        )
                    )
                );

            if ($domain === '') {
                continue;
            }

            $domains[] = [
                'domain' =>
                    $domain,

                'zone_id' =>
                    (string)(
                        $item['DomainId']
                        ?? ''
                    ),
            ];
        }

        $totalCount =
            (int)(
                $data['TotalCount']
                ?? count($domains)
            );

        $pageNumber++;

    } while (
        (($pageNumber - 1) * $pageSize)
        < $totalCount
    );

    return $domains;
}
    public function getDomainInfo(
        string $domain
    ): array {

        $domain =
            trim($domain);

        if ($domain === '') {

            return [
                'success' => false,
                'message' =>
                    '阿里云 DNS 域名未配置',
                'version_code' =>
                    'mianfei',
                'min_ttl' =>
                    600,
                'available_ttls' => [
                    600,
                    1800,
                    3600,
                    7200,
                    10800,
                    21600,
                    43200,
                    86400,
                ],
            ];
        }

        $result =
            $this->request(
                'DescribeDomainInfo',
                [
                    'DomainName' =>
                        $domain,

                    'NeedDetailAttributes' =>
                        'true',
                ]
            );

        if (!$result['success']) {
            return $result;
        }

        $data =
            $result['data']
            ?? [];

        if (!is_array($data)) {
            $data = [];
        }

        $versionCode =
            (string)(
                $data['VersionCode']
                ?? 'mianfei'
            );

        $versionName =
            (string)(
                $data['VersionName']
                ?? '免费版'
            );

        $minTtl =
            (int)(
                $data['MinTtl']
                ?? 600
            );

        if (
            $versionCode === 'mianfei'
        ) {
            $minTtl = 600;
        }

        if ($minTtl <= 0) {
            $minTtl = 600;
        }

        $availableTtls = [];

        $available =
            $data['AvailableTtls']
            ?? [];

        if (is_array($available)) {

            $available =
                $available[
                    'AvailableTtl'
                ]
                ?? [];
        }

        if (
            is_string($available)
            &&
            $available !== ''
        ) {

            $decoded =
                json_decode(
                    $available,
                    true
                );

            if (is_array($decoded)) {
                $available = $decoded;
            }
        }

        if (is_array($available)) {

            foreach (
                $available
                as $ttl
            ) {

                if (
                    is_string($ttl)
                    &&
                    str_starts_with(
                        trim($ttl),
                        '['
                    )
                ) {

                    $decoded =
                        json_decode(
                            $ttl,
                            true
                        );

                    if (is_array($decoded)) {

                        foreach (
                            $decoded
                            as $item
                        ) {

                            $item =
                                (int)$item;

                            if ($item > 0) {

                                $availableTtls[] =
                                    $item;
                            }
                        }

                        continue;
                    }
                }

                $ttl =
                    (int)$ttl;

                if ($ttl > 0) {
                    $availableTtls[] =
                        $ttl;
                }
            }
        }

        if (
            empty($availableTtls)
        ) {

            $availableTtls = [
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

        $availableTtls =
            array_values(
                array_unique(
                    array_map(
                        'intval',
                        $availableTtls
                    )
                )
            );

        sort($availableTtls);

        $availableTtls =
            array_values(
                array_filter(
                    $availableTtls,
                    static function (
                        int $ttl
                    ) use (
                        $minTtl
                    ): bool {

                        return
                            $ttl >= $minTtl;
                    }
                )
            );

        if (
            empty($availableTtls)
        ) {

            $availableTtls = [
                $minTtl,
            ];
        }

        return [
            'success' => true,

            'message' =>
                '阿里云域名信息获取成功',

            'version_code' =>
                $versionCode,

            'version_name' =>
                $versionName,

            'min_ttl' =>
                $minTtl,

            'available_ttls' =>
                $availableTtls,

            'data' =>
                $data,
        ];
    }
    
    public function findRecords(
        string $fqdn
    ): array {

        $domain =
            strtolower(
                rtrim(
                    trim(
                        (string)(
                            $this->config['domain']
                            ?? ''
                        )
                    ),
                    '.'
                )
            );

        $fqdn =
            strtolower(
                rtrim(
                    trim($fqdn),
                    '.'
                )
            );

        if ($domain === '') {

            return [
                'success' => false,
                'message' =>
                    '阿里云 DNS 域名未配置',
            ];
        }

        if ($fqdn === '') {

            return [
                'success' => false,
                'message' =>
                    'DNS 查询域名不能为空',
            ];
        }

        $suffix =
            '.' . $domain;

        if (
            $fqdn !== $domain
            &&
            !str_ends_with(
                $fqdn,
                $suffix
            )
        ) {

            return [
                'success' => false,
                'message' =>
                    '查询域名不属于当前阿里云 DNS 域名',
            ];
        }

        if ($fqdn === $domain) {

            $rr = '@';

        } else {

            $rr =
                substr(
                    $fqdn,
                    0,
                    -strlen($suffix)
                );

            if ($rr === '') {
                $rr = '@';
            }
        }

        $result =
            $this->request(
                'DescribeDomainRecords',
                [
                    'DomainName' =>
                        $domain,

                    'RRKeyWord' =>
                        $rr,

                    'TypeKeyWord' =>
                        '',
                ]
            );

        if (!$result['success']) {
            return $result;
        }

        $data =
            $result['data']
            ?? [];

        if (!is_array($data)) {
            $data = [];
        }

        $records =
            $data['DomainRecords']['Record']
            ?? [];

        if (!is_array($records)) {
            $records = [];
        }

        
        $matched = [];

        foreach (
            $records
            as $record
        ) {

            if (!is_array($record)) {
                continue;
            }

            $recordRR =
                strtolower(
                    trim(
                        (string)(
                            $record['RR']
                            ?? ''
                        )
                    )
                );

            if (
                $recordRR === strtolower($rr)
            ) {

                $matched[] =
                    $record;
            }
        }

        return [
            'success' => true,

            'message' =>
                '阿里云 DNS 查询成功',

            'records' =>
                $matched,

            'data' =>
                $data,
        ];
    }

    public function createRecord(
        string $fqdn,
        string $type,
        string $content,
        int $ttl,
        bool $proxied = false
    ): array {

        $domain =
            trim(
                $this->config['domain']
                ?? ''
            );

        if ($domain === '') {

            return [
                'success' => false,
                'message' =>
                    '阿里云 DNS 域名未配置',
            ];
        }

        $recordType =
            strtoupper($type);

        $rr =
            $this->extractRR(
                $fqdn,
                $domain
            );

        if ($rr === null) {

            return [
                'success' => false,
                'message' =>
                    '无法解析 DNS 主机记录',
            ];
        }

        $result =
            $this->request(
                'AddDomainRecord',
                [
                    'DomainName' =>
                        $domain,

                    'RR' =>
                        $rr,

                    'Type' =>
                        $recordType,

                    'Value' =>
                        $content,

                    'TTL' =>
                        $ttl > 0
                            ? $ttl
                            : 600,
                ]
            );

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,

            'message' =>
                '阿里云 DNS 记录创建成功',

            'id' =>
                $result['data']['RecordId']
                ?? null,

            'data' =>
                $result['data']
                ?? null,
        ];
    }

    public function updateRecord(
        string $recordId,
        string $fqdn,
        string $type,
        string $content,
        int $ttl,
        bool $proxied = false
    ): array {

        if ($recordId === '') {

            return [
                'success' => false,
                'message' =>
                    '阿里云 DNS Record ID 未提供',
            ];
        }

        $domain =
            trim(
                $this->config['domain']
                ?? ''
            );

        if ($domain === '') {

            return [
                'success' => false,
                'message' =>
                    '阿里云 DNS 域名未配置',
            ];
        }

        $rr =
            $this->extractRR(
                $fqdn,
                $domain
            );

        if ($rr === null) {

            return [
                'success' => false,
                'message' =>
                    '无法解析 DNS 主机记录',
            ];
        }

        $result =
            $this->request(
                'UpdateDomainRecord',
                [
                    'RecordId' =>
                        $recordId,

                    'RR' =>
                        $rr,

                    'Type' =>
                        strtoupper($type),

                    'Value' =>
                        $content,

                    'TTL' =>
                        $ttl > 0
                            ? $ttl
                            : 600,
                ]
            );

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,

            'message' =>
                '阿里云 DNS 记录更新成功',

            'id' =>
                $recordId,

            'data' =>
                $result['data']
                ?? null,
        ];
    }

    public function deleteRecord(
        string $recordId
    ): array {

        if ($recordId === '') {

            return [
                'success' => false,
                'message' =>
                    '阿里云 DNS Record ID 未提供',
            ];
        }

        $result =
            $this->request(
                'DeleteDomainRecord',
                [
                    'RecordId' =>
                        $recordId,
                ]
            );

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,

            'message' =>
                '阿里云 DNS 记录删除成功',

            'id' =>
                $recordId,

            'data' =>
                $result['data']
                ?? null,
        ];
    }

    private function getEndpoint(): string
    {
        $endpoint =
            trim(
                $this->config['endpoint']
                ?? ''
            );

        if ($endpoint === '') {
            $endpoint =
                $this->defaultEndpoint;
        }

        $endpoint =
            preg_replace(
                '#^https?://#i',
                '',
                $endpoint
            );

        return
            'https://' .
            rtrim(
                $endpoint,
                '/'
            );
    }

    private function extractRR(
        string $fqdn,
        string $domain
    ): ?string {

        $fqdn =
            strtolower(
                rtrim(
                    trim($fqdn),
                    '.'
                )
            );

        $domain =
            strtolower(
                rtrim(
                    trim($domain),
                    '.'
                )
            );

        if (
            $fqdn === ''
            ||
            $domain === ''
        ) {
            return null;
        }

        if ($fqdn === $domain) {
            return '@';
        }

        $suffix =
            '.' . $domain;

        if (
            !str_ends_with(
                $fqdn,
                $suffix
            )
        ) {
            return null;
        }

        $rr =
            substr(
                $fqdn,
                0,
                -strlen($suffix)
            );

        if ($rr === '') {
            return '@';
        }

        return $rr;
    }

    private function request(
        string $action,
        array $params = []
    ): array {

        $accessKeyId =
            trim(
                $this->config['access_key_id']
                ?? ''
            );

        $accessKeySecret =
            trim(
                $this->config['access_key_secret']
                ?? ''
            );

        if (
            $accessKeyId === ''
            ||
            $accessKeySecret === ''
        ) {

            return [
                'success' => false,
                'message' =>
                    '阿里云 AccessKey 配置不完整',
            ];
        }

        $commonParams = [
            'Format' =>
                'JSON',

            'Version' =>
                '2015-01-09',

            'AccessKeyId' =>
                $accessKeyId,

            'SignatureMethod' =>
                'HMAC-SHA1',

            'SignatureNonce' =>
                bin2hex(
                    random_bytes(16)
                ),

            'SignatureVersion' =>
                '1.0',

            'Timestamp' =>
                gmdate(
                    'Y-m-d\TH:i:s\Z'
                ),

            'Action' =>
                $action,
        ];

        $params =
            array_merge(
                $commonParams,
                $params
            );

        ksort($params);

        $canonicalizedQueryString =
            $this->buildCanonicalizedQuery(
                $params
            );

        $stringToSign =
            'GET&%2F&' .
            $this->percentEncode(
                $canonicalizedQueryString
            );

        $signature =
            base64_encode(
                hash_hmac(
                    'sha1',
                    $stringToSign,
                    $accessKeySecret . '&',
                    true
                )
            );

        $params['Signature'] =
            $signature;

        ksort($params);

        $query =
            $this->buildCanonicalizedQuery(
                $params
            );

        $url =
            $this->getEndpoint() .
            '/?' .
            $query;

        $ch =
            curl_init($url);

        if ($ch === false) {

            return [
                'success' => false,
                'message' =>
                    '无法初始化 cURL',
            ];
        }

        curl_setopt_array(
            $ch,
            [
                CURLOPT_RETURNTRANSFER =>
                    true,

                CURLOPT_HTTPGET =>
                    true,

                CURLOPT_CONNECTTIMEOUT =>
                    10,

                CURLOPT_TIMEOUT =>
                    30,

                CURLOPT_FOLLOWLOCATION =>
                    false,

                CURLOPT_HTTP_VERSION =>
    CURL_HTTP_VERSION_1_1,

CURLOPT_HTTPHEADER => [
    'Accept: application/json',
    'Connection: close',
],
            ]
        );

        $response = false;
$curlError = '';
$curlErrno = 0;

$maxAttempts = 3;

for (
    $attempt = 1;
    $attempt <= $maxAttempts;
    $attempt++
) {

    $response =
        curl_exec($ch);

    $curlError =
        curl_error($ch);

    $curlErrno =
        curl_errno($ch);

    if ($response !== false) {
        break;
    }

    if ($attempt < $maxAttempts) {
        usleep(500000);
    }
}

        $httpCode =
            (int)curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

        curl_close($ch);

        if ($response === false) {

            return [
                'success' => false,
                'message' =>
                    '阿里云 API 请求失败：' .
                    $curlError,
                'curl_errno' =>
                    $curlErrno,
            ];
        }

        $decoded =
            json_decode(
                $response,
                true
            );

        if (!is_array($decoded)) {

            return [
                'success' => false,
                'message' =>
                    '阿里云返回了无效 JSON',
                'http_code' =>
                    $httpCode,
                'raw' =>
                    $response,
            ];
        }

        if (
            $httpCode < 200
            ||
            $httpCode >= 300
            ||
            isset($decoded['Code'])
        ) {

            $message =
                $decoded['Message']
                ??
                '阿里云 API 请求失败';

            if (
                !empty(
                    $decoded['Code']
                )
            ) {

                $message =
                    '[' .
                    $decoded['Code'] .
                    '] ' .
                    $message;
            }

            return [
                'success' => false,
                'message' =>
                    $message,
                'http_code' =>
                    $httpCode,
                'data' =>
                    $decoded,
            ];
        }

        return [
            'success' => true,

            'message' =>
                '请求成功',

            'http_code' =>
                $httpCode,

            'data' =>
                $decoded,
        ];
    }

    private function buildCanonicalizedQuery(
        array $params
    ): string {

        ksort($params);

        $parts = [];

        foreach (
            $params
            as $key => $value
        ) {

            $parts[] =
                $this->percentEncode(
                    (string)$key
                )
                .
                '='
                .
                $this->percentEncode(
                    (string)$value
                );
        }

        return implode(
            '&',
            $parts
        );
    }

    private function percentEncode(
        string $value
    ): string {

        return str_replace(
            [
                '+',
                '*',
                '%7E',
            ],
            [
                '%20',
                '%2A',
                '~',
            ],
            urlencode($value)
        );
    }
}