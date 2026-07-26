<?php
declare(strict_types=1);

require_once __DIR__ . '/DocumentPolicy.php';

/**
 * Dependency-free, server-side A4 PDF renderer for Resplendent documents.
 *
 * It deliberately uses PDF core fonts and a bundled JPEG rendering of the
 * approved logo so it works on ordinary shared PHP hosting without Composer,
 * browser automation or a third-party document service.
 */
final class PdfDocumentGenerator
{
    private const PAGE_WIDTH = 595.28;
    private const PAGE_HEIGHT = 841.89;
    private const LEFT = 52.0;
    private const RIGHT = 543.0;
    private const CONTENT_BOTTOM = 770.0;

    /** @var string[] */
    private array $pages = [];
    private string $content = '';
    private float $y = 112.0;

    public function __construct(private readonly string $logoPath)
    {
        if (!is_file($logoPath)) {
            throw new RuntimeException('The approved PDF logo asset is unavailable.');
        }
    }

    /** @param array<string,mixed> $document */
    public function render(array $document): string
    {
        $this->pages = [];
        $this->content = '';
        $this->y = 112.0;
        $this->newPage();

        $payload = is_array($document['payload'] ?? null) ? $document['payload'] : [];
        $type = (string)($document['type'] ?? 'proposal');
        $title = match ($type) {
            'quotation' => 'EXECUTIVE QUOTATION',
            'invoice' => 'INVOICE',
            default => 'EXECUTIVE PROPOSAL',
        };
        $service = match ($type) {
            'quotation' => $this->value($payload, 'quotation_type', 'Travel & Services'),
            'invoice' => $this->value($payload, 'invoice_type', 'Travel & Services'),
            default => $this->value($payload, 'proposal_type', 'Luxury Travel'),
        };

        $this->titleBlock(
            $title,
            (string)($document['number'] ?? ''),
            $service,
            $this->value($payload, 'client_name', 'Client'),
            $this->value($payload, 'company', '')
        );

        if ($type === 'proposal') {
            $this->renderProposal($document, $payload);
        } elseif ($type === 'quotation') {
            $this->renderQuotation($document, $payload);
        } else {
            $this->renderInvoice($document, $payload);
        }

        $policy = is_array($document['policy'] ?? null)
            ? $document['policy']
            : DocumentPolicy::snapshot($document);
        $terms = is_array($policy['terms'] ?? null) ? $policy['terms'] : [];
        if ($terms !== []) {
            $policyHeight = 38.0;
            foreach ($terms as $term) {
                $policyHeight += count($this->wrap((string)$term, 491, 8.2)) * 12.5 + 6;
            }
            $this->ensureSpace($policyHeight);
            $this->sectionHeading('IMPORTANT TERMS');
            foreach ($terms as $term) {
                $this->paragraph((string)$term, 8.2, 'F4', [0.31, 0.34, 0.38], 12.5);
                $this->y += 2;
            }
        }

        $this->finishPage();
        return $this->buildPdf();
    }

