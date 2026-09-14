<?php

declare(strict_types=1);

class CloudflareProvider implements DnsProviderInterface
{
    private string $email;

    private string $apiKey;

    private ?string $zoneId;

    private string $baseUrl = 'https://api.cloudflare.com/client/v4';

    public function __construct(array $config)
    {
        $this->email = trim(
            (string)($config['email'] ?? '')
        );

        $this->apiKey = trim(
            (string)($config['api_key'] ?? '')
        );

        $zoneId = trim(
            (string)($config['zone_id'] ?? '')
        );

        $this->zoneId = $zoneId !== ''
            ? $zoneId
            : null;

        if ($this->email === '') {
            throw new RuntimeException(
                'Cloudflare 邮箱未配置'
            );
        }

        if ($this->apiKey === '') {
            throw new RuntimeException(
                'Cloudflare Global API Key 未配置'
            );
        }
    }

    public function testConnection(): array
    {
        if ($this->zoneId !== null) {
            $result = $this->request(
                'GET',
                '/zones/' .
                rawurlencode(
                    $this->zoneId
                )
            );

            if (!$result['success']) {
                return $result;
            }

            return [
                'success' => true,
                'message' => 'Cloudflare 连接成功',
                'data' => $result['data']
            ];
        }

        $result = $this->request(
            'GET',
            '/zones?per_page=1&page=1'
        );

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'message' => 'Cloudflare 连接成功',
            'data' => $result['data']
        ];
    }

    public function createRecord(
        string $fqdn,
        string $type,
        string $content,
        int $ttl,
        bool $proxied = false
    ): array {
        $this->requireZoneId();

        $type = strtoupper(
            trim($type)
        );

        if (!in_array(
            $type,
            [
                'A',
                'AAAA',
                'CNAME',
                'TXT'
            ],
            true
        )) {
            return [
                'success' => false,
                'message' => '不支持的 DNS 记录类型'
            ];
        }

        $fqdn = strtolower(
            trim($fqdn)
        );

        $content = trim(
            $content
        );

        if ($fqdn === '') {
            return [
                'success' => false,
                'message' => 'DNS 名称不能为空'
            ];
        }

        if ($content === '') {
            return [
                'success' => false,
                'message' => 'DNS 内容不能为空'
            ];
        }

        $payload = [
            'type' => $type,
            'name' => $fqdn,
            'content' => $content,
            'ttl' => max(
                60,
                $ttl
            ),
            'proxied' => $type === 'TXT'
                ? false
                : $proxied
        ];

        return $this->request(
            'POST',
            '/zones/' .
            rawurlencode(
                $this->zoneId
            ) .
            '/dns_records',
            $payload
        );
    }

    public function updateRecord(
        string $id,
        string $fqdn,
        string $type,
        string $content,
        int $ttl,
        bool $proxied = false
    ): array {
        $this->requireZoneId();

        $id = trim(
            $id
        );

        if ($id === '') {
            return [
                'success' => false,
                'message' => 'Cloudflare DNS 记录 ID 不能为空'
            ];
        }

        $type = strtoupper(
            trim($type)
        );

        if (!in_array(
            $type,
            [
                'A',
                'AAAA',
                'CNAME',
                'TXT'
            ],
            true
        )) {
            return [
                'success' => false,
                'message' => '不支持的 DNS 记录类型'
            ];
        }

        $fqdn = strtolower(
            trim($fqdn)
        );

        $content = trim(
            $content
        );

        if ($fqdn === '') {
            return [
                'success' => false,
                'message' => 'DNS 名称不能为空'
            ];
        }

        if ($content === '') {
            return [
                'success' => false,
                'message' => 'DNS 内容不能为空'
            ];
        }

        $payload = [
            'type' => $type,
            'name' => $fqdn,
            'content' => $content,
            'ttl' => max(
                60,
                $ttl
            ),
            'proxied' => $type === 'TXT'
                ? false
                : $proxied
        ];

        return $this->request(
            'PUT',
            '/zones/' .
            rawurlencode(
                $this->zoneId
            ) .
            '/dns_records/' .
            rawurlencode(
                $id
            ),
            $payload
        );
    }

    public function deleteRecord(
        string $id
    ): array {
        $this->requireZoneId();

        $id = trim(
            $id
        );

        if ($id === '') {
            return [
                'success' => false,
                'message' => 'Cloudflare DNS 记录 ID 不能为空'
            ];
        }

        return $this->request(
            'DELETE',
            '/zones/' .
            rawurlencode(
                $this->zoneId
            ) .
            '/dns_records/' .
            rawurlencode(
                $id
            )
        );
    }

    public function getDomainInfo(
        string $domain
    ): array {
        $domain = strtolower(
            trim($domain)
        );

        if ($domain === '') {
            return [
                'success' => false,
                'message' => '域名不能为空'
            ];
        }

        $result = $this->request(
            'GET',
            '/zones?name=' .
            rawurlencode($domain) .
            '&per_page=1&page=1'
        );

        if (!$result['success']) {
            return $result;
        }

        $resultData = $result['data'] ?? [];

        $zones = $resultData['result'] ?? [];

        if (
            !is_array($zones) ||
            count($zones) === 0
        ) {
            return [
                'success' => false,
                'message' => 'Cloudflare 中不存在该域名'
            ];
        }

        $zone = $zones[0];

        if (!is_array($zone)) {
            return [
                'success' => false,
                'message' => 'Cloudflare 返回了无效域名信息'
            ];
        }

        return [
            'success' => true,
            'message' => '获取域名信息成功',
            'data' => $zone
        ];
    }

    public function findRecords(
        string $fqdn
    ): array {
        $this->requireZoneId();

        $fqdn = strtolower(
            trim($fqdn)
        );

        if ($fqdn === '') {
            return [
                'success' => false,
                'message' => 'DNS 名称不能为空'
            ];
        }

        $result = $this->request(
            'GET',
            '/zones/' .
            rawurlencode(
                $this->zoneId
            ) .
            '/dns_records?name=' .
            rawurlencode($fqdn) .
            '&per_page=100'
        );

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'message' => '获取 DNS 记录成功',
            'data' => $result['data'] ?? []
        ];
    }

    public function listDomains(): array
    {
        $domains = [];

        $page = 1;

        $perPage = 50;

        do {
            $result = $this->request(
                'GET',
                '/zones?per_page=' .
                $perPage .
                '&page=' .
                $page
            );

            if (!$result['success']) {
                return $result;
            }

            $data = $result['data'] ?? [];

            $zones = $data['result'] ?? [];

            if (!is_array($zones)) {
                $zones = [];
            }

            foreach ($zones as $zone) {
                if (!is_array($zone)) {
                    continue;
                }

                $domain = strtolower(
                    trim(
                        (string)(
                            $zone['name'] ?? ''
                        )
                    )
                );

                $zoneId = trim(
                    (string)(
                        $zone['id'] ?? ''
                    )
                );

                if (
                    $domain === '' ||
                    $zoneId === ''
                ) {
                    continue;
                }

                $domains[] = [
                    'domain' => $domain,
                    'zone_id' => $zoneId,
                    'provider_zone_id' => $zoneId,
                    'status' => (string)(
                        $zone['status'] ?? ''
                    ),
                    'name_servers' =>
                        is_array(
                            $zone['name_servers'] ?? null
                        )
                            ? $zone['name_servers']
                            : []
                ];
            }

            $resultInfo =
                $data['result_info'] ?? [];

            $totalPages = (int)(
                $resultInfo['total_pages']
                ?? $page
            );

            if ($totalPages <= $page) {
                break;
            }

            $page++;

        } while ($page <= 100);

        return [
            'success' => true,
            'message' => 'Cloudflare 域名获取成功',
            'domains' => $domains
        ];
    }

    private function requireZoneId(): void
    {
        if (
            $this->zoneId === null ||
            $this->zoneId === ''
        ) {
            throw new RuntimeException(
                'Cloudflare Zone ID 未配置'
            );
        }
    }

    private function request(
        string $method,
        string $path,
        ?array $payload = null
    ): array {
        $method = strtoupper(
            trim($method)
        );

        $url = $this->baseUrl . $path;

        $ch = curl_init();

        if ($ch === false) {
            return [
                'success' => false,
                'message' => '无法初始化 cURL'
            ];
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Auth-Email: ' . $this->email,
            'X-Auth-Key: ' . $this->apiKey
        ];

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ];

        if ($payload !== null) {
            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

            if ($json === false) {
                curl_close(
                    $ch
                );

                return [
                    'success' => false,
                    'message' => 'JSON 编码失败'
                ];
            }

            $options[
                CURLOPT_POSTFIELDS
            ] = $json;
        }

        curl_setopt_array(
            $ch,
            $options
        );

        $response = curl_exec(
            $ch
        );

        $curlError = curl_error(
            $ch
        );

        $curlErrno = curl_errno(
            $ch
        );

        $httpCode = (int)curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close(
            $ch
        );

        if ($response === false) {
            return [
                'success' => false,
                'message' =>
                    'Cloudflare 请求失败：' .
                    $curlError .
                    '，错误码：' .
                    $curlErrno
            ];
        }

        $data = json_decode(
            $response,
            true
        );

        if (!is_array($data)) {
            return [
                'success' => false,
                'message' =>
                    'Cloudflare 返回了无效 JSON',
                'http_code' => $httpCode,
                'raw' => $response
            ];
        }

        if (
            isset($data['success']) &&
            $data['success'] === true
        ) {
            return [
                'success' => true,
                'message' => '请求成功',
                'data' => $data
            ];
        }

        return [
            'success' => false,
            'message' =>
                $this->getErrorMessage(
                    $data
                ),
            'data' => $data,
            'http_code' => $httpCode
        ];
    }

    private function getErrorMessage(
        array $data
    ): string {
        $errors = $data['errors'] ?? [];

        if (
            is_array($errors) &&
            count($errors) > 0
        ) {
            $messages = [];

            foreach ($errors as $error) {
                if (!is_array($error)) {
                    continue;
                }

                $code = trim(
                    (string)(
                        $error['code'] ?? ''
                    )
                );

                $message = trim(
                    (string)(
                        $error['message'] ?? ''
                    )
                );

                if (
                    $code !== '' &&
                    $message !== ''
                ) {
                    $messages[] =
                        '[' .
                        $code .
                        '] ' .
                        $message;
                } elseif (
                    $message !== ''
                ) {
                    $messages[] = $message;
                }
            }

            if (
                count($messages) > 0
            ) {
                return implode(
                    '；',
                    $messages
                );
            }
        }

        return 'Cloudflare API 请求失败';
    }
}