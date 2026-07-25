<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
admin_require_auth();

$id = admin_text($_GET['id'] ?? '', 100);
$document = $id !== '' ? admin_store()->getDocument($id) : null;
if (!$document) {
    http_response_code(404);
    admin_flash('error', 'That generated document could not be found.');
    header('Location: index.php', true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($document['type'] ?? '') === 'invoice') {
    if (!admin_verify_csrf($_POST['csrf'] ?? null)) {
        header('Location: document.php?id=' . rawurlencode($id) . '&payment=error', true, 303);
        exit;
    }
    $amountPaid = max(0, (float)($_POST['amount_paid'] ?? 0));
    $updated = admin_store()->updateInvoicePayment($id, $amountPaid);
    header(
        'Location: document.php?id=' . rawurlencode($id) . '&payment=' . ($updated ? 'updated' : 'error'),
        true,
        303
    );
    exit;
}

$payload = is_array($document['payload'] ?? null) ? $document['payload'] : [];
$type = (string)($document['type'] ?? 'proposal');
$isProposal = $type === 'proposal';
$isQuotation = $type === 'quotation';
$isInvoice = $type === 'invoice';
$documentTitle = match ($type) {
    'quotation' => 'Executive Quotation',
    'invoice' => 'Invoice',
    default => 'Executive Proposal',
};
$currency = (string)($payload['currency'] ?? 'USD');

function document_value(array $payload, string $key, string $fallback = '—'): string
{
    $value = trim((string)($payload[$key] ?? ''));
    return $value !== '' ? $value : $fallback;
}

function document_money(string $currency, mixed $amount): string
{
    return $currency . ' ' . number_format((float)$amount, 2);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?= admin_e($document['number'] ?? '') ?> | <?= admin_e($documentTitle) ?></title>
    <link rel="icon" href="../favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css?v=9.6.1">
</head>
<body class="document-page">
<div class="document-actions">
    <a href="<?= $isProposal ? 'proposal.php' : ($isQuotation ? 'quotation.php' : 'invoice.php') ?>">Create another</a>
    <?php if ($isQuotation): ?><a href="invoice.php?quotation=<?= rawurlencode($id) ?>">Create Invoice</a><?php endif; ?>
    <a href="index.php">Operations home</a>
    <button type="button" onclick="window.print()">Print / Save PDF</button>
</div>
<?php if ($isInvoice): ?>
<section class="document-payment-control">
    <div>
        <span>Invoice status</span>
        <strong class="invoice-status <?= admin_e(strtolower(str_replace('-', '', (string)($payload['invoice_status'] ?? 'Issued')))) ?>"><?= admin_e($payload['invoice_status'] ?? 'Issued') ?></strong>
        <?php if (($_GET['payment'] ?? '') === 'updated'): ?><small class="payment-update-success">Payment position updated.</small><?php endif; ?>
        <?php if (($_GET['payment'] ?? '') === 'error'): ?><small class="payment-update-error">The payment update could not be saved.</small><?php endif; ?>
    </div>
    <form method="post" class="payment-update-form">
        <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>">
        <label>Total amount paid
            <input type="number" name="amount_paid" min="0" step="0.01" max="<?= admin_e($payload['total_amount'] ?? '') ?>" value="<?= admin_e($payload['amount_paid'] ?? '0.00') ?>">
        </label>
        <button type="submit">Update Payment</button>
    </form>
</section>
<?php endif; ?>
<main class="final-document">
    <header class="final-document-header">
        <div class="final-brand"><img src="../assets/images/logo-r.png" alt="" width="64" height="64"><span><strong>RESPLENDENT</strong><small>GLOBAL TRAVEL SOLUTIONS</small></span></div>
        <div><span><?= admin_e($documentTitle) ?></span><strong><?= admin_e($document['number'] ?? '') ?></strong></div>
    </header>

    <section class="final-title">
        <p><?= admin_e(document_value($payload, $isProposal ? 'proposal_type' : ($isQuotation ? 'quotation_type' : 'invoice_type'), $documentTitle)) ?></p>
        <h1><?= admin_e(document_value($payload, 'client_name', 'Client')) ?></h1>
        <?php if (!empty($payload['company'])): ?><span>Prepared for <?= admin_e($payload['company']) ?></span><?php endif; ?>
    </section>

    <section class="final-meta">
        <div><span><?= $isInvoice ? 'Issued' : 'Prepared' ?></span><strong><?= admin_e(date('j F Y', strtotime((string)($payload[$isProposal ? 'proposal_date' : ($isQuotation ? 'quotation_date' : 'invoice_date')] ?? $document['created_at'])))) ?></strong></div>
        <?php if ($isQuotation): ?><div><span>Valid until</span><strong><?= admin_e(date('j F Y', strtotime((string)($payload['valid_until'] ?? $document['created_at'])))) ?></strong></div><?php endif; ?>
        <?php if ($isInvoice): ?><div><span>Due date</span><strong><?= admin_e(date('j F Y', strtotime((string)($payload['due_date'] ?? $document['created_at'])))) ?></strong></div><div><span>Status</span><strong><?= admin_e($payload['invoice_status'] ?? 'Issued') ?></strong></div><?php endif; ?>
        <?php if (!empty($payload['email'])): ?><div><span>Client contact</span><strong><?= admin_e($payload['email']) ?></strong></div><?php endif; ?>
    </section>

    <?php if ($isProposal): ?>
        <p class="final-introduction"><?= nl2br(admin_e(document_value($payload, 'introduction', 'Thank you for the opportunity to consider your requirements.'))) ?></p>
        <?php $business = ($payload['proposal_type'] ?? '') === 'Global Business Connections'; ?>
        <?php if (!$business): ?>
            <section class="final-section">
                <div class="section-number">01</div><div>
                    <h2>Flight Recommendation</h2>
                    <dl class="final-specs"><div><dt>Airline</dt><dd><?= admin_e(document_value($payload, 'airline')) ?></dd></div><div><dt>Route</dt><dd><?= admin_e(document_value($payload, 'route')) ?></dd></div><div><dt>Cabin</dt><dd><?= admin_e(document_value($payload, 'cabin')) ?></dd></div></dl>
                    <p><?= nl2br(admin_e(document_value($payload, 'flight_recommendation', 'Recommendation to be confirmed.'))) ?></p>
                </div>
            </section>
            <section class="final-section">
                <div class="section-number">02</div><div>
                    <h2>Accommodation</h2>
                    <dl class="final-specs"><div><dt>Hotel</dt><dd><?= admin_e(document_value($payload, 'hotel')) ?></dd></div><div><dt>Room &amp; plan</dt><dd><?= admin_e(trim(document_value($payload, 'room') . ' · ' . document_value($payload, 'meal_plan'))) ?></dd></div><div><dt>Stay</dt><dd><?= admin_e(document_value($payload, 'nights')) ?> nights</dd></div></dl>
                    <p><?= nl2br(admin_e(document_value($payload, 'hotel_recommendation', 'Recommendation to be confirmed.'))) ?></p>
                </div>
            </section>
        <?php else: ?>
            <section class="final-section">
                <div class="section-number">01</div><div>
                    <h2>Business Connection Mandate</h2>
                    <dl class="final-specs"><div><dt>Market</dt><dd><?= admin_e(document_value($payload, 'target_market')) ?></dd></div><div><dt>Partner profile</dt><dd><?= admin_e(document_value($payload, 'partner_profile')) ?></dd></div><div><dt>Objective</dt><dd><?= admin_e(document_value($payload, 'engagement_objective')) ?></dd></div></dl>
                    <p><?= nl2br(admin_e(document_value($payload, 'business_approach'))) ?></p>
                </div>
            </section>
        <?php endif; ?>

        <section class="final-section">
            <div class="section-number"><?= $business ? '02' : '03' ?></div><div>
                <h2>Included Services</h2>
                <ul class="final-services">
                    <?php foreach ((array)($payload['included_services'] ?? []) as $service): ?><li><?= admin_e($service) ?></li><?php endforeach; ?>
                </ul>
            </div>
        </section>
        <section class="final-investment">
            <div>
                <span>Total investment</span>
                <strong><?= admin_e(document_value($payload, 'grand_total', $currency . ' 0.00')) ?></strong>
            </div>
            <?php if ($business): ?><p><strong>Payment schedule:</strong> USD 250 on engagement, USD 150 at the mid-project milestone and USD 100 on final delivery.</p><?php else: ?><p><?= nl2br(admin_e(document_value($payload, 'investment_note'))) ?></p><?php endif; ?>
        </section>
    <?php elseif ($isQuotation): ?>
        <?php $business = ($payload['quotation_type'] ?? '') === 'Business Matchmaking'; ?>
        <section class="final-section quote-section">
            <div class="section-number">01</div><div>
                <h2>Investment Schedule</h2>
                <table class="final-quote-table">
                    <thead><tr><th>Description</th><th>Qty</th><th>Amount</th></tr></thead>
                    <tbody>
                    <?php if ($business): ?>
                        <tr><td>On acceptance — engagement and research commencement</td><td>1</td><td>USD 250.00</td></tr>
                        <tr><td>Mid-project research and outreach milestone</td><td>1</td><td>USD 150.00</td></tr>
                        <tr><td>Final delivery of agreed engagement output</td><td>1</td><td>USD 100.00</td></tr>
                    <?php else: ?>
                        <?php foreach ((array)($payload['items'] ?? []) as $item): if (!is_array($item)) continue; ?>
                            <tr><td><?= admin_e($item['description'] ?? '') ?></td><td><?= admin_e($item['quantity'] ?? '1') ?></td><td><?= admin_e(document_money($currency, $item['total'] ?? 0)) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if ((float)($payload['service_fee'] ?? 0) > 0): ?><tr><td>Professional service fee</td><td>1</td><td><?= admin_e(document_money($currency, $payload['service_fee'])) ?></td></tr><?php endif; ?>
                        <?php if ((float)($payload['discount'] ?? 0) > 0): ?><tr><td>Discount</td><td>—</td><td>−<?= admin_e(document_money($currency, $payload['discount'])) ?></td></tr><?php endif; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <section class="final-investment quote-final-total"><div><span>Total investment</span><strong><?= admin_e(document_value($payload, 'grand_total', $currency . ' 0.00')) ?></strong></div></section>
        <section class="final-section compact">
            <div class="section-number">02</div><div><h2>Terms</h2><p><?= nl2br(admin_e(document_value($payload, 'scope_note'))) ?></p><p><?= nl2br(admin_e(document_value($payload, 'payment_terms'))) ?></p></div>
        </section>
        <section class="final-section compact">
            <div class="section-number">03</div><div><h2>Payment</h2><p><?= nl2br(admin_e(document_value($payload, 'payment_details', 'Payment instructions will be provided on acceptance.'))) ?></p></div>
        </section>
    <?php else: ?>
        <section class="final-section quote-section">
            <div class="section-number">01</div><div>
                <h2>Invoice Details</h2>
                <?php if (!empty($payload['quotation_number'])): ?><p class="invoice-reference-line">Related quotation: <strong><?= admin_e($payload['quotation_number']) ?></strong></p><?php endif; ?>
                <table class="final-quote-table">
                    <thead><tr><th>Description</th><th>Qty</th><th>Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ((array)($payload['items'] ?? []) as $item): if (!is_array($item)) continue; ?>
                        <tr><td><?= admin_e($item['description'] ?? '') ?></td><td><?= admin_e($item['quantity'] ?? '1') ?></td><td><?= admin_e(document_money($currency, $item['total'] ?? 0)) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if ((float)($payload['service_fee'] ?? 0) > 0): ?><tr><td>Professional service fee</td><td>1</td><td><?= admin_e(document_money($currency, $payload['service_fee'])) ?></td></tr><?php endif; ?>
                    <?php if ((float)($payload['discount'] ?? 0) > 0): ?><tr><td>Discount</td><td>—</td><td>−<?= admin_e(document_money($currency, $payload['discount'])) ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <section class="invoice-financial-summary">
            <div><span>Invoice total</span><strong><?= admin_e(document_money($currency, $payload['total_amount'] ?? 0)) ?></strong></div>
            <div><span>Amount paid</span><strong><?= admin_e(document_money($currency, $payload['amount_paid'] ?? 0)) ?></strong></div>
            <div class="balance"><span>Balance due</span><strong><?= admin_e(document_money($currency, $payload['balance_amount'] ?? 0)) ?></strong></div>
        </section>
        <?php $payment = is_array($payload['payment_settings'] ?? null) ? $payload['payment_settings'] : []; ?>
        <section class="final-section compact">
            <div class="section-number">02</div><div>
                <h2>Payment Instructions</h2>
                <dl class="invoice-payment-grid">
                    <?php if (!empty($payment['bank_name'])): ?><div><dt>Bank</dt><dd><?= admin_e($payment['bank_name']) ?></dd></div><?php endif; ?>
                    <?php if (!empty($payment['account_name'])): ?><div><dt>Account name</dt><dd><?= admin_e($payment['account_name']) ?></dd></div><?php endif; ?>
                    <?php if (!empty($payment['account_number'])): ?><div><dt>Account number</dt><dd><?= admin_e($payment['account_number']) ?></dd></div><?php endif; ?>
                    <?php if (!empty($payment['branch'])): ?><div><dt>Branch</dt><dd><?= admin_e($payment['branch']) ?></dd></div><?php endif; ?>
                    <?php if (!empty($payment['swift_iban'])): ?><div><dt>SWIFT / IBAN</dt><dd><?= admin_e($payment['swift_iban']) ?></dd></div><?php endif; ?>
                    <?php if (!empty($payment['currency'])): ?><div><dt>Account currency</dt><dd><?= admin_e($payment['currency']) ?></dd></div><?php endif; ?>
                    <?php if (!empty($payment['mpesa_number'])): ?><div><dt>M-Pesa</dt><dd><?= admin_e(trim((string)($payment['mpesa_name'] ?? '') . ' ' . (string)$payment['mpesa_number'])) ?></dd></div><?php endif; ?>
                </dl>
                <p><?= nl2br(admin_e(document_value($payment, 'instructions', 'Payment instructions will be provided separately.'))) ?></p>
                <?php if (!empty($payment['payment_link'])): ?><p><a href="<?= admin_e($payment['payment_link']) ?>">Secure payment link</a></p><?php endif; ?>
            </div>
        </section>
        <section class="final-section compact">
            <div class="section-number">03</div><div><h2>Thank You</h2><p><?= nl2br(admin_e(document_value($payload, 'invoice_note'))) ?></p></div>
        </section>
    <?php endif; ?>

    <footer class="final-document-footer"><span>resplendentglobaltravel.com</span><span>info@resplendentglobaltravel.com</span><span>Precision · Discretion · Purpose</span></footer>
</main>
</body>
</html>
