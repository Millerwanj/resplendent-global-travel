<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/Payments/payment-bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/Payments/Providers/PaystackProvider.php';
require_once dirname(__DIR__, 2) . '/includes/Connectivity/ConnectivityPaymentProcessor.php';
require_once dirname(__DIR__, 2) . '/includes/OperationsStore.php';
require_once dirname(__DIR__, 2) . '/includes/Payments/PaymentReconciler.php';

use Resplendent\Payments\Providers\PaystackProvider;
use Resplendent\Payments\PaymentReconciler;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

try {
    $raw = (string)file_get_contents('php://input');
    $signature = trim((string)($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? ''));
    $config = rgts_payment_config();
    $providerConfig = is_array($config['providers']['paystack'] ?? null) ? $config['providers']['paystack'] : [];
    $provider = new PaystackProvider($providerConfig);
    if (!$provider->verifyWebhookSignature($raw, $signature)) {
        http_response_code(401);
        echo json_encode(['ok' => false]);
        exit;
    }
    $event = json_decode($raw, true);
    if (!is_array($event)) throw new RuntimeException('Invalid webhook payload.');

    // Only successful charges can initiate fulfilment. All others are safely acknowledged.
    if (($event['event'] ?? '') !== 'charge.success') {
        http_response_code(200);
        echo json_encode(['ok' => true]);
        exit;
    }
    $refs = $provider->parseNotification($event);
    $store = new ConnectivityOrderStore();
    $order = $store->get((string)$refs['merchant_reference'])
        ?? $store->findByProviderReference((string)$refs['provider_reference']);
    if (is_array($order)) {
        if (($order['payment']['provider'] ?? '') !== 'paystack') throw new RuntimeException('Payment provider mismatch.');
        // The fulfilment service and order store both lock/idempotently re-check state.
        rgts_connectivity_reconcile_and_fulfill($store, $order, (string)$refs['provider_reference']);
    } else {
        $reconciler = new PaymentReconciler(new OperationsStore(), $provider, 'paystack');
        $reconciler->reconcilePaystack((string)$refs['provider_reference']);
    }
    http_response_code(200);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('RGTS Paystack webhook: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
