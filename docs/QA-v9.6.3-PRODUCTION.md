# QA - v9.6.3 Production

## Automated verification

- PHP 8.3 integration flow passed.
- Proposal revision numbering passed through `R1` and `R2`.
- Pre-acceptance change request passed without creating an invoice.
- Post-acceptance change request passed and superseded the unsent draft.
- Latest-version acceptance created a linked draft invoice.
- A legacy accepted proposal created a linked draft through the authenticated,
  CSRF-protected backfill action.
- Repeating the legacy conversion returned the same invoice and created no
  duplicate.
- Draft invoice remained unsent until final review.
- Reviewed draft changed to **Issued** after recorded SMTP acceptance.
- Business Matchmaking acceptance created the USD 250 engagement draft.
- Bookings, Corporate, Business, Accounts and General sender resolution passed.
- Receiving-email verification and raw-token non-storage passed.
- Branded proposal, quotation and invoice PDF generation passed.
- Multipart MIME attachment construction passed.
- PHP, JavaScript, CSS, JSON, internal-reference and credential scans passed.
- Clean-package extraction and release-manifest verification passed.

## Live verification

Use `DEPLOYMENT-v9.6.3.md` after upload. Live Verpex SMTP sender authorisation
and external mailbox delivery must be confirmed on the deployed domain.
