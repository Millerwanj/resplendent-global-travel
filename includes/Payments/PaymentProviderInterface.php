<?php
declare(strict_types=1);

namespace Resplendent\Payments;

/**
 * Contract for any hosted checkout provider connected to Resplendent.
 * Implementations must verify transactions with the provider's server before
 * returning a completed, verified result.
 */
interface PaymentProviderInterface
{
    public function identifier(): string;

    /**
     * @param array{merchant_reference:string,amount:string,currency:string,description:string} $payment
     * @param array{callback_url:string,notification_url:string} $urls
     * @return array{redirect_url:string,provider_reference:string,status:string}
     */
    public function createCheckout(array $payment, array $urls): array;

    /**
     * @return array{provider_reference:string,merchant_reference:string,amount:string,currency:string,status:string,verified:bool}
     */
    public function verifyTransaction(string $providerReference): array;

    /**
     * Parse references only. Notification data must never be treated as proof
     * of payment until verifyTransaction() succeeds server-to-server.
     *
     * @param array<string,mixed> $notification
     * @return array{provider_reference:string,merchant_reference:string}
     */
    public function parseNotification(array $notification): array;
}
