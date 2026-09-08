<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/Connectivity/connectivity-bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/Payments/payment-bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/Payments/Providers/PesapalProvider.php';

use Resplendent\Payments\Providers\PesapalProvider;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    rgts_connectivity_json(['ok' => false, 'message' => 'POST required.'], 405);
}

try {
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) throw new InvalidArgumentException('Invalid request.');

    $planId = strtolower(trim((string)($input['plan_id'] ?? '')));
    $destinationId = strtolower(trim((string)($input['destination_id'] ?? '')));
    $catalog = rgts_connectivity_catalog();
    if (isset($catalog[$planId])) {
        $plan = $catalog[$planId];
    } else {
        $plan = rgts_connectivity_resolve_live_plan($destinationId, $planId);
    }

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
    $paymentEnvironment = strtolower(trim((string)($pcfg['environment'] ?? 'sandbox')));
    if (!in_array($paymentEnvironment, ['sandbox','production'], true)) throw new RuntimeException('PesaPal environment is invalid.');
    if (!empty($plan['test_only']) && $paymentEnvironment !== 'sandbox') {
        throw new RuntimeException('The sandbox test product cannot be purchased through live PesaPal.');
    }

    $activePesaConfig = is_array($pcfg['environments'][$paymentEnvironment] ?? null)
        ? $pcfg['environments'][$paymentEnvironment]
        : $pcfg;
    foreach (['consumer_key', 'consumer_secret', 'notification_id'] as $required) {
        if (trim((string)($activePesaConfig[$required] ?? '')) === '') {
            throw new RuntimeException('PesaPal ' . $paymentEnvironment . ' configuration is incomplete.');
        }
    }

    $price = number_format((float)($plan['price'] ?? 0), 2, '.', '');
    $currency = strtoupper(trim((string)($plan['currency'] ?? '')));
    if ((float)$price <= 0 || !preg_match('/^[A-Z]{3}$/', $currency)) throw new RuntimeException('The selected plan has invalid commercial terms.');

    $store = new ConnectivityOrderStore();
    $order = $store->create([
        'customer' => ['name' => $name, 'email' => $email, 'phone' => $phone],
        'plan' => $plan,
        'amount' => $price,
        'currency' => $currency,
    ]);

    $parts = preg_split('/\s+/', $name, 2) ?: [$name];
    $provider = new PesapalProvider($pcfg);
    $base = rgts_connectivity_base_url();
    $description = !empty($plan['test_only']) ? 'Resplendent connectivity acceptance test' : 'Resplendent eSIM · ' . (string)($plan['destination'] ?? 'Travel connectivity');

    $checkout = $provider->createCheckout([
        'merchant_reference' => $order['id'],
        'amount' => $price,
        'currency' => $currency,
        'description' => substr($description, 0, 100),
        'billing_address' => [
            'email_address' => $email,
            'phone_number' => $phone,
            'first_name' => (string)($parts[0] ?? ''),
            'last_name' => (string)($parts[1] ?? ''),
        ],
    ], [
        'callback_url' => $base . '/api/connectivity/callback.php?order_id=' . rawurlencode($order['id']),
        'notification_url' => $base . '/api/payments/ipn.php',
        'cancellation_url' => $base . '/esim-order.html?cancelled=1',
    ]);

    $order['payment']['provider_reference'] = (string)$checkout['provider_reference'];
    $order['payment']['merchant_reference'] = $order['id'];
    $store->save($order);

    rgts_connectivity_json(['ok' => true, 'order_id' => $order['id'], 'redirect_url' => (string)$checkout['redirect_url']]);
} catch (Throwable $e) {
    error_log('RGTS connectivity create: ' . $e->getMessage());
    rgts_connectivity_json(['ok' => false, 'message' => $e->getMessage()], 400);
}
