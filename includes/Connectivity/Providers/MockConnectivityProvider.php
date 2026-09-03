<?php
declare(strict_types=1);

namespace Resplendent\Connectivity\Providers;

use Resplendent\Connectivity\ConnectivityProviderInterface;
use RuntimeException;

require_once dirname(__DIR__) . '/ConnectivityProviderInterface.php';

/** Development-only adapter proving the provider contract before live APIs arrive. */
final class MockConnectivityProvider implements ConnectivityProviderInterface
{
    public function __construct(private string $id = 'mock') {}
    public function identifier(): string { return $this->id; }

    public function searchOffers(array $request): array
    {
        $country = strtoupper((string)$request['country']);
        return [[
            'provider_offer_id' => $this->id . '-' . $country . '-10GB-30D',
            'name' => '10 GB / 30 days',
            'countries' => [$country],
            'data_gb' => 10,
            'validity_days' => 30,
            'voice' => false,
            'sms' => false,
            'network_type' => '4G/5G where available',
            'wholesale_price' => $this->id === 'mock-b' ? 11.50 : 12.00,
            'currency' => 'USD',
            'quality_score' => $this->id === 'mock-b' ? 88 : 82,
        ]];
    }

    public function provision(string $providerOfferId, array $customer, string $merchantReference): array
    {
        throw new RuntimeException('Mock provider cannot provision live connectivity.');
    }

    public function status(string $providerOrderId): array
    {
        return ['status' => 'unavailable'];
    }
}
