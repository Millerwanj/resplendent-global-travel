<?php
declare(strict_types=1);

namespace Resplendent\EsimCard;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class EsimCardClient
{
    private string $baseUrl;
    private string $email;
    private string $password;
    private int $timeout;
    private ?string $token = null;
    private string $pricingCacheFile;
    private string $countriesCacheFile;
    private ?array $freshPackages = null;

    /** @param array<string,mixed> $config */
    public function __construct(array $config)
    {
        $environment = strtolower(trim((string)($config['environment'] ?? 'sandbox')));
        if (!in_array($environment, ['sandbox', 'production'], true)) {
            throw new InvalidArgumentException('Invalid eSIMCard environment.');
        }

        $defaultUrl = $environment === 'production'
            ? 'https://portal.esimcard.com/api'
            : 'https://sandbox.esimcard.com/api';
        $this->baseUrl = rtrim((string)($config['base_url'] ?? $defaultUrl), '/');
        $this->email = trim((string)($config['email'] ?? ''));
        $this->password = (string)($config['password'] ?? '');
        $this->timeout = max(5, min(45, (int)($config['timeout'] ?? 20)));
        $apiToken = trim((string)($config['api_token'] ?? ''));
        $this->token = $apiToken !== '' ? $apiToken : null;
        $this->pricingCacheFile = dirname(__DIR__, 3) . '/rgts-esimcard-packages-v1267-' . $environment . '.json';
        $this->countriesCacheFile = dirname(__DIR__, 3) . '/rgts-esimcard-countries-' . $environment . '.json';

        if ($this->token === null && (!filter_var($this->email, FILTER_VALIDATE_EMAIL) || $this->password === '')) {
            throw new RuntimeException('eSIMCard credentials are not configured.');
        }
        if (!str_starts_with($this->baseUrl, 'https://')) {
            throw new InvalidArgumentException('The eSIMCard API URL must use HTTPS.');
        }
    }

    /** @return array<string,mixed> */
    public function balance(): array
    {
        return $this->request('GET', '/developer/reseller/balance');
    }

    /** @return array<string,mixed> */
    public function packages(string $packageType = 'DATA-ONLY'): array
    {
        $this->packageType($packageType);
        $items = [];
        $seen = [];
        for ($page = 1; $page <= 200; $page++) {
            $response = $this->request('GET', '/developer/reseller/packages', [], [
                'page' => $page, 'per_page' => 100,
            ], true);
            $data = $response['data'] ?? $response;
            if (!is_array($data)) throw new RuntimeException('Invalid eSIMCard package catalogue.');
            $rows = $data['data'] ?? $data['packages'] ?? $data['countries'] ?? $data;
            if (!is_array($rows) || !array_is_list($rows)) {
                throw new RuntimeException('Unrecognised eSIMCard package catalogue.');
            }
            $fingerprint = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
            if ($rows !== [] && isset($seen[$fingerprint])) {
                throw new RuntimeException('eSIMCard pagination repeated a page.');
            }
            $seen[$fingerprint] = true;
            foreach ($rows as $row) {
                if (!is_array($row)) throw new RuntimeException('Invalid eSIMCard package record.');
                $items[] = $row;
            }
            $meta = $response['meta'] ?? $data['meta'] ?? $data;
            $currentPage = $meta['current_page'] ?? $response['current_page'] ?? null;
            if ($currentPage !== null && (!is_numeric($currentPage) || (int)$currentPage !== $page)) {
                throw new RuntimeException('eSIMCard did not return the requested page.');
            }
            $lastPage = $meta['last_page'] ?? $response['last_page'] ?? null;
            $links = $response['links'] ?? $data['links'] ?? [];
            $hasNext = array_key_exists('next_page_url', $data) || array_key_exists('next_page_url', $response)
                || (is_array($links) && array_key_exists('next', $links));
            $next = $data['next_page_url'] ?? $response['next_page_url'] ?? $links['next'] ?? null;
            if ($lastPage !== null) {
                if (!is_numeric($lastPage) || (int)$lastPage < $page || (int)$lastPage > 200) {
                    throw new RuntimeException('Invalid eSIMCard pagination metadata.');
                }
                if ($page >= (int)$lastPage) return ['data' => $items];
            } elseif ($hasNext) {
                if ($next === null || $next === '') return ['data' => $items];
                // Never follow a supplier URL with the bearer token. Request only
                // the fixed trusted endpoint using the next numeric page.
            } elseif (count($rows) < 100) {
                return ['data' => $items];
            }
        }
        throw new RuntimeException('eSIMCard package pagination limit exceeded.');
    }

    /** @return array<string,mixed> */
    public function pricing(): array
    {
        // Catalogue browsing must never wait on the supplier when we already
        // have a safe snapshot. Checkout verifies the selected package before any
        // order is created, so a seven-day browsing snapshot is both fast and
        // commercially safe.
        $cached = $this->freshPackages ?? $this->readPricingCache(604800);
        if ($cached !== null) return $cached;

        try {
            return $this->refreshPricing();
        } catch (Throwable $error) {
            $stale = $this->readPricingCache(2592000);
            if ($stale !== null) return $stale;
            throw $error;
        }
    }

