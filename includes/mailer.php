<?php

declare(strict_types=1);


class AzSmtpMailer
{
    private array $settings;
    private $socket = null;
    private int $timeout = 20;

    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    public function send(string $to, string $subject, string $html): void
    {
        $host = trim((string)($this->settings['smtp_host'] ?? ''));
        $port = (int)($this->settings['smtp_port'] ?? 465);
        $encryption = (string)($this->settings['smtp_encryption'] ?? 'ssl');
        $username = (string)($this->settings['smtp_username'] ?? '');
        $password = (string)($this->settings['smtp_password'] ?? '');
        $fromEmail = trim((string)($this->settings['from_email'] ?? ''));
        $fromName = trim((string)($this->settings['from_name'] ?? 'AZhai Sub'));
        $replyTo = trim((string)($this->settings['reply_to'] ?? ''));

        if ($host === '' || $port < 1 || $port > 65535) {
            throw new RuntimeException('SMTP 服务器配置不完整');
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('收件人邮箱格式错误');
        }
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('发件人邮箱格式错误');
        }
        if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('回复邮箱格式错误');
        }

        $target = $encryption === 'ssl' ? 'ssl://' . $host . ':' . $port : 'tcp://' . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $this->socket = @stream_socket_client(
            $target,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            throw new RuntimeException('无法连接 SMTP：' . ($errstr ?: ('错误码 ' . $errno)));
        }

        stream_set_timeout($this->socket, $this->timeout);

        try {
            $this->expect([220]);
            $this->command('EHLO ' . $this->hostname(), [250]);

            if ($encryption === 'tls') {
                $this->command('STARTTLS', [220]);
                $ok = @stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($ok !== true) {
                    throw new RuntimeException('SMTP STARTTLS 加密失败');
                }
                $this->command('EHLO ' . $this->hostname(), [250]);
            }

            if ($username !== '') {
                $this->command('AUTH LOGIN', [334]);
                $this->command(base64_encode($username), [334]);
                $this->command(base64_encode($password), [235]);
            }

            $this->command('MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->command('RCPT TO:<' . $to . '>', [250, 251]);
            $this->command('DATA', [354]);

            $message = $this->buildMessage($to, $subject, $html, $fromEmail, $fromName, $replyTo);
            $message = preg_replace('/(?m)^\./', '..', $message) ?? $message;
            $this->write($message . "\r\n.\r\n");
            $this->expect([250]);
            $this->command('QUIT', [221, 250]);
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
            $this->socket = null;
        }
    }

    private function buildMessage(string $to, string $subject, string $html, string $fromEmail, string $fromName, string $replyTo): string
    {
        $headers = [
            'Date: ' . date('r'),
            'From: ' . $this->encodeHeader($fromName) . ' <' . $fromEmail . '>',
            'To: <' . $to . '>',
            'Subject: ' . $this->encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: AZhai Sub SMTP',
        ];
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        return implode("\r\n", $headers) . "\r\n\r\n" . str_replace(["\r\n", "\r", "\n"], "\r\n", $html);
    }

    private function encodeHeader(string $value): string
    {
        if ($value === '') return '';
        if (preg_match('/^[\x20-\x7E]*$/', $value)) return $value;
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function hostname(): string
    {
        return preg_replace('/[^A-Za-z0-9.-]/', '', gethostname() ?: 'localhost') ?: 'localhost';
    }

    private function command(string $command, array $codes): string
    {
        $this->write($command . "\r\n");
        return $this->expect($codes);
    }

    private function write(string $data): void
    {
        if (!is_resource($this->socket) || fwrite($this->socket, $data) === false) {
            throw new RuntimeException('SMTP 写入失败');
        }
    }

    private function expect(array $codes): string
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('SMTP 连接已关闭');
        }
        $response = '';
        while (($line = fgets($this->socket, 2048)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        if ($response === '') {
            throw new RuntimeException('SMTP 未返回响应');
        }
        $code = (int)substr(trim($response), 0, 3);
        if (!in_array($code, $codes, true)) {
            $safe = preg_replace('/\s+/', ' ', trim($response)) ?? trim($response);
            throw new RuntimeException('SMTP 返回错误：' . substr($safe, 0, 500));
        }
        return $response;
    }
}
