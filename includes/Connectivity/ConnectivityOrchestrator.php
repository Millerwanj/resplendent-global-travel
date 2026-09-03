<?php
declare(strict_types=1);

namespace Resplendent\Connectivity;

use InvalidArgumentException;

require_once __DIR__ . '/ConnectivityProviderInterface.php';

/**
 * Supplier-neutral Resplendent connectivity decision engine.
 * Providers keep their own API quirks inside adapters; the storefront only
 * consumes the normalised offer schema returned here.
 */
final class ConnectivityOrchestrator
{
    /** @var array<string,ConnectivityProviderInterface> */
    private array $providers = [];

    /** @param array<string,mixed> $config */
    public function __construct(private array $config = []) {}

    public function register(ConnectivityProviderInterface $provider): void
    {
        $id = strtolower(trim($provider->identifier()));
        if (!preg_match('/^[a-z0-9_-]{2,40}$/', $id)) throw new InvalidArgumentException('Invalid connectivity provider identifier.');
        $this->providers[$id] = $provider;
    }

    /** @param array<string,mixed> $request @return array<int,array<string,mixed>> */
    public function rankedOffers(array $request): array
    {
        $country = strtoupper(trim((string)($request['country'] ?? '')));
        if (!preg_match('/^[A-Z]{2}$/', $country)) throw new InvalidArgumentException('A two-letter destination country code is required.');
        $request['country'] = $country;
        $offers = [];
        foreach ($this->providers as $providerId => $provider) {
            $providerConfig = is_array($this->config['providers'][$providerId] ?? null) ? $this->config['providers'][$providerId] : [];
            if (array_key_exists('enabled', $providerConfig) && !$providerConfig['enabled']) continue;
            foreach ($provider->searchOffers($request) as $offer) {
                if (!is_array($offer)) continue;
                $normal = $this->normalise($providerId, $offer);
                if ($normal !== null) $offers[] = $normal;
            }
        }
        usort($offers, static fn(array $a, array $b): int => ($b['score'] <=> $a['score']) ?: ($a['retail_price'] <=> $b['retail_price']));
        return $offers;
    }

    /** @param array<string,mixed> $offer @return array<string,mixed>|null */
    private function normalise(string $providerId, array $offer): ?array
    {
        $wholesale = (float)($offer['wholesale_price'] ?? 0);
        $currency = strtoupper(trim((string)($offer['currency'] ?? 'USD')));
        $dataGb = (float)($offer['data_gb'] ?? 0);
        $days = (int)($offer['validity_days'] ?? 0);
        if ($wholesale < 0 || $dataGb <= 0 || $days <= 0 || !preg_match('/^[A-Z]{3}$/', $currency)) return null;

        $margin = max(0, (float)($this->config['default_margin_percent'] ?? 25));
        $retail = (float)($offer['retail_price'] ?? ($wholesale * (1 + $margin / 100)));
        $quality = max(0, min(100, (float)($offer['quality_score'] ?? 70)));
        $marginValue = max(0, $retail - $wholesale);
        $marginPct = $retail > 0 ? ($marginValue / $retail) * 100 : 0;
        $score = round(($quality * 0.55) + (min(100, $marginPct * 3) * 0.25) + (min(100, $dataGb / max(1, $wholesale) * 20) * 0.20), 2);

        return [
            'provider' => $providerId,
            'provider_offer_id' => trim((string)($offer['provider_offer_id'] ?? '')),
            'name' => trim((string)($offer['name'] ?? 'Resplendent Connectivity')),
            'countries' => array_values(array_filter((array)($offer['countries'] ?? []), 'is_string')),
            'data_gb' => $dataGb,
            'validity_days' => $days,
            'voice' => !empty($offer['voice']),
            'sms' => !empty($offer['sms']),
            'network_type' => trim((string)($offer['network_type'] ?? '')),
            'wholesale_price' => round($wholesale, 2),
            'retail_price' => round($retail, 2),
            'currency' => $currency,
            'gross_margin' => round($marginValue, 2),
            'score' => $score,
        ];
    }
}
