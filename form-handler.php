<?php
declare(strict_types=1);

const RGTS_RELEASE = '9.5.2';
const RGTS_ZOHO_ENDPOINT = 'https://crm.zoho.com/crm/WebToLeadForm';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: contact.html', true, 303);
    exit;
}

function limit_text(string $value, int $max): string
{
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function clean(string $value, int $max = 2000): string
{
    $value = trim(strip_tags($value));
    $value = preg_replace('/[\r\n]+/', ' ', $value) ?? '';
    return limit_text($value, $max);
}

function reference_id(string $candidate = ''): string
{
    $candidate = clean($candidate, 40);
    if (preg_match('/^RGTS-\d{8}-[A-Z0-9]{4}$/', $candidate)) {
        return $candidate;
    }

    try {
        $token = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
    } catch (Throwable) {
        $token = strtoupper(substr(hash('sha256', uniqid('', true)), 0, 4));
    }
    return 'RGTS-' . date('Ymd') . '-' . $token;
}

function redirect_result(string $status, string $reference = '', string $stage = ''): never
{
    $params = ['status' => $status];
    if ($reference !== '') $params['reference'] = $reference;
    if ($stage !== '') $params['stage'] = $stage;
    header('Location: contact.html?' . http_build_query($params) . '#form-status', true, 303);
    exit;
}

/** Write one structured line per pipeline event. Logs are stored outside public_html when possible. */
function pipeline_log(string $reference, string $stage, string $status, array $context = []): void
{
    $safeContext = [];
    foreach ($context as $key => $value) {
        if (in_array((string)$key, ['password', 'smtp_password', 'xnQsjsdp', 'xmIwtLD'], true)) continue;
        if (is_scalar($value) || $value === null) {
            $safeContext[(string)$key] = limit_text((string)$value, 1200);
        }
    }

    $record = [
        'time_utc' => gmdate('c'),
        'release' => RGTS_RELEASE,
        'reference' => $reference,
        'stage' => $stage,
        'status' => $status,
        'context' => $safeContext,
    ];

    $preferred = dirname(__DIR__) . '/rgts-logs';
    $fallback = __DIR__ . '/.rgts-logs';
    $directory = is_dir($preferred) || @mkdir($preferred, 0750, true) ? $preferred : $fallback;
    if (!is_dir($directory)) @mkdir($directory, 0750, true);
    if ($directory === $fallback && is_dir($directory)) {
        @file_put_contents($directory . '/.htaccess', "Require all denied\nDeny from all\n", LOCK_EX);
        @file_put_contents($directory . '/index.html', '', LOCK_EX);
    }

    $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    $written = @file_put_contents($directory . '/pipeline-' . gmdate('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    if ($written === false) {
        error_log(sprintf('[%s] %s/%s %s', $reference, $stage, $status, json_encode($safeContext)));
    }
}

/**
 * @return array{ok:bool,http_code:int,location:string,response_excerpt:string,error:string}
 */
function submit_to_zoho(array $payload): array
{
    $encoded = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
    $result = ['ok' => false, 'http_code' => 0, 'location' => '', 'response_excerpt' => '', 'error' => ''];

    if (function_exists('curl_init')) {
        $headers = [];
        $curl = curl_init(RGTS_ZOHO_ENDPOINT);
        if ($curl === false) {
            $result['error'] = 'curl_init_failed';
            return $result;
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encoded,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: text/html,application/xhtml+xml',
            ],
            CURLOPT_USERAGENT => 'Resplendent-Website/' . RGTS_RELEASE,
            CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $headerLine) use (&$headers): int {
                $length = strlen($headerLine);
                $parts = explode(':', $headerLine, 2);
                if (count($parts) === 2) $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                return $length;
            },
        ]);
        $response = curl_exec($curl);
        $result['http_code'] = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($response === false || $curlError !== '') {
            $result['error'] = $curlError !== '' ? $curlError : 'empty_curl_response';
            return $result;
        }
        $result['location'] = (string)($headers['location'] ?? '');
        $result['response_excerpt'] = limit_text(trim(preg_replace('/\s+/', ' ', strip_tags((string)$response)) ?? ''), 900);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: text/html,application/xhtml+xml\r\nUser-Agent: Resplendent-Website/" . RGTS_RELEASE . "\r\n",
                'content' => $encoded,
                'timeout' => 25,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
        ]);
        $response = @file_get_contents(RGTS_ZOHO_ENDPOINT, false, $context);
        if ($response === false) {
            $result['error'] = 'stream_request_failed';
            return $result;
        }
        $headers = $http_response_header ?? [];
        $statusLine = (string)($headers[0] ?? '');
        if (preg_match('/\s(\d{3})\s/', $statusLine, $match)) $result['http_code'] = (int)$match[1];
        foreach ($headers as $header) {
            if (stripos($header, 'Location:') === 0) $result['location'] = trim(substr($header, 9));
        }
        $result['response_excerpt'] = limit_text(trim(preg_replace('/\s+/', ' ', strip_tags((string)$response)) ?? ''), 900);
    }

    $combined = strtolower($result['location'] . ' ' . $result['response_excerpt']);
    $explicitFailure = preg_match('/invalid|error|failed|failure|unable to process|mandatory field|captcha/i', $combined) === 1;
    $acceptedHttp = in_array($result['http_code'], [200, 201, 202, 301, 302, 303], true);
    $result['ok'] = $acceptedHttp && !$explicitFailure;
    if (!$result['ok'] && $result['error'] === '') $result['error'] = $explicitFailure ? 'zoho_rejected_payload' : 'unexpected_http_status';
    return $result;
}

