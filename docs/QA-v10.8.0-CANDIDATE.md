# Candidate QA — v10.8.0

## Automated results — 10 August 2026

- 31 sitemap URLs checked; every route maps to a release source file.
- 35 HTML files checked; zero metadata, canonical, H1, JSON-LD, local-link or
  local-asset errors.
- All three detailed Signature Journey pages contain the approved visible
  starting price and matching USD Offer data after the itinerary.
- The homepage and Signature Journeys overview contain none of the three public
  starting prices.
- Public pages contain no supplier-cost formula, margin percentage or internal
  pricing language.
- The quotation generator uses `supplier cost / 0.72`, protecting a 25% base
  margin plus the separate 3% payment and foreign-exchange reserve.
- Terms checks confirm taxes, domestic/international flight treatment, RTGS
  priority, payment-versus-confirmation wording and cancellation conditions.
- Payment checks confirm provider-neutral configuration, an adapter contract,
  server-side verification guidance and idempotent ledger fields.
- The payment page has no provider-specific public claim and keeps RTGS first.
- The quotation script passes JavaScript syntax validation.
- 26 PHP files pass independent PHP AST parsing with zero syntax errors. A
  native PHP runtime was not installed in the packaging workspace.
- `git diff --check` passes with zero whitespace errors.

## Design preservation

SHA-256 comparison against the deployed v10.7 ZIP confirms these public files
are byte-for-byte identical:

- `styles.css`
- `assets/css/main.css`
- `assets/css/signature-journeys.css`
- `assets/css/international.css`

The new price sections use existing public components. Only the private admin
stylesheet changes, to accommodate the supplier-cost and margin-floor controls.

Status: **PASS — ready for Verpex deployment packaging.**
