<?php
declare(strict_types=1);

namespace Resplendent\Esim;

use Resplendent\EsimCard\EsimCardClient;

final class EsimCardAdapter implements ProviderAdapterInterface
{
    public function __construct(private EsimCardClient $client)
    {
    }

    public function identifier(): string
    {
        return 'esimcard';
    }

    public function available(): bool
    {
        return true;
    }

    public function destinations(): array
    {
        return $this->items($this->client->countries());
    }

    public function offers(string $destinationId): array
    {
        return $this->items($this->client->packagesByCountry($destinationId, 'DATA-ONLY'));
    }

    /** @param array<string,mixed> $response @return array<int,array<string,mixed>> */
    private function items(array $response): array
    {
        $items = $response['data'] ?? [];
        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }
}
