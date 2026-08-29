<?php
declare(strict_types=1);

namespace Resplendent\Payments;

use OperationsStore;
use RuntimeException;
use Resplendent\Payments\Providers\PesapalProvider;

require_once dirname(__DIR__) . '/OperationsStore.php';
require_once __DIR__ . '/Providers/PesapalProvider.php';

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
        private PesapalProvider $pesapal
    ) {}

    /** @return array<string,mixed> */
    public function reconcilePesapal(string $trackingId, string $invoiceId = ''): array
    {
        $trackingId = trim($trackingId);
        if ($trackingId === '') throw new RuntimeException('Missing PesaPal tracking ID.');

        $invoice = $invoiceId !== '' ? $this->store->getDocument($invoiceId) : null;
        if (!is_array($invoice) || ($invoice['type'] ?? '') !== 'invoice') {
            $invoice = $this->findInvoiceByProviderReference('pesapal', $trackingId);
        }
        if (!is_array($invoice)) throw new RuntimeException('Matching invoice was not found.');

        $previouslyCompleted = $this->hasVerifiedCompletedTransaction((string)$invoice['id'], 'pesapal', $trackingId);
        $verified = $this->pesapal->verifyTransaction($trackingId);

        $this->store->recordPaymentTransaction([
            'invoice_id' => (string)$invoice['id'],
            'channel' => 'online',
            'provider' => 'pesapal',
            'provider_reference' => (string)$verified['provider_reference'],
            'merchant_reference' => (string)$verified['merchant_reference'],
            'confirmation_code' => (string)($verified['confirmation_code'] ?? ''),
            'amount' => (string)$verified['amount'],
            'currency' => (string)$verified['currency'],
            'status' => (string)$verified['status'],
            'verified' => !empty($verified['verified']),
            'idempotency_key' => 'pesapal-status|' . $trackingId . '|' . (string)$verified['status'],
            'note' => 'Server-to-server PesaPal reconciliation.',
        ]);

        $invoiceUpdated = false;
        if (!$previouslyCompleted && !empty($verified['verified']) && ($verified['status'] ?? '') === 'completed') {
            $payload = is_array($invoice['payload'] ?? null) ? $invoice['payload'] : [];
            $expectedCurrency = strtoupper((string)($payload['currency'] ?? ''));
            if ($expectedCurrency !== strtoupper((string)$verified['currency'])) {
                throw new RuntimeException('Currency mismatch during payment verification.');
            }
            $alreadyPaid = (float)($payload['amount_paid'] ?? 0);
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
