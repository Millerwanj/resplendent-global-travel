<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/ConnectivityProviderInterface.php';

final class SandboxConnectivityProvider implements ConnectivityProviderInterface
{
    public function providerName(): string { return 'sandbox'; }

    public function provision(array $order): array
    {
        if (($order['status'] ?? '') !== 'PAID') {
            throw new RuntimeException('Provisioning is blocked until payment is verified.');
        }

        return [
            'provider' => 'sandbox',
            'status' => 'PROVISIONED',
            'provider_order_reference' => 'SIM-' . strtoupper(bin2hex(random_bytes(4))),
            'activation' => [
                'delivery_mode' => 'TEST_ONLY',
                'message' => 'Sandbox provisioning successful. No real eSIM was issued.',
            ],
        ];
    }

    public function getStatus(string $providerOrderReference): array
    {
        return [
            'provider' => 'sandbox',
            'provider_order_reference' => $providerOrderReference,
            'status' => 'PROVISIONED',
        ];
    }
}
