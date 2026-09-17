<?php
declare(strict_types=1);

namespace Resplendent\Payments;

use OperationsStore;
use RuntimeException;
use Resplendent\Payments\PaymentProviderInterface;

require_once dirname(__DIR__) . '/OperationsStore.php';
require_once __DIR__ . '/PaymentProviderInterface.php';

/**
 * Provider-neutral payment reconciliation helper.
 *
 * It verifies a gateway transaction server-to-server, records the status event,
 * and updates an invoice exactly once when a payment first becomes completed.
 */
final class PaymentReconciler
{
    public function __construct(
        private OperationsStore $store,
        private PaymentProviderInterface $provider,
        private string $providerId = 'pesapal'
    ) {}

    /** @return array<string,mixed> */
    public function reconcilePesapal(string $trackingId, string $invoiceId = ''): array
    {
        if ($this->providerId !== 'pesapal') throw new RuntimeException('Payment reconciler provider mismatch.');
        return $this->reconcile($trackingId, $invoiceId);
    }

    /** @return array<string,mixed> */
    public function reconcilePaystack(string $reference, string $invoiceId = ''): array
    {
        if ($this->providerId !== 'paystack') throw new RuntimeException('Payment reconciler provider mismatch.');
        return $this->reconcile($reference, $invoiceId);
    }

    /** @return array<string,mixed> */
    private function reconcile(string $trackingId, string $invoiceId = ''): array
    {
        $trackingId = trim($trackingId);
        if ($trackingId === '') throw new RuntimeException('Missing payment reference.');

        $invoice = $this->findInvoiceByProviderReference($this->providerId, $trackingId);
        if (!is_array($invoice)) throw new RuntimeException('Matching invoice was not found.');
        if ($invoiceId !== '' && !hash_equals((string)$invoice['id'], $invoiceId)) {
            throw new RuntimeException('Payment reference does not match this invoice.');
        }

        $previouslyCompleted = $this->hasVerifiedCompletedTransaction((string)$invoice['id'], $this->providerId, $trackingId);
        $verified = $this->provider->verifyTransaction($trackingId);

        $this->store->recordPaymentTransaction([
            'invoice_id' => (string)$invoice['id'],
            'channel' => 'online',
            'provider' => $this->providerId,
            'provider_reference' => (string)$verified['provider_reference'],
            'merchant_reference' => (string)$verified['merchant_reference'],
            'confirmation_code' => (string)($verified['confirmation_code'] ?? ''),
            'amount' => (string)$verified['amount'],
            'currency' => (string)$verified['currency'],
            'status' => (string)$verified['status'],
            'verified' => !empty($verified['verified']),
            'idempotency_key' => $this->providerId . '-status|' . $trackingId . '|' . (string)$verified['status'],
            'note' => 'Server-to-server ' . ucfirst($this->providerId) . ' reconciliation.',
        ]);

        $invoiceUpdated = false;
        if (!$previouslyCompleted && !empty($verified['verified']) && ($verified['status'] ?? '') === 'completed') {
            $payload = is_array($invoice['payload'] ?? null) ? $invoice['payload'] : [];
            $expectedCurrency = strtoupper((string)($payload['currency'] ?? ''));
            if ($expectedCurrency !== strtoupper((string)$verified['currency'])) {
                throw new RuntimeException('Currency mismatch during payment verification.');
            }
            $alreadyPaid = (float)($payload['amount_paid'] ?? 0);
            $remainingBalance = max(0, (float)($payload['total_amount'] ?? 0) - $alreadyPaid);
            if ((float)$verified['amount'] > $remainingBalance + 0.005) {
                throw new RuntimeException('Verified payment exceeds the outstanding invoice balance.');
            }
            $this->store->updateInvoicePayment((string)$invoice['id'], $alreadyPaid + (float)$verified['amount']);
            $invoiceUpdated = true;
        }

        return [
            'invoice_id' => (string)$invoice['id'],
            'invoice_number' => (string)($invoice['number'] ?? ''),
            'status' => (string)($verified['status'] ?? 'pending'),
            'verified' => !empty($verified['verified']),
            'provider_reference' => $trackingId,
            'merchant_reference' => (string)($verified['merchant_reference'] ?? ''),
            'confirmation_code' => (string)($verified['confirmation_code'] ?? ''),
            'amount' => (string)($verified['amount'] ?? '0.00'),
            'currency' => (string)($verified['currency'] ?? ''),
            'payment_method' => (string)($verified['payment_method'] ?? ''),
            'invoice_updated' => $invoiceUpdated,
            'already_completed' => $previouslyCompleted,
        ];
    }

    /** @return array<string,mixed>|null */
    private function findInvoiceByProviderReference(string $provider, string $reference): ?array
    {
        foreach ($this->store->listDocuments() as $document) {
            if (!is_array($document) || ($document['type'] ?? '') !== 'invoice') continue;
            foreach ($this->store->listPaymentTransactions((string)$document['id']) as $tx) {
                if (($tx['provider'] ?? '') === $provider && ($tx['provider_reference'] ?? '') === $reference) {
                    return $document;
                }
            }
        }
        return null;
    }

    private function hasVerifiedCompletedTransaction(string $invoiceId, string $provider, string $reference): bool
    {
        foreach ($this->store->listPaymentTransactions($invoiceId) as $tx) {
            if (($tx['provider'] ?? '') !== $provider) continue;
            if (($tx['provider_reference'] ?? '') !== $reference) continue;
            if (($tx['status'] ?? '') === 'completed' && !empty($tx['verified'])) return true;
        }
        return false;
    }
}
