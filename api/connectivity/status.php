<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/Connectivity/connectivity-bootstrap.php';

try {
    $id = trim((string)($_GET['order_id'] ?? ''));
    $store = new ConnectivityOrderStore();
    $order = $store->get($id);
    if (!is_array($order)) throw new RuntimeException('Order not found.');

    // Never expose supplier activation credentials from this public endpoint.
    rgts_connectivity_json([
        'ok' => true,
        'order' => [
            'id' => $order['id'],
            'status' => $order['status'],
            'created_at' => $order['created_at'],
            'plan' => [
                'name' => $order['payload']['plan']['name'] ?? '',
                'destination' => $order['payload']['plan']['destination'] ?? '',
                'data' => $order['payload']['plan']['data'] ?? '',
                'validity' => $order['payload']['plan']['validity'] ?? '',
            ],
            'amount' => $order['payload']['amount'] ?? '',
            'currency' => $order['payload']['currency'] ?? '',
            'payment_verified' => !empty($order['payment']['verified']),
            'provisioning_status' => $order['provisioning']['status'] ?? 'NOT_STARTED',
        ],
    ]);
} catch (Throwable $e) {
    rgts_connectivity_json(['ok' => false, 'message' => 'Order not found.'], 404);
}
