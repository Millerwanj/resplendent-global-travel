<?php
declare(strict_types=1);

use Resplendent\Payments\PaymentCoordinator;
use Resplendent\Payments\Providers\PesapalProvider;
use Resplendent\Payments\Providers\PaystackProvider;

require_once __DIR__ . '/PaymentCoordinator.php';
require_once __DIR__ . '/Providers/PesapalProvider.php';
require_once __DIR__ . '/Providers/PaystackProvider.php';

/** @return array<string,mixed> */
function rgts_payment_config(): array
{
    $configured = trim((string)(getenv('RGTS_PAYMENT_CONFIG') ?: ''));
    $path = $configured !== '' ? $configured : dirname(__DIR__, 3) . '/rgts-payment-config.php';
    if (!is_file($path)) return [];
    $config = require $path;
    return is_array($config) ? $config : [];
}

function rgts_payment_coordinator(): PaymentCoordinator
{
    $config = rgts_payment_config();
    $coordinator = new PaymentCoordinator($config);
    $pesapal = is_array($config['providers']['pesapal'] ?? null) ? $config['providers']['pesapal'] : [];
    if ($pesapal !== []) $coordinator->register(new PesapalProvider($pesapal));
    $paystack = is_array($config['providers']['paystack'] ?? null) ? $config['providers']['paystack'] : [];
    if ($paystack !== []) $coordinator->register(new PaystackProvider($paystack));
    return $coordinator;
}
