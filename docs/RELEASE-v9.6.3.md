# Release v9.6.3 - Controlled Approval Pipeline

Released: 26 July 2026

v9.6.3 closes the operational gap between client response and invoicing while
preserving human review at every client-facing send.

## Department senders

The document suite selects the sender from the document type:

- Luxury and leisure travel: `bookings@resplendentglobaltravel.com`
- Corporate Travel: `corporate@resplendentglobaltravel.com`
- Global Business Connections: `business@resplendentglobaltravel.com`
- Invoices: `accounts@resplendentglobaltravel.com`
- General fallback: `info@resplendentglobaltravel.com`

The suggested sender remains editable in the final review panel. Central
oversight continues through the configured `central_copy` address.

## Client response and revision control

Proposal and quotation response pages now provide **Accept Document** and
**Request changes instead**.

A revision request:

1. verifies the receiving email address;
2. records the request against the exact document hash;
3. moves the client to **Revision Requested**;
4. notifies the responsible department;
5. prevents the current version from being sent again; and
6. creates no invoice.

The administrator can create a prefilled controlled revision. Revisions retain
the original document number and append `R1`, `R2` and later suffixes. Older
sent links are superseded, while previous records remain available for audit.

## Acceptance and draft invoicing

Acceptance of the latest active proposal or quotation automatically prepares a
linked invoice in **Draft** status.

- Travel and corporate drafts mirror the accepted line items, discount,
  service fee and total.
- A Business Matchmaking acceptance prepares the first USD 250 engagement
  milestone invoice.
- The accepted PDF SHA-256 hash is retained on the draft invoice relationship.
- The draft cannot reach the client until an administrator reviews and sends
  it.
- A reviewed draft becomes **Issued** only after SMTP accepts the email.
- A later change request supersedes any unsent automatic draft.
- Older accepted proposals and quotations that predate v9.6.3 display a
  controlled **Create Linked Draft Invoice** action. The original acceptance
  record is preserved, duplicate invoices are prevented and the resulting
  invoice remains unsent until review.

## Compatibility

The release is cumulative and can overwrite v9.6.2 files. Existing private
administrator configuration, mail configuration, payment settings, client
records, documents and delivery records remain compatible. Legacy acceptance
records are upgraded only when an administrator deliberately creates their
linked draft invoice; no bulk invoice migration runs during deployment.
