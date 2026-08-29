<?php
declare(strict_types=1);

namespace Resplendent\Esim;

final class OfferRouter
{
    /** @param array<int,ProviderAdapterInterface> $adapters */
    public function __construct(private array $adapters, private float $markupPercent = 25.0)
    {
    }

    /** @return array<int,array{id:string,name:string,code:string}> */
    public function destinations(): array
    {
        $destinations = [];
        foreach ($this->adapters as $adapter) {
            if (!$adapter->available()) continue;
            foreach ($adapter->destinations() as $destination) {
                $id = trim((string)($destination['id'] ?? ''));
                $name = trim((string)($destination['name'] ?? $destination['country'] ?? ''));
                $code = strtoupper(trim((string)($destination['code'] ?? $destination['iso2'] ?? $destination['iso'] ?? '')));
                if ($id === '' || $name === '') continue;
                $key = $code !== '' ? $code : strtolower($name);
                $destinations[$key] = ['id' => $this->publicId($adapter->identifier(), $id), 'name' => $name, 'code' => $code];
            }
        }
        uasort($destinations, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        return array_values($destinations);
    }

    /** @return array<int,array<string,mixed>> */
    public function offers(string $publicDestinationId): array
    {
        $offers = [];
        foreach ($this->adapters as $adapter) {
            if (!$adapter->available()) continue;
            $destinationId = '';
            foreach ($adapter->destinations() as $destination) {
                $candidate = trim((string)($destination['id'] ?? ''));
                if ($candidate !== '' && hash_equals($this->publicId($adapter->identifier(), $candidate), $publicDestinationId)) {
                    $destinationId = $candidate;
                    break;
                }
            }
            if ($destinationId === '') continue;
            foreach ($adapter->offers($destinationId) as $raw) {
                $normalized = $this->normalize($adapter->identifier(), $raw);
                if ($normalized !== null) $offers[] = $normalized;
            }
        }
        usort($offers, static fn(array $a, array $b): int => ($a['retail_price'] <=> $b['retail_price']) ?: ($a['data_gb'] <=> $b['data_gb']));
        return $offers;
    }

    private function publicId(string $provider, string $providerId): string
    {
        return substr(hash('sha256', $provider . ':' . $providerId), 0, 32);
    }

    /** @param array<string,mixed> $raw @return array<string,mixed>|null */
    private function normalize(string $provider, array $raw): ?array
    {
        $providerId = trim((string)($raw['id'] ?? $raw['package_type_id'] ?? ''));
        $name = trim((string)($raw['name'] ?? ''));
        $wholesale = $this->wholesalePrice($raw);
        if ($providerId === '' || $name === '' || $wholesale <= 0) return null;

        $dataQuantity = (float)($raw['data_quantity'] ?? 0);
        $dataUnit = strtoupper(trim((string)($raw['data_unit'] ?? $raw['data_quantity_unit'] ?? 'GB')));
        $dataGb = $dataUnit === 'MB' ? $dataQuantity / 1024 : $dataQuantity;
        $validity = (int)($raw['package_validity'] ?? 0);
        if ($dataGb <= 0 && preg_match('/(\d+(?:\.\d+)?)\s*(GB|MB)/i', $name, $dataMatch)) {
            $parsed = (float)$dataMatch[1];
            $dataGb = strtoupper($dataMatch[2]) === 'MB' ? $parsed / 1024 : $parsed;
        }
        if ($validity <= 0 && preg_match('/(\d+)\s*Days?/i', $name, $validityMatch)) {
            $validity = (int)$validityMatch[1];
        }
        $retail = ceil(($wholesale * (1 + ($this->markupPercent / 100))) * 100) / 100;

        return [
            'id' => hash('sha256', $provider . ':' . $providerId),
            'name' => $name,
            'data_gb' => round($dataGb, 2),
            'validity_days' => $validity,
            'retail_price' => $retail,
            'currency' => 'USD',
            'scope' => strtolower(trim((string)($raw['scope'] ?? 'local'))),
        ];
    }

    /** @param array<string,mixed> $raw */
    private function wholesalePrice(array $raw): float
    {
        foreach (['reseller_price', 'wholesale_price', 'net_price', 'sale_price'] as $field) {
            if (isset($raw[$field]) && is_numeric($raw[$field]) && (float)$raw[$field] > 0) {
                return (float)$raw[$field];
            }
        }

        foreach (['pricing', 'prices', 'cost'] as $container) {
            $value = $raw[$container] ?? null;
            if (is_numeric($value) && (float)$value > 0) return (float)$value;
            if (!is_array($value)) continue;
            foreach (['USD', 'usd', 'reseller_price', 'wholesale_price', 'net_price', 'price', 'amount'] as $field) {
                if (isset($value[$field]) && is_numeric($value[$field]) && (float)$value[$field] > 0) {
                    return (float)$value[$field];
                }
            }
        }

        foreach (['price', 'amount'] as $field) {
            if (isset($raw[$field]) && is_numeric($raw[$field]) && (float)$raw[$field] > 0) {
                return (float)$raw[$field];
            }
        }

        return 0.0;
    }
}
