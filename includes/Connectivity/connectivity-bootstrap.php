<?php
declare(strict_types=1);

require_once __DIR__ . '/ConnectivityOrderStore.php';

/** @return array<string,mixed> */
function rgts_esimcard_config(): array
{
    $configured = trim((string)(getenv('RGTS_ESIMCARD_CONFIG') ?: ''));
    $path = $configured !== '' ? $configured : dirname(__DIR__, 3) . '/rgts-esimcard-config.php';
    if (!is_file($path)) return [];
    $config = require $path;
    return is_array($config) ? $config : [];
}

function rgts_connectivity_catalog(): array
{
    // Retained strictly for controlled sandbox acceptance tests.
    return [
        'sandbox-usd-1' => [
            'id' => 'sandbox-usd-1',
            'name' => 'Resplendent Connectivity Sandbox Test',
            'destination' => 'Test destination',
            'destination_id' => 'sandbox',
            'data' => '1 GB',
            'data_gb' => 1,
            'validity' => '7 days',
            'validity_days' => 7,
            'currency' => 'USD',
            'price' => '1.00',
            'provider' => 'sandbox',
            'provider_package_id' => 'sandbox-usd-1',
            'test_only' => true,
        ],
    ];
}

/** @return array<string,mixed> */
function rgts_connectivity_resolve_live_plan(string $destinationId, string $publicPlanId): array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $destinationId)) throw new InvalidArgumentException('Invalid destination reference.');
    if (!preg_match('/^[a-f0-9]{64}$/', $publicPlanId)) throw new InvalidArgumentException('Invalid plan reference.');

    $config = rgts_esimcard_config();
    if ($config === []) throw new RuntimeException('eSIM package configuration is unavailable.');

    require_once dirname(__DIR__) . '/EsimCard/EsimCardClient.php';
    require_once dirname(__DIR__) . '/Esim/ProviderAdapterInterface.php';
    require_once dirname(__DIR__) . '/Esim/EsimCardAdapter.php';
    require_once dirname(__DIR__) . '/Esim/FirstyAdapter.php';
    require_once dirname(__DIR__) . '/Esim/OfferRouter.php';

    $client = new Resplendent\EsimCard\EsimCardClient($config);
    $router = new Resplendent\Esim\OfferRouter([
        new Resplendent\Esim\EsimCardAdapter($client),
        new Resplendent\Esim\FirstyAdapter(),
    ], (float)($config['retail_markup_percent'] ?? 25));

    $destination = null;
    foreach ($router->destinations() as $item) {
        if (($item['id'] ?? '') === $destinationId) { $destination = $item; break; }
    }
    if (!is_array($destination)) throw new RuntimeException('The selected destination is no longer available.');

    foreach ($router->offers($destinationId) as $offer) {
        if (($offer['id'] ?? '') !== $publicPlanId) continue;
        return [
            'id' => (string)$offer['id'],
            'name' => (string)($offer['name'] ?? 'Resplendent eSIM'),
            'destination' => (string)($destination['name'] ?? ''),
            'destination_id' => $destinationId,
            'data' => ((float)($offer['data_gb'] ?? 0)) < 0
                ? 'Unlimited data'
                : rtrim(rtrim(number_format((float)($offer['data_gb'] ?? 0), 2, '.', ''), '0'), '.') . ' GB',
            'data_gb' => ((float)($offer['data_gb'] ?? 0)) < 0 ? null : (float)($offer['data_gb'] ?? 0),
            'validity' => ((int)($offer['validity_days'] ?? 0)) . ' days',
            'validity_days' => (int)($offer['validity_days'] ?? 0),
            'currency' => strtoupper((string)($offer['currency'] ?? 'USD')),
            'price' => number_format((float)($offer['retail_price'] ?? 0), 2, '.', ''),
            'provider' => (string)($offer['provider'] ?? ''),
            'provider_package_id' => (string)($offer['provider_package_id'] ?? ''),
            'test_only' => false,
        ];
    }
    throw new RuntimeException('That package is no longer available. Please choose a current plan.');
}

function rgts_connectivity_provider(string $providerName): ConnectivityProviderInterface
{
    $providerName = strtolower(trim($providerName));
    if ($providerName === 'sandbox') {
        require_once __DIR__ . '/Providers/SandboxConnectivityProvider.php';
        return new SandboxConnectivityProvider();
    }
    if ($providerName === 'esimcard') {
        require_once __DIR__ . '/Providers/EsimCardConnectivityProvider.php';
        $config = rgts_esimcard_config();
        if ($config === []) throw new RuntimeException('eSIMCard configuration is unavailable.');
        return new EsimCardConnectivityProvider($config);
    }
    throw new RuntimeException('The selected connectivity provider is not configured.');
}

function rgts_connectivity_base_url(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'www.resplendentglobaltravel.com';
    return 'https://' . preg_replace('/[^A-Za-z0-9.\-:]/', '', $host);
}

function rgts_connectivity_send_delivery_email(array &$order): void
{
    if (!empty($order['delivery']['email_sent_at'])) return;
    $email = trim((string)($order['payload']['customer']['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return;
    $configPath = dirname(__DIR__, 3) . '/rgts-mail-config.php';
    if (!is_file($configPath)) return;
    $mailConfig = require $configPath;
    if (!is_array($mailConfig)) return;

    require_once dirname(__DIR__) . '/SmtpMailer.php';
    $activation = is_array($order['provisioning']['activation'] ?? null) ? $order['provisioning']['activation'] : [];
    $body = "Your Resplendent eSIM is ready.\n\n";
    $body .= 'Order: ' . (string)$order['id'] . "\n";
    $body .= 'Destination: ' . (string)($order['payload']['plan']['destination'] ?? '') . "\n";
    $body .= 'Plan: ' . (string)($order['payload']['plan']['name'] ?? '') . "\n\n";
    if (!empty($activation['qr_code_url'])) $body .= 'QR code: ' . $activation['qr_code_url'] . "\n";
    if (!empty($activation['smdp_address'])) $body .= 'SM-DP+ address: ' . $activation['smdp_address'] . "\n";
    if (!empty($activation['activation_code'])) $body .= 'Activation code: ' . $activation['activation_code'] . "\n";
    if (!empty($activation['manual_code'])) $body .= 'Manual code: ' . $activation['manual_code'] . "\n";
    if (!empty($activation['iccid'])) $body .= 'ICCID: ' . $activation['iccid'] . "\n";
    $body .= "\nKeep this email until your eSIM is installed. If you need assistance, reply to this message.\n\nResplendent Global Travel Solutions\nSIMless Travel";

    try {
        $fromEmail = trim((string)($mailConfig['from_email'] ?? $mailConfig['smtp_username'] ?? 'bookings@resplendentglobaltravel.com'));
        $fromName = trim((string)($mailConfig['from_name'] ?? 'Resplendent Global Travel Solutions'));
        $replyTo = trim((string)($mailConfig['reply_to'] ?? 'bookings@resplendentglobaltravel.com'));
        $mailer = new SmtpMailer($mailConfig);
        $mailer->send([$email], [], $fromEmail, $fromName, $replyTo, 'Your Resplendent eSIM is ready — ' . (string)$order['id'], $body);
        $order['delivery']['email_sent_at'] = gmdate('c');
        $order['delivery']['email'] = $email;
    } catch (Throwable $e) {
        $order['delivery']['email_error'] = substr(strip_tags($e->getMessage()), 0, 240);
        error_log('RGTS eSIM delivery email: ' . $e->getMessage());
    }
}

function rgts_connectivity_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}
