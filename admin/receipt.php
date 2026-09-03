<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
admin_require_auth();

$store = admin_store();
$documents = $store->listDocuments();
$invoices = array_values(array_filter($documents, static fn(array $document): bool =>
    ($document['type'] ?? '') === 'invoice'
    && ($document['lifecycle_status'] ?? 'active') !== 'superseded'
    && !in_array((string)($document['payload']['invoice_status'] ?? ''), ['Draft', 'Superseded'], true)
    && (float)($document['payload']['amount_paid'] ?? 0) > 0
));
$invoiceId = admin_text($_GET['invoice'] ?? $_POST['invoice_id'] ?? '', 100);
$invoice = $invoiceId !== '' ? $store->getDocument($invoiceId) : null;
if ($invoice && (($invoice['type'] ?? '') !== 'invoice' || (float)($invoice['payload']['amount_paid'] ?? 0) <= 0)) $invoice = null;
$invoicePayload = is_array($invoice['payload'] ?? null) ? $invoice['payload'] : [];

$receipted = 0.0;
if ($invoice) {
    foreach ($documents as $doc) {
        if (($doc['type'] ?? '') !== 'receipt') continue;
        $payload = is_array($doc['payload'] ?? null) ? $doc['payload'] : [];
        if (($payload['invoice_id'] ?? '') === ($invoice['id'] ?? '')) $receipted += max(0, (float)($payload['amount_received'] ?? 0));
    }
}
$available = max(0, (float)($invoicePayload['amount_paid'] ?? 0) - $receipted);
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'The secure session expired. Please refresh and try again.';
    } elseif (!$invoice) {
        $error = 'Choose an invoice with a verified payment before issuing a receipt.';
    } else {
        $amount = max(0, (float)($_POST['amount_received'] ?? 0));
        if ($amount <= 0) {
            $error = 'Enter the amount actually received.';
        } elseif ($amount > $available + 0.005) {
            $error = 'This amount is higher than the verified payment not yet receipted.';
        } else {
            $currency = admin_text($invoicePayload['currency'] ?? 'USD', 10);
            $balance = max(0, (float)($invoicePayload['total_amount'] ?? 0) - (float)($invoicePayload['amount_paid'] ?? 0));
            $payload = [
                'client_reference' => $invoice['client_reference'] ?? '',
                'client_name' => $invoicePayload['client_name'] ?? $invoice['client_name'] ?? '',
                'company' => $invoicePayload['company'] ?? $invoice['company'] ?? '',
                'email' => $invoicePayload['email'] ?? '',
                'phone' => $invoicePayload['phone'] ?? '',
                'receipt_date' => admin_text($_POST['receipt_date'] ?? gmdate('Y-m-d'), 20),
                'invoice_id' => $invoice['id'] ?? '',
                'invoice_number' => $invoice['number'] ?? '',
                'invoice_type' => $invoicePayload['invoice_type'] ?? 'Travel & Services',
                'service_description' => admin_text($_POST['service_description'] ?? '', 500),
                'currency' => $currency,
                'amount_received' => number_format($amount, 2, '.', ''),
                'payment_method' => admin_text($_POST['payment_method'] ?? 'Bank Transfer / RTGS', 80),
                'payment_reference' => admin_text($_POST['payment_reference'] ?? '', 120),
                'invoice_total' => number_format((float)($invoicePayload['total_amount'] ?? 0), 2, '.', ''),
                'invoice_amount_paid' => number_format((float)($invoicePayload['amount_paid'] ?? 0), 2, '.', ''),
                'balance_remaining' => number_format($balance, 2, '.', ''),
                'payment_status' => $balance <= 0.005 ? 'PAID IN FULL' : 'PART PAYMENT',
                'receipt_note' => admin_text($_POST['receipt_note'] ?? 'Thank you. This receipt confirms funds received by Resplendent Global Solutions Limited.', 1000),
            ];
            if ($payload['service_description'] === '') {
                $items = is_array($invoicePayload['items'] ?? null) ? $invoicePayload['items'] : [];
                $payload['service_description'] = admin_text($items[0]['description'] ?? $invoicePayload['invoice_type'] ?? 'Travel services', 500);
            }
            $record = $store->saveDocument('receipt', $payload);
            admin_flash('success', 'Receipt ' . (string)$record['number'] . ' is ready.');
            header('Location: document.php?id=' . rawurlencode((string)$record['id']), true, 303);
            exit;
        }
    }
}

