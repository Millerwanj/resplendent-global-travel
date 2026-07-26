<?php
declare(strict_types=1);

const RGTS_RELEASE = '9.6.3';

require_once __DIR__ . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/DocumentSender.php';
admin_require_auth();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: index.php', true, 303);
    exit;
}

$id = admin_text($_POST['document_id'] ?? '', 100);
$redirect = 'document.php?id=' . rawurlencode($id);
if (!admin_verify_csrf($_POST['csrf'] ?? null)) {
    admin_flash('error', 'The secure session expired. Please review the document and try again.');
    header('Location: ' . $redirect, true, 303);
    exit;
}

$store = admin_store();
$document = $id !== '' ? $store->getDocument($id) : null;
if (!$document) {
    admin_flash('error', 'The document could not be found.');
    header('Location: index.php', true, 303);
    exit;
}
if (
    in_array((string)($document['lifecycle_status'] ?? 'active'), ['superseded', 'revision_requested'], true)
    || (($document['type'] ?? '') === 'invoice' && ($document['payload']['invoice_status'] ?? '') === 'Superseded')
) {
    admin_flash('error', 'This document requires a revised version and cannot be sent.');
    header('Location: ' . $redirect, true, 303);
    exit;
}
if (empty($_POST['review_confirmed'])) {
    admin_flash('error', 'Confirm that you reviewed the final document before sending it.');
    header('Location: ' . $redirect, true, 303);
    exit;
}

$recipient = strtolower(trim((string)($_POST['recipient'] ?? '')));
$subject = preg_replace('/[\r\n]+/', ' ', admin_text($_POST['subject'] ?? '', 240)) ?? '';
$coverMessage = admin_text($_POST['cover_message'] ?? '', 4000);
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || $subject === '' || $coverMessage === '') {
    admin_flash('error', 'Add a valid recipient, subject and cover message.');
    header('Location: ' . $redirect, true, 303);
    exit;
}

$type = (string)($document['type'] ?? 'proposal');
$acceptanceRequired = $type !== 'invoice' && !empty($_POST['acceptance_required']);
$label = match ($type) {
    'quotation' => 'Executive Quotation',
    'invoice' => 'Invoice',
    default => 'Executive Proposal',
};
$number = (string)($document['number'] ?? 'Resplendent Document');
$clientName = trim((string)($document['client_name'] ?? 'Client'));
$firstName = trim((string)(preg_split('/\s+/', $clientName, 2)[0] ?? 'Client'));

$configPath = dirname(__DIR__, 2) . '/rgts-mail-config.php';
if (!is_file($configPath)) {
    admin_flash('error', 'The private email configuration is unavailable. The document was not sent.');
    header('Location: ' . $redirect, true, 303);
    exit;
}
$config = require $configPath;
if (!is_array($config)) {
    admin_flash('error', 'The private email configuration is invalid. The document was not sent.');
    header('Location: ' . $redirect, true, 303);
    exit;
}
$sender = DocumentSender::resolve(
    $document,
    $config,
    admin_text($_POST['sender_profile'] ?? '', 40)
);
$mailerConfig = DocumentSender::mailerConfig($config, $sender);

require_once dirname(__DIR__) . '/includes/PdfDocumentGenerator.php';
require_once dirname(__DIR__) . '/includes/SmtpMailer.php';

$delivery = null;
try {
    $generator = new PdfDocumentGenerator(dirname(__DIR__) . '/assets/images/logo-r-pdf.jpg');
    $pdf = $generator->render($document);
    $pdfHash = hash('sha256', $pdf);
    $delivery = $store->createDocumentDelivery(
        $id,
        $recipient,
        $acceptanceRequired,
        $subject,
        $pdfHash,
        (string)$sender['key'],
        (string)$sender['email']
    );

    $baseUrl = rtrim((string)($config['public_base_url'] ?? 'https://www.resplendentglobaltravel.com'), '/');
    if (!preg_match('#^https://[A-Za-z0-9.-]+(?::\d+)?$#', $baseUrl)) {
        $baseUrl = 'https://www.resplendentglobaltravel.com';
    }
    $acceptanceUrl = $acceptanceRequired
        ? $baseUrl . '/accept.php?token=' . rawurlencode((string)$delivery['token'])
        : '';

    $body = "Dear {$firstName},\n\n";
    $body .= $coverMessage . "\n\n";
    $body .= "The attached PDF is {$label} {$number}.\n";
    if ($acceptanceUrl !== '') {
        $body .= "\nWhen you are ready, record your acceptance securely using this link:\n{$acceptanceUrl}\n";
    }
    $body .= "\nWarm regards,\n\n";
    $body .= "Resplendent Global Travel Solutions\n";
    $body .= "Luxury Travel | Corporate Travel | Global Business Connections\n";
    $body .= (string)$sender['reply_to'] . "\n";
    $body .= "+254 724 785 341\n";

    $fromEmail = (string)$sender['email'];
    $replyTo = (string)$sender['reply_to'];
    $centralCopy = trim((string)($config['central_copy'] ?? ''));
    $cc = ($centralCopy !== '' && strcasecmp($centralCopy, $recipient) !== 0)
        ? [$centralCopy]
        : [];
    $filename = (preg_replace('/[^A-Za-z0-9._-]/', '-', $number) ?: 'Resplendent-Document') . '.pdf';

    $mailer = new SmtpMailer($mailerConfig);
    $sendResult = $mailer->send(
        [$recipient],
        $cc,
        $fromEmail,
        (string)$sender['name'],
        $replyTo,
        $subject,
        $body,
        [[
            'filename' => $filename,
            'mime_type' => 'application/pdf',
            'content' => $pdf,
        ]]
    );
    $sendResult['sender_profile'] = (string)$sender['key'];
    $sendResult['from_email'] = $fromEmail;
    $store->markDocumentDelivery((string)$delivery['id'], 'sent', $sendResult);
    admin_flash(
        'success',
        $label . ' ' . $number . ' was emailed with its PDF attached'
        . ($acceptanceRequired ? ' and a secure acceptance link.' : '.')
        . ' Sent from ' . $fromEmail . '.'
    );
} catch (Throwable $error) {
    if (is_array($delivery) && !empty($delivery['id'])) {
        try {
            $store->markDocumentDelivery((string)$delivery['id'], 'failed', [
                'message' => $error->getMessage(),
            ]);
        } catch (Throwable) {
            // Preserve the original delivery error below.
        }
    }
    error_log('RGTS document delivery failed: ' . $error->getMessage());
    admin_flash('error', 'The document was not sent. Review the protected delivery log and try again.');
}

header('Location: ' . $redirect, true, 303);
exit;
