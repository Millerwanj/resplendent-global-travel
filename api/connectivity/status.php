<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/Connectivity/connectivity-bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/Connectivity/ConnectivityFulfillmentService.php';

try {
    $id = trim((string)($_GET['order_id'] ?? ''));
    $token = trim((string)($_GET['token'] ?? ''));
    $store = new ConnectivityOrderStore();
    $order = $store->get($id);
    if (!is_array($order)) throw new RuntimeException('Order not found.');
    $expectedToken = (string)($order['result_token'] ?? '');
    if ($expectedToken === '' || $token === '' || !hash_equals($expectedToken, $token)) {
        throw new RuntimeException('Order access denied.');
    }

    $service = new ConnectivityFulfillmentService($store);
    if (in_array(($order['provisioning']['status'] ?? ''), ['AWAITING_SUPPLIER', 'PROVISIONING_REVIEW'], true)
        && !empty($order['provisioning']['provider_order_reference'])) {
        $order = $service->reconcile($id);
    }
    if (($order['status'] ?? '') === 'PROVISIONED' && empty($order['delivery']['email_sent_at'])) {
        $order = $service->retryDelivery($id);
    }

    $activation = [];
    if (($order['status'] ?? '') === 'PROVISIONED') {
        $raw = is_array($order['provisioning']['activation'] ?? null) ? $order['provisioning']['activation'] : [];
        foreach (['qr_code','qr_code_url','iccid','smdp_address','activation_code','manual_code'] as $key) {
            if (isset($raw[$key]) && is_scalar($raw[$key])) $activation[$key] = (string)$raw[$key];
        }
    }

    rgts_connectivity_json([
        'ok' => true,
        'order' => [
            'id' => $order['id'], 'status' => $order['status'], 'created_at' => $order['created_at'],
            'plan' => [
                'name' => $order['payload']['plan']['name'] ?? '',
                'destination' => $order['payload']['plan']['destination'] ?? '',
                'data' => $order['payload']['plan']['data'] ?? '',
                'validity' => $order['payload']['plan']['validity'] ?? '',
            ],
            'amount' => $order['payload']['amount'] ?? '', 'currency' => $order['payload']['currency'] ?? '',
            'payment_verified' => !empty($order['payment']['verified']) && !empty($order['payment']['matches_order']),
            'provisioning_status' => $order['provisioning']['status'] ?? 'NOT_STARTED',
            'activation' => $activation,
            'delivery_email_sent' => !empty($order['delivery']['email_sent_at']),
            'delivery_status' => $order['delivery']['status'] ?? 'PENDING',
        ],
    ]);
} catch (Throwable $e) {
    rgts_connectivity_json(['ok' => false, 'message' => 'Order not found or access link is invalid.'], 404);
}
