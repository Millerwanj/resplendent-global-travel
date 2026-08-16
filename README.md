# Resplendent Global Travel Solutions

**Deployment candidate: v10.9.0 — Corporate Search & Serengeti Edition**

**Current production release: v10.7.0 — SEO Visibility Edition**

Production website for `resplendentglobaltravel.com`.

## Public enquiry workflow

The Contact form retains the verified server-side pipeline:

1. validation and departmental routing;
2. Zoho CRM Web-to-Lead submission with HTTP/response diagnostics;
3. external SMTP configuration check;
4. authenticated SMTP delivery to the selected department and central copy;
5. customer acknowledgement and minimalist inline confirmation with the enquiry reference;
6. automatic entry in the private Client Workflow.

The approved English website, Google Analytics ID `G-5WEEFVG6MB`, operational
pipeline and premium minimalist design remain intact. The finalized Chinese
page at `/zh` and localized Japanese, German, French and Italian pages at
`/ja`, `/de`, `/fr` and `/it` retain reciprocal language selection and complete
multilingual search metadata.

The v10.9.0 candidate preserves the approved visual design and adds focused
Journey Investment section to each detailed Signature Journey page. Public
starting prices are USD 14,100 for Across the Virungas, USD 7,350 for the Masai
Mara, USD 2,400 for Watamu and USD 6,800 for the Serengeti, per person sharing
under the stated assumptions.
It also adds the approved tax, flight, payment and confirmation terms.

RTGS remains the preferred live payment method. The private operations layer is
provider-neutral: future online or contactless providers use a common adapter
contract, private external configuration and an idempotent transaction ledger.
No provider is represented as active before approval and production testing.

The private quotation policy protects a 25% base margin plus a 3% payment and
foreign-exchange reserve. Current peak-season supplier costs must still be
entered in full; the reserve is not a substitute for seasonal cost updates.

The Serengeti journey uses original provider-neutral imagery. The remaining
locally created imagery in `assets/images/signature-journeys/` is approved as
interim launch media and can be replaced later without changing the journey
routes or enquiry flow.

## Private Client Engagement Suite

Open `/admin/` after deployment to access:

- Executive Proposal Generator with travel, corporate and separate Global Business Connections modes;
- Executive Quotation Generator with itemised travel costs and the fixed USD 250 / 150 / 100 business-matchmaking schedule;
- Invoice Generator with quotation conversion, milestone invoicing, payment status and balance tracking;
- automatic proposal and quotation numbering;
- automatic `RGT-I-YYYY-000001` invoice numbering;
- automatic branded PDF generation and secure email attachment delivery;
- review-before-send control with editable cover email;
- service-aware sender selection for Bookings, Corporate Travel, Global Business Connections and Accounts;
- secure proposal and quotation response links with **Accept** and **Request changes** actions;
- controlled proposal and quotation revisions with `R1`, `R2` and later audit references;
- automatic draft invoice creation only after acceptance of the latest document version;
- mandatory review-before-send for every automatically prepared invoice;
- Client Workflow with enquiry capture, delivery, revision requests, acceptance, status changes, notes and document history;
- private Payment Settings with RTGS priority and optional provider-neutral
  online and contactless channels that can be updated without redeployment.

Use `ADMIN-SETUP.md` for the one-time private administrator setup.

## Required private mail configuration

Keep `/home/resplend/rgts-mail-config.php` outside `public_html`. Use `docs/rgts-mail-config.example.php` as the template. Never place the live mailbox password in this ZIP or GitHub.

Keep `/home/resplend/rgts-payment-config.php` outside `public_html`. Use
`docs/rgts-payment-config.example.php` only as the template for future provider
credentials. RTGS does not require this file.

## Private operational data

Admin configuration and client data are written outside `public_html`:

- `/home/resplend/rgts-admin-config.php`
- `/home/resplend/rgts-operations-data/`

The ZIP contains no client records, mailbox passwords or administrator password.
Each invoice stores a snapshot of the payment details active when it was issued;
later settings changes affect new invoices only.
Document delivery, revision-request and acceptance records are stored in the
same protected operations directory; raw response tokens and client IP
addresses are not stored.

## Pipeline logs

The handler writes logs to `/home/resplend/rgts-logs/pipeline-YYYY-MM-DD.log`. Search the file for the reference shown after form submission, for example `RGTS-20260724-AB12`.
