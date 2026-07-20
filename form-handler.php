<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact.html');
    exit;
}

function clean(string $value, int $max = 2000): string {
    $value = trim(strip_tags($value));
    $value = preg_replace('/[\r\n]+/', ' ', $value) ?? '';
    return mb_substr($value, 0, $max);
}

function redirect_result(string $status, string $reference = ''): never {
    $query = http_build_query(array_filter(['status' => $status, 'reference' => $reference]));
    header('Location: contact.html?' . $query . '#form-status', true, 303);
    exit;
}

// Honeypot: bots commonly fill this hidden field.
if (!empty($_POST['website'] ?? '')) {
    redirect_result('success');
}

$name = clean((string)($_POST['name'] ?? ''), 120);
$email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$phone = clean((string)($_POST['phone'] ?? ''), 40);
$company = clean((string)($_POST['company'] ?? ''), 160);
$service = clean((string)($_POST['service'] ?? ''), 80);
$message = trim(strip_tags((string)($_POST['message'] ?? '')));
$message = mb_substr($message, 0, 6000);
$consent = isset($_POST['consent']);

if ($name === '' || !$email || $phone === '' || $service === '' || $message === '' || !$consent) {
    redirect_result('error');
}
if (!preg_match('/^[+()0-9\s.\-]{7,24}$/', $phone)) {
    redirect_result('error');
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
    redirect_result('error');
}
$recipient = $routes[$service];
$reference = clean((string)($_POST['reference'] ?? ''), 40);
if (!preg_match('/^RGTS-\d{8}-[A-Z0-9]{4}$/', $reference)) {
    $reference = 'RGTS-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
}

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
    $allowed = ['Flights','Hotels','Corporate Travel','Business Connections','Medical Cover','Car Rental'];
    $selected = array_values(array_intersect($allowed, array_map('strval', $_POST['combined_services'])));
    if ($selected) $details[] = 'Combined services: ' . implode(', ', $selected);
}

$subject = "[$reference] $service enquiry — $name";
$body = "RESPLENDENT GLOBAL TRAVEL SOLUTIONS\n";
$body .= "NEW WEBSITE ENQUIRY\n\n";
$body .= "Reference: $reference\nDepartment: $recipient\nService: $service\nSubmitted: " . date('j F Y, H:i T') . "\n\n";
$body .= "CLIENT DETAILS\nName: $name\nEmail: $email\nPhone / WhatsApp: $phone\nCompany: " . ($company ?: '-') . "\n\n";
if ($details) $body .= "REQUEST DETAILS\n" . implode("\n", $details) . "\n\n";
$body .= "CLIENT MESSAGE\n$message\n";

$configPath = dirname(__DIR__) . '/rgts-mail-config.php';
if (!is_file($configPath)) {
    error_log("[$reference] RGTS SMTP configuration file is missing: $configPath");
    redirect_result('error');
}
$config = require $configPath;
if (!is_array($config)) {
    error_log("[$reference] RGTS SMTP configuration is invalid.");
    redirect_result('error');
}

require_once __DIR__ . '/includes/SmtpMailer.php';

$centralCopy = (string)($config['central_copy'] ?? 'info@resplendentglobaltravel.com');
$cc = ($centralCopy !== '' && strcasecmp($centralCopy, $recipient) !== 0) ? [$centralCopy] : [];

try {
    $mailer = new SmtpMailer($config);
    $mailer->send(
        [$recipient],
        $cc,
        (string)($config['from_email'] ?? 'info@resplendentglobaltravel.com'),
        (string)($config['from_name'] ?? 'Resplendent Website'),
        (string)$email,
        $subject,
        $body
    );
} catch (Throwable $error) {
    error_log("[$reference] RGTS SMTP delivery failed: " . $error->getMessage());
    redirect_result('error');
}

redirect_result('success', $reference);