    /** @param array<string,mixed> $document @param array<string,mixed> $payload */
    private function renderProposal(array $document, array $payload): void
    {
        $this->metaRow([
            'Prepared' => $this->displayDate((string)($payload['proposal_date'] ?? $document['created_at'] ?? '')),
            'Client contact' => $this->value($payload, 'email', 'Not provided'),
        ]);
        $this->paragraph($this->value($payload, 'introduction', 'Thank you for the opportunity to consider your requirements.'), 10.5, 'F3', [0.10, 0.14, 0.20], 16);

        $business = (string)($payload['proposal_type'] ?? '') === 'Global Business Connections';
        if ($business) {
            $this->sectionHeading('01  BUSINESS CONNECTION MANDATE');
            $this->keyValues([
                'Market' => $this->value($payload, 'target_market'),
                'Partner profile' => $this->value($payload, 'partner_profile'),
                'Objective' => $this->value($payload, 'engagement_objective'),
            ]);
            $this->paragraph($this->value($payload, 'business_approach', 'The engagement approach will be confirmed with the client.'));
        } else {
            $this->sectionHeading('01  FLIGHT RECOMMENDATION');
            $this->keyValues([
                'Airline' => $this->value($payload, 'airline'),
                'Route' => $this->value($payload, 'route'),
                'Cabin' => $this->value($payload, 'cabin'),
            ]);
            $this->paragraph($this->value($payload, 'flight_recommendation', 'Recommendation to be confirmed.'));

            $this->sectionHeading('02  ACCOMMODATION');
            $this->keyValues([
                'Hotel' => $this->value($payload, 'hotel'),
                'Room & plan' => trim($this->value($payload, 'room') . ' / ' . $this->value($payload, 'meal_plan')),
                'Stay' => $this->value($payload, 'nights') . ' nights',
            ]);
            $this->paragraph($this->value($payload, 'hotel_recommendation', 'Recommendation to be confirmed.'));
        }

        $this->sectionHeading(($business ? '02' : '03') . '  INCLUDED SERVICES');
        $services = is_array($payload['included_services'] ?? null) ? $payload['included_services'] : [];
        if ($services === []) $services = ['Services to be confirmed'];
        foreach ($services as $service) $this->bullet((string)$service);

        $currency = (string)($payload['currency'] ?? 'USD');
        $this->investmentBox(
            'TOTAL INVESTMENT',
            $this->value($payload, 'grand_total', $currency . ' 0.00'),
            $business
                ? 'Payment schedule: USD 250 on engagement, USD 150 at the mid-project milestone and USD 100 on final delivery.'
                : $this->value($payload, 'investment_note', '')
        );
    }

    /** @param array<string,mixed> $document @param array<string,mixed> $payload */
    private function renderQuotation(array $document, array $payload): void
    {
        $this->metaRow([
            'Prepared' => $this->displayDate((string)($payload['quotation_date'] ?? $document['created_at'] ?? '')),
            'Valid until' => $this->displayDate((string)($payload['valid_until'] ?? '')),
            'Client contact' => $this->value($payload, 'email', 'Not provided'),
        ]);
        $this->sectionHeading('01  INVESTMENT SCHEDULE');
        $business = (string)($payload['quotation_type'] ?? '') === 'Business Matchmaking';
        $currency = (string)($payload['currency'] ?? 'USD');
        $rows = [];
        if ($business) {
            $rows = [
                ['On acceptance - engagement and research commencement', '1', 'USD 250.00'],
                ['Mid-project research and outreach milestone', '1', 'USD 150.00'],
                ['Final delivery of agreed engagement output', '1', 'USD 100.00'],
            ];
        } else {
            foreach ((array)($payload['items'] ?? []) as $item) {
                if (!is_array($item)) continue;
                $rows[] = [
                    (string)($item['description'] ?? ''),
                    (string)($item['quantity'] ?? '1'),
                    $this->money($currency, $item['total'] ?? 0),
                ];
            }
            if ((float)($payload['service_fee'] ?? 0) > 0) {
                $rows[] = ['Professional service fee', '1', $this->money($currency, $payload['service_fee'])];
            }
            if ((float)($payload['discount'] ?? 0) > 0) {
                $rows[] = ['Discount', '-', '-' . $this->money($currency, $payload['discount'])];
            }
        }
        $this->table($rows);
        $this->investmentBox('TOTAL INVESTMENT', $this->value($payload, 'grand_total', $currency . ' 0.00'));

        $this->sectionHeading('02  TERMS');
        $this->paragraph($this->value($payload, 'scope_note', 'Services are limited to the items described in this quotation.'));
        $this->paragraph($this->value($payload, 'payment_terms', 'Payment is required before confirmation.'));

        $this->sectionHeading('03  PAYMENT');
        $this->paragraph($this->value($payload, 'payment_details', 'Payment instructions will be provided on acceptance.'));
    }

