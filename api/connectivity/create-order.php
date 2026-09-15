<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/Connectivity/connectivity-bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/Payments/payment-bootstrap.php';

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
    $paymentProvider = strtolower(trim((string)($config['active_online_provider'] ?? '')));
    if (!in_array($paymentProvider, ['paystack', 'pesapal'], true)) {
        throw new RuntimeException('Secure online checkout is not configured.');
    }
    $providerConfig = is_array($config['providers'][$paymentProvider] ?? null) ? $config['providers'][$paymentProvider] : [];
    if (empty($providerConfig['enabled'])) throw new RuntimeException('Secure online checkout is not enabled.');
    $paymentEnvironment = strtolower(trim((string)($providerConfig['environment'] ?? '')));
    $isTestEnvironment = ($paymentProvider === 'paystack' && $paymentEnvironment === 'test')
        || ($paymentProvider === 'pesapal' && $paymentEnvironment === 'sandbox');
    if (!empty($plan['test_only']) && !$isTestEnvironment) {
        throw new RuntimeException('The sandbox test product cannot be purchased through live payment processing.');
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
    ], $paymentProvider);

    $parts = preg_split('/\s+/', $name, 2) ?: [$name];
    $coordinator = rgts_payment_coordinator();
    $base = rgts_connectivity_base_url();
    $description = !empty($plan['test_only']) ? 'Resplendent connectivity acceptance test' : 'Resplendent eSIM · ' . (string)($plan['destination'] ?? 'Travel connectivity');

    $notificationUrl = $paymentProvider === 'paystack'
        ? $base . '/api/payments/paystack-webhook.php'
        : $base . '/api/payments/ipn.php';
    $checkout = $coordinator->createCheckout([
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
        'notification_url' => $notificationUrl,
        'cancellation_url' => $base . '/esim-order.html?cancelled=1',
    ], $paymentProvider);

    $order['payment']['provider_reference'] = (string)$checkout['provider_reference'];
    $order['payment']['merchant_reference'] = $order['id'];
    $store->save($order);

    rgts_connectivity_json(['ok' => true, 'order_id' => $order['id'], 'redirect_url' => (string)$checkout['redirect_url']]);
} catch (Throwable $e) {
    error_log('RGTS connectivity create: ' . $e->getMessage());
    rgts_connectivity_json(['ok' => false, 'message' => $e->getMessage()], 400);
}
