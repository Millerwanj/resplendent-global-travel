<?php
declare(strict_types=1);

require_once __DIR__ . '/connectivity-bootstrap.php';
require_once dirname(__DIR__) . '/Payments/payment-bootstrap.php';
require_once dirname(__DIR__) . '/Payments/Providers/PesapalProvider.php';
require_once __DIR__ . '/ConnectivityFulfillmentService.php';

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
    $expectedReference = (string)($order['id'] ?? '');
    $paidReference = trim((string)($verified['merchant_reference'] ?? ''));
    $storedTrackingId = trim((string)($order['payment']['provider_reference'] ?? ''));

    $referenceMatches = $expectedReference !== ''
        && $paidReference !== ''
        && hash_equals($expectedReference, $paidReference);
    $trackingMatches = $storedTrackingId !== ''
        && hash_equals($storedTrackingId, $trackingId);
    $sameCurrency = $expectedCurrency !== ''
        && $paidCurrency !== ''
        && hash_equals($expectedCurrency, $paidCurrency);
    $amountMatches = $expectedAmount === $paidAmount;

    // PesaPal can collect in a locally converted currency while the merchant
    // order remains denominated in USD. For a provider order created by us,
    // COMPLETED + exact tracking ID + exact merchant reference is authoritative.
    // Same-currency payments continue to require an exact amount match.
    $paymentMethod = strtolower(trim((string)($verified['payment_method'] ?? '')));

    $providerConvertedPayment = !$sameCurrency
        && $paidCurrency !== ''
        && (float)$paidAmount > 0
        && $trackingMatches
        && $referenceMatches;

    // PesaPal M-Pesa quirk observed in live verification:
    // a USD merchant order can be collected in KES, while GetTransactionStatus
    // returns the KES numeric amount but still labels the currency as USD.
    // Trust this only for a server-verified COMPLETED M-Pesa transaction tied
    // to the exact provider tracking ID and exact Resplendent merchant reference.
    $pesapalMpesaConvertedPayment = $sameCurrency
        && !$amountMatches
        && $expectedCurrency === 'USD'
        && str_contains($paymentMethod, 'mpesa')
        && (float)$paidAmount > 0
        && $trackingMatches
        && $referenceMatches;

    $matchesOrder = $referenceMatches
        && $trackingMatches
        && (
            ($sameCurrency && $amountMatches)
            || $providerConvertedPayment
            || $pesapalMpesaConvertedPayment
        );

    $order['payment']['provider_reference'] = $trackingId;
    $order['payment']['merchant_reference'] = $paidReference;
    $order['payment']['status'] = (string)($verified['status'] ?? 'pending');
    $order['payment']['verified'] = !empty($verified['verified']);
    $order['payment']['matches_order'] = $matchesOrder;
    $order['payment']['confirmation_code'] = (string)($verified['confirmation_code'] ?? '');
    $order['payment']['payment_method'] = (string)($verified['payment_method'] ?? '');
    $order['payment']['expected_amount'] = $expectedAmount;
    $order['payment']['expected_currency'] = $expectedCurrency;
    $order['payment']['paid_amount'] = $paidAmount;
    $order['payment']['paid_currency'] = $paidCurrency;
    $order['payment']['provider_converted'] = $providerConvertedPayment || $pesapalMpesaConvertedPayment;
    $order['payment']['conversion_mode'] = $pesapalMpesaConvertedPayment
        ? 'pesapal_mpesa_local_collection'
        : ($providerConvertedPayment ? 'provider_currency_conversion' : 'none');
    $order['payment']['verified_at'] = gmdate('c');

    if (!empty($verified['verified']) && ($verified['status'] ?? '') === 'completed') {
        $order['status'] = $matchesOrder ? 'PAID' : 'PAYMENT_REVIEW';
    } elseif (in_array(($verified['status'] ?? ''), ['failed', 'reversed'], true)) {
        $order['status'] = 'PAYMENT_FAILED';
    } else {
        $order['status'] = 'PAYMENT_PENDING';
    }
    return $order;
}

/** @return array<string,mixed> */
function rgts_connectivity_reconcile_and_fulfill(ConnectivityOrderStore $store, array $order, string $trackingId): array
{
    $order = rgts_connectivity_verify_payment($order, $trackingId);
    $store->save($order);
    if (($order['status'] ?? '') !== 'PAID') return $order;

    $service = new ConnectivityFulfillmentService($store);
    try {
        return $service->fulfill((string)$order['id']);
    } catch (Throwable $e) {
        error_log('RGTS connectivity fulfilment: ' . $e->getMessage());
        $latest = $store->get((string)$order['id']);
        return is_array($latest) ? $latest : $order;
    }
}
