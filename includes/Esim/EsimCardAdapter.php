<?php
declare(strict_types=1);

namespace Resplendent\Esim;

use Resplendent\EsimCard\EsimCardClient;

final class EsimCardAdapter implements ProviderAdapterInterface
{
    /** @var array<string,mixed>|null */
    private ?array $countries = null;

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
        $countries = $this->countryItems();
        $destinations = [];
        foreach ($countries as $country) {
            if (!is_array($country)) continue;
            $id = trim((string)($country['id'] ?? $country['country_id'] ?? ''));
            $name = trim((string)($country['name'] ?? $country['country'] ?? ''));
            $code = strtolower(trim((string)($country['code'] ?? $country['iso2'] ?? $country['iso'] ?? '')));
            if ($id === '' || $name === '') continue;
            $destinations[] = ['id' => $id, 'name' => $name, 'code' => $code];
        }
        return $destinations;
    }

    public function offers(string $destinationId): array
    {
        $response = $this->client->packagesByCountry($destinationId, 'DATA-ONLY');
        $packages = $this->listItems($response);
        $offers = [];
        foreach ($packages as $package) {
            if (!is_array($package)) continue;
            $packageId = trim((string)($package['id'] ?? $package['package_type_id'] ?? ''));
            if ($packageId !== '') {
                try {
                    $detail = $this->objectData($this->client->package($packageId));
                    if ($detail !== []) $package = array_replace_recursive($package, $detail);
                } catch (\Throwable $error) {
                    error_log('[RGTS eSIM catalogue] Package detail unavailable for ' . substr($packageId, 0, 12) . '.');
                }
            }
            $offers[] = $package;
        }
        return $offers;
    }

    /** @return array<int,array<string,mixed>> */
    private function countryItems(): array
    {
        if ($this->countries === null) $this->countries = $this->client->countries();
        return $this->listItems($this->countries);
    }

    /** @param array<string,mixed> $response @return array<int,array<string,mixed>> */
    private function listItems(array $response): array
    {
        $data = $response['data'] ?? $response;
        if (is_array($data) && isset($data['countries']) && is_array($data['countries'])) $data = $data['countries'];
        if (is_array($data) && isset($data['packages']) && is_array($data['packages'])) $data = $data['packages'];
        if (!is_array($data)) return [];
        return array_values(array_filter($data, 'is_array'));
    }

    /** @param array<string,mixed> $response @return array<string,mixed> */
    private function objectData(array $response): array
    {
        $data = $response['data'] ?? $response;
        if (!is_array($data)) return [];
        if (array_is_list($data)) {
            $first = $data[0] ?? [];
            return is_array($first) ? $first : [];
        }
        return $data;
    }
}
