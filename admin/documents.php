<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
admin_require_auth();

$store = admin_store();
$allowedTypes = ['proposal', 'quotation', 'invoice'];
$type = strtolower(admin_text($_GET['type'] ?? '', 20));
if (!in_array($type, $allowedTypes, true)) $type = '';

$documents = $store->listDocuments();
if ($type !== '') {
    $documents = array_values(array_filter(
        $documents,
        static fn(array $document): bool => ($document['type'] ?? '') === $type
    ));
}

$title = match ($type) {
    'proposal' => 'Proposals',
    'quotation' => 'Quotations',
    'invoice' => 'Invoices',
    default => 'Documents',
};

admin_page_header($title, 'dashboard');
?>
<section class="admin-panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">Documents</p>
            <h2><?= admin_e($title) ?></h2>
        </div>
        <div class="document-filter-links">
            <a href="documents.php"<?= $type === '' ? ' aria-current="page"' : '' ?>>All</a>
            <a href="documents.php?type=proposal"<?= $type === 'proposal' ? ' aria-current="page"' : '' ?>>Proposals</a>
            <a href="documents.php?type=quotation"<?= $type === 'quotation' ? ' aria-current="page"' : '' ?>>Quotations</a>
            <a href="documents.php?type=invoice"<?= $type === 'invoice' ? ' aria-current="page"' : '' ?>>Invoices</a>
        </div>
    </div>

    <?php if ($documents === []): ?>
        <div class="empty-state">
            <h3>No <?= admin_e(strtolower($title)) ?> yet.</h3>
            <p>Generated documents will appear here automatically.</p>
        </div>
    <?php else: ?>
        <div class="compact-list document-register">
            <?php foreach ($documents as $document): ?>
                <?php
                    $documentType = (string)($document['type'] ?? 'document');
                    $deliveryStatus = (string)($document['delivery']['status'] ?? '');
                    $invoiceStatus = (string)($document['payload']['invoice_status'] ?? 'Issued');
                    $status = $deliveryStatus !== ''
                        ? ucfirst($deliveryStatus)
                        : ($documentType === 'invoice' ? $invoiceStatus : ucfirst($documentType));
                    $mark = $documentType === 'proposal' ? 'P' : ($documentType === 'quotation' ? 'Q' : 'I');
                    $linkedInvoice = is_array($document['draft_invoice'] ?? null) ? $document['draft_invoice'] : [];
                ?>
                <a href="document.php?id=<?= rawurlencode((string)($document['id'] ?? '')) ?>">
                    <span class="document-mark"><?= admin_e($mark) ?></span>
                    <div>
                        <strong><?= admin_e($document['number'] ?? 'Document') ?></strong>
                        <small>
                            <?= admin_e($document['client_name'] ?? 'Client document') ?>
                            <?php if ($documentType === 'quotation' && !empty($linkedInvoice['number'])): ?>
                                · Linked invoice <?= admin_e((string)$linkedInvoice['number']) ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    <em><?= admin_e($status) ?></em>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php admin_page_footer(); ?>
