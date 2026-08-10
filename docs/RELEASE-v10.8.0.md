# Resplendent Global Travel Solutions v10.8.0

## Journey Investment & Payment-Ready Edition

Deployment candidate prepared: 10 August 2026  
Deployment target: Verpex `public_html`

### Public scope

- Adds one restrained Journey Investment section after the itinerary on each
  detailed Signature Journey page.
- Publishes the approved starting prices per person sharing: Across the
  Virungas USD 12,100, Masai Mara USD 7,350 and Watamu USD 2,400.
- Keeps prices out of hero areas, the homepage and the Signature Journeys
  overview.
- Adds matching TouristTrip Offer data for search engines.
- Adds the approved pricing, taxes, flight, payment, confirmation, cancellation
  and privacy terms.
- Keeps RTGS bank transfer as the preferred live payment method.

### Design lock

The four public stylesheets are byte-for-byte identical to the deployed v10.7
release. New public content reuses the established section, split, heading and
text-link components. No typography, colour, spacing, navigation, hero, image,
responsive rule or public form style is changed.

### Internal controls

- The quotation generator records an internal supplier cost per item and
  requires confirmation that the cost basis is complete.
- Travel and corporate quotations below `supplier cost / 0.72` are blocked on
  both the browser and server sides. This protects the 25% base margin after the
  3% payment/foreign-exchange allowance.
- Peak-season quotations must use current peak-season supplier costs before the
  protected formula is applied.
- Supplier costs and margin calculations do not appear in client documents.

### Payment readiness

- Payment settings default to RTGS and support optional online and contactless
  provider identifiers.
- A provider-neutral interface and coordinator allow future gateway adapters.
- Credentials remain outside `public_html`.
- An idempotent transaction ledger is ready for verified provider events and
  stores no full card data, security codes, PINs or raw gateway payloads.
- No online or contactless provider is activated in this release.
