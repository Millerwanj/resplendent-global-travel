<?php
declare(strict_types=1);

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

require_once dirname(__DIR__, 2) . '/includes/OperationsStore.php';

$invoiceId = trim((string)($_GET['invoice'] ?? ''));
$trackingId = trim((string)($_GET['tracking'] ?? ''));

try {
    if ($invoiceId === '' || $trackingId === '') throw new RuntimeException('Receipt reference is incomplete.');
    $store = new OperationsStore();
    $invoice = $store->getDocument($invoiceId);
    if (!is_array($invoice) || ($invoice['type'] ?? '') !== 'invoice') throw new RuntimeException('Invoice not found.');

    $payment = null;
    foreach ($store->listPaymentTransactions($invoiceId) as $tx) {
        if (($tx['provider'] ?? '') !== 'pesapal') continue;
        if (($tx['provider_reference'] ?? '') !== $trackingId) continue;
        if (($tx['status'] ?? '') !== 'completed' || empty($tx['verified'])) continue;
        $payment = $tx;
        break;
    }
    if (!is_array($payment)) throw new RuntimeException('A verified completed payment was not found.');

    $p = is_array($invoice['payload'] ?? null) ? $invoice['payload'] : [];
    $esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $receiptNo = 'RGT-R-' . strtoupper(substr(hash('sha256', $trackingId), 0, 10));
    $date = (string)($payment['verified_at'] ?? $payment['recorded_at'] ?? gmdate('c'));
    $dateText = date('d M Y, H:i', strtotime($date) ?: time());
} catch (Throwable $e) {
    http_response_code(404);
    exit('Receipt unavailable.');
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment Receipt | Resplendent Global Travel Solutions</title>
<style>
body{margin:0;background:#f6f2e9;color:#173f3a;font-family:Arial,sans-serif}.wrap{max-width:760px;margin:40px auto;background:#fff;padding:46px;box-sizing:border-box}.brand{display:flex;align-items:center;gap:14px;border-bottom:1px solid #ddd;padding-bottom:24px}.brand img{width:52px;height:52px;object-fit:contain}.brand strong{font-family:Georgia,serif;font-size:24px;letter-spacing:.08em}.muted{color:#667}.title{font-family:Georgia,serif;font-size:36px;margin:34px 0 8px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin:30px 0}.box{border-top:1px solid #ddd;padding-top:12px}.label{font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#777}.value{font-size:16px;margin-top:6px}.amount{font-family:Georgia,serif;font-size:30px}.actions{margin-top:32px}.btn{display:inline-block;padding:12px 18px;border:1px solid #173f3a;color:#173f3a;text-decoration:none;margin-right:8px;background:#fff}.note{font-size:12px;line-height:1.6;color:#666;margin-top:30px}@media(max-width:620px){.wrap{margin:0;padding:28px 20px}.grid{grid-template-columns:1fr}}@media print{body{background:#fff}.wrap{margin:0;max-width:none}.actions{display:none}}
</style></head><body><main class="wrap">
<div class="brand"><img src="/assets/images/logo-r.png" alt=""><div><strong>RESPLENDENT</strong><div class="muted">GLOBAL TRAVEL SOLUTIONS</div></div></div>
<h1 class="title">Payment Receipt</h1><p class="muted">Verified payment received.</p>
<div class="grid">
<div class="box"><div class="label">Receipt</div><div class="value"><?= $esc($receiptNo) ?></div></div>
<div class="box"><div class="label">Date</div><div class="value"><?= $esc($dateText) ?></div></div>
<div class="box"><div class="label">Invoice</div><div class="value"><?= $esc($invoice['number'] ?? '') ?></div></div>
<div class="box"><div class="label">Client</div><div class="value"><?= $esc($p['client_name'] ?? '') ?></div></div>
<div class="box"><div class="label">PesaPal confirmation</div><div class="value"><?= $esc($payment['confirmation_code'] ?? '') ?></div></div>
<div class="box"><div class="label">Payment reference</div><div class="value"><?= $esc($payment['merchant_reference'] ?? '') ?></div></div>
<div class="box"><div class="label">Amount received</div><div class="value amount"><?= $esc($payment['currency'] ?? '') ?> <?= $esc($payment['amount'] ?? '') ?></div></div>
<div class="box"><div class="label">Invoice status</div><div class="value"><?= $esc($p['invoice_status'] ?? '') ?></div></div>
</div>
<div class="actions"><button class="btn" onclick="window.print()">Print / Save PDF</button><a class="btn" href="/">Return to Resplendent</a></div>
<p class="note">This receipt confirms funds verified by our payment provider against the invoice shown above. Supplier space, ticketing, permits and travel services remain subject to the written Resplendent confirmation applicable to your booking.</p>
</main></body></html>