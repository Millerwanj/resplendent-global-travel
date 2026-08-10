<?php
declare(strict_types=1);

const RGTS_RELEASE = '9.7.0';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header("Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

require_once __DIR__ . '/includes/OperationsStore.php';
require_once __DIR__ . '/includes/DocumentSender.php';

function acceptance_e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function workflow_notify(array $result, string $event): void
{
    $delivery = is_array($result['delivery'] ?? null) ? $result['delivery'] : [];
    $document = is_array($result['document'] ?? null) ? $result['document'] : [];
    $acceptance = is_array($delivery['acceptance'] ?? null) ? $delivery['acceptance'] : [];
    $changeRequest = is_array($delivery['change_request'] ?? null) ? $delivery['change_request'] : [];
    $draftInvoice = is_array($result['draft_invoice'] ?? null) ? $result['draft_invoice'] : [];
    $configPath = dirname(__DIR__) . '/rgts-mail-config.php';
    if (!is_file($configPath)) return;
    $config = require $configPath;
    if (!is_array($config)) return;

    try {
        require_once __DIR__ . '/includes/SmtpMailer.php';
        $sender = DocumentSender::resolve($document, $config);
        $recipient = (string)$sender['email'];
        $centralCopy = trim((string)($config['central_copy'] ?? ''));
        $cc = ($centralCopy !== '' && strcasecmp($centralCopy, $recipient) !== 0) ? [$centralCopy] : [];
        $clientEmail = (string)(
            $event === 'changes_requested'
                ? ($changeRequest['email'] ?? $delivery['recipient'] ?? '')
                : ($acceptance['email'] ?? $delivery['recipient'] ?? '')
        );
        $number = (string)($delivery['document_number'] ?? $document['number'] ?? 'Document');
        $acceptedEvent = $event === 'accepted';
        $subject = ($acceptedEvent ? 'Accepted: ' : 'Changes requested: ')
            . $number . ' — ' . (string)($delivery['client_name'] ?? 'Client');
        $body = $acceptedEvent
            ? "RESPLENDENT CLIENT ACCEPTANCE\n\n"
            : "RESPLENDENT DOCUMENT REVISION REQUEST\n\n";
        $body .= "Document: {$number}\n";
        $body .= "Client: " . (string)($delivery['client_name'] ?? '') . "\n";
        $body .= "Client reference: " . (string)($delivery['client_reference'] ?? '') . "\n";
        if ($acceptedEvent) {
            $body .= "Accepted by: " . (string)($acceptance['accepted_by'] ?? '') . "\n";
            $body .= "Email: {$clientEmail}\n";
            $body .= "Accepted at (UTC): " . (string)($acceptance['accepted_at_utc'] ?? '') . "\n";
            $body .= "Document SHA-256: " . (string)($delivery['document_hash'] ?? '') . "\n";
            if (!empty($draftInvoice['number'])) {
                $body .= "Draft invoice: " . (string)$draftInvoice['number'] . "\n";
                $baseUrl = rtrim((string)($config['public_base_url'] ?? ''), '/');
                if (preg_match('#^https://[A-Za-z0-9.-]+(?::\d+)?$#', $baseUrl)) {
                    $body .= "Review: {$baseUrl}/admin/document.php?id="
                        . rawurlencode((string)$draftInvoice['id']) . "\n";
                }
            }
            $body .= "\nThe acceptance and workflow records were updated. Review the draft invoice before sending it.\n";
        } else {
            $body .= "Requested by: " . (string)($changeRequest['requested_by'] ?? '') . "\n";
            $body .= "Email: {$clientEmail}\n";
            $body .= "Requested at (UTC): " . (string)($changeRequest['requested_at_utc'] ?? '') . "\n\n";
            $body .= "REQUESTED CHANGES\n" . (string)($changeRequest['notes'] ?? '') . "\n\n";
            $body .= "Create a controlled revision in the admin suite. No invoice was sent automatically.\n";
        }

        $mailer = new SmtpMailer($config);
        $mailer->send(
            [$recipient],
            $cc,
            (string)($config['from_email'] ?? 'info@resplendentglobaltravel.com'),
            'Resplendent Global Travel Solutions',
            filter_var($clientEmail, FILTER_VALIDATE_EMAIL) ? $clientEmail : $recipient,
            $subject,
            $body
        );
        $reference = (string)($delivery['client_reference'] ?? '');
        if ($reference !== '') {
            (new OperationsStore())->recordPipelineEvent($reference, $event . '_notification', 'ok', [
                'document' => $number,
            ]);
        }
    } catch (Throwable $error) {
        error_log('RGTS workflow notification failed: ' . $error->getMessage());
        $reference = (string)($delivery['client_reference'] ?? '');
        if ($reference !== '') {
            try {
                (new OperationsStore())->recordPipelineEvent($reference, $event . '_notification', 'failed', [
                    'document' => (string)($delivery['document_number'] ?? ''),
                ]);
            } catch (Throwable) {
                // The acceptance itself is already safely recorded.
            }
        }
    }
}

$token = strtolower(trim((string)($_POST['token'] ?? $_GET['token'] ?? '')));
$store = null;
$delivery = null;
$document = null;
$resultStatus = '';
$error = '';

try {
    $store = new OperationsStore();
    $delivery = $store->getDocumentDeliveryByToken($token);
    $document = is_array($delivery['document'] ?? null) ? $delivery['document'] : null;
} catch (Throwable $storeError) {
    error_log('RGTS acceptance store failed: ' . $storeError->getMessage());
}

if (!$delivery || !$document || empty($delivery['acceptance_required'])) {
    http_response_code(404);
    $error = 'This acceptance link is not valid.';
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string)($_POST['action'] ?? 'accept');
    $personName = trim(strip_tags((string)($_POST['person_name'] ?? '')));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $userAgent = trim(strip_tags((string)($_SERVER['HTTP_USER_AGENT'] ?? '')));

    if ($action === 'request_changes') {
        $notes = trim(strip_tags((string)($_POST['change_notes'] ?? '')));
        $result = $store->requestDocumentChanges($token, $personName, $email, $notes, $ipHash, $userAgent);
        $resultStatus = (string)($result['status'] ?? 'invalid');
        if ($resultStatus === 'changes_requested') {
            workflow_notify($result, 'changes_requested');
            $delivery = $result['delivery'];
            $document = $result['document'];
        } elseif ($resultStatus === 'already_requested') {
            $delivery = $result['delivery'];
            $document = $result['document'];
        } else {
            $error = (string)($result['message'] ?? 'The revision request could not be recorded.');
            if (($delivery['status'] ?? '') === 'accepted') $resultStatus = 'already_accepted';
        }
    } elseif (empty($_POST['accept_terms']) || empty($_POST['authority_confirmed'])) {
        $error = 'Confirm the document terms and your authority to accept before continuing.';
    } else {
        $result = $store->acceptDocumentDelivery($token, $personName, $email, $ipHash, $userAgent);
        $resultStatus = (string)($result['status'] ?? 'invalid');
        if ($resultStatus === 'accepted') {
            workflow_notify($result, 'accepted');
            $delivery = $result['delivery'];
            $document = $result['document'];
        } elseif ($resultStatus === 'already_accepted') {
            $delivery = $result['delivery'];
            $document = $result['document'];
        } else {
            $error = (string)($result['message'] ?? 'The acceptance could not be recorded.');
        }
    }
} elseif (($delivery['status'] ?? '') === 'accepted') {
    $resultStatus = 'already_accepted';
} elseif (($delivery['status'] ?? '') === 'changes_requested') {
    $resultStatus = 'already_requested';
} elseif (($delivery['status'] ?? '') !== 'sent') {
    http_response_code(410);
    $error = 'This acceptance link is no longer active.';
}

