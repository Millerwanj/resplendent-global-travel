# QA — v9.6.1 Production

## Automated production gates

- JavaScript syntax checks for every public and admin script.
- PHP 8.3 parsing and execution tests for the operations store.
- CSS parser validation.
- Local page, asset, form-action and navigation reference checks.
- JSON validation and SHA-256 release-manifest verification.
- Clean ZIP extraction and byte-for-byte manifest comparison.
- Security scan for private configuration, client data and packaged credentials.

## Functional checks

- Quotation-to-invoice conversion data path.
- Travel invoice line items, discount, service fee, total and balance.
- Business invoice modes: USD 250 / USD 150 / USD 100 / USD 500.
- `RGT-I-YYYY-000001` numbering.
- Issued, Part-paid, Paid and Overdue status calculations.
- Payment-position update and Client Workflow movement.
- Editable Payment Settings and immutable invoice snapshot behaviour.
- Proposal, quotation, workflow and Enhanced Success Page regression checks.

## Live verification

Third-party Zoho and SMTP delivery and Verpex filesystem permissions must be
confirmed after deployment using `DEPLOYMENT-v9.6.1.md`.
