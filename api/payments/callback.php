<?php
declare(strict_types=1);

header('Cache-Control: no-store');
require_once dirname(__DIR__, 2) . '/includes/OperationsStore.php';
require_once dirname(__DIR__, 2) . '/includes/Payments/payment-bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/Payments/PaymentReconciler.php';

use Resplendent\Payments\PaymentReconciler;
use Resplendent\Payments\Providers\PesapalProvider;
use Resplendent\Payments\Providers\PaystackProvider;

$trackingId = trim((string)($_GET['OrderTrackingId'] ?? $_GET['orderTrackingId'] ?? ''));
$paystackReference = trim((string)($_GET['reference'] ?? $_GET['trxref'] ?? ''));
$invoiceId = trim((string)($_GET['invoice_id'] ?? ''));
$result = 'pending';

try {
    if ($paystackReference !== '') {
        $config = rgts_payment_config();
        $providerConfig = is_array($config['providers']['paystack'] ?? null) ? $config['providers']['paystack'] : [];
        $reconciler = new PaymentReconciler(new OperationsStore(), new PaystackProvider($providerConfig), 'paystack');
        $verified = $reconciler->reconcilePaystack($paystackReference, $invoiceId);
        $result = (string)($verified['status'] ?? 'pending');
        $invoiceId = (string)($verified['invoice_id'] ?? $invoiceId);
        $trackingId = $paystackReference;
    } elseif ($trackingId !== '') {
        $config = rgts_payment_config();
        $providerConfig = is_array($config['providers']['pesapal'] ?? null) ? $config['providers']['pesapal'] : [];
        $reconciler = new PaymentReconciler(new OperationsStore(), new PesapalProvider($providerConfig), 'pesapal');
        $verified = $reconciler->reconcilePesapal($trackingId, $invoiceId);
        $result = (string)($verified['status'] ?? 'pending');
        $invoiceId = (string)($verified['invoice_id'] ?? $invoiceId);
    }
} catch (Throwable $e) {
    error_log('RGTS payment callback reconciliation: ' . $e->getMessage());
    $result = 'pending';
}

$query = http_build_query([
    'payment' => $result,
    'invoice' => $invoiceId,
    'tracking' => $trackingId,
]);
header('Location: /payments.html?' . $query, true, 303);
exit;