admin_page_header('Receipt Generator', 'receipt');
?>
<?php if ($error !== ''): ?><div class="admin-alert error" role="alert"><?= admin_e($error) ?></div><?php endif; ?>
<div class="generator-shell">
<form method="post" class="generator-form admin-form">
<input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>">
<div class="generator-intro"><div><p class="eyebrow">Payment Document</p><h2>Confirm funds actually received.</h2></div><p>Issue a receipt only after the payment has been verified in the bank or approved payment channel. Bank account details are deliberately excluded from receipts.</p></div>
<fieldset><legend><span>01</span> Verified invoice</legend>
<label>Invoice
<select name="invoice_id" onchange="if(this.value){window.location='receipt.php?invoice='+encodeURIComponent(this.value)}">
<option value="">Select an invoice with payment recorded</option>
<?php foreach ($invoices as $item): ?><option value="<?= admin_e($item['id'] ?? '') ?>" <?= ($invoice['id'] ?? '') === ($item['id'] ?? '') ? 'selected' : '' ?>><?= admin_e(($item['number'] ?? '') . ' — ' . ($item['client_name'] ?? 'Client') . ' — ' . ($item['payload']['currency'] ?? 'USD') . ' ' . number_format((float)($item['payload']['amount_paid'] ?? 0), 2)) ?></option><?php endforeach; ?>
</select></label>
<?php if ($invoice): ?><div class="form-grid"><label>Client<input type="text" readonly value="<?= admin_e($invoicePayload['client_name'] ?? $invoice['client_name'] ?? '') ?>"></label><label>Company<input type="text" readonly value="<?= admin_e($invoicePayload['company'] ?? '') ?>"></label><label>Invoice total<input type="text" readonly value="<?= admin_e(($invoicePayload['currency'] ?? 'USD') . ' ' . number_format((float)($invoicePayload['total_amount'] ?? 0),2)) ?>"></label><label>Verified paid to date<input type="text" readonly value="<?= admin_e(($invoicePayload['currency'] ?? 'USD') . ' ' . number_format((float)($invoicePayload['amount_paid'] ?? 0),2)) ?>"></label><label>Available to receipt<input type="text" readonly value="<?= admin_e(($invoicePayload['currency'] ?? 'USD') . ' ' . number_format($available,2)) ?>"></label></div><?php endif; ?>
</fieldset>
<?php if ($invoice): ?>
<fieldset><legend><span>02</span> Payment received</legend><div class="form-grid"><label>Receipt date<input type="date" name="receipt_date" value="<?= gmdate('Y-m-d') ?>" required></label><label>Amount received<input type="number" name="amount_received" min="0.01" step="0.01" max="<?= admin_e(number_format($available,2,'.','')) ?>" value="<?= admin_e(number_format($available,2,'.','')) ?>" required></label><label>Payment method<select name="payment_method"><option>Bank Transfer / RTGS</option><option>Card / Online Payment</option><option>M-Pesa</option><option>Other Approved Method</option></select></label><label>Payment reference<input type="text" name="payment_reference" maxlength="120" placeholder="Bank / transaction reference"></label></div><label>Service description<input type="text" name="service_description" maxlength="500" value="<?= admin_e($invoicePayload['items'][0]['description'] ?? $invoicePayload['invoice_type'] ?? 'Travel services') ?>"></label><label>Receipt note<textarea name="receipt_note" rows="3">Thank you. This receipt confirms funds received by Resplendent Global Solutions Limited.</textarea></label></fieldset>
<div class="generator-actions"><button type="submit" <?= $available <= 0.005 ? 'disabled' : '' ?>>Generate Receipt</button><?php if ($available <= 0.005): ?><small>All currently verified payments on this invoice have already been receipted.</small><?php endif; ?></div>
<?php endif; ?>
</form></div>
<?php admin_page_footer(); ?>
