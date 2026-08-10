<?php
declare(strict_types=1);

namespace Resplendent\Payments;

use InvalidArgumentException;
use RuntimeException;

require_once __DIR__ . '/PaymentProviderInterface.php';

/**
 * Provider-neutral checkout registry. No provider is active until an adapter
 * is registered and explicitly enabled in private configuration.
 */
final class PaymentCoordinator
{
    /** @var array<string,PaymentProviderInterface> */
    private array $providers = [];

    /** @param array<string,mixed> $config */
    public function __construct(private array $config = [])
    {
    }

    public function register(PaymentProviderInterface $provider): void
    {
        $identifier = strtolower(trim($provider->identifier()));
        if (!preg_match('/^[a-z0-9_-]{2,40}$/', $identifier)) {
            throw new InvalidArgumentException('Invalid payment provider identifier.');
        }
        $this->providers[$identifier] = $provider;
    }

    public function primaryMethod(): string
    {
        $method = strtolower(trim((string)($this->config['primary_method'] ?? 'rtgs')));
        return in_array($method, ['rtgs', 'online', 'contactless', 'other'], true) ? $method : 'rtgs';
    }

    /**
     * @param array{merchant_reference:string,amount:string,currency:string,description:string} $payment
     * @param array{callback_url:string,notification_url:string} $urls
     * @return array{redirect_url:string,provider_reference:string,status:string}
     */
    public function createCheckout(array $payment, array $urls, ?string $providerId = null): array
    {
        $identifier = strtolower(trim($providerId ?: (string)($this->config['active_online_provider'] ?? '')));
        $providerConfig = is_array($this->config['providers'][$identifier] ?? null)
            ? $this->config['providers'][$identifier]
            : [];
        if ($identifier === '' || empty($providerConfig['enabled']) || !isset($this->providers[$identifier])) {
            throw new RuntimeException('Online checkout is not currently enabled.');
        }
        $this->validatePayment($payment);
        $this->validateUrls($urls);
        return $this->providers[$identifier]->createCheckout($payment, $urls);
    }

    /**
     * @return array{provider_reference:string,merchant_reference:string,amount:string,currency:string,status:string,verified:bool}
     */
    public function verify(string $providerId, string $providerReference): array
    {
        $identifier = strtolower(trim($providerId));
        if (!isset($this->providers[$identifier])) {
            throw new RuntimeException('The requested payment provider is not registered.');
        }
        if (trim($providerReference) === '') {
            throw new InvalidArgumentException('A provider reference is required.');
        }
        return $this->providers[$identifier]->verifyTransaction($providerReference);
    }

    /** @param array<string,mixed> $payment */
    private function validatePayment(array $payment): void
    {
        if (trim((string)($payment['merchant_reference'] ?? '')) === '') {
            throw new InvalidArgumentException('A merchant reference is required.');
        }
        if (!is_numeric($payment['amount'] ?? null) || (float)$payment['amount'] <= 0) {
            throw new InvalidArgumentException('A positive server-calculated amount is required.');
        }
        if (!preg_match('/^[A-Z]{3}$/', strtoupper((string)($payment['currency'] ?? '')))) {
            throw new InvalidArgumentException('A three-letter currency code is required.');
        }
    }

    /** @param array<string,mixed> $urls */
    private function validateUrls(array $urls): void
    {
        foreach (['callback_url', 'notification_url'] as $key) {
            $url = (string)($urls[$key] ?? '');
            if (!filter_var($url, FILTER_VALIDATE_URL) || strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https') {
                throw new InvalidArgumentException('Payment callback and notification URLs must use HTTPS.');
            }
        }
    }
}
