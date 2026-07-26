<?php
declare(strict_types=1);

require_once __DIR__ . '/DocumentPolicy.php';

/**
 * Lightweight, file-backed operations store for the Resplendent admin suite.
 *
 * Production data is kept outside public_html whenever the hosting account
 * allows it. No client records or generated documents are included in a
 * release archive.
 */
final class OperationsStore
{
    public const STATUSES = [
        'New Enquiry',
        'Discovery',
        'Proposal',
        'Quotation',
        'Revision Requested',
        'Approval',
        'Invoice',
        'Payment',
        'Service',
        'Relationship',
        'Closed',
    ];

    private string $directory;

    public function __construct(?string $directory = null)
    {
        $configured = trim((string)(getenv('RGTS_OPERATIONS_DATA') ?: ''));
        $preferred = dirname(__DIR__, 2) . '/rgts-operations-data';
        $fallback = dirname(__DIR__) . '/.rgts-operations-data';
        $this->directory = $directory ?: ($configured !== '' ? $configured : $preferred);

        if (!$this->ensureDirectory($this->directory)) {
            $this->directory = $fallback;
            if (!$this->ensureDirectory($this->directory)) {
                throw new RuntimeException('The Resplendent operations data directory is not writable.');
            }
        }
    }

    public function storageDirectory(): string
    {
        return $this->directory;
    }

