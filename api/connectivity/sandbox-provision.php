<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/Connectivity/connectivity-bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/Connectivity/Providers/SandboxConnectivityProvider.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rgts_connectivity_json(['ok' => false, 'message' => 'POST required.'], 405);
}

try {
    $input = json_decode((string)file_get_contents('php://input'), true);
    $id = trim((string)($input['order_id'] ?? ''));

    $store = new ConnectivityOrderStore();
    $order = $store->get($id);
    if (!is_array($order)) throw new RuntimeException('Order not found.');
    if (($order['status'] ?? '') !== 'PAID' || empty($order['payment']['verified'])) {
        throw new RuntimeException('Provisioning blocked: payment is not verified.');
    }
    if (($order['provisioning']['status'] ?? '') === 'PROVISIONED') {
        rgts_connectivity_json(['ok' => true, 'status' => 'PROVISIONED', 'message' => 'Already provisioned.']);
    }

    $provider = new SandboxConnectivityProvider();
    $result = $provider->provision($order);
    $order['provisioning'] = [
        'provider' => $provider->providerName(),
        'status' => $result['status'],
        'provider_order_reference' => $result['provider_order_reference'],
        'activation' => $result['activation'],
    ];
    $order['status'] = 'PROVISIONED';
    $store->save($order);

    rgts_connectivity_json([
        'ok' => true,
        'status' => 'PROVISIONED',
        'message' => 'Sandbox provisioning successful. No real eSIM was issued.',
    ]);
} catch (Throwable $e) {
    error_log('RGTS sandbox provision: ' . $e->getMessage());
    rgts_connectivity_json(['ok' => false, 'message' => $e->getMessage()], 400);
}
