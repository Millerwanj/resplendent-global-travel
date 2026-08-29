<?php
declare(strict_types=1);

namespace Resplendent\Payments\Providers;

use InvalidArgumentException;
use Resplendent\Payments\PaymentProviderInterface;
use RuntimeException;

require_once dirname(__DIR__) . '/PaymentProviderInterface.php';

/**
 * Pesapal API 3.0 hosted-checkout adapter.
 *
 * This class is safe to ship with empty credentials. It performs no network
 * call until the provider is enabled in the private payment config.
 */
final class PesapalProvider implements PaymentProviderInterface
{
    private string $baseUrl;

    /** @param array<string,mixed> $config */
    public function __construct(private array $config)
    {
        $environment = strtolower(trim((string)($config['environment'] ?? 'sandbox')));
        if (!in_array($environment, ['sandbox', 'live'], true)) {
            throw new InvalidArgumentException('Invalid Pesapal environment.');
        }

        // Prefer environment-specific private credentials while remaining
        // backwards compatible with the original flat configuration.
        $environmentConfig = is_array($config['environments'][$environment] ?? null)
            ? $config['environments'][$environment]
            : [];
        if ($environmentConfig !== []) {
            $this->config = array_replace($config, $environmentConfig);
        }
        $this->config['environment'] = $environment;

        $this->baseUrl = $environment === 'live'
            ? 'https://pay.pesapal.com/v3'
            : 'https://cybqa.pesapal.com/pesapalv3';
    }

    public function identifier(): string
    {
        return 'pesapal';
    }

