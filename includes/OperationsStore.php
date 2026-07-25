<?php
declare(strict_types=1);

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
        $number = $this->nextNumber($type);
        $record = [
            'id' => $id,
            'type' => $type,
            'number' => $number,
            'client_reference' => $this->text($payload['client_reference'] ?? '', 40),
            'client_name' => $this->text($payload['client_name'] ?? '', 120),
            'company' => $this->text($payload['company'] ?? '', 160),
            'created_at' => $now,
            'updated_at' => $now,
            'payload' => $this->normalisePayload($payload),
        ];

        $this->mutateJson('documents.json', [], function (array &$documents) use ($id, $record): void {
            $documents[$id] = $record;
        });

        $clientReference = (string)$record['client_reference'];
        if ($clientReference !== '') {
            if ($type === 'proposal') {
                $status = 'Proposal';
            } elseif ($type === 'quotation') {
                $status = 'Quotation';
            } else {
                $invoiceStatus = (string)($record['payload']['invoice_status'] ?? 'Issued');
                $status = in_array($invoiceStatus, ['Part-paid', 'Paid'], true) ? 'Payment' : 'Invoice';
            }
            $this->updateClient($clientReference, $status, ucfirst($type) . ' ' . $number . ' generated.');
        }

        return $record;
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
            if ($balance <= 0.005 && $total > 0) {
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
