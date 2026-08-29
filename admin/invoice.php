<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
admin_require_auth();

$store = admin_store();
$quotationIsAccepted = static function (OperationsStore $store, array $document): bool {
    if (($document['type'] ?? '') !== 'quotation') return false;
    if (($document['lifecycle_status'] ?? 'active') === 'superseded') return false;
    if (!empty($document['draft_invoice']['id'])) return false;
    if (($document['delivery']['status'] ?? '') === 'accepted') return true;
    foreach ($store->listDocumentDeliveries((string)($document['id'] ?? '')) as $delivery) {
        if (is_array($delivery) && ($delivery['status'] ?? '') === 'accepted') return true;
    }
    return false;
};
$quotations = array_values(array_filter(
    $store->listDocuments(),
    static fn(array $document): bool => $quotationIsAccepted($store, $document)
));
$sourceId = admin_text($_GET['quotation'] ?? $_POST['source_quotation'] ?? '', 100);
$source = $sourceId !== '' ? $store->getDocument($sourceId) : null;
if ($source && !$quotationIsAccepted($store, $source)) {
    $source = null;
}
$sourcePayload = is_array($source['payload'] ?? null) ? $source['payload'] : [];
$clientReference = admin_text(
    $_GET['client'] ?? $_POST['client_reference'] ?? $source['client_reference'] ?? '',
    40
);
$client = $clientReference !== '' ? $store->getClient($clientReference) : null;
$paymentSettings = $store->getPaymentSettings();
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'The secure session expired. Please refresh and try again.';
    } else {
        $payload = json_decode((string)($_POST['document_payload'] ?? ''), true);
        if (!is_array($payload)) {
            $error = 'The invoice data could not be read.';
        } elseif (admin_text($payload['client_name'] ?? '', 120) === '' || empty($payload['items'])) {
            $error = 'Add the client name and at least one invoice item.';
        } else {
            $total = max(0, (float)($payload['total_amount'] ?? 0));
            $paid = min($total, max(0, (float)($payload['amount_paid'] ?? 0)));
            $balance = max(0, $total - $paid);
            $dueDate = admin_text($payload['due_date'] ?? '', 20);
            if ($balance <= 0.005 && $total > 0) {
                $status = 'Paid';
            } elseif ($paid > 0) {
                $status = 'Part-paid';
            } elseif ($dueDate !== '' && $dueDate < gmdate('Y-m-d')) {
                $status = 'Overdue';
            } else {
                $status = 'Issued';
            }
            $payload['amount_paid'] = number_format($paid, 2, '.', '');
            $payload['balance_amount'] = number_format($balance, 2, '.', '');
            $payload['invoice_status'] = $status;
            $payload['payment_settings'] = $store->getPaymentSettings();
            $record = $store->saveDocument('invoice', $payload);
            admin_flash('success', 'Invoice ' . (string)$record['number'] . ' is ready.');
            header('Location: document.php?id=' . rawurlencode((string)$record['id']), true, 303);
            exit;
        }
    }
}

$prefillItems = is_array($sourcePayload['items'] ?? null) ? $sourcePayload['items'] : [];
if ($prefillItems === []) $prefillItems = [['description' => 'Professional travel service', 'quantity' => '1', 'unit_price' => '0.00']];
$sourceType = (string)($sourcePayload['quotation_type'] ?? 'Travel & Services');
$invoiceType = $sourceType === 'Business Matchmaking' ? 'Business Matchmaking' : ($sourceType === 'Corporate Travel' ? 'Corporate Travel' : 'Travel & Services');
$prefillClient = [
    'name' => $sourcePayload['client_name'] ?? $client['name'] ?? '',
    'company' => $sourcePayload['company'] ?? $client['company'] ?? '',
    'email' => $sourcePayload['email'] ?? $client['email'] ?? '',
    'phone' => $sourcePayload['phone'] ?? $client['phone'] ?? '',
];