    /** @param array<string,mixed> $document @param array<string,mixed> $payload */
    private function renderInvoice(array $document, array $payload): void
    {
        $this->metaRow([
            'Issued' => $this->displayDate((string)($payload['invoice_date'] ?? $document['created_at'] ?? '')),
            'Due date' => $this->displayDate((string)($payload['due_date'] ?? '')),
            'Status' => $this->value($payload, 'invoice_status', 'Issued'),
        ]);
        $this->sectionHeading('01  INVOICE DETAILS');
        $sourceNumber = (string)($payload['source_document_number'] ?? $payload['quotation_number'] ?? '');
        if ($sourceNumber !== '') {
            $this->paragraph('Related accepted document: ' . $sourceNumber, 9, 'F2', [0.10, 0.14, 0.20], 14);
        }
        $currency = (string)($payload['currency'] ?? 'USD');
        $rows = [];
        foreach ((array)($payload['items'] ?? []) as $item) {
            if (!is_array($item)) continue;
            $rows[] = [
                (string)($item['description'] ?? ''),
                (string)($item['quantity'] ?? '1'),
                $this->money($currency, $item['total'] ?? 0),
            ];
        }
        if ((float)($payload['service_fee'] ?? 0) > 0) {
            $rows[] = ['Professional service fee', '1', $this->money($currency, $payload['service_fee'])];
        }
        if ((float)($payload['discount'] ?? 0) > 0) {
            $rows[] = ['Discount', '-', '-' . $this->money($currency, $payload['discount'])];
        }
        $this->table($rows);

        $this->financialSummary([
            'Invoice total' => $this->money($currency, $payload['total_amount'] ?? 0),
            'Amount paid' => $this->money($currency, $payload['amount_paid'] ?? 0),
            'Balance due' => $this->money($currency, $payload['balance_amount'] ?? 0),
        ]);

        $this->sectionHeading('02  PAYMENT INSTRUCTIONS');
        $payment = is_array($payload['payment_settings'] ?? null) ? $payload['payment_settings'] : [];
        $details = [];
        foreach ([
            'bank_name' => 'Bank',
            'account_name' => 'Account name',
            'account_number' => 'Account number',
            'branch' => 'Branch',
            'swift_iban' => 'SWIFT / IBAN',
            'currency' => 'Account currency',
        ] as $key => $label) {
            if (!empty($payment[$key])) $details[$label] = (string)$payment[$key];
        }
        if (!empty($payment['mpesa_number'])) {
            $details['M-Pesa'] = trim((string)($payment['mpesa_name'] ?? '') . ' ' . (string)$payment['mpesa_number']);
        }
        if ($details !== []) $this->keyValues($details);
        $this->paragraph($this->value($payment, 'instructions', 'Payment instructions will be provided separately.'));
        if (!empty($payment['payment_link'])) $this->paragraph('Secure payment link: ' . (string)$payment['payment_link'], 8.5, 'F2', [0.65, 0.47, 0.19], 13);

        $this->sectionHeading('03  THANK YOU');
        $this->paragraph($this->value($payload, 'invoice_note', 'Thank you for choosing Resplendent Global Travel Solutions.'));
    }

    private function newPage(): void
    {
        if ($this->content !== '') $this->pages[] = $this->content;
        $this->content = '';
        $this->y = 112.0;

        $this->image('Logo', self::LEFT, 36, 42, 42);
        $this->drawText('RESPLENDENT', 104, 50, 14, 'F3', [0.04, 0.13, 0.24]);
        $this->drawText('GLOBAL TRAVEL SOLUTIONS', 104, 67, 6.5, 'F2', [0.65, 0.47, 0.19]);
        $this->line(self::LEFT, 88, self::RIGHT, 88, [0.65, 0.47, 0.19], 0.8);

        $this->line(self::LEFT, 801, self::RIGHT, 801, [0.78, 0.72, 0.61], 0.45);
        $this->drawText('resplendentglobaltravel.com', self::LEFT, 817, 6.5, 'F1', [0.35, 0.38, 0.42]);
        $this->drawText('Precision / Discretion / Purpose', 382, 817, 6.5, 'F1', [0.35, 0.38, 0.42]);
    }

