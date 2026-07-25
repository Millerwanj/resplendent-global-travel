<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
admin_require_auth();

$store = admin_store();
$clientReference = admin_text($_GET['client'] ?? $_POST['client_reference'] ?? '', 40);
$client = $clientReference !== '' ? $store->getClient($clientReference) : null;
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'The secure session expired. Please refresh and try again.';
    } else {
        $payload = json_decode((string)($_POST['document_payload'] ?? ''), true);
        if (!is_array($payload)) {
            $error = 'The quotation data could not be read.';
        } elseif (admin_text($payload['client_name'] ?? '', 120) === '' || empty($payload['items'])) {
            $error = 'Add the client name and at least one quotation item.';
        } else {
            $record = $store->saveDocument('quotation', $payload);
            admin_flash('success', 'Quotation ' . (string)$record['number'] . ' is ready.');
            header('Location: document.php?id=' . rawurlencode((string)$record['id']), true, 303);
            exit;
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
        <input type="hidden" name="document_payload" data-document-payload>
        <div class="generator-intro">
            <div><p class="eyebrow">Executive Document</p><h2>Create a clear, decision-ready quotation.</h2></div>
            <p>Use the travel layout for itemised services or switch to the fixed business-matchmaking fee schedule.</p>
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
                <div class="quotation-item" data-quotation-item>
                    <label>Description<input type="text" name="item_description[]" value="Professional travel service"></label>
                    <label>Qty<input type="number" name="item_quantity[]" min="0" step="1" value="1"></label>
                    <label>Unit price<input type="number" name="item_price[]" min="0" step="0.01" value="0"></label>
                    <button type="button" class="remove-item" data-remove-item aria-label="Remove item">×</button>
                </div>
            </div>
            <button type="button" class="text-action" data-add-item>+ Add another item</button>
            <div class="form-grid quotation-adjustments">
                <label>Discount<input type="number" name="discount" min="0" step="0.01" value="0"></label>
                <label>Service fee<input type="number" name="service_fee" min="0" step="0.01" value="0"></label>
            </div>
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
            <div><span>Grand total</span><strong data-form-total>USD 0.00</strong></div>
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
            <footer><span>resplendentglobaltravel.com</span><span>Precision · Discretion · Purpose</span></footer>
        </article>
    </aside>
</div>
<?php admin_page_footer(['assets/quotation.js']); ?>
