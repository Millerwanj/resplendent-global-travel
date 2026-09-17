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
    // This is the final pre-payment boundary. Force current supplier pricing
    // here so customers can browse instantly without ever paying a stale price.
    $client->refreshPricing();
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

function rgts_connectivity_email_escape(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rgts_connectivity_email_https_url(mixed $value): string
{
    $url = trim((string)$value);
    if (!filter_var($url, FILTER_VALIDATE_URL)) return '';
    $parts = parse_url($url);
    return strtolower((string)($parts['scheme'] ?? '')) === 'https' ? $url : '';
}

/** @return array{text:string,html:string,subject:string} */
function rgts_connectivity_delivery_message(array $order): array
{
    $activation = is_array($order['provisioning']['activation'] ?? null) ? $order['provisioning']['activation'] : [];
    $customer = is_array($order['payload']['customer'] ?? null) ? $order['payload']['customer'] : [];
    $plan = is_array($order['payload']['plan'] ?? null) ? $order['payload']['plan'] : [];
    $orderId = trim((string)($order['id'] ?? ''));
    $firstName = trim((string)($customer['first_name'] ?? $customer['name'] ?? ''));
    if (str_contains($firstName, ' ')) $firstName = explode(' ', $firstName, 2)[0];
    $greeting = $firstName !== '' ? 'Hello ' . $firstName . ',' : 'Hello,';
    $destination = trim((string)($plan['destination'] ?? 'Your destination'));
    $planName = trim((string)($plan['name'] ?? 'Resplendent eSIM'));
    $data = trim((string)($plan['data'] ?? ''));
    $validity = trim((string)($plan['validity'] ?? ''));

    $qrUrl = rgts_connectivity_email_https_url($activation['qr_code_url'] ?? '');
    if ($qrUrl === '') $qrUrl = rgts_connectivity_email_https_url($activation['qr_code'] ?? '');
    $installUrl = rgts_connectivity_email_https_url($activation['install_url'] ?? '');
    $iosUrl = rgts_connectivity_email_https_url($activation['ios_install_url'] ?? '');
    $androidUrl = rgts_connectivity_email_https_url($activation['android_install_url'] ?? '');

    $details = [
        'SM-DP+ address' => trim((string)($activation['smdp_address'] ?? '')),
        'Activation code' => trim((string)($activation['activation_code'] ?? '')),
        'Manual code' => trim((string)($activation['manual_code'] ?? '')),
        'ICCID' => trim((string)($activation['iccid'] ?? '')),
    ];

    $text = $greeting . "\n\nYour Resplendent eSIM for {$destination} is ready.\n\n";
    $text .= "PLAN DETAILS\nOrder: {$orderId}\nDestination: {$destination}\nPlan: {$planName}\n";
    if ($data !== '') $text .= "Data: {$data}\n";
    if ($validity !== '') $text .= "Validity: {$validity}\n";
    if ($qrUrl !== '') $text .= "\nQR code: {$qrUrl}\n";
    foreach ([$installUrl, $iosUrl, $androidUrl] as $url) {
        if ($url !== '') { $text .= "Install eSIM: {$url}\n"; break; }
    }
    $hasManual = false;
    foreach ($details as $label => $value) {
        if ($value === '') continue;
        if (!$hasManual) { $text .= "\nMANUAL INSTALLATION DETAILS\n"; $hasManual = true; }
        $text .= $label . ': ' . $value . "\n";
    }
    $text .= "\nINSTALLATION\n1. Connect to reliable Wi-Fi before you begin.\n2. Open your phone's Cellular/Mobile Data settings and choose Add eSIM.\n3. Scan the QR code above, or enter the manual details.\n4. Label the new line ‘Resplendent eSIM’.\n5. On arrival, select it for mobile data and enable data roaming for this eSIM only.\n\nKeep this email until installation is complete. For help, reply to this message.\n\nResplendent eSIM — SIMless Travel\nResplendent Global Travel Solutions";

    $e = 'rgts_connectivity_email_escape';
    $summaryRows = '';
    foreach (['Order' => $orderId, 'Destination' => $destination, 'Plan' => $planName, 'Data' => $data, 'Validity' => $validity] as $label => $value) {
        if ($value === '') continue;
        $summaryRows .= '<tr><td style="padding:8px 0;color:#6b746f;font-size:13px;width:38%;">' . $e($label) . '</td><td style="padding:8px 0;color:#183d31;font-size:14px;font-weight:600;">' . $e($value) . '</td></tr>';
    }
    $manualRows = '';
    foreach ($details as $label => $value) {
        if ($value === '') continue;
        $manualRows .= '<tr><td style="padding:7px 0;color:#6b746f;font-size:12px;vertical-align:top;width:38%;">' . $e($label) . '</td><td style="padding:7px 0;color:#183d31;font-family:Menlo,Consolas,monospace;font-size:12px;word-break:break-all;">' . $e($value) . '</td></tr>';
    }
    $qrBlock = $qrUrl !== '' ? '<div style="text-align:center;margin:28px 0 20px;"><p style="margin:0 0 14px;color:#183d31;font-size:17px;font-weight:700;">Scan to install your eSIM</p><img src="' . $e($qrUrl) . '" width="220" alt="Resplendent eSIM QR code" style="display:inline-block;width:220px;max-width:80%;height:auto;border:12px solid #fff;box-shadow:0 4px 22px rgba(24,61,49,.13);"><p style="margin:13px 0 0;color:#6b746f;font-size:12px;">Open this email on another device while scanning.</p></div>' : '';
    $buttonUrl = $installUrl !== '' ? $installUrl : ($iosUrl !== '' ? $iosUrl : $androidUrl);
    $installButton = $buttonUrl !== '' ? '<div style="text-align:center;margin:18px 0 26px;"><a href="' . $e($buttonUrl) . '" style="display:inline-block;background:#183d31;color:#fff;text-decoration:none;padding:13px 24px;border-radius:999px;font-size:14px;font-weight:700;">Install your eSIM</a></div>' : '';
    $manualBlock = $manualRows !== '' ? '<div style="background:#f3f6f4;border:1px solid #dfe7e2;border-radius:12px;padding:18px 20px;margin:24px 0;"><p style="margin:0 0 7px;color:#183d31;font-size:15px;font-weight:700;">Manual installation details</p><table role="presentation" width="100%" cellspacing="0" cellpadding="0">' . $manualRows . '</table></div>' : '';

    $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;background:#edf2ef;font-family:Arial,Helvetica,sans-serif;color:#26352f;"><div style="display:none;max-height:0;overflow:hidden;opacity:0;">Your Resplendent eSIM for ' . $e($destination) . ' is ready to install.</div><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#edf2ef;"><tr><td align="center" style="padding:24px 10px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 8px 32px rgba(24,61,49,.08);"><tr><td style="background:#183d31;text-align:center;padding:26px 24px 24px;"><img src="https://www.resplendentglobaltravel.com/assets/images/logo-r.png" width="76" alt="Resplendent Global Travel Solutions" style="display:inline-block;width:76px;height:auto;"><p style="margin:10px 0 0;color:#d9ba6a;letter-spacing:2.5px;font-size:12px;font-weight:700;">RESPLENDENT eSIM</p><p style="margin:5px 0 0;color:#fff;font-size:13px;">SIMless Travel</p></td></tr><tr><td style="padding:32px 34px 30px;"><p style="margin:0 0 12px;font-size:16px;">' . $e($greeting) . '</p><h1 style="margin:0 0 12px;color:#183d31;font-family:Georgia,serif;font-size:29px;line-height:1.2;font-weight:500;">Your eSIM is ready</h1><p style="margin:0 0 24px;color:#55645d;font-size:15px;line-height:1.6;">Everything you need to connect in <strong style="color:#183d31;">' . $e($destination) . '</strong> is below.</p><div style="border-top:1px solid #e2e9e5;border-bottom:1px solid #e2e9e5;padding:10px 0;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0">' . $summaryRows . '</table></div>' . $qrBlock . $installButton . $manualBlock . '<h2 style="margin:27px 0 13px;color:#183d31;font-size:18px;">Install in five steps</h2><ol style="margin:0;padding-left:21px;color:#46554e;font-size:14px;line-height:1.65;"><li style="margin-bottom:7px;">Connect to reliable Wi-Fi before you begin.</li><li style="margin-bottom:7px;">Open Cellular or Mobile Data settings and choose <strong>Add eSIM</strong>.</li><li style="margin-bottom:7px;">Scan the QR code, or enter the manual details above.</li><li style="margin-bottom:7px;">Label the new line <strong>Resplendent eSIM</strong>.</li><li>On arrival, select it for mobile data and enable data roaming for this eSIM only.</li></ol><div style="margin:26px 0 0;padding:15px 17px;border-left:3px solid #d9ba6a;background:#fbfaf6;color:#5d594c;font-size:13px;line-height:1.55;"><strong>Travel tip:</strong> Install before departure, then activate the line when you arrive. Keep this email until installation is complete.</div><p style="margin:27px 0 0;color:#55645d;font-size:14px;line-height:1.6;">Need help? Simply reply to this email and our team will assist you.</p></td></tr><tr><td style="background:#f6f7f5;text-align:center;padding:20px 24px;color:#708078;font-size:11px;line-height:1.6;"><strong style="color:#183d31;">Resplendent Global Travel Solutions</strong><br>Resplendent eSIM — SIMless Travel<br><a href="https://www.resplendentglobaltravel.com" style="color:#8c7330;text-decoration:none;">resplendentglobaltravel.com</a></td></tr></table></td></tr></table></body></html>';

    $subjectDestination = trim(preg_replace('/[\r\n]+/', ' ', $destination) ?? $destination);
    return [
        'subject' => 'Your Resplendent eSIM is ready — ' . $subjectDestination,
        'text' => $text,
        'html' => $html,
    ];
}

function rgts_connectivity_send_delivery_email(array &$order): bool
{
    if (!empty($order['delivery']['email_sent_at'])) return true;
    $email = trim((string)($order['payload']['customer']['email'] ?? ''));
    $order['delivery']['attempts'] = (int)($order['delivery']['attempts'] ?? 0) + 1;
    $order['delivery']['last_attempt_at'] = gmdate('c');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $order['delivery']['status'] = 'INVALID_ADDRESS';
        $order['delivery']['email_error'] = 'The customer email address is invalid.';
        return false;
    }
    $configPath = dirname(__DIR__, 3) . '/rgts-mail-config.php';
    if (!is_file($configPath)) {
        $order['delivery']['status'] = 'NOT_CONFIGURED';
        $order['delivery']['email_error'] = 'Email delivery is not configured.';
        return false;
    }
    $mailConfig = require $configPath;
    if (!is_array($mailConfig)) {
        $order['delivery']['status'] = 'NOT_CONFIGURED';
        $order['delivery']['email_error'] = 'Email delivery configuration is invalid.';
        return false;
    }

    require_once dirname(__DIR__) . '/SmtpMailer.php';
    $message = rgts_connectivity_delivery_message($order);

    try {
        $fromEmail = trim((string)($mailConfig['from_email'] ?? $mailConfig['smtp_username'] ?? 'bookings@resplendentglobaltravel.com'));
        $fromName = trim((string)($mailConfig['from_name'] ?? 'Resplendent Global Travel Solutions'));
        $replyTo = trim((string)($mailConfig['reply_to'] ?? 'bookings@resplendentglobaltravel.com'));
        $mailer = new SmtpMailer($mailConfig);
        $mailer->send([$email], [], $fromEmail, $fromName, $replyTo, $message['subject'], $message['text'], [], $message['html']);
        $order['delivery']['status'] = 'SENT';
        $order['delivery']['email_sent_at'] = gmdate('c');
        $order['delivery']['email'] = $email;
        $order['delivery']['email_error'] = '';
        return true;
    } catch (Throwable $e) {
        $order['delivery']['status'] = 'FAILED';
        $order['delivery']['email_error'] = substr(strip_tags($e->getMessage()), 0, 240);
        error_log('RGTS eSIM delivery email: ' . $e->getMessage());
        return false;
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
