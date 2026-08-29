<?php
declare(strict_types=1);

use Resplendent\Payments\PaymentCoordinator;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 2) . '/includes/OperationsStore.php';
require_once dirname(__DIR__, 2) . '/includes/Payments/payment-bootstrap.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') throw new RuntimeException('POST required.');
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $invoiceId = trim((string)($input['invoice_id'] ?? ''));
    $store = new OperationsStore();
    $invoice = $store->getDocument($invoiceId);
    if (!is_array($invoice) || ($invoice['type'] ?? '') !== 'invoice') throw new RuntimeException('Invoice not found.');
    $payload = is_array($invoice['payload'] ?? null) ? $invoice['payload'] : [];
    $status = (string)($payload['invoice_status'] ?? '');
    if (!in_array($status, ['Issued', 'Part-paid', 'Overdue'], true)) throw new RuntimeException('Invoice is not payable online.');
    $amount = (float)($payload['balance_amount'] ?? 0);
    if ($amount <= 0) throw new RuntimeException('Invoice has no outstanding balance.');
    $currency = strtoupper((string)($payload['currency'] ?? ''));
    $name = trim((string)($payload['client_name'] ?? 'Traveller'));
    $parts = preg_split('/\s+/', $name, 2) ?: [$name];
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'www.resplendentglobaltravel.com'));
    if ($scheme !== 'https') throw new RuntimeException('Online payments require HTTPS.');
    $base = $scheme . '://' . $host;
    $merchantReference = preg_replace('/[^A-Za-z0-9_.:-]/', '-', (string)($invoice['number'] ?? $invoiceId)) . '-' . gmdate('YmdHis');

    // Prevent accidental rapid duplicate checkouts for the same invoice.
    foreach ($store->listPaymentTransactions($invoiceId) as $existingTx) {
        if (($existingTx['provider'] ?? '') !== 'pesapal') continue;
        if (($existingTx['status'] ?? '') === 'completed' && !empty($existingTx['verified'])) {
            throw new RuntimeException('This invoice already has a verified completed PesaPal payment.');
        }
        if (($existingTx['status'] ?? '') === 'pending') {
            $recordedAt = strtotime((string)($existingTx['recorded_at'] ?? '')) ?: 0;
            if ($recordedAt > 0 && (time() - $recordedAt) < 300) {
                throw new RuntimeException('A PesaPal checkout is already pending for this invoice. Please use the existing checkout or wait a few minutes before trying again.');
            }
        }
    }

    $checkout = rgts_payment_coordinator()->createCheckout([
        'merchant_reference' => substr($merchantReference, 0, 50),
        'amount' => number_format($amount, 2, '.', ''),
        'currency' => $currency,
        'description' => 'Resplendent invoice ' . (string)($invoice['number'] ?? ''),
        'billing_address' => [
            'email_address' => (string)($payload['email'] ?? ''),
            'phone_number' => (string)($payload['phone'] ?? ''),
            'first_name' => (string)($parts[0] ?? ''),
            'last_name' => (string)($parts[1] ?? ''),
        ],
    ], [
        'callback_url' => $base . '/api/payments/callback.php?invoice_id=' . rawurlencode($invoiceId),
        'notification_url' => $base . '/api/payments/ipn.php',
        'cancellation_url' => $base . '/payments.html?cancelled=1',
    ]);

    $store->recordPaymentTransaction([
        'invoice_id' => $invoiceId,
        'channel' => 'online',
        'provider' => 'pesapal',
        'provider_reference' => $checkout['provider_reference'],
        'merchant_reference' => substr($merchantReference, 0, 50),
        'amount' => $amount,
        'currency' => $currency,
        'status' => 'pending',
        'verified' => false,
    ]);

    echo json_encode(['ok' => true, 'redirect_url' => $checkout['redirect_url']], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
