<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/Connectivity/ConnectivityFulfillmentService.php';

try {
    $config = rgts_esimcard_config();
    $configuredToken = trim((string)(getenv('RGTS_CONNECTIVITY_RECONCILE_TOKEN') ?: ($config['reconcile_token'] ?? '')));
    $providedToken = trim((string)($_SERVER['HTTP_X_RGTS_RECONCILE_TOKEN'] ?? ''));
    if ($configuredToken === '' || $providedToken === '' || !hash_equals($configuredToken, $providedToken)) {
        rgts_connectivity_json(['ok' => false, 'message' => 'Access denied.'], 403);
    }

    $store = new ConnectivityOrderStore();
    $service = new ConnectivityFulfillmentService($store);
    $processed = 0;
    $updated = 0;
    foreach ($store->listRecent(100) as $order) {
        $id = (string)($order['id'] ?? '');
        if ($id === '') continue;
        $before = (string)($order['updated_at'] ?? '');
        if (in_array(($order['provisioning']['status'] ?? ''), ['AWAITING_SUPPLIER', 'PROVISIONING_REVIEW'], true)
            && !empty($order['provisioning']['provider_order_reference'])) {
            $order = $service->reconcile($id);
            $processed++;
        }
        if (($order['status'] ?? '') === 'PROVISIONED' && empty($order['delivery']['email_sent_at'])) {
            $order = $service->retryDelivery($id);
            $processed++;
        }
        if ((string)($order['updated_at'] ?? '') !== $before) $updated++;
    }
    rgts_connectivity_json(['ok' => true, 'processed' => $processed, 'updated' => $updated]);
} catch (Throwable $e) {
    error_log('RGTS connectivity reconciliation: ' . $e->getMessage());
    rgts_connectivity_json(['ok' => false, 'message' => 'Reconciliation could not be completed.'], 500);
}
