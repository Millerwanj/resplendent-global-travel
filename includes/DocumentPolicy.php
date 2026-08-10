<?php
declare(strict_types=1);

/**
 * Approved client-facing commercial wording.
 *
 * Keep these terms central so the browser preview, generated PDF, delivery
 * record and acceptance page all present the same language.
 */
final class DocumentPolicy
{
    public const VERSION = '2026-08-10';

    public const BUSINESS_NON_REFUNDABLE = 'Non-refundable professional fees: Each milestone payment becomes non-refundable once the corresponding work has commenced, as it covers research, due diligence, outreach, coordination and professional time committed to the engagement. This does not affect any rights available where agreed services are not delivered or applicable law requires otherwise.';

    public const BUSINESS_OUTCOME = 'Payment of professional fees does not guarantee a successful introduction, transaction or commercial outcome.';

    public const TRAVEL_AVAILABILITY = 'Airfares, accommodation rates, taxes, availability and supplier conditions are indicative at the time of enquiry and may change until the required payment is received and the service is ticketed or formally confirmed in writing.';

    public const TRAVEL_TAXES = 'Unless expressly stated otherwise, the client total includes applicable taxes, statutory charges and Resplendent service fees. VAT is included only where it is legally applicable to the relevant supply.';

    public const TRAVEL_FLIGHTS = 'Domestic flights identified as included form part of the package total. International flights are quoted separately unless expressly included. Airline fares, taxes, baggage allowances and fare rules remain subject to change until ticketed; flight components may require full immediate payment and may be non-refundable or subject to change penalties under the airline rules disclosed in the proposal.';

    public const TRAVEL_PAYMENT_CONFIRMATION = 'RTGS bank transfer is the preferred payment method unless the invoice offers another authorised channel. A payment receipt confirms funds received only; supplier space, permits, services and tickets are confirmed separately in writing after the relevant supplier and ticketing checks are complete.';

    /** @param array<string,mixed> $document @return string[] */
    public static function termsForDocument(array $document): array
    {
        $payload = is_array($document['payload'] ?? null) ? $document['payload'] : [];
        $type = (string)($document['type'] ?? '');
        $serviceType = match ($type) {
            'proposal' => (string)($payload['proposal_type'] ?? ''),
            'quotation' => (string)($payload['quotation_type'] ?? ''),
            'invoice' => (string)($payload['invoice_type'] ?? ''),
            default => '',
        };

        if (in_array($serviceType, ['Global Business Connections', 'Business Matchmaking'], true)) {
            return [self::BUSINESS_NON_REFUNDABLE, self::BUSINESS_OUTCOME];
        }

        return [
            self::TRAVEL_AVAILABILITY,
            self::TRAVEL_TAXES,
            self::TRAVEL_FLIGHTS,
            self::TRAVEL_PAYMENT_CONFIRMATION,
        ];
    }

    /** @param array<string,mixed> $document @return array{version:string,terms:string[]} */
    public static function snapshot(array $document): array
    {
        return [
            'version' => self::VERSION,
            'terms' => self::termsForDocument($document),
        ];
    }
}
