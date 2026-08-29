<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
admin_require_auth();

$clients = admin_store()->listClients();
$documents = admin_store()->listDocuments();
$activeClients = array_values(array_filter($clients, static fn(array $client): bool =>
    !in_array((string)($client['status'] ?? ''), ['Relationship', 'Closed'], true)
));
$proposalCount = count(array_filter($documents, static fn(array $document): bool => ($document['type'] ?? '') === 'proposal'));
$quotationCount = count(array_filter($documents, static fn(array $document): bool => ($document['type'] ?? '') === 'quotation'));
$invoiceCount = count(array_filter($documents, static fn(array $document): bool => ($document['type'] ?? '') === 'invoice'));
$recentClients = array_slice($clients, 0, 6);
$recentDocuments = array_slice($documents, 0, 6);

admin_page_header('Operations Overview', 'dashboard');
?>
<section class="metric-grid" aria-label="Operations summary">
    <article><span>Active clients</span><strong><?= count($activeClients) ?></strong><small>Across the engagement workflow</small></article>
    <article><span>New enquiries</span><strong><?= count(array_filter($clients, static fn(array $c): bool => ($c['status'] ?? '') === 'New Enquiry')) ?></strong><small>Ready for initial review</small></article>
    <a class="metric-card-link" href="documents.php?type=proposal"><article><span>Proposals</span><strong><?= $proposalCount ?></strong><small>Open generated proposals</small></article></a>
    <a class="metric-card-link" href="documents.php?type=quotation"><article><span>Quotations</span><strong><?= $quotationCount ?></strong><small>Open and move approved quotations forward</small></article></a>
    <a class="metric-card-link" href="documents.php?type=invoice"><article><span>Invoices</span><strong><?= $invoiceCount ?></strong><small>Open invoices and payment status</small></article></a>
</section>

<section class="quick-actions">
    <div>
        <p class="eyebrow">Create</p>
        <h2>Move a client forward.</h2>
        <p>Build a refined executive document or review every engagement from one place.</p>
    </div>
    <div class="quick-action-links">
        <a href="proposal.php"><span>01</span><strong>New Proposal</strong><small>Travel, corporate or business connections</small></a>
        <a href="quotation.php"><span>02</span><strong>New Quotation</strong><small>Travel costs or matchmaking milestones</small></a>
        <a href="invoice.php"><span>03</span><strong>New Invoice</strong><small>Convert an approved quotation or bill a milestone</small></a>
        <a href="workflow.php"><span>04</span><strong>Client Workflow</strong><small>Review status and record the next action</small></a>
    </div>
</section>

<div class="admin-two-column">
    <section class="admin-panel">
        <div class="panel-heading"><div><p class="eyebrow">Clients</p><h2>Recent enquiries</h2></div><a href="workflow.php">View all</a></div>
        <?php if ($recentClients === []): ?>
            <div class="empty-state"><h3>No client enquiries yet.</h3><p>New website enquiries will appear here automatically after deployment.</p></div>
        <?php else: ?>
            <div class="compact-list">
                <?php foreach ($recentClients as $client): ?>
                <a href="workflow.php?client=<?= rawurlencode((string)$client['reference']) ?>">
                    <span class="status-dot"></span>
                    <div><strong><?= admin_e($client['name'] ?? 'Unnamed client') ?></strong><small><?= admin_e($client['service'] ?? 'General enquiry') ?> · <?= admin_e($client['reference'] ?? '') ?></small></div>
                    <em><?= admin_e($client['status'] ?? 'New Enquiry') ?></em>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <section class="admin-panel">
        <div class="panel-heading"><div><p class="eyebrow">Documents</p><h2>Recently generated</h2></div></div>
        <?php if ($recentDocuments === []): ?>
            <div class="empty-state"><h3>No documents yet.</h3><p>Your first proposal or quotation will appear here.</p></div>
        <?php else: ?>
            <div class="compact-list">
                <?php foreach ($recentDocuments as $document): ?>
                <?php $deliveryStatus = (string)($document['delivery']['status'] ?? ''); ?>
                <a href="document.php?id=<?= rawurlencode((string)$document['id']) ?>">
                    <span class="document-mark"><?= ($document['type'] ?? '') === 'proposal' ? 'P' : (($document['type'] ?? '') === 'quotation' ? 'Q' : 'I') ?></span>
                    <div><strong><?= admin_e($document['client_name'] ?? 'Client document') ?></strong><small><?= admin_e($document['number'] ?? '') ?></small></div>
                    <em><?= admin_e($deliveryStatus !== '' ? ucfirst($deliveryStatus) : (($document['type'] ?? '') === 'invoice' ? ($document['payload']['invoice_status'] ?? 'Issued') : ucfirst((string)($document['type'] ?? 'document')))) ?></em>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php admin_page_footer(); ?>
