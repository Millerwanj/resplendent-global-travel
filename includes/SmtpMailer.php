<?php
declare(strict_types=1);

final class SmtpMailer
{
    /** @var resource|null */
    private $socket = null;
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private int $timeout;

    public function __construct(array $config)
    {
        $this->host = (string)($config['smtp_host'] ?? '');
        $this->port = (int)($config['smtp_port'] ?? 465);
        $this->username = (string)($config['smtp_username'] ?? '');
        $this->password = (string)($config['smtp_password'] ?? '');
        $this->timeout = (int)($config['smtp_timeout'] ?? 20);

        if ($this->host === '' || $this->username === '' || $this->password === '') {
            throw new RuntimeException('SMTP configuration is incomplete.');
        }
    }

    /**
     * @param string[] $to
     * @param string[] $cc
     */
    public function send(
        array $to,
        array $cc,
        string $fromEmail,
        string $fromName,
        string $replyTo,
        string $subject,
        string $body
    ): void {
        $recipients = array_values(array_unique(array_filter(array_merge($to, $cc))));
        if ($recipients === []) {
            throw new RuntimeException('No email recipient was supplied.');
        }

        $this->connect();
        try {
            $this->command('EHLO ' . $this->clientName(), [250]);
            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode($this->username), [334], false);
            $this->command(base64_encode($this->password), [235], false);
            $this->command('MAIL FROM:<' . $fromEmail . '>', [250]);
            foreach ($recipients as $recipient) {
                $this->command('RCPT TO:<' . $recipient . '>', [250, 251]);
            }
            $this->command('DATA', [354]);
            $this->write($this->buildMessage($to, $cc, $fromEmail, $fromName, $replyTo, $subject, $body) . "\r\n.\r\n");
            $this->expect([250]);
            $this->command('QUIT', [221]);
        } finally {
            $this->close();
        }
    }

    private function connect(): void
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $this->host,
                'allow_self_signed' => false,
                'SNI_enabled' => true,
            ],
        ]);

        $errno = 0;
        $error = '';
        $this->socket = @stream_socket_client(
            'ssl://' . $this->host . ':' . $this->port,
            $errno,
            $error,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($this->socket)) {
            throw new RuntimeException("SMTP connection failed ({$errno}): {$error}");
        }

        stream_set_timeout($this->socket, $this->timeout);
        $this->expect([220]);
    }

    /** @param int[] $expected */
    private function command(string $command, array $expected, bool $logSafe = true): void
    {
        $this->write($command . "\r\n");
        $this->expect($expected, $logSafe ? $command : '[credential]');
    }

    private function write(string $data): void
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('SMTP connection is not open.');
        }
        $written = fwrite($this->socket, $data);
        if ($written === false || $written < strlen($data)) {
            throw new RuntimeException('Could not write the complete SMTP message.');
        }
    }

    /** @param int[] $expected */
    private function expect(array $expected, string $context = ''): string
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('SMTP connection is not open.');
        }

        $response = '';
        do {
            $line = fgets($this->socket, 8192);
            if ($line === false) {
                $meta = stream_get_meta_data($this->socket);
                $reason = !empty($meta['timed_out']) ? 'timed out' : 'closed unexpectedly';
                throw new RuntimeException('SMTP connection ' . $reason . '.');
            }
            $response .= $line;
        } while (strlen($line) >= 4 && $line[3] === '-');

        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            $detail = trim(preg_replace('/\s+/', ' ', $response) ?? $response);
            throw new RuntimeException('SMTP rejected ' . ($context !== '' ? $context . ': ' : '') . $detail);
        }
        return $response;
    }

    /**
     * @param string[] $to
     * @param string[] $cc
     */
    private function buildMessage(
        array $to,
        array $cc,
        string $fromEmail,
        string $fromName,
        string $replyTo,
        string $subject,
        string $body
    ): string {
        $domain = substr(strrchr($fromEmail, '@') ?: '@localhost', 1);
        $messageId = sprintf('<%s.%s@%s>', bin2hex(random_bytes(8)), time(), $domain);
        $encodedFromName = mb_encode_mimeheader($fromName, 'UTF-8', 'B', "\r\n");
        $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: ' . $messageId,
            'From: ' . $encodedFromName . ' <' . $fromEmail . '>',
            'To: ' . implode(', ', $to),
        ];
        if ($cc !== []) {
            $headers[] = 'Cc: ' . implode(', ', $cc);
        }
        $headers[] = 'Reply-To: ' . $replyTo;
        $headers[] = 'Subject: ' . $encodedSubject;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $headers[] = 'X-Mailer: RGTS Website SMTP';

        $normalizedBody = preg_replace('/\r\n|\r|\n/', "\r\n", $body) ?? $body;
        $normalizedBody = preg_replace('/^\./m', '..', $normalizedBody) ?? $normalizedBody;
        return implode("\r\n", $headers) . "\r\n\r\n" . $normalizedBody;
    }

    private function clientName(): string
    {
        return preg_replace('/[^A-Za-z0-9.-]/', '', $_SERVER['SERVER_NAME'] ?? 'resplendentglobaltravel.com') ?: 'resplendentglobaltravel.com';
    }

    private function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }
}
