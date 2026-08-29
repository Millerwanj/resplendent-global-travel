<?php
declare(strict_types=1);

namespace Resplendent\EsimCard;

use InvalidArgumentException;
use RuntimeException;

final class EsimCardClient
{
    private string $baseUrl;
    private string $email;
    private string $password;
    private int $timeout;
    private ?string $token = null;

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

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL) || $this->password === '') {
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
        return $this->request('GET', '/developer/reseller/packages', ['package_type' => $this->packageType($packageType)]);
    }

    /** @return array<string,mixed> */
    public function pricing(): array
    {
        return $this->request('GET', '/developer/reseller/pricing');
    }

    /** @return array<string,mixed> */
    public function countries(): array
    {
        return $this->request('GET', '/developer/reseller/packages/country');
    }

    /** @return array<string,mixed> */
    public function packagesByCountry(string $countryId, string $packageType = 'DATA-ONLY'): array
    {
        $path = '/developer/reseller/packages/country/' . rawurlencode($this->identifier($countryId)) . '/' . rawurlencode($this->packageType($packageType));
        return $this->request('GET', $path);
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

    /** @param array<string,string> $query @param array<string,string>|null $form @return array<string,mixed> */
    private function request(string $method, string $path, array $query = [], ?array $form = null): array
    {
        $this->authenticate();
        return $this->requestRaw($method, $path, $query, $form, true, false);
    }

    /** @param array<string,string> $query @param array<string,string>|null $body @return array<string,mixed> */
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
        $error = curl_error($curl);
        curl_close($curl);

        if ($raw === false || $error !== '') throw new RuntimeException('eSIMCard network request failed.');
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) throw new RuntimeException('eSIMCard returned an invalid response.');
        if ($status < 200 || $status >= 300) {
            $message = (string)($decoded['message'] ?? $decoded['error'] ?? 'API request failed');
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
}
