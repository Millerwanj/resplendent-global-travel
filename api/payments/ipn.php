<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 2) . '/includes/OperationsStore.php';
require_once dirname(__DIR__, 2) . '/includes/Payments/payment-bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/Payments/PaymentReconciler.php';

use Resplendent\Payments\PaymentReconciler;
use Resplendent\Payments\Providers\PesapalProvider;

try {
    // Safe health check for deployment monitoring.
    if (empty($_REQUEST) && stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') === false) {
        http_response_code(200);
        echo json_encode(['status' => 200, 'message' => 'Resplendent PesaPal IPN listener ready.']);
        exit;
    }

    $notification = $_REQUEST;
    if (stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
        $json = json_decode((string)file_get_contents('php://input'), true);
        if (is_array($json)) $notification = array_merge($notification, $json);
    }

    $config = rgts_payment_config();
    $providerConfig = is_array($config['providers']['pesapal'] ?? null) ? $config['providers']['pesapal'] : [];
    $provider = new PesapalProvider($providerConfig);
    $refs = $provider->parseNotification($notification);

    // Connectivity orders use the same registered Pesapal IPN endpoint but
    // reconcile in their isolated order ledger, not the invoice ledger.
    if (str_starts_with((string)($refs['merchant_reference'] ?? ''), 'RGTS-CON-')) {
        require_once dirname(__DIR__, 2) . '/includes/Connectivity/ConnectivityPaymentProcessor.php';
        $connectivityStore = new ConnectivityOrderStore();
        $connectivityOrder = $connectivityStore->get((string)$refs['merchant_reference'])
            ?? $connectivityStore->findByProviderReference((string)$refs['provider_reference']);
        if (!is_array($connectivityOrder)) throw new RuntimeException('Matching connectivity order was not found.');
        $connectivityOrder = rgts_connectivity_verify_payment($connectivityOrder, (string)$refs['provider_reference']);
        $connectivityStore->save($connectivityOrder);
        echo json_encode([
            'orderNotificationType' => (string)($notification['OrderNotificationType'] ?? $notification['orderNotificationType'] ?? 'IPNCHANGE'),
            'orderTrackingId' => (string)$refs['provider_reference'],
            'orderMerchantReference' => (string)$connectivityOrder['id'],
            'status' => 200,
        ]);
        exit;
    }

    $reconciler = new PaymentReconciler(new OperationsStore(), $provider);
    $verified = $reconciler->reconcilePesapal($refs['provider_reference']);

    echo json_encode([
        'orderNotificationType' => (string)($notification['OrderNotificationType'] ?? $notification['orderNotificationType'] ?? 'IPNCHANGE'),
        'orderTrackingId' => $refs['provider_reference'],
        'orderMerchantReference' => (string)($verified['merchant_reference'] ?? $refs['merchant_reference']),
        'status' => 200,
    ]);
} catch (Throwable $e) {
    error_log('RGTS PesaPal IPN reconciliation: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 500, 'message' => 'Payment notification could not be processed.']);
}
