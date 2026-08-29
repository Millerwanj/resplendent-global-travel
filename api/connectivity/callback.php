<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/Connectivity/ConnectivityPaymentProcessor.php';

$orderId = trim((string)($_GET['order_id'] ?? ''));
$trackingId = trim((string)($_GET['OrderTrackingId'] ?? $_GET['orderTrackingId'] ?? ''));

try {
    $store = new ConnectivityOrderStore();
    $order = $orderId !== '' ? $store->get($orderId) : null;
    if (!is_array($order) && $trackingId !== '') $order = $store->findByProviderReference($trackingId);
    if (!is_array($order)) throw new RuntimeException('Connectivity order was not found.');

    $trackingId = $trackingId !== '' ? $trackingId : trim((string)($order['payment']['provider_reference'] ?? ''));
    $order = rgts_connectivity_verify_payment($order, $trackingId);
    $store->save($order);

    header('Location: /connectivity-result.html?order_id=' . rawurlencode((string)$order['id']), true, 302);
    exit;
} catch (Throwable $e) {
    error_log('RGTS connectivity callback: ' . $e->getMessage());
    header('Location: /connectivity-result.html?order_id=' . rawurlencode($orderId) . '&review=1', true, 302);
    exit;
}