    /** @return array<int,array<string,mixed>> */
    public function listClients(): array
    {
        $clients = array_values($this->readJson('clients.json', []));
        usort($clients, static fn(array $a, array $b): int =>
            strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''))
        );
        return $clients;
    }

    /** @return array<string,mixed>|null */
    public function getClient(string $reference): ?array
    {
        $clients = $this->readJson('clients.json', []);
        return isset($clients[$reference]) && is_array($clients[$reference]) ? $clients[$reference] : null;
    }

    /** @param array<string,mixed> $details */
    public function recordEnquiry(string $reference, array $details): void
    {
        $now = gmdate('c');
        $this->mutateJson('clients.json', [], function (array &$clients) use ($reference, $details, $now): void {
            $existing = isset($clients[$reference]) && is_array($clients[$reference]) ? $clients[$reference] : [];
            $history = isset($existing['history']) && is_array($existing['history']) ? $existing['history'] : [];
            if ($history === []) {
                $history[] = [
                    'time' => $now,
                    'status' => 'New Enquiry',
                    'note' => 'Enquiry received from the Resplendent website.',
                ];
            }

            $clients[$reference] = array_merge($existing, [
                'reference' => $reference,
                'name' => $this->text($details['name'] ?? '', 120),
                'email' => $this->text($details['email'] ?? '', 180),
                'phone' => $this->text($details['phone'] ?? '', 50),
                'company' => $this->text($details['company'] ?? '', 160),
                'service' => $this->text($details['service'] ?? '', 100),
                'destination' => $this->text($details['destination'] ?? '', 160),
                'dates' => $this->text($details['dates'] ?? '', 120),
                'message' => $this->text($details['message'] ?? '', 6000, true),
                'status' => (string)($existing['status'] ?? 'New Enquiry'),
                'created_at' => (string)($existing['created_at'] ?? $now),
                'updated_at' => $now,
                'history' => $history,
            ]);
        });
    }

    /** @param array<string,mixed> $context */
    public function recordPipelineEvent(string $reference, string $event, string $result, array $context = []): void
    {
        $now = gmdate('c');
        $this->mutateJson('clients.json', [], function (array &$clients) use ($reference, $event, $result, $context, $now): void {
            if (!isset($clients[$reference]) || !is_array($clients[$reference])) return;
            $events = isset($clients[$reference]['pipeline']) && is_array($clients[$reference]['pipeline'])
                ? $clients[$reference]['pipeline']
                : [];
            $events[] = [
                'time' => $now,
                'event' => $this->text($event, 80),
                'result' => $this->text($result, 40),
                'context' => array_map(fn($value): string => $this->text($value, 240), $context),
            ];
            $clients[$reference]['pipeline'] = array_slice($events, -30);
            $clients[$reference]['updated_at'] = $now;
        });
    }

    public function updateClient(string $reference, string $status, string $note = ''): bool
    {
        if (!in_array($status, self::STATUSES, true)) return false;
        $updated = false;
        $now = gmdate('c');
        $this->mutateJson('clients.json', [], function (array &$clients) use ($reference, $status, $note, $now, &$updated): void {
            if (!isset($clients[$reference]) || !is_array($clients[$reference])) return;
            $history = isset($clients[$reference]['history']) && is_array($clients[$reference]['history'])
                ? $clients[$reference]['history']
                : [];
            $history[] = [
                'time' => $now,
                'status' => $status,
                'note' => $this->text($note, 1000, true),
            ];
            $clients[$reference]['status'] = $status;
            $clients[$reference]['history'] = array_slice($history, -100);
            $clients[$reference]['updated_at'] = $now;
            $updated = true;
        });
        return $updated;
    }

    /** @return array<int,array<string,mixed>> */
    public function listDocuments(): array
    {
        $documents = array_values($this->readJson('documents.json', []));
        usort($documents, static fn(array $a, array $b): int =>
            strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''))
        );
        return $documents;
    }

    /** @return array<string,mixed>|null */
    public function getDocument(string $id): ?array
    {
        $documents = $this->readJson('documents.json', []);
        if (!isset($documents[$id]) || !is_array($documents[$id])) return null;
        $document = $documents[$id];
        if (!is_array($document['policy'] ?? null)) {
            $document['policy'] = DocumentPolicy::snapshot($document);
        }
        if (($document['type'] ?? '') === 'invoice' && is_array($document['payload'] ?? null)) {
            $dueDate = (string)($document['payload']['due_date'] ?? '');
            $paid = (float)($document['payload']['amount_paid'] ?? 0);
            $balance = (float)($document['payload']['balance_amount'] ?? 0);
            if ($dueDate !== '' && $dueDate < gmdate('Y-m-d') && $paid <= 0 && $balance > 0) {
                $document['payload']['invoice_status'] = 'Overdue';
            }
        }
        return $document;
    }

    /** @return array<string,string> */
    public function getPaymentSettings(): array
    {
        $defaults = [
            'bank_name' => '',
            'account_name' => '',
            'account_number' => '',
            'branch' => '',
            'swift_iban' => '',
            'currency' => '',
            'mpesa_name' => '',
            'mpesa_number' => '',
            'payment_link' => '',
            'instructions' => 'Payment instructions will be provided separately.',
            'updated_at' => '',
        ];
        $stored = $this->readJson('payment-settings.json', []);
        $settings = [];
        foreach ($defaults as $key => $default) {
            $settings[$key] = $this->text($stored[$key] ?? $default, $key === 'instructions' ? 1500 : 240, true);
        }
        return $settings;
    }

    /** @param array<string,mixed> $settings @return array<string,string> */
    public function savePaymentSettings(array $settings): array
    {
        $allowed = [
            'bank_name', 'account_name', 'account_number', 'branch', 'swift_iban',
            'currency', 'mpesa_name', 'mpesa_number', 'payment_link', 'instructions',
        ];
        $clean = [];
        foreach ($allowed as $key) {
            $clean[$key] = $this->text($settings[$key] ?? '', $key === 'instructions' ? 1500 : 240, true);
        }
        if ($clean['instructions'] === '') $clean['instructions'] = 'Payment instructions will be provided separately.';
        $clean['updated_at'] = gmdate('c');
        $this->mutateJson('payment-settings.json', [], function (array &$stored) use ($clean): void {
            $stored = $clean;
        });
        return $clean;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function saveDocument(string $type, array $payload): array
    {
        if (!in_array($type, ['proposal', 'quotation', 'invoice'], true)) {
            throw new InvalidArgumentException('Unsupported document type.');
        }

        $now = gmdate('c');
        $id = $type . '-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(3));
        $revisionSource = null;
        $revisionOfId = $this->text($payload['revision_of_id'] ?? '', 100);
        if ($type !== 'invoice' && $revisionOfId !== '') {
            $candidate = $this->getDocument($revisionOfId);
            if (is_array($candidate) && ($candidate['type'] ?? '') === $type) {
                $revisionSource = $candidate;
            }
        }

        $revisionRootId = '';
        $revisionRootNumber = '';
        $revisionSequence = 0;
        if (is_array($revisionSource)) {
            $revisionRootId = (string)($revisionSource['revision_root_id'] ?? $revisionSource['id'] ?? '');
            $revisionRootNumber = (string)($revisionSource['revision_root_number'] ?? $revisionSource['number'] ?? '');
            $revisionSequence = $this->nextRevisionSequence($revisionRootId);
            $number = $revisionRootNumber . '-R' . $revisionSequence;
        } else {
            $number = $this->nextNumber($type);
        }
        $record = [
            'id' => $id,
            'type' => $type,
            'number' => $number,
            'release' => '9.6.3',
            'client_reference' => $this->text($payload['client_reference'] ?? '', 40),
            'client_name' => $this->text($payload['client_name'] ?? '', 120),
            'company' => $this->text($payload['company'] ?? '', 160),
            'lifecycle_status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
            'payload' => $this->normalisePayload($payload),
        ];
        if (is_array($revisionSource)) {
            $record['revision_of_id'] = (string)$revisionSource['id'];
            $record['revision_of_number'] = (string)$revisionSource['number'];
            $record['revision_root_id'] = $revisionRootId;
            $record['revision_root_number'] = $revisionRootNumber;
            $record['revision_sequence'] = $revisionSequence;
        }
        $record['policy'] = DocumentPolicy::snapshot($record);

        $this->mutateJson('documents.json', [], function (array &$documents) use ($id, $record): void {
            $documents[$id] = $record;
        });

        if (is_array($revisionSource)) {
            $this->markDocumentRevised(
                (string)$revisionSource['id'],
                (string)$record['id'],
                (string)$record['number']
            );
        }

        $clientReference = (string)$record['client_reference'];
        if ($clientReference !== '') {
            if ($type === 'proposal') {
                $status = 'Proposal';
            } elseif ($type === 'quotation') {
                $status = 'Quotation';
            } else {
                $invoiceStatus = (string)($record['payload']['invoice_status'] ?? 'Issued');
                if ($invoiceStatus === 'Draft') {
                    $status = 'Approval';
                } else {
                    $status = in_array($invoiceStatus, ['Part-paid', 'Paid'], true) ? 'Payment' : 'Invoice';
                }
            }
            $note = $type === 'invoice' && (($record['payload']['invoice_status'] ?? '') === 'Draft')
                ? 'Draft invoice ' . $number . ' prepared automatically for review.'
                : ucfirst($type) . ' ' . $number . ' generated.';
            $this->updateClient($clientReference, $status, $note);
        }

        return $record;
    }

    /**
     * Prepare one secure delivery and acceptance token. Earlier outstanding
     * acceptance links for the same document are superseded automatically.
     *
     * @return array<string,mixed>
     */
    public function createDocumentDelivery(
        string $documentId,
        string $recipient,
        bool $acceptanceRequired,
        string $subject,
        string $documentHash,
        string $senderProfile = '',
        string $fromEmail = ''
    ): array {
        $document = $this->getDocument($documentId);
        if (!$document) throw new InvalidArgumentException('The document could not be found.');
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid client email is required.');
        }

        $token = $acceptanceRequired ? bin2hex(random_bytes(32)) : '';
        $id = 'delivery-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
        $now = gmdate('c');
        $record = [
            'id' => $id,
            'document_id' => $documentId,
            'document_number' => (string)($document['number'] ?? ''),
            'document_type' => (string)($document['type'] ?? ''),
            'client_reference' => (string)($document['client_reference'] ?? ''),
            'client_name' => (string)($document['client_name'] ?? ''),
            'recipient' => strtolower($recipient),
            'subject' => $this->text($subject, 240),
            'sender_profile' => $this->text($senderProfile, 40),
            'from_email' => strtolower($this->text($fromEmail, 180)),
            'acceptance_required' => $acceptanceRequired,
            'token_hash' => $token !== '' ? hash('sha256', $token) : '',
            'document_hash' => $this->text($documentHash, 128),
            'policy' => is_array($document['policy'] ?? null)
                ? $document['policy']
                : DocumentPolicy::snapshot($document),
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
            'sent_at' => '',
            'failed_at' => '',
            'accepted_at' => '',
            'changes_requested_at' => '',
            'delivery_context' => [],
            'acceptance' => [],
            'change_request' => [],
            'draft_invoice_id' => '',
            'draft_invoice_number' => '',
        ];

        $this->mutateJson('deliveries.json', [], function (array &$deliveries) use ($documentId, $id, $record, $now): void {
            foreach ($deliveries as &$delivery) {
                if (
                    is_array($delivery)
                    && ($delivery['document_id'] ?? '') === $documentId
                    && !empty($delivery['acceptance_required'])
                    && in_array((string)($delivery['status'] ?? ''), ['pending', 'sent'], true)
                ) {
                    $delivery['status'] = 'superseded';
                    $delivery['updated_at'] = $now;
                }
            }
            unset($delivery);
            $deliveries[$id] = $record;
        });

        $record['token'] = $token;
        return $record;
    }

    /** @param array<string,mixed> $context */
    public function markDocumentDelivery(string $deliveryId, string $status, array $context = []): ?array
    {
        if (!in_array($status, ['sent', 'failed'], true)) {
            throw new InvalidArgumentException('Unsupported delivery status.');
        }
        $updated = null;
        $now = gmdate('c');
        $this->mutateJson('deliveries.json', [], function (array &$deliveries) use ($deliveryId, $status, $context, $now, &$updated): void {
            if (!isset($deliveries[$deliveryId]) || !is_array($deliveries[$deliveryId])) return;
            $delivery = $deliveries[$deliveryId];
            $delivery['status'] = $status;
            $delivery['updated_at'] = $now;
            $delivery[$status === 'sent' ? 'sent_at' : 'failed_at'] = $now;
            $cleanContext = [];
            foreach ($context as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $cleanContext[$this->text($key, 80)] = $this->text($value, 500);
                }
            }
            $delivery['delivery_context'] = $cleanContext;
            $deliveries[$deliveryId] = $delivery;
            $updated = $delivery;
        });

        if (!is_array($updated)) return null;
        if ($status === 'sent' && ($updated['document_type'] ?? '') === 'invoice') {
            $this->markDraftInvoiceIssued((string)$updated['document_id']);
        }
        $this->updateDocumentDeliverySummary((string)$updated['document_id'], $updated);
        $reference = (string)($updated['client_reference'] ?? '');
        if ($reference !== '') {
            $documentType = (string)($updated['document_type'] ?? 'document');
            $workflowStatus = match ($documentType) {
                'proposal' => 'Proposal',
                'quotation' => 'Quotation',
                'invoice' => 'Invoice',
                default => 'Discovery',
            };
            if ($status === 'sent') {
                $note = ucfirst($documentType) . ' ' . (string)($updated['document_number'] ?? '')
                    . ' emailed to ' . (string)($updated['recipient'] ?? '') . '.';
                $this->updateClient($reference, $workflowStatus, $note);
            }
            $this->recordPipelineEvent($reference, 'document_email', $status === 'sent' ? 'ok' : 'failed', [
                'document' => (string)($updated['document_number'] ?? ''),
                'recipient' => (string)($updated['recipient'] ?? ''),
            ]);
        }
        return $updated;
    }

    /** @return array<int,array<string,mixed>> */
    public function listDocumentDeliveries(string $documentId): array
    {
        $deliveries = array_values(array_filter(
            $this->readJson('deliveries.json', []),
            static fn(mixed $delivery): bool =>
                is_array($delivery) && ($delivery['document_id'] ?? '') === $documentId
        ));
        usort($deliveries, static fn(array $a, array $b): int =>
            strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''))
        );
        return $deliveries;
    }

    /** @return array<string,mixed>|null */
    public function getDocumentDeliveryByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $tokenHash = hash('sha256', $token);
        foreach ($this->readJson('deliveries.json', []) as $delivery) {
            if (!is_array($delivery)) continue;
            if (hash_equals((string)($delivery['token_hash'] ?? ''), $tokenHash)) {
                $document = $this->getDocument((string)($delivery['document_id'] ?? ''));
                if (!$document) return null;
                $delivery['document'] = $document;
                return $delivery;
            }
        }
        return null;
    }

    /**
     * @return array{status:string,delivery?:array<string,mixed>,document?:array<string,mixed>,message?:string}
     */
    public function acceptDocumentDelivery(
        string $token,
        string $acceptedBy,
        string $email,
        string $ipHash,
        string $userAgent
    ): array {
        $delivery = $this->getDocumentDeliveryByToken($token);
        if (!$delivery || empty($delivery['acceptance_required'])) {
            return ['status' => 'invalid', 'message' => 'This acceptance link is not valid.'];
        }
        if (($delivery['status'] ?? '') === 'accepted') {
            return [
                'status' => 'already_accepted',
                'delivery' => $delivery,
                'document' => $delivery['document'],
            ];
        }
        if (($delivery['status'] ?? '') !== 'sent') {
            return ['status' => 'invalid', 'message' => 'This acceptance link is no longer active.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strcasecmp($email, (string)$delivery['recipient']) !== 0) {
            return ['status' => 'email_mismatch', 'message' => 'Use the email address to which the document was sent.'];
        }
        if (trim($acceptedBy) === '') {
            return ['status' => 'invalid', 'message' => 'Enter the name of the authorised person accepting the document.'];
        }

        $deliveryId = (string)$delivery['id'];
        $now = gmdate('c');
        $updated = null;
        $this->mutateJson('deliveries.json', [], function (array &$deliveries) use (
            $deliveryId,
            $acceptedBy,
            $email,
            $ipHash,
            $userAgent,
            $now,
            &$updated
        ): void {
            if (!isset($deliveries[$deliveryId]) || !is_array($deliveries[$deliveryId])) return;
            if (($deliveries[$deliveryId]['status'] ?? '') !== 'sent') {
                $updated = $deliveries[$deliveryId];
                return;
            }
            $deliveries[$deliveryId]['status'] = 'accepted';
            $deliveries[$deliveryId]['accepted_at'] = $now;
            $deliveries[$deliveryId]['updated_at'] = $now;
            $deliveries[$deliveryId]['acceptance'] = [
                'accepted_by' => $this->text($acceptedBy, 160),
                'email' => strtolower($this->text($email, 180)),
                'accepted_at_utc' => $now,
                'ip_hash' => $this->text($ipHash, 128),
                'user_agent' => $this->text($userAgent, 300),
                'document_hash' => (string)($deliveries[$deliveryId]['document_hash'] ?? ''),
                'policy_version' => (string)($deliveries[$deliveryId]['policy']['version'] ?? ''),
            ];
            $updated = $deliveries[$deliveryId];
        });

        if (!is_array($updated) || ($updated['status'] ?? '') !== 'accepted') {
            return ['status' => 'invalid', 'message' => 'This acceptance could not be recorded.'];
        }

        $document = $this->getDocument((string)$updated['document_id']);
        $draftInvoice = is_array($document)
            ? $this->createDraftInvoiceFromAcceptedDocument($document, $updated)
            : null;
        if (is_array($draftInvoice)) {
            $updated['draft_invoice_id'] = (string)($draftInvoice['id'] ?? '');
            $updated['draft_invoice_number'] = (string)($draftInvoice['number'] ?? '');
            $this->linkDraftInvoice(
                (string)$updated['id'],
                (string)$updated['document_id'],
                $draftInvoice
            );
        }
        $this->updateDocumentDeliverySummary((string)$updated['document_id'], $updated);
        $reference = (string)($updated['client_reference'] ?? '');
        if ($reference !== '') {
            $this->updateClient(
                $reference,
                'Approval',
                (string)($updated['document_number'] ?? 'Document') . ' accepted by '
                    . $this->text($acceptedBy, 160) . '.'
            );
            $this->recordPipelineEvent($reference, 'client_acceptance', 'ok', [
                'document' => (string)($updated['document_number'] ?? ''),
                'accepted_by' => $this->text($acceptedBy, 160),
                'draft_invoice' => (string)($updated['draft_invoice_number'] ?? ''),
            ]);
        }

        return [
            'status' => 'accepted',
            'delivery' => $updated,
            'document' => $document ?? [],
            'draft_invoice' => $draftInvoice ?? [],
        ];
    }

    /**
     * Create or recover the linked invoice for an accepted proposal or
     * quotation whose acceptance predates automatic draft generation.
     *
     * @return array{status:string,document?:array<string,mixed>,invoice?:array<string,mixed>,message?:string}
     */
    public function createLinkedDraftInvoice(string $documentId): array
    {
        $document = $this->getDocument($documentId);
        if (!is_array($document)) {
            return ['status' => 'not_found', 'message' => 'The accepted document could not be found.'];
        }
        if (!in_array((string)($document['type'] ?? ''), ['proposal', 'quotation'], true)) {
            return ['status' => 'not_eligible', 'message' => 'Only accepted proposals and quotations can create a linked draft invoice.'];
        }
        if (($document['lifecycle_status'] ?? 'active') !== 'active') {
            return ['status' => 'not_eligible', 'message' => 'This document is no longer the active accepted version.'];
        }

        $acceptedDelivery = null;
        foreach ($this->listDocumentDeliveries($documentId) as $delivery) {
            if (is_array($delivery) && ($delivery['status'] ?? '') === 'accepted') {
                $acceptedDelivery = $delivery;
                break;
            }
        }
        if (!is_array($acceptedDelivery)) {
            return ['status' => 'not_accepted', 'message' => 'The document must be accepted before an invoice can be created.'];
        }

        $existing = $this->findLinkedInvoice($document);
        $invoice = $existing ?? $this->createDraftInvoiceFromAcceptedDocument($document, $acceptedDelivery);
        if (!is_array($invoice)) {
            return ['status' => 'error', 'message' => 'The linked draft invoice could not be created.'];
        }

        $this->linkDraftInvoice(
            (string)($acceptedDelivery['id'] ?? ''),
            $documentId,
            $invoice
        );
        $acceptedDelivery['draft_invoice_id'] = (string)($invoice['id'] ?? '');
        $acceptedDelivery['draft_invoice_number'] = (string)($invoice['number'] ?? '');
        $this->updateDocumentDeliverySummary($documentId, $acceptedDelivery);

        $reference = (string)($document['client_reference'] ?? '');
        $result = is_array($existing) ? 'existing' : 'created';
        if ($reference !== '') {
            $this->recordPipelineEvent($reference, 'linked_draft_invoice', $result, [
                'document' => (string)($document['number'] ?? ''),
                'invoice' => (string)($invoice['number'] ?? ''),
            ]);
        }

        return [
            'status' => $result,
            'document' => $document,
            'invoice' => $invoice,
        ];
    }

    /**
     * Record a client's request for a revised document. A sent or accepted
     * version may receive a change request; any unsent automatic draft invoice
     * linked to that version is superseded.
     *
     * @return array{status:string,delivery?:array<string,mixed>,document?:array<string,mixed>,message?:string}
     */
    public function requestDocumentChanges(
        string $token,
        string $requestedBy,
        string $email,
        string $notes,
        string $ipHash,
        string $userAgent
    ): array {
        $delivery = $this->getDocumentDeliveryByToken($token);
        if (!$delivery || empty($delivery['acceptance_required'])) {
            return ['status' => 'invalid', 'message' => 'This document link is not valid.'];
        }
        if (($delivery['document']['lifecycle_status'] ?? 'active') === 'superseded') {
            return ['status' => 'invalid', 'message' => 'A newer revision of this document is now active.'];
        }
        if (($delivery['status'] ?? '') === 'changes_requested') {
            return [
                'status' => 'already_requested',
                'delivery' => $delivery,
                'document' => $delivery['document'],
            ];
        }
        if (!in_array((string)($delivery['status'] ?? ''), ['sent', 'accepted'], true)) {
            return ['status' => 'invalid', 'message' => 'This document link is no longer active.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strcasecmp($email, (string)$delivery['recipient']) !== 0) {
            return ['status' => 'email_mismatch', 'message' => 'Use the email address to which the document was sent.'];
        }
        if (trim($requestedBy) === '') {
            return ['status' => 'invalid', 'message' => 'Enter the name of the person requesting the revision.'];
        }
        if (trim($notes) === '') {
            return ['status' => 'invalid', 'message' => 'Describe the changes you would like us to review.'];
        }

        $deliveryId = (string)$delivery['id'];
        $now = gmdate('c');
        $updated = null;
        $this->mutateJson('deliveries.json', [], function (array &$deliveries) use (
            $deliveryId,
            $requestedBy,
            $email,
            $notes,
            $ipHash,
            $userAgent,
            $now,
            &$updated
        ): void {
            if (!isset($deliveries[$deliveryId]) || !is_array($deliveries[$deliveryId])) return;
            $currentStatus = (string)($deliveries[$deliveryId]['status'] ?? '');
            if (!in_array($currentStatus, ['sent', 'accepted'], true)) {
                $updated = $deliveries[$deliveryId];
                return;
            }
            $deliveries[$deliveryId]['previous_status'] = $currentStatus;
            $deliveries[$deliveryId]['status'] = 'changes_requested';
            $deliveries[$deliveryId]['changes_requested_at'] = $now;
            $deliveries[$deliveryId]['updated_at'] = $now;
            $deliveries[$deliveryId]['change_request'] = [
                'requested_by' => $this->text($requestedBy, 160),
                'email' => strtolower($this->text($email, 180)),
                'notes' => $this->text($notes, 3000, true),
                'requested_at_utc' => $now,
                'ip_hash' => $this->text($ipHash, 128),
                'user_agent' => $this->text($userAgent, 300),
                'document_hash' => (string)($deliveries[$deliveryId]['document_hash'] ?? ''),
            ];
            $updated = $deliveries[$deliveryId];
        });

        if (!is_array($updated) || ($updated['status'] ?? '') !== 'changes_requested') {
            return ['status' => 'invalid', 'message' => 'This revision request could not be recorded.'];
        }

        $documentId = (string)$updated['document_id'];
        $document = $this->getDocument($documentId);
        $this->markDocumentRevisionRequested($documentId, $updated);
        $this->supersedeDraftInvoicesForSource(
            $documentId,
            'Superseded after the client requested changes to '
                . (string)($updated['document_number'] ?? 'the accepted document') . '.'
        );
        $this->updateDocumentDeliverySummary($documentId, $updated);

        $reference = (string)($updated['client_reference'] ?? '');
        if ($reference !== '') {
            $this->updateClient(
                $reference,
                'Revision Requested',
                (string)($updated['document_number'] ?? 'Document') . ' revision requested by '
                    . $this->text($requestedBy, 160) . '.'
            );
            $this->recordPipelineEvent($reference, 'document_change_request', 'ok', [
                'document' => (string)($updated['document_number'] ?? ''),
                'requested_by' => $this->text($requestedBy, 160),
            ]);
        }

        return [
            'status' => 'changes_requested',
            'delivery' => $updated,
            'document' => $document ?? [],
        ];
    }

    /** @return array<string,mixed>|null */
    private function createDraftInvoiceFromAcceptedDocument(array $document, array $delivery): ?array
    {
        if (!in_array((string)($document['type'] ?? ''), ['proposal', 'quotation'], true)) return null;

        $existing = $this->findLinkedInvoice($document);
        if (is_array($existing)) return $existing;

        $payload = is_array($document['payload'] ?? null) ? $document['payload'] : [];
        $sourceType = strtolower(trim((string)(
            $payload['proposal_type']
            ?? $payload['quotation_type']
            ?? ''
        )));
        $business = str_contains($sourceType, 'business');
        $currency = $business ? 'USD' : strtoupper($this->text($payload['currency'] ?? 'USD', 8));
        if (!in_array($currency, ['USD', 'KES', 'EUR', 'GBP'], true)) $currency = 'USD';

        $items = [];
        $discount = 0.0;
        $serviceFee = 0.0;
        if ($business) {
            $items[] = [
                'description' => 'Business matchmaking - engagement and research commencement',
                'quantity' => '1',
                'unit_price' => '250.00',
                'total' => '250.00',
            ];
            $total = 250.0;
        } elseif (($document['type'] ?? '') === 'quotation') {
            $items = $this->normaliseInvoiceItems(is_array($payload['items'] ?? null) ? $payload['items'] : []);
            $discount = $this->moneyValue($payload['discount'] ?? 0);
            $serviceFee = $this->moneyValue($payload['service_fee'] ?? 0);
            $subtotal = array_reduce(
                $items,
                static fn(float $sum, array $item): float => $sum + (float)($item['total'] ?? 0),
                0.0
            );
            $total = max(0, $subtotal - $discount + $serviceFee);
            $statedTotal = $this->moneyValue($payload['grand_total'] ?? 0);
            if ($statedTotal > 0) $total = $statedTotal;
        } else {
            $flight = $this->moneyValue($payload['flight_price'] ?? 0);
            $hotel = $this->moneyValue($payload['hotel_price'] ?? 0);
            $additional = $this->moneyValue($payload['additional_price'] ?? 0);
            $serviceFee = $this->moneyValue($payload['service_fee'] ?? 0);
            if ($flight > 0) {
                $description = 'Flight arrangement';
                $airline = $this->text($payload['airline'] ?? '', 120);
                $route = $this->text($payload['route'] ?? '', 180);
                if ($airline !== '') $description .= ' - ' . $airline;
                if ($route !== '') $description .= ' (' . $route . ')';
                $items[] = $this->invoiceItem($description, $flight);
            }
            if ($hotel > 0) {
                $description = 'Accommodation arrangement';
                $hotelName = $this->text($payload['hotel'] ?? '', 160);
                if ($hotelName !== '') $description .= ' - ' . $hotelName;
                $items[] = $this->invoiceItem($description, $hotel);
            }
            if ($additional > 0) $items[] = $this->invoiceItem('Additional accepted services', $additional);
            $subtotal = $flight + $hotel + $additional;
            $total = max(0, $subtotal + $serviceFee);
            $statedTotal = $this->moneyValue($payload['grand_total'] ?? 0);
            if ($statedTotal > 0) {
                if ($statedTotal > $total + 0.005) {
                    $items[] = $this->invoiceItem('Accepted proposal balance', $statedTotal - $total);
                }
                $total = $statedTotal;
            }
        }

        if ($items === []) {
            $total = max(0, $this->moneyValue($payload['grand_total'] ?? 0));
            $items[] = $this->invoiceItem('Accepted professional services', $total);
        }

        $invoiceType = $business
            ? 'Business Matchmaking'
            : (str_contains($sourceType, 'corporate') ? 'Corporate Travel' : 'Travel & Services');
        $invoicePayload = [
            'client_reference' => (string)($document['client_reference'] ?? ''),
            'source_document_id' => (string)($document['id'] ?? ''),
            'source_document_number' => (string)($document['number'] ?? ''),
            'source_document_type' => (string)($document['type'] ?? ''),
            'source_document_hash' => (string)($delivery['document_hash'] ?? ''),
            'accepted_delivery_id' => (string)($delivery['id'] ?? ''),
            'auto_generated' => '1',
            'draft_created_at' => gmdate('c'),
            'invoice_type' => $invoiceType,
            'business_milestone' => $business ? 'engagement' : '',
            'invoice_date' => gmdate('Y-m-d'),
            'due_date' => gmdate('Y-m-d', strtotime('+7 days')),
            'currency' => $currency,
            'client_name' => (string)($document['client_name'] ?? $payload['client_name'] ?? ''),
            'company' => (string)($document['company'] ?? $payload['company'] ?? ''),
            'email' => (string)($payload['email'] ?? $delivery['recipient'] ?? ''),
            'phone' => (string)($payload['phone'] ?? ''),
            'items' => $items,
            'subtotal' => number_format(array_reduce(
                $items,
                static fn(float $sum, array $item): float => $sum + (float)($item['total'] ?? 0),
                0.0
            ), 2, '.', ''),
            'discount' => number_format($discount, 2, '.', ''),
            'service_fee' => number_format($serviceFee, 2, '.', ''),
            'total_amount' => number_format($total, 2, '.', ''),
            'grand_total' => $currency . ' ' . number_format($total, 2),
            'amount_paid' => '0.00',
            'balance_amount' => number_format($total, 2, '.', ''),
            'invoice_status' => 'Draft',
            'payment_settings' => $this->getPaymentSettings(),
            'invoice_note' => 'Prepared from accepted '
                . (string)($document['number'] ?? 'document')
                . '. Review before sending to the client.',
        ];

        return $this->saveDocument('invoice', $invoicePayload);
    }

    /** @return array<string,mixed>|null */
    private function findLinkedInvoice(array $document): ?array
    {
        $sourceId = (string)($document['id'] ?? '');
        $sourceNumber = (string)($document['number'] ?? '');
        foreach ($this->readJson('documents.json', []) as $existing) {
            if (!is_array($existing) || ($existing['type'] ?? '') !== 'invoice') continue;
            $payload = is_array($existing['payload'] ?? null) ? $existing['payload'] : [];
            if (($payload['invoice_status'] ?? '') === 'Superseded') continue;
            $matches = ($payload['source_document_id'] ?? '') === $sourceId
                || (
                    $sourceNumber !== ''
                    && in_array(
                        $sourceNumber,
                        [
                            (string)($payload['source_document_number'] ?? ''),
                            (string)($payload['quotation_number'] ?? ''),
                        ],
                        true
                    )
                );
            if ($matches) return $this->getDocument((string)($existing['id'] ?? ''));
        }
        return null;
    }

    /** @return array<int,array<string,string>> */
    private function normaliseInvoiceItems(array $items): array
    {
        $normalised = [];
        foreach (array_slice($items, 0, 50) as $item) {
            if (!is_array($item)) continue;
            $description = $this->text($item['description'] ?? '', 300);
            $quantity = max(0, $this->moneyValue($item['quantity'] ?? 0));
            $unitPrice = max(0, $this->moneyValue($item['unit_price'] ?? 0));
            $total = max(0, $this->moneyValue($item['total'] ?? ($quantity * $unitPrice)));
            if ($description === '') continue;
            $normalised[] = [
                'description' => $description,
                'quantity' => number_format($quantity, 0, '.', ''),
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'total' => number_format($total, 2, '.', ''),
            ];
        }
        return $normalised;
    }

    /** @return array<string,string> */
    private function invoiceItem(string $description, float $amount): array
    {
        return [
            'description' => $this->text($description, 300),
            'quantity' => '1',
            'unit_price' => number_format(max(0, $amount), 2, '.', ''),
            'total' => number_format(max(0, $amount), 2, '.', ''),
        ];
    }

    private function moneyValue(mixed $value): float
    {
        $normalised = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string)$value)) ?? '0';
        return is_numeric($normalised) ? (float)$normalised : 0.0;
    }

    private function linkDraftInvoice(string $deliveryId, string $documentId, array $invoice): void
    {
        $invoiceId = (string)($invoice['id'] ?? '');
        $invoiceNumber = (string)($invoice['number'] ?? '');
        $invoiceStatus = (string)($invoice['payload']['invoice_status'] ?? 'Draft');
        $this->mutateJson('deliveries.json', [], function (array &$deliveries) use (
            $deliveryId,
            $invoiceId,
            $invoiceNumber
        ): void {
            if (!isset($deliveries[$deliveryId]) || !is_array($deliveries[$deliveryId])) return;
            $deliveries[$deliveryId]['draft_invoice_id'] = $invoiceId;
            $deliveries[$deliveryId]['draft_invoice_number'] = $invoiceNumber;
            $deliveries[$deliveryId]['updated_at'] = gmdate('c');
        });
        $this->mutateJson('documents.json', [], function (array &$documents) use (
            $documentId,
            $invoiceId,
            $invoiceNumber,
            $invoiceStatus
        ): void {
            if (!isset($documents[$documentId]) || !is_array($documents[$documentId])) return;
            $documents[$documentId]['draft_invoice'] = [
                'id' => $invoiceId,
                'number' => $invoiceNumber,
                'status' => $invoiceStatus,
            ];
            $documents[$documentId]['updated_at'] = gmdate('c');
        });
    }

    private function markDocumentRevisionRequested(string $documentId, array $delivery): void
    {
        $this->mutateJson('documents.json', [], function (array &$documents) use ($documentId, $delivery): void {
            if (!isset($documents[$documentId]) || !is_array($documents[$documentId])) return;
            $documents[$documentId]['lifecycle_status'] = 'revision_requested';
            $documents[$documentId]['change_request'] = [
                'delivery_id' => (string)($delivery['id'] ?? ''),
                'requested_at' => (string)($delivery['changes_requested_at'] ?? ''),
                'requested_by' => (string)($delivery['change_request']['requested_by'] ?? ''),
                'notes' => (string)($delivery['change_request']['notes'] ?? ''),
            ];
            $documents[$documentId]['updated_at'] = gmdate('c');
        });
    }

    private function supersedeDraftInvoicesForSource(string $sourceDocumentId, string $reason): void
    {
        $now = gmdate('c');
        $this->mutateJson('documents.json', [], function (array &$documents) use (
            $sourceDocumentId,
            $reason,
            $now
        ): void {
            foreach ($documents as &$document) {
                if (
                    !is_array($document)
                    || ($document['type'] ?? '') !== 'invoice'
                    || ($document['payload']['source_document_id'] ?? '') !== $sourceDocumentId
                    || ($document['payload']['invoice_status'] ?? '') !== 'Draft'
                ) {
                    continue;
                }
                $document['payload']['invoice_status'] = 'Superseded';
                $document['payload']['superseded_at'] = $now;
                $document['payload']['superseded_reason'] = $this->text($reason, 1000, true);
                $document['lifecycle_status'] = 'superseded';
                $document['updated_at'] = $now;
            }
            unset($document);
            if (isset($documents[$sourceDocumentId]) && is_array($documents[$sourceDocumentId])) {
                if (is_array($documents[$sourceDocumentId]['draft_invoice'] ?? null)) {
                    $documents[$sourceDocumentId]['draft_invoice']['status'] = 'Superseded';
                }
                $documents[$sourceDocumentId]['updated_at'] = $now;
            }
        });
    }

    private function markDocumentRevised(string $sourceId, string $revisionId, string $revisionNumber): void
    {
        $now = gmdate('c');
        $this->mutateJson('documents.json', [], function (array &$documents) use (
            $sourceId,
            $revisionId,
            $revisionNumber,
            $now
        ): void {
            if (!isset($documents[$sourceId]) || !is_array($documents[$sourceId])) return;
            $documents[$sourceId]['lifecycle_status'] = 'superseded';
            $documents[$sourceId]['superseded_by_id'] = $revisionId;
            $documents[$sourceId]['superseded_by_number'] = $revisionNumber;
            $documents[$sourceId]['updated_at'] = $now;
        });
        $this->mutateJson('deliveries.json', [], function (array &$deliveries) use ($sourceId, $now): void {
            foreach ($deliveries as &$delivery) {
                if (
                    is_array($delivery)
                    && ($delivery['document_id'] ?? '') === $sourceId
                    && in_array((string)($delivery['status'] ?? ''), ['pending', 'sent'], true)
                ) {
                    $delivery['status'] = 'superseded';
                    $delivery['updated_at'] = $now;
                }
            }
            unset($delivery);
        });
        $this->supersedeDraftInvoicesForSource(
            $sourceId,
            'Superseded when revision ' . $revisionNumber . ' was created.'
        );
    }

    private function nextRevisionSequence(string $rootId): int
    {
        $sequence = 0;
        foreach ($this->readJson('documents.json', []) as $document) {
            if (!is_array($document)) continue;
            $candidateRoot = (string)($document['revision_root_id'] ?? $document['id'] ?? '');
            if ($candidateRoot !== $rootId) continue;
            $sequence = max($sequence, (int)($document['revision_sequence'] ?? 0));
        }
        return $sequence + 1;
    }

    private function markDraftInvoiceIssued(string $invoiceId): void
    {
        $this->mutateJson('documents.json', [], function (array &$documents) use ($invoiceId): void {
            if (!isset($documents[$invoiceId]) || !is_array($documents[$invoiceId])) return;
            if (($documents[$invoiceId]['type'] ?? '') !== 'invoice') return;
            if (($documents[$invoiceId]['payload']['invoice_status'] ?? '') !== 'Draft') return;
            $documents[$invoiceId]['payload']['invoice_status'] = 'Issued';
            $documents[$invoiceId]['payload']['issued_at'] = gmdate('c');
            $documents[$invoiceId]['updated_at'] = gmdate('c');
        });
    }

    /** @return array<string,mixed>|null */
    public function updateInvoicePayment(string $id, float $amountPaid): ?array
    {
        $updated = null;
        $now = gmdate('c');
        $this->mutateJson('documents.json', [], function (array &$documents) use ($id, $amountPaid, $now, &$updated): void {
            if (!isset($documents[$id]) || !is_array($documents[$id]) || ($documents[$id]['type'] ?? '') !== 'invoice') return;
            $payload = is_array($documents[$id]['payload'] ?? null) ? $documents[$id]['payload'] : [];
            $total = max(0, (float)($payload['total_amount'] ?? 0));
            $paid = min($total, max(0, $amountPaid));
            $balance = max(0, $total - $paid);
            $dueDate = (string)($payload['due_date'] ?? '');
            $currentStatus = (string)($payload['invoice_status'] ?? 'Issued');
            if ($currentStatus === 'Superseded') {
                return;
            } elseif ($currentStatus === 'Draft' && $paid <= 0) {
                $status = 'Draft';
            } elseif ($balance <= 0.005 && $total > 0) {
                $status = 'Paid';
            } elseif ($paid > 0) {
                $status = 'Part-paid';
            } elseif ($dueDate !== '' && $dueDate < gmdate('Y-m-d')) {
                $status = 'Overdue';
            } else {
                $status = 'Issued';
            }
            $payload['amount_paid'] = number_format($paid, 2, '.', '');
            $payload['balance_amount'] = number_format($balance, 2, '.', '');
            $payload['invoice_status'] = $status;
            $documents[$id]['payload'] = $payload;
            $documents[$id]['updated_at'] = $now;
            $updated = $documents[$id];
        });

        if (is_array($updated) && !empty($updated['client_reference'])) {
            $invoiceStatus = (string)($updated['payload']['invoice_status'] ?? 'Issued');
            $workflowStatus = in_array($invoiceStatus, ['Part-paid', 'Paid'], true) ? 'Payment' : 'Invoice';
            $this->updateClient(
                (string)$updated['client_reference'],
                $workflowStatus,
                'Invoice ' . (string)($updated['number'] ?? '') . ' marked ' . $invoiceStatus . '.'
            );
        }
        return $updated;
    }

    /** @param array<string,mixed> $delivery */
    private function updateDocumentDeliverySummary(string $documentId, array $delivery): void
    {
        $this->mutateJson('documents.json', [], function (array &$documents) use ($documentId, $delivery): void {
            if (!isset($documents[$documentId]) || !is_array($documents[$documentId])) return;
            $documents[$documentId]['delivery'] = [
                'id' => (string)($delivery['id'] ?? ''),
                'recipient' => (string)($delivery['recipient'] ?? ''),
                'status' => (string)($delivery['status'] ?? ''),
                'acceptance_required' => !empty($delivery['acceptance_required']),
                'sent_at' => (string)($delivery['sent_at'] ?? ''),
                'accepted_at' => (string)($delivery['accepted_at'] ?? ''),
                'changes_requested_at' => (string)($delivery['changes_requested_at'] ?? ''),
                'document_hash' => (string)($delivery['document_hash'] ?? ''),
                'sender_profile' => (string)($delivery['sender_profile'] ?? ''),
                'from_email' => (string)($delivery['from_email'] ?? ''),
                'draft_invoice_id' => (string)($delivery['draft_invoice_id'] ?? ''),
                'draft_invoice_number' => (string)($delivery['draft_invoice_number'] ?? ''),
            ];
            $documents[$documentId]['updated_at'] = gmdate('c');
        });
    }

    private function nextNumber(string $type): string
    {
        $year = gmdate('Y');
        $key = $type . '-' . $year;
        $next = 1;
        $this->mutateJson('counters.json', [], function (array &$counters) use ($key, &$next): void {
            $next = max(1, ((int)($counters[$key] ?? 0)) + 1);
            $counters[$key] = $next;
        });
        $prefix = match ($type) {
            'quotation' => 'RGT-Q',
            'invoice' => 'RGT-I',
            default => 'RGT',
        };
        return sprintf('%s-%s-%06d', $prefix, $year, $next);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function normalisePayload(array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            $safeKey = preg_replace('/[^a-z0-9_]/i', '', (string)$key) ?: '';
            if ($safeKey === '') continue;
            if (is_array($value)) {
                $clean[$safeKey] = $this->normaliseArray($value);
            } elseif (is_scalar($value) || $value === null) {
                $clean[$safeKey] = $this->text($value ?? '', 8000, true);
            }
        }
        return $clean;
    }

    /** @param array<mixed> $values @return array<mixed> */
    private function normaliseArray(array $values): array
    {
        $normalised = [];
        foreach (array_slice($values, 0, 100) as $key => $value) {
            if (is_array($value)) {
                $normalised[$key] = $this->normaliseArray($value);
            } elseif (is_scalar($value) || $value === null) {
                $normalised[$key] = $this->text($value ?? '', 3000, true);
            }
        }
        return $normalised;
    }

    private function text(mixed $value, int $max, bool $preserveLines = false): string
    {
        $value = trim(strip_tags((string)$value));
        if (!$preserveLines) $value = preg_replace('/[\r\n]+/', ' ', $value) ?? '';
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    private function ensureDirectory(string $directory): bool
    {
        if (!is_dir($directory) && !@mkdir($directory, 0750, true)) return false;
        if (!is_writable($directory)) return false;
        @file_put_contents($directory . '/.htaccess', "Require all denied\nDeny from all\nOptions -Indexes\n", LOCK_EX);
        @file_put_contents($directory . '/index.html', '', LOCK_EX);
        return true;
    }

    /** @return array<mixed> */
    private function readJson(string $filename, array $default): array
    {
        $path = $this->directory . '/' . $filename;
        if (!is_file($path)) return $default;
        $handle = @fopen($path, 'rb');
        if ($handle === false) return $default;
        flock($handle, LOCK_SH);
        $contents = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        $decoded = json_decode((string)$contents, true);
        return is_array($decoded) ? $decoded : $default;
    }

    private function mutateJson(string $filename, array $default, callable $mutation): void
    {
        $path = $this->directory . '/' . $filename;
        $handle = @fopen($path, 'c+');
        if ($handle === false) throw new RuntimeException('Could not open the operations data file.');
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new RuntimeException('Could not lock the operations data file.');
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        $decoded = json_decode((string)$contents, true);
        $data = is_array($decoded) ? $decoded : $default;
        $mutation($data);
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            flock($handle, LOCK_UN);
            fclose($handle);
            throw new RuntimeException('Could not encode operations data.');
        }
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, $encoded . PHP_EOL);
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        @chmod($path, 0640);
    }
}
