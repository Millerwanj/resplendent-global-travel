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
            $error = 'The proposal data could not be read.';
        } elseif (admin_text($payload['client_name'] ?? '', 120) === '' || admin_text($payload['proposal_type'] ?? '', 80) === '') {
            $error = 'Add the client name and select a proposal type.';
        } else {
            $record = $store->saveDocument('proposal', $payload);
            admin_flash('success', 'Proposal ' . (string)$record['number'] . ' is ready.');
            header('Location: document.php?id=' . rawurlencode((string)$record['id']), true, 303);
            exit;
        }
    }
}

admin_page_header('Proposal Generator', 'proposal');
?>
<?php if ($error !== ''): ?><div class="admin-alert error" role="alert"><?= admin_e($error) ?></div><?php endif; ?>
<div class="generator-shell" data-proposal-generator>
    <form method="post" class="generator-form admin-form" data-generator-form>
        <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>">
        <input type="hidden" name="client_reference" value="<?= admin_e($clientReference) ?>">
        <input type="hidden" name="document_payload" data-document-payload>
        <div class="generator-intro">
            <div><p class="eyebrow">Executive Document</p><h2>Build a concise, client-ready proposal.</h2></div>
            <p>The preview updates as you work. Recommendations are intentionally limited to two sentences.</p>
        </div>

        <fieldset>
            <legend><span>01</span> Client &amp; proposal</legend>
            <div class="form-grid">
                <label>Proposal type
                    <select name="proposal_type" required data-proposal-type>
                        <option value="Luxury Travel">Luxury Travel</option>
                        <option value="Corporate Travel">Corporate Travel</option>
                        <option value="Global Business Connections">Global Business Connections</option>
                    </select>
                </label>
                <label>Proposal date<input type="date" name="proposal_date" value="<?= gmdate('Y-m-d') ?>"></label>
                <label>Client name<input type="text" name="client_name" required value="<?= admin_e($client['name'] ?? '') ?>"></label>
                <label>Company<input type="text" name="company" value="<?= admin_e($client['company'] ?? '') ?>"></label>
                <label>Email<input type="email" name="email" value="<?= admin_e($client['email'] ?? '') ?>"></label>
                <label>Telephone<input type="tel" name="phone" value="<?= admin_e($client['phone'] ?? '') ?>"></label>
            </div>
            <label>Client requirements <small>Internal working note; not shown on the proposal</small>
                <textarea name="internal_requirements" rows="3"><?= admin_e($client['message'] ?? '') ?></textarea>
            </label>
            <label>Executive introduction
                <textarea name="introduction" rows="3">Thank you for the opportunity to consider your requirements. We have selected the following approach to deliver a seamless, carefully managed experience.</textarea>
            </label>
        </fieldset>

        <fieldset data-travel-section>
            <legend><span>02</span> Flight recommendation</legend>
            <div class="form-grid">
                <label>Airline<input type="text" name="airline" placeholder="e.g. Emirates"></label>
                <label>Route<input type="text" name="route" placeholder="Nairobi — Dubai — Nairobi"></label>
                <label>Cabin<input type="text" name="cabin" placeholder="Business Class"></label>
                <label>Flight investment<input type="number" min="0" step="0.01" name="flight_price" placeholder="0.00"></label>
            </div>
            <label>Why we selected this option
                <textarea name="flight_recommendation" rows="2" maxlength="360" data-sentence-limit>This routing balances schedule quality, comfort and reliable connections while preserving flexibility for the client.</textarea>
                <small data-sentence-message></small>
            </label>
        </fieldset>

        <fieldset data-travel-section>
            <legend><span>03</span> Accommodation recommendation</legend>
            <div class="form-grid">
                <label>Hotel<input type="text" name="hotel"></label>
                <label>Room<input type="text" name="room" placeholder="Deluxe King"></label>
                <label>Meal plan<input type="text" name="meal_plan" placeholder="Breakfast included"></label>
                <label>Nights<input type="number" min="0" step="1" name="nights"></label>
                <label>Accommodation investment<input type="number" min="0" step="0.01" name="hotel_price" placeholder="0.00"></label>
            </div>
            <label>Why we selected this option
                <textarea name="hotel_recommendation" rows="2" maxlength="360" data-sentence-limit>The property offers the right balance of location, service and quiet comfort for this itinerary.</textarea>
                <small data-sentence-message></small>
            </label>
        </fieldset>

        <fieldset data-business-section hidden>
            <legend><span>02</span> Business connection mandate</legend>
            <div class="form-grid">
                <label>Market / geography<input type="text" name="target_market" placeholder="Country or region"></label>
                <label>Target partner profile<input type="text" name="partner_profile" placeholder="Distributor, investor, supplier…"></label>
                <label class="full-span">Engagement objective<input type="text" name="engagement_objective" placeholder="What a successful connection should achieve"></label>
            </div>
            <label>Our proposed approach<textarea name="business_approach" rows="4">We will refine the mandate, research suitable counterparties, conduct a qualified outreach process and coordinate the most relevant introductions.</textarea></label>
            <div class="milestone-note">
                <strong>Fixed professional fee: USD 500</strong>
                <span>USD 250 on engagement · USD 150 mid-project · USD 100 on final delivery</span>
            </div>
        </fieldset>

        <fieldset>
            <legend><span>04</span> Included services</legend>
            <div class="checkbox-grid" data-included-services>
                <label><input type="checkbox" value="Flight planning and reservation" checked><span>Flight planning and reservation</span></label>
                <label><input type="checkbox" value="Accommodation coordination" checked><span>Accommodation coordination</span></label>
                <label><input type="checkbox" value="Itinerary management"><span>Itinerary management</span></label>
                <label><input type="checkbox" value="Traveller support"><span>Traveller support</span></label>
                <label><input type="checkbox" value="Qualified partner research"><span>Qualified partner research</span></label>
                <label><input type="checkbox" value="Introduction coordination"><span>Introduction coordination</span></label>
            </div>
            <label>Additional included services<input type="text" name="additional_services" placeholder="Separate additional items with commas"></label>
        </fieldset>

        <fieldset>
            <legend><span>05</span> Investment summary</legend>
            <div class="form-grid">
                <label>Currency
                    <select name="currency"><option>USD</option><option>KES</option><option>EUR</option><option>GBP</option></select>
                </label>
                <label>Additional services / fees<input type="number" min="0" step="0.01" name="additional_price" value="0"></label>
                <label data-service-fee-field>Professional service fee <small>Standard: 5% of flight and hotel value</small><input type="number" min="0" step="0.01" name="service_fee" value="0" data-auto-service-fee></label>
                <label>Grand total<input type="text" name="grand_total_display" readonly value="USD 0.00"></label>
            </div>
            <label>Investment note<textarea name="investment_note" rows="2">All services are subject to availability at the time of confirmation. Final reservations proceed once the proposal is accepted and the required payment is received.</textarea></label>
        </fieldset>
        <div class="generator-submit">
            <button class="admin-button primary" type="submit">Generate Proposal</button>
            <span>The final document opens in a print-ready view for saving as PDF.</span>
        </div>
    </form>

    <aside class="live-preview-panel">
        <div class="preview-toolbar"><span>Live preview</span><button type="button" data-preview-expand>Full screen</button></div>
        <article class="document-preview" data-proposal-preview>
            <header>
                <img src="../assets/images/logo-r.png" alt="" width="54" height="54">
                <div><strong>RESPLENDENT</strong><small>GLOBAL TRAVEL SOLUTIONS</small></div>
                <span>Executive Proposal</span>
            </header>
            <div class="document-title">
                <p data-preview-type>Luxury Travel</p>
                <h2 data-preview-client>Client Name</h2>
                <span data-preview-company></span>
            </div>
            <div class="document-meta"><span>Prepared <strong data-preview-date><?= admin_e(date('j F Y')) ?></strong></span><span>Proposal <strong>Generated on confirmation</strong></span></div>
            <p class="document-intro" data-preview-introduction></p>
            <div data-preview-travel>
                <section><h3>Flight Recommendation</h3><div class="preview-specs"><span>Airline<strong data-preview-airline>To be confirmed</strong></span><span>Route<strong data-preview-route>To be confirmed</strong></span><span>Cabin<strong data-preview-cabin>To be confirmed</strong></span></div><p data-preview-flight-recommendation></p></section>
                <section><h3>Accommodation</h3><div class="preview-specs"><span>Hotel<strong data-preview-hotel>To be confirmed</strong></span><span>Room &amp; plan<strong data-preview-room>To be confirmed</strong></span><span>Stay<strong data-preview-nights>To be confirmed</strong></span></div><p data-preview-hotel-recommendation></p></section>
            </div>
            <section data-preview-business hidden><h3>Business Connection Mandate</h3><div class="preview-specs"><span>Market<strong data-preview-market>To be confirmed</strong></span><span>Partner profile<strong data-preview-partner>To be confirmed</strong></span></div><p data-preview-business-approach></p></section>
            <section><h3>Included Services</h3><ul data-preview-services><li>Flight planning and reservation</li><li>Accommodation coordination</li></ul></section>
            <section class="preview-investment"><div><span>Investment</span><strong data-preview-total>USD 0.00</strong></div><p data-preview-investment-note></p></section>
            <footer><span>resplendentglobaltravel.com</span><span>Precision · Discretion · Purpose</span></footer>
        </article>
    </aside>
</div>
<?php admin_page_footer(['assets/proposal.js']); ?>