    public function createCheckout(array $payment, array $urls): array
    {
        $notificationId = trim((string)($this->config['notification_id'] ?? ''));
        if ($notificationId === '') {
            throw new RuntimeException('Pesapal notification_id is not configured. Register the IPN URL first.');
        }

        $reference = trim((string)($payment['merchant_reference'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_.:-]{1,50}$/', $reference)) {
            throw new InvalidArgumentException('Pesapal merchant reference contains unsupported characters.');
        }

        $billing = is_array($payment['billing_address'] ?? null) ? $payment['billing_address'] : [];
        $email = trim((string)($billing['email_address'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid customer email address is required for Pesapal checkout.');
        }

        $payload = [
            'id' => $reference,
            'currency' => strtoupper((string)$payment['currency']),
            'amount' => (float)$payment['amount'],
            'description' => substr(trim((string)$payment['description']), 0, 100),
            'callback_url' => (string)$urls['callback_url'],
            'cancellation_url' => (string)($urls['cancellation_url'] ?? ''),
            'notification_id' => $notificationId,
            'billing_address' => [
                'email_address' => $email,
                'phone_number' => trim((string)($billing['phone_number'] ?? '')),
                'country_code' => strtoupper(trim((string)($billing['country_code'] ?? ''))),
                'first_name' => trim((string)($billing['first_name'] ?? '')),
                'middle_name' => trim((string)($billing['middle_name'] ?? '')),
                'last_name' => trim((string)($billing['last_name'] ?? '')),
                'line_1' => trim((string)($billing['line_1'] ?? '')),
                'line_2' => trim((string)($billing['line_2'] ?? '')),
                'city' => trim((string)($billing['city'] ?? '')),
                'state' => trim((string)($billing['state'] ?? '')),
                'postal_code' => trim((string)($billing['postal_code'] ?? '')),
                'zip_code' => trim((string)($billing['zip_code'] ?? '')),
            ],
        ];
        if ($payload['cancellation_url'] === '') unset($payload['cancellation_url']);

        $response = $this->request('POST', '/api/Transactions/SubmitOrderRequest', $payload, true);
        $redirectUrl = trim((string)($response['redirect_url'] ?? ''));
        $trackingId = trim((string)($response['order_tracking_id'] ?? ''));
        if ($redirectUrl === '' || $trackingId === '') {
            throw new RuntimeException('Pesapal did not return a checkout redirect URL and tracking reference.');
        }

        return [
            'redirect_url' => $redirectUrl,
            'provider_reference' => $trackingId,
            'status' => 'pending',
        ];
    }

    public function verifyTransaction(string $providerReference): array
    {
        $trackingId = trim($providerReference);
        if ($trackingId === '') throw new InvalidArgumentException('Missing Pesapal tracking reference.');

        // PesaPal may briefly return a transient 5xx/HTML response immediately
        // after a mobile-money completion. Retry verification without ever
        // treating a browser redirect as proof of payment.
        $delays = [0, 2, 5];
        $lastError = null;
        $response = null;
        foreach ($delays as $delay) {
            if ($delay > 0) sleep($delay);
            try {
                $response = $this->request(
                    'GET',
                    '/api/Transactions/GetTransactionStatus?orderTrackingId=' . rawurlencode($trackingId),
                    null,
                    true
                );
                break;
            } catch (RuntimeException $e) {
                $lastError = $e;
            }
        }
        if (!is_array($response)) {
            throw new RuntimeException('Pesapal status verification is temporarily unavailable.', 0, $lastError);
        }

        $providerStatus = strtoupper(trim((string)($response['payment_status_description'] ?? '')));
        $status = match ($providerStatus) {
            'COMPLETED' => 'completed',
            'FAILED', 'INVALID' => 'failed',
            'REVERSED' => 'reversed',
            default => 'pending',
        };

        return [
            'provider_reference' => $trackingId,
            'merchant_reference' => trim((string)($response['merchant_reference'] ?? '')),
            'amount' => number_format((float)($response['amount'] ?? 0), 2, '.', ''),
            'currency' => strtoupper(trim((string)($response['currency'] ?? ''))),
            'status' => $status,
            'verified' => $status === 'completed',
            'confirmation_code' => trim((string)($response['confirmation_code'] ?? '')),
            'payment_method' => trim((string)($response['payment_method'] ?? '')),
        ];
    }

    public function parseNotification(array $notification): array
    {
        $providerReference = trim((string)($notification['OrderTrackingId'] ?? $notification['orderTrackingId'] ?? ''));
        $merchantReference = trim((string)($notification['OrderMerchantReference'] ?? $notification['orderMerchantReference'] ?? ''));
        if ($providerReference === '') {
            throw new InvalidArgumentException('Pesapal notification is missing OrderTrackingId.');
        }
        return [
            'provider_reference' => $providerReference,
            'merchant_reference' => $merchantReference,
        ];
    }

    /** @return array<string,mixed> */
    public function registerIpn(string $notificationUrl, string $method = 'POST'): array
    {
        if (!filter_var($notificationUrl, FILTER_VALIDATE_URL) || parse_url($notificationUrl, PHP_URL_SCHEME) !== 'https') {
            throw new InvalidArgumentException('Pesapal IPN URL must be a valid HTTPS URL.');
        }
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST'], true)) $method = 'POST';
        return $this->request('POST', '/api/URLSetup/RegisterIPN', [
            'url' => $notificationUrl,
            'ipn_notification_type' => $method,
        ], true);
    }

    private function token(): string
    {
        $key = trim((string)($this->config['consumer_key'] ?? ''));
        $secret = trim((string)($this->config['consumer_secret'] ?? ''));
        if ($key === '' || $secret === '') {
            throw new RuntimeException('Pesapal credentials are not configured.');
        }
        $response = $this->request('POST', '/api/Auth/RequestToken', [
            'consumer_key' => $key,
            'consumer_secret' => $secret,
        ], false);
        $token = trim((string)($response['token'] ?? ''));
 if ($token === '') {
    $status = trim((string)($response['status'] ?? ''));
    $message = trim((string)($response['message'] ?? ''));
    $errorMessage = is_array($response['error'] ?? null)
        ? trim((string)($response['error']['message'] ?? ''))
        : '';

    throw new RuntimeException(
        'Pesapal authentication did not return a token.'
        . ($status !== '' ? ' Status: ' . $status . '.' : '')
        . ($message !== '' ? ' Message: ' . $message . '.' : '')
        . ($errorMessage !== '' ? ' Error: ' . $errorMessage . '.' : '')
    );
}       return $token;
    }

    /** @param array<string,mixed>|null $payload @return array<string,mixed> */
    private function request(string $method, string $path, ?array $payload, bool $authenticated): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL is required for Pesapal integration.');
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($authenticated) $headers[] = 'Authorization: Bearer ' . $this->token();

        $ch = curl_init($this->baseUrl . $path);
        if ($ch === false) throw new RuntimeException('Unable to initialise Pesapal request.');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
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
        if ($body === false || $error !== '') throw new RuntimeException('Pesapal network error: ' . $error);
        $decoded = json_decode((string)$body, true);
        if (!is_array($decoded)) throw new RuntimeException('Pesapal returned an invalid JSON response.');
        if ($http < 200 || $http >= 300 || !empty($decoded['error']['message'])) {
            $message = trim((string)($decoded['error']['message'] ?? $decoded['message'] ?? 'Pesapal request failed.'));
            throw new RuntimeException($message !== '' ? $message : 'Pesapal request failed.');
        }
        return $decoded;
    }
}