    private function finishPage(): void
    {
        if ($this->content !== '') {
            $this->pages[] = $this->content;
            $this->content = '';
        }
    }

    private function ensureSpace(float $needed): void
    {
        if ($this->y + $needed > self::CONTENT_BOTTOM) $this->newPage();
    }

    private function titleBlock(string $title, string $number, string $service, string $client, string $company): void
    {
        $this->drawText($title, self::LEFT, $this->y, 7.5, 'F2', [0.65, 0.47, 0.19]);
        $this->drawText($number, 400, $this->y, 8, 'F2', [0.35, 0.38, 0.42]);
        $this->y += 25;
        $this->drawText($service, self::LEFT, $this->y, 9, 'F2', [0.35, 0.38, 0.42]);
        $this->y += 24;
        $this->drawText($client, self::LEFT, $this->y, 25, 'F3', [0.04, 0.13, 0.24]);
        $this->y += 18;
        if ($company !== '') {
            $this->drawText('Prepared for ' . $company, self::LEFT, $this->y, 8, 'F1', [0.35, 0.38, 0.42]);
            $this->y += 14;
        }
        $this->y += 6;
    }

    /** @param array<string,string> $items */
    private function metaRow(array $items): void
    {
        $this->ensureSpace(44);
        $count = max(1, count($items));
        $width = (self::RIGHT - self::LEFT) / $count;
        $x = self::LEFT;
        foreach ($items as $label => $value) {
            $this->drawText(strtoupper($label), $x, $this->y, 6.2, 'F2', [0.65, 0.47, 0.19]);
            $this->drawText($value, $x, $this->y + 14, 8.2, 'F1', [0.10, 0.14, 0.20]);
            $x += $width;
        }
        $this->y += 38;
    }

    private function sectionHeading(string $title): void
    {
        $this->ensureSpace(38);
        $this->y += 8;
        $this->line(self::LEFT, $this->y, self::RIGHT, $this->y, [0.82, 0.79, 0.72], 0.45);
        $this->y += 20;
        $this->drawText($title, self::LEFT, $this->y, 8, 'F2', [0.65, 0.47, 0.19]);
        $this->y += 18;
    }

    /** @param array<string,string> $items */
    private function keyValues(array $items): void
    {
        foreach (array_chunk($items, 3, true) as $row) {
            $count = max(1, count($row));
            $columnWidth = (self::RIGHT - self::LEFT) / $count;
            $wrappedValues = [];
            $maxLines = 1;
            foreach ($row as $label => $value) {
                $wrappedValues[$label] = $this->wrap($value !== '' ? $value : 'To be confirmed', $columnWidth - 14, 8.5);
                $maxLines = max($maxLines, count($wrappedValues[$label]));
            }
            $height = 17 + $maxLines * 11;
            $this->ensureSpace($height + 6);
            $x = self::LEFT;
            foreach ($row as $label => $value) {
                $this->drawText(strtoupper($label), $x, $this->y, 6.2, 'F2', [0.65, 0.47, 0.19]);
                $lineY = $this->y + 14;
                foreach ($wrappedValues[$label] as $line) {
                    $this->drawText($line, $x, $lineY, 8.5, 'F1', [0.10, 0.14, 0.20]);
                    $lineY += 11;
                }
                $x += $columnWidth;
            }
            $this->y += $height + 5;
        }
    }

