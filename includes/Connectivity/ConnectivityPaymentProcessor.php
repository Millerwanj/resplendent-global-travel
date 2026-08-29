<?php
declare(strict_types=1);

require_once __DIR__ . '/connectivity-bootstrap.php';
require_once dirname(__DIR__) . '/Payments/payment-bootstrap.php';
require_once dirname(__DIR__) . '/Payments/Providers/PesapalProvider.php';

use Resplendent\Payments\Providers\PesapalProvider;

/** @return array<string,mixed> */
function rgts_connectivity_verify_payment(array $order, string $trackingId): array
{
    $trackingId = trim($trackingId);
    if ($trackingId === '') throw new RuntimeException('Payment tracking reference is missing.');

    $config = rgts_payment_config();
    $pcfg = is_array($config['providers']['pesapal'] ?? null) ? $config['providers']['pesapal'] : [];
    $provider = new PesapalProvider($pcfg);
    $verified = $provider->verifyTransaction($trackingId);

    $expectedAmount = number_format((float)($order['payload']['amount'] ?? 0), 2, '.', '');
    $paidAmount = number_format((float)($verified['amount'] ?? 0), 2, '.', '');
    $expectedCurrency = strtoupper((string)($order['payload']['currency'] ?? ''));
    $paidCurrency = strtoupper((string)($verified['currency'] ?? ''));

    $order['payment']['provider_reference'] = $trackingId;
    $order['payment']['merchant_reference'] = (string)($verified['merchant_reference'] ?? $order['id'] ?? '');
    $order['payment']['status'] = (string)($verified['status'] ?? 'pending');
    $order['payment']['verified'] = !empty($verified['verified']);
    $order['payment']['confirmation_code'] = (string)($verified['confirmation_code'] ?? '');
    $order['payment']['payment_method'] = (string)($verified['payment_method'] ?? '');

    if (!empty($verified['verified']) && ($verified['status'] ?? '') === 'completed') {
        $order['status'] = ($expectedAmount === $paidAmount && $expectedCurrency === $paidCurrency)
            ? 'PAID'
            : 'PAYMENT_REVIEW';
    } elseif (in_array(($verified['status'] ?? ''), ['failed', 'reversed'], true)) {
        $order['status'] = 'PAYMENT_FAILED';
    } else {
        $order['status'] = 'PAYMENT_PENDING';
    }

    return $order;
}
