<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/Payments/Providers/PesapalProvider.php';

use Resplendent\Payments\Providers\PesapalProvider;

admin_require_auth();

$config = admin_payment_config();
$pesapal = is_array($config['providers']['pesapal'] ?? null)
    ? $config['providers']['pesapal']
    : [];

$environment = strtolower(trim((string)($pesapal['environment'] ?? '')));

$envConfig = is_array($pesapal['environments'][$environment] ?? null)
    ? $pesapal['environments'][$environment]
    : [];

$effective = $envConfig !== []
    ? array_replace($pesapal, $envConfig)
    : $pesapal;

$configured =
    trim((string)($effective['consumer_key'] ?? '')) !== ''
    && trim((string)($effective['consumer_secret'] ?? '')) !== '';

$notificationId = trim((string)($effective['notification_id'] ?? ''));

$result = null;
$checkout = null;
$statusResult = null;
$statusTrackingId = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Your session token expired. Refresh and try again.';
    } elseif ($environment !== 'sandbox') {
        $error = 'This test screen is locked unless the active Pesapal environment is sandbox.';
    } elseif (!$configured) {
        $error = 'Sandbox consumer key and secret are not configured in the private payment file.';
    } else {
        try {
            $provider = new PesapalProvider($pesapal);
            $action = admin_text($_POST['action'] ?? '', 30);

            if ($action === 'register_ipn') {
                $ipnUrl = trim((string)($pesapal['ipn_url'] ?? ''));

                if ($ipnUrl === '') {
                    throw new RuntimeException('Pesapal IPN URL is not configured.');
                }

                $result = $provider->registerIpn($ipnUrl, 'POST');
            }

            if ($action === 'create_checkout') {
                if ($notificationId === '') {
                    throw new RuntimeException(
                        'Sandbox notification_id is not configured. Complete Step 1 first.'
                    );
                }

                $reference = 'RGTS-SANDBOX-' . date('Ymd-His');

                $payment = [
                    'merchant_reference' => $reference,
                    'currency' => 'USD',
                    'amount' => 1.00,
                    'description' => 'Resplendent Pesapal sandbox payment test',
                    'billing_address' => [
                        'email_address' => 'info@resplendentglobaltravel.com',
                        'phone_number' => '',
                        'country_code' => 'KE',
                        'first_name' => 'Resplendent',
                        'middle_name' => '',
                        'last_name' => 'Sandbox',
                        'line_1' => '',
                        'line_2' => '',
                        'city' => 'Nairobi',
                        'state' => '',
                        'postal_code' => '',
                        'zip_code' => '',
                    ],
                ];

                $urls = [
                    'callback_url' =>
                        'https://www.resplendentglobaltravel.com/api/payments/callback.php',
                    'cancellation_url' =>
                        'https://www.resplendentglobaltravel.com/payments.html?cancelled=1',
                ];

                $checkout = $provider->createCheckout($payment, $urls);
            }

            if ($action === 'check_status') {
                $statusTrackingId = trim((string)($_POST['tracking_id'] ?? ''));

                if ($statusTrackingId === '') {
                    throw new RuntimeException('Enter the Pesapal tracking ID to check.');
                }

                if (
                    !preg_match(
                        '/^[A-Za-z0-9-]{10,100}$/',
                        $statusTrackingId
                    )
                ) {
                    throw new RuntimeException('The Pesapal tracking ID format is invalid.');
                }

                $statusResult = $provider->verifyTransaction($statusTrackingId);
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

function payment_mask(bool $present): string
{
    return $present ? 'Configured' : 'Missing';
}

function payment_json(mixed $value): string
{
    return is_array($value)
        ? (json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '')
        : '';
}

admin_page_header('PesaPal Sandbox', 'payments');
?>

<section class="admin-panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">PesaPal API 3.0</p>
            <h2>Sandbox environment</h2>
        </div>
    </div>

    <p>
        This private screen never prints your consumer key or secret.
        It is locked to the PesaPal sandbox environment.
    </p>

    <?php if ($error !== ''): ?>
        <div class="admin-alert error" role="alert">
            <?= admin_e($error) ?>
        </div>
    <?php endif; ?>

    <div class="payment-settings-summary">
        <span>
            Environment
            <strong><?= admin_e($environment !== '' ? $environment : 'Not configured') ?></strong>
        </span>

        <span>
            Provider enabled
            <strong><?= !empty($pesapal['enabled']) ? 'Yes' : 'No' ?></strong>
        </span>

        <span>
            Sandbox credentials
            <strong><?= admin_e(payment_mask($configured)) ?></strong>
        </span>

        <span>
            Notification ID
            <strong><?= admin_e(payment_mask($notificationId !== '')) ?></strong>
        </span>

        <p>
            IPN URL:
            <?= admin_e((string)($pesapal['ipn_url'] ?? '')) ?>
        </p>
    </div>
</section>

<section class="admin-panel" style="margin-top:26px">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">Step 1</p>
            <h2>Register sandbox IPN</h2>
        </div>
    </div>

    <p>
        Run this only when a sandbox IPN needs to be registered.
        Once the returned <strong>ipn_id</strong> is stored as the sandbox
        <strong>notification_id</strong>, you do not need to register it again.
    </p>

    <form method="post" class="admin-form">
        <input
            type="hidden"
            name="csrf"
            value="<?= admin_e(admin_csrf_token()) ?>"
        >

        <input
            type="hidden"
            name="action"
            value="register_ipn"
        >

        <button
            class="admin-button primary"
            type="submit"
            <?= ($environment !== 'sandbox' || !$configured) ? 'disabled' : '' ?>
        >
            Register Sandbox IPN
        </button>
    </form>

    <?php if ($result !== null): ?>
        <pre class="api-response"><?= admin_e(payment_json($result)) ?></pre>
    <?php endif; ?>
</section>

<section class="admin-panel" style="margin-top:26px">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">Step 2</p>
            <h2>Create sandbox payment</h2>
        </div>
    </div>

    <p>
        Creates a USD 1.00 test order in the PesaPal sandbox.
        This does not enable PesaPal for customers and does not create a live charge.
    </p>

    <form method="post" class="admin-form">
        <input
            type="hidden"
            name="csrf"
            value="<?= admin_e(admin_csrf_token()) ?>"
        >

        <input
            type="hidden"
            name="action"
            value="create_checkout"
        >

        <button
            class="admin-button primary"
            type="submit"
            <?= (
                $environment !== 'sandbox'
                || !$configured
                || $notificationId === ''
            ) ? 'disabled' : '' ?>
        >
            Create Sandbox Payment
        </button>
    </form>

    <?php if ($checkout !== null): ?>
        <pre class="api-response"><?= admin_e(payment_json($checkout)) ?></pre>

        <?php if (trim((string)($checkout['redirect_url'] ?? '')) !== ''): ?>
            <p style="margin-top:18px">
                <a
                    class="admin-button primary"
                    href="<?= admin_e((string)$checkout['redirect_url']) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Open PesaPal Sandbox Checkout
                </a>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="admin-panel" style="margin-top:26px">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">Step 3</p>
            <h2>Check sandbox payment status</h2>
        </div>
    </div>

    <p>
        Re-check an existing sandbox transaction directly with PesaPal.
        This does not create another payment and does not update a customer invoice.
    </p>

    <form method="post" class="admin-form">
        <input
            type="hidden"
            name="csrf"
            value="<?= admin_e(admin_csrf_token()) ?>"
        >

        <input
            type="hidden"
            name="action"
            value="check_status"
        >

        <label>
            PesaPal Order Tracking ID
            <input
                type="text"
                name="tracking_id"
                value="<?= admin_e($statusTrackingId) ?>"
                placeholder="Enter OrderTrackingId"
                required
                autocomplete="off"
            >
        </label>

        <button
            class="admin-button primary"
            type="submit"
            <?= ($environment !== 'sandbox' || !$configured) ? 'disabled' : '' ?>
        >
            Check Payment Status
        </button>
    </form>

    <?php if ($statusResult !== null): ?>
        <pre class="api-response"><?= admin_e(payment_json($statusResult)) ?></pre>
    <?php endif; ?>
</section>

<?php admin_page_footer(); ?>