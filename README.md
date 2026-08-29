# Resplendent Global Travel Solutions

**Deployment candidate: v11.6.5 — Clear Service Architecture & Connectivity Edition**

**Current production baseline: v11.5.4 — Global Business Connections Film Edition**

The v11.6.5 candidate simplifies the public journey to Home, Destinations,
Signature Journeys, Executive Mobility, eSIM & Connectivity, Journal and About. Corporate and
appropriate concierge support are consolidated under Executive Mobility;
Global Business Connections remains available from contextual and footer links.
The release adds a standalone eSIM page and correct enquiry routing, preserves
the approved films and imagery, redirects retired public routes, improves image
loading and updates search metadata and sitemap entries. Chinese, Japanese,
German, French and Italian landing pages gain a brighter premium hero plus
localized pathways into journeys, executive mobility, connectivity, editorial content
and the standalone Global Business Connections service.

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

Keep `/home/resplend/rgts-esimcard-config.php` outside `public_html`. Use
`docs/rgts-esimcard-config.example.php` as the template, select the sandbox
environment and add the credentials supplied directly by eSIMCard. The private
Operations screen at `/admin/esim-sandbox.php` can verify login, balance,
package retrieval and an explicitly enabled test purchase. Public checkout is
not enabled by this diagnostic integration.

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


## v11.8.0 — Instant Checkout & Workflow Edition (29 August 2026)
- Built from the authoritative VS Code working copy supplied on 29 August 2026.
- Adds isolated Instant Connectivity Checkout with Pesapal sandbox payment verification and simulated provisioning only.
- Preserves provider abstraction for Firsty without inventing undocumented API behavior.
- Reorders the eSIM journey: understand data needs, choose destination, compare plans, select plan, pre-travel checks, install eSIM.
- Strengthens accepted quotation → invoice recovery by reading acceptance deliveries when document summaries are stale.
- Keeps automatically generated linked invoices as Draft until reviewed and sent.