    private function paragraph(
        string $text,
        float $size = 9.2,
        string $font = 'F1',
        array $colour = [0.22, 0.25, 0.29],
        float $lineHeight = 14,
        float $width = 491
    ): void {
        $lines = $this->wrap($text, $width, $size);
        if ($lines === []) return;
        foreach ($lines as $line) {
            $this->ensureSpace($lineHeight + 2);
            $this->drawText($line, self::LEFT, $this->y, $size, $font, $colour);
            $this->y += $lineHeight;
        }
        $this->y += 4;
    }

    private function bullet(string $text): void
    {
        $lines = $this->wrap($text, 465, 9);
        foreach ($lines as $index => $line) {
            $this->ensureSpace(15);
            $this->drawText($index === 0 ? '-' : '', self::LEFT, $this->y, 9, 'F2', [0.65, 0.47, 0.19]);
            $this->drawText($line, self::LEFT + 14, $this->y, 9, 'F1', [0.22, 0.25, 0.29]);
            $this->y += 14;
        }
    }

    private function investmentBox(string $label, string $amount, string $note = ''): void
    {
        $noteLines = $note !== '' ? $this->wrap($note, 255, 8) : [];
        $height = max(64, 36 + count($noteLines) * 11);
        $this->ensureSpace($height + 12);
        $this->rect(self::LEFT, $this->y, self::RIGHT - self::LEFT, $height, [0.04, 0.13, 0.24]);
        $this->drawText($label, self::LEFT + 18, $this->y + 21, 6.5, 'F2', [0.78, 0.63, 0.36]);
        $this->drawText($amount, self::LEFT + 18, $this->y + 45, 18, 'F3', [1, 1, 1]);
        $noteY = $this->y + 24;
        foreach ($noteLines as $line) {
            $this->drawText($line, self::LEFT + 225, $noteY, 8, 'F1', [0.86, 0.88, 0.91]);
            $noteY += 11;
        }
        $this->y += $height + 8;
    }

    /** @param array<string,string> $items */
    private function financialSummary(array $items): void
    {
        $this->ensureSpace(70);
        $x = self::LEFT;
        $width = (self::RIGHT - self::LEFT) / max(1, count($items));
        foreach ($items as $label => $amount) {
            $this->drawText(strtoupper($label), $x, $this->y, 6.2, 'F2', [0.65, 0.47, 0.19]);
            $this->drawText($amount, $x, $this->y + 23, 13, 'F3', [0.04, 0.13, 0.24]);
            $x += $width;
        }
        $this->y += 54;
    }

    /** @param array<int,array{0:string,1:string,2:string}> $rows */
    private function table(array $rows): void
    {
        $this->ensureSpace(30);
        $this->rect(self::LEFT, $this->y, self::RIGHT - self::LEFT, 22, [0.96, 0.95, 0.92]);
        $this->drawText('DESCRIPTION', self::LEFT + 7, $this->y + 15, 6.5, 'F2', [0.35, 0.38, 0.42]);
        $this->drawText('QTY', 402, $this->y + 15, 6.5, 'F2', [0.35, 0.38, 0.42]);
        $this->drawText('AMOUNT', 462, $this->y + 15, 6.5, 'F2', [0.35, 0.38, 0.42]);
        $this->y += 28;

        foreach ($rows as $row) {
            $descriptionLines = $this->wrap($row[0], 330, 8.5);
            $height = max(25, count($descriptionLines) * 12 + 8);
            $this->ensureSpace($height + 2);
            $lineY = $this->y + 12;
            foreach ($descriptionLines as $line) {
                $this->drawText($line, self::LEFT + 7, $lineY, 8.5, 'F1', [0.15, 0.18, 0.22]);
                $lineY += 12;
            }
            $this->drawText($row[1], 405, $this->y + 12, 8.5, 'F1', [0.15, 0.18, 0.22]);
            $this->drawText($row[2], 462, $this->y + 12, 8.5, 'F2', [0.15, 0.18, 0.22]);
            $this->line(self::LEFT, $this->y + $height, self::RIGHT, $this->y + $height, [0.86, 0.84, 0.80], 0.35);
            $this->y += $height;
        }
        $this->y += 8;
    }

