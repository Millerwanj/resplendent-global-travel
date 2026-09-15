<?php
declare(strict_types=1);

namespace Resplendent\Payments\Providers;

use InvalidArgumentException;
use Resplendent\Payments\PaymentProviderInterface;
use RuntimeException;

require_once dirname(__DIR__) . '/PaymentProviderInterface.php';

/** Paystack hosted-checkout adapter. Secret keys remain in private config. */
final class PaystackProvider implements PaymentProviderInterface
{
    private string $baseUrl = 'https://api.paystack.co';

    /** @param array<string,mixed> $config */
    public function __construct(private array $config)
    {
        $environment = strtolower(trim((string)($config['environment'] ?? 'test')));
        if (!in_array($environment, ['test', 'live'], true)) {
            throw new InvalidArgumentException('Invalid Paystack environment.');
        }
        $environmentConfig = is_array($config['environments'][$environment] ?? null)
            ? $config['environments'][$environment]
            : [];
        if ($environmentConfig !== []) $this->config = array_replace($config, $environmentConfig);
        $this->config['environment'] = $environment;
    }

    public function identifier(): string { return 'paystack'; }

    public function createCheckout(array $payment, array $urls): array
    {
        $reference = trim((string)($payment['merchant_reference'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9.=_-]{1,100}$/', $reference)) {
            throw new InvalidArgumentException('Paystack reference contains unsupported characters.');
        }
        $billing = is_array($payment['billing_address'] ?? null) ? $payment['billing_address'] : [];
        $email = trim((string)($billing['email_address'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid customer email address is required for Paystack checkout.');
        }
        $amount = (int)round(((float)($payment['amount'] ?? 0)) * 100);
        if ($amount < 1) throw new InvalidArgumentException('Paystack amount must be positive.');

        $payload = [
            'email' => $email,
            'amount' => $amount,
            'currency' => strtoupper((string)$payment['currency']),
            'reference' => $reference,
            'callback_url' => (string)$urls['callback_url'],
            'metadata' => [
                'merchant_reference' => $reference,
                'description' => substr(trim((string)($payment['description'] ?? '')), 0, 200),
                'customer_name' => trim((string)(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''))),
                'customer_phone' => trim((string)($billing['phone_number'] ?? '')),
            ],
        ];
        if (!empty($this->config['channels']) && is_array($this->config['channels'])) {
            $payload['channels'] = array_values(array_intersect($this->config['channels'], [
                'card','bank','apple_pay','ussd','qr','mobile_money','bank_transfer','eft',
            ]));
        }
        $response = $this->request('POST', '/transaction/initialize', $payload);
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $redirectUrl = trim((string)($data['authorization_url'] ?? ''));
        $providerReference = trim((string)($data['reference'] ?? $reference));
        if ($redirectUrl === '' || !filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Paystack did not return a valid checkout URL.');
        }
        return ['redirect_url' => $redirectUrl, 'provider_reference' => $providerReference, 'status' => 'pending'];
    }

    public function verifyTransaction(string $providerReference): array
    {
        $reference = trim($providerReference);
        if (!preg_match('/^[A-Za-z0-9.=_-]{1,100}$/', $reference)) {
            throw new InvalidArgumentException('Invalid Paystack reference.');
        }
        $response = $this->request('GET', '/transaction/verify/' . rawurlencode($reference), null);
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $providerStatus = strtolower(trim((string)($data['status'] ?? '')));
        $status = match ($providerStatus) {
            'success' => 'completed',
            'failed', 'abandoned' => 'failed',
            'reversed' => 'reversed',
            default => 'pending',
        };
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $authorization = is_array($data['authorization'] ?? null) ? $data['authorization'] : [];
        return [
            'provider_reference' => trim((string)($data['reference'] ?? $reference)),
            'merchant_reference' => trim((string)($metadata['merchant_reference'] ?? $data['reference'] ?? '')),
            'amount' => number_format(((int)($data['amount'] ?? 0)) / 100, 2, '.', ''),
            'currency' => strtoupper(trim((string)($data['currency'] ?? ''))),
            'status' => $status,
            'verified' => $status === 'completed',
            'confirmation_code' => trim((string)($data['id'] ?? '')),
            'payment_method' => trim((string)($authorization['channel'] ?? $data['channel'] ?? '')),
        ];
    }

    public function parseNotification(array $notification): array
    {
        $data = is_array($notification['data'] ?? null) ? $notification['data'] : [];
        $reference = trim((string)($data['reference'] ?? ''));
        if ($reference === '') throw new InvalidArgumentException('Paystack notification is missing a reference.');
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        return [
            'provider_reference' => $reference,
            'merchant_reference' => trim((string)($metadata['merchant_reference'] ?? $reference)),
        ];
    }

    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = $this->secretKey();
        return $signature !== '' && hash_equals(hash_hmac('sha512', $rawBody, $secret), strtolower($signature));
    }

    private function secretKey(): string
    {
        $secret = trim((string)($this->config['secret_key'] ?? ''));
        $expectedPrefix = ($this->config['environment'] ?? 'test') === 'live' ? 'sk_live_' : 'sk_test_';
        if ($secret === '' || !str_starts_with($secret, $expectedPrefix)) {
            throw new RuntimeException('Paystack secret key is not configured for the selected environment.');
        }
        return $secret;
    }

    /** @param array<string,mixed>|null $payload @return array<string,mixed> */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL is required for Paystack integration.');
        $ch = curl_init($this->baseUrl . $path);
        if ($ch === false) throw new RuntimeException('Unable to initialise Paystack request.');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->secretKey(), 'Accept: application/json', 'Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false || $error !== '') throw new RuntimeException('Paystack network error: ' . $error);
        $decoded = json_decode((string)$body, true);
        if (!is_array($decoded)) throw new RuntimeException('Paystack returned an invalid response.');
        if ($http < 200 || $http >= 300 || empty($decoded['status'])) {
            $message = trim((string)($decoded['message'] ?? 'Paystack request failed.'));
            throw new RuntimeException($message !== '' ? $message : 'Paystack request failed.');
        }
        return $decoded;
    }
}
