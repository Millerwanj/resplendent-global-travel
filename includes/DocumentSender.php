<?php
declare(strict_types=1);

/**
 * Resolves the operational mailbox used for a client document.
 *
 * The configured SMTP account remains the fallback authentication account.
 * Individual sender profiles may optionally provide their own SMTP username
 * and password in the private configuration outside public_html.
 */
final class DocumentSender
{
    /** @return array<string,array<string,string>> */
    public static function profiles(array $config): array
    {
        $fallbackEmail = filter_var((string)($config['from_email'] ?? ''), FILTER_VALIDATE_EMAIL)
            ? (string)$config['from_email']
            : 'info@resplendentglobaltravel.com';
        $defaults = [
            'bookings' => [
                'label' => 'Bookings',
                'email' => 'bookings@resplendentglobaltravel.com',
                'name' => 'Resplendent Bookings',
                'reply_to' => 'bookings@resplendentglobaltravel.com',
            ],
            'corporate' => [
                'label' => 'Corporate Travel',
                'email' => 'corporate@resplendentglobaltravel.com',
                'name' => 'Resplendent Corporate Travel',
                'reply_to' => 'corporate@resplendentglobaltravel.com',
            ],
            'business' => [
                'label' => 'Global Business Connections',
                'email' => 'business@resplendentglobaltravel.com',
                'name' => 'Resplendent Global Business Connections',
                'reply_to' => 'business@resplendentglobaltravel.com',
            ],
            'accounts' => [
                'label' => 'Accounts',
                'email' => 'accounts@resplendentglobaltravel.com',
                'name' => 'Resplendent Accounts',
                'reply_to' => 'accounts@resplendentglobaltravel.com',
            ],
            'info' => [
                'label' => 'General',
                'email' => $fallbackEmail,
                'name' => (string)($config['from_name'] ?? 'Resplendent Global Travel Solutions'),
                'reply_to' => (string)($config['central_copy'] ?? $config['from_email'] ?? 'info@resplendentglobaltravel.com'),
            ],
        ];

        $configured = is_array($config['document_senders'] ?? null) ? $config['document_senders'] : [];
        foreach ($defaults as $key => &$profile) {
            $override = is_array($configured[$key] ?? null) ? $configured[$key] : [];
            foreach (['label', 'email', 'name', 'reply_to', 'smtp_username', 'smtp_password'] as $field) {
                if (isset($override[$field]) && is_scalar($override[$field])) {
                    $profile[$field] = self::headerText((string)$override[$field], $field === 'smtp_password');
                }
            }
            if (!filter_var($profile['email'], FILTER_VALIDATE_EMAIL)) {
                $profile['email'] = $fallbackEmail;
            }
            if (!filter_var($profile['reply_to'], FILTER_VALIDATE_EMAIL)) {
                $profile['reply_to'] = $profile['email'];
            }
            if ($profile['name'] === '') $profile['name'] = 'Resplendent Global Travel Solutions';
        }
        unset($profile);

        return $defaults;
    }

    public static function departmentForDocument(array $document): string
    {
        $type = (string)($document['type'] ?? '');
        $payload = is_array($document['payload'] ?? null) ? $document['payload'] : [];
        if ($type === 'invoice') return 'accounts';

        $documentType = strtolower(trim((string)(
            $payload['proposal_type']
            ?? $payload['quotation_type']
            ?? ''
        )));
        if (str_contains($documentType, 'corporate')) return 'corporate';
        if (str_contains($documentType, 'business')) return 'business';
        if ($type === 'proposal' || $type === 'quotation') return 'bookings';
        return 'info';
    }

    /** @return array<string,string> */
    public static function resolve(array $document, array $config, string $requested = ''): array
    {
        $profiles = self::profiles($config);
        $key = isset($profiles[$requested]) ? $requested : self::departmentForDocument($document);
        if (!isset($profiles[$key])) $key = 'info';
        return ['key' => $key] + $profiles[$key];
    }

    /** @param array<string,string> $profile */
    public static function mailerConfig(array $config, array $profile): array
    {
        $mailerConfig = $config;
        if (!empty($profile['smtp_username'])) $mailerConfig['smtp_username'] = $profile['smtp_username'];
        if (!empty($profile['smtp_password'])) $mailerConfig['smtp_password'] = $profile['smtp_password'];
        return $mailerConfig;
    }

    private static function headerText(string $value, bool $preserveWhitespace = false): string
    {
        $value = trim($value);
        if ($preserveWhitespace) return $value;
        return trim(preg_replace('/[\r\n]+/', ' ', $value) ?? '');
    }
}
