<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
admin_require_auth();

$store = admin_store();
$revisionId = admin_text($_GET['revision'] ?? $_POST['revision_of_id'] ?? '', 100);
$revisionSource = $revisionId !== '' ? $store->getDocument($revisionId) : null;
if ($revisionSource && ($revisionSource['type'] ?? '') !== 'quotation') $revisionSource = null;
$revisionPayload = is_array($revisionSource['payload'] ?? null) ? $revisionSource['payload'] : [];
if ($revisionPayload !== []) {
    $revisionPayload['quotation_date'] = gmdate('Y-m-d');
    $revisionPayload['valid_until'] = gmdate('Y-m-d', strtotime('+7 days'));
}
$revisionItems = is_array($revisionPayload['items'] ?? null) ? $revisionPayload['items'] : [];
if ($revisionItems === []) {
    $revisionItems = [['description' => 'Professional travel service', 'quantity' => '1', 'supplier_cost' => '0.00', 'unit_price' => '0.00']];
}
$clientReference = admin_text(
    $_GET['client'] ?? $_POST['client_reference'] ?? $revisionSource['client_reference'] ?? '',
    40
);
$client = $clientReference !== '' ? $store->getClient($clientReference) : null;
$error = '';

/** @param array<string,mixed> $payload @return array{payload:array<string,mixed>,error:string} */
function quotation_apply_margin_policy(array $payload): array
{
    if (($payload['quotation_type'] ?? '') === 'Business Matchmaking') {
        return ['payload' => $payload, 'error' => ''];
    }

    $subtotal = 0.0;
    $supplierCost = 0.0;
    foreach ((array)($payload['items'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $quantity = max(0, (float)($item['quantity'] ?? 0));
        $unitPrice = max(0, (float)($item['unit_price'] ?? 0));
        $unitCost = max(0, (float)($item['supplier_cost'] ?? 0));
        $subtotal += $quantity * $unitPrice;
        $supplierCost += $quantity * $unitCost;
    }
    $discount = max(0, (float)($payload['discount'] ?? 0));
    $serviceFee = max(0, (float)($payload['service_fee'] ?? 0));
    $clientTotal = max(0, $subtotal - $discount + $serviceFee);
    $minimumClientTotal = $supplierCost > 0 ? $supplierCost / 0.72 : 0.0;
    if ($supplierCost > 0 && $clientTotal + 0.005 < $minimumClientTotal) {
        $currency = admin_text($payload['currency'] ?? 'USD', 3);
        return [
            'payload' => $payload,
            'error' => 'This quotation is below the approved 25% base-margin floor. The minimum client total is '
                . $currency . ' ' . number_format($minimumClientTotal, 2) . '.',
        ];
    }

    $grossMargin = $clientTotal > 0 ? (($clientTotal - $supplierCost) / $clientTotal) * 100 : 0.0;
    $payload['subtotal'] = number_format($subtotal, 2, '.', '');
    $payload['grand_total'] = admin_text($payload['currency'] ?? 'USD', 3) . ' ' . number_format($clientTotal, 2);
    $payload['internal_supplier_cost'] = number_format($supplierCost, 2, '.', '');
    $payload['minimum_client_total'] = number_format($minimumClientTotal, 2, '.', '');
    $payload['base_margin_after_allowance'] = number_format(max(0, $grossMargin - 3), 2, '.', '');
    $payload['pricing_policy_version'] = '2026-08-10';
    return ['payload' => $payload, 'error' => ''];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'The secure session expired. Please refresh and try again.';
    } else {
        $payload = json_decode((string)($_POST['document_payload'] ?? ''), true);
        if (!is_array($payload)) {
            $error = 'The quotation data could not be read.';
        } elseif (admin_text($payload['client_name'] ?? '', 120) === '' || empty($payload['items'])) {
            $error = 'Add the client name and at least one quotation item.';
        } elseif (
            ($payload['quotation_type'] ?? '') !== 'Business Matchmaking'
            && ($_POST['cost_basis_confirmed'] ?? '') !== '1'
        ) {
            $error = 'Confirm that the supplier-cost basis is complete before generating the quotation.';
        } else {
            $marginResult = quotation_apply_margin_policy($payload);
            if ($marginResult['error'] !== '') {
                $error = $marginResult['error'];
            } else {
                $payload = $marginResult['payload'];
                $payload['revision_of_id'] = admin_text($_POST['revision_of_id'] ?? '', 100);
                $record = $store->saveDocument('quotation', $payload);
                admin_flash('success', 'Quotation ' . (string)$record['number'] . ' is ready.');
                header('Location: document.php?id=' . rawurlencode((string)$record['id']), true, 303);
                exit;
            }
        }
    }
}

admin_page_header('Quotation Generator', 'quotation');
?>
<?php if ($error !== ''): ?><div class="admin-alert error" role="alert"><?= admin_e($error) ?></div><?php endif; ?>
<div class="generator-shell" data-quotation-generator>
    <form method="post" class="generator-form admin-form" data-quotation-form>
        <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>">
        <input type="hidden" name="client_reference" value="<?= admin_e($clientReference) ?>">
        <input type="hidden" name="revision_of_id" value="<?= admin_e($revisionSource['id'] ?? '') ?>">
        <input type="hidden" name="document_payload" data-document-payload>
        <?php if ($revisionPayload !== []): ?>
            <script type="application/json" data-revision-payload><?= json_encode($revisionPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
        <?php endif; ?>
        <div class="generator-intro">
            <div><p class="eyebrow"><?= $revisionSource ? 'Controlled Revision' : 'Executive Document' ?></p><h2><?= $revisionSource ? 'Revise ' . admin_e($revisionSource['number'] ?? 'quotation') . '.' : 'Create a clear, decision-ready quotation.' ?></h2></div>
            <p><?= $revisionSource ? 'The new version retains the original reference and receives the next revision number.' : 'Use the travel layout for itemised services or switch to the fixed business-matchmaking fee schedule.' ?></p>
        </div>

        <fieldset>
            <legend><span>01</span> Client &amp; quotation</legend>
            <div class="form-grid">
                <label>Quotation type
                    <select name="quotation_type" data-quotation-type>
                        <option value="Travel & Services">Travel &amp; Services</option>
                        <option value="Corporate Travel">Corporate Travel</option>
                        <option value="Business Matchmaking">Business Matchmaking</option>
                    </select>
                </label>
                <label>Quotation date<input type="date" name="quotation_date" value="<?= gmdate('Y-m-d') ?>"></label>
                <label>Valid until<input type="date" name="valid_until" value="<?= gmdate('Y-m-d', strtotime('+7 days')) ?>"></label>
                <label>Currency
                    <select name="currency"><option>USD</option><option>KES</option><option>EUR</option><option>GBP</option></select>
                </label>
                <label>Client name<input type="text" name="client_name" required value="<?= admin_e($client['name'] ?? '') ?>"></label>
                <label>Company<input type="text" name="company" value="<?= admin_e($client['company'] ?? '') ?>"></label>
                <label>Email<input type="email" name="email" value="<?= admin_e($client['email'] ?? '') ?>"></label>
                <label>Telephone<input type="tel" name="phone" value="<?= admin_e($client['phone'] ?? '') ?>"></label>
            </div>
        </fieldset>

        <fieldset data-travel-quotation>
            <legend><span>02</span> Quotation items</legend>
            <div class="quotation-items" data-quotation-items>
                <?php foreach ($revisionItems as $item): if (!is_array($item)) continue; ?>
                <div class="quotation-item" data-quotation-item>
                    <label>Description<input type="text" name="item_description[]" value="<?= admin_e($item['description'] ?? '') ?>"></label>
                    <label>Qty<input type="number" name="item_quantity[]" min="0" step="1" value="<?= admin_e($item['quantity'] ?? '1') ?>"></label>
                    <label>Supplier cost / unit<input type="number" name="item_cost[]" min="0" step="0.01" value="<?= admin_e($item['supplier_cost'] ?? '0') ?>"></label>
                    <label>Unit price<input type="number" name="item_price[]" min="0" step="0.01" value="<?= admin_e($item['unit_price'] ?? $item['total'] ?? '0') ?>"></label>
                    <button type="button" class="remove-item" data-remove-item aria-label="Remove item">×</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="text-action" data-add-item>+ Add another item</button>
            <div class="form-grid quotation-adjustments">
                <label>Discount<input type="number" name="discount" min="0" step="0.01" value="0"></label>
                <label>Service fee<input type="number" name="service_fee" min="0" step="0.01" value="0"></label>
            </div>
            <label class="checkbox-card"><input type="checkbox" name="cost_basis_confirmed" value="1" required data-cost-basis-confirmed> I confirm that all supplier costs are entered, using zero only where there is no external supplier cost.</label>
        </fieldset>

        <fieldset data-business-quotation hidden>
            <legend><span>02</span> Business matchmaking fee</legend>
            <div class="matchmaking-summary">
                <article><span>On acceptance</span><strong>USD 250</strong><p>Engagement, mandate refinement and commencement of research.</p></article>
                <article><span>Mid-project</span><strong>USD 150</strong><p>Qualified research progress and outreach milestone.</p></article>
                <article><span>Final delivery</span><strong>USD 100</strong><p>Delivery of the agreed introductions or final engagement output.</p></article>
            </div>
            <p class="milestone-total">Total professional fee <strong>USD 500</strong></p>
        </fieldset>

        <fieldset>
            <legend><span>03</span> Terms &amp; payment</legend>
            <label>Scope note<textarea name="scope_note" rows="3">This quotation covers the services listed above and is based on the information currently available. Any material change in scope will be confirmed before additional costs are incurred.</textarea></label>
            <label>Payment terms<textarea name="payment_terms" rows="3">Reservations and third-party services are confirmed upon receipt of the required funds. Supplier prices and availability remain subject to change until ticketed or formally confirmed.</textarea></label>
            <label>Payment details<textarea name="payment_details" rows="3" placeholder="Add approved business banking or payment instructions when available.">Payment instructions will be provided on acceptance.</textarea></label>
        </fieldset>
        <div class="generator-submit">
            <div><span>Grand total</span><strong data-form-total>USD 0.00</strong><small data-margin-status>Enter supplier costs to validate the internal pricing floor.</small></div>
            <button class="admin-button primary" type="submit">Generate Quotation</button>
        </div>
    </form>

    <aside class="live-preview-panel">
        <div class="preview-toolbar"><span>Live preview</span><button type="button" data-preview-expand>Full screen</button></div>
        <article class="document-preview quotation-preview" data-quotation-preview>
            <header>
                <img src="../assets/images/logo-r.png" alt="" width="54" height="54">
                <div><strong>RESPLENDENT</strong><small>GLOBAL TRAVEL SOLUTIONS</small></div>
                <span>Executive Quotation</span>
            </header>
            <div class="document-title">
                <p data-preview-type>Travel &amp; Services</p>
                <h2 data-preview-client>Client Name</h2>
                <span data-preview-company></span>
            </div>
            <div class="document-meta"><span>Prepared <strong data-preview-date><?= admin_e(date('j F Y')) ?></strong></span><span>Valid until <strong data-preview-valid><?= admin_e(date('j F Y', strtotime('+7 days'))) ?></strong></span></div>
            <div data-preview-travel-quote>
                <table class="preview-quote-table"><thead><tr><th>Description</th><th>Qty</th><th>Amount</th></tr></thead><tbody data-preview-items></tbody></table>
            </div>
            <div data-preview-business-quote hidden>
                <table class="preview-quote-table"><thead><tr><th>Milestone</th><th>Amount</th></tr></thead><tbody><tr><td>On acceptance — engagement and research</td><td>USD 250.00</td></tr><tr><td>Mid-project milestone</td><td>USD 150.00</td></tr><tr><td>Final delivery</td><td>USD 100.00</td></tr></tbody></table>
            </div>
            <section class="quote-total"><span>Total investment</span><strong data-preview-total>USD 0.00</strong></section>
            <section><h3>Terms</h3><p data-preview-payment-terms></p></section>
            <section><h3>Payment</h3><p data-preview-payment-details></p></section>
            <section class="preview-policy">
                <div data-policy-travel><h3>Important Terms</h3><p>Airfares, accommodation rates, taxes, availability and supplier conditions are indicative at the time of enquiry and may change until the required payment is received and the service is ticketed or formally confirmed in writing.</p><p>Unless expressly stated otherwise, the client total includes applicable taxes, statutory charges and Resplendent service fees. VAT is included only where legally applicable.</p><p>RTGS bank transfer is the preferred payment method unless the invoice offers another authorised channel. Payment receipt does not by itself confirm supplier space, permits or ticketing.</p></div>
                <div data-policy-business hidden><h3>Important Terms</h3><p>Non-refundable professional fees: Each milestone payment becomes non-refundable once the corresponding work has commenced, as it covers research, due diligence, outreach, coordination and professional time committed to the engagement. This does not affect any rights available where agreed services are not delivered or applicable law requires otherwise.</p><p>Payment of professional fees does not guarantee a successful introduction, transaction or commercial outcome.</p></div>
            </section>
            <footer><span>resplendentglobaltravel.com</span><span>Precision · Discretion · Purpose</span></footer>
        </article>
    </aside>
</div>
<?php admin_page_footer(['assets/quotation.js']); ?>