$reference = reference_id((string)($_POST['reference'] ?? ''));
pipeline_log($reference, 'received', 'ok', [
    'ip_hash' => hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
    'user_agent' => clean((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 240),
]);

// Honeypot submissions are acknowledged without touching CRM or SMTP.
// The trap uses a non-semantic field name to reduce browser/password-manager autofill.
$honeypot = trim((string)($_POST['rgts_fax_number'] ?? ''));
if ($honeypot !== '') {
    pipeline_log($reference, 'antispam', 'blocked', [
        'trap_field' => 'rgts_fax_number',
        'value_length' => strlen($honeypot),
        'value_excerpt' => clean($honeypot, 120),
    ]);
    redirect_result('success', $reference, 'antispam');
}
pipeline_log($reference, 'antispam', 'ok');

$name = clean((string)($_POST['name'] ?? ''), 120);
$email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$phone = clean((string)($_POST['phone'] ?? ''), 40);
$company = clean((string)($_POST['company'] ?? ''), 160);
$service = clean((string)($_POST['service'] ?? ''), 80);
$message = limit_text(trim(strip_tags((string)($_POST['message'] ?? ''))), 6000);
$consent = isset($_POST['consent']);

if ($name === '' || !$email || $phone === '' || $service === '' || $message === '' || !$consent) {
    pipeline_log($reference, 'validation', 'failed', ['reason' => 'missing_or_invalid_required_field']);
    redirect_result('error', $reference, 'validation');
}
if (!preg_match('/^[+()0-9\s.\-]{7,24}$/', $phone)) {
    pipeline_log($reference, 'validation', 'failed', ['reason' => 'invalid_phone']);
    redirect_result('error', $reference, 'validation');
}

$routes = [
    'General Enquiry' => 'info@resplendentglobaltravel.com',
    'Leisure Travel' => 'bookings@resplendentglobaltravel.com',
    'Flight Booking' => 'bookings@resplendentglobaltravel.com',
    'Hotel Reservation' => 'bookings@resplendentglobaltravel.com',
    'International Medical Cover' => 'bookings@resplendentglobaltravel.com',
    'Car Rental' => 'bookings@resplendentglobaltravel.com',
    'Corporate Travel' => 'corporate@resplendentglobaltravel.com',
    'Business Connections' => 'business@resplendentglobaltravel.com',
    'Customer Support' => 'support@resplendentglobaltravel.com',
    'Accounts & Billing' => 'accounts@resplendentglobaltravel.com',
    'Multi-service Request' => 'info@resplendentglobaltravel.com',
];
if (!isset($routes[$service])) {
    pipeline_log($reference, 'validation', 'failed', ['reason' => 'unknown_service', 'service' => $service]);
    redirect_result('error', $reference, 'validation');
}
$recipient = $routes[$service];
pipeline_log($reference, 'validation', 'ok', ['service' => $service, 'route' => $recipient]);

$labels = [
    'destination' => 'Destination / Country', 'dates' => 'Preferred dates', 'travellers' => 'Travellers / delegates',
    'travel_flexibility' => 'Travel flexibility', 'accommodation_preference' => 'Accommodation preference',
    'budget' => 'Approximate budget', 'interests' => 'Interests', 'corporate_industry' => 'Corporate industry',
    'delegates' => 'Number of delegates', 'corporate_objective' => 'Corporate travel objective',
    'business_industry' => 'Business industry / sector', 'partner_profile' => 'Target partner profile',
    'meeting_objective' => 'Meeting objective'
];
$details = [];
foreach ($labels as $key => $label) {
    $value = clean((string)($_POST[$key] ?? ''), 500);
    if ($value !== '') $details[] = "$label: $value";
}
if (isset($_POST['combined_services']) && is_array($_POST['combined_services'])) {
    $allowed = ['Flights', 'Hotels', 'Corporate Travel', 'Business Connections', 'Medical Cover', 'Car Rental'];
    $selected = array_values(array_intersect($allowed, array_map('strval', $_POST['combined_services'])));
    if ($selected) $details[] = 'Combined services: ' . implode(', ', $selected);
}

$subject = "[$reference] $service enquiry — $name";
$body = "RESPLENDENT GLOBAL TRAVEL SOLUTIONS\nNEW WEBSITE ENQUIRY\n\n";
$body .= "Reference: $reference\nDepartment: $recipient\nService: $service\nSubmitted: " . date('j F Y, H:i T') . "\n\n";
$body .= "CLIENT DETAILS\nName: $name\nEmail: $email\nPhone / WhatsApp: $phone\nCompany: " . ($company ?: '-') . "\n\n";
if ($details) $body .= "REQUEST DETAILS\n" . implode("\n", $details) . "\n\n";
$body .= "CLIENT MESSAGE\n$message\n";

$nameParts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
if (count($nameParts) > 1) {
    $zohoLastName = (string)array_pop($nameParts);
    $zohoFirstName = implode(' ', $nameParts);
} else {
    $zohoFirstName = '';
    $zohoLastName = $name;
}

$zohoDescription = "Website enquiry reference: $reference\nService: $service\n";
if ($company !== '') $zohoDescription .= "Company: $company\n";
if ($details) $zohoDescription .= implode("\n", $details) . "\n";
$zohoDescription .= "\nMessage:\n$message";

$zohoPayload = [
    'xnQsjsdp' => '593369a07e876f128cf6ba8c6f6db21ddc5b607d361d2b31e9932a6a1fecbf0d',
    'zc_gad' => '',
    'xmIwtLD' => 'de858a0a0ffa0ce6b8c709d8ded691f994c0b4ff9d2f7020c568c07e9fc7eeca1ebe141d1bbc2f0a52b5b97438773630',
    'actionType' => 'TGVhZHM=',
    'returnURL' => 'https://www.resplendentglobaltravel.com/contact.html?status=success&reference=' . rawurlencode($reference) . '#form-status',
    'First Name' => $zohoFirstName,
    'Last Name' => $zohoLastName,
    'Email' => (string)$email,
    'Phone' => $phone,
    'Description' => limit_text($zohoDescription, 32000),
    'aG9uZXlwb3Q' => '',
];

pipeline_log($reference, 'zoho', 'started');
$zoho = submit_to_zoho($zohoPayload);
pipeline_log($reference, 'zoho', $zoho['ok'] ? 'ok' : 'failed', [
    'http_code' => $zoho['http_code'],
    'location' => $zoho['location'],
    'response_excerpt' => $zoho['response_excerpt'],
    'error' => $zoho['error'],
]);
if (!$zoho['ok']) redirect_result('error', $reference, 'zoho');

$configPath = dirname(__DIR__) . '/rgts-mail-config.php';
if (!is_file($configPath)) {
    pipeline_log($reference, 'smtp_config', 'failed', ['path' => $configPath, 'reason' => 'missing']);
    redirect_result('partial', $reference, 'smtp_config');
}
$config = require $configPath;
if (!is_array($config)) {
    pipeline_log($reference, 'smtp_config', 'failed', ['reason' => 'invalid_return_value']);
    redirect_result('partial', $reference, 'smtp_config');
}
pipeline_log($reference, 'smtp_config', 'ok', [
    'host' => (string)($config['smtp_host'] ?? ''),
    'port' => (string)($config['smtp_port'] ?? ''),
    'encryption' => (string)($config['smtp_encryption'] ?? 'ssl'),
    'username_present' => !empty($config['smtp_username']) ? 'yes' : 'no',
]);

require_once __DIR__ . '/includes/SmtpMailer.php';
$centralCopy = (string)($config['central_copy'] ?? 'info@resplendentglobaltravel.com');
$cc = ($centralCopy !== '' && strcasecmp($centralCopy, $recipient) !== 0) ? [$centralCopy] : [];
try {
    pipeline_log($reference, 'smtp', 'started', ['recipient' => $recipient, 'cc' => implode(',', $cc)]);
    $mailer = new SmtpMailer($config);
    $smtpResult = $mailer->send(
        [$recipient],
        $cc,
        (string)($config['from_email'] ?? 'info@resplendentglobaltravel.com'),
        (string)($config['from_name'] ?? 'Resplendent Website'),
        (string)$email,
        $subject,
        $body
    );
    pipeline_log($reference, 'smtp', 'ok', $smtpResult);
} catch (Throwable $error) {
    pipeline_log($reference, 'smtp', 'failed', ['message' => $error->getMessage()]);
    redirect_result('partial', $reference, 'smtp');
}

// Send a separate acknowledgement only after CRM capture and internal routing succeed.
$customerSubject = "We've received your enquiry — $reference";
$customerBody = "Dear $name,

";
$customerBody .= "Thank you for contacting Resplendent Global Travel Solutions.

";
$customerBody .= "We have successfully received your $service enquiry and assigned it the reference number $reference.

";
$customerBody .= "Your request is currently being reviewed by our team. A Resplendent consultant will contact you as soon as the initial review is complete. We aim to respond within one business day, and often much sooner.

";
$customerBody .= "Should you need to add further information, simply reply to this email and quote your reference number.

";
$customerBody .= "Warm regards,

";
$customerBody .= "Resplendent Global Travel Solutions
";
$customerBody .= "Luxury Travel | Corporate Travel | Global Business Connections
";
$customerBody .= "info@resplendentglobaltravel.com
";
$customerBody .= "+254 724 785 341
";

try {
    pipeline_log($reference, 'customer_ack', 'started', ['recipient' => (string)$email]);
    $ackMailer = new SmtpMailer($config);
    $ackResult = $ackMailer->send(
        [(string)$email],
        [],
        (string)($config['from_email'] ?? 'info@resplendentglobaltravel.com'),
        'Resplendent Global Travel Solutions',
        $centralCopy !== '' ? $centralCopy : 'info@resplendentglobaltravel.com',
        $customerSubject,
        $customerBody
    );
    pipeline_log($reference, 'customer_ack', 'ok', $ackResult);
} catch (Throwable $error) {
    // The enquiry is already safely in Zoho and the responsible department's inbox.
    // Keep the customer-facing submission successful while preserving the failure for operations.
    pipeline_log($reference, 'customer_ack', 'failed', ['message' => $error->getMessage()]);
}

pipeline_log($reference, 'complete', 'ok', ['customer_ack' => 'attempted']);
redirect_result('success', $reference, 'complete');
