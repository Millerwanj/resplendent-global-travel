<?php
declare(strict_types=1);

require_once __DIR__ . '/connectivity-bootstrap.php';

final class ConnectivityFulfillmentService
{
    public function __construct(private ConnectivityOrderStore $store) {}

    /** @return array<string,mixed> */
    public function fulfill(string $orderId): array
    {
        return $this->store->withOrderLock($orderId, function () use ($orderId): array {
            $order = $this->store->get($orderId);
            if (!is_array($order)) throw new RuntimeException('Connectivity order was not found.');

            if (($order['status'] ?? '') === 'PROVISIONED' && ($order['provisioning']['status'] ?? '') === 'PROVISIONED') {
                return $order;
            }
            if (($order['provisioning']['status'] ?? '') === 'PROVISIONING_REVIEW') {
                $lastError = trim((string)($order['provisioning']['last_error'] ?? ''));

                // A local pre-purchase configuration block is safe to retry because
                // no supplier purchase request could have been sent. All other
                // review states remain locked to prevent duplicate supplier charges.
                $safePrePurchaseRetry = str_contains(
                    strtolower($lastError),
                    'production purchasing is disabled in private configuration'
                );

                if (!$safePrePurchaseRetry) {
                    return $order; // Never blindly repurchase after an uncertain supplier response.
                }

                $order['provisioning']['status'] = 'NOT_STARTED';
                $order['provisioning']['last_error'] = '';
                $order['provisioning']['safe_retry_at'] = gmdate('c');
                $order['status'] = 'PAID';
                $this->store->save($order);
            }
            if (($order['status'] ?? '') !== 'PAID' || empty($order['payment']['verified']) || empty($order['payment']['matches_order'])) {
                return $order;
            }

            $providerName = strtolower(trim((string)($order['payload']['plan']['provider'] ?? '')));
            if ($providerName === '') throw new RuntimeException('Connectivity provider is missing from the verified order.');

            $provisionInput = $order; // Preserve the verified PAID snapshot for the provider contract.

            $order['provisioning']['provider'] = $providerName;
            $order['provisioning']['status'] = 'PROVISIONING';
            $order['provisioning']['started_at'] = gmdate('c');
            $order['provisioning']['attempts'] = (int)($order['provisioning']['attempts'] ?? 0) + 1;
            $order['status'] = 'PROVISIONING';
            $this->store->save($order);

            try {
                $provider = rgts_connectivity_provider($providerName);
                $result = $provider->provision($provisionInput);
                $reference = trim((string)($result['provider_order_reference'] ?? ''));
                if ($reference === '') throw new RuntimeException('Supplier provisioning returned no order reference.');

                $order['provisioning'] = array_merge($order['provisioning'], [
                    'provider' => $providerName,
                    'status' => 'PROVISIONED',
                    'provider_order_reference' => $reference,
                    'activation' => is_array($result['activation'] ?? null) ? $result['activation'] : [],
                    'test_purchase' => !empty($result['test_purchase']),
                    'provisioned_at' => gmdate('c'),
                    'last_error' => '',
                ]);
                $order['status'] = 'PROVISIONED';
                rgts_connectivity_send_delivery_email($order);
                return $this->store->save($order);
            } catch (Throwable $e) {
                $order['status'] = 'FULFILMENT_REVIEW';
                $order['provisioning']['status'] = 'PROVISIONING_REVIEW';
                $order['provisioning']['last_error'] = substr(strip_tags($e->getMessage()), 0, 240);
                $order['provisioning']['review_at'] = gmdate('c');
                $this->store->save($order);
                throw $e;
            }
        });
    }
}