admin_page_header('Invoice Generator', 'invoice');
?>
<?php if ($error !== ''): ?><div class="admin-alert error" role="alert"><?= admin_e($error) ?></div><?php endif; ?>
<div class="generator-shell" data-invoice-generator>
    <form method="post" class="generator-form admin-form" data-invoice-form>
        <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>">
        <input type="hidden" name="client_reference" value="<?= admin_e($clientReference) ?>">
        <input type="hidden" name="document_payload" data-document-payload>
        <div class="generator-intro">
            <div><p class="eyebrow">Financial Document</p><h2>Issue a clear, professional invoice.</h2></div>
            <p>Convert an approved quotation or create a new invoice. Payment details are drawn from your private settings automatically.</p>
        </div>

        <fieldset>
            <legend><span>01</span> Source &amp; client</legend>
            <?php if ($quotations !== []): ?>
            <label>Convert approved quotation
                <select name="source_quotation" data-source-quotation>
                    <option value="">Create without a quotation</option>
                    <?php foreach ($quotations as $quotation): ?>
                        <option value="<?= admin_e($quotation['id'] ?? '') ?>" <?= ($source['id'] ?? '') === ($quotation['id'] ?? '') ? 'selected' : '' ?>>
                            <?= admin_e(($quotation['number'] ?? '') . ' — ' . ($quotation['client_name'] ?? 'Client')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php else: ?><input type="hidden" name="source_quotation" value=""><?php endif; ?>
            <div class="form-grid">
                <label>Invoice type
                    <select name="invoice_type" data-invoice-type>
                        <option <?= $invoiceType === 'Travel & Services' ? 'selected' : '' ?>>Travel &amp; Services</option>
                        <option <?= $invoiceType === 'Corporate Travel' ? 'selected' : '' ?>>Corporate Travel</option>
                        <option <?= $invoiceType === 'Business Matchmaking' ? 'selected' : '' ?>>Business Matchmaking</option>
                    </select>
                </label>
                <label>Related quotation<input type="text" name="quotation_number" readonly value="<?= admin_e($source['number'] ?? '') ?>" placeholder="Not linked"></label>
                <label>Client name<input type="text" name="client_name" required value="<?= admin_e($prefillClient['name']) ?>"></label>
                <label>Company<input type="text" name="company" value="<?= admin_e($prefillClient['company']) ?>"></label>
                <label>Email<input type="email" name="email" value="<?= admin_e($prefillClient['email']) ?>"></label>
                <label>Telephone<input type="tel" name="phone" value="<?= admin_e($prefillClient['phone']) ?>"></label>
            </div>
        </fieldset>

        <fieldset>
            <legend><span>02</span> Dates &amp; currency</legend>
            <div class="form-grid">
                <label>Issue date<input type="date" name="invoice_date" value="<?= gmdate('Y-m-d') ?>"></label>
                <label>Due date<input type="date" name="due_date" value="<?= gmdate('Y-m-d', strtotime('+7 days')) ?>"></label>
                <label>Currency
                    <select name="currency">
                        <?php $sourceCurrency = (string)($sourcePayload['currency'] ?? 'USD'); ?>
                        <?php foreach (['USD', 'KES', 'EUR', 'GBP'] as $currency): ?><option <?= $sourceCurrency === $currency ? 'selected' : '' ?>><?= $currency ?></option><?php endforeach; ?>
                    </select>
                </label>
            </div>
        </fieldset>

        <fieldset data-standard-invoice>
            <legend><span>03</span> Invoice items</legend>
            <div class="quotation-items" data-invoice-items>
                <?php foreach ($prefillItems as $item): if (!is_array($item)) continue; ?>
                <div class="quotation-item" data-invoice-item>
                    <label>Description<input type="text" name="item_description[]" value="<?= admin_e($item['description'] ?? '') ?>"></label>
                    <label>Qty<input type="number" name="item_quantity[]" min="0" step="1" value="<?= admin_e($item['quantity'] ?? '1') ?>"></label>
                    <label>Unit price<input type="number" name="item_price[]" min="0" step="0.01" value="<?= admin_e($item['unit_price'] ?? $item['total'] ?? '0') ?>"></label>
                    <button type="button" class="remove-item" data-remove-invoice-item aria-label="Remove item">×</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="text-action" data-add-invoice-item>+ Add another item</button>
            <div class="form-grid quotation-adjustments">
                <label>Discount<input type="number" name="discount" min="0" step="0.01" value="<?= admin_e($sourcePayload['discount'] ?? '0') ?>"></label>
                <label>Service fee<input type="number" name="service_fee" min="0" step="0.01" value="<?= admin_e($sourcePayload['service_fee'] ?? '0') ?>"></label>
            </div>
        </fieldset>

        <fieldset data-business-invoice hidden>
            <legend><span>03</span> Business matchmaking milestone</legend>
            <label>Invoice milestone
                <select name="business_milestone">
                    <option value="engagement">On acceptance — USD 250</option>
                    <option value="mid-project">Mid-project — USD 150</option>
                    <option value="final-delivery">Final delivery — USD 100</option>
                    <option value="full">Full professional fee — USD 500</option>
                </select>
            </label>
            <div class="matchmaking-summary compact">
                <article><span>Engagement</span><strong>USD 250</strong></article>
                <article><span>Mid-project</span><strong>USD 150</strong></article>
                <article><span>Final delivery</span><strong>USD 100</strong></article>
            </div>
        </fieldset>

        <fieldset>
            <legend><span>04</span> Payment position</legend>
            <div class="form-grid">
                <label>Amount already paid<input type="number" name="amount_paid" min="0" step="0.01" value="0"></label>
                <label>Invoice status<input type="text" name="invoice_status_display" readonly value="Issued"></label>
                <label>Grand total<input type="text" name="grand_total_display" readonly></label>
                <label>Balance due<input type="text" name="balance_display" readonly></label>
            </div>
            <label>Invoice note<textarea name="invoice_note" rows="3">Thank you for choosing Resplendent Global Travel Solutions. Please quote the invoice number when making payment.</textarea></label>
        </fieldset>

        <fieldset>
            <legend><span>05</span> Payment instructions</legend>
            <div class="payment-settings-summary">
                <?php if ($paymentSettings['bank_name'] !== ''): ?><span>Bank<strong><?= admin_e($paymentSettings['bank_name']) ?></strong></span><?php endif; ?>
                <?php if ($paymentSettings['account_name'] !== ''): ?><span>Account name<strong><?= admin_e($paymentSettings['account_name']) ?></strong></span><?php endif; ?>
                <?php if ($paymentSettings['account_number'] !== ''): ?><span>Account number<strong><?= admin_e($paymentSettings['account_number']) ?></strong></span><?php endif; ?>
                <?php if ($paymentSettings['mpesa_number'] !== ''): ?><span>M-Pesa<strong><?= admin_e($paymentSettings['mpesa_number']) ?></strong></span><?php endif; ?>
                <p><?= nl2br(admin_e($paymentSettings['instructions'])) ?></p>
            </div>
            <a class="text-action settings-link" href="settings.php">Update Payment Settings</a>
        </fieldset>

        <div class="generator-submit">
            <div><span>Balance due</span><strong data-form-balance>USD 0.00</strong></div>
            <button class="admin-button primary" type="submit">Generate Invoice</button>
        </div>
    </form>

    <aside class="live-preview-panel">
        <div class="preview-toolbar"><span>Live preview</span><button type="button" data-preview-expand>Full screen</button></div>
        <article class="document-preview invoice-preview" data-invoice-preview>
            <header>
                <img src="../assets/images/logo-r.png" alt="" width="54" height="54">
                <div><strong>RESPLENDENT</strong><small>GLOBAL TRAVEL SOLUTIONS</small></div>
                <span>Invoice</span>
            </header>
            <div class="document-title">
                <p data-preview-type><?= admin_e($invoiceType) ?></p>
                <h2 data-preview-client>Client Name</h2>
                <span data-preview-company></span>
            </div>
            <div class="document-meta"><span>Issued <strong data-preview-date><?= admin_e(date('j F Y')) ?></strong></span><span>Due <strong data-preview-due><?= admin_e(date('j F Y', strtotime('+7 days'))) ?></strong></span><span>Status <strong data-preview-status>Issued</strong></span></div>
            <table class="preview-quote-table"><thead><tr><th>Description</th><th>Qty</th><th>Amount</th></tr></thead><tbody data-preview-invoice-items></tbody></table>
            <section class="invoice-totals">
                <span>Total<strong data-preview-total>USD 0.00</strong></span>
                <span>Paid<strong data-preview-paid>USD 0.00</strong></span>
                <span>Balance<strong data-preview-balance>USD 0.00</strong></span>
            </section>
            <section><h3>Payment Instructions</h3><div class="preview-payment-details">
                <?php if ($paymentSettings['bank_name'] !== ''): ?><span>Bank<strong><?= admin_e($paymentSettings['bank_name']) ?></strong></span><?php endif; ?>
                <?php if ($paymentSettings['account_name'] !== ''): ?><span>Account name<strong><?= admin_e($paymentSettings['account_name']) ?></strong></span><?php endif; ?>
                <?php if ($paymentSettings['account_number'] !== ''): ?><span>Account number<strong><?= admin_e($paymentSettings['account_number']) ?></strong></span><?php endif; ?>
                <?php if ($paymentSettings['branch'] !== ''): ?><span>Branch<strong><?= admin_e($paymentSettings['branch']) ?></strong></span><?php endif; ?>
                <?php if ($paymentSettings['swift_iban'] !== ''): ?><span>SWIFT / IBAN<strong><?= admin_e($paymentSettings['swift_iban']) ?></strong></span><?php endif; ?>
                <?php if ($paymentSettings['mpesa_number'] !== ''): ?><span>M-Pesa<strong><?= admin_e($paymentSettings['mpesa_number']) ?></strong></span><?php endif; ?>
            </div><p><?= nl2br(admin_e($paymentSettings['instructions'])) ?></p></section>
            <section><p data-preview-note></p></section>
            <section class="preview-policy">
                <div data-policy-travel><h3>Important Terms</h3><p>Airfares, accommodation rates, taxes, availability and supplier conditions are indicative at the time of enquiry and may change until the required payment is received and the service is ticketed or formally confirmed in writing.</p></div>
                <div data-policy-business hidden><h3>Important Terms</h3><p>Non-refundable professional fees: Each milestone payment becomes non-refundable once the corresponding work has commenced, as it covers research, due diligence, outreach, coordination and professional time committed to the engagement. This does not affect any rights available where agreed services are not delivered or applicable law requires otherwise.</p><p>Payment of professional fees does not guarantee a successful introduction, transaction or commercial outcome.</p></div>
            </section>
            <footer><span>resplendentglobaltravel.com</span><span>Precision · Discretion · Purpose</span></footer>
        </article>
    </aside>
</div>
<?php admin_page_footer(['assets/invoice.js']); ?>
