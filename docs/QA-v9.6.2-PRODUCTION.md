# QA — v9.6.2 Production

## Automated gates

- JavaScript syntax checks for every public and admin script.
- PHP syntax parsing for every PHP source file.
- CSS parser validation.
- HTML, image, stylesheet, script and form-action reference checks.
- JSON validation.
- Generated PDF structural checks, text extraction and page rendering.
- Delivery and acceptance workflow fixture checks.
- Release-manifest SHA-256 verification.
- Clean ZIP extraction and byte-for-byte manifest comparison.
- Credential, client-data and suspicious-PHP primitive scan.

## Functional coverage

- Contact success redirects to inline confirmation rather than a separate page.
- Successful inline state changes the button to **Submitted** and shows the
  enquiry reference.
- Travel and business policy wording appears in previews and final documents.
- PDF generation covers proposal, quotation and invoice examples.
- Review confirmation is required before document delivery.
- SMTP builds one multipart message with the generated PDF attachment.
- Acceptance tokens are stored only as SHA-256 hashes.
- A newer send supersedes earlier outstanding acceptance links.
- Receiving-email verification, acceptance record and Approval workflow update.
- Invoice payment tracking and v9.6.1 generator regression checks.

## Live verification

Zoho, authenticated SMTP, Verpex permissions and receipt of the internal
acceptance notification must be confirmed after deployment using
`DEPLOYMENT-v9.6.2.md`.
