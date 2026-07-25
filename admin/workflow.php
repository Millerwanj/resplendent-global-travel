<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
admin_require_auth();

$store = admin_store();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_verify_csrf($_POST['csrf'] ?? null)) {
        admin_flash('error', 'The secure session expired. Please try again.');
    } else {
        $reference = admin_text($_POST['reference'] ?? '', 40);
        $status = admin_text($_POST['status'] ?? '', 40);
        $note = admin_text($_POST['note'] ?? '', 1000);
        if ($store->updateClient($reference, $status, $note)) {
            admin_flash('success', $reference . ' moved to ' . $status . '.');
        } else {
            admin_flash('error', 'The client status could not be updated.');
        }
    }
    $redirectReference = rawurlencode(admin_text($_POST['reference'] ?? '', 40));
    header('Location: workflow.php' . ($redirectReference !== '' ? '?client=' . $redirectReference : ''), true, 303);
    exit;
}

$clients = $store->listClients();
$selectedReference = admin_text($_GET['client'] ?? '', 40);
$selectedClient = $selectedReference !== '' ? $store->getClient($selectedReference) : null;

admin_page_header('Client Workflow', 'workflow');
?>
<section class="workflow-guide" aria-label="Standard client lifecycle">
    <?php foreach (array_values(array_filter(OperationsStore::STATUSES, static fn(string $status): bool => $status !== 'Closed')) as $index => $status): ?>
        <span><em><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></em><?= admin_e($status) ?></span>
    <?php endforeach; ?>
</section>

<section class="admin-panel workflow-panel">
    <div class="panel-heading workflow-heading">
        <div><p class="eyebrow">Engagements</p><h2>All clients</h2></div>
        <div class="workflow-filters">
            <label class="sr-only" for="workflow-search">Search clients</label>
            <input id="workflow-search" type="search" placeholder="Search client or reference" data-workflow-search>
            <label class="sr-only" for="workflow-status">Filter by status</label>
            <select id="workflow-status" data-workflow-status>
                <option value="">All stages</option>
                <?php foreach (OperationsStore::STATUSES as $status): ?><option><?= admin_e($status) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php if ($clients === []): ?>
        <div class="empty-state"><h3>No client records yet.</h3><p>The next verified website enquiry will create the first workflow record automatically.</p></div>
    <?php else: ?>
        <div class="workflow-table-wrap">
            <table class="workflow-table">
                <thead><tr><th>Client</th><th>Service</th><th>Reference</th><th>Stage</th><th>Updated</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($clients as $client): ?>
                    <tr data-workflow-row data-search="<?= admin_e(strtolower(implode(' ', [
                        (string)($client['name'] ?? ''), (string)($client['company'] ?? ''),
                        (string)($client['reference'] ?? ''), (string)($client['service'] ?? '')
                    ]))) ?>" data-status="<?= admin_e($client['status'] ?? '') ?>">
                        <td><strong><?= admin_e($client['name'] ?? 'Unnamed client') ?></strong><small><?= admin_e($client['company'] ?? '') ?></small></td>
                        <td><?= admin_e($client['service'] ?? 'General Enquiry') ?></td>
                        <td><code><?= admin_e($client['reference'] ?? '') ?></code></td>
                        <td><span class="status-pill"><?= admin_e($client['status'] ?? 'New Enquiry') ?></span></td>
                        <td><?= admin_e(isset($client['updated_at']) ? date('j M Y', strtotime((string)$client['updated_at'])) : '—') ?></td>
                        <td><a class="table-action" href="?client=<?= rawurlencode((string)$client['reference']) ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="workflow-empty-filter" data-workflow-empty hidden>No clients match that filter.</p>
    <?php endif; ?>
</section>

<?php if ($selectedClient): ?>
<div class="workflow-drawer" data-workflow-drawer>
    <a class="drawer-backdrop" href="workflow.php" aria-label="Close client details"></a>
    <section class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="client-title">
        <a class="drawer-close" href="workflow.php" aria-label="Close">×</a>
        <p class="eyebrow"><?= admin_e($selectedClient['reference'] ?? '') ?></p>
        <h2 id="client-title"><?= admin_e($selectedClient['name'] ?? 'Client') ?></h2>
        <p><?= admin_e($selectedClient['service'] ?? 'General Enquiry') ?><?= !empty($selectedClient['company']) ? ' · ' . admin_e($selectedClient['company']) : '' ?></p>
        <dl class="client-details">
            <div><dt>Email</dt><dd><a href="mailto:<?= admin_e($selectedClient['email'] ?? '') ?>"><?= admin_e($selectedClient['email'] ?? '—') ?></a></dd></div>
            <div><dt>Phone</dt><dd><?= admin_e($selectedClient['phone'] ?? '—') ?></dd></div>
            <div><dt>Destination</dt><dd><?= admin_e($selectedClient['destination'] ?? '—') ?></dd></div>
            <div><dt>Dates</dt><dd><?= admin_e($selectedClient['dates'] ?? '—') ?></dd></div>
        </dl>
        <?php if (!empty($selectedClient['message'])): ?>
            <div class="client-brief"><span>Client brief</span><p><?= nl2br(admin_e($selectedClient['message'])) ?></p></div>
        <?php endif; ?>
        <div class="drawer-actions">
            <a class="admin-button secondary" href="proposal.php?client=<?= rawurlencode((string)$selectedClient['reference']) ?>">Create Proposal</a>
            <a class="admin-button secondary" href="quotation.php?client=<?= rawurlencode((string)$selectedClient['reference']) ?>">Create Quotation</a>
            <a class="admin-button secondary" href="invoice.php?client=<?= rawurlencode((string)$selectedClient['reference']) ?>">Create Invoice</a>
        </div>
        <form method="post" class="admin-form status-form">
            <input type="hidden" name="csrf" value="<?= admin_e(admin_csrf_token()) ?>">
            <input type="hidden" name="reference" value="<?= admin_e($selectedClient['reference'] ?? '') ?>">
            <label>Current stage
                <select name="status" required>
                    <?php foreach (OperationsStore::STATUSES as $status): ?>
                        <option <?= ($selectedClient['status'] ?? '') === $status ? 'selected' : '' ?>><?= admin_e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Progress note<textarea name="note" rows="3" placeholder="Record the next action or material update"></textarea></label>
            <button class="admin-button primary" type="submit">Update Client</button>
        </form>
        <?php if (!empty($selectedClient['history']) && is_array($selectedClient['history'])): ?>
        <div class="client-history">
            <h3>Activity</h3>
            <?php foreach (array_reverse($selectedClient['history']) as $event): ?>
                <article><span></span><div><strong><?= admin_e($event['status'] ?? 'Update') ?></strong><small><?= admin_e(isset($event['time']) ? date('j M Y, H:i', strtotime((string)$event['time'])) : '') ?></small><p><?= admin_e($event['note'] ?? '') ?></p></div></article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</div>
<?php endif; ?>
<?php admin_page_footer(); ?>
