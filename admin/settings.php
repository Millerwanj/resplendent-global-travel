<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
admin_require_auth();

$store = admin_store();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_verify_csrf($_POST['csrf'] ?? null)) {
        admin_flash('error', 'The secure session expired. Please try again.');
    } else {
        $paymentLink = trim((string)($_POST['payment_link'] ?? ''));
        if (
            $paymentLink !== ''
            && (
                !filter_var($paymentLink, FILTER_VALIDATE_URL)
                || strtolower((string)parse_url($paymentLink, PHP_URL_SCHEME)) !== 'https'
            )
        ) {
            admin_flash('error', 'Enter a complete payment-link URL beginning with https://, or leave it blank.');
        } else {
            $store->savePaymentSettings($_POST);
            admin_flash('success', 'Payment settings saved. New invoices will use these details.');
        }
    }
    header('Location: settings.php', true, 303);
    exit;
}

$settings = $store->getPaymentSettings();
admin_page_header('Payment Settings', 'settings');
?>
<div class="settings-layout">
    <section class="admin-panel settings-intro">
        <p class="eyebrow">Private Configuration</p>
        <h2>Update payment details without changing code.</h2>
        <p>RTGS is the preferred live method. Online checkout and contactless providers remain optional channels and should only be enabled after merchant approval and production verification.</p>
        <div class="settings-assurance">
            <strong>Historical accuracy</strong>
            <p>Each invoice stores a snapshot of the payment details used when it was issued. Updating this page changes future invoices only.</p>
        </div>
    </section>
    <form method="post" class="admin-panel admin-form settings-form">
        <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>">
        <fieldset>
            <legend>Payment priority</legend>
            <div class="form-grid">
                <label>Preferred client method
                    <select name="primary_method">
                        <option value="rtgs" <?= $settings['primary_method'] === 'rtgs' ? 'selected' : '' ?>>RTGS bank transfer</option>
                        <option value="online" <?= $settings['primary_method'] === 'online' ? 'selected' : '' ?>>Secure online checkout</option>
                        <option value="contactless" <?= $settings['primary_method'] === 'contactless' ? 'selected' : '' ?>>Contactless terminal</option>
                        <option value="other" <?= $settings['primary_method'] === 'other' ? 'selected' : '' ?>>Other approved method</option>
                    </select>
                </label>
            </div>
        </fieldset>
        <fieldset>
            <legend>RTGS bank details</legend>
            <div class="form-grid">
                <label>Bank name<input type="text" name="bank_name" value="<?= admin_e($settings['bank_name']) ?>"></label>
                <label>Account name<input type="text" name="account_name" value="<?= admin_e($settings['account_name']) ?>"></label>
                <label>Account number<input type="text" name="account_number" value="<?= admin_e($settings['account_number']) ?>" autocomplete="off"></label>
                <label>Branch<input type="text" name="branch" value="<?= admin_e($settings['branch']) ?>"></label>
                <label>SWIFT / IBAN<input type="text" name="swift_iban" value="<?= admin_e($settings['swift_iban']) ?>" autocomplete="off"></label>
                <label>Account currency<input type="text" name="currency" value="<?= admin_e($settings['currency']) ?>" placeholder="e.g. KES or USD"></label>
            </div>
        </fieldset>
        <fieldset>
            <legend>Alternative payment channels</legend>
            <div class="form-grid">
                <label>M-Pesa account name<input type="text" name="mpesa_name" value="<?= admin_e($settings['mpesa_name']) ?>"></label>
                <label>M-Pesa number / till<input type="text" name="mpesa_number" value="<?= admin_e($settings['mpesa_number']) ?>" autocomplete="off"></label>
                <label>Online payment provider<input type="text" name="online_provider" value="<?= admin_e($settings['online_provider']) ?>" placeholder="Provider name after approval"></label>
                <label>Online checkout status
                    <select name="online_enabled">
                        <option value="0" <?= $settings['online_enabled'] !== '1' ? 'selected' : '' ?>>Not enabled</option>
                        <option value="1" <?= $settings['online_enabled'] === '1' ? 'selected' : '' ?>>Enabled and production-verified</option>
                    </select>
                </label>
                <label>Contactless terminal provider<input type="text" name="terminal_provider" value="<?= admin_e($settings['terminal_provider']) ?>" placeholder="e.g. Sabi or another provider"></label>
                <label>Contactless status
                    <select name="terminal_enabled">
                        <option value="0" <?= $settings['terminal_enabled'] !== '1' ? 'selected' : '' ?>>Not enabled</option>
                        <option value="1" <?= $settings['terminal_enabled'] === '1' ? 'selected' : '' ?>>Enabled and reconciled</option>
                    </select>
                </label>
                <label class="full-span">Secure payment link<input type="url" name="payment_link" value="<?= admin_e($settings['payment_link']) ?>" placeholder="https://"></label>
            </div>
        </fieldset>
        <fieldset>
            <legend>Invoice instruction</legend>
            <label>Payment note<textarea name="instructions" rows="4"><?= admin_e($settings['instructions']) ?></textarea></label>
        </fieldset>
        <button class="admin-button primary" type="submit">Save Payment Settings</button>
    </form>
</div>
<?php admin_page_footer(); ?>