    /** @return array<string,mixed> */
    public function refreshPricing(): array
    {
        $response = $this->packages();
        $this->writePricingCache($response);
        $this->freshPackages = $response;
        return $response;
    }

    /** Verify one package live without replacing the complete browsing cache. */
    public function refreshSelectedPackage(string $publicPlanId): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $publicPlanId)) {
            throw new InvalidArgumentException('Invalid plan reference.');
        }
        $cached = $this->readPricingCache(2592000);
        if ($cached === null) throw new RuntimeException('Please reload the plan catalogue before continuing.');
        $data = $cached['data'] ?? $cached;
        $rows = $data['data'] ?? $data['packages'] ?? $data['countries'] ?? $data;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new RuntimeException('Invalid saved package catalogue.');
        }
        $providerId = null;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $id = (string)($row['id'] ?? $row['package_type_id'] ?? '');
            if ($id !== '' && hash_equals(hash('sha256', 'esimcard:' . $id), $publicPlanId)) {
                $providerId = $id;
                break;
            }
        }
        if ($providerId === null) throw new RuntimeException('That package is no longer available. Please choose a current plan.');
        $response = $this->package($providerId);
        $package = $response['data'] ?? $response;
        if (!is_array($package) || array_is_list($package)
            || (string)($package['id'] ?? '') !== $providerId) {
            throw new RuntimeException('The supplier could not verify the selected package.');
        }
        // Only this request uses the live record. Never persist a one-record
        // response over the complete supplier catalogue.
        $this->freshPackages = ['data' => [$package]];
    }

    /** @return array<string,mixed> */
    public function countries(): array
    {
        // Countries change rarely. Caching removes a full supplier login + API
        // round trip from every plan request while checkout still revalidates
        // the selected package and price independently.
        $cached = $this->readJsonCache($this->countriesCacheFile, 604800);
        if ($cached !== null) return $cached;
        try {
            $response = $this->request('GET', '/developer/reseller/packages/country');
            $this->writeJsonCache($this->countriesCacheFile, $response, 'Countries');
            return $response;
        } catch (Throwable $error) {
            $stale = $this->readJsonCache($this->countriesCacheFile, 2592000);
            if ($stale !== null) return $stale;
            throw $error;
        }
    }

    /** @return array<string,mixed> */
    public function packagesByCountry(string $countryId, string $packageType = 'DATA-ONLY'): array
    {
        $countryId = trim($countryId);
        if (!preg_match('/^\d{1,10}$/', $countryId)) throw new InvalidArgumentException('Invalid country identifier.');
        $path = '/developer/reseller/packages/country/' . rawurlencode($countryId);
        return $this->request('GET', $path, ['package_type' => $this->packageType($packageType)]);
    }

    /** @return array<string,mixed> */
    public function package(string $packageTypeId): array
    {
        return $this->request('GET', '/developer/reseller/package/detail/' . rawurlencode($this->identifier($packageTypeId)));
    }

    /** @return array<string,mixed> */
    public function order(string $orderId): array
    {
        return $this->request('GET', '/developer/reseller/order/' . rawurlencode($this->identifier($orderId)));
    }

    /** @return array<string,mixed> */
    public function purchaseDataPackage(string $packageTypeId, string $iccid = '', bool $test = true): array
    {
        $form = ['package_type_id' => $this->identifier($packageTypeId), 'iccid' => trim($iccid)];
        return $this->request('POST', '/developer/reseller/package/purchase', ['test' => $test ? 'true' : 'false'], $form);
    }

    /** @return array<string,mixed> */
    private function login(): array
    {
        return $this->requestRaw('POST', '/developer/reseller/login', [], [
            'email' => $this->email,
            'password' => $this->password,
        ], false, true);
    }

    private function authenticate(): void
    {
        if ($this->token !== null) return;
        $response = $this->login();
        $token = $response['token'] ?? $response['access_token'] ?? $response['data']['token'] ?? null;
        if (!is_string($token) || trim($token) === '') {
            throw new RuntimeException('eSIMCard authentication succeeded without returning an access token.');
        }
        $this->token = trim($token);
    }

    /** @param array<string,string> $query @param array<string,mixed>|null $form @return array<string,mixed> */
    private function request(string $method, string $path, array $query = [], ?array $form = null, bool $jsonBody = false): array
    {
        $this->authenticate();
        return $this->requestRaw($method, $path, $query, $form, true, $jsonBody);
    }

    /** @param array<string,string> $query @param array<string,mixed>|null $body @return array<string,mixed> */
    private function requestRaw(string $method, string $path, array $query, ?array $body, bool $authenticated, bool $jsonBody): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL is required for the eSIMCard integration.');
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        if ($query !== []) $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        $headers = ['Accept: application/json'];
        if ($authenticated) $headers[] = 'Authorization: Bearer ' . $this->token;
        $payload = null;
        if ($body !== null) {
            if ($jsonBody) {
                $payload = json_encode($body, JSON_THROW_ON_ERROR);
                $headers[] = 'Content-Type: application/json';
            } else {
                $payload = $body;
            }
        }

        $curl = curl_init($url);
        if ($curl === false) throw new RuntimeException('Unable to initialise the eSIMCard request.');
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'Resplendent-eSIM-Sandbox/1.0',
        ]);
        if ($payload !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        $raw = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $errorNumber = curl_errno($curl);
        $info = curl_getinfo($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($raw === false || $error !== '') {
            // Log only transport metadata, never credentials, headers or response bodies.
            $diagnostic = [
                'method' => strtoupper($method),
                'host' => (string)parse_url($this->baseUrl, PHP_URL_HOST),
                'endpoint' => $path === '/developer/reseller/login' ? 'login' : ($path === '/developer/reseller/packages' ? 'packages' : 'supplier-request'),
                'curl_errno' => $errorNumber,
                'curl_error' => curl_strerror($errorNumber),
                'http_status' => $status,
                'dns_seconds' => $info['namelookup_time'] ?? null,
                'connect_seconds' => $info['connect_time'] ?? null,
                'tls_seconds' => $info['appconnect_time'] ?? null,
                'first_byte_seconds' => $info['starttransfer_time'] ?? null,
                'total_seconds' => $info['total_time'] ?? null,
                'ssl_verify_result' => $info['ssl_verify_result'] ?? null,
            ];
            error_log('[RGTS eSIM transport] ' . json_encode($diagnostic, JSON_UNESCAPED_SLASHES));
            throw new RuntimeException('eSIMCard network request failed.');
        }
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) throw new RuntimeException('eSIMCard returned an invalid response.');
        if ($status < 200 || $status >= 300) {
            $message = (string)($decoded['message'] ?? $decoded['error'] ?? 'API request failed');
            $message = strip_tags($message);
            $message = function_exists('mb_substr') ? mb_substr($message, 0, 240) : substr($message, 0, 240);
            throw new RuntimeException('eSIMCard: ' . $message);
        }

        // Some eSIMCard endpoints may return HTTP 2xx with an application-level
        // failure in the JSON body. Never treat those responses as a successful
        // purchase/provisioning result.
        $success = $decoded['success'] ?? null;
        $rawStatus = strtolower(trim((string)($decoded['status'] ?? '')));
        $applicationFailed = $success === false
            || in_array($rawStatus, ['error', 'failed', 'failure', 'invalid', 'declined'], true);

        if ($applicationFailed) {
            $message = (string)($decoded['message'] ?? $decoded['error'] ?? $decoded['detail'] ?? 'API request was not completed');
            $message = strip_tags($message);
            $message = function_exists('mb_substr') ? mb_substr($message, 0, 240) : substr($message, 0, 240);
            throw new RuntimeException('eSIMCard: ' . $message);
        }

        return $decoded;
    }

    private function identifier(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/^[A-Za-z0-9_-]{3,100}$/', $value)) throw new InvalidArgumentException('Invalid API identifier.');
        return $value;
    }

    private function packageType(string $value): string
    {
        $value = strtoupper(trim($value));
        if (!in_array($value, ['DATA-ONLY', 'DATA-VOICE-SMS'], true)) throw new InvalidArgumentException('Invalid package type.');
        return $value;
    }

    /** @return array<string,mixed>|null */
    private function readPricingCache(int $maximumAge): ?array
    {
        if (!is_file($this->pricingCacheFile)) return null;
        $modified = filemtime($this->pricingCacheFile);
        if ($modified === false || $modified < time() - $maximumAge) return null;
        $raw = file_get_contents($this->pricingCacheFile);
        if (!is_string($raw) || $raw === '') return null;
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string,mixed> $response */
    private function writePricingCache(array $response): void
    {
        try {
            $encoded = json_encode($response, JSON_THROW_ON_ERROR);
            $temporary = $this->pricingCacheFile . '.tmp-' . bin2hex(random_bytes(4));
            if (file_put_contents($temporary, $encoded, LOCK_EX) === false) return;
            chmod($temporary, 0600);
            if (!rename($temporary, $this->pricingCacheFile)) unlink($temporary);
        } catch (Throwable $error) {
            error_log('[RGTS eSIM catalogue] Pricing cache could not be refreshed.');
        }
    }

    /** @return array<string,mixed>|null */
    private function readJsonCache(string $path, int $maximumAge): ?array
    {
        if (!is_file($path)) return null;
        $modified = filemtime($path);
        if ($modified === false || $modified < time() - $maximumAge) return null;
        $raw = file_get_contents($path);
        if (!is_string($raw) || $raw === '') return null;
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string,mixed> $response */
    private function writeJsonCache(string $path, array $response, string $label): void
    {
        try {
            $encoded = json_encode($response, JSON_THROW_ON_ERROR);
            $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));
            if (file_put_contents($temporary, $encoded, LOCK_EX) === false) return;
            chmod($temporary, 0600);
            if (!rename($temporary, $path)) unlink($temporary);
        } catch (Throwable $error) {
            error_log('[RGTS eSIM catalogue] ' . $label . ' cache could not be refreshed.');
        }
    }
}
