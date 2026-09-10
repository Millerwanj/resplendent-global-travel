<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/Connectivity/ConnectivityFulfillmentService.php';

admin_require_auth();
$store = new ConnectivityOrderStore();
$service = new ConnectivityFulfillmentService($store);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_verify_csrf($_POST['csrf'] ?? null)) {
        admin_flash('error', 'Your session token expired. Refresh and try again.');
    } else {
        $orderId = admin_text($_POST['order_id'] ?? '', 80);
        $action = admin_text($_POST['action'] ?? '', 40);
        try {
            if ($action === 'check-provider') {
                $service->reconcile($orderId, true);
                admin_flash('success', 'The supplier status was checked without creating a new purchase.');
            } elseif ($action === 'retry-email') {
                $service->retryDelivery($orderId, true);
                admin_flash('success', 'The delivery email retry was processed without creating a new purchase.');
            }
        } catch (Throwable $e) {
            error_log('RGTS eSIM admin operation: ' . $e->getMessage());
            admin_flash('error', 'The eSIM operation needs review. No new supplier purchase was started.');
        }
    }
    header('Location: esim-orders.php', true, 303);
    exit;
}

$orders = $store->listRecent(100);
admin_page_header('eSIM Orders', 'esim-orders');
?>
<section class="admin-panel">
    <div class="panel-heading"><div><p class="eyebrow">Provisioning operations</p><h2>Payment, supplier and delivery status</h2></div></div>
    <p>Supplier-status checks and email retries are deliberately separate. Neither action below starts a new eSIM purchase.</p>
    <?php if ($orders === []): ?>
        <div class="admin-alert">No connectivity orders have been recorded yet.</div>
    <?php else: ?>
        <div style="overflow-x:auto">
        <table class="preview-quote-table">
            <thead><tr><th>Order</th><th>Customer</th><th>Payment</th><th>Supplier</th><th>Delivery</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $order):
                $orderId = (string)($order['id'] ?? '');
                $provisioning = is_array($order['provisioning'] ?? null) ? $order['provisioning'] : [];
                $delivery = is_array($order['delivery'] ?? null) ? $order['delivery'] : [];
                $canCheck = !empty($provisioning['provider_order_reference'])
                    && in_array(($provisioning['status'] ?? ''), ['AWAITING_SUPPLIER', 'PROVISIONING_REVIEW'], true);
                $canRetryEmail = ($order['status'] ?? '') === 'PROVISIONED' && empty($delivery['email_sent_at']);
            ?>
                <tr>
                    <td><strong><?= admin_e($orderId) ?></strong><br><small><?= admin_e((string)($order['updated_at'] ?? '')) ?></small></td>
                    <td><?= admin_e((string)($order['payload']['customer']['name'] ?? '')) ?><br><small><?= admin_e((string)($order['payload']['customer']['email'] ?? '')) ?></small></td>
                    <td><?= admin_e((string)($order['payment']['status'] ?? 'pending')) ?><br><small><?= !empty($order['payment']['matches_order']) ? 'Verified' : 'Not verified' ?></small></td>
                    <td><?= admin_e((string)($provisioning['status'] ?? 'NOT_STARTED')) ?><br><small><?= admin_e((string)($provisioning['provider_order_reference'] ?? '')) ?></small></td>
                    <td><?= admin_e((string)($delivery['status'] ?? (empty($delivery['email_sent_at']) ? 'PENDING' : 'SENT'))) ?><br><small><?= admin_e((string)($delivery['email_error'] ?? '')) ?></small></td>
                    <td>
                        <?php if ($canCheck): ?>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>">
                            <input type="hidden" name="action" value="check-provider">
                            <input type="hidden" name="order_id" value="<?= admin_e($orderId) ?>">
                            <button class="admin-button secondary" type="submit">Check supplier</button>
                        </form>
                        <?php elseif ($canRetryEmail): ?>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>">
                            <input type="hidden" name="action" value="retry-email">
                            <input type="hidden" name="order_id" value="<?= admin_e($orderId) ?>">
                            <button class="admin-button secondary" type="submit">Retry email</button>
                        </form>
                        <?php else: ?><small>No action required</small><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
<?php admin_page_footer(); ?>