$typeLabel = match ((string)($document['type'] ?? '')) {
    'quotation' => 'Executive Quotation',
    'invoice' => 'Invoice',
    default => 'Executive Proposal',
};
$policy = is_array($delivery['policy'] ?? null)
    ? $delivery['policy']
    : (is_array($document['policy'] ?? null) ? $document['policy'] : []);
$terms = is_array($policy['terms'] ?? null) ? $policy['terms'] : [];
$accepted = in_array($resultStatus, ['accepted', 'already_accepted'], true);
$changesRequested = in_array($resultStatus, ['changes_requested', 'already_requested'], true);
$documentSuperseded = ($document['lifecycle_status'] ?? 'active') === 'superseded';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="theme-color" content="#10151F">
    <title><?= $accepted ? 'Acceptance Recorded' : ($changesRequested ? 'Revision Requested' : 'Document Response') ?> | Resplendent</title>
    <link rel="icon" href="favicon.ico">
    <link rel="stylesheet" href="assets/css/acceptance.css?v=9.7.0">
</head>
<body>
<main class="acceptance-shell">
    <section class="acceptance-card">
        <header class="acceptance-brand">
            <img src="assets/images/logo-r.png" alt="" width="58" height="58">
            <span><strong>RESPLENDENT</strong><small>GLOBAL TRAVEL SOLUTIONS</small></span>
        </header>

        <?php if ($changesRequested): ?>
            <div class="acceptance-complete">
                <p class="eyebrow">Revision Requested</p>
                <h1>Thank you.</h1>
                <p>Your requested changes to <?= acceptance_e($typeLabel) ?> <strong><?= acceptance_e($document['number'] ?? '') ?></strong> have been delivered to Resplendent.</p>
                <small>We will review your notes and send a revised document for approval.</small>
            </div>
        <?php elseif ($accepted): ?>
            <div class="acceptance-complete">
                <p class="eyebrow">Acceptance Recorded</p>
                <h1>Thank you.</h1>
                <p>Your acceptance of <?= acceptance_e($typeLabel) ?> <strong><?= acceptance_e($document['number'] ?? '') ?></strong> has been recorded securely.</p>
                <p>Resplendent will review the corresponding draft invoice and send it separately.</p>
                <?php if (!empty($delivery['accepted_at'])): ?>
                    <small>Recorded <?= acceptance_e(gmdate('j F Y, H:i', strtotime((string)$delivery['accepted_at']))) ?> UTC</small>
                <?php endif; ?>
            </div>
            <?php if ($documentSuperseded): ?>
                <div class="acceptance-terms"><p>A newer revision of this document is now active. Please use the latest email from Resplendent.</p></div>
            <?php else: ?>
            <details class="change-request-panel" <?= $error !== '' ? 'open' : '' ?>>
                <summary>Request a revision after acceptance</summary>
                <?php if ($error !== ''): ?><div class="acceptance-error" role="alert"><?= acceptance_e($error) ?></div><?php endif; ?>
                <form method="post" class="acceptance-form change-request-form">
                    <input type="hidden" name="token" value="<?= acceptance_e($token) ?>">
                    <input type="hidden" name="action" value="request_changes">
                    <label>Full name
                        <input type="text" name="person_name" autocomplete="name" required value="<?= acceptance_e($_POST['person_name'] ?? '') ?>">
                    </label>
                    <label>Email address used to receive the document
                        <input type="email" name="email" autocomplete="email" required value="<?= acceptance_e($_POST['email'] ?? '') ?>">
                    </label>
                    <label>Changes requested
                        <textarea name="change_notes" rows="5" maxlength="3000" required><?= acceptance_e($_POST['change_notes'] ?? '') ?></textarea>
                    </label>
                    <button type="submit" class="secondary-action">Send Revision Request</button>
                </form>
            </details>
            <?php endif; ?>
        <?php elseif ($error !== '' && (!$delivery || !in_array((string)($delivery['status'] ?? ''), ['sent', 'accepted'], true))): ?>
            <div class="acceptance-complete">
                <p class="eyebrow">Document Acceptance</p>
                <h1>Link unavailable.</h1>
                <p><?= acceptance_e($error) ?></p>
                <p>Contact <a href="mailto:info@resplendentglobaltravel.com">info@resplendentglobaltravel.com</a> for assistance.</p>
            </div>
        <?php else: ?>
            <div class="acceptance-heading">
                <p class="eyebrow">Secure Document Acceptance</p>
                <h1><?= acceptance_e($typeLabel) ?></h1>
                <dl>
                    <div><dt>Document</dt><dd><?= acceptance_e($document['number'] ?? '') ?></dd></div>
                    <div><dt>Prepared for</dt><dd><?= acceptance_e($document['client_name'] ?? 'Client') ?></dd></div>
                </dl>
                <p>Review the attached PDF received by email before recording acceptance here.</p>
            </div>

            <?php if ($error !== ''): ?><div class="acceptance-error" role="alert"><?= acceptance_e($error) ?></div><?php endif; ?>

            <?php if ($terms !== []): ?>
                <section class="acceptance-terms">
                    <h2>Important terms</h2>
                    <?php foreach ($terms as $term): ?><p><?= acceptance_e($term) ?></p><?php endforeach; ?>
                </section>
            <?php endif; ?>

            <form method="post" class="acceptance-form">
                <input type="hidden" name="token" value="<?= acceptance_e($token) ?>">
                <input type="hidden" name="action" value="accept">
                <label>Full name
                    <input type="text" name="person_name" autocomplete="name" required value="<?= acceptance_e($_POST['person_name'] ?? '') ?>">
                </label>
                <label>Email address used to receive the document
                    <input type="email" name="email" autocomplete="email" required value="<?= acceptance_e($_POST['email'] ?? '') ?>">
                </label>
                <label class="acceptance-check">
                    <input type="checkbox" name="accept_terms" required>
                    <span>I have reviewed and accept <?= acceptance_e($typeLabel) ?> <?= acceptance_e($document['number'] ?? '') ?> and its stated terms.</span>
                </label>
                <label class="acceptance-check">
                    <input type="checkbox" name="authority_confirmed" required>
                    <span>I confirm that I am authorised to record this acceptance.</span>
                </label>
                <button type="submit">Accept Document</button>
                <small>Acceptance records the document version, time, supplied identity and a privacy-preserving connection fingerprint.</small>
            </form>
            <details class="change-request-panel">
                <summary>Request changes instead</summary>
                <form method="post" class="acceptance-form change-request-form">
                    <input type="hidden" name="token" value="<?= acceptance_e($token) ?>">
                    <input type="hidden" name="action" value="request_changes">
                    <label>Full name
                        <input type="text" name="person_name" autocomplete="name" required value="<?= acceptance_e($_POST['person_name'] ?? '') ?>">
                    </label>
                    <label>Email address used to receive the document
                        <input type="email" name="email" autocomplete="email" required value="<?= acceptance_e($_POST['email'] ?? '') ?>">
                    </label>
                    <label>Changes requested
                        <textarea name="change_notes" rows="5" maxlength="3000" required><?= acceptance_e($_POST['change_notes'] ?? '') ?></textarea>
                    </label>
                    <button type="submit" class="secondary-action">Send Revision Request</button>
                    <small>Your notes are recorded against this exact document version. No invoice will be sent automatically.</small>
                </form>
            </details>
        <?php endif; ?>

        <footer>
            <span>resplendentglobaltravel.com</span>
            <a href="terms">Terms of Service</a>
        </footer>
    </section>
</main>
</body>
</html>
