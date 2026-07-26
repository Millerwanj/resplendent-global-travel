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
    private string $encryption;
    private string $stage = 'configuration';

    public function __construct(array $config)
    {
        $this->host = trim((string)($config['smtp_host'] ?? ''));
        $this->port = (int)($config['smtp_port'] ?? 465);
        $this->username = trim((string)($config['smtp_username'] ?? ''));
        $this->password = (string)($config['smtp_password'] ?? '');
        $this->timeout = max(5, min(60, (int)($config['smtp_timeout'] ?? 20)));
        $this->encryption = strtolower(trim((string)($config['smtp_encryption'] ?? ($this->port === 587 ? 'tls' : 'ssl'))));

        if ($this->host === '' || $this->username === '' || $this->password === '') {
            throw new RuntimeException('SMTP stage configuration: required settings are incomplete.');
        }
        if (!in_array($this->encryption, ['ssl', 'tls', 'none'], true)) {
            throw new RuntimeException('SMTP stage configuration: encryption must be ssl, tls, or none.');
        }
    }

    /**
     * @param string[] $to
     * @param string[] $cc
     * @param array<int,array{filename:string,mime_type:string,content:string}> $attachments
     *  @return array<string,string>
     */
    public function send(
        array $to,
        array $cc,
        string $fromEmail,
        string $fromName,
        string $replyTo,
        string $subject,
        string $body,
        array $attachments = []
    ): array
    {
        $recipients = array_values(array_unique(array_filter(array_merge($to, $cc), static fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL))));
        if ($recipients === []) throw new RuntimeException('SMTP stage recipients: no valid recipient was supplied.');
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('SMTP stage sender: from address is invalid.');
        if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('SMTP stage sender: reply-to address is invalid.');

        $this->connect();
        try {
            $this->stage = 'greeting';
            $this->command('EHLO ' . $this->clientName(), [250]);

            if ($this->encryption === 'tls') {
                $this->stage = 'starttls';
                $this->command('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP stage starttls: TLS negotiation failed.');
                }
                $this->command('EHLO ' . $this->clientName(), [250]);
            }

            $this->stage = 'authentication';
            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode($this->username), [334], false);
            $this->command(base64_encode($this->password), [235], false);

            $this->stage = 'sender';
            $this->command('MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->stage = 'recipients';
            foreach ($recipients as $recipient) $this->command('RCPT TO:<' . $recipient . '>', [250, 251]);

            $this->stage = 'message_data';
            $this->command('DATA', [354]);
            $this->write($this->buildMessage(
                $to,
                $cc,
                $fromEmail,
                $fromName,
                $replyTo,
                $subject,
                $body,
                $attachments
            ) . "\r\n.\r\n");
            $this->expect([250]);

            $this->stage = 'quit';
            $this->command('QUIT', [221]);
            return [
                'host' => $this->host,
                'port' => (string)$this->port,
                'encryption' => $this->encryption,
                'recipients_accepted' => (string)count($recipients),
                'attachments' => (string)count($attachments),
                'final_stage' => 'queued_by_smtp_server',
            ];
        } finally {
            $this->close();
        }
    }

    private function connect(): void
    {
        $this->stage = 'connection';
        $context = stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $this->host,
            'allow_self_signed' => false,
            'SNI_enabled' => true,
        ]]);
        $transport = $this->encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $errno = 0;
        $error = '';
        $this->socket = @stream_socket_client(
            $transport . $this->host . ':' . $this->port,
            $errno,
            $error,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!is_resource($this->socket)) {
            throw new RuntimeException("SMTP stage connection: failed ({$errno}) {$error}");
        }
        stream_set_timeout($this->socket, $this->timeout);
        $this->expect([220]);
    }

    /** @param int[] $expected */
    private function command(string $command, array $expected, bool $showCommand = true): void
    {
        $this->write($command . "\r\n");
        $this->expect($expected, $showCommand ? $command : '[credential]');
    }

    private function write(string $data): void
    {
        if (!is_resource($this->socket)) throw new RuntimeException("SMTP stage {$this->stage}: connection is not open.");
        $total = strlen($data);
        $offset = 0;
        while ($offset < $total) {
            $written = fwrite($this->socket, substr($data, $offset));
            if ($written === false || $written === 0) throw new RuntimeException("SMTP stage {$this->stage}: socket write failed.");
            $offset += $written;
        }
    }

    /** @param int[] $expected */
    private function expect(array $expected, string $context = ''): string
    {
        if (!is_resource($this->socket)) throw new RuntimeException("SMTP stage {$this->stage}: connection is not open.");
        $response = '';
        do {
            $line = fgets($this->socket, 8192);
            if ($line === false) {
                $meta = stream_get_meta_data($this->socket);
                $reason = !empty($meta['timed_out']) ? 'timed out' : 'closed unexpectedly';
                throw new RuntimeException("SMTP stage {$this->stage}: connection {$reason}.");
            }
            $response .= $line;
        } while (strlen($line) >= 4 && $line[3] === '-');

        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            $detail = trim(preg_replace('/\s+/', ' ', $response) ?? $response);
            throw new RuntimeException("SMTP stage {$this->stage}: server rejected " . ($context !== '' ? $context . ' — ' : '') . $detail);
        }
        return $response;
    }

    /**
     * @param string[] $to
     * @param string[] $cc
     * @param array<int,array{filename:string,mime_type:string,content:string}> $attachments
     */
    private function buildMessage(
        array $to,
        array $cc,
        string $fromEmail,
        string $fromName,
        string $replyTo,
        string $subject,
        string $body,
        array $attachments
    ): string
    {
        $domain = substr(strrchr($fromEmail, '@') ?: '@localhost', 1);
        $messageId = sprintf('<%s.%s@%s>', bin2hex(random_bytes(8)), time(), $domain);
        $encodedFromName = $this->encodeHeader($fromName);
        $encodedSubject = $this->encodeHeader($subject);
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: ' . $messageId,
            'From: ' . $encodedFromName . ' <' . $fromEmail . '>',
            'To: ' . implode(', ', $to),
        ];
        if ($cc !== []) $headers[] = 'Cc: ' . implode(', ', $cc);
        $headers[] = 'Reply-To: ' . $replyTo;
        $headers[] = 'Subject: ' . $encodedSubject;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'X-Mailer: RGTS Website SMTP ' . (defined('RGTS_RELEASE') ? RGTS_RELEASE : '9.6.4');
        $normalizedBody = preg_replace('/\r\n|\r|\n/', "\r\n", $body) ?? $body;

        if ($attachments === []) {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
            $message = implode("\r\n", $headers) . "\r\n\r\n" . $normalizedBody;
            return preg_replace('/^\./m', '..', $message) ?? $message;
        }

        $boundary = 'rgts_' . bin2hex(random_bytes(18));
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        $parts = [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $normalizedBody,
        ];

        foreach ($attachments as $attachment) {
            $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', basename((string)($attachment['filename'] ?? 'document.pdf'))) ?: 'document.pdf';
            $mimeType = preg_match('/^[A-Za-z0-9.+-]+\/[A-Za-z0-9.+-]+$/', (string)($attachment['mime_type'] ?? ''))
                ? (string)$attachment['mime_type']
                : 'application/octet-stream';
            $content = (string)($attachment['content'] ?? '');
            if ($content === '') throw new RuntimeException('SMTP stage attachment: an attachment was empty.');
            $parts[] = '--' . $boundary;
            $parts[] = 'Content-Type: ' . $mimeType . '; name="' . $filename . '"';
            $parts[] = 'Content-Transfer-Encoding: base64';
            $parts[] = 'Content-Disposition: attachment; filename="' . $filename . '"';
            $parts[] = '';
            $parts[] = rtrim(chunk_split(base64_encode($content), 76, "\r\n"));
        }
        $parts[] = '--' . $boundary . '--';
        $parts[] = '';
        $message = implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $parts);
        return preg_replace('/^\./m', '..', $message) ?? $message;
    }

    private function encodeHeader(string $value): string
    {
        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function clientName(): string
    {
        return preg_replace('/[^A-Za-z0-9.-]/', '', $_SERVER['SERVER_NAME'] ?? 'resplendentglobaltravel.com') ?: 'resplendentglobaltravel.com';
    }

    private function close(): void
    {
        if (is_resource($this->socket)) fclose($this->socket);
        $this->socket = null;
    }
}
