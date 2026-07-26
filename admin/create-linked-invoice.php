<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
admin_require_auth();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$documentId = admin_text($_POST['document_id'] ?? '', 100);
$returnUrl = 'document.php?id=' . rawurlencode($documentId);
if ($documentId === '' || !admin_verify_csrf($_POST['csrf'] ?? null)) {
    admin_flash('error', 'The secure session expired. Please refresh and try again.');
    header('Location: ' . ($documentId !== '' ? $returnUrl : 'index.php'), true, 303);
    exit;
}

$result = admin_store()->createLinkedDraftInvoice($documentId);
$invoice = is_array($result['invoice'] ?? null) ? $result['invoice'] : null;
if (
    in_array((string)($result['status'] ?? ''), ['created', 'existing'], true)
    && is_array($invoice)
    && !empty($invoice['id'])
) {
    $message = ($result['status'] ?? '') === 'created'
        ? 'Linked draft invoice ' . (string)($invoice['number'] ?? '') . ' was created for review.'
        : 'The existing linked invoice ' . (string)($invoice['number'] ?? '') . ' was opened.';
    admin_flash('success', $message);
    header('Location: document.php?id=' . rawurlencode((string)$invoice['id']), true, 303);
    exit;
}

admin_flash('error', (string)($result['message'] ?? 'The linked draft invoice could not be created.'));
header('Location: ' . $returnUrl, true, 303);
exit;
