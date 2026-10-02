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
        $countryName = '';
        foreach ($this->countryItems() as $country) {
            if (!is_array($country)) continue;
            if ((string)($country['id'] ?? $country['country_id'] ?? '') !== $destinationId) continue;
            $countryName = trim((string)($country['name'] ?? $country['country'] ?? ''));
            break;
        }
        if ($countryName === '') return [];

        $rows = $this->listItems($this->client->pricing());
        $offers = [];
        foreach ($rows as $row) {
            // Support both grouped country records and flat package records.
            if (isset($row['packages']) && is_array($row['packages'])) {
                if ($this->matchesCountry($row, $destinationId, $countryName)) {
                    foreach ($row['packages'] as $package) if (is_array($package)) $offers[] = $package;
                }
                continue;
            }
            $country = $row['country'] ?? null;
            $matches = $this->matchesCountry($row, $destinationId, $countryName);
            if (is_array($country)) $matches = $matches || $this->matchesCountry($country, $destinationId, $countryName, true);
            $coveredCountries = $row['countries'] ?? [];
            foreach (is_array($coveredCountries) ? $coveredCountries : [] as $covered) {
                if (is_array($covered)) $matches = $matches || $this->matchesCountry($covered, $destinationId, $countryName, true);
            }
            if ($matches) $offers[] = $row;
        }
        return $offers;
    }

    private function matchesCountry(array $record, string $id, string $name, bool $countryRecord = false): bool
    {
        $countryId = $record['country_id'] ?? ($countryRecord || isset($record['packages']) ? ($record['id'] ?? '') : '');
        if ((string)$countryId === $id) return true;
        $countryName = $record['country_name'] ?? $record['country'] ?? ($countryRecord || isset($record['packages']) ? ($record['name'] ?? '') : '');
        return is_string($countryName) && strcasecmp(trim($countryName), $name) === 0;
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
        if (is_array($data) && isset($data['data']) && is_array($data['data'])) $data = $data['data'];
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
