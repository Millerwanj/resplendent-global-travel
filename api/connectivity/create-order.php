<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/Connectivity/connectivity-bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/Payments/payment-bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/Payments/Providers/PesapalProvider.php';

use Resplendent\Payments\Providers\PesapalProvider;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rgts_connectivity_json(['ok' => false, 'message' => 'POST required.'], 405);
}

try {
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) throw new InvalidArgumentException('Invalid request.');

    $planId = trim((string)($input['plan_id'] ?? ''));
    $catalog = rgts_connectivity_catalog();
    if (!isset($catalog[$planId])) throw new InvalidArgumentException('Unknown connectivity plan.');
    $plan = $catalog[$planId];

    $name = trim((string)($input['name'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $phone = trim((string)($input['phone'] ?? ''));

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Name and a valid email address are required.');
    }
    if (strlen($name) > 120 || strlen($email) > 190 || strlen($phone) > 40) {
        throw new InvalidArgumentException('Customer details are too long.');
    }

    $config = rgts_payment_config();
    $pcfg = is_array($config['providers']['pesapal'] ?? null) ? $config['providers']['pesapal'] : [];

    // Hard safety guard: this v1 endpoint works ONLY in Pesapal sandbox.
    if (strtolower(trim((string)($pcfg['environment'] ?? ''))) !== 'sandbox') {
        throw new RuntimeException('Instant Connectivity Checkout v1 is locked to Pesapal sandbox.');
    }

    $sandboxConfig = is_array($pcfg['environments']['sandbox'] ?? null)
        ? $pcfg['environments']['sandbox']
        : $pcfg;
    foreach (['consumer_key', 'consumer_secret', 'notification_id'] as $required) {
        if (trim((string)($sandboxConfig[$required] ?? '')) === '') {
            throw new RuntimeException('Pesapal sandbox configuration is incomplete.');
        }
    }

    $store = new ConnectivityOrderStore();
    $order = $store->create([
        'customer' => ['name' => $name, 'email' => $email, 'phone' => $phone],
        'plan' => $plan,
        'amount' => $plan['price'],
        'currency' => $plan['currency'],
    ]);

    $parts = preg_split('/\s+/', $name, 2) ?: [$name];
    $provider = new PesapalProvider($pcfg);
    $base = rgts_connectivity_base_url();

    $checkout = $provider->createCheckout([
        'merchant_reference' => $order['id'],
        'amount' => $plan['price'],
        'currency' => $plan['currency'],
        'description' => 'Resplendent connectivity sandbox test',
        'billing_address' => [
            'email_address' => $email,
            'phone_number' => $phone,
            'country_code' => 'KE',
            'first_name' => (string)($parts[0] ?? ''),
            'last_name' => (string)($parts[1] ?? ''),
        ],
    ], [
        'callback_url' => $base . '/api/connectivity/callback.php?order_id=' . rawurlencode($order['id']),
        'notification_url' => $base . '/api/payments/ipn.php',
        'cancellation_url' => $base . '/connectivity-checkout.html?cancelled=1',
    ]);

    $order['payment']['provider_reference'] = (string)$checkout['provider_reference'];
    $order['payment']['merchant_reference'] = $order['id'];
    $store->save($order);

    rgts_connectivity_json([
        'ok' => true,
        'order_id' => $order['id'],
        'redirect_url' => (string)$checkout['redirect_url'],
    ]);
} catch (Throwable $e) {
    error_log('RGTS connectivity create: ' . $e->getMessage());
    rgts_connectivity_json(['ok' => false, 'message' => $e->getMessage()], 400);
}
