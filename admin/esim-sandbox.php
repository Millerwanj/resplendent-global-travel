<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/EsimCard/EsimCardClient.php';

use Resplendent\EsimCard\EsimCardClient;

admin_require_auth();
$config = admin_esimcard_config();
$configured = is_array($config) && !empty($config['email']) && !empty($config['password']);
$balance = null;
$packages = null;
$purchase = null;
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Your session token expired. Refresh and try again.';
    } elseif (!$configured) {
        $error = 'Add the private eSIMCard configuration before testing.';
    } else {
        try {
            $client = new EsimCardClient($config);
            $action = admin_text($_POST['action'] ?? '', 40);
            if ($action === 'connection') {
                $balance = $client->balance();
                $packages = $client->packages('DATA-ONLY');
            } elseif ($action === 'purchase') {
                if (($config['environment'] ?? 'sandbox') !== 'sandbox' || empty($config['allow_sandbox_purchase'])) {
                    throw new RuntimeException('Sandbox purchases are disabled in the private configuration.');
                }
                $packageId = admin_text($_POST['package_type_id'] ?? '', 100);
                $purchase = $client->purchaseDataPackage($packageId, '', true);
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

function esim_summary(mixed $value): string
{
    if (!is_array($value)) return '';
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '';
}

admin_page_header('eSIM Sandbox', 'esim');
?>
<section class="admin-panel">
    <div class="panel-heading"><div><p class="eyebrow">eSIMCard integration</p><h2>Private test environment</h2></div></div>
    <p>This screen calls only the configured sandbox API. Credentials remain in the private server configuration and are never sent to the browser.</p>
    <?php if (!$configured): ?>
        <div class="admin-alert error">Configuration required: copy <strong>docs/rgts-esimcard-config.example.php</strong> to <strong>/home/resplend/rgts-esimcard-config.php</strong> and add the sandbox password Umar supplied.</div>
    <?php endif; ?>
    <?php if ($error !== ''): ?><div class="admin-alert error" role="alert"><?= admin_e($error) ?></div><?php endif; ?>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>">
        <input type="hidden" name="action" value="connection">
        <button class="admin-button primary" type="submit" <?= !$configured ? 'disabled' : '' ?>>Test Login, Balance &amp; Packages</button>
    </form>
</section>

<?php if ($balance !== null || $packages !== null): ?>
<div class="admin-two-column" style="margin-top:26px">
    <section class="admin-panel"><div class="panel-heading"><h2>Wallet response</h2></div><pre class="api-response"><?= admin_e(esim_summary($balance)) ?></pre></section>
    <section class="admin-panel"><div class="panel-heading"><h2>Package response</h2></div><pre class="api-response"><?= admin_e(esim_summary($packages)) ?></pre></section>
</div>
<?php endif; ?>

<section class="admin-panel" style="margin-top:26px">
    <div class="panel-heading"><div><p class="eyebrow">Controlled provisioning</p><h2>Test one DATA-ONLY package</h2></div></div>
    <p>Use a package type ID returned by the package test above. This action includes <strong>test=true</strong> and remains locked unless <strong>allow_sandbox_purchase</strong> is explicitly enabled.</p>
    <form method="post" class="admin-form form-grid">
        <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>">
        <input type="hidden" name="action" value="purchase">
        <label class="full-span">Package type ID<input name="package_type_id" required maxlength="100" autocomplete="off"></label>
        <div class="full-span"><button class="admin-button secondary" type="submit" <?= !$configured ? 'disabled' : '' ?>>Run Sandbox Purchase</button></div>
    </form>
    <?php if ($purchase !== null): ?><pre class="api-response"><?= admin_e(esim_summary($purchase)) ?></pre><?php endif; ?>
</section>
<?php admin_page_footer(); ?>
