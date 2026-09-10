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
                $providerStatus = strtoupper(trim((string)($result['status'] ?? 'PENDING')));
                $completed = $providerStatus === 'PROVISIONED';
                $failed = $providerStatus === 'FAILED';

                $order['provisioning'] = array_merge($order['provisioning'], [
                    'provider' => $providerName,
                    'status' => $failed ? 'FAILED' : ($completed ? 'PROVISIONED' : 'AWAITING_SUPPLIER'),
                    'provider_order_reference' => $reference,
                    'activation' => is_array($result['activation'] ?? null) ? $result['activation'] : [],
                    'test_purchase' => !empty($result['test_purchase']),
                    'provider_status' => $providerStatus,
                    'provisioned_at' => $completed ? gmdate('c') : '',
                    'last_status_check_at' => gmdate('c'),
                    'last_error' => '',
                ]);
                $order['status'] = $failed ? 'FULFILMENT_FAILED' : ($completed ? 'PROVISIONED' : 'AWAITING_SUPPLIER');
                if ($failed) {
                    $order['provisioning']['failed_at'] = gmdate('c');
                    $order['provisioning']['last_error'] = 'The supplier reported that provisioning failed. Manual payment review is required.';
                }
                if ($completed) rgts_connectivity_send_delivery_email($order);
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

    /** @return array<string,mixed> */
    public function reconcile(string $orderId, bool $force = false): array
    {
        return $this->store->withOrderLock($orderId, function () use ($orderId, $force): array {
            $order = $this->store->get($orderId);
            if (!is_array($order)) throw new RuntimeException('Connectivity order was not found.');

            $status = (string)($order['provisioning']['status'] ?? '');
            $reference = trim((string)($order['provisioning']['provider_order_reference'] ?? ''));
            if (!in_array($status, ['AWAITING_SUPPLIER', 'PROVISIONING_REVIEW'], true) || $reference === '') return $order;

            $lastCheck = strtotime((string)($order['provisioning']['last_status_check_at'] ?? '')) ?: 0;
            if (!$force && $lastCheck > time() - 30) return $order;

            $providerName = strtolower(trim((string)($order['provisioning']['provider'] ?? '')));
            $order['provisioning']['last_status_check_at'] = gmdate('c');
            $order['provisioning']['status_checks'] = (int)($order['provisioning']['status_checks'] ?? 0) + 1;
            try {
                $result = rgts_connectivity_provider($providerName)->getStatus($reference);
                $providerStatus = strtoupper(trim((string)($result['status'] ?? 'PENDING')));
                $order['provisioning']['provider_status'] = $providerStatus;
                $activation = is_array($result['activation'] ?? null) ? $result['activation'] : [];
                if ($activation !== []) {
                    $order['provisioning']['activation'] = array_merge(
                        is_array($order['provisioning']['activation'] ?? null) ? $order['provisioning']['activation'] : [],
                        $activation
                    );
                }
                if ($providerStatus === 'PROVISIONED') {
                    $order['status'] = 'PROVISIONED';
                    $order['provisioning']['status'] = 'PROVISIONED';
                    $order['provisioning']['provisioned_at'] = gmdate('c');
                    $order['provisioning']['last_error'] = '';
                    rgts_connectivity_send_delivery_email($order);
                } elseif ($providerStatus === 'FAILED') {
                    $order['status'] = 'FULFILMENT_FAILED';
                    $order['provisioning']['status'] = 'FAILED';
                    $order['provisioning']['failed_at'] = gmdate('c');
                    $order['provisioning']['last_error'] = 'The supplier reported that provisioning failed. Manual payment review is required.';
                } else {
                    $order['status'] = 'AWAITING_SUPPLIER';
                    $order['provisioning']['status'] = 'AWAITING_SUPPLIER';
                }
            } catch (Throwable $e) {
                $order['status'] = 'FULFILMENT_REVIEW';
                $order['provisioning']['status'] = 'PROVISIONING_REVIEW';
                $order['provisioning']['last_error'] = substr(strip_tags($e->getMessage()), 0, 240);
            }
            return $this->store->save($order);
        });
    }

    /** @return array<string,mixed> */
    public function retryDelivery(string $orderId, bool $force = false): array
    {
        return $this->store->withOrderLock($orderId, function () use ($orderId, $force): array {
            $order = $this->store->get($orderId);
            if (!is_array($order)) throw new RuntimeException('Connectivity order was not found.');
            if (($order['status'] ?? '') !== 'PROVISIONED') return $order;
            if (!empty($order['delivery']['email_sent_at'])) return $order;
            $lastAttempt = strtotime((string)($order['delivery']['last_attempt_at'] ?? '')) ?: 0;
            if (!$force && $lastAttempt > time() - 60) return $order;
            rgts_connectivity_send_delivery_email($order);
            return $this->store->save($order);
        });
    }
}