    /** @return string[] */
    private function wrap(string $text, float $width, float $size): array
    {
        $text = trim(preg_replace('/[ \t]+/', ' ', str_replace(["\r\n", "\r"], "\n", $text)) ?? '');
        if ($text === '') return [];
        $max = max(12, (int)floor($width / max(3.2, $size * 0.52)));
        $lines = [];
        foreach (explode("\n", $text) as $paragraph) {
            $wrapped = wordwrap(trim($paragraph), $max, "\n", true);
            foreach (explode("\n", $wrapped) as $line) {
                if ($line !== '') $lines[] = $line;
            }
        }
        return $lines;
    }

    private function value(array $values, string $key, string $fallback = 'To be confirmed'): string
    {
        $value = trim((string)($values[$key] ?? ''));
        return $value !== '' ? $value : $fallback;
    }

    private function money(string $currency, mixed $amount): string
    {
        return $currency . ' ' . number_format((float)$amount, 2);
    }

    private function displayDate(string $value): string
    {
        $timestamp = strtotime($value);
        return $timestamp !== false ? date('j F Y', $timestamp) : 'To be confirmed';
    }

    private function drawText(string $text, float $x, float $top, float $size, string $font, array $colour): void
    {
        $text = $this->pdfText($text);
        $r = (float)($colour[0] ?? 0);
        $g = (float)($colour[1] ?? 0);
        $b = (float)($colour[2] ?? 0);
        $pdfY = self::PAGE_HEIGHT - $top;
        $this->content .= sprintf(
            "BT /%s %.2F Tf %.3F %.3F %.3F rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $font,
            $size,
            $r,
            $g,
            $b,
            $x,
            $pdfY,
            $this->escape($text)
        );
    }

    private function line(float $x1, float $top1, float $x2, float $top2, array $colour, float $width): void
    {
        $this->content .= sprintf(
            "%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S\n",
            (float)$colour[0],
            (float)$colour[1],
            (float)$colour[2],
            $width,
            $x1,
            self::PAGE_HEIGHT - $top1,
            $x2,
            self::PAGE_HEIGHT - $top2
        );
    }

    private function rect(float $x, float $top, float $width, float $height, array $colour): void
    {
        $this->content .= sprintf(
            "%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n",
            (float)$colour[0],
            (float)$colour[1],
            (float)$colour[2],
            $x,
            self::PAGE_HEIGHT - $top - $height,
            $width,
            $height
        );
    }

    private function image(string $name, float $x, float $top, float $width, float $height): void
    {
        $this->content .= sprintf(
            "q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n",
            $width,
            $height,
            $x,
            self::PAGE_HEIGHT - $top - $height,
            $name
        );
    }

    private function pdfText(string $text): string
    {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
            if (is_string($converted)) return $converted;
        }
        return preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $text);
    }

    private function buildPdf(): string
    {
        $logo = file_get_contents($this->logoPath);
        if ($logo === false) throw new RuntimeException('The approved PDF logo could not be read.');
        $imageSize = getimagesize($this->logoPath);
        if (!is_array($imageSize)) throw new RuntimeException('The approved PDF logo is not a valid image.');

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman /Encoding /WinAnsiEncoding >>';
        $objects[6] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>';
        $objects[7] = sprintf(
            "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
            (int)$imageSize[0],
            (int)$imageSize[1],
            strlen($logo),
            $logo
        );

        $kids = [];
        $next = 8;
        foreach ($this->pages as $pageContent) {
            $contentId = $next++;
            $pageId = $next++;
            $objects[$contentId] = sprintf(
                "<< /Length %d >>\nstream\n%s\nendstream",
                strlen($pageContent),
                $pageContent
            );
            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R /F4 6 0 R >> /XObject << /Logo 7 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $contentId
            );
            $kids[] = $pageId . ' 0 R';
        }
        $objects[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $kids), count($kids));
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
        return $pdf;
    }
}
