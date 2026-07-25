# QA — v9.6.0 Production

## Automated checks

- JavaScript syntax validation across public and admin scripts.
- PHP parser validation across every PHP source file.
- HTML structure and local asset/link validation.
- JSON validation for manifest, web manifest and site data.
- Release inventory and SHA-256 manifest generation.
- ZIP integrity verification and clean extraction comparison.
- Security scan for packaged passwords, private configuration files and runtime client data.

## Functional checks

- Verified proposal mode switching and live calculation logic.
- Verified 5% travel service-fee calculation and total composition.
- Verified separate USD 500 Global Business Connections proposal.
- Verified quotation line-item, discount and service-fee calculations.
- Verified fixed USD 250 / 150 / 100 Business Matchmaking quotation.
- Verified automatic numbering formats in the operations store.
- Verified workflow creation/update integration in the form handler.
- Verified success redirect and enhanced confirmation-page reference handling.
- Verified private admin routes, CSRF checks, session expiry and first-time setup guard.

## Post-deployment verification required

The existing Zoho and SMTP flow is preserved, but third-party delivery and the
hosting account's write permissions can only be confirmed on Verpex. Follow
`DEPLOYMENT-v9.6.0.md` for the final live test.
